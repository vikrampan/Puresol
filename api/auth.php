<?php
/**
 * Puresol Authentication API
 *
 * POST /api/auth.php?action=login   — Log in
 * POST /api/auth.php?action=logout  — Log out
 * GET  /api/auth.php?action=me      — Current user info
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {

    // -------------------------------------------------------
    // LOGIN
    // -------------------------------------------------------
    case 'login':
        if ($method !== 'POST') {
            json_response(null, 405, 'Method not allowed.');
        }

        $body = get_json_body();
        $username = sanitize_input($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if ($username === '' || $password === '') {
            json_response(null, 400, 'Username and password are required.');
        }

        $db   = Database::connect();
        $stmt = $db->prepare(
            "SELECT id, username, email, password_hash, role
             FROM admin_users
             WHERE username = :username
             LIMIT 1"
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            log_activity('login_failed', "username={$username}");
            json_response(null, 401, 'Invalid username or password.');
        }

        // Update last_login
        $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id")
           ->execute([':id' => $user['id']]);

        // Regenerate session to prevent fixation
        session_regenerate_id(true);

        $_SESSION['admin_id']        = (int) $user['id'];
        $_SESSION['admin_username']  = $user['username'];
        $_SESSION['admin_email']     = $user['email'];
        $_SESSION['admin_role']      = $user['role'];
        $_SESSION['admin_logged_in'] = true;

        $csrf = generate_csrf_token();

        log_activity('login_success', "username={$username}", (int) $user['id']);

        json_response([
            'user' => [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ],
            'csrf_token' => $csrf,
        ], 200, 'Login successful.');
        break;

    // -------------------------------------------------------
    // LOGOUT
    // -------------------------------------------------------
    case 'logout':
        if ($method !== 'POST') {
            json_response(null, 405, 'Method not allowed.');
        }

        if (!verify_csrf_token()) {
            json_response(null, 403, 'Invalid or missing CSRF token.');
        }

        $admin_id = get_current_admin_id();
        log_activity('logout', '', $admin_id);

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();

        json_response(null, 200, 'Logged out successfully.');
        break;

    // -------------------------------------------------------
    // ME — Current user info
    // -------------------------------------------------------
    case 'me':
        if ($method !== 'GET') {
            json_response(null, 405, 'Method not allowed.');
        }

        require_admin_auth();

        $db   = Database::connect();
        $stmt = $db->prepare(
            "SELECT id, username, email, role, created_at, last_login
             FROM admin_users
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            json_response(null, 404, 'User not found.');
        }

        $user['id'] = (int) $user['id'];

        json_response([
            'user'       => $user,
            'csrf_token' => generate_csrf_token(),
        ], 200, 'Authenticated user.');
        break;

    default:
        json_response(null, 400, 'Invalid action. Use: login, logout, or me.');
}
