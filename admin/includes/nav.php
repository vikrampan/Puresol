<?php
$_nav_page  = basename($_SERVER['PHP_SELF'], '.php');
$_nav_user  = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin');
$_nav_init  = strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1));
$_nav_role  = htmlspecialchars($_SESSION['admin_role'] ?? 'admin');

$_nav_links = [
    [
        'href'  => 'index.php',
        'label' => 'Dashboard',
        'key'   => 'index',
        'icon'  => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h2a1 1 0 001-1V10',
    ],
    [
        'href'  => 'products.php',
        'label' => 'Products',
        'key'   => 'products',
        'icon'  => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    ],
    [
        'href'  => 'messages.php',
        'label' => 'Messages',
        'key'   => 'messages',
        'icon'  => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    ],
    [
        'href'  => 'blog.php',
        'label' => 'Journal',
        'key'   => 'blog',
        'icon'  => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    ],
    [
        'href'  => 'finances.php',
        'label' => 'Finances',
        'key'   => 'finances',
        'icon'  => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ],
    [
        'href'  => 'subscribers.php',
        'label' => 'Subscribers',
        'key'   => 'subscribers',
        'icon'  => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    ],
    [
        'href'  => 'analytics.php',
        'label' => 'Analytics',
        'key'   => 'analytics',
        'icon'  => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    ],
];
?>
<aside class="w-64 bg-[#5C1A2A] flex flex-col fixed inset-y-0 left-0 z-30 shadow-xl">

    <!-- Logo -->
    <div class="px-6 py-5 border-b border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-[#D4A853] flex items-center justify-center flex-shrink-0">
                <span class="text-[#5C1A2A] font-bold text-sm">P</span>
            </div>
            <div>
                <h1 class="text-white font-semibold text-base leading-none">Puresol</h1>
                <p class="text-white/40 text-[10px] mt-0.5 tracking-widest uppercase">Admin</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <?php foreach ($_nav_links as $link):
            $active = ($_nav_page === $link['key']); ?>
        <a href="<?= $link['href'] ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-[13.5px] font-medium transition-all group
                  <?= $active
                        ? 'bg-white/15 text-white shadow-sm'
                        : 'text-white/60 hover:bg-white/8 hover:text-white' ?>">
            <svg class="w-[17px] h-[17px] flex-shrink-0 transition-colors
                        <?= $active ? 'text-[#D4A853]' : 'text-white/50 group-hover:text-white/80' ?>"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $link['icon'] ?>"/>
            </svg>
            <?= $link['label'] ?>
            <?php if ($active): ?>
            <span class="ml-auto w-1.5 h-1.5 rounded-full bg-[#D4A853] flex-shrink-0"></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- User + Logout -->
    <div class="px-3 pb-4 border-t border-white/10 pt-3 space-y-0.5">
        <div class="flex items-center gap-3 px-3 py-2.5 mb-1">
            <div class="w-8 h-8 rounded-full bg-[#D4A853]/20 border border-[#D4A853]/30 flex items-center justify-center flex-shrink-0">
                <span class="text-[#D4A853] text-xs font-bold"><?= $_nav_init ?></span>
            </div>
            <div class="min-w-0">
                <p class="text-white text-[13px] font-medium truncate"><?= $_nav_user ?></p>
                <p class="text-white/40 text-[11px] capitalize"><?= $_nav_role ?></p>
            </div>
        </div>
        <a href="logout.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-[13.5px] text-white/55 hover:bg-white/8 hover:text-white transition-all">
            <svg class="w-[17px] h-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Sign Out
        </a>
    </div>

</aside>
