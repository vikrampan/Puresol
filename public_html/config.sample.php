<?php
/**
 * Puresol Configuration — SAMPLE
 * Agrigore Ventures Pvt. Ltd.
 *
 * Copy this file to `config.php` on the server and fill in real values.
 * `config.php` is gitignored and must NEVER be committed (it holds secrets).
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// Environment: 'development' or 'production'
define('APP_ENV', 'production');

// ── Database Credentials ──────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'YOUR_DB_NAME');
define('DB_USER', 'YOUR_DB_USER');
define('DB_PASS', 'YOUR_DB_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

// ── Base URL (no trailing slash) ──────────────────────────
define('BASE_URL', 'https://puresol.in');
define('API_URL', BASE_URL . '/api');
define('UPLOAD_DIR', __DIR__ . '/uploads/products/');
define('UPLOAD_URL', BASE_URL . '/uploads/products/');

// ── Encoding ──────────────────────────────────────────────
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// ── Session ───────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', APP_ENV === 'production' ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 7200);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Error Reporting ───────────────────────────────────────
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// ── CORS ──────────────────────────────────────────────────
$allowed_origins = [
    'https://puresol.in',
    'https://www.puresol.in',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── SMTP ──────────────────────────────────────────────────
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'info@puresol.in');
define('SMTP_PASS', 'YOUR_SMTP_PASSWORD');
define('SMTP_FROM_EMAIL', 'info@puresol.in');
define('SMTP_FROM_NAME', 'Puresol');
define('ADMIN_NOTIFY_EMAIL', 'YOUR_NOTIFY_EMAIL');

// ── PDO Database Connection (Singleton) ───────────────────
class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST
                 . ';dbname=' . DB_NAME
                 . ';charset=' . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        }

        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton'); }
}
