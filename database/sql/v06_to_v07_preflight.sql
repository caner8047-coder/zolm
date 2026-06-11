-- ==============================================================
-- ZOLM v0.6 -> v0.7 KAYIPSIZ GECIS ON KONTROLU
-- Olusturma Tarihi: 2026-05-08
--
-- Bu dosya veri silmez, tablo bosaltmaz, dump import etmez.
-- Amac:
--   1) v0.6 dump'inda var olup migrations kaydinda eksik gorunen
--      production_revenue migration'ini guvenli isaretlemek.
--   2) v0.7 migration'lari calistiktan sonra desteklenen pazaryerlerinde
--      soru sync profilini acik tutmak.
--
-- Kullanim:
--   - Canli DB yedegi alindiktan sonra calistirin.
--   - Ardindan veya oncesinde `php artisan migrate --force` calisabilir;
--     script iki durumda da tekrar calistirilmeye uygundur.
-- ==============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT NOT NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

START TRANSACTION;

SET @next_batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_03_07_120000_create_production_revenue_tables', @next_batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_03_07_120000_create_production_revenue_tables'
)
AND EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'production_revenue_imports'
)
AND EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'production_revenue_entries'
);

SET @has_sync_profiles := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'integration_sync_profiles'
);

SET @has_marketplace_stores := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'marketplace_stores'
);

SET @has_questions_enabled := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'integration_sync_profiles'
      AND column_name = 'questions_enabled'
);

SET @has_questions_poll_minutes := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'integration_sync_profiles'
      AND column_name = 'questions_poll_minutes'
);

SET @sql := IF(
    @has_sync_profiles > 0
    AND @has_marketplace_stores > 0
    AND @has_questions_enabled > 0
    AND @has_questions_poll_minutes > 0,
    'UPDATE `integration_sync_profiles` p
        INNER JOIN `marketplace_stores` s ON s.`id` = p.`store_id`
      SET p.`questions_enabled` = 1,
          p.`questions_poll_minutes` = COALESCE(NULLIF(p.`questions_poll_minutes`, 0), 15)
      WHERE s.`marketplace` IN (''trendyol'', ''hepsiburada'', ''n11'', ''pazarama'', ''ciceksepeti'', ''koctas'', ''woocommerce'')',
    'SELECT ''integration_sync_profiles.questions_* kolonlari henuz yok; php artisan migrate --force sonrasinda bu script tekrar calistirilabilir.'' AS note'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

SELECT `migration`, COUNT(*) AS row_count
FROM `migrations`
WHERE `migration` IN (
    '2026_03_07_120000_create_production_revenue_tables',
    '2026_04_25_010000_create_marketplace_question_center_tables',
    '2026_04_27_140000_add_question_sync_fields_to_integration_sync_profiles'
)
GROUP BY `migration`;
