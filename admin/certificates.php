<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_auth();

$pdo = get_db();

// ── XLSX parser (zero-dependency: ZipArchive + SimpleXML) ─────────────────────
function parse_xlsx(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];

    $sheetXml   = $zip->getFromName('xl/worksheets/sheet1.xml');
    $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();

    if ($sheetXml === false) return [];

    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    // Build shared strings table (handles default-namespace XLSX files)
    $strings = [];
    if ($stringsXml !== false) {
        $sst = new SimpleXMLElement($stringsXml);
        foreach ($sst->children($ns) as $si) {
            $text = '';
            foreach ($si->children($ns) as $t) {
                $text .= (string) $t;
            }
            $strings[] = $text;
        }
    }

    $sheet = new SimpleXMLElement($sheetXml);
    $sheet->registerXPathNamespace('s', $ns);

    $rows = [];
    foreach ($sheet->xpath('//s:row') as $row) {
        $cells = [];
        foreach ($row->children($ns) as $cell) {
            $type  = (string) ($cell->attributes()['t'] ?? '');
            $v     = (string) ($cell->v ?? '');
            $value = ($type === 's' && isset($strings[(int) $v])) ? $strings[(int) $v] : $v;
            $cells[] = $value;
        }
        $rows[] = $cells;
    }
    return $rows;
}

// ── Demo XLSX download ────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'demo_xlsx') {
    $file = __DIR__ . '/demo-import.xlsx';
    if (!file_exists($file)) {
        http_response_code(404); exit('Demo file not found.');
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="demo-import.xlsx"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Security token mismatch.');
        redirect(BASE_PATH . '/admin/certificates.php');
    }

    $action = $_POST['action'] ?? '';

    // Single add
    if ($action === 'add') {
        $num       = strtoupper(trim($_POST['cert_number'] ?? ''));
        $name      = trim($_POST['holder_name'] ?? '');
        $projectId = (int) ($_POST['project_id'] ?? 0);

        if ($num === '' || $name === '' || $projectId === 0) {
            flash('error', 'All fields are required.');
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO certificates (cert_number, holder_name, project_id) VALUES (:n, :h, :p)'
                )->execute([':n' => $num, ':h' => $name, ':p' => $projectId]);
                flash('success', "Certificate $num added.");
            } catch (PDOException $e) {
                if (str_starts_with($e->getCode(), '23')) {
                    flash('error', "Certificate number \"$num\" already exists in the database.");
                } else {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
        $qs = $projectId ? "?project_id=$projectId" : '';
        redirect(BASE_PATH . '/admin/certificates.php' . $qs);
    }

    // Delete single
    if ($action === 'delete') {
        $id        = (int) ($_POST['id'] ?? 0);
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $pdo->prepare('DELETE FROM certificates WHERE id = :id')->execute([':id' => $id]);
        flash('success', 'Certificate deleted.');
        $qs = $projectId ? "?project_id=$projectId" : '';
        redirect(BASE_PATH . '/admin/certificates.php' . $qs);
    }

    // Bulk import
    if ($action === 'bulk') {
        $projectId = (int) ($_POST['bulk_project_id'] ?? 0);
        if ($projectId === 0) {
            flash('error', 'Please select a project for bulk import.');
            redirect(BASE_PATH . '/admin/certificates.php');
        }

        $rows = [];

        // File upload takes priority over textarea
        if (!empty($_FILES['csv_file']['tmp_name']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmp      = $_FILES['csv_file']['tmp_name'];
            $origName = strtolower($_FILES['csv_file']['name']);

            if (str_ends_with($origName, '.xlsx')) {
                $parsed = parse_xlsx($tmp);
                foreach ($parsed as $r) {
                    $rows[] = [(string)($r[0] ?? ''), (string)($r[1] ?? '')];
                }
            } else {
                // CSV file
                $handle = fopen($tmp, 'r');
                while (($r = fgetcsv($handle)) !== false) {
                    $rows[] = [(string)($r[0] ?? ''), (string)($r[1] ?? '')];
                }
                fclose($handle);
            }
        } elseif (!empty($_POST['csv_text'])) {
            foreach (explode("\n", $_POST['csv_text']) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $r = str_getcsv($line);
                $rows[] = [(string)($r[0] ?? ''), (string)($r[1] ?? '')];
            }
        }

        if (empty($rows)) {
            flash('error', 'No data found to import.');
            redirect(BASE_PATH . '/admin/certificates.php?project_id=' . $projectId);
        }

        // Skip header row if first cell looks like a header
        $firstCell = strtoupper(trim($rows[0][0] ?? ''));
        if (in_array($firstCell, ['CERT_NUMBER', 'CERT NO', 'CERT NUMBER', 'CERTIFICATE NUMBER', 'CERTIFICATE NO', 'CERT', 'ID'], true)) {
            array_shift($rows);
        }

        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO certificates (cert_number, holder_name, project_id) VALUES (:n, :h, :p)'
        );

        $pdo->beginTransaction();
        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        foreach ($rows as $i => $r) {
            $num  = strtoupper(trim($r[0]));
            $name = trim($r[1]);
            if ($num === '' || $name === '') {
                $skipped++;
                continue;
            }
            $stmt->execute([':n' => $num, ':h' => $name, ':p' => $projectId]);
            if ($stmt->rowCount() > 0) {
                $imported++;
            } else {
                $skipped++;
            }
        }
        $pdo->commit();

        flash('success', "Bulk import complete. Imported: $imported. Duplicates/skipped: $skipped.");
        redirect(BASE_PATH . '/admin/certificates.php?project_id=' . $projectId);
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$filterProject = (int) ($_GET['project_id'] ?? 0);
$search        = trim($_GET['q'] ?? '');

$projects = $pdo->query('SELECT id, name FROM projects ORDER BY name')->fetchAll();

$params = [':pid' => $filterProject, ':q' => $search ? "%$search%" : ''];
$certs  = $pdo->prepare(
    'SELECT c.id, c.cert_number, c.holder_name, c.created_at, p.name AS project_name, c.project_id
     FROM certificates c
     JOIN projects p ON p.id = c.project_id
     WHERE (:pid = 0 OR c.project_id = :pid)
       AND (:q = \'\' OR c.cert_number LIKE :q OR c.holder_name LIKE :q)
     ORDER BY c.created_at DESC
     LIMIT 300'
);
$certs->execute($params);
$certs = $certs->fetchAll();

$totalCount = (int) $pdo->query('SELECT COUNT(*) FROM certificates')->fetchColumn();
$flashes    = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Certificates · UBU Admin</title>
  <style><?php include __DIR__ . '/shared.css.php'; ?></style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="page">
  <div class="page-header">
    <h1>Certificates <span class="badge"><?= $totalCount ?> total</span></h1>
  </div>

  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start;">

    <!-- Left: list + filters -->
    <div>
      <!-- Filter bar -->
      <form method="GET" action="certificates.php" style="display:flex;gap:8px;margin-bottom:1rem;flex-wrap:wrap;">
        <select name="project_id" style="border:1px solid #d0d8d0;border-radius:8px;padding:8px 13px;font-size:13px;outline:none;background:#fff;">
          <option value="0">All Projects</option>
          <?php foreach ($projects as $proj): ?>
            <option value="<?= (int) $proj['id'] ?>" <?= $filterProject === (int) $proj['id'] ? 'selected' : '' ?>>
              <?= e($proj['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= e($search) ?>"
               placeholder="Search name or cert number…"
               style="flex:1;border:1px solid #d0d8d0;border-radius:8px;padding:8px 13px;font-size:13px;outline:none;min-width:160px;">
        <button type="submit" class="btn">Filter</button>
        <?php if ($filterProject || $search): ?>
          <a href="certificates.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <div class="card">
        <div class="card-header">
          <h2><?= count($certs) ?> certificate(s) shown</h2>
        </div>
        <?php if (empty($certs)): ?>
          <p class="empty">No certificates match your filters.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Reference No</th>
                <th>Holder Name</th>
                <th>Project</th>
                <th>Added</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($certs as $c): ?>
              <?php $verifyUrl = BASE_PATH . '/?id=' . rawurlencode($c['cert_number']); ?>
              <tr>
                <td><code><?= e($c['cert_number']) ?></code></td>
                <td><?= e($c['holder_name']) ?></td>
                <td style="font-size:11px;color:#6b7a6b"><?= e($c['project_name']) ?></td>
                <td style="font-size:11px;color:#9aaa9a"><?= e(substr($c['created_at'], 0, 10)) ?></td>
                <td style="white-space:nowrap;display:flex;gap:4px;align-items:center;">
                  <a href="<?= e($verifyUrl) ?>" target="_blank" class="btn btn-sm btn-outline" title="Open verify page">View</a>
                  <button type="button" class="btn btn-sm btn-secondary"
                          onclick="copyLink('<?= e($verifyUrl) ?>', this)"
                          title="Copy verify link">Copy Link</button>
                  <form method="POST" action="certificates.php"
                        onsubmit="return confirm('Delete <?= e($c['cert_number']) ?>?')"
                        style="display:inline;margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <input type="hidden" name="project_id" value="<?= (int) $c['project_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Del</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: add + bulk import -->
    <div>

      <!-- Single add -->
      <div class="card" style="margin-bottom:1rem;">
        <div class="card-header"><h2>Add Certificate</h2></div>
        <div class="card-body">
          <form method="POST" action="certificates.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="form-group">
              <label>Project *</label>
              <select name="project_id" required>
                <option value="">Select project…</option>
                <?php foreach ($projects as $proj): ?>
                  <option value="<?= (int) $proj['id'] ?>" <?= $filterProject === (int) $proj['id'] ? 'selected' : '' ?>>
                    <?= e($proj['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Certificate Number *</label>
              <input type="text" name="cert_number" placeholder="UBU/E&amp;S/2026/032"
                     style="text-transform:uppercase;" required>
            </div>
            <div class="form-group">
              <label>Holder Name *</label>
              <input type="text" name="holder_name" placeholder="SURNAME Firstname Middle" required>
            </div>
            <button type="submit" class="btn" style="width:100%">Add Certificate</button>
          </form>
        </div>
      </div>

      <!-- Bulk import -->
      <div class="card">
        <div class="card-header">
          <h2>Bulk Import</h2>
          <a href="certificates.php?action=demo_xlsx" class="btn btn-sm btn-outline" download>Download Demo .xlsx</a>
        </div>
        <div class="card-body">
          <form method="POST" action="certificates.php" enctype="multipart/form-data" id="bulkForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk">

            <div class="form-group">
              <label>Project *</label>
              <select name="bulk_project_id" required>
                <option value="">Select project…</option>
                <?php foreach ($projects as $proj): ?>
                  <option value="<?= (int) $proj['id'] ?>" <?= $filterProject === (int) $proj['id'] ? 'selected' : '' ?>>
                    <?= e($proj['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- File upload -->
            <div class="form-group">
              <label>Upload File (.csv or .xlsx)</label>
              <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6b7a6b" stroke-width="1.5">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="17 8 12 3 7 8"/>
                  <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <p id="fileName">Click to upload or drag &amp; drop</p>
                <p style="font-size:11px;color:#9aaa9a;margin-top:4px;">Supports .xlsx and .csv files</p>
                <input type="file" id="fileInput" name="csv_file" accept=".csv,.xlsx">
              </div>
            </div>

            <div style="text-align:center;font-size:12px;color:#9aaa9a;margin:0.75rem 0;">— or paste CSV below —</div>

            <!-- CSV paste -->
            <div class="form-group">
              <label>Paste CSV / Excel data</label>
              <textarea name="csv_text" rows="6"
                        placeholder="cert_number,holder_name&#10;UBU/E&amp;S/2026/032,SURNAME Firstname&#10;UBU/E&amp;S/2026/033,SURNAME Firstname"></textarea>
              <p class="form-hint">First row can be a header — it will be skipped automatically.</p>
            </div>

            <button type="submit" class="btn" style="width:100%">Import</button>
          </form>
          <div style="margin-top:1rem;padding:10px;background:#fafbfa;border-radius:8px;border:1px solid #eaeeea;">
            <p style="font-size:11px;color:#6b7a6b;font-weight:600;margin-bottom:4px;">Expected format:</p>
            <code style="font-size:11px;line-height:1.7;display:block;">
              cert_number,holder_name<br>
              UBU/E&amp;S/2026/032,SURNAME Firstname<br>
              UBU/E&amp;S/2026/033,SURNAME Firstname
            </code>
            <p style="font-size:11px;color:#9aaa9a;margin-top:6px;">Duplicates are skipped automatically. Column A = cert number, Column B = name.</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function copyLink(path, btn) {
  const url = window.location.protocol + '//' + window.location.host + path;
  navigator.clipboard.writeText(url).then(() => {
    const orig = btn.textContent;
    btn.textContent = 'Copied!';
    btn.style.background = '#2d7a3a';
    btn.style.color = '#fff';
    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; btn.style.color = ''; }, 1800);
  });
}

document.getElementById('fileInput').addEventListener('change', function() {
  const name = this.files[0] ? this.files[0].name : 'Click to upload or drag & drop';
  document.getElementById('fileName').textContent = name;
});

// Drag & drop on upload area
const area = document.querySelector('.upload-area');
area.addEventListener('dragover', e => { e.preventDefault(); area.style.borderColor = '#2d7a3a'; });
area.addEventListener('dragleave', () => { area.style.borderColor = ''; });
area.addEventListener('drop', e => {
  e.preventDefault();
  area.style.borderColor = '';
  const file = e.dataTransfer.files[0];
  if (file) {
    document.getElementById('fileInput').files = e.dataTransfer.files;
    document.getElementById('fileName').textContent = file.name;
  }
});
</script>
</body>
</html>
