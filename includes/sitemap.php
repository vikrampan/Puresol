<?php
/**
 * Sitemap generator.
 *
 * regenerate_sitemap() rewrites /sitemap.xml from a fixed list of static
 * pages plus every published blog post. Called by the admin Journal CMS
 * whenever a post is published, updated, or removed, so the sitemap always
 * reflects live content without manual editing.
 */

if (!function_exists('regenerate_sitemap')) {

    /**
     * @param PDO $db
     * @return bool true on success
     */
    function regenerate_sitemap(PDO $db): bool
    {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://puresol.in';
        $today = date('Y-m-d');

        // ── Static pages (loc, changefreq, priority) ──────────────
        $static = [
            ['/',             'weekly',  '1.0'],
            ['/story.html',   'monthly', '0.8'],
            ['/science.html', 'monthly', '0.9'],
            ['/health.html',  'monthly', '0.9'],
            ['/blog.html',    'weekly',  '0.8'],
            ['/contact.html', 'monthly', '0.9'],
            ['/terms.html',   'yearly',  '0.3'],
            ['/privacy.html', 'yearly',  '0.3'],
        ];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n\n";

        foreach ($static as [$loc, $freq, $prio]) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($base . $loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <changefreq>{$freq}</changefreq>\n";
            $xml .= "    <priority>{$prio}</priority>\n";
            $xml .= "  </url>\n\n";
        }

        // ── Published blog posts ──────────────────────────────────
        try {
            $rows = $db->query(
                "SELECT slug, COALESCE(published_at, updated_at) AS lastmod
                 FROM posts
                 WHERE status = 'published'
                 ORDER BY published_at DESC"
            )->fetchAll();
        } catch (Throwable $e) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $loc = $base . '/blog/' . rawurlencode($row['slug']);
            $lastmod = $row['lastmod'] ? date('Y-m-d', strtotime($row['lastmod'])) : $today;
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>monthly</changefreq>\n";
            $xml .= "    <priority>0.7</priority>\n";
            $xml .= "  </url>\n\n";
        }

        $xml .= "</urlset>\n";

        $path = dirname(__DIR__) . '/sitemap.xml';
        return @file_put_contents($path, $xml) !== false;
    }
}
