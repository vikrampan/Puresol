<?php
/**
 * Puresol Newsletter Subscription API
 *
 * POST /api/subscribe.php — Subscribe an email address
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(null, 405, 'Method not allowed.');
}

$body  = get_json_body();

// Fallback: if JSON body is empty, try URL-encoded form data
if (empty($body) || !isset($body['email'])) {
    $body = ['email' => $_POST['email'] ?? ''];
}

$email = isset($body['email']) ? sanitize_input($body['email']) : '';

if ($email === '') {
    json_response(null, 400, 'Email address is required.');
}

if (!validate_email($email)) {
    json_response(null, 400, 'Invalid email address.');
}

$db = Database::connect();

try {
    $stmt = $db->prepare(
        "INSERT IGNORE INTO subscribers (email) VALUES (:email)"
    );
    $stmt->execute([':email' => $email]);
} catch (\Throwable $e) {
    error_log('subscribe.php error: ' . $e->getMessage());
    log_activity('subscribe_error', $e->getMessage());
    json_response(null, 500, 'Subscription failed. Please try again.');
}

log_activity('newsletter_subscribe', "email={$email}");

json_response(null, 200, 'You have been subscribed successfully.');
