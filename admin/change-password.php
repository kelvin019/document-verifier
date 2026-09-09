<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
require_auth();

$pdo    = get_db();
$flashes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Security token mismatch.');
        redirect(BASE_PATH . '/admin/change-password.php');
    }

    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        flash('error', 'All fields are required.');
    } elseif (strlen($new) < 8) {
        flash('error', 'New password must be at least 8 characters.');
    } elseif ($new !== $confirm) {
        flash('error', 'New password and confirmation do not match.');
    } else {
        $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($current, $admin['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id')
                ->execute([':h' => $hash, ':id' => $_SESSION['admin_id']]);
            flash('success', 'Password changed successfully.');
            redirect(BASE_PATH . '/admin/change-password.php');
        }
    }
    redirect(BASE_PATH . '/admin/change-password.php');
}

$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Change Password · UBU Admin</title>
  <style><?php include __DIR__ . '/shared.css.php'; ?></style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<div class="page">
  <div class="page-header">
    <h1>Change Password</h1>
  </div>

  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

  <div style="max-width:420px;">
    <div class="card">
      <div class="card-header"><h2>Update Admin Password</h2></div>
      <div class="card-body">
        <form method="POST" action="change-password.php">
          <?= csrf_field() ?>

          <div class="form-group">
            <label>Current Password *</label>
            <input type="password" name="current_password" autocomplete="current-password" required>
          </div>
          <div class="form-group">
            <label>New Password *</label>
            <input type="password" name="new_password" autocomplete="new-password"
                   minlength="8" required>
            <p class="form-hint">Minimum 8 characters.</p>
          </div>
          <div class="form-group">
            <label>Confirm New Password *</label>
            <input type="password" name="confirm_password" autocomplete="new-password"
                   minlength="8" required>
          </div>

          <button type="submit" class="btn" style="width:100%">Change Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
</body>
</html>
