<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: text/html; charset=UTF-8');

$db      = Database::connect();
$success = '';
$error   = '';

// ── Handle POST (add entry) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $action      = $_POST['action'] ?? '';
        $type        = $_POST['type'] ?? '';
        $category    = trim($_POST['category'] ?? '');
        $amount      = (float) ($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $entry_date  = trim($_POST['entry_date'] ?? date('Y-m-d'));

        if ($action === 'add') {
            if (!in_array($type, ['revenue', 'expense'], true)) {
                $error = 'Type must be revenue or expense.';
            } elseif ($category === '') {
                $error = 'Category is required.';
            } elseif ($amount <= 0) {
                $error = 'Amount must be greater than zero.';
            } elseif ($description === '') {
                $error = 'Description is required.';
            } else {
                $db->prepare(
                    "INSERT INTO finances (type, category, amount, description, date)
                     VALUES (:type, :category, :amount, :description, :date)"
                )->execute([
                    ':type'        => $type,
                    ':category'    => $category,
                    ':amount'      => $amount,
                    ':description' => $description,
                    ':date'        => $entry_date,
                ]);
                $success = ucfirst($type) . " entry of ₹" . number_format($amount, 2) . " added.";
            }

        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM finances WHERE id = :id")->execute([':id' => $id]);
                $success = 'Entry deleted.';
            }
        }
    }
}

// ── Summary totals ────────────────────────────────────────────
$total_revenue  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM finances WHERE type='revenue'")->fetchColumn();
$total_expenses = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM finances WHERE type='expense'")->fetchColumn();
$balance        = $total_revenue - $total_expenses;

// ── Ledger entries (most recent first) ───────────────────────
$filter = $_GET['filter'] ?? 'all';
$whereMap = [
    'all'     => '',
    'revenue' => "WHERE type='revenue'",
    'expense' => "WHERE type='expense'",
];
$where   = $whereMap[$filter] ?? '';
$entries = $db->query(
    "SELECT id, type, amount, description, date, created_at
     FROM finances
     {$where}
     ORDER BY date DESC, created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finances — Puresol Admin</title>
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
                <h1 class="text-gray-900 font-semibold text-[15px]">Finances</h1>
                <p class="text-gray-400 text-xs mt-0.5">Revenue &amp; expense ledger</p>
            </div>
            <button onclick="document.getElementById('add-form-panel').classList.toggle('hidden')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#5C1A2A] text-white text-sm font-medium rounded-xl hover:bg-[#7a2438] transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add Entry
            </button>
        </header>

        <main class="flex-1 p-8 space-y-6">

            <?php if ($success): ?>
            <div class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-100 rounded-xl text-green-700 text-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-red-700 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- ── Summary Cards ── -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">

                <!-- Revenue -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-sm font-medium text-gray-500">Total Revenue</p>
                        <div class="w-9 h-9 bg-green-50 rounded-xl flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">₹<?= number_format($total_revenue, 2) ?></p>
                </div>

                <!-- Expenses -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-sm font-medium text-gray-500">Total Expenses</p>
                        <div class="w-9 h-9 bg-red-50 rounded-xl flex items-center justify-center">
                            <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">₹<?= number_format($total_expenses, 2) ?></p>
                </div>

                <!-- Net Balance -->
                <div class="rounded-2xl border p-6 shadow-sm
                            <?= $balance >= 0
                                  ? 'bg-[#5C1A2A] border-[#5C1A2A]'
                                  : 'bg-white border-red-200' ?>">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-sm font-medium <?= $balance >= 0 ? 'text-white/70' : 'text-gray-500' ?>">Net Balance</p>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center
                                    <?= $balance >= 0 ? 'bg-white/15' : 'bg-red-50' ?>">
                            <svg class="w-4 h-4 <?= $balance >= 0 ? 'text-[#D4A853]' : 'text-red-500' ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold <?= $balance >= 0 ? 'text-white' : 'text-red-600' ?>">
                        <?= $balance < 0 ? '−' : '' ?>₹<?= number_format(abs($balance), 2) ?>
                    </p>
                    <p class="text-xs mt-1 <?= $balance >= 0 ? 'text-white/50' : 'text-red-400' ?>">
                        <?= $balance >= 0 ? 'Profitable' : 'In deficit' ?>
                    </p>
                </div>

            </div>

            <!-- ── Add Entry Form ── -->
            <div id="add-form-panel" class="<?= $error ? '' : 'hidden' ?> bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-gray-800 mb-5">New Finance Entry</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Type *</label>
                            <select name="type" required
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 bg-white outline-none focus:border-[#5C1A2A] focus:ring-4 focus:ring-[#5C1A2A]/8 transition-all">
                                <option value="revenue" <?= ($_POST['type'] ?? '') === 'revenue' ? 'selected' : '' ?>>Revenue</option>
                                <option value="expense" <?= ($_POST['type'] ?? '') === 'expense' ? 'selected' : '' ?>>Expense</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Category *</label>
                            <input type="text" name="category" required
                                   value="<?= htmlspecialchars($_POST['category'] ?? '') ?>"
                                   placeholder="e.g. order, salary, rent"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 outline-none focus:border-[#5C1A2A] focus:ring-4 focus:ring-[#5C1A2A]/8 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Amount (₹) *</label>
                            <input type="number" name="amount" step="0.01" min="0.01" required
                                   value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                                   placeholder="0.00"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 outline-none focus:border-[#5C1A2A] focus:ring-4 focus:ring-[#5C1A2A]/8 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Date *</label>
                            <input type="date" name="entry_date" required
                                   value="<?= htmlspecialchars($_POST['entry_date'] ?? date('Y-m-d')) ?>"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 outline-none focus:border-[#5C1A2A] focus:ring-4 focus:ring-[#5C1A2A]/8 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Description *</label>
                            <input type="text" name="description" required
                                   value="<?= htmlspecialchars($_POST['description'] ?? '') ?>"
                                   placeholder="e.g. Product sale - batch #12"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 outline-none focus:border-[#5C1A2A] focus:ring-4 focus:ring-[#5C1A2A]/8 transition-all">
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                        <button type="submit"
                                class="px-5 py-2.5 bg-[#5C1A2A] text-white text-sm font-semibold rounded-xl hover:bg-[#7a2438] transition-colors shadow-sm">
                            Add Entry
                        </button>
                        <button type="button"
                                onclick="document.getElementById('add-form-panel').classList.add('hidden')"
                                class="px-5 py-2.5 border border-gray-200 text-gray-600 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── Ledger ── -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                    <h2 class="text-sm font-semibold text-gray-800">Ledger</h2>
                    <div class="flex items-center gap-1 bg-gray-50 rounded-lg border border-gray-100 p-1">
                        <?php foreach (['all' => 'All', 'revenue' => 'Revenue', 'expense' => 'Expenses'] as $key => $label): ?>
                        <a href="?filter=<?= $key ?>"
                           class="px-3 py-1.5 rounded-md text-[12px] font-medium transition-all
                                  <?= $filter === $key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800' ?>">
                            <?= $label ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (empty($entries)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                    <svg class="w-10 h-10 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm">No entries yet. Add your first entry above.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Description</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="py-3 px-6 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Amount</th>
                                <th class="py-3 px-6 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($entries as $entry): ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">

                                <td class="py-3.5 px-6 text-gray-500 text-[12px] whitespace-nowrap">
                                    <?= date('d M Y', strtotime($entry['date'])) ?>
                                </td>

                                <td class="py-3.5 px-6 text-gray-800 font-medium">
                                    <?= htmlspecialchars($entry['description']) ?>
                                </td>

                                <td class="py-3.5 px-6">
                                    <?php if ($entry['type'] === 'revenue'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-green-50 text-green-700">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                                        </svg>
                                        Revenue
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-red-50 text-red-600">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
                                        </svg>
                                        Expense
                                    </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-6 text-right font-semibold
                                           <?= $entry['type'] === 'revenue' ? 'text-green-700' : 'text-red-600' ?>">
                                    <?= $entry['type'] === 'expense' ? '−' : '+' ?>₹<?= number_format((float)$entry['amount'], 2) ?>
                                </td>

                                <td class="py-3.5 px-6 text-right">
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this entry?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
                                        <button type="submit"
                                                class="px-3 py-1.5 text-[12px] font-medium text-red-500 border border-red-100 rounded-lg hover:bg-red-50 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>

                        <!-- Running totals footer -->
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 bg-gray-50/50">
                                <td colspan="3" class="py-4 px-6 text-sm font-semibold text-gray-700">
                                    <?= $filter === 'all' ? 'Net Balance' : ($filter === 'revenue' ? 'Total Revenue' : 'Total Expenses') ?>
                                </td>
                                <td class="py-4 px-6 text-right text-base font-bold
                                           <?= ($filter === 'expense') ? 'text-red-600' : ($balance >= 0 ? 'text-green-700' : 'text-red-600') ?>">
                                    <?php if ($filter === 'revenue'): ?>
                                    +₹<?= number_format($total_revenue, 2) ?>
                                    <?php elseif ($filter === 'expense'): ?>
                                    −₹<?= number_format($total_expenses, 2) ?>
                                    <?php else: ?>
                                    <?= $balance < 0 ? '−' : '+' ?>₹<?= number_format(abs($balance), 2) ?>
                                    <?php endif; ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

</body>
</html>
