-- ============================================================
-- Puresol by Agrigore Ventures Pvt. Ltd.
-- Database Schema Setup
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- 1. Admin Users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)     NOT NULL,
    `email`         VARCHAR(255)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `role`          ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login`    DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_username` (`username`),
    UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 2. Products
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255)    NOT NULL,
    `slug`          VARCHAR(255)    NOT NULL,
    `tagline`       VARCHAR(500)    DEFAULT NULL,
    `description`   TEXT            DEFAULT NULL,
    `price`         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `compare_price` DECIMAL(10,2)   DEFAULT NULL,
    `stock`         INT UNSIGNED    NOT NULL DEFAULT 0,
    `sku`           VARCHAR(100)    DEFAULT NULL,
    `image_url`     VARCHAR(500)    DEFAULT NULL,
    `badge`         VARCHAR(100)    DEFAULT NULL,
    `features`      JSON            DEFAULT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `sort_order`    INT             NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_slug` (`slug`),
    UNIQUE KEY `uq_product_sku` (`sku`),
    INDEX `idx_product_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 3. Orders
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `order_number`    VARCHAR(30)       NOT NULL,
    `customer_name`   VARCHAR(255)      NOT NULL,
    `customer_email`  VARCHAR(255)      NOT NULL,
    `customer_phone`  VARCHAR(20)       NOT NULL,
    `address`         TEXT              NOT NULL,
    `city`            VARCHAR(100)      NOT NULL,
    `state`           VARCHAR(100)      NOT NULL,
    `pincode`         VARCHAR(10)       NOT NULL,
    `items`           JSON              NOT NULL,
    `subtotal`        DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    `shipping`        DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    `total`           DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    `status`          ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    `payment_method`  VARCHAR(50)       DEFAULT NULL,
    `payment_status`  ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `notes`           TEXT              DEFAULT NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_number` (`order_number`),
    INDEX `idx_order_status` (`status`),
    INDEX `idx_order_payment` (`payment_status`),
    INDEX `idx_order_email` (`customer_email`),
    INDEX `idx_order_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 4. Finances
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finances` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `type`          ENUM('revenue','expense') NOT NULL,
    `category`      VARCHAR(100)    NOT NULL,
    `description`   VARCHAR(500)    DEFAULT NULL,
    `amount`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `date`          DATE            NOT NULL,
    `reference_id`  VARCHAR(100)    DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_finance_type` (`type`),
    INDEX `idx_finance_date` (`date`),
    INDEX `idx_finance_category` (`category`),
    INDEX `idx_finance_reference` (`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 5. Messages
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255)    NOT NULL,
    `email`         VARCHAR(255)    NOT NULL,
    `phone`         VARCHAR(20)     DEFAULT NULL,
    `subject`       VARCHAR(500)    DEFAULT NULL,
    `message`       TEXT            NOT NULL,
    `is_read`       TINYINT(1)      NOT NULL DEFAULT 0,
    `is_archived`   TINYINT(1)      NOT NULL DEFAULT 0,
    `replied_at`    DATETIME        DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_message_read` (`is_read`),
    INDEX `idx_message_archived` (`is_archived`),
    INDEX `idx_message_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 6. Subscribers
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscribers` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(255) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subscriber_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 7. Settings
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)    NOT NULL,
    `setting_value` TEXT            DEFAULT NULL,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Blog / Journal Posts
-- ============================================================
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

-- ============================================================
-- Seed Data
-- ============================================================

-- IMPORTANT: Replace `password_hash` with a real bcrypt hash before deploying.
-- Generate one with: php -r "echo password_hash('YOUR_STRONG_PASSWORD', PASSWORD_BCRYPT, ['cost' => 12]);"
-- The placeholder value below will NOT authenticate against any password.
INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `role`) VALUES
('admin', 'admin@puresol.in', 'REPLACE_WITH_BCRYPT_HASH_BEFORE_DEPLOYING', 'admin');

-- Seed products
INSERT INTO `products` (`name`, `slug`, `tagline`, `description`, `price`, `compare_price`, `stock`, `sku`, `image_url`, `badge`, `features`, `is_active`, `sort_order`) VALUES
(
    'Natural Alkaline Salt',
    'natural-alkaline-salt',
    'Pure, unrefined alkaline salt straight from nature',
    'Puresol Natural Alkaline Salt is harvested from pristine mineral-rich deposits and carefully processed to retain its natural alkalinity and trace minerals. Free from additives, anti-caking agents, and artificial processing. Ideal for everyday cooking, seasoning, and health-conscious households.',
    299.00,
    399.00,
    500,
    'PS-NAS-500G',
    '/uploads/products/natural-alkaline-salt.jpg',
    'Bestseller',
    '["100% Natural & Unrefined","Rich in 84+ Trace Minerals","Alkaline pH 9+","No Anti-Caking Agents","Hand-Harvested & Sun-Dried"]',
    1,
    1
),
(
    'Super 54+ Salt',
    'super-54-salt',
    '54+ trace minerals, naturally enriched with Pro-Vitamin A',
    'Puresol Super 54+ Salt is Sambhar Lake alkaline salt concentrated with beta-carotene from Dunaliella salina. It carries the full 54+ trace-mineral profile plus natural Pro-Vitamin A. Perfect for athletes, health enthusiasts, and anyone seeking optimal mineral nutrition.',
    499.00,
    699.00,
    300,
    'PS-S54S-500G',
    '/uploads/products/super-54-salt.jpg',
    'Premium',
    '["54+ Trace Minerals","Enhanced Electrolyte Balance","Supports pH Balance","Rich in Magnesium & Potassium","Athletic Performance Formula"]',
    1,
    2
);

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Puresol'),
('site_tagline', 'Premium Alkaline Salt by Agrigore Ventures'),
('contact_email', 'hello@puresol.in'),
('contact_phone', '+91-XXXXXXXXXX'),
('shipping_charge', '49.00'),
('free_shipping_above', '999.00'),
('gst_percent', '18'),
('currency', 'INR'),
('currency_symbol', '₹');

SET FOREIGN_KEY_CHECKS = 1;
