<?php
/**
 * Puresol Posts (Journal) API — read-only public endpoint.
 *
 * GET /api/posts.php              — List published posts (newest first)
 * GET /api/posts.php?limit=6      — Limit results (1–50, default 12)
 * GET /api/posts.php?category=... — Filter by category
 * GET /api/posts.php?slug=...     — Single published post (full body)
 *
 * Used by blog.html to render the live "Latest Articles" grid. Writes are
 * handled in the admin panel (admin/blog.php), not here.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(null, 405, 'Method not allowed.');
}

$db   = Database::connect();
$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://puresol.in';

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'])) : null;

if ($slug) {
    $stmt = $db->prepare("SELECT * FROM posts WHERE slug = :slug AND status = 'published' LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $p = $stmt->fetch();
    if (!$p) {
        json_response(null, 404, 'Post not found.');
    }
    $p['id']   = (int) $p['id'];
    $p['url']  = $base . '/blog/' . rawurlencode($p['slug']);
    $p['tags'] = array_values(array_filter(array_map('trim', explode(',', (string) $p['tags']))));
    json_response($p, 200, 'Post retrieved.');
}

$limit = isset($_GET['limit']) ? max(1, min(50, (int) $_GET['limit'])) : 12;
$category = isset($_GET['category']) ? trim($_GET['category']) : null;

$where  = "status = 'published'";
$params = [];
if ($category && strtolower($category) !== 'all') {
    $where .= " AND category = :category";
    $params[':category'] = $category;
}

$sql = "SELECT id, title, slug, excerpt, cover_image, cover_alt, category, tags,
               author, published_at, views
        FROM posts
        WHERE {$where}
        ORDER BY published_at DESC
        LIMIT {$limit}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$posts = array_map(function ($r) use ($base) {
    return [
        'id'           => (int) $r['id'],
        'title'        => $r['title'],
        'slug'         => $r['slug'],
        'url'          => $base . '/blog/' . rawurlencode($r['slug']),
        'excerpt'      => $r['excerpt'],
        'cover_image'  => $r['cover_image'],
        'cover_alt'    => $r['cover_alt'] ?: $r['title'],
        'category'     => $r['category'],
        'tags'         => array_values(array_filter(array_map('trim', explode(',', (string) $r['tags'])))),
        'author'       => $r['author'],
        'published_at' => $r['published_at'],
        'date_human'   => $r['published_at'] ? date('M j, Y', strtotime($r['published_at'])) : '',
        'views'        => (int) $r['views'],
    ];
}, $rows);

json_response($posts, 200, 'Posts retrieved.');
