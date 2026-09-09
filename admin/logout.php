<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/db.php';
session_destroy();
redirect(BASE_PATH . '/admin/index.php');
