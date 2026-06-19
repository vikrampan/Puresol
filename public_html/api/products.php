<?php
/**
 * Puresol Products API
 *
 * GET    /api/products.php             — List all active products
 * GET    /api/products.php?slug=...    — Single product by slug
 * GET    /api/products.php?id=...      — Single product by id
 * POST   /api/products.php             — Create product (admin)
 * PUT    /api/products.php?id=...      — Update product (admin)
 * DELETE /api/products.php?id=...      — Soft-delete product (admin)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = Database::connect();

switch ($method) {

    // -------------------------------------------------------
    // GET — Retrieve products
    // -------------------------------------------------------
    case 'GET':
        $slug = $_GET['slug'] ?? null;
        $id   = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if ($slug) {
            $stmt = $db->prepare(
                "SELECT id, name, slug, tagline, description, price, compare_price,
                        stock, sku, image_url, badge, features, is_active, sort_order,
                        created_at, updated_at
                 FROM products
                 WHERE slug = :slug AND is_active = 1
                 LIMIT 1"
            );
            $stmt->execute([':slug' => $slug]);
            $product = $stmt->fetch();

            if (!$product) {
                json_response(null, 404, 'Product not found.');
            }

            $product['id']    = (int) $product['id'];
            $product['price'] = (float) $product['price'];
            $product['compare_price'] = $product['compare_price'] ? (float) $product['compare_price'] : null;
            $product['stock'] = (int) $product['stock'];
            $product['features'] = json_decode($product['features'], true);
            $product['is_active'] = (bool) $product['is_active'];

            json_response($product, 200, 'Product retrieved.');

        } elseif ($id) {
            // Admin can fetch any product by ID (including inactive)
            $where_active = is_admin_authenticated() ? '' : 'AND is_active = 1';

            $stmt = $db->prepare(
                "SELECT id, name, slug, tagline, description, price, compare_price,
                        stock, sku, image_url, badge, features, is_active, sort_order,
                        created_at, updated_at
                 FROM products
                 WHERE id = :id {$where_active}
                 LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $product = $stmt->fetch();

            if (!$product) {
                json_response(null, 404, 'Product not found.');
            }

            $product['id']    = (int) $product['id'];
            $product['price'] = (float) $product['price'];
            $product['compare_price'] = $product['compare_price'] ? (float) $product['compare_price'] : null;
            $product['stock'] = (int) $product['stock'];
            $product['features'] = json_decode($product['features'], true);
            $product['is_active'] = (bool) $product['is_active'];

            json_response($product, 200, 'Product retrieved.');

        } else {
            // List all active products (admin sees all)
            $show_all = is_admin_authenticated() && isset($_GET['all']);
            $where    = $show_all ? '' : 'WHERE is_active = 1';

            $stmt = $db->query(
                "SELECT id, name, slug, tagline, price, compare_price,
                        stock, sku, image_url, badge, features, is_active, sort_order,
                        created_at, updated_at
                 FROM products
                 {$where}
                 ORDER BY sort_order ASC, created_at DESC"
            );
            $products = $stmt->fetchAll();

            foreach ($products as &$p) {
                $p['id']    = (int) $p['id'];
                $p['price'] = (float) $p['price'];
                $p['compare_price'] = $p['compare_price'] ? (float) $p['compare_price'] : null;
                $p['stock'] = (int) $p['stock'];
                $p['features'] = json_decode($p['features'], true);
                $p['is_active'] = (bool) $p['is_active'];
            }
            unset($p);

            json_response($products, 200, 'Products retrieved.');
        }
        break;

    // -------------------------------------------------------
    // POST — Create product
    // -------------------------------------------------------
    case 'POST':
        require_admin_auth();

        if (!verify_csrf_token()) {
            json_response(null, 403, 'Invalid or missing CSRF token.');
        }

        $body    = get_json_body();
        $missing = validate_required($body, ['name', 'price']);
        if (!empty($missing)) {
            json_response(null, 400, 'Missing required fields: ' . implode(', ', $missing));
        }

        $name          = sanitize_input($body['name']);
        $slug          = isset($body['slug']) && $body['slug'] !== '' ? create_slug($body['slug']) : create_slug($name);
        $tagline       = sanitize_input($body['tagline'] ?? '');
        $description   = sanitize_input($body['description'] ?? '');
        $price         = (float) $body['price'];
        $compare_price = isset($body['compare_price']) ? (float) $body['compare_price'] : null;
        $stock         = (int) ($body['stock'] ?? 0);
        $sku           = sanitize_input($body['sku'] ?? '');
        $image_url     = sanitize_input($body['image_url'] ?? '');
        $badge         = sanitize_input($body['badge'] ?? '');
        $features      = isset($body['features']) ? json_encode($body['features']) : null;
        $is_active     = (int) ($body['is_active'] ?? 1);
        $sort_order    = (int) ($body['sort_order'] ?? 0);

        // Check slug uniqueness
        $check = $db->prepare("SELECT id FROM products WHERE slug = :slug LIMIT 1");
        $check->execute([':slug' => $slug]);
        if ($check->fetch()) {
            json_response(null, 409, 'A product with this slug already exists.');
        }

        $stmt = $db->prepare(
            "INSERT INTO products
             (name, slug, tagline, description, price, compare_price, stock, sku,
              image_url, badge, features, is_active, sort_order)
             VALUES
             (:name, :slug, :tagline, :description, :price, :compare_price, :stock, :sku,
              :image_url, :badge, :features, :is_active, :sort_order)"
        );

        $stmt->execute([
            ':name'          => $name,
            ':slug'          => $slug,
            ':tagline'       => $tagline,
            ':description'   => $description,
            ':price'         => $price,
            ':compare_price' => $compare_price,
            ':stock'         => $stock,
            ':sku'           => $sku ?: null,
            ':image_url'     => $image_url ?: null,
            ':badge'         => $badge ?: null,
            ':features'      => $features,
            ':is_active'     => $is_active,
            ':sort_order'    => $sort_order,
        ]);

        $new_id = (int) $db->lastInsertId();
        log_activity('product_created', "id={$new_id} name={$name}");

        json_response(['id' => $new_id, 'slug' => $slug], 201, 'Product created successfully.');
        break;

    // -------------------------------------------------------
    // PUT — Update product
    // -------------------------------------------------------
    case 'PUT':
        require_admin_auth();

        if (!verify_csrf_token()) {
            json_response(null, 403, 'Invalid or missing CSRF token.');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            json_response(null, 400, 'Product ID is required.');
        }

        // Verify product exists
        $check = $db->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            json_response(null, 404, 'Product not found.');
        }

        $body = get_json_body();
        if (empty($body)) {
            json_response(null, 400, 'No data provided for update.');
        }

        $allowed = [
            'name', 'slug', 'tagline', 'description', 'price', 'compare_price',
            'stock', 'sku', 'image_url', 'badge', 'features', 'is_active', 'sort_order',
        ];

        $sets   = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }

            $value = $body[$field];

            if ($field === 'features' && is_array($value)) {
                $value = json_encode($value);
            } elseif ($field === 'slug') {
                $value = create_slug($value);
            } elseif (in_array($field, ['name', 'tagline', 'description', 'sku', 'image_url', 'badge'], true)) {
                $value = sanitize_input((string) $value);
            } elseif (in_array($field, ['price', 'compare_price'], true)) {
                $value = (float) $value;
            } elseif (in_array($field, ['stock', 'is_active', 'sort_order'], true)) {
                $value = (int) $value;
            }

            $sets[]             = "`{$field}` = :{$field}";
            $params[":{$field}"] = $value;
        }

        if (empty($sets)) {
            json_response(null, 400, 'No valid fields to update.');
        }

        $sql = "UPDATE products SET " . implode(', ', $sets) . " WHERE id = :id";
        $db->prepare($sql)->execute($params);

        log_activity('product_updated', "id={$id}");
        json_response(['id' => $id], 200, 'Product updated successfully.');
        break;

    // -------------------------------------------------------
    // DELETE — Soft-delete (set is_active = 0)
    // -------------------------------------------------------
    case 'DELETE':
        require_admin_auth();

        if (!verify_csrf_token()) {
            json_response(null, 403, 'Invalid or missing CSRF token.');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            json_response(null, 400, 'Product ID is required.');
        }

        $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            json_response(null, 404, 'Product not found.');
        }

        log_activity('product_deleted', "id={$id}");
        json_response(null, 200, 'Product deactivated successfully.');
        break;

    default:
        json_response(null, 405, 'Method not allowed.');
}
