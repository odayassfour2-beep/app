<?php
/** One-time installer. Delete this file immediately after a successful install. */
$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) { http_response_code(500); exit('Create config.php from config.example.php first.'); }
$config = require $configPath;
$key = (string)($config['install_key'] ?? '');
if (!$key || str_starts_with($key, 'REPLACE_')) { http_response_code(500); exit('Set a one-time install_key in config.php first.'); }
$provided = (string)($_GET['key'] ?? $_POST['key'] ?? '');
if (!hash_equals($key, $provided)) { http_response_code(404); exit('Not found'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) $error = 'Username must be 3–64 letters, numbers, dot, underscore or hyphen.';
    elseif (strlen($password) < 12) $error = 'Use a password of at least 12 characters.';
    else {
        try {
            $d = $config['db'];
            $dsn = 'mysql:host=' . $d['host'] . ';port=' . (int)($d['port'] ?? 3306) . ';dbname=' . $d['name'] . ';charset=' . ($d['charset'] ?? 'utf8mb4');
            $pdo = new PDO($dsn, $d['user'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_users (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, username VARCHAR(64) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS app_state (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, payload LONGTEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt = $pdo->prepare('INSERT INTO app_users (id, username, password_hash) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE username = VALUES(username), password_hash = VALUES(password_hash)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $pdo->exec("INSERT IGNORE INTO app_state (id, payload) VALUES (1, 'null')");
            $done = true;
        } catch (Throwable $e) { $error = 'Database setup failed. Check config.php and ensure the MySQL user has create/alter privileges.'; }
    }
}
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>One-time setup</title>
<style>body{font:16px system-ui;max-width:560px;margin:40px auto;padding:0 16px}label{display:block;margin:16px 0}input{display:block;width:100%;padding:10px;box-sizing:border-box}button{padding:10px 16px}.err{color:#a00}</style>
<h1>School app — one-time setup</h1>
<?php if (!empty($done)): ?><p>Setup complete. <strong>Delete setup.php now</strong>, then open the site and sign in with the username and password you just created.</p><?php else: ?>
<p>Create the server-side administrator account. Use a unique password of at least 12 characters.</p><?php if ($error): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="key" value="<?= htmlspecialchars($provided, ENT_QUOTES, 'UTF-8') ?>"><label>Username<input name="username" required minlength="3" maxlength="64" autocomplete="username"></label><label>Password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><button>Create database tables and admin</button></form>
<?php endif; ?></html>
