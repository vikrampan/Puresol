<?php
/**
 * Public single blog post — served at the clean URL /blog/<slug>
 * (rewritten to post.php?slug=<slug> by .htaccess).
 *
 * Renders a published post from the `posts` table with full on-page SEO:
 * canonical, meta description/keywords, OpenGraph, Twitter cards, and
 * Article + BreadcrumbList JSON-LD structured data.
 */

require_once __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? '');
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

$post = null;
if ($slug !== '') {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM posts WHERE slug = :slug AND status = 'published' LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $post = $stmt->fetch() ?: null;
}

// ── 404 for missing / unpublished posts ───────────────────────
if (!$post) {
    http_response_code(404);
    $f = __DIR__ . '/404.html';
    if (is_file($f)) { readfile($f); } else { echo 'Post not found.'; }
    exit;
}

// Count a view (best-effort, non-blocking on failure)
try {
    $db->prepare("UPDATE posts SET views = views + 1 WHERE id = :id")->execute([':id' => $post['id']]);
} catch (Throwable $e) { /* ignore */ }

// ── Derived values ────────────────────────────────────────────
$base       = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://puresol.in';
$url        = $base . '/blog/' . rawurlencode($post['slug']);
$plain      = trim(preg_replace('/\s+/', ' ', strip_tags($post['body'])));
$wordCount  = str_word_count($plain);
$readMins   = max(1, (int) round($wordCount / 200));
$metaTitle  = $post['meta_title'] ?: $post['title'];
$metaDesc   = $post['meta_description'] ?: (mb_substr($plain, 0, 155) . (mb_strlen($plain) > 155 ? '…' : ''));
$metaKw     = $post['meta_keywords'] ?: $post['tags'];
$cover      = $post['cover_image'] ?: '/assets/logo-full.png';
$coverAbs   = (strncmp($cover, 'http', 4) === 0) ? $cover : $base . '/' . ltrim($cover, '/');
$coverAlt   = $post['cover_alt'] ?: $post['title'];
$pubISO     = $post['published_at'] ? date('c', strtotime($post['published_at'])) : date('c', strtotime($post['created_at']));
$modISO     = date('c', strtotime($post['updated_at']));
$pubHuman   = date('F j, Y', strtotime($post['published_at'] ?: $post['created_at']));
$tags       = array_filter(array_map('trim', explode(',', (string) $post['tags'])));
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);

// Article JSON-LD
$articleLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'BlogPosting',
    'headline' => $post['title'],
    'description' => $metaDesc,
    'image'    => [$coverAbs],
    'datePublished' => $pubISO,
    'dateModified'  => $modISO,
    'author'   => ['@type' => 'Organization', 'name' => $post['author'] ?: 'Puresol'],
    'publisher' => [
        '@type' => 'Organization',
        'name'  => 'Puresol',
        'logo'  => ['@type' => 'ImageObject', 'url' => $base . '/assets/logo-full.png'],
    ],
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
    'articleSection' => $post['category'],
    'keywords' => $metaKw,
    'wordCount' => $wordCount,
    'url' => $url,
];
$breadcrumbLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'The Journal', 'item' => $base . '/blog.html'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $url],
    ],
];
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
?>
<!DOCTYPE html>
<html lang="en" class="lenis">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3VYJ4LFE76"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-3VYJ4LFE76');
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
  <meta name="googlebot" content="index, follow">
  <meta name="author" content="<?= $e($post['author']) ?> &mdash; Puresol">
  <meta name="geo.region" content="IN-RJ">
  <meta name="theme-color" content="#5C1A2A">

  <title><?= $e($metaTitle) ?> | Puresol Journal</title>
  <meta name="description" content="<?= $e($metaDesc) ?>">
  <?php if ($metaKw): ?><meta name="keywords" content="<?= $e($metaKw) ?>"><?php endif; ?>
  <link rel="canonical" href="<?= $e($url) ?>">

  <!-- Open Graph -->
  <meta property="og:type" content="article">
  <meta property="og:site_name" content="Puresol">
  <meta property="og:title" content="<?= $e($metaTitle) ?>">
  <meta property="og:description" content="<?= $e($metaDesc) ?>">
  <meta property="og:url" content="<?= $e($url) ?>">
  <meta property="og:image" content="<?= $e($coverAbs) ?>">
  <meta property="article:published_time" content="<?= $e($pubISO) ?>">
  <meta property="article:modified_time" content="<?= $e($modISO) ?>">
  <meta property="article:section" content="<?= $e($post['category']) ?>">
  <?php foreach ($tags as $t): ?><meta property="article:tag" content="<?= $e($t) ?>">
  <?php endforeach; ?>

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $e($metaTitle) ?>">
  <meta name="twitter:description" content="<?= $e($metaDesc) ?>">
  <meta name="twitter:image" content="<?= $e($coverAbs) ?>">

  <link rel="icon" href="/assets/logo-full.png">
  <link rel="stylesheet" href="/style.css">

  <script type="application/ld+json"><?= json_encode($articleLd, $jsonFlags) ?></script>
  <script type="application/ld+json"><?= json_encode($breadcrumbLd, $jsonFlags) ?></script>

  <style>
    .post-wrap { max-width: 760px; margin: 0 auto; padding: 0 1.25rem; }
    .post-hero { padding: calc(var(--space-2xl, 5rem) + 40px) 0 var(--space-lg, 2rem); }
    .post-breadcrumb { font-size: 0.8rem; color: var(--magenta, #c7195a); margin-bottom: 1.25rem; }
    .post-breadcrumb a { color: inherit; text-decoration: none; opacity: 0.8; }
    .post-breadcrumb a:hover { opacity: 1; text-decoration: underline; }
    .post-eyebrow { display: inline-block; font-size: 0.72rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--magenta, #c7195a); font-weight: 600; margin-bottom: 0.9rem; }
    .post-title { font-family: var(--font-heading, Georgia, serif); font-style: italic; font-weight: 600; color: var(--maroon, #5C1A2A); font-size: clamp(1.9rem, 4.5vw, 3rem); line-height: 1.12; margin-bottom: 1rem; }
    .post-meta { display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; font-size: 0.85rem; color: var(--charcoal, #44403c); opacity: 0.7; margin-bottom: 1.75rem; }
    .post-cover { width: 100%; border-radius: 18px; overflow: hidden; margin: 0 auto 2.5rem; box-shadow: 0 24px 60px rgba(62,15,26,0.18); }
    .post-cover img { width: 100%; height: auto; display: block; }
    .post-body { font-family: var(--font-body, system-ui, sans-serif); font-size: 1.075rem; line-height: 1.85; color: var(--charcoal, #2c2826); }
    .post-body > * { margin-bottom: 1.4rem; }
    .post-body h2 { font-family: var(--font-heading, Georgia, serif); font-style: italic; color: var(--maroon, #5C1A2A); font-size: clamp(1.4rem, 2.6vw, 1.9rem); line-height: 1.25; margin-top: 2.6rem; }
    .post-body h3 { font-family: var(--font-heading, Georgia, serif); color: var(--maroon, #5C1A2A); font-size: 1.3rem; margin-top: 2rem; }
    .post-body a { color: var(--magenta, #c7195a); text-decoration: underline; text-underline-offset: 2px; }
    .post-body ul, .post-body ol { padding-left: 1.4rem; }
    .post-body li { margin-bottom: 0.6rem; }
    .post-body blockquote { border-left: 3px solid var(--magenta, #c7195a); padding: 0.4rem 0 0.4rem 1.4rem; font-style: italic; color: var(--maroon, #5C1A2A); margin-left: 0; }
    .post-body img { max-width: 100%; height: auto; border-radius: 12px; }
    .post-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 2.5rem 0; }
    .post-tag { font-size: 0.78rem; padding: 0.35rem 0.8rem; border-radius: 999px; background: rgba(199,25,90,0.08); color: var(--magenta, #c7195a); }
    .post-cta { text-align: center; margin: 3rem auto 1rem; padding: 2rem; border-radius: 18px; background: var(--cream, #f6ece2); }
    .post-cta a { display: inline-block; margin-top: 0.75rem; background: var(--maroon, #5C1A2A); color: #fff8f0; padding: 0.8rem 1.6rem; border-radius: 999px; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
    .reading-progress { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: var(--magenta, #c7195a); z-index: 9999; }
  </style>
</head>
<body>

  <div class="reading-progress" id="reading-progress" role="progressbar" aria-hidden="true"></div>

  <nav class="nav scrolled" role="navigation" aria-label="Main navigation">
    <div class="container nav__inner">
      <a href="/index.html" class="nav__logo">
        <img src="/assets/logo-full.png" alt="Puresol" class="nav__logo-img" width="38" height="38">
        <span class="nav__logo-text">Puresol</span>
      </a>
      <div class="nav__hamburger" aria-label="Toggle menu" role="button" tabindex="0">
        <span></span><span></span><span></span>
      </div>
      <ul class="nav__links">
        <li><a href="/index.html#products" class="nav__link">Products</a></li>
        <li><a href="/science.html" class="nav__link">The Science</a></li>
        <li><a href="/story.html" class="nav__link">Our Story</a></li>
        <li><a href="/health.html" class="nav__link">Health</a></li>
        <li><a href="/contact.html" class="nav__link">Contact</a></li>
        <li><a href="/blog.html" class="nav__link nav__link--active">The Journal</a></li>
      </ul>
    </div>
  </nav>

  <main>
    <article class="post-hero">
      <div class="post-wrap">
        <nav class="post-breadcrumb" aria-label="Breadcrumb">
          <a href="/index.html">Home</a> &nbsp;/&nbsp;
          <a href="/blog.html">The Journal</a> &nbsp;/&nbsp;
          <span><?= $e($post['category']) ?></span>
        </nav>

        <span class="post-eyebrow"><?= $e($post['category']) ?></span>
        <h1 class="post-title"><?= $e($post['title']) ?></h1>
        <div class="post-meta">
          <span>By <?= $e($post['author']) ?></span>
          <span><?= $e($pubHuman) ?></span>
          <span><?= (int) $readMins ?> min read</span>
        </div>

        <?php if ($post['cover_image']): ?>
        <figure class="post-cover">
          <img src="<?= $e($cover) ?>" alt="<?= $e($coverAlt) ?>" width="1200" height="675" loading="eager">
        </figure>
        <?php endif; ?>

        <div class="post-body">
          <?= $post['body'] /* trusted admin-authored HTML */ ?>
        </div>

        <?php if ($tags): ?>
        <div class="post-tags">
          <?php foreach ($tags as $t): ?><span class="post-tag">#<?= $e($t) ?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="post-cta">
          <p style="font-family:var(--font-heading,serif);font-style:italic;color:var(--maroon,#5C1A2A);font-size:1.25rem;">Taste the difference 54+ minerals make.</p>
          <a href="/index.html#products">Shop Puresol Alkaline Salt &rarr;</a>
        </div>

        <p style="text-align:center;margin:2rem 0 4rem;"><a href="/blog.html" style="color:var(--magenta,#c7195a);text-decoration:none;font-weight:600;">&larr; Back to The Journal</a></p>
      </div>
    </article>

    <footer class="footer">
      <div class="container">
        <div class="footer__top">
          <div class="footer__brand-col">
            <h3 class="footer__brand-name">Puresol</h3>
            <p class="footer__brand-desc">
              Hand-harvested from Sambhar Lake, Rajasthan.
              54+ minerals, zero microplastics. Sun-cured, not manufactured.
            </p>
          </div>
          <div>
            <h4 class="footer__col-title">Products</h4>
            <a href="/index.html#products" class="footer__link">Natural Alkaline Salt</a>
            <a href="/index.html#products" class="footer__link">Super 7 Salt</a>
          </div>
          <div>
            <h4 class="footer__col-title">Company</h4>
            <a href="/story.html" class="footer__link">Our Story</a>
            <a href="/science.html" class="footer__link">The Science</a>
            <a href="/health.html" class="footer__link">Health Benefits</a>
            <a href="/blog.html" class="footer__link">The Journal</a>
            <a href="/contact.html" class="footer__link">Contact</a>
          </div>
          <div>
            <h4 class="footer__col-title">Get in Touch</h4>
            <a href="tel:+918586891913" class="footer__link">+91 85868 91913</a>
            <a href="mailto:info@puresol.in" class="footer__link">info@puresol.in</a>
            <a href="https://www.puresol.in" class="footer__link" target="_blank" rel="noopener">www.puresol.in</a>
            <p class="footer__link" style="margin-top:0.5rem;">Agrigore Ventures Pvt. Ltd.</p>
          </div>
        </div>
        <div class="footer__bottom">
          <span>&copy; 2025 Puresol. All rights reserved.</span>
          <span><a href="/privacy.html">Privacy</a> &nbsp;&middot;&nbsp; <a href="/terms.html">Terms</a></span>
        </div>
      </div>
    </footer>
  </main>

  <!-- Animation libs (main.js depends on GSAP/ScrollTrigger/Lenis) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
  <script src="https://unpkg.com/lenis@1.1.18/dist/lenis.min.js"></script>
  <script src="/main.js"></script>
  <script>
    window.addEventListener('scroll', function () {
      var el = document.getElementById('reading-progress');
      if (!el) return;
      var st = document.documentElement.scrollTop || document.body.scrollTop;
      var sh = document.documentElement.scrollHeight - document.documentElement.clientHeight;
      el.style.width = (sh > 0 ? (st / sh) * 100 : 0) + '%';
    }, { passive: true });
  </script>

</body>
</html>
