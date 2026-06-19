<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadCount = 0;
// Fetch unread message count
try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    // We'll use JS to fetch this dynamically
} catch (Exception $e) {}
?>
<!-- Sidebar Overlay (mobile) -->
<div id="sidebar-overlay" class="sidebar-overlay"></div>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar">
    <div class="sidebar-logo">
        <h1>Puresol</h1>
        <span>Admin Panel</span>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/>
            </svg>
            Dashboard
        </a>

        <a href="products.php" class="<?= $currentPage === 'products' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            Products
        </a>

        <a href="orders.php" class="<?= $currentPage === 'orders' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            Orders
        </a>

        <a href="inbox.php" class="<?= $currentPage === 'inbox' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Inbox
            <span class="nav-badge" id="sidebar-unread-badge" style="display:none;">0</span>
        </a>

    </nav>

    <div class="sidebar-footer">
        <a href="/api/auth.php?action=logout">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Logout
        </a>
    </div>
</aside>

<script>
// Fetch unread count for sidebar badge
(async function() {
    try {
        const res = await fetch('/api/messages.php?count_unread=1');
        const data = await res.json();
        if (data.unread_count && data.unread_count > 0) {
            const badge = document.getElementById('sidebar-unread-badge');
            if (badge) {
                badge.textContent = data.unread_count;
                badge.style.display = '';
            }
        }
    } catch(e) { /* silent */ }
})();
</script>
