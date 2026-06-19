<?php
/**
 * Puresol Finances API
 *
 * GET  /api/finances.php?action=summary        — Dashboard summary
 * GET  /api/finances.php?action=list            — List transactions (filterable)
 * GET  /api/finances.php?action=monthly         — Monthly aggregation
 * GET  /api/finances.php?action=weekly          — Weekly aggregation
 * POST /api/finances.php                        — Add transaction (admin)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = Database::connect();

// All finance endpoints require admin auth
require_admin_auth();

$action = $_GET['action'] ?? 'list';

switch ($method) {

    // -------------------------------------------------------
    // GET — Read financial data
    // -------------------------------------------------------
    case 'GET':

        switch ($action) {

            // -------------------------------------------
            // Dashboard Summary
            // -------------------------------------------
            case 'summary':
                // Total revenue
                $rev_stmt = $db->query(
                    "SELECT COALESCE(SUM(amount), 0) FROM finances WHERE type = 'revenue'"
                );
                $total_revenue = (float) $rev_stmt->fetchColumn();

                // Total expenses
                $exp_stmt = $db->query(
                    "SELECT COALESCE(SUM(amount), 0) FROM finances WHERE type = 'expense'"
                );
                $total_expenses = (float) $exp_stmt->fetchColumn();

                $net_profit = $total_revenue - $total_expenses;

                // Revenue this month
                $month_rev = $db->query(
                    "SELECT COALESCE(SUM(amount), 0) FROM finances
                     WHERE type = 'revenue'
                       AND YEAR(date) = YEAR(CURDATE())
                       AND MONTH(date) = MONTH(CURDATE())"
                )->fetchColumn();

                // Expenses this month
                $month_exp = $db->query(
                    "SELECT COALESCE(SUM(amount), 0) FROM finances
                     WHERE type = 'expense'
                       AND YEAR(date) = YEAR(CURDATE())
                       AND MONTH(date) = MONTH(CURDATE())"
                )->fetchColumn();

                // Pending orders value (overdue / unpaid)
                $overdue = $db->query(
                    "SELECT COALESCE(SUM(total), 0) FROM orders
                     WHERE payment_status = 'pending'
                       AND status NOT IN ('cancelled')"
                )->fetchColumn();

                // Order counts by status
                $order_counts = $db->query(
                    "SELECT status, COUNT(*) as count FROM orders GROUP BY status"
                )->fetchAll();

                $status_map = [];
                foreach ($order_counts as $row) {
                    $status_map[$row['status']] = (int) $row['count'];
                }

                // Recent transactions
                $recent = $db->query(
                    "SELECT id, type, category, description, amount, date, reference_id, created_at
                     FROM finances
                     ORDER BY date DESC, created_at DESC
                     LIMIT 10"
                )->fetchAll();

                foreach ($recent as &$r) {
                    $r['id']     = (int) $r['id'];
                    $r['amount'] = (float) $r['amount'];
                }
                unset($r);

                json_response([
                    'total_revenue'     => $total_revenue,
                    'total_expenses'    => $total_expenses,
                    'net_profit'        => $net_profit,
                    'month_revenue'     => (float) $month_rev,
                    'month_expenses'    => (float) $month_exp,
                    'month_net'         => (float) $month_rev - (float) $month_exp,
                    'overdue_amount'    => (float) $overdue,
                    'order_status_counts' => $status_map,
                    'recent_transactions' => $recent,
                ], 200, 'Financial summary retrieved.');
                break;

            // -------------------------------------------
            // List transactions with date range
            // -------------------------------------------
            case 'list':
                $pagination = get_pagination(25);

                $conditions = [];
                $params     = [];

                if (!empty($_GET['type']) && in_array($_GET['type'], ['revenue', 'expense'], true)) {
                    $conditions[]     = 'type = :type';
                    $params[':type']  = $_GET['type'];
                }

                if (!empty($_GET['category'])) {
                    $conditions[]         = 'category = :category';
                    $params[':category']  = sanitize_input($_GET['category']);
                }

                if (!empty($_GET['date_from'])) {
                    $conditions[]          = 'date >= :date_from';
                    $params[':date_from']  = sanitize_input($_GET['date_from']);
                }

                if (!empty($_GET['date_to'])) {
                    $conditions[]        = 'date <= :date_to';
                    $params[':date_to']  = sanitize_input($_GET['date_to']);
                }

                $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

                $count_stmt = $db->prepare("SELECT COUNT(*) FROM finances {$where}");
                $count_stmt->execute($params);
                $total = (int) $count_stmt->fetchColumn();

                $sql = "SELECT id, type, category, description, amount, date, reference_id, created_at
                        FROM finances
                        {$where}
                        ORDER BY date DESC, created_at DESC
                        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $transactions = $stmt->fetchAll();

                foreach ($transactions as &$t) {
                    $t['id']     = (int) $t['id'];
                    $t['amount'] = (float) $t['amount'];
                }
                unset($t);

                json_response([
                    'transactions' => $transactions,
                    'pagination'   => [
                        'page'        => $pagination['page'],
                        'limit'       => $pagination['limit'],
                        'total'       => $total,
                        'total_pages' => (int) ceil($total / $pagination['limit']),
                    ],
                ], 200, 'Transactions retrieved.');
                break;

            // -------------------------------------------
            // Monthly aggregation
            // -------------------------------------------
            case 'monthly':
                $year = (int) ($_GET['year'] ?? date('Y'));

                $stmt = $db->prepare(
                    "SELECT
                        MONTH(date) AS month,
                        type,
                        COALESCE(SUM(amount), 0) AS total
                     FROM finances
                     WHERE YEAR(date) = :year
                     GROUP BY MONTH(date), type
                     ORDER BY MONTH(date) ASC"
                );
                $stmt->execute([':year' => $year]);
                $rows = $stmt->fetchAll();

                // Build structured monthly data
                $months = [];
                for ($m = 1; $m <= 12; $m++) {
                    $months[$m] = [
                        'month'    => $m,
                        'revenue'  => 0.0,
                        'expense'  => 0.0,
                        'net'      => 0.0,
                    ];
                }

                foreach ($rows as $row) {
                    $m = (int) $row['month'];
                    $months[$m][$row['type']] = (float) $row['total'];
                }

                foreach ($months as &$mo) {
                    $mo['net'] = $mo['revenue'] - $mo['expense'];
                }
                unset($mo);

                json_response([
                    'year'   => $year,
                    'months' => array_values($months),
                ], 200, 'Monthly aggregation retrieved.');
                break;

            // -------------------------------------------
            // Weekly aggregation (last 12 weeks)
            // -------------------------------------------
            case 'weekly':
                $weeks_back = min(52, max(4, (int) ($_GET['weeks'] ?? 12)));

                $stmt = $db->prepare(
                    "SELECT
                        YEARWEEK(date, 1) AS yw,
                        MIN(date) AS week_start,
                        type,
                        COALESCE(SUM(amount), 0) AS total
                     FROM finances
                     WHERE date >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
                     GROUP BY YEARWEEK(date, 1), type
                     ORDER BY yw ASC"
                );
                $stmt->execute([':weeks' => $weeks_back]);
                $rows = $stmt->fetchAll();

                $weeks = [];
                foreach ($rows as $row) {
                    $yw = $row['yw'];
                    if (!isset($weeks[$yw])) {
                        $weeks[$yw] = [
                            'year_week'  => $yw,
                            'week_start' => $row['week_start'],
                            'revenue'    => 0.0,
                            'expense'    => 0.0,
                            'net'        => 0.0,
                        ];
                    }
                    $weeks[$yw][$row['type']] = (float) $row['total'];
                }

                foreach ($weeks as &$w) {
                    $w['net'] = $w['revenue'] - $w['expense'];
                }
                unset($w);

                json_response([
                    'weeks_back' => $weeks_back,
                    'weeks'      => array_values($weeks),
                ], 200, 'Weekly aggregation retrieved.');
                break;

            default:
                json_response(null, 400, 'Invalid action. Use: summary, list, monthly, weekly.');
        }
        break;

    // -------------------------------------------------------
    // POST — Add a financial transaction
    // -------------------------------------------------------
    case 'POST':
        if (!verify_csrf_token()) {
            json_response(null, 403, 'Invalid or missing CSRF token.');
        }

        $body    = get_json_body();
        $missing = validate_required($body, ['type', 'category', 'amount', 'date']);
        if (!empty($missing)) {
            json_response(null, 400, 'Missing required fields: ' . implode(', ', $missing));
        }

        $type = $body['type'];
        if (!in_array($type, ['revenue', 'expense'], true)) {
            json_response(null, 400, 'Type must be "revenue" or "expense".');
        }

        $category    = sanitize_input($body['category']);
        $description = sanitize_input($body['description'] ?? '');
        $amount      = (float) $body['amount'];
        $date        = sanitize_input($body['date']);
        $reference   = sanitize_input($body['reference_id'] ?? '');

        if ($amount <= 0) {
            json_response(null, 400, 'Amount must be greater than zero.');
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            json_response(null, 400, 'Date must be in YYYY-MM-DD format.');
        }

        $stmt = $db->prepare(
            "INSERT INTO finances (type, category, description, amount, date, reference_id)
             VALUES (:type, :category, :description, :amount, :date, :reference_id)"
        );
        $stmt->execute([
            ':type'         => $type,
            ':category'     => $category,
            ':description'  => $description,
            ':amount'       => $amount,
            ':date'         => $date,
            ':reference_id' => $reference ?: null,
        ]);

        $new_id = (int) $db->lastInsertId();
        log_activity('finance_added', "id={$new_id} type={$type} amount={$amount}");

        json_response(['id' => $new_id], 201, 'Transaction recorded successfully.');
        break;

    default:
        json_response(null, 405, 'Method not allowed.');
}
