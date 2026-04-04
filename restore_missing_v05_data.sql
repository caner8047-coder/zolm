-- =========================================================
-- ZOLM v0.5 -> v0.6 Missing Data Restore Script
-- Restores:
--   - profiles
--   - reports
--   - report_files
--   - supply_orders
--
-- HOW TO USE
-- 1) Import old dump into a separate DB first (example: aizemure_lv129)
-- 2) Make sure you are connected to target DB (example: aizemure_v06)
-- 3) If your old DB name is different, replace: aizemure_lv129
-- 4) Run this file once.
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Clean target tables (current DB)
TRUNCATE TABLE report_files;
TRUNCATE TABLE reports;
TRUNCATE TABLE profiles;
TRUNCATE TABLE supply_orders;

-- Restore profiles
INSERT INTO profiles (
  id, user_id, name, type, input_config, output_config, ai_prompt,
  sample_input_path, sample_output_path, ai_generated_rules,
  is_ai_generated, status, error_message, is_default, created_at, updated_at
)
SELECT
  id, user_id, name, type, input_config, output_config, ai_prompt,
  sample_input_path, sample_output_path, ai_generated_rules,
  is_ai_generated, status, error_message, is_default, created_at, updated_at
FROM aizemure_v06.profiles;

-- Restore reports
INSERT INTO reports (
  id, user_id, profile_id, original_filename, status, error_message, created_at, updated_at
)
SELECT
  id, user_id, profile_id, original_filename, status, error_message, created_at, updated_at
FROM aizemure_v06.reports;

-- Restore report files
INSERT INTO report_files (
  id, report_id, filename, file_path, sheet_type, created_at, updated_at
)
SELECT
  id, report_id, filename, file_path, sheet_type, created_at, updated_at
FROM aizemure_v06.report_files;

-- Restore supply orders
INSERT INTO supply_orders (
  id, siparis_no, kayit_tarihi, musteri_adi, telefon, adres, ilce, il,
  urun_adi, kategori, adet, soz_tarihi, renk_etiketi, durum, sebebiyet,
  gonderim_tarihi, notlar, created_at, updated_at
)
SELECT
  id, siparis_no, kayit_tarihi, musteri_adi, telefon, adres, ilce, il,
  urun_adi, kategori, adet, soz_tarihi, renk_etiketi, durum, sebebiyet,
  gonderim_tarihi, notlar, created_at, updated_at
FROM aizemure_v06.supply_orders;

SET FOREIGN_KEY_CHECKS = 1;

-- Optional sanity checks
SELECT COUNT(*) AS profiles_count FROM profiles;
SELECT COUNT(*) AS reports_count FROM reports;
SELECT COUNT(*) AS report_files_count FROM report_files;
SELECT COUNT(*) AS supply_orders_count FROM supply_orders;
