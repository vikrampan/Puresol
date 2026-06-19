<?php
/**
 * Puresol Messages (Contact Form) API
 *
 * POST /api/messages.php             — Submit a contact message
 * GET  /api/messages.php             — List messages (admin, paginated)
 * PUT  /api/messages.php?id=...      — Mark read / archived (admin)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = Database::connect();

switch ($method) {

    // -------------------------------------------------------
    // POST — Submit a contact message (public)
    // -------------------------------------------------------
    case 'POST':
      try {
        // Rate limiting: max 5 messages per IP per hour (file-backed, not bypassable via new session)
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_dir  = dirname(__DIR__) . '/logs/rate_limits';
        if (!is_dir($rate_dir)) {
            mkdir($rate_dir, 0700, true);
        }
        $rate_file = $rate_dir . '/msg_' . md5($client_ip) . '.json';
        $rate      = file_exists($rate_file)
            ? json_decode(file_get_contents($rate_file), true)
            : ['count' => 0, 'window_start' => time()];

        if (time() - $rate['window_start'] > 3600) {
            $rate = ['count' => 0, 'window_start' => time()];
        }

        if ($rate['count'] >= 5) {
            json_response(null, 429, 'Too many messages. Please try again later.');
        }

        $body    = get_json_body();
        $missing = validate_required($body, ['name', 'email', 'message']);
        if (!empty($missing)) {
            json_response(null, 400, 'Missing required fields: ' . implode(', ', $missing));
        }

        $name    = sanitize_input($body['name']);
        $email   = sanitize_input($body['email']);
        $subject = sanitize_input($body['subject'] ?? '');
        $message = sanitize_input($body['message']);
        $phone   = sanitize_input($body['phone'] ?? '');

        if (!validate_email($email)) {
            json_response(null, 400, 'Invalid email address.');
        }

        if (strlen($message) < 10) {
            json_response(null, 400, 'Message must be at least 10 characters.');
        }

        if (strlen($message) > 5000) {
            json_response(null, 400, 'Message must not exceed 5000 characters.');
        }

        $stmt = $db->prepare(
            "INSERT INTO messages (name, email, phone, subject, message)
             VALUES (:name, :email, :phone, :subject, :message)"
        );
        $stmt->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':phone'   => $phone,
            ':subject' => $subject,
            ':message' => $message,
        ]);

        $msg_id = (int) $db->lastInsertId();

        // Persist rate counter
        $rate['count']++;
        file_put_contents($rate_file, json_encode($rate), LOCK_EX);

        // ---- Send email notification to admin (plain text) ----
        $admin_subject = "New Contact Message: " . ($subject ?: 'No Subject');
        $admin_body    = "Name: {$name}\n"
                       . "Email: {$email}\n"
                       . "Phone: {$phone}\n"
                       . "Subject: {$subject}\n\n"
                       . "Message:\n{$message}\n";

        $safe_reply_to = str_replace(["\r", "\n"], '', $email);
        $admin_headers  = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $admin_headers .= "Reply-To: {$safe_reply_to}\r\n";
        $admin_headers .= "MIME-Version: 1.0\r\n";
        $admin_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $admin_sent = mail(ADMIN_NOTIFY_EMAIL, $admin_subject, $admin_body, $admin_headers);
        if (!$admin_sent) {
            log_activity('mail_failed', "msg_id={$msg_id} to=" . ADMIN_NOTIFY_EMAIL);
        }

        // ---- Send confirmation email to the user (HTML) ----
        $safe_to = str_replace(["\r", "\n"], '', $email);
        $user_subject = "Thank you for contacting Puresol";

        $name_safe    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $subject_safe = htmlspecialchars($subject ?: 'General Enquiry', ENT_QUOTES, 'UTF-8');
        $message_safe = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        $user_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Thank You — Puresol</title></head>
<body style="margin:0;padding:0;background:#f6f3ee;font-family:Georgia,'Times New Roman',serif;color:#2a2a2a;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ee;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e6e0d5;">
        <tr><td style="padding:32px 40px 16px;border-bottom:1px solid #ece6da;">
          <h1 style="margin:0;font-size:28px;letter-spacing:0.5px;color:#2a2a2a;font-family:'Playfair Display',Georgia,serif;">Puresol</h1>
          <p style="margin:6px 0 0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#8a7f6c;">Agrigore Ventures Pvt. Ltd.</p>
        </td></tr>
        <tr><td style="padding:32px 40px 8px;">
          <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Dear {$name_safe},</p>
          <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Thank you for your enquiry. We have received your message and our team will get back to you shortly &mdash; typically within 24 business hours.</p>
          <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">For your records, here is a copy of what you sent:</p>
        </td></tr>
        <tr><td style="padding:0 40px 16px;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf8f3;border-left:3px solid #b89968;padding:16px 20px;">
            <tr><td style="padding:6px 0;font-size:14px;line-height:1.6;color:#4a4a4a;"><strong>Subject:</strong> {$subject_safe}</td></tr>
            <tr><td style="padding:6px 0;font-size:14px;line-height:1.6;color:#4a4a4a;"><strong>Message:</strong><br>{$message_safe}</td></tr>
          </table>
        </td></tr>
        <tr><td style="padding:16px 40px 32px;">
          <p style="margin:0 0 8px;font-size:15px;line-height:1.6;">If your enquiry is urgent, you can also reach us directly:</p>
          <p style="margin:0 0 4px;font-size:15px;line-height:1.6;">Phone: <a href="tel:+918586891913" style="color:#b89968;text-decoration:none;">+91 85868 91913</a></p>
          <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Email: <a href="mailto:info@puresol.in" style="color:#b89968;text-decoration:none;">info@puresol.in</a></p>
          <p style="margin:24px 0 0;font-size:15px;line-height:1.6;">Warm regards,<br><em>The Puresol Team</em></p>
        </td></tr>
        <tr><td style="padding:20px 40px;background:#f1ece1;border-top:1px solid #e6e0d5;">
          <p style="margin:0;font-size:12px;line-height:1.5;color:#8a7f6c;text-align:center;">
            &copy; 2025 Puresol &middot; Agrigore Ventures Pvt. Ltd.<br>
            Hand-harvested from Sambhar Lake, Rajasthan &middot; <a href="https://puresol.in" style="color:#8a7f6c;text-decoration:underline;">puresol.in</a>
          </p>
          <p style="margin:8px 0 0;font-size:11px;line-height:1.5;color:#a89c84;text-align:center;">
            This is an automated confirmation. Please do not reply directly to this email.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        $user_headers  = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $user_headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
        $user_headers .= "MIME-Version: 1.0\r\n";
        $user_headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $user_sent = mail($safe_to, $user_subject, $user_body, $user_headers);
        if (!$user_sent) {
            log_activity('mail_failed', "msg_id={$msg_id} user_confirmation_to={$safe_to}");
        }

        log_activity('message_received', "id={$msg_id} from={$email}");

        json_response(['id' => $msg_id], 201, 'Thank you for your enquiry. Our team will get back to you shortly.');

      } catch (\Throwable $e) {
        error_log('messages.php POST error: ' . $e->getMessage());
        log_activity('message_error', $e->getMessage());
        json_response(null, 500, 'An internal error occurred. Please try again later.');
      }
        break;

    // -------------------------------------------------------
    // GET — List messages (admin only, paginated)
    // -------------------------------------------------------
    case 'GET':
        require_admin_auth();

        $pagination = get_pagination(20);
        $filter     = $_GET['filter'] ?? 'all'; // all, unread, archived

        $where = '';
        switch ($filter) {
            case 'unread':
                $where = 'WHERE is_read = 0 AND is_archived = 0';
                break;
            case 'archived':
                $where = 'WHERE is_archived = 1';
                break;
            case 'read':
                $where = 'WHERE is_read = 1 AND is_archived = 0';
                break;
            default:
                $where = 'WHERE is_archived = 0';
        }

        // Total count
        $count_stmt = $db->query("SELECT COUNT(*) FROM messages {$where}");
        $total      = (int) $count_stmt->fetchColumn();

        // Fetch page
        $stmt = $db->prepare(
            "SELECT id, name, email, subject, message, is_read, is_archived,
                    replied_at, created_at
             FROM messages
             {$where}
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll();

        foreach ($messages as &$m) {
            $m['id']          = (int) $m['id'];
            $m['is_read']     = (bool) $m['is_read'];
            $m['is_archived'] = (bool) $m['is_archived'];
        }
        unset($m);

        json_response([
            'messages'   => $messages,
            'pagination' => [
                'page'       => $pagination['page'],
                'limit'      => $pagination['limit'],
                'total'      => $total,
                'total_pages'=> (int) ceil($total / $pagination['limit']),
            ],
        ], 200, 'Messages retrieved.');
        break;

    // -------------------------------------------------------
    // PUT — Mark as read / archived
    // -------------------------------------------------------
    case 'PUT':
        require_admin_auth();

        if (!verify_csrf_token()) {
            json_response(null, 403, 'Invalid or missing CSRF token.');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            json_response(null, 400, 'Message ID is required.');
        }

        $body = get_json_body();

        $sets   = [];
        $params = [':id' => $id];

        if (array_key_exists('is_read', $body)) {
            $sets[]             = 'is_read = :is_read';
            $params[':is_read'] = (int) (bool) $body['is_read'];
        }

        if (array_key_exists('is_archived', $body)) {
            $sets[]                 = 'is_archived = :is_archived';
            $params[':is_archived'] = (int) (bool) $body['is_archived'];
        }

        if (array_key_exists('replied_at', $body)) {
            if ($body['replied_at']) {
                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $body['replied_at']);
                if (!$dt || $dt->format('Y-m-d H:i:s') !== $body['replied_at']) {
                    json_response(null, 400, 'replied_at must be in YYYY-MM-DD HH:MM:SS format.');
                }
                $replied_at = $body['replied_at'];
            } else {
                $replied_at = date('Y-m-d H:i:s');
            }
            $sets[]                = 'replied_at = :replied_at';
            $params[':replied_at'] = $replied_at;
        }

        if (empty($sets)) {
            json_response(null, 400, 'No valid fields to update.');
        }

        $sql  = "UPDATE messages SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            json_response(null, 404, 'Message not found.');
        }

        log_activity('message_updated', "id={$id}");
        json_response(null, 200, 'Message updated successfully.');
        break;

    default:
        json_response(null, 405, 'Method not allowed.');
}
