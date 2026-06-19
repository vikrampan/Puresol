<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: text/html; charset=UTF-8');

$db = Database::connect();

// ── Stats ────────────────────────────────────────────────────
$total_revenue   = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM finances WHERE type='revenue'")->fetchColumn();
$total_expenses  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM finances WHERE type='expense'")->fetchColumn();
$active_products = (int)   $db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
$unread_messages = (int)   $db->query("SELECT COUNT(*) FROM messages WHERE is_read=0")->fetchColumn();

// ── Recent enquiries (last 8) ─────────────────────────────────
$enquiries = $db->query(
    "SELECT id, name, email, subject, is_read, created_at
     FROM messages
     ORDER BY created_at DESC
     LIMIT 8"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Puresol Admin</title>
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

    <!-- Main -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen">

        <!-- Top bar -->
        <header class="h-16 bg-white border-b border-gray-100 flex items-center justify-between px-8 sticky top-0 z-20">
            <div>
                <h1 class="text-gray-900 font-semibold text-[15px]">Dashboard</h1>
                <p class="text-gray-400 text-xs mt-0.5">Overview &amp; recent activity</p>
            </div>
            <span class="text-xs text-gray-400"><?= date('l, d M Y') ?></span>
        </header>

        <main class="flex-1 p-8 space-y-8">

            <!-- ── Stat Cards ── -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">

                <!-- Total Revenue -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <p class="text-sm font-medium text-gray-500">Total Revenue</p>
                        <div class="w-10 h-10 bg-[#5C1A2A]/8 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-[#5C1A2A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 tracking-tight">
                        ₹<?= number_format($total_revenue, 2) ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-1.5">All-time gross revenue</p>
                </div>

                <!-- Active Products -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <p class="text-sm font-medium text-gray-500">Active Products</p>
                        <div class="w-10 h-10 bg-[#D4A853]/10 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-[#D4A853]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 tracking-tight"><?= $active_products ?></p>
                    <p class="text-xs text-gray-400 mt-1.5">Published in store</p>
                </div>

                <!-- Unread Messages -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <p class="text-sm font-medium text-gray-500">Unread Messages</p>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0
                                    <?= $unread_messages > 0 ? 'bg-red-50' : 'bg-gray-50' ?>">
                            <svg class="w-5 h-5 <?= $unread_messages > 0 ? 'text-red-500' : 'text-gray-400' ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 tracking-tight"><?= $unread_messages ?></p>
                    <a href="messages.php" class="text-xs text-[#5C1A2A] hover:underline mt-1.5 inline-block font-medium">
                        View all messages →
                    </a>
                </div>

            </div>

            <!-- ── Recent Enquiries Table ── -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">Recent Enquiries</h2>
                    <a href="messages.php"
                       class="text-xs font-medium text-[#5C1A2A] hover:text-[#7a2438] transition-colors">
                        View all →
                    </a>
                </div>

                <?php if (empty($enquiries)): ?>
                <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <svg class="w-10 h-10 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm">No enquiries yet.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Sender</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Subject</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($enquiries as $eq): ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="py-3.5 px-6">
                                    <p class="font-semibold text-gray-900 text-[13px]"><?= htmlspecialchars($eq['name']) ?></p>
                                    <p class="text-[11px] text-gray-400 mt-0.5"><?= htmlspecialchars($eq['email']) ?></p>
                                </td>
                                <td class="py-3.5 px-6 text-gray-600 max-w-xs truncate">
                                    <?= htmlspecialchars($eq['subject'] ?: '(no subject)') ?>
                                </td>
                                <td class="py-3.5 px-6 text-gray-400 text-[12px] whitespace-nowrap">
                                    <?= date('d M Y, H:i', strtotime($eq['created_at'])) ?>
                                </td>
                                <td class="py-3.5 px-6">
                                    <?php if ($eq['is_read']): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">
                                        Read
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[#5C1A2A]/8 text-[#5C1A2A]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#5C1A2A] inline-block"></span>
                                        Unread
                                    </span>
                                    <?php endif; ?>
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
