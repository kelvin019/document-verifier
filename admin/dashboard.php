<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_auth();

$pdo = get_db();

$totalProjects = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$totalCerts    = (int) $pdo->query('SELECT COUNT(*) FROM certificates')->fetchColumn();

$projects = $pdo->query(
    'SELECT p.id, p.name, p.date, p.cert_prefix, COUNT(c.id) AS cert_count
     FROM projects p
     LEFT JOIN certificates c ON c.project_id = p.id
     GROUP BY p.id
     ORDER BY p.created_at DESC'
)->fetchAll();

$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard · UBU Admin</title>
  <style>
    <?php include __DIR__ . '/shared.css.php'; ?>
  </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="page">
  <div class="page-header">
    <h1>Dashboard</h1>
  </div>

  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-num"><?= $totalProjects ?></div>
      <div class="stat-label">Projects</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $totalCerts ?></div>
      <div class="stat-label">Records</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Projects</h2>
      <a href="projects.php" class="btn btn-sm">Manage Projects</a>
    </div>
    <?php if (empty($projects)): ?>
      <p class="empty">No projects yet. <a href="projects.php">Create one.</a></p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Project Name</th>
            <th>Type</th>
            <th>Date</th>
            <th>Prefix</th>
            <th>Records</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projects as $p): ?>
          <tr>
            <td><?= e($p['name']) ?></td>
            <td><span class="badge"><?= e($p['type'] ?? 'Certificate') ?></span></td>
            <td><?= e($p['date']) ?></td>
            <td><code><?= e($p['cert_prefix']) ?></code></td>
            <td><?= (int) $p['cert_count'] ?></td>
            <td class="actions">
              <a href="certificates.php?project_id=<?= (int) $p['id'] ?>" class="btn btn-sm">View Certs</a>
              <a href="projects.php?edit=<?= (int) $p['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
