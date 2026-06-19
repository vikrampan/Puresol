<?php
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/sitemap.php';
header('Content-Type: text/html; charset=UTF-8');

$db      = Database::connect();
$success = '';
$error   = '';

/** Build a unique, URL-safe slug from a title. */
function make_slug(PDO $db, string $title, int $ignoreId = 0): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'post-' . date('YmdHis');
    }

    $check = $db->prepare("SELECT slug FROM posts WHERE slug LIKE :s AND id <> :id");
    $check->execute([':s' => $slug . '%', ':id' => $ignoreId]);
    $existing = $check->fetchAll(PDO::FETCH_COLUMN);

    if (in_array($slug, $existing, true)) {
        $i = 2;
        while (in_array($slug . '-' . $i, $existing, true)) {
            $i++;
        }
        $slug .= '-' . $i;
    }
    return $slug;
}

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── DELETE (hard delete — also removes it from the sitemap) ──
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM posts WHERE id = :id")->execute([':id' => $id]);
                regenerate_sitemap($db);
                $success = 'Post deleted.';
            }

        // ── QUICK PUBLISH / UNPUBLISH TOGGLE ──
        } elseif ($action === 'toggle_status') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $cur = $db->prepare("SELECT status, published_at FROM posts WHERE id = :id");
                $cur->execute([':id' => $id]);
                $row = $cur->fetch();
                if ($row) {
                    if ($row['status'] === 'published') {
                        $db->prepare("UPDATE posts SET status='draft' WHERE id=:id")->execute([':id' => $id]);
                        $success = 'Post moved to draft.';
                    } else {
                        $pub = $row['published_at'] ?: date('Y-m-d H:i:s');
                        $db->prepare("UPDATE posts SET status='published', published_at=:p WHERE id=:id")
                           ->execute([':p' => $pub, ':id' => $id]);
                        $success = 'Post published.';
                    }
                    regenerate_sitemap($db);
                }
            }

        // ── ADD or UPDATE ──
        } elseif ($action === 'add' || $action === 'update') {

            $id        = (int) ($_POST['id'] ?? 0);
            $title     = trim($_POST['title'] ?? '');
            $body      = trim($_POST['body'] ?? '');
            $category  = trim($_POST['category'] ?? 'Salt Science');
            $excerpt   = trim($_POST['excerpt'] ?? '');
            $cover     = trim($_POST['cover_image'] ?? '');
            $coverAlt  = trim($_POST['cover_alt'] ?? '');
            $tags      = trim($_POST['tags'] ?? '');
            $metaTitle = trim($_POST['meta_title'] ?? '');
            $metaDesc  = trim($_POST['meta_description'] ?? '');
            $metaKw    = trim($_POST['meta_keywords'] ?? '');
            $author    = trim($_POST['author'] ?? '') ?: 'Puresol Editorial';
            $status    = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

            if ($title === '' || $body === '') {
                $error = 'Title and body are required.';
            } elseif ($cover && !filter_var($cover, FILTER_VALIDATE_URL) && $cover[0] !== '/') {
                $error = 'Cover image must be a valid URL or a path starting with /.';
            } else {
                // Auto-fill excerpt / meta description from the body if left blank
                $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
                if ($excerpt === '')  { $excerpt  = mb_substr($plain, 0, 180) . (mb_strlen($plain) > 180 ? '…' : ''); }
                if ($metaDesc === '') { $metaDesc = mb_substr($plain, 0, 155) . (mb_strlen($plain) > 155 ? '…' : ''); }

                if ($action === 'add') {
                    $slug = make_slug($db, $title);
                    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

                    $db->prepare(
                        "INSERT INTO posts
                            (title, slug, excerpt, body, cover_image, cover_alt, category, tags,
                             meta_title, meta_description, meta_keywords, author, status, published_at)
                         VALUES
                            (:title, :slug, :excerpt, :body, :cover, :cover_alt, :category, :tags,
                             :meta_title, :meta_desc, :meta_kw, :author, :status, :published_at)"
                    )->execute([
                        ':title' => $title, ':slug' => $slug, ':excerpt' => $excerpt, ':body' => $body,
                        ':cover' => $cover ?: null, ':cover_alt' => $coverAlt ?: null,
                        ':category' => $category, ':tags' => $tags ?: null,
                        ':meta_title' => $metaTitle ?: null, ':meta_desc' => $metaDesc ?: null,
                        ':meta_kw' => $metaKw ?: null, ':author' => $author,
                        ':status' => $status, ':published_at' => $publishedAt,
                    ]);
                    $success = $status === 'published' ? 'Post published.' : 'Draft saved.';

                } else { // update
                    // Preserve original published_at unless first publish
                    $prev = $db->prepare("SELECT status, published_at FROM posts WHERE id = :id");
                    $prev->execute([':id' => $id]);
                    $prevRow = $prev->fetch();
                    $publishedAt = $prevRow['published_at'] ?? null;
                    if ($status === 'published' && empty($publishedAt)) {
                        $publishedAt = date('Y-m-d H:i:s');
                    }

                    $db->prepare(
                        "UPDATE posts SET
                            title=:title, excerpt=:excerpt, body=:body, cover_image=:cover,
                            cover_alt=:cover_alt, category=:category, tags=:tags,
                            meta_title=:meta_title, meta_description=:meta_desc, meta_keywords=:meta_kw,
                            author=:author, status=:status, published_at=:published_at
                         WHERE id=:id"
                    )->execute([
                        ':title' => $title, ':excerpt' => $excerpt, ':body' => $body,
                        ':cover' => $cover ?: null, ':cover_alt' => $coverAlt ?: null,
                        ':category' => $category, ':tags' => $tags ?: null,
                        ':meta_title' => $metaTitle ?: null, ':meta_desc' => $metaDesc ?: null,
                        ':meta_kw' => $metaKw ?: null, ':author' => $author,
                        ':status' => $status, ':published_at' => $publishedAt, ':id' => $id,
                    ]);
                    $success = 'Post updated.';
                }
                regenerate_sitemap($db);
            }
        }
    }
}

// ── Load post for editing ─────────────────────────────────────
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}
$showForm = isset($_GET['new']) || $editing !== null;

// ── Fetch all posts ───────────────────────────────────────────
$posts = $db->query(
    "SELECT id, title, slug, category, status, published_at, views, updated_at
     FROM posts
     ORDER BY (status='published') DESC, COALESCE(published_at, updated_at) DESC"
)->fetchAll();

$categories = ['Salt Science', 'Wellness', 'Ayurveda', 'Nutrition', 'Sustainability', 'Recipes', 'Company'];
$csrf = $_SESSION['csrf_token'];
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal — Puresol Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased">
<div class="flex min-h-screen">

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="flex-1 ml-64 flex flex-col min-h-screen">

        <header class="h-16 bg-white border-b border-gray-100 flex items-center justify-between px-8 sticky top-0 z-20">
            <div>
                <h1 class="text-gray-900 font-semibold text-[15px]">The Journal</h1>
                <p class="text-gray-400 text-xs mt-0.5">Write &amp; publish SEO blog articles</p>
            </div>
            <?php if (!$showForm): ?>
            <a href="blog.php?new=1" class="inline-flex items-center gap-2 bg-[#5C1A2A] hover:bg-[#7a2438] text-white text-[13px] font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New Article
            </a>
            <?php else: ?>
            <a href="blog.php" class="text-[13px] text-gray-500 hover:text-gray-800">&larr; Back to all posts</a>
            <?php endif; ?>
        </header>

        <main class="flex-1 p-8 space-y-6">

            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3"><?= $e($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3"><?= $e($error) ?></div>
            <?php endif; ?>

            <?php if ($showForm): ?>
            <!-- ══════════ EDITOR FORM ══════════ -->
            <?php $ed = $editing ?? []; ?>
            <form method="POST" action="blog.php" class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $ed['id'] ?>"><?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">
                    <!-- Main column -->
                    <div class="lg:col-span-2 p-6 space-y-5 border-r border-gray-100">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Title *</label>
                            <input name="title" required value="<?= $e($ed['title'] ?? '') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30 focus:border-[#5C1A2A]"
                                   placeholder="e.g. Why Sambhar Lake Salt Has Zero Microplastics">
                            <?php if ($editing): ?>
                            <p class="text-[11px] text-gray-400 mt-1">URL: <span class="font-mono">/blog/<?= $e($ed['slug']) ?></span> (slug is fixed after creation)</p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Body * <span class="font-normal normal-case text-gray-400">(HTML allowed: &lt;h2&gt; &lt;p&gt; &lt;strong&gt; &lt;ul&gt; &lt;a&gt; &lt;blockquote&gt;)</span></label>
                            <textarea name="body" required rows="20"
                                      class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-mono leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30 focus:border-[#5C1A2A]"
                                      placeholder="<h2>Section heading</h2>&#10;<p>Your paragraph...</p>"><?= $e($ed['body'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Excerpt <span class="font-normal normal-case text-gray-400">(auto-filled from body if blank)</span></label>
                            <textarea name="excerpt" rows="2"
                                      class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30 focus:border-[#5C1A2A]"
                                      placeholder="One- or two-sentence summary shown on the blog listing."><?= $e($ed['excerpt'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Sidebar column -->
                    <div class="p-6 space-y-5 bg-gray-50/50">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
                            <select name="status" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30">
                                <option value="draft"     <?= (($ed['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= (($ed['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Category</label>
                            <select name="category" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $e($cat) ?>" <?= (($ed['category'] ?? 'Salt Science') === $cat) ? 'selected' : '' ?>><?= $e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Cover image URL</label>
                            <input name="cover_image" value="<?= $e($ed['cover_image'] ?? '') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30"
                                   placeholder="/assets/photo.jpg or https://...">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Cover image ALT text</label>
                            <input name="cover_alt" value="<?= $e($ed['cover_alt'] ?? '') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30"
                                   placeholder="Descriptive alt text (helps image SEO)">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Author</label>
                            <input name="author" value="<?= $e($ed['author'] ?? 'Puresol Editorial') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Tags <span class="font-normal normal-case text-gray-400">(comma-separated)</span></label>
                            <input name="tags" value="<?= $e($ed['tags'] ?? '') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30"
                                   placeholder="alkaline salt, microplastics, sambhar lake">
                        </div>

                        <hr class="border-gray-200">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">SEO Overrides (optional)</p>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Meta title</label>
                            <input name="meta_title" value="<?= $e($ed['meta_title'] ?? '') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30"
                                   placeholder="Defaults to the title">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Meta description</label>
                            <textarea name="meta_description" rows="3"
                                      class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30"
                                      placeholder="≈155 chars. Auto-filled from body if blank."><?= $e($ed['meta_description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Meta keywords</label>
                            <input name="meta_keywords" value="<?= $e($ed['meta_keywords'] ?? '') ?>"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#5C1A2A]/30"
                                   placeholder="comma, separated, keywords">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-100 bg-white rounded-b-2xl">
                    <a href="blog.php" class="text-sm text-gray-500 hover:text-gray-800">Cancel</a>
                    <div class="flex items-center gap-2">
                        <?php if ($editing): ?>
                        <a href="/blog/<?= $e($ed['slug']) ?>" target="_blank" rel="noopener"
                           class="text-sm text-[#5C1A2A] hover:underline px-3 py-2">Preview &nearr;</a>
                        <?php endif; ?>
                        <button type="submit" class="bg-[#5C1A2A] hover:bg-[#7a2438] text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                            <?= $editing ? 'Save changes' : 'Save article' ?>
                        </button>
                    </div>
                </div>
            </form>

            <?php else: ?>
            <!-- ══════════ POST LIST ══════════ -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <?php if (empty($posts)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                    <svg class="w-12 h-12 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    <p class="text-sm mb-4">No articles yet. Publishing fresh content regularly is the #1 SEO lever.</p>
                    <a href="blog.php?new=1" class="bg-[#5C1A2A] text-white text-sm font-medium px-4 py-2 rounded-lg">Write your first article</a>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Title</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Category</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="py-3 px-6 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="py-3 px-6 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($posts as $p): ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="py-3.5 px-6">
                                    <p class="font-semibold text-gray-900 text-[13px]"><?= $e($p['title']) ?></p>
                                    <p class="text-[11px] text-gray-400 mt-0.5 font-mono">/blog/<?= $e($p['slug']) ?></p>
                                </td>
                                <td class="py-3.5 px-6 text-gray-500 text-[12px]"><?= $e($p['category']) ?></td>
                                <td class="py-3.5 px-6">
                                    <?php if ($p['status'] === 'published'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-green-50 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Published</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-6 text-gray-400 text-[12px] whitespace-nowrap">
                                    <?= $p['published_at'] ? date('d M Y', strtotime($p['published_at'])) : date('d M Y', strtotime($p['updated_at'])) ?>
                                </td>
                                <td class="py-3.5 px-6">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="blog.php?edit=<?= (int) $p['id'] ?>" class="text-[12px] font-medium text-[#5C1A2A] hover:underline px-2 py-1">Edit</a>
                                        <form method="POST" action="blog.php" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="text-[12px] font-medium text-gray-500 hover:text-gray-900 px-2 py-1"><?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button>
                                        </form>
                                        <form method="POST" action="blog.php" class="inline" onsubmit="return confirm('Delete this post permanently?');">
                                            <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="text-[12px] font-medium text-red-500 hover:text-red-700 px-2 py-1">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>
</body>
</html>
