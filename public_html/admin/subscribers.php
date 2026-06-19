<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: text/html; charset=UTF-8');

$db = Database::connect();

$total_count = (int) $db->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();

$subscribers = $db->query(
    "SELECT id, email, created_at
     FROM subscribers
     ORDER BY created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribers — Puresol Admin</title>
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
                <h1 class="text-gray-900 font-semibold text-[15px]">Subscribers</h1>
                <p class="text-gray-400 text-xs mt-0.5">Newsletter sign-ups</p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#5C1A2A]/8 text-[#5C1A2A]">
                <span class="w-1.5 h-1.5 rounded-full bg-[#5C1A2A]"></span>
                <?= $total_count ?> total
            </span>
        </header>

        <main class="flex-1 p-8">

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">

                <?php if (empty($subscribers)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                    <svg class="w-10 h-10 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p class="text-sm">No subscribers yet.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">#</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Email</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Subscribed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($subscribers as $i => $sub): ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="py-3.5 px-6 text-gray-400 text-[12px]"><?= $total_count - $i ?></td>
                                <td class="py-3.5 px-6">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-[#5C1A2A]/8 flex items-center justify-center flex-shrink-0">
                                            <span class="text-[#5C1A2A] text-[11px] font-bold">
                                                <?= strtoupper(substr($sub['email'], 0, 1)) ?>
                                            </span>
                                        </div>
                                        <a href="mailto:<?= htmlspecialchars($sub['email']) ?>"
                                           class="text-gray-800 font-medium hover:text-[#5C1A2A] transition-colors text-[13px]">
                                            <?= htmlspecialchars($sub['email']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="py-3.5 px-6 text-gray-400 text-[12px] whitespace-nowrap">
                                    <?= date('d M Y, H:i', strtotime($sub['created_at'])) ?>
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
