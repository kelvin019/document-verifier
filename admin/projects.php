<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_auth();

$pdo    = get_db();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$editId = (int) ($_GET['edit'] ?? 0);

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Security token mismatch.');
        redirect(BASE_PATH . '/admin/projects.php');
    }

    $validTypes = ['Certificate', 'ID Card', 'Document', 'Badge', 'Permit', 'License', 'Other'];

    if ($action === 'create') {
        $name   = trim($_POST['name'] ?? '');
        $type   = in_array($_POST['type'] ?? '', $validTypes, true) ? $_POST['type'] : 'Certificate';
        $theme  = trim($_POST['theme'] ?? '');
        $date   = trim($_POST['date'] ?? '');
        $venue  = trim($_POST['venue'] ?? '');
        $issuer = trim($_POST['issuer'] ?? '');
        $prefix = trim($_POST['cert_prefix'] ?? '');

        if ($name === '' || $prefix === '') {
            flash('error', 'Project name and ID prefix are required.');
        } else {
            $pdo->prepare(
                'INSERT INTO projects (name, type, theme, date, venue, issuer, cert_prefix)
                 VALUES (:n, :ty, :t, :d, :v, :i, :p)'
            )->execute([':n' => $name, ':ty' => $type, ':t' => $theme, ':d' => $date, ':v' => $venue, ':i' => $issuer, ':p' => $prefix]);
            flash('success', "Project \"$name\" created.");
        }
        redirect(BASE_PATH . '/admin/projects.php');
    }

    if ($action === 'save') {
        $id     = (int) ($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $type   = in_array($_POST['type'] ?? '', $validTypes, true) ? $_POST['type'] : 'Certificate';
        $theme  = trim($_POST['theme'] ?? '');
        $date   = trim($_POST['date'] ?? '');
        $venue  = trim($_POST['venue'] ?? '');
        $issuer = trim($_POST['issuer'] ?? '');
        $prefix = trim($_POST['cert_prefix'] ?? '');

        if ($name === '' || $prefix === '') {
            flash('error', 'Project name and ID prefix are required.');
            redirect(BASE_PATH . '/admin/projects.php?edit=' . $id);
        }

        $pdo->prepare(
            'UPDATE projects SET name=:n, type=:ty, theme=:t, date=:d, venue=:v, issuer=:i, cert_prefix=:p WHERE id=:id'
        )->execute([':n' => $name, ':ty' => $type, ':t' => $theme, ':d' => $date, ':v' => $venue, ':i' => $issuer, ':p' => $prefix, ':id' => $id]);
        flash('success', "Project updated.");
        redirect(BASE_PATH . '/admin/projects.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!empty($_POST['confirm_delete'])) {
            $pdo->prepare('DELETE FROM projects WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Project and all its certificates deleted.');
            redirect(BASE_PATH . '/admin/projects.php');
        } else {
            flash('error', 'Deletion cancelled (confirmation not checked).');
            redirect(BASE_PATH . '/admin/projects.php');
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$projects = $pdo->query(
    'SELECT p.*, COUNT(c.id) AS cert_count
     FROM projects p
     LEFT JOIN certificates c ON c.project_id = p.id
     GROUP BY p.id
     ORDER BY p.created_at DESC'
)->fetchAll();

$editProject = null;
if ($editId > 0) {
    $s = $pdo->prepare('SELECT * FROM projects WHERE id = :id LIMIT 1');
    $s->execute([':id' => $editId]);
    $editProject = $s->fetch();
}

$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Projects · UBU Admin</title>
  <style><?php include __DIR__ . '/shared.css.php'; ?></style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="page">
  <div class="page-header">
    <h1>Projects</h1>
  </div>

  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start;">

    <!-- Project list -->
    <div class="card">
      <div class="card-header"><h2>All Projects</h2></div>
      <?php if (empty($projects)): ?>
        <p class="empty">No projects yet. Create one using the form.</p>
      <?php else: ?>
        <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Prefix</th>
              <th>Certs</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $p): ?>
            <tr>
              <td>
                <strong><?= e($p['name']) ?></strong>
                <?php if ($p['date']): ?><br><span style="font-size:11px;color:#6b7a6b"><?= e($p['date']) ?></span><?php endif; ?>
              </td>
              <td><span class="badge"><?= e($p['type'] ?? 'Certificate') ?></span></td>
              <td><code><?= e($p['cert_prefix']) ?></code></td>
              <td><span class="badge"><?= (int) $p['cert_count'] ?></span></td>
              <td class="actions">
                <a href="?edit=<?= (int) $p['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                <a href="certificates.php?project_id=<?= (int) $p['id'] ?>" class="btn btn-sm">Certs</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Create / Edit form -->
    <div>
      <div class="card">
        <div class="card-header">
          <h2><?= $editProject ? 'Edit Project' : 'New Project' ?></h2>
          <?php if ($editProject): ?>
            <a href="projects.php" class="btn btn-sm btn-secondary">Cancel</a>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <form method="POST" action="projects.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editProject ? 'save' : 'create' ?>">
            <?php if ($editProject): ?>
              <input type="hidden" name="id" value="<?= (int) $editProject['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
              <label>Project / Workshop Name *</label>
              <input type="text" name="name" value="<?= e($editProject['name'] ?? '') ?>" placeholder="E&amp;S Safeguards Workshop 2026" required>
            </div>
            <div class="form-group">
              <label>Document Type *</label>
              <select name="type" required>
                <?php
                $types = ['Certificate', 'ID Card', 'Document', 'Badge', 'Permit', 'License', 'Other'];
                $current_type = $editProject['type'] ?? 'Certificate';
                foreach ($types as $t):
                ?>
                  <option value="<?= e($t) ?>" <?= $current_type === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="form-hint">What kind of document does this project issue?</p>
            </div>
            <div class="form-group">
              <label>ID / Reference Prefix *</label>
              <input type="text" name="cert_prefix" value="<?= e($editProject['cert_prefix'] ?? '') ?>" placeholder="UBU/E&amp;S/2026">
              <p class="form-hint">The common prefix for all IDs in this project.</p>
            </div>
            <div class="form-group">
              <label>Theme / Description</label>
              <textarea name="theme" placeholder="Workshop theme or description..."><?= e($editProject['theme'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
              <label>Date</label>
              <input type="text" name="date" value="<?= e($editProject['date'] ?? '') ?>" placeholder="25th – 28th May, 2026">
            </div>
            <div class="form-group">
              <label>Venue</label>
              <input type="text" name="venue" value="<?= e($editProject['venue'] ?? '') ?>" placeholder="Rosmohr Gold Hotel, Uyo…">
            </div>
            <div class="form-group">
              <label>Issuer</label>
              <input type="text" name="issuer" value="<?= e($editProject['issuer'] ?? '') ?>" placeholder="Dr Bassey Uzodinma, CEO, UBU Office Limited">
            </div>

            <button type="submit" class="btn" style="width:100%">
              <?= $editProject ? 'Save Changes' : 'Create Project' ?>
            </button>
          </form>
        </div>
      </div>

      <?php if ($editProject): ?>
      <!-- Delete section -->
      <div class="card" style="border-color:#f0b0b0;">
        <div class="card-header"><h2 style="color:#a03030">Delete Project</h2></div>
        <div class="card-body">
          <p style="font-size:13px;color:#7a3030;margin-bottom:1rem;">
            This will permanently delete <strong><?= e($editProject['name']) ?></strong>
            and all <strong><?= (int) ($editProject['cert_count'] ?? 0) ?> certificate(s)</strong> linked to it.
          </p>
          <form method="POST" action="projects.php" onsubmit="return confirm('Are you sure? This cannot be undone.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $editProject['id'] ?>">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:1rem;cursor:pointer;">
              <input type="checkbox" name="confirm_delete" value="1" required>
              I understand this will delete all certificates in this project
            </label>
            <button type="submit" class="btn btn-danger" style="width:100%">Delete Project</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>
</body>
</html>
