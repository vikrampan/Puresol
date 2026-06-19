<?php
/**
 * Puresol Auth Middleware
 *
 * Call require_admin_auth() at the top of any endpoint that needs protection.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Verify that the current request comes from an authenticated admin user.
 * Terminates with a 401 JSON response if not authenticated.
 *
 * @param string|null $required_role  If provided, also check that the user has this role.
 */
function require_admin_auth(?string $required_role = null): void
{
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check session-based authentication
    if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_logged_in'])) {
        json_response(null, 401, 'Authentication required. Please log in.');
    }

    // Optional role check
    if ($required_role !== null && ($_SESSION['admin_role'] ?? '') !== $required_role) {
        json_response(null, 403, 'Insufficient permissions. Required role: ' . $required_role);
    }
}

/**
 * Check authentication without terminating. Returns true if the user is logged in.
 */
function is_admin_authenticated(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_logged_in']);
}

/**
 * Get the currently authenticated admin user's ID, or null.
 */
function get_current_admin_id(): ?int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}
