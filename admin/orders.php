<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/html; charset=UTF-8');
$pageTitle = 'Orders';
$pageSubtitle = 'Track and manage customer orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — Puresol Admin</title>
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
                    <div class="search-wrapper" style="flex:1;max-width:20rem;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="order-search" class="form-input" placeholder="Search by order # or customer...">
                    </div>
                </div>

                <!-- Status Tabs -->
                <div class="tab-group" style="margin-bottom:1.5rem;">
                    <button class="tab-item active" data-status="all">All</button>
                    <button class="tab-item" data-status="pending">Pending</button>
                    <button class="tab-item" data-status="confirmed">Confirmed</button>
                    <button class="tab-item" data-status="shipped">Shipped</button>
                    <button class="tab-item" data-status="delivered">Delivered</button>
                    <button class="tab-item" data-status="cancelled">Cancelled</button>
                </div>

                <!-- Orders Table -->
                <div class="card">
                    <div class="table-responsive">
                        <table class="data-table" id="orders-table">
                            <thead>
                                <tr>
                                    <th style="width:36px;"></th>
                                    <th data-sort="order_number">Order #</th>
                                    <th data-sort="customer">Customer</th>
                                    <th data-sort="date">Date</th>
                                    <th data-sort="items">Items</th>
                                    <th data-sort="total">Total</th>
                                    <th data-sort="status">Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="orders-body">
                                <tr><td colspan="8" style="padding:3rem;text-align:center;"><div class="skeleton" style="height:1rem;width:40%;margin:0 auto;"></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination" id="orders-pagination"></div>
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

        let allOrders = [];
        let filteredOrders = [];
        let currentStatus = 'all';
        let currentPage = 1;
        const perPage = 15;
        let expandedRow = null;

        // Status tabs
        document.querySelectorAll('.tab-item[data-status]').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab-item[data-status]').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentStatus = this.dataset.status;
                currentPage = 1;
                filterAndRender();
            });
        });

        // Search
        let searchTimer;
        document.getElementById('order-search').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                currentPage = 1;
                filterAndRender();
            }, 250);
        });

        // Init table sort
        Admin.initTableSort('orders-table');

        function filterAndRender() {
            const query = document.getElementById('order-search').value.toLowerCase().trim();

            filteredOrders = allOrders.filter(o => {
                if (currentStatus !== 'all' && o.status !== currentStatus) return false;
                if (query) {
                    const searchStr = `${o.order_number} ${o.customer_name} ${o.customer_email || ''}`.toLowerCase();
                    if (!searchStr.includes(query)) return false;
                }
                return true;
            });

            renderOrders();
            renderPagination();
        }

        function renderOrders() {
            const tbody = document.getElementById('orders-body');
            const start = (currentPage - 1) * perPage;
            const pageOrders = filteredOrders.slice(start, start + perPage);

            if (!pageOrders.length) {
                tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg><p>No orders found.</p></div></td></tr>';
                return;
            }

            tbody.innerHTML = pageOrders.map(o => `
                <tr class="order-row" data-order-id="${o.id}">
                    <td>
                        <button class="btn btn-ghost" style="padding:0.25rem;" onclick="toggleOrderDetails(${o.id})">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" id="chevron-${o.id}" style="transition:transform 0.2s;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </td>
                    <td><span style="font-weight:600;color:var(--text-primary);">${escHtml(o.order_number)}</span></td>
                    <td>
                        <div style="font-weight:500;color:var(--text-primary);">${escHtml(o.customer_name)}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">${escHtml(o.customer_email || '')}</div>
                    </td>
                    <td>${Admin.formatDate(o.created_at)}</td>
                    <td>${o.items_count || (o.items ? o.items.length : '—')} items</td>
                    <td style="font-weight:600;color:var(--text-primary);">${Admin.formatCurrency(o.total)}</td>
                    <td><span class="badge badge-${escHtml(o.status)}">${escHtml(o.status)}</span></td>
                    <td>
                        <select class="form-input" style="padding:0.35rem 1.75rem 0.35rem 0.5rem;font-size:0.75rem;width:auto;min-width:7rem;" onchange="updateStatus(${o.id}, this.value, this)" data-current="${escHtml(o.status)}">
                            <option value="" disabled selected>Update...</option>
                            <option value="pending" ${o.status === 'pending' ? 'disabled' : ''}>Pending</option>
                            <option value="confirmed" ${o.status === 'confirmed' ? 'disabled' : ''}>Confirmed</option>
                            <option value="shipped" ${o.status === 'shipped' ? 'disabled' : ''}>Shipped</option>
                            <option value="delivered" ${o.status === 'delivered' ? 'disabled' : ''}>Delivered</option>
                            <option value="cancelled" ${o.status === 'cancelled' ? 'disabled' : ''}>Cancelled</option>
                        </select>
                    </td>
                </tr>
                <tr class="expand-row" id="details-${o.id}">
                    <td colspan="8" style="padding:0 1rem 1rem;">
                        <div class="expand-content">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                                <div>
                                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem;">Shipping Address</div>
                                    <div style="font-size:0.85rem;color:var(--text-secondary);">${escHtml(o.shipping_address || o.address || 'N/A')}</div>
                                </div>
                                <div>
                                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem;">Phone</div>
                                    <div style="font-size:0.85rem;color:var(--text-secondary);">${escHtml(o.phone || 'N/A')}</div>
                                </div>
                                <div>
                                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem;">Payment Method</div>
                                    <div style="font-size:0.85rem;color:var(--text-secondary);">${escHtml(o.payment_method || 'N/A')}</div>
                                </div>
                                <div>
                                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem;">Notes</div>
                                    <div style="font-size:0.85rem;color:var(--text-secondary);">${escHtml(o.notes || 'None')}</div>
                                </div>
                            </div>
                            ${o.items && o.items.length ? `
                                <div style="margin-top:1rem;">
                                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.5rem;">Order Items</div>
                                    <table style="width:100%;font-size:0.825rem;">
                                        <thead><tr style="border-bottom:1px solid var(--border-color);">
                                            <th style="padding:0.375rem 0.5rem;text-align:left;color:var(--text-muted);font-weight:500;">Product</th>
                                            <th style="padding:0.375rem 0.5rem;text-align:right;color:var(--text-muted);font-weight:500;">Qty</th>
                                            <th style="padding:0.375rem 0.5rem;text-align:right;color:var(--text-muted);font-weight:500;">Price</th>
                                            <th style="padding:0.375rem 0.5rem;text-align:right;color:var(--text-muted);font-weight:500;">Subtotal</th>
                                        </tr></thead>
                                        <tbody>${o.items.map(item => `
                                            <tr style="border-bottom:1px solid var(--border-color);">
                                                <td style="padding:0.375rem 0.5rem;color:var(--text-secondary);">${escHtml(item.name)}</td>
                                                <td style="padding:0.375rem 0.5rem;text-align:right;color:var(--text-secondary);">${item.quantity}</td>
                                                <td style="padding:0.375rem 0.5rem;text-align:right;color:var(--text-secondary);">${Admin.formatCurrency(item.price)}</td>
                                                <td style="padding:0.375rem 0.5rem;text-align:right;color:var(--text-primary);font-weight:500;">${Admin.formatCurrency(item.quantity * item.price)}</td>
                                            </tr>
                                        `).join('')}</tbody>
                                    </table>
                                </div>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function toggleOrderDetails(id) {
            const row = document.getElementById(`details-${id}`);
            const chevron = document.getElementById(`chevron-${id}`);
            if (!row) return;

            if (expandedRow && expandedRow !== id) {
                const prevRow = document.getElementById(`details-${expandedRow}`);
                const prevChevron = document.getElementById(`chevron-${expandedRow}`);
                if (prevRow) prevRow.classList.remove('open');
                if (prevChevron) prevChevron.style.transform = '';
            }

            if (row.classList.contains('open')) {
                row.classList.remove('open');
                if (chevron) chevron.style.transform = '';
                expandedRow = null;
            } else {
                row.classList.add('open');
                if (chevron) chevron.style.transform = 'rotate(90deg)';
                expandedRow = id;
            }
        }

        async function updateStatus(id, newStatus, selectEl) {
            if (!newStatus) return;

            const confirmed = await Admin.confirm(
                `Change order status to <strong>${newStatus}</strong>?`,
                { title: 'Update Order Status', confirmText: 'Update', danger: newStatus === 'cancelled' }
            );

            if (!confirmed) {
                selectEl.value = '';
                return;
            }

            try {
                await Admin.apiRequest('/api/orders.php', {
                    method: 'PUT',
                    body: JSON.stringify({ id, status: newStatus })
                });

                const order = allOrders.find(o => o.id === id);
                if (order) order.status = newStatus;

                Admin.toast(`Order updated to ${newStatus}.`, 'success');
                filterAndRender();
            } catch (err) {
                Admin.toast(err.message, 'error');
                selectEl.value = '';
            }
        }

        function renderPagination() {
            const totalPages = Math.ceil(filteredOrders.length / perPage);
            if (totalPages <= 1) {
                document.getElementById('orders-pagination').innerHTML = '';
                return;
            }
            Admin.renderPagination('orders-pagination', currentPage, totalPages, (page) => {
                currentPage = page;
                renderOrders();
                renderPagination();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // Load orders
        async function loadOrders() {
            try {
                const data = await Admin.apiRequest('/api/orders.php');
                allOrders = data.orders || data.data || [];
                filterAndRender();
            } catch (err) {
                allOrders = getPlaceholderOrders();
                filterAndRender();
            }
        }

        function getPlaceholderOrders() {
            const statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
            const names = ['Sarah Johnson', 'Michael Chen', 'Emma Williams', 'James Brown', 'Olivia Davis', 'Liam Wilson', 'Sophia Martinez', 'Noah Anderson', 'Ava Thomas', 'Mason Taylor', 'Isabella Moore', 'Lucas Garcia', 'Mia Robinson', 'Ethan Clark', 'Charlotte Lewis', 'Alexander Walker', 'Amelia Hall', 'Benjamin Allen', 'Harper Young', 'Daniel King'];
            const products = [
                { name: 'Himalayan Pink Salt', price: 24.99 },
                { name: 'Dead Sea Salt Blend', price: 32.99 },
                { name: 'Celtic Grey Sea Salt', price: 28.99 },
                { name: 'Persian Blue Salt', price: 44.99 },
            ];

            return Array.from({ length: 42 }, (_, i) => {
                const itemCount = Math.floor(Math.random() * 3) + 1;
                const items = Array.from({ length: itemCount }, () => {
                    const p = products[Math.floor(Math.random() * products.length)];
                    return { ...p, quantity: Math.floor(Math.random() * 3) + 1 };
                });
                const total = items.reduce((s, it) => s + it.price * it.quantity, 0);

                return {
                    id: i + 1,
                    order_number: `PS-${String(1050 - i).padStart(4, '0')}`,
                    customer_name: names[i % names.length],
                    customer_email: names[i % names.length].toLowerCase().replace(' ', '.') + '@email.com',
                    created_at: new Date(Date.now() - i * 86400000 * (0.5 + Math.random())).toISOString(),
                    items_count: itemCount,
                    items: items,
                    total: total.toFixed(2),
                    status: statuses[Math.floor(Math.random() * statuses.length)],
                    shipping_address: '123 Main St, Suite ' + (i + 1) + ', New York, NY 10001',
                    phone: '+1 (555) ' + String(100 + i).padStart(3, '0') + '-' + String(4000 + i).padStart(4, '0'),
                    payment_method: Math.random() > 0.5 ? 'Credit Card' : 'PayPal',
                    notes: i % 3 === 0 ? 'Gift wrapping requested' : '',
                };
            });
        }

        // Init
        loadOrders();
    </script>
</body>
</html>
