<?php
$current = basename($_SERVER['PHP_SELF'], '.php');
function nav_active(string $page): string {
    global $current;
    return $current === $page ? ' class="active"' : '';
}
?>
<nav class="nav">
  <div class="nav-brand">
    <div class="logo-dot">UBU</div>
    Certificate Admin
  </div>
  <a href="<?= BASE_PATH ?>/admin/dashboard.php"<?= nav_active('dashboard') ?>>Dashboard</a>
  <a href="<?= BASE_PATH ?>/admin/projects.php"<?= nav_active('projects') ?>>Projects</a>
  <a href="<?= BASE_PATH ?>/admin/certificates.php"<?= nav_active('certificates') ?>>Records</a>
  <span class="nav-sep">|</span>
  <a href="<?= BASE_PATH ?>/admin/change-password.php"<?= nav_active('change-password') ?>>Change Password</a>
  <a href="<?= BASE_PATH ?>/" target="_blank">Public Site</a>
  <a href="<?= BASE_PATH ?>/admin/logout.php">Logout</a>
</nav>
