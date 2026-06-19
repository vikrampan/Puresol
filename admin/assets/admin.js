/* ========================================
   PURESOL ADMIN — Core JavaScript
   ======================================== */

const Admin = (() => {
    'use strict';

    // ---- CSRF Token ----
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // ---- Sidebar Toggle ----
    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const hamburger = document.getElementById('hamburger-btn');

        if (!sidebar) return;

        const open = () => {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        };

        const close = () => {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        };

        if (hamburger) hamburger.addEventListener('click', open);
        if (overlay) overlay.addEventListener('click', close);

        // Close on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) close();
        });
    }

    // ---- Modal Helpers ----
    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        // Focus first input
        setTimeout(() => {
            const firstInput = modal.querySelector('input, textarea, select');
            if (firstInput) firstInput.focus();
        }, 100);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function initModals() {
        // Close on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Close buttons
        document.querySelectorAll('.modal-close, [data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal-overlay');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Escape key
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                const active = document.querySelector('.modal-overlay.active');
                if (active) {
                    active.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });
    }

    // ---- Toast Notifications ----
    function toast(message, type = 'info', duration = 4000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const icons = {
            success: '<svg class="toast-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
            error: '<svg class="toast-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
            warning: '<svg class="toast-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 3l9.5 16.5H2.5L12 3z"/></svg>',
            info: '<svg class="toast-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01"/></svg>'
        };

        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.innerHTML = `${icons[type] || icons.info}<span>${message}</span>`;
        container.appendChild(el);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => el.classList.add('show'));
        });

        setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 300);
        }, duration);
    }

    // ---- Fetch Helper ----
    async function apiRequest(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
        };

        // Merge headers
        if (options.headers) {
            defaults.headers = { ...defaults.headers, ...options.headers };
        }

        // If sending FormData, remove Content-Type so browser sets boundary
        if (options.body instanceof FormData) {
            delete defaults.headers['Content-Type'];
        }

        const config = { ...defaults, ...options, headers: { ...defaults.headers, ...(options.headers || {}) } };

        if (options.body instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || `Request failed (${response.status})`);
            }

            return data;
        } catch (err) {
            if (err.name === 'TypeError' && err.message === 'Failed to fetch') {
                throw new Error('Network error. Please check your connection.');
            }
            throw err;
        }
    }

    // ---- Form Submission ----
    function initFormHandler(formId, url, { method = 'POST', onSuccess, onError, useFormData = false } = {}) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"/><path d="M12 2a10 10 0 019.95 9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> Saving...';
            }

            try {
                let body;
                if (useFormData) {
                    body = new FormData(form);
                } else {
                    const formData = new FormData(form);
                    body = JSON.stringify(Object.fromEntries(formData));
                }

                const data = await apiRequest(url, { method, body });

                if (onSuccess) onSuccess(data);
                else toast('Saved successfully!', 'success');
            } catch (err) {
                if (onError) onError(err);
                else toast(err.message, 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    }

    // ---- Table Sorting ----
    function initTableSort(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const headers = table.querySelectorAll('thead th[data-sort]');
        let currentSort = { column: null, dir: 'asc' };

        headers.forEach(th => {
            th.addEventListener('click', () => {
                const column = th.dataset.sort;
                const dir = currentSort.column === column && currentSort.dir === 'asc' ? 'desc' : 'asc';
                currentSort = { column, dir };

                // Update UI
                headers.forEach(h => h.classList.remove('sorted'));
                th.classList.add('sorted');

                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr:not(.expand-row)'));

                rows.sort((a, b) => {
                    const aVal = a.querySelector(`td[data-col="${column}"]`)?.textContent.trim() || a.cells[th.cellIndex]?.textContent.trim() || '';
                    const bVal = b.querySelector(`td[data-col="${column}"]`)?.textContent.trim() || b.cells[th.cellIndex]?.textContent.trim() || '';

                    // Try numeric
                    const aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
                    const bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return dir === 'asc' ? aNum - bNum : bNum - aNum;
                    }

                    return dir === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                });

                rows.forEach(row => tbody.appendChild(row));
            });
        });
    }

    // ---- Search / Filter ----
    function initSearch(inputId, targetSelector, searchFields = []) {
        const input = document.getElementById(inputId);
        if (!input) return;

        let debounceTimer;
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const query = input.value.toLowerCase().trim();
                const items = document.querySelectorAll(targetSelector);

                items.forEach(item => {
                    if (!query) {
                        item.style.display = '';
                        return;
                    }

                    let text = '';
                    if (searchFields.length > 0) {
                        searchFields.forEach(field => {
                            const el = item.querySelector(`[data-search="${field}"]`);
                            if (el) text += ' ' + el.textContent;
                        });
                    } else {
                        text = item.textContent;
                    }

                    item.style.display = text.toLowerCase().includes(query) ? '' : 'none';
                });
            }, 250);
        });
    }

    // ---- Image Upload Preview ----
    function initImagePreview(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!input || !preview) return;

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) {
                preview.innerHTML = '';
                return;
            }

            if (!file.type.startsWith('image/')) {
                toast('Please select an image file.', 'warning');
                input.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                toast('Image must be under 5MB.', 'warning');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = e => {
                preview.innerHTML = `<img src="${e.target.result}" class="image-preview" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        });
    }

    // ---- Tag Input ----
    function initTagInput(containerId, inputId, hiddenId) {
        const container = document.getElementById(containerId);
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        if (!container || !input) return;

        let tags = [];

        // Load existing tags
        if (hidden && hidden.value) {
            try {
                tags = JSON.parse(hidden.value);
                renderTags();
            } catch (e) { /* ignore */ }
        }

        function renderTags() {
            container.querySelectorAll('.tag').forEach(el => el.remove());
            tags.forEach((tag, i) => {
                const el = document.createElement('span');
                el.className = 'tag';
                el.innerHTML = `${tag}<button type="button" data-index="${i}">&times;</button>`;
                container.insertBefore(el, input);
            });
            if (hidden) hidden.value = JSON.stringify(tags);
        }

        input.addEventListener('keydown', e => {
            if ((e.key === 'Enter' || e.key === ',') && input.value.trim()) {
                e.preventDefault();
                const val = input.value.trim().replace(/,/g, '');
                if (val && !tags.includes(val)) {
                    tags.push(val);
                    renderTags();
                }
                input.value = '';
            }
            if (e.key === 'Backspace' && !input.value && tags.length > 0) {
                tags.pop();
                renderTags();
            }
        });

        container.addEventListener('click', e => {
            if (e.target.closest('.tag button')) {
                const idx = parseInt(e.target.closest('button').dataset.index);
                tags.splice(idx, 1);
                renderTags();
            } else {
                input.focus();
            }
        });

        return {
            getTags: () => [...tags],
            setTags: (newTags) => { tags = [...newTags]; renderTags(); },
            clear: () => { tags = []; renderTags(); }
        };
    }

    // ---- Pagination Helper ----
    function renderPagination(containerId, currentPage, totalPages, onChange) {
        const container = document.getElementById(containerId);
        if (!container) return;

        let html = '';
        html += `<button ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">&laquo;</button>`;

        const range = 2;
        let start = Math.max(1, currentPage - range);
        let end = Math.min(totalPages, currentPage + range);

        if (start > 1) {
            html += `<button data-page="1">1</button>`;
            if (start > 2) html += `<button disabled>...</button>`;
        }

        for (let i = start; i <= end; i++) {
            html += `<button data-page="${i}" class="${i === currentPage ? 'active' : ''}">${i}</button>`;
        }

        if (end < totalPages) {
            if (end < totalPages - 1) html += `<button disabled>...</button>`;
            html += `<button data-page="${totalPages}">${totalPages}</button>`;
        }

        html += `<button ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">&raquo;</button>`;

        container.innerHTML = html;

        container.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page);
                if (page >= 1 && page <= totalPages && page !== currentPage) {
                    onChange(page);
                }
            });
        });
    }

    // ---- Confirm Dialog ----
    function confirm(message, { title = 'Confirm', confirmText = 'Confirm', cancelText = 'Cancel', danger = false } = {}) {
        return new Promise(resolve => {
            // Remove existing
            const existing = document.getElementById('confirm-dialog');
            if (existing) existing.remove();

            const overlay = document.createElement('div');
            overlay.id = 'confirm-dialog';
            overlay.className = 'modal-overlay active';
            overlay.innerHTML = `
                <div class="modal" style="max-width: 420px;">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button class="modal-close" id="confirm-cancel-x">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline" id="confirm-cancel-btn">${cancelText}</button>
                        <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" id="confirm-ok-btn">${confirmText}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            const cleanup = (result) => {
                overlay.remove();
                document.body.style.overflow = '';
                resolve(result);
            };

            overlay.querySelector('#confirm-ok-btn').addEventListener('click', () => cleanup(true));
            overlay.querySelector('#confirm-cancel-btn').addEventListener('click', () => cleanup(false));
            overlay.querySelector('#confirm-cancel-x').addEventListener('click', () => cleanup(false));
            overlay.addEventListener('click', e => { if (e.target === overlay) cleanup(false); });
        });
    }

    // ---- Format Helpers ----
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatDateTime(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function timeAgo(dateStr) {
        const now = new Date();
        const d = new Date(dateStr);
        const seconds = Math.floor((now - d) / 1000);

        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
        return formatDate(dateStr);
    }

    // ---- Debounce ----
    function debounce(fn, delay = 300) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    // ---- Init ----
    function init() {
        initSidebar();
        initModals();
    }

    // Auto-init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API
    return {
        openModal,
        closeModal,
        toast,
        apiRequest,
        initFormHandler,
        initTableSort,
        initSearch,
        initImagePreview,
        initTagInput,
        renderPagination,
        confirm,
        formatCurrency,
        formatDate,
        formatDateTime,
        timeAgo,
        debounce,
    };
})();
