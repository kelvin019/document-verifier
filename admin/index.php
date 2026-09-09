<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/db.php';

if (is_logged_in()) {
    redirect(BASE_PATH . '/admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            $stmt = get_db()->prepare('SELECT id, password_hash FROM admins WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['last_regen'] = time();
                redirect(BASE_PATH . '/admin/dashboard.php');
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login · UBU Certificate System</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f4f6f4;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 2rem 1rem;
    }
    .container { width: 100%; max-width: 380px; }
    .header {
      display: flex; align-items: center; gap: 14px;
      margin-bottom: 1.5rem;
      background: #fff; border-radius: 14px;
      border: 1px solid #e2e8e2; padding: 1.25rem 1.5rem;
    }
    .logo {
      width: 46px; height: 46px; border-radius: 10px;
      background: #1a4a0d; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .logo span { color: #7dd3aa; font-weight: 700; font-size: 13px; }
    .header-text h1 { font-size: 15px; font-weight: 600; color: #1a1a1a; }
    .header-text p  { font-size: 11px; color: #6b7a6b; margin-top: 2px; }
    .card {
      background: #fff; border-radius: 14px;
      border: 1px solid #e2e8e2; padding: 2rem;
    }
    .card h2 { font-size: 15px; font-weight: 600; color: #1a1a1a; margin-bottom: 1.5rem; }
    label { display: block; font-size: 12px; font-weight: 500; color: #4a5a4a; margin-bottom: 5px; }
    input[type=text], input[type=password] {
      width: 100%; border: 1px solid #d0d8d0; border-radius: 8px;
      padding: 10px 14px; font-size: 14px; color: #1a1a1a; outline: none;
      transition: border-color 0.2s; margin-bottom: 1rem;
    }
    input:focus { border-color: #2d7a3a; }
    button[type=submit] {
      width: 100%; background: #1a4a0d; color: #fff; border: none;
      border-radius: 8px; padding: 11px; font-size: 14px; font-weight: 500;
      cursor: pointer; transition: background 0.2s;
    }
    button[type=submit]:hover { background: #2d6b18; }
    .error {
      background: #fff4f4; border: 1px solid #f0b0b0;
      border-radius: 8px; padding: 10px 14px;
      font-size: 13px; color: #7a3030; margin-bottom: 1rem;
    }
    .back { text-align: center; margin-top: 1rem; font-size: 12px; }
    .back a { color: #2d7a3a; text-decoration: none; }
    .back a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="logo"><span>UBU</span></div>
    <div class="header-text">
      <h1>Certificate System</h1>
      <p>Admin Panel &middot; UBU Office Solution Ltd</p>
    </div>
  </div>

  <div class="card">
    <h2>Admin Login</h2>

    <?php if ($error): ?>
      <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>
      <label for="username">Username</label>
      <input type="text" id="username" name="username"
             value="<?= e($_POST['username'] ?? '') ?>"
             autocomplete="username" required />

      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required />

      <button type="submit">Sign In</button>
    </form>
  </div>

  <div class="back"><a href="<?= BASE_PATH ?>/">&larr; Back to Document Verifier</a></div>
</div>
</body>
</html>
