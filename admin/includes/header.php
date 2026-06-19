<?php
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($pageSubtitle)) $pageSubtitle = '';
$adminName = $_SESSION['admin_username'] ?? 'Admin';
?>
<!-- Header -->
<header class="admin-header">
    <div class="header-inner">
        <div class="header-left">
            <button id="hamburger-btn" class="hamburger-btn" aria-label="Toggle sidebar">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="header-title">
                <h2><?= htmlspecialchars($pageTitle) ?></h2>
                <?php if ($pageSubtitle): ?>
                    <p><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="header-right">
            <a href="products.php?action=add" class="btn btn-primary btn-sm" onclick="event.preventDefault(); if(typeof openProductModal === 'function') openProductModal(); else window.location='products.php';">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add Product
            </a>

            <div style="display:flex;align-items:center;gap:0.5rem;margin-left:0.5rem;padding-left:0.75rem;border-left:1px solid var(--border-color);">
                <div style="width:2rem;height:2rem;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="var(--accent)" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <span style="font-size:0.8rem;color:var(--text-secondary);font-weight:500;"><?= htmlspecialchars($adminName) ?></span>
            </div>
        </div>
    </div>
</header>
