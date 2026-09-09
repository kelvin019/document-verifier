<?php
declare(strict_types=1);

// Reconstruct the full ID from the raw query string.
// When a URL like ?id=UBU/E&S/2026/001 is used without encoding the &,
// PHP splits it into $_GET['id']='UBU/E' and $_GET['S/2026/001']=''.
// We rebuild the original value by reading the raw QUERY_STRING instead.
$rawQuery = $_SERVER['QUERY_STRING'] ?? '';
$autoId   = '';
if (preg_match('/(?:^|&)id=([^&]*)/', $rawQuery, $m)) {
    // URL-decode just this segment, then uppercase
    $autoId = strtoupper(urldecode($m[1]));
}
// Fallback: also accept remaining keys to handle bare & as separator
// e.g. ?id=UBU/E&S/2026/001 → keys: id=UBU/E, S/2026/001=''
if ($autoId === '' && isset($_GET['id'])) {
    $autoId = strtoupper(trim($_GET['id']));
}
// If id got cut at & but remaining GET keys look like the rest of the ID, rejoin
if ($autoId !== '' && !str_contains($autoId, '&')) {
    foreach (array_keys($_GET) as $k) {
        if ($k === 'id') continue;
        // Key looks like the continuation (e.g. "S/2026/001")
        if (preg_match('/^[A-Z0-9\/.\-_]+$/i', $k)) {
            $candidate = strtoupper($autoId . '&' . $k);
            $autoId    = $candidate;
            break;
        }
    }
}

$autoData = null;

if ($autoId !== '') {
    require_once __DIR__ . '/db.php';
    $stmt = get_db()->prepare(
        'SELECT c.cert_number, c.holder_name,
                p.name AS workshop, p.type, p.theme, p.date, p.venue, p.issuer
         FROM certificates c
         JOIN projects p ON p.id = c.project_id
         WHERE c.cert_number = :num
         LIMIT 1'
    );
    $stmt->execute([':num' => $autoId]);
    $row = $stmt->fetch();

    if ($row) {
        $autoData = [
            'found'    => true,
            'cert_no'  => $row['cert_number'],
            'name'     => $row['holder_name'],
            'type'     => $row['type'],
            'workshop' => $row['workshop'],
            'event'    => $row['theme'],
            'date'     => $row['date'],
            'venue'    => $row['venue'],
            'issuer'   => $row['issuer'],
        ];
    } else {
        $autoData = [
            'found' => false,
            'error' => 'The ID "' . htmlspecialchars($autoId, ENT_QUOTES, 'UTF-8')
                     . '" does not match any record in our database.',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>UBU Document Verifier</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f4f6f4;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    .container { width: 100%; max-width: 540px; }
    .header {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 2rem;
      background: #fff;
      border-radius: 14px;
      border: 1px solid #e2e8e2;
      padding: 1.25rem 1.5rem;
    }
    .logo {
      width: 50px; height: 50px;
      border-radius: 10px;
      background: #1a4a0d;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .logo span { color: #7dd3aa; font-weight: 700; font-size: 13px; letter-spacing: 0.5px; }
    .header-text h1 { font-size: 16px; font-weight: 600; color: #1a1a1a; }
    .header-text p { font-size: 12px; color: #6b7a6b; margin-top: 2px; }

    .card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid #e2e8e2;
      padding: 2rem;
    }
    .card h2 { font-size: 15px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
    .card .sub { font-size: 13px; color: #6b7a6b; margin-bottom: 1.5rem; }

    .input-group { display: flex; gap: 8px; margin-bottom: 8px; }
    .input-group input {
      flex: 1;
      border: 1px solid #d0d8d0;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 14px;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: #1a1a1a;
      outline: none;
      transition: border-color 0.2s;
    }
    .input-group input:focus { border-color: #2d7a3a; }
    .input-group button {
      background: #1a4a0d;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      white-space: nowrap;
      transition: background 0.2s;
    }
    .input-group button:hover { background: #2d6b18; }
    .hint { font-size: 11px; color: #9aaa9a; margin-bottom: 1.5rem; }

    .result { border-radius: 10px; padding: 1.25rem; display: none; margin-top: 0.5rem; }
    .result.success { background: #f0faf3; border: 1px solid #a8d8b0; }
    .result.error   { background: #fff4f4; border: 1px solid #f0b0b0; }

    .result-status {
      display: flex; align-items: center; gap: 8px;
      font-size: 15px; font-weight: 600; margin-bottom: 10px;
    }
    .result-status svg { width: 20px; height: 20px; flex-shrink: 0; }
    .result.success .result-status { color: #1a6b30; }
    .result.error   .result-status { color: #a03030; }

    .result-name { font-size: 20px; font-weight: 700; color: #1a1a1a; margin-bottom: 10px; }
    .result-meta { font-size: 12px; color: #4a6a4a; line-height: 1.8; }
    .result-meta strong { color: #1a3a1a; }
    .error-msg { font-size: 13px; color: #7a3030; }

    .divider { border: none; border-top: 1px solid #eaeeea; margin: 1.5rem 0; }
    .footer { font-size: 11px; color: #9aaa9a; display: flex; align-items: center; gap: 6px; }
    .footer svg { width: 13px; height: 13px; }

    #loading { display: none; font-size: 13px; color: #6b7a6b; padding: 8px 0; }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="logo"><span>UBU</span></div>
    <div class="header-text">
      <h1>Document Verifier</h1>
      <p>UBU Office Solution Ltd &middot; Verification System</p>
    </div>
  </div>

  <div class="card">
    <h2>Verify a document</h2>
    <p class="sub">Enter the ID or reference number printed on the document to confirm its authenticity.</p>

    <div class="input-group">
      <input type="text" id="certInput"
             placeholder="e.g. UBU/E&S/2026/001"
             maxlength="40"
             value="<?= htmlspecialchars($autoId, ENT_QUOTES, 'UTF-8') ?>" />
      <button onclick="verifyCert()">Verify</button>
    </div>
    <p class="hint">Enter the ID or reference number exactly as printed on your document.</p>

    <div id="loading">Checking record&hellip;</div>
    <div id="resultBox" class="result">
      <div class="result-status" id="resultStatus"></div>
      <div id="resultBody"></div>
    </div>

    <div class="divider"></div>
    <div class="footer">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Official records from UBU Office Solution Ltd. For queries, contact UBU directly.
    </div>
  </div>
</div>

<script>
function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function verifyCert() {
  const input = document.getElementById('certInput').value.trim();
  const loading = document.getElementById('loading');
  const box = document.getElementById('resultBox');

  if (!input) return;

  box.style.display = 'none';
  loading.style.display = 'block';

  const fd = new FormData();
  fd.append('cert', input);

  fetch('verify.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      loading.style.display = 'none';
      showResult(data);
    })
    .catch(() => {
      loading.style.display = 'none';
      showResult({ found: false, error: 'Server error. Please try again.' });
    });
}

function showResult(data) {
  const box    = document.getElementById('resultBox');
  const status = document.getElementById('resultStatus');
  const body   = document.getElementById('resultBody');

  box.style.display = 'block';

  if (data.found) {
    const docType = escHtml(data.type || 'Document');
    box.className = 'result success';
    status.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="#1a6b30" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-5"/></svg>
      ${docType} verified
    `;
    // Build meta rows — only show rows that have a value
    const metaRows = [
      ['Reference No', data.cert_no],
      ['Issued to',    data.name],
      [data.type || 'Project', data.workshop],
      ['Theme',        data.event],
      ['Date',         data.date],
      ['Venue',        data.venue],
      ['Issued by',    data.issuer],
    ].filter(([, v]) => v).map(([k, v]) =>
      `<strong>${escHtml(k)}:</strong> ${escHtml(v)}`
    ).join('<br>');

    body.innerHTML = `
      <div class="result-name">${escHtml(data.name)}</div>
      <div class="result-meta">${metaRows}</div>
    `;
  } else {
    box.className = 'result error';
    status.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="#a03030" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
      Not found
    `;
    body.innerHTML = `<p class="error-msg">${escHtml(data.error || 'This ID does not match any record in our database.')}</p>`;
  }
}

document.getElementById('certInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') verifyCert();
});

<?php if ($autoData !== null): ?>
document.addEventListener('DOMContentLoaded', function () {
  showResult(<?= json_encode($autoData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
});
<?php endif; ?>
</script>
</body>
</html>
