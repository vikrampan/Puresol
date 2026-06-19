<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: text/html; charset=UTF-8');

$db      = Database::connect();
$success = '';
$error   = '';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);

        if ($action === 'mark_read' && $id > 0) {
            $db->prepare("UPDATE messages SET is_read = 1 WHERE id = :id")
               ->execute([':id' => $id]);
            $success = 'Message marked as read.';

        } elseif ($action === 'mark_unread' && $id > 0) {
            $db->prepare("UPDATE messages SET is_read = 0 WHERE id = :id")
               ->execute([':id' => $id]);
            $success = 'Message marked as unread.';

        } elseif ($action === 'delete' && $id > 0) {
            $db->prepare("DELETE FROM messages WHERE id = :id")
               ->execute([':id' => $id]);
            $success = 'Message deleted.';
        }
    }
}

// ── Fetch messages ────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$whereMap = [
    'all'    => 'WHERE is_archived = 0',
    'unread' => 'WHERE is_read = 0 AND is_archived = 0',
    'read'   => 'WHERE is_read = 1 AND is_archived = 0',
];
$where = $whereMap[$filter] ?? $whereMap['all'];

$messages = $db->query(
    "SELECT id, name, email, subject, message, is_read, created_at
     FROM messages
     {$where}
     ORDER BY created_at DESC"
)->fetchAll();

$unread_count = (int) $db->query("SELECT COUNT(*) FROM messages WHERE is_read=0 AND is_archived=0")->fetchColumn();

// ── Message detail view ───────────────────────────────────────
$detail = null;
if (isset($_GET['id'])) {
    $detailId = (int) $_GET['id'];
    $stmt = $db->prepare("SELECT * FROM messages WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $detailId]);
    $detail = $stmt->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Puresol Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        maroon: { DEFAULT: '#5C1A2A', light: '#7a2438', dark: '#3d1019' },
                        gold:   { DEFAULT: '#D4A853', light: '#debb75' }
                    },
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex min-h-screen">
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="flex-1 ml-64 flex flex-col min-h-screen">

        <!-- Top bar -->
        <header class="h-16 bg-white border-b border-gray-100 flex items-center justify-between px-8 sticky top-0 z-20">
            <div>
                <h1 class="text-gray-900 font-semibold text-[15px]">Messages</h1>
                <p class="text-gray-400 text-xs mt-0.5">Contact form submissions</p>
            </div>
            <?php if ($unread_count > 0): ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#5C1A2A]/8 text-[#5C1A2A]">
                <span class="w-1.5 h-1.5 rounded-full bg-[#5C1A2A]"></span>
                <?= $unread_count ?> unread
            </span>
            <?php endif; ?>
        </header>

        <main class="flex-1 p-8">

            <?php if ($success): ?>
            <div class="mb-5 flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-100 rounded-xl text-green-700 text-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-red-700 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Filter tabs -->
            <div class="flex items-center gap-1 mb-6 bg-white rounded-xl border border-gray-100 p-1 w-fit shadow-sm">
                <?php
                $tabs = ['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'];
                foreach ($tabs as $key => $label):
                    $active = ($filter === $key);
                ?>
                <a href="?filter=<?= $key ?>"
                   class="px-4 py-1.5 rounded-lg text-[13px] font-medium transition-all
                          <?= $active ? 'bg-[#5C1A2A] text-white shadow-sm' : 'text-gray-500 hover:text-gray-800' ?>">
                    <?= $label ?>
                    <?php if ($key === 'unread' && $unread_count > 0): ?>
                    <span class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px]
                                 <?= $active ? 'bg-white/20 text-white' : 'bg-[#5C1A2A]/10 text-[#5C1A2A]' ?>">
                        <?= $unread_count ?>
                    </span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ── Detail Panel (if message selected) ── -->
            <?php if ($detail): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-6 p-6">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">
                            <?= htmlspecialchars($detail['subject'] ?: '(no subject)') ?>
                        </h2>
                        <div class="flex items-center gap-3 mt-2">
                            <div class="w-8 h-8 rounded-full bg-[#5C1A2A]/10 flex items-center justify-center">
                                <span class="text-[#5C1A2A] text-xs font-bold">
                                    <?= strtoupper(substr($detail['name'], 0, 1)) ?>
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($detail['name']) ?></p>
                                <a href="mailto:<?= htmlspecialchars($detail['email']) ?>"
                                   class="text-xs text-[#5C1A2A] hover:underline">
                                    <?= htmlspecialchars($detail['email']) ?>
                                </a>
                            </div>
                            <span class="text-xs text-gray-400 ml-2">
                                <?= date('d M Y, H:i', strtotime($detail['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    <a href="messages.php?filter=<?= htmlspecialchars($filter) ?>"
                       class="text-gray-400 hover:text-gray-700 transition-colors p-1">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                </div>

                <div class="bg-gray-50 rounded-xl px-5 py-4 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap mb-5">
                    <?= htmlspecialchars($detail['message']) ?>
                </div>

                <div class="flex items-center gap-3 flex-wrap">
                    <a href="mailto:<?= htmlspecialchars($detail['email']) ?>?subject=Re: <?= rawurlencode($detail['subject'] ?? '') ?>"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-[#5C1A2A] text-white text-sm font-medium rounded-lg hover:bg-[#7a2438] transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Reply
                    </a>
                    <form method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
                        <input type="hidden" name="action" value="<?= $detail['is_read'] ? 'mark_unread' : 'mark_read' ?>">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                            <?= $detail['is_read'] ? 'Mark as Unread' : 'Mark as Read' ?>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this message?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 border border-red-100 text-red-500 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Messages Table ── -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">

                <?php if (empty($messages)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                    <svg class="w-10 h-10 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm">No messages found.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">From</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Subject</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Preview</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="py-3 px-6 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($messages as $msg): ?>
                            <tr class="hover:bg-gray-50/60 transition-colors <?= !$msg['is_read'] ? 'bg-[#5C1A2A]/[0.02]' : '' ?>">

                                <td class="py-3.5 px-6">
                                    <div class="flex items-center gap-2.5">
                                        <?php if (!$msg['is_read']): ?>
                                        <span class="w-2 h-2 rounded-full bg-[#5C1A2A] flex-shrink-0"></span>
                                        <?php else: ?>
                                        <span class="w-2 h-2 flex-shrink-0"></span>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-semibold text-gray-900 text-[13px]"><?= htmlspecialchars($msg['name']) ?></p>
                                            <p class="text-[11px] text-gray-400"><?= htmlspecialchars($msg['email']) ?></p>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-6 font-medium text-gray-700 max-w-[180px] truncate">
                                    <a href="?id=<?= $msg['id'] ?>&filter=<?= htmlspecialchars($filter) ?>"
                                       class="hover:text-[#5C1A2A] transition-colors">
                                        <?= htmlspecialchars($msg['subject'] ?: '(no subject)') ?>
                                    </a>
                                </td>

                                <td class="py-3.5 px-6 text-gray-400 text-[12px] max-w-[220px] truncate">
                                    <?= htmlspecialchars(substr($msg['message'], 0, 90)) ?>…
                                </td>

                                <td class="py-3.5 px-6 text-gray-400 text-[12px] whitespace-nowrap">
                                    <?= date('d M, H:i', strtotime($msg['created_at'])) ?>
                                </td>

                                <td class="py-3.5 px-6">
                                    <?php if ($msg['is_read']): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">Read</span>
                                    <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[#5C1A2A]/8 text-[#5C1A2A]">Unread</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-6 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="?id=<?= $msg['id'] ?>&filter=<?= htmlspecialchars($filter) ?>"
                                           class="px-3 py-1.5 text-[12px] font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                            View
                                        </a>
                                        <?php if (!$msg['is_read']): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
                                            <input type="hidden" name="action" value="mark_read">
                                            <button type="submit"
                                                    class="px-3 py-1.5 text-[12px] font-medium text-[#5C1A2A] border border-[#5C1A2A]/20 rounded-lg hover:bg-[#5C1A2A]/5 transition-colors">
                                                Mark Read
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this message?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit"
                                                    class="px-3 py-1.5 text-[12px] font-medium text-red-500 border border-red-100 rounded-lg hover:bg-red-50 transition-colors">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

</body>
</html>
