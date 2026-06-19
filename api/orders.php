<?php
/**
 * Puresol Orders API
 *
 * GET  /api/orders.php                — List orders with filters (admin)
 * GET  /api/orders.php?id=...         — Single order (admin)
 * POST /api/orders.php                — Create order (public checkout)
 * PUT  /api/orders.php?id=...         — Update order status (admin)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = Database::connect();

switch ($method) {

    // -------------------------------------------------------
    // GET — List / single order (admin only)
    // -------------------------------------------------------
    case 'GET':
        require_admin_auth();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $stmt = $db->prepare(
                "SELECT id, order_number, customer_name, customer_email, customer_phone,
                        address, city, state, pincode, items, subtotal, shipping, total,
                        status, payment_method, payment_status, notes, created_at, updated_at
                 FROM orders
                 WHERE id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch();

            if (!$order) {
                json_response(null, 404, 'Order not found.');
            }

            $order['id']       = (int) $order['id'];
            $order['items']    = json_decode($order['items'], true);
            $order['subtotal'] = (float) $order['subtotal'];
            $order['shipping'] = (float) $order['shipping'];
            $order['total']    = (float) $order['total'];

            json_response($order, 200, 'Order retrieved.');
        }

        // List with filters
        $pagination = get_pagination(20);

        $conditions = [];
        $params     = [];

        // Status filter
        if (!empty($_GET['status'])) {
            $conditions[]       = 'status = :status';
            $params[':status']  = sanitize_input($_GET['status']);
        }

        // Payment status filter
        if (!empty($_GET['payment_status'])) {
            $conditions[]               = 'payment_status = :payment_status';
            $params[':payment_status']  = sanitize_input($_GET['payment_status']);
        }

        // Date range
        if (!empty($_GET['date_from'])) {
            $conditions[]          = 'DATE(created_at) >= :date_from';
            $params[':date_from']  = sanitize_input($_GET['date_from']);
        }
        if (!empty($_GET['date_to'])) {
            $conditions[]        = 'DATE(created_at) <= :date_to';
            $params[':date_to']  = sanitize_input($_GET['date_to']);
        }

        // Search by customer name, email, or order number
        if (!empty($_GET['search'])) {
            $search_term       = '%' . sanitize_input($_GET['search']) . '%';
            $conditions[]      = '(customer_name LIKE :search OR customer_email LIKE :search2 OR order_number LIKE :search3)';
            $params[':search']  = $search_term;
            $params[':search2'] = $search_term;
            $params[':search3'] = $search_term;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count
        $count_sql  = "SELECT COUNT(*) FROM orders {$where}";
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        // Fetch
        $sql = "SELECT id, order_number, customer_name, customer_email, customer_phone,
                       city, state, items, subtotal, shipping, total,
                       status, payment_method, payment_status, created_at, updated_at
                FROM orders
                {$where}
                ORDER BY created_at DESC
                LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$o) {
            $o['id']       = (int) $o['id'];
            $o['items']    = json_decode($o['items'], true);
            $o['subtotal'] = (float) $o['subtotal'];
            $o['shipping'] = (float) $o['shipping'];
            $o['total']    = (float) $o['total'];
        }
        unset($o);

        json_response([
            'orders'     => $orders,
            'pagination' => [
                'page'        => $pagination['page'],
                'limit'       => $pagination['limit'],
                'total'       => $total,
                'total_pages' => (int) ceil($total / $pagination['limit']),
            ],
        ], 200, 'Orders retrieved.');
        break;

    // -------------------------------------------------------
    // POST — Create order (public checkout)
    // -------------------------------------------------------
    case 'POST':
        $body    = get_json_body();
        $missing = validate_required($body, [
            'customer_name', 'customer_email', 'customer_phone',
            'address', 'city', 'state', 'pincode', 'items',
        ]);

        if (!empty($missing)) {
            json_response(null, 400, 'Missing required fields: ' . implode(', ', $missing));
        }

        $customer_name  = sanitize_input($body['customer_name']);
        $customer_email = sanitize_input($body['customer_email']);
        $customer_phone = sanitize_input($body['customer_phone']);
        $address        = sanitize_input($body['address']);
        $city           = sanitize_input($body['city']);
        $state          = sanitize_input($body['state']);
        $pincode        = sanitize_input($body['pincode']);
        $items          = $body['items']; // array of {product_id, name, price, quantity}
        $payment_method = sanitize_input($body['payment_method'] ?? 'cod');
        $notes          = sanitize_input($body['notes'] ?? '');

        if (!validate_email($customer_email)) {
            json_response(null, 400, 'Invalid email address.');
        }

        if (!preg_match('/^[0-9]{6}$/', $pincode)) {
            json_response(null, 400, 'Invalid pincode. Must be 6 digits.');
        }

        if (!is_array($items) || empty($items)) {
            json_response(null, 400, 'At least one item is required.');
        }

        // Calculate totals
        $subtotal = 0;
        $validated_items = [];

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                json_response(null, 400, 'Each item must have product_id and quantity.');
            }

            $product_stmt = $db->prepare(
                "SELECT id, name, price, stock FROM products WHERE id = :id AND is_active = 1 LIMIT 1"
            );
            $product_stmt->execute([':id' => (int) $item['product_id']]);
            $product = $product_stmt->fetch();

            if (!$product) {
                json_response(null, 400, 'Product not found: ID ' . (int) $item['product_id']);
            }

            $qty = max(1, (int) $item['quantity']);

            if ($qty > (int) $product['stock']) {
                json_response(null, 400, "Insufficient stock for {$product['name']}. Available: {$product['stock']}.");
            }

            $line_total = (float) $product['price'] * $qty;
            $subtotal  += $line_total;

            $validated_items[] = [
                'product_id' => (int) $product['id'],
                'name'       => $product['name'],
                'price'      => (float) $product['price'],
                'quantity'   => $qty,
                'line_total' => $line_total,
            ];
        }

        // Fetch shipping settings
        $ship_stmt   = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'shipping_charge' LIMIT 1");
        $ship_stmt->execute();
        $shipping_charge = (float) ($ship_stmt->fetchColumn() ?: 49.00);

        $free_stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'free_shipping_above' LIMIT 1");
        $free_stmt->execute();
        $free_above = (float) ($free_stmt->fetchColumn() ?: 999.00);

        $shipping = $subtotal >= $free_above ? 0.00 : $shipping_charge;
        $total    = $subtotal + $shipping;

        $order_id     = null;
        $order_number = '';

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $db->beginTransaction();

            try {
                // Sequence read is inside the transaction so it and the INSERT
                // are committed atomically. On a duplicate-key retry, $attempt > 0
                // adds an offset that guarantees a different candidate number.
                $order_number = generate_order_number($db, $attempt);
                $stmt = $db->prepare(
                    "INSERT INTO orders
                     (order_number, customer_name, customer_email, customer_phone,
                      address, city, state, pincode, items, subtotal, shipping, total,
                      status, payment_method, payment_status, notes)
                     VALUES
                     (:order_number, :customer_name, :customer_email, :customer_phone,
                      :address, :city, :state, :pincode, :items, :subtotal, :shipping, :total,
                      'pending', :payment_method, 'pending', :notes)"
                );
                $stmt->execute([
                    ':order_number'   => $order_number,
                    ':customer_name'  => $customer_name,
                    ':customer_email' => $customer_email,
                    ':customer_phone' => $customer_phone,
                    ':address'        => $address,
                    ':city'           => $city,
                    ':state'          => $state,
                    ':pincode'        => $pincode,
                    ':items'          => json_encode($validated_items),
                    ':subtotal'       => $subtotal,
                    ':shipping'       => $shipping,
                    ':total'          => $total,
                    ':payment_method' => $payment_method,
                    ':notes'          => $notes,
                ]);

                $order_id = (int) $db->lastInsertId();

                // Decrease stock
                foreach ($validated_items as $vi) {
                    $db->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id")
                       ->execute([':qty' => $vi['quantity'], ':id' => $vi['product_id']]);
                }

                // Record revenue in finances
                $db->prepare(
                    "INSERT INTO finances (type, category, description, amount, date, reference_id)
                     VALUES ('revenue', 'order', :desc, :amount, CURDATE(), :ref)"
                )->execute([
                    ':desc'   => "Order {$order_number}",
                    ':amount' => $total,
                    ':ref'    => $order_number,
                ]);

                $db->commit();
                break; // success — exit the retry loop

            } catch (\PDOException $e) {
                $db->rollBack();
                // SQLSTATE 23000 = integrity constraint violation (duplicate order_number).
                // Retry up to 2 more times with a bumped sequence; fail on anything else.
                if ((string) $e->getCode() === '23000' && $attempt < 2) {
                    continue;
                }
                log_activity('order_failed', $e->getMessage());
                json_response(null, 500, 'Failed to place order. Please try again.');
            } catch (\Exception $e) {
                $db->rollBack();
                log_activity('order_failed', $e->getMessage());
                json_response(null, 500, 'Failed to place order. Please try again.');
            }
        }

        log_activity('order_created', "id={$order_id} number={$order_number}");

        json_response([
            'order_id'     => $order_id,
            'order_number' => $order_number,
            'total'        => $total,
            'status'       => 'pending',
        ], 201, 'Order placed successfully.');
        break;

    // -------------------------------------------------------
    // PUT — Update order status (admin)
    // -------------------------------------------------------
    case 'PUT':
        require_admin_auth();

        if (!verify_csrf_token()) {
            json_response(null, 403, 'Invalid or missing CSRF token.');
        }

        $body = get_json_body();
        if (empty($body)) {
            json_response(null, 400, 'No data provided.');
        }

        // Accept the order ID from the query string (REST style) or the JSON body
        // (required for JS clients that send PUT without a query-string id).
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($body['id']) ? (int) $body['id'] : 0);
        if ($id <= 0) {
            json_response(null, 400, 'Order ID is required.');
        }

        $valid_statuses  = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        $valid_payments  = ['pending', 'paid', 'failed', 'refunded'];

        $sets   = [];
        $params = [':id' => $id];

        if (isset($body['status'])) {
            if (!in_array($body['status'], $valid_statuses, true)) {
                json_response(null, 400, 'Invalid order status.');
            }
            $sets[]             = 'status = :status';
            $params[':status']  = $body['status'];
        }

        if (isset($body['payment_status'])) {
            if (!in_array($body['payment_status'], $valid_payments, true)) {
                json_response(null, 400, 'Invalid payment status.');
            }
            $sets[]                     = 'payment_status = :payment_status';
            $params[':payment_status']  = $body['payment_status'];
        }

        if (isset($body['notes'])) {
            $sets[]            = 'notes = :notes';
            $params[':notes']  = sanitize_input($body['notes']);
        }

        if (empty($sets)) {
            json_response(null, 400, 'No valid fields to update.');
        }

        $sql  = "UPDATE orders SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            json_response(null, 404, 'Order not found.');
        }

        // If cancelled, restore stock
        if (isset($body['status']) && $body['status'] === 'cancelled') {
            $order_stmt = $db->prepare("SELECT items FROM orders WHERE id = :id LIMIT 1");
            $order_stmt->execute([':id' => $id]);
            $order_items = json_decode($order_stmt->fetchColumn(), true);

            if (is_array($order_items)) {
                foreach ($order_items as $oi) {
                    $db->prepare("UPDATE products SET stock = stock + :qty WHERE id = :id")
                       ->execute([':qty' => $oi['quantity'], ':id' => $oi['product_id']]);
                }
            }
        }

        log_activity('order_updated', "id={$id}");
        json_response(null, 200, 'Order updated successfully.');
        break;

    default:
        json_response(null, 405, 'Method not allowed.');
}
