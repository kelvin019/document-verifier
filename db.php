<?php
declare(strict_types=1);

// Change this to match your deployment folder, e.g. '/document-verify' or '' for root
define('BASE_PATH', '/document-verify');

define('DB_PATH', __DIR__ . '/data/certs.db');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            type        TEXT NOT NULL DEFAULT 'Certificate',
            theme       TEXT NOT NULL DEFAULT '',
            date        TEXT NOT NULL DEFAULT '',
            venue       TEXT NOT NULL DEFAULT '',
            issuer      TEXT NOT NULL DEFAULT '',
            cert_prefix TEXT NOT NULL DEFAULT '',
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS certificates (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            cert_number TEXT NOT NULL UNIQUE,
            holder_name TEXT NOT NULL,
            project_id  INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE INDEX IF NOT EXISTS idx_certs_project ON certificates(project_id);
        CREATE INDEX IF NOT EXISTS idx_certs_number  ON certificates(cert_number);

        CREATE TABLE IF NOT EXISTS admins (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    // Idempotent migration: add 'type' column to existing projects tables
    try {
        $pdo->exec("ALTER TABLE projects ADD COLUMN type TEXT NOT NULL DEFAULT 'Certificate'");
    } catch (PDOException) { /* column already exists — ignore */ }

    return $pdo;
}
