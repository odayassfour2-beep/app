<?php
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) { http_response_code(503); header('Content-Type: application/json; charset=utf-8'); echo '{"error":"Server is not configured"}'; exit; }
$config = require $configFile;
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('sinjel_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $status, array $data): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function db(): PDO {
    global $config;
    static $pdo = null;
    if ($pdo) return $pdo;
    $d = $config['db'];
    $dsn = 'mysql:host='.$d['host'].';port='.(int)($d['port'] ?? 3306).';dbname='.$d['name'].';charset='.($d['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $d['user'], $d['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function body_json(): array { $raw = file_get_contents('php://input'); $v = json_decode($raw ?: '', true); if (!is_array($v)) reply(400, ['error'=>'Invalid JSON']); return $v; }
function require_auth(): void { if (empty($_SESSION['user_id'])) reply(401, ['error'=>'Sign in required']); }
function require_csrf(): void { $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''); if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) reply(403, ['error'=>'Invalid request token']); }
try {
    $action = (string)($_GET['action'] ?? '');
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = body_json(); $username = trim((string)($in['username'] ?? '')); $password = (string)($in['password'] ?? '');
        $q = db()->prepare('SELECT id, username, password_hash FROM app_users WHERE id=1 LIMIT 1'); $q->execute(); $user = $q->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password_hash']) || !hash_equals($user['username'], $username)) reply(401, ['error'=>'Invalid username or password']);
        session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id']; $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $q = db()->query('SELECT payload FROM app_state WHERE id=1'); $raw = $q->fetchColumn();
        $state = json_decode($raw === false ? 'null' : $raw, true);
        reply(200, ['ok'=>true, 'csrf'=>$_SESSION['csrf'], 'state'=>$state]);
    }
    if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        require_auth(); $q = db()->query('SELECT payload FROM app_state WHERE id=1'); $raw = $q->fetchColumn();
        reply(200, ['state'=>json_decode($raw === false ? 'null' : $raw, true), 'csrf'=>$_SESSION['csrf'] ?? null]);
    }
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_auth(); require_csrf();
        $in = body_json(); $state = $in['state'] ?? null;
        if (!is_array($state) || !isset($state['settings'], $state['students']) || !is_array($state['settings']) || !is_array($state['students'])) reply(400, ['error'=>'Unexpected application data']);
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || strlen($json) > 8 * 1024 * 1024) reply(413, ['error'=>'Data is too large or invalid']);
        $q = db()->prepare('INSERT INTO app_state (id,payload) VALUES (1,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)'); $q->execute([$json]);
        reply(200, ['ok'=>true]);
    }
    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_auth(); require_csrf(); $_SESSION = []; session_destroy(); reply(200, ['ok'=>true]);
    }
    reply(404, ['error'=>'Unknown endpoint']);
} catch (Throwable $e) { reply(500, ['error'=>'Server error. Check PHP/MySQL configuration and server logs.']); }
