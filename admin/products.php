<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: text/html; charset=UTF-8');

$db      = Database::connect();
$success = '';
$error   = '';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {

        $action = $_POST['action'] ?? '';

        // ── DELETE (soft delete) ──
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);

            if ($id > 0) {
                $db->prepare("UPDATE products SET is_active = 0 WHERE id = :id")
                   ->execute([':id' => $id]);

                $success = 'Product deactivated.';
            }

        // ── ADD ──
        } elseif ($action === 'add') {

            $name  = trim($_POST['name'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $stock = (int)   ($_POST['stock'] ?? 0);

            if ($name === '' || $price <= 0 || $stock < 0) {
                $error = 'Invalid product data.';
            } else {

                // Generate slug
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
                $slug = trim($slug, '-');

                // Ensure unique slug
                $check = $db->prepare("SELECT slug FROM products WHERE slug LIKE :s");
                $check->execute([':s' => $slug . '%']);
                $existing = $check->fetchAll(PDO::FETCH_COLUMN);

                if (in_array($slug, $existing)) {
                    $i = 2;
                    while (in_array($slug . '-' . $i, $existing)) {
                        $i++;
                    }
                    $slug .= '-' . $i;
                }

                $image_url = trim($_POST['image_url'] ?? '');
                if ($image_url && !filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $error = 'Invalid image URL.';
                } else {

                    $compare   = $_POST['compare_price'] !== '' ? (float) $_POST['compare_price'] : null;
                    $tagline   = trim($_POST['tagline'] ?? '');
                    $badge     = trim($_POST['badge'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;

                    $stmt = $db->prepare(
                        "INSERT INTO products (name, slug, tagline, price, compare_price, stock, image_url, badge, is_active)
                         VALUES (:name, :slug, :tagline, :price, :compare_price, :stock, :image_url, :badge, :is_active)"
                    );

                    $stmt->execute([
                        ':name'          => $name,
                        ':slug'          => $slug,
                        ':tagline'       => $tagline ?: null,
                        ':price'         => $price,
                        ':compare_price' => $compare,
                        ':stock'         => $stock,
                        ':image_url'     => $image_url ?: null,
                        ':badge'         => $badge ?: null,
                        ':is_active'     => $is_active,
                    ]);

                    $success = 'Product "' . $name . '" added successfully.';
                }
            }

        // ── EDIT ──
        } elseif ($action === 'edit') {

            $id    = (int) ($_POST['id'] ?? 0);
            $name  = trim($_POST['name'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $stock = (int)   ($_POST['stock'] ?? 0);

            if ($id <= 0 || $name === '' || $price <= 0 || $stock < 0) {
                $error = 'Invalid update data.';
            } else {

                $image_url = trim($_POST['image_url'] ?? '');
                if ($image_url && !filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $error = 'Invalid image URL.';
                } else {

                    $compare   = $_POST['compare_price'] !== '' ? (float) $_POST['compare_price'] : null;
                    $tagline   = trim($_POST['tagline'] ?? '');
                    $badge     = trim($_POST['badge'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;

                    $db->prepare(
                        "UPDATE products
                         SET name=:name, tagline=:tagline, price=:price, compare_price=:compare_price,
                             stock=:stock, image_url=:image_url, badge=:badge, is_active=:is_active
                         WHERE id=:id"
                    )->execute([
                        ':name'          => $name,
                        ':tagline'       => $tagline ?: null,
                        ':price'         => $price,
                        ':compare_price' => $compare,
                        ':stock'         => $stock,
                        ':image_url'     => $image_url ?: null,
                        ':badge'         => $badge ?: null,
                        ':is_active'     => $is_active,
                        ':id'            => $id,
                    ]);

                    $success = 'Product updated.';
                }
            }
        }
    }
}

// ── Load product for editing ──────────────────────────────────
$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];

    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);

    $editing = $stmt->fetch() ?: null;
}

// ── Fetch all products ────────────────────────────────────────
$products = $db->query(
    "SELECT id, name, slug, tagline, price, compare_price, stock, image_url, badge, is_active, created_at
     FROM products
     ORDER BY sort_order ASC, created_at DESC"
)->fetchAll();
?>