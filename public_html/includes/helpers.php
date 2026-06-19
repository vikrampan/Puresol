<?php
/**
 * Puresol Helper Functions
 */

/**
 * Sanitize user input string.
 */
function sanitize_input(string $data): string
{
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

/**
 * Send a JSON response and terminate.
 *
 * @param mixed $data        Payload (array or object).
 * @param int   $status_code HTTP status code.
 * @param string $message    Human-readable message.
 */
function json_response($data = null, int $status_code = 200, string $message = ''): void
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');

    $success = $status_code >= 200 && $status_code < 300;

    $body = [
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ];

    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Generate a unique order number in PS-YYYYMMDD-XXXX format.
 */
function generate_order_number(PDO $db, int $extra = 0): string
{
    $date_part = date('Ymd');
    $prefix    = "PS-{$date_part}-";

    // MAX(CAST(...)) finds the true highest sequence regardless of insertion order.
    // COALESCE returns 0 when no orders exist yet for today.
    $stmt = $db->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(order_number, -4) AS UNSIGNED)), 0)
         FROM orders
         WHERE order_number LIKE :prefix"
    );
    $stmt->execute([':prefix' => $prefix . '%']);
    $max_seq = (int) $stmt->fetchColumn();

    // $extra lets the caller skip ahead on a duplicate-key retry without
    // hitting the database again.
    return $prefix . str_pad($max_seq + 1 + $extra, 4, '0', STR_PAD_LEFT);
}

/**
 * Validate an email address.
 */
function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Log an activity to a simple log file.
 */
function log_activity(string $action, string $details = '', ?int $user_id = null): void
{
    $log_dir  = dirname(__DIR__) . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/activity_' . date('Y-m') . '.log';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $user     = $user_id ?? ($_SESSION['admin_id'] ?? 0);
    $timestamp = date('Y-m-d H:i:s');

    $entry = "[{$timestamp}] USER={$user} IP={$ip} ACTION={$action} {$details}" . PHP_EOL;
    file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Read JSON body from a request.
 *
 * @return array Decoded JSON as associative array.
 */
function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Require specific fields in an array; return missing field names.
 */
function validate_required(array $data, array $fields): array
{
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Generate a CSRF token and store it in the session.
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token from header or body.
 */
function verify_csrf_token(?string $token = null): bool
{
    if ($token === null) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Create a URL-friendly slug from a string.
 */
function create_slug(string $text): string
{
    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Get pagination parameters from query string.
 *
 * @return array{page: int, limit: int, offset: int}
 */
function get_pagination(int $default_limit = 20, int $max_limit = 100): array
{
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min($max_limit, max(1, (int) ($_GET['limit'] ?? $default_limit)));
    $offset = ($page - 1) * $limit;

    return compact('page', 'limit', 'offset');
}
