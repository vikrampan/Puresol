<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — Puresol Admin</title>
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
                <h1 class="text-gray-900 font-semibold text-[15px]">Analytics</h1>
                <p class="text-gray-400 text-xs mt-0.5">Live traffic &middot; SEO &middot; Ad campaigns</p>
            </div>
            <span class="text-xs text-gray-400"><?= date('l, d M Y') ?></span>
        </header>

        <main class="flex-1 p-8 space-y-6">

            <!-- Command Center card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">

                <!-- Card header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">Command Center</h2>
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wider">No engines connected</span>
                </div>

                <!-- Tab bar -->
                <div class="px-6 pt-4">
                    <div class="flex gap-1 p-1 bg-gray-100 rounded-xl w-fit" role="tablist">
                        <button role="tab" data-tab="traffic"
                                class="cc-tab px-4 py-2 rounded-lg text-sm font-medium transition-all bg-white shadow-sm text-gray-900">
                            Live Traffic
                        </button>
                        <button role="tab" data-tab="seo"
                                class="cc-tab px-4 py-2 rounded-lg text-sm font-medium transition-all text-gray-500 hover:text-gray-700">
                            SEO Engine
                        </button>
                        <button role="tab" data-tab="ads"
                                class="cc-tab px-4 py-2 rounded-lg text-sm font-medium transition-all text-gray-500 hover:text-gray-700">
                            Ad Campaigns
                        </button>
                    </div>
                </div>

                <!-- Panels -->
                <div class="p-6 pt-4">

                    <!-- Traffic panel -->
                    <div id="cc-panel-traffic" class="cc-panel">
                        <iframe src="https://lookerstudio.google.com/embed/reporting/938d4811-77d8-46bb-a14a-d598e9493d37/page/kIV1C"
                                width="100%" height="800"
                                class="rounded-xl border-0 shadow-inner"
                                title="Live Traffic" allowfullscreen></iframe>
                    </div>

                    <!-- SEO panel -->
                    <div id="cc-panel-seo" class="cc-panel hidden">
                        <iframe src="https://lookerstudio.google.com/embed/reporting/a8f8f1e6-7ea0-4e0e-9d35-8378fc1c7829/page/q0RuF"
                                width="100%" height="800"
                                class="rounded-xl border-0 shadow-inner"
                                title="SEO Engine" allowfullscreen></iframe>
                    </div>

                    <!-- Ads panel -->
                    <div id="cc-panel-ads" class="cc-panel hidden">
                        <div class="relative rounded-xl border border-dashed border-gray-200 bg-gray-50/50" style="height:520px;">
                            <iframe id="cc-iframe-ads" src="" title="Ad Campaigns"
                                    class="absolute inset-0 w-full h-full rounded-xl border-0 hidden"></iframe>
                            <div id="cc-ph-ads" class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-4">
                                <svg class="w-8 h-8 text-gray-300 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <p class="text-sm font-semibold text-gray-600">Awaiting Engine Connection</p>
                                <p class="text-xs text-gray-400 text-center max-w-xs">
                                    Connect Google Ads, Meta Ads Manager, or any campaign dashboard to track spend and ROAS here.
                                </p>
                                <button onclick="ccConnect('ads')"
                                        class="mt-3 text-xs font-medium text-[#5C1A2A] border border-[#5C1A2A]/20 rounded-lg px-4 py-2 hover:bg-[#5C1A2A]/5 transition-colors">
                                    Connect
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </main>
    </div>
</div>

<script>
    // Tab switching
    document.querySelectorAll('.cc-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;

            // Reset all tabs
            document.querySelectorAll('.cc-tab').forEach(b => {
                b.className = 'cc-tab px-4 py-2 rounded-lg text-sm font-medium transition-all text-gray-500 hover:text-gray-700';
            });
            // Activate clicked tab
            btn.className = 'cc-tab px-4 py-2 rounded-lg text-sm font-medium transition-all bg-white shadow-sm text-gray-900';

            // Show correct panel
            document.querySelectorAll('.cc-panel').forEach(p => p.classList.add('hidden'));
            document.getElementById('cc-panel-' + target).classList.remove('hidden');
        });
    });

    // Connect an iframe engine
    function ccConnect(tab) {
        const hints = {
            traffic: 'e.g. https://analytics.google.com/...',
            seo:     'e.g. https://app.ahrefs.com/...',
            ads:     'e.g. https://ads.google.com/...',
        };
        const url = prompt('Enter the dashboard URL to embed:\n(' + hints[tab] + ')');
        if (!url || !url.startsWith('http')) return;

        document.getElementById('cc-iframe-' + tab).src = url;
        document.getElementById('cc-iframe-' + tab).classList.remove('hidden');
        document.getElementById('cc-ph-'     + tab).classList.add('hidden');
    }
</script>

</body>
</html>
