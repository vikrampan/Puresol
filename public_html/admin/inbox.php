<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/html; charset=UTF-8');
$pageTitle = 'Inbox';
$pageSubtitle = 'Contact form messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox — Puresol Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-main">
            <?php include 'includes/header.php'; ?>

            <div class="admin-content">
                <!-- Toolbar -->
                <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
                    <div class="tab-group">
                        <button class="tab-item active" data-filter="all">All</button>
                        <button class="tab-item" data-filter="unread">Unread <span id="unread-tab-count"></span></button>
                        <button class="tab-item" data-filter="archived">Archived</button>
                    </div>
                    <div class="search-wrapper" style="max-width:16rem;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="message-search" class="form-input" placeholder="Search messages...">
                    </div>
                </div>

                <!-- Messages Layout -->
                <div style="display:grid;grid-template-columns:1fr;gap:1.5rem;" id="inbox-layout">
                    <!-- Message List -->
                    <div class="card" style="padding:0;overflow:hidden;">
                        <div id="message-list" style="max-height:calc(100vh - 16rem);overflow-y:auto;">
                            <div style="padding:2rem;text-align:center;">
                                <div class="skeleton" style="height:3rem;margin-bottom:0.5rem;"></div>
                                <div class="skeleton" style="height:3rem;margin-bottom:0.5rem;"></div>
                                <div class="skeleton" style="height:3rem;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Message Detail -->
                    <div class="card" id="message-detail" style="display:none;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;">
                            <div>
                                <h3 id="detail-subject" style="font-size:1.1rem;font-weight:600;color:var(--text-primary);"></h3>
                                <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.5rem;">
                                    <div style="width:2rem;height:2rem;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;">
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="var(--accent)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </div>
                                    <div>
                                        <div id="detail-name" style="font-size:0.875rem;font-weight:500;color:var(--text-primary);"></div>
                                        <div id="detail-email" style="font-size:0.75rem;color:var(--text-muted);"></div>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <span id="detail-date" style="font-size:0.75rem;color:var(--text-muted);"></span>
                                <button class="btn btn-ghost btn-sm" onclick="closeDetail()" title="Close">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>

                        <div id="detail-body" style="font-size:0.875rem;color:var(--text-secondary);line-height:1.7;white-space:pre-wrap;padding:1rem;background:var(--bg-primary);border-radius:var(--radius-lg);margin-bottom:1.5rem;"></div>

                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                            <button class="btn btn-primary btn-sm" id="reply-btn">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                Reply
                            </button>
                            <button class="btn btn-outline btn-sm" id="mark-read-btn">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5"/></svg>
                                <span id="mark-read-text">Mark as Read</span>
                            </button>
                            <button class="btn btn-outline btn-sm" id="archive-btn">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                Archive
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/admin.js"></script>
    <script>
        function escHtml(s) {
            if (s == null) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // Responsive layout
        const style = document.createElement('style');
        style.textContent = `@media(min-width:1024px){#inbox-layout{grid-template-columns:380px 1fr !important;}#message-detail{display:block !important;}}`;
        document.head.appendChild(style);

        let messages = [];
        let currentFilter = 'all';
        let selectedMessage = null;

        // Filter tabs
        document.querySelectorAll('.tab-item[data-filter]').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab-item[data-filter]').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                renderMessages();
            });
        });

        // Search
        let searchTimer;
        document.getElementById('message-search').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(renderMessages, 250);
        });

        async function loadMessages() {
            try {
                const data = await Admin.apiRequest('/api/messages.php');
                messages = data.messages || data.data || [];
                renderMessages();
                updateUnreadCount();
            } catch (err) {
                messages = getPlaceholderMessages();
                renderMessages();
                updateUnreadCount();
            }
        }

        function getPlaceholderMessages() {
            return [
                { id: 1, name: 'Jessica Moore', email: 'jessica.moore@gmail.com', subject: 'Wholesale inquiry for retail chain', message: 'Hi there,\n\nI am the purchasing manager for a regional health food retail chain with 24 locations across the Pacific Northwest. We are very interested in carrying Puresol products in our stores.\n\nCould you please send me your wholesale pricing catalog and minimum order quantities? We are particularly interested in your Himalayan Pink Salt and Celtic Grey Sea Salt.\n\nLooking forward to hearing from you.\n\nBest regards,\nJessica Moore\nPurchasing Manager, NatureFresh Markets', created_at: new Date(Date.now() - 1800000).toISOString(), is_read: false, is_archived: false },
                { id: 2, name: 'David Kim', email: 'david.k@outlook.com', subject: 'Question about mineral content', message: 'Hello,\n\nI have been using your Himalayan Pink Salt for a few months now and I love it. I was wondering if you could provide more detailed information about the mineral analysis of your products?\n\nSpecifically, I am interested in the magnesium, potassium, and calcium content per serving.\n\nThank you!', created_at: new Date(Date.now() - 7200000).toISOString(), is_read: false, is_archived: false },
                { id: 3, name: 'Rachel Green', email: 'rachel.green@yahoo.com', subject: 'Shipping delay on order PS-1038', message: 'Hi,\n\nMy order PS-1038 was supposed to arrive yesterday but the tracking still shows it in transit. Could you please look into this for me?\n\nOrder number: PS-1038\nTracking: delayed\n\nThanks for your help.', created_at: new Date(Date.now() - 18000000).toISOString(), is_read: true, is_archived: false },
                { id: 4, name: 'Tom Harris', email: 'tom@healthfoodblog.com', subject: 'Partnership and product review proposal', message: 'Dear Puresol Team,\n\nI run HealthFoodBlog.com, a popular health and wellness blog with over 200K monthly readers. We would love to feature your products in an upcoming review article.\n\nWould you be interested in sending some samples for review? We can also discuss sponsored content opportunities.\n\nBest,\nTom Harris\nEditor, HealthFoodBlog.com', created_at: new Date(Date.now() - 86400000).toISOString(), is_read: true, is_archived: false },
                { id: 5, name: 'Maria Santos', email: 'maria.s@company.co', subject: 'Bulk order for corporate gifts', message: 'Hello,\n\nOur company is looking to purchase premium salt gift sets as holiday gifts for our top clients (approximately 150 sets). Do you offer custom packaging or branding options for bulk orders?\n\nPlease let me know the pricing and lead time for such an order.\n\nRegards,\nMaria Santos', created_at: new Date(Date.now() - 172800000).toISOString(), is_read: false, is_archived: false },
                { id: 6, name: 'Alex Thompson', email: 'alex.t@email.com', subject: 'Return request for damaged item', message: 'Hi,\n\nI received my order today but unfortunately the Celtic Grey Sea Salt jar arrived cracked and some of the product leaked during shipping. Could you please arrange a replacement?\n\nI have photos of the damage if needed.\n\nThanks,\nAlex', created_at: new Date(Date.now() - 259200000).toISOString(), is_read: true, is_archived: false },
                { id: 7, name: 'Dr. Susan Lee', email: 'dr.lee@wellness.org', subject: 'Speaking engagement invitation', message: 'Dear Puresol,\n\nI am organizing the Annual Wellness Summit this September and would love to invite a representative from Puresol to speak about the benefits of alkaline minerals.\n\nPlease let me know if you would be interested.\n\nDr. Susan Lee', created_at: new Date(Date.now() - 432000000).toISOString(), is_read: true, is_archived: true },
            ];
        }

        function renderMessages() {
            const query = document.getElementById('message-search').value.toLowerCase().trim();
            const list = document.getElementById('message-list');

            let filtered = messages.filter(m => {
                if (currentFilter === 'unread' && m.is_read) return false;
                if (currentFilter === 'archived' && !m.is_archived) return false;
                if (currentFilter === 'all' && m.is_archived) return false;
                if (query) {
                    const text = `${m.name} ${m.email} ${m.subject} ${m.message}`.toLowerCase();
                    if (!text.includes(query)) return false;
                }
                return true;
            });

            if (!filtered.length) {
                list.innerHTML = '<div class="empty-state" style="padding:3rem;"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg><p>No messages found.</p></div>';
                return;
            }

            list.innerHTML = filtered.map(m => `
                <div class="message-item ${m.is_read ? '' : 'unread'} ${selectedMessage && selectedMessage.id === m.id ? 'selected' : ''}"
                     onclick="selectMessage(${m.id})"
                     style="${selectedMessage && selectedMessage.id === m.id ? 'background:rgba(199,25,90,0.08);' : ''}">
                    ${!m.is_read ? '<div class="message-dot"></div>' : '<div style="width:0.5rem;flex-shrink:0;"></div>'}
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
                            <span class="message-sender">${escHtml(m.name)}</span>
                            <span class="message-time">${Admin.timeAgo(m.created_at)}</span>
                        </div>
                        <div class="message-subject">${escHtml(m.subject)}</div>
                        <div class="message-preview">${escHtml(m.message.substring(0, 100))}...</div>
                    </div>
                </div>
            `).join('');
        }

        function selectMessage(id) {
            const msg = messages.find(m => m.id === id);
            if (!msg) return;
            selectedMessage = msg;

            document.getElementById('detail-subject').textContent = msg.subject;
            document.getElementById('detail-name').textContent = msg.name;
            document.getElementById('detail-email').textContent = msg.email;
            document.getElementById('detail-date').textContent = Admin.formatDateTime(msg.created_at);
            document.getElementById('detail-body').textContent = msg.message;

            // Update mark read button
            const markBtn = document.getElementById('mark-read-text');
            markBtn.textContent = msg.is_read ? 'Mark as Unread' : 'Mark as Read';

            // Show detail panel on mobile
            const detail = document.getElementById('message-detail');
            detail.style.display = 'block';

            // Mark as read automatically
            if (!msg.is_read) {
                markAsRead(id, true);
            }

            // Re-render to show selected state
            renderMessages();

            // Setup reply button
            document.getElementById('reply-btn').onclick = () => {
                window.location.href = `mailto:${msg.email}?subject=Re: ${encodeURIComponent(msg.subject)}`;
            };

            // Setup mark read/unread
            document.getElementById('mark-read-btn').onclick = () => {
                markAsRead(id, !msg.is_read);
            };

            // Setup archive
            document.getElementById('archive-btn').onclick = () => {
                archiveMessage(id);
            };
        }

        function closeDetail() {
            selectedMessage = null;
            document.getElementById('message-detail').style.display = 'none';
            renderMessages();
        }

        async function markAsRead(id, isRead) {
            try {
                await Admin.apiRequest('/api/messages.php', {
                    method: 'PUT',
                    body: JSON.stringify({ id, is_read: isRead })
                });
            } catch (err) { /* silent for demo */ }

            const msg = messages.find(m => m.id === id);
            if (msg) msg.is_read = isRead;

            updateUnreadCount();
            renderMessages();

            if (selectedMessage && selectedMessage.id === id) {
                document.getElementById('mark-read-text').textContent = isRead ? 'Mark as Unread' : 'Mark as Read';
            }
        }

        async function archiveMessage(id) {
            try {
                await Admin.apiRequest('/api/messages.php', {
                    method: 'PUT',
                    body: JSON.stringify({ id, is_archived: true })
                });
            } catch (err) { /* silent */ }

            const msg = messages.find(m => m.id === id);
            if (msg) msg.is_archived = true;

            Admin.toast('Message archived.', 'success');
            if (selectedMessage && selectedMessage.id === id) closeDetail();
            renderMessages();
        }

        function updateUnreadCount() {
            const count = messages.filter(m => !m.is_read && !m.is_archived).length;
            const tabCount = document.getElementById('unread-tab-count');
            if (tabCount) tabCount.textContent = count > 0 ? `(${count})` : '';

            // Also update sidebar badge
            const badge = document.getElementById('sidebar-unread-badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        // Init
        loadMessages();

        // Check URL for specific message
        const urlParams = new URLSearchParams(window.location.search);
        const msgId = urlParams.get('id');
        if (msgId) {
            setTimeout(() => selectMessage(parseInt(msgId)), 500);
        }
    </script>
</body>
</html>
