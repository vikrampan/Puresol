<?php
/**
 * One-off migration: create the `posts` table for the Journal / Blog CMS.
 *
 * Usage (CLI on the server, recommended):
 *     php scripts/migrate_posts.php
 *
 * Or via browser ONCE, then delete this file:
 *     https://puresol.in/scripts/migrate_posts.php?key=RUN_ME
 *
 * Safe to run multiple times (CREATE TABLE IF NOT EXISTS).
 */

require_once dirname(__DIR__) . '/config.php';

// Tiny guard so the web route can't be hit casually. Change/remove after running.
if (PHP_SAPI !== 'cli' && (($_GET['key'] ?? '') !== 'RUN_ME')) {
    http_response_code(403);
    exit('Forbidden. Run from CLI or append ?key=RUN_ME, then delete this file.');
}

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS `posts` (
    `id`               INT UNSIGNED              NOT NULL AUTO_INCREMENT,
    `title`            VARCHAR(255)              NOT NULL,
    `slug`             VARCHAR(255)              NOT NULL,
    `excerpt`          VARCHAR(500)              DEFAULT NULL,
    `body`             MEDIUMTEXT                NOT NULL,
    `cover_image`      VARCHAR(500)              DEFAULT NULL,
    `cover_alt`        VARCHAR(255)              DEFAULT NULL,
    `category`         VARCHAR(100)              NOT NULL DEFAULT 'Salt Science',
    `tags`             VARCHAR(500)              DEFAULT NULL,
    `meta_title`       VARCHAR(255)              DEFAULT NULL,
    `meta_description` VARCHAR(320)              DEFAULT NULL,
    `meta_keywords`    VARCHAR(500)              DEFAULT NULL,
    `author`           VARCHAR(120)              NOT NULL DEFAULT 'Puresol Editorial',
    `status`           ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `published_at`     DATETIME                  DEFAULT NULL,
    `views`            INT UNSIGNED              NOT NULL DEFAULT 0,
    `created_at`       DATETIME                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME                  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_post_slug` (`slug`),
    INDEX `idx_post_status_pub` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

try {
    $db = Database::connect();
    $db->exec($sql);
    $msg = "OK: `posts` table is ready.\n";
    echo (PHP_SAPI === 'cli') ? $msg : nl2br(htmlspecialchars($msg));
} catch (Throwable $e) {
    http_response_code(500);
    $msg = 'Migration failed: ' . $e->getMessage() . "\n";
    echo (PHP_SAPI === 'cli') ? $msg : nl2br(htmlspecialchars($msg));
}
