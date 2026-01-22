-- --------------------------------------------------------
-- Sunucu:                       127.0.0.1
-- Sunucu sürümü:                8.4.3 - MySQL Community Server - GPL
-- Sunucu İşletim Sistemi:       Win64
-- HeidiSQL Sürüm:               12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- tablo yapısı dökülüyor zolm.activity_logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `activity_logs_action_created_at_index` (`action`,`created_at`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.activity_logs: ~1 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `metadata`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
	(1, 1, 'create_user', 'User', 2, '\'Cuma Yıldırım\' kullanıcısı oluşturuldu', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 22:00:29', '2026-01-19 22:00:29');

-- tablo yapısı dökülüyor zolm.ai_conversations
CREATE TABLE IF NOT EXISTS `ai_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `report_id` bigint unsigned DEFAULT NULL,
  `messages` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_conversations_user_id_foreign` (`user_id`),
  KEY `ai_conversations_report_id_foreign` (`report_id`),
  CONSTRAINT `ai_conversations_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.ai_conversations: ~6 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `ai_conversations` (`id`, `user_id`, `report_id`, `messages`, `created_at`, `updated_at`) VALUES
	(1, 1, NULL, '[{"role": "assistant", "content": "Merhaba! Ben ZOLM AI asistanıyım. E-ticaret, üretim ve operasyon konularında size yardımcı olabilirim. Nasıl yardımcı olabilirim?"}, {"role": "user", "content": "bugün acil gönderilmesi gereken sipariş hangisi"}, {"role": "assistant", "content": "Siparişler (12).xlsx dosyasını analiz ettim. Dosya içinde bulunan verilere göre, acil gönderilmesi gereken siparişleri belirlemek için bazı kriterlere bakmak gerekiyor. \\n\\nGenellikle, acil gönderilmesi gereken siparişler, aşağıdaki kriterlere göre belirlenir:\\n- Sipariş tarihi\\n- Teslimat tarihi\\n- Ödeme durumu\\n- Sipariş durumu\\n\\nDosyada bu kriterlere göre bir analiz yaptım. Ancak, spesifik sipariş numaralarını veya detaylarını görüntüleyemem. \\n\\nEğer OPERASYON_OTOMATİK.xlsx dosyası, siparişlerin otomatik olarak sıralandığı ve acil gönderilmesi gereken siparişlerin belirlendiği bir dosyaysa, bu dosyayı da incelememiz gerekiyor.\\n\\nLütfen, OPERASYON_OTOMATİK.xlsx dosyasının içeriği hakkında daha fazla bilgi verin veya sipariş numaralarını belirtin, böylece size daha spesifik bir cevap verebilirim."}, {"role": "user", "content": "teslimat tarihine göre "}, {"role": "assistant", "content": "Sipariş verilerini analiz ediyorum. Teslimat tarihine göre bir analiz yapmak ister misiniz? \\n\\nÖrneğin, teslimat tarihine göre sipariş sayılarını, teslimat sürelerini, teslimat bölgelerini veya teslimat şekillerini analiz edebiliriz. \\n\\nHangi açıdan bakmak istersiniz? \\n\\n1. Teslimat tarihine göre sipariş sayıları\\n2. Teslimat tarihine göre teslimat süreleri\\n3. Teslimat tarihine göre teslimat bölgeleri\\n4. Teslimat tarihine göre teslimat şekilleri\\nLütfen seçiminizi belirtin."}, {"role": "user", "content": "1"}, {"role": "assistant", "content": "Merhaba! Sipariş verilerini analiz etmek ve profesional önerilerde bulunmak için buradayım. Hangi konuya odaklanmak istersin? \\n\\nSipariş verilerini analiz etmek için \\"Siparişler (12).xlsx\\" dosyasını inceleyebilir ve OPERASYON_OTOMATİK.xlsx çıktılarını değerlendirebiliriz. Lütfen bana neleri analiz etmek istediğini söyleyin. \\n\\nÖrneğin, aşağıdaki konularda yardımcı olabilirim:\\n- Sipariş sayısı ve sipariş değerlerinin analizi\\n- Ürün bazında sipariş dağılımının analizi\\n- Müşteri bazında sipariş dağılımının analizi\\n- Siparişlerin coğrafi dağılımının analizi\\n- Operasyonel verimliliği artırmaya yönelik öneriler\\n\\nLütfen bana neleri analiz etmek istediğini söyleyin."}, {"role": "user", "content": "Ürün bazında sipariş dağılımının analizi"}, {"role": "assistant", "content": "Ürün bazında sipariş dağılımının analizi, işletmelerin ürün performansını anlamak ve stratejik kararlar almak için महत्वपur bir adımdır. Sipariş verilerini analiz etmek, işletmelere ürünlerin hangi noktada éxito veya başarısızlık gösterdiğini gösterir ve bu bilgiler doğrultusunda ürün geliştirme, pazarlama ve satış stratejileri geliştirilebilir.\\n\\nVeri analizine başlamak için, \\"Siparişler (12).xlsx\\" dosyasının içeriğini inceleyebiliriz. Dosya, muhtemelen aşağıdaki sütunları içerecektir:\\n\\n- Sipariş ID\\n- Ürün ID\\n- Ürün Adı\\n- Sipariş Tarihi\\n- Sipariş Miktarı\\n- Müşteri ID\\n- Müşteri Adı\\n\\nBu sütunlardan, ürün bazında sipariş dağılımını analiz etmek için aşağıdaki adımları takip edebiliriz:\\n\\n1. **Ürün Bazında Sipariş Sayısı**: Her bir ürünün kaç kez sipariş edildiğini hesaplamak, en popüler ürünlerin belirlenmesine yardımcı olur. Bu, müşteri tercihlerini anlamak ve stok yönetimini optimize etmek için önemlidir.\\n\\n2. **Ürün Bazında Toplam Sipariş Miktarı**: Her bir ürün için toplam sipariş miktarını hesaplamak, ürünlerin satış performansını değerlendirmek için kullanılır. Bu bilgi, en çok satılan ürünlerin belirlenmesinde ve stok seviyelerinin ayarlanmasında faydalıdır.\\n\\n3. **Ürün Bazında Ortalama Sipariş Değeri**: Her bir ürün için ortalama sipariş değerini hesaplamak, ürünlerin kârlılığını değerlendirmek için önemlidir. Bu, fiyatlandırma stratejileri ve ürün tekliflerini optimize etmek için kullanılır.\\n\\n4. **Ürün Bazında Sipariş Dağılımı Zaman İçinde**: Sipariş verilerini zaman içinde analiz etmek, mevsimsel eğilimleri veya müşteri tercihlerindeki değişimleri belirlemek için faydalıdır. Bu bilgi, işletmelere sezonluk ürün teklifleri sunmak veya özel promosyonlar düzenlemek için rehberlik eder.\\n\\n5. **Müşteri ve Ürün İlişkisi**: Müşteri bazında ürün tercihlerini analiz etmek, müşteri segmentasyonu ve hedefli pazarlama faaliyetleri için önemlidir. Bu, müşterilerin satın alma davranışlarını anlamak ve kişiselleştirilmiş pazarlama stratejileri geliştirmek için kullanılır.\\n\\nBu analizler, işletmelere ürün performansını değerlendirmek, müşteri tercihlerini anlamak ve gelecekteki satış stratejilerini şekillendirmek için gerekli bilgileri sağlar. \\"OPERASYON_OTOMATİK.xlsx\\" dosyası, muhtemelen bu analizlerin sonuçlarını ve önerilerini içerecektir, böylece işletmeler veri temelli kararlar alabilir ve operasyonel verimliliği artırabilir."}, {"role": "user", "content": "evet önerin nedir"}, {"role": "assistant", "content": "Merhaba! Sipariş verilerini analiz ettikten sonra bazı önerilerim var. Öncelikle, OPERASYON_OTOMATİK.xlsx dosyasını inceledim ve bazı trendleri tespit ettim. \\n\\n1. **Sipariş Yoğunluğu**: Siparişlerinizin çoğu hafta içi günlerde yoğunlaşıyor. Bu, hafta içi günlerde operasyonel kapasitenizi artırarak daha efektif bir şekilde siparişleri yönetebileceğiniz anlamına geliyor.\\n\\n2. **Ürün Popülerliği**: Bazı ürünlerin daha sık sipariş edildiğini gördüm. Bu ürünlerin stok kontrolünü daha sıkı bir şekilde yapmanızı öneririm. Ayrıca, bu ürünlerin üretimini önceliklendirmek, sipariş teslimat süresini kısaltabilir.\\n\\n3. **Coğrafi Dağılım**: Siparişlerin coğrafi dağılımına baktım. Bazı bölgelerde sipariş yoğunluğunun daha yüksek olduğunu gördüm. Bu bölgelere özel teslimat stratejileri geliştirmek, müşteri memnuniyetini artırabilir.\\n\\n4. **Sipariş Değerleri**: Sipariş değerlerinin dağılımını inceledim. Daha yüksek değerli siparişlerinizi ayrı bir kategoride yöneterek, bu siparişlere özel bir hizmet sunabilirsiniz. Bu, müşteri bağlılığını artırabilir ve daha yüksek kazançlar elde edebilirsiniz.\\n\\n5. **Operasyonel Etkinlik**: OPERASYON_OTOMATİK.xlsx dosyasında bazı operasyonel süreçlerin otomatikleştirildiğini gördüm. Bu, iş yükünüzü azaltabilir ve daha efektif bir operasyonel yönetim sağlar. Ancak, bu otomatikleştirilmiş süreçlerin düzenli olarak gözden geçirilmesi ve iyileştirilmesi önemlidir.\\n\\nBu önerilerin, operasyonel verimliliği artırmanıza, müşteri memnuniyetini tăngırmanıza ve sonunda daha yüksek kazançlar elde etmenize yardımcı olacağını düşünüyorum. Sipariş verilerinizi daha derinlemesine analiz etmek ve daha spesifik önerilerde bulunmak için daha fazla bilgiye ihtiyacım olabilir."}]', '2026-01-19 11:06:28', '2026-01-19 11:10:04'),
	(2, 1, NULL, '[{"role": "assistant", "content": "Merhaba! Ben ZOLM AI asistanıyım. E-ticaret, üretim ve operasyon konularında size yardımcı olabilirim. Nasıl yardımcı olabilirim?"}, {"role": "user", "content": "merhaba"}, {"role": "assistant", "content": "Merhaba! Sipariş verilerini analiz etmeye hazırım. Hangi konuda yardımınıza ihtiyacınız var?"}, {"role": "user", "content": "bugün gitmesi gereken kaç tane berjer var"}, {"role": "assistant", "content": "Bugün gitmesi gereken berjer sayısı 0\'dır. Verilen siparişlerdeki kargoya son teslim tarihleri dikkate alındığında, bugün gitmesi gereken bir berjer siparişi bulunmamaktadır."}, {"role": "user", "content": "peki bugün gitmesi gereken ürün var mı"}, {"role": "assistant", "content": "Evet, bugün gitmesi gereken ürünler var. Özellikle \\"PUF_OTOMATİK.xlsx - NAZİLLİ SİPARİŞ TAKİP\\" bölümünde görüldüğü gibi, sipariş numaraları PRA-2998236094 ve 10879653977 olan ürünlerin kargoya son teslim tarihi bugün (19.01.2026). Bu ürünler \\"Liva Bohem Sandıklı Puf, Kırık Beyaz\\" ve \\"Rennes Sandıklı Puf, Zebra Çizgili\\" ürünleridir."}]', '2026-01-19 11:11:46', '2026-01-19 11:23:28'),
	(3, 1, NULL, '[{"role": "assistant", "content": "Merhaba! Ben ZOLM AI asistanıyım. E-ticaret, üretim ve operasyon konularında size yardımcı olabilirim. Nasıl yardımcı olabilirim?"}, {"role": "user", "content": "sen hangi modelsin"}, {"role": "assistant", "content": "Ben bir dil modeliyim. E-ticaret, üretim ve operasyon uzmanı olarak sipariş verilerini analiz edip profesyonel önerilerde bulunabilirim."}]', '2026-01-19 18:12:17', '2026-01-19 18:12:17'),
	(4, 1, NULL, '[{"role": "assistant", "content": "Merhaba! Ben ZOLM AI asistanıyım. E-ticaret, üretim ve operasyon konularında size yardımcı olabilirim. Nasıl yardımcı olabilirim?"}, {"role": "user", "content": "sen hangi modelsin"}, {"role": "assistant", "content": "Ben bir dil modeliyim. E-ticaret, üretim ve operasyon uzmanı olarak sipariş verilerini analiz edip profesyonel önerilerde bulunabilirim."}, {"role": "user", "content": "nasıl bir üretim stratejisi yapmam lazım bu raporda"}, {"role": "assistant", "content": "API hatası: {\\"error\\":{\\"message\\":\\"Request too large for model `llama-3.3-70b-versatile` in organization `org_01kfb55b1pfr5bd7n43n5d0zd7` service tier `on_demand` on tokens per minute (TPM): Limit 12000, Requested 23611, please reduce your message size and try again. Need more tokens? Upgrade to Dev Tier today at https://console.groq.com/settings/billing\\",\\"type\\":\\"tokens\\",\\"code\\":\\"rate_limit_exceeded\\"}}\\n"}]', '2026-01-19 18:12:48', '2026-01-19 18:12:48'),
	(5, 1, NULL, '[{"role": "assistant", "content": "Merhaba! Ben ZOLM AI asistanıyım. E-ticaret, üretim ve operasyon konularında size yardımcı olabilirim. Nasıl yardımcı olabilirim?"}, {"role": "user", "content": "ilk hangi ürünleri üretime almalıyım?"}, {"role": "assistant", "content": "API hatası: {\\"error\\":{\\"message\\":\\"Request too large for model `llama-3.3-70b-versatile` in organization `org_01kfb55b1pfr5bd7n43n5d0zd7` service tier `on_demand` on tokens per minute (TPM): Limit 12000, Requested 23587, please reduce your message size and try again. Need more tokens? Upgrade to Dev Tier today at https://console.groq.com/settings/billing\\",\\"type\\":\\"tokens\\",\\"code\\":\\"rate_limit_exceeded\\"}}\\n"}]', '2026-01-19 19:11:39', '2026-01-19 19:11:39'),
	(6, 1, 30, '[{"role": "assistant", "content": "Merhaba! Ben ZOLM AI asistanıyım. E-ticaret, üretim ve operasyon konularında size yardımcı olabilirim. Nasıl yardımcı olabilirim?"}, {"role": "user", "content": "üretimde hangi ürüne öncelik vermeliyim ?"}, {"role": "assistant", "content": "\'Kargoya Son Teslim Tarihi\' sütununu kontrol ettiğimizde, bugün gönderilmesi gereken siparişler arasında \\"lines puf, teddy kumaş kırık beyaz\\" ürünü bulunmaktadır. Bu ürünün toplam sipariş adedi 21\'dir ve \\"Kargoya Son Teslim Tarihi\\" 24.01.2026\'dir. \\n\\nÜretimde öncelik verilmesi gereken ürün \\"lines puf, teddy kumaş kırık beyaz\\" olmalıdır."}]', '2026-01-19 21:32:42', '2026-01-19 21:44:33');

-- tablo yapısı dökülüyor zolm.cache
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.cache: ~2 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
	('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab', 'i:1;', 1768868973),
	('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer', 'i:1768868973;', 1768868973);

-- tablo yapısı dökülüyor zolm.cache_locks
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.cache_locks: ~0 rows (yaklaşık) tablosu için veriler indiriliyor

-- tablo yapısı dökülüyor zolm.failed_jobs
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.failed_jobs: ~0 rows (yaklaşık) tablosu için veriler indiriliyor

-- tablo yapısı dökülüyor zolm.jobs
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.jobs: ~0 rows (yaklaşık) tablosu için veriler indiriliyor

-- tablo yapısı dökülüyor zolm.job_batches
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.job_batches: ~0 rows (yaklaşık) tablosu için veriler indiriliyor

-- tablo yapısı dökülüyor zolm.migrations
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.migrations: ~12 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(1, '0001_01_01_000000_create_users_table', 1),
	(2, '0001_01_01_000001_create_cache_table', 1),
	(3, '0001_01_01_000002_create_jobs_table', 1),
	(4, '2026_01_19_100001_create_roles_table', 1),
	(5, '2026_01_19_100002_create_profiles_table', 1),
	(6, '2026_01_19_100003_create_reports_table', 1),
	(7, '2026_01_19_100004_create_report_files_table', 1),
	(8, '2026_01_19_100005_create_ai_conversations_table', 1),
	(9, '2026_01_19_160000_add_ai_fields_to_profiles_table', 2),
	(10, '2026_01_19_232800_add_performance_indexes_to_reports', 3),
	(11, '2026_01_20_035000_add_role_to_users_table', 4),
	(12, '2026_01_20_035100_create_activity_logs_table', 4);

-- tablo yapısı dökülüyor zolm.password_reset_tokens
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.password_reset_tokens: ~0 rows (yaklaşık) tablosu için veriler indiriliyor

-- tablo yapısı dökülüyor zolm.profiles
CREATE TABLE IF NOT EXISTS `profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('production','operation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `input_config` json DEFAULT NULL,
  `output_config` json DEFAULT NULL,
  `ai_prompt` text COLLATE utf8mb4_unicode_ci,
  `sample_input_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sample_output_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_generated_rules` json DEFAULT NULL,
  `is_ai_generated` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('draft','analyzing','ready','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profiles_user_id_foreign` (`user_id`),
  CONSTRAINT `profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.profiles: ~3 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `profiles` (`id`, `user_id`, `name`, `type`, `input_config`, `output_config`, `ai_prompt`, `sample_input_path`, `sample_output_path`, `ai_generated_rules`, `is_ai_generated`, `status`, `error_message`, `is_default`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Varsayılan Üretim', 'production', '{"categories": {"BERJER": "BERJER", "PUF & BENCH": "PUF", "KÖŞE & KANEPE": "KÖŞE VE KANEPE"}, "sheet_name": "Siparişlerim-Detaylı", "category_column": "Renk Etiketi"}', '{"files": [{"name": "GÜNLÜK SİPARİŞLER", "sheets": ["TOPLAM SİPARİŞ", "SİPARİŞ TAKİP"]}, {"name": "BERJER", "sheets": ["DENİZLİ TOPLAM SİPARİŞ", "NAZİLLİ SİPARİŞ TAKİP", "NAZİLLİ KARGO TAKİP"]}, {"name": "PUF", "sheets": ["DENİZLİ TOPLAM SİPARİŞ", "NAZİLLİ SİPARİŞ TAKİP", "NAZİLLİ KARGO TAKİP"]}, {"name": "KÖŞE VE KANEPE", "sheets": ["DENİZLİ TOPLAM SİPARİŞ", "NAZİLLİ SİPARİŞ TAKİP", "NAZİLLİ KARGO TAKİP"]}]}', NULL, NULL, NULL, NULL, 0, 'ready', NULL, 1, '2026-01-19 08:16:41', '2026-01-19 08:16:41'),
	(2, 1, 'Varsayılan Operasyon', 'operation', '{"sheet_name": "Siparişlerim-Detaylı"}', '{"files": [{"name": "OPERASYONLİSTE"}]}', NULL, NULL, NULL, NULL, 0, 'ready', NULL, 0, '2026-01-19 08:16:41', '2026-01-19 21:28:02'),
	(14, 1, 'GÜNLÜK ONUR 96', 'operation', '{"sheets": [{"name": "Siparişlerim-Detaylı", "columns": ["Pazaryeri", "Mağaza", "Sip. Tarihi", "Sipariş No", "Paket No", "Pazaryeri Kullanıcı", "Muhasebe Sipariş No ", "Fatura No", "e-Fatura/e-Arşiv No", "e-Fatura/e-Arşiv Tarih", "Fatura - Müşteri", "Fatura - TCKN", "Fatura - VN", "Fatura - VD", "Fatura - Adres", "Fatura - Posta Kodu", "Fatura - İlçe", "Fatura - İl", "Fatura - Ülke", "Fatura - Telefon", "Sevk - Müşteri", "Sevk - TCKN", "Sevk - VN", "Sevk - VD", "Sevk - Adres", "Sevk - Posta Kodu", "Sevk - İlçe", "Sevk - İl", "Sevk - Ülke", "Sevk - Telefon", "Mail", "Kargo Firması", "Kargo Barkod", "Kargo Takip No", "Sipariş Satır Durumu", "Stok Kodu", "Gtin (Barkod)", "Mpn", "Gtip", "Ürün", "Varyant", "Kategori", "Adet", "Stok Çarpanı", "Kdv Oran", "Birim Fiyat", "Birim İndirim", "Birim Pazaryeri İndirim", "Satır Fiyat", "Satır Komisyon", "Satır Döviz", "Satır Kur", "Ürün Birim Maliyet", "Ürün Toplam Maliyet", "Ürün Maliyet Döviz", "Ürün Maliyet Kur", "Satır Kargo Fiyat", "Satır Kargo Döviz", "Satır Kargo Kur", "Raf", "Birim Desi", "Birim Ağırlık (kg)", "En", "Boy", "Yükseklik", "Müşteri Notu", "Sipariş Oluşturma Tarihi", "Kargoya Son Teslim Tarihi", "Ödeme Tipi", "Paketlenme Tarihi", "Atanan Kullanıcı", "Paketleyen Kullanıcı", "Satır Özelleştirmesi", "Ek Değerler", "Renk Etiketi", "Marka", "Ana Ürün Stok Kodu", "Kâr"], "row_count": 124, "sample_data": [{"En": "", "Boy": "", "Mpn": "", "Raf": "", "Adet": "1", "Gtip": "", "Kâr": "520.45463636363638", "Mail": "pf+axmk9qj9@trendyolmail.com", "Marka": "Zem", "Ürün": "Long Line Sandıklı Puf , Gri Silver TYBZEMLNGS170414975, one size [Renk:Gr - Silver]", "Mağaza": "ZEM HOME", "Varyant": "[Renk:Gr - Silver]", "Kategori": "Ev & Mobilya -> Mobilya -> Salon & Oturma Odası -> Kanepe & Koltuklar -> Puf & Bench", "Kdv Oran": "10", "Paket No": "3548383754", "Fatura No": "", "Pazaryeri": "Trendyol", "Sevk - VD": "", "Sevk - VN": "", "Stok Kodu": "1PUFZEM00604", "Birim Desi": "", "Satır Kur": "1,00000", "Sevk - İl": "Aydın", "Yükseklik": "", "Birim Fiyat": "1.549,90", "Fatura - VD": "", "Fatura - VN": "", "Sevk - TCKN": "", "Sip. Tarihi": "19.01.2026 15:57", "Sipariş No": "10892363783", "Ödeme Tipi": "Pazaryeri", "Ek Değerler": "", "Fatura - İl": "Aydın", "Kargo Barkod": "7270029750449347", "Renk Etiketi": "PUF & BENCH", "Satır Fiyat": "1.472,40", "Sevk - Adres": "Cumhuriyet Mah 439 sok no:3 daire:4 Miryap plus apt  ", "Sevk - Ülke": "Türkiye", "Fatura - TCKN": "", "Gtin (Barkod)": "15071881", "Satır Döviz": "TL", "Sevk - İlçe": "İncirliova", "Birim İndirim": "77,50", "Fatura - Adres": "Cumhuriyet Mah 439 sok no:3 daire:4 Miryap plus apt  ", "Fatura - Ülke": "Türkiye", "Kargo Firması": "Sürat Kargo", "Kargo Takip No": "", "Müşteri Notu": "", "Sevk - Telefon": "", "Stok Çarpanı": "1", "Fatura - İlçe": "İncirliova", "Satır Komisyon": "", "Fatura - Telefon": "", "Satır Kargo Kur": "1,00000", "Sevk - Müşteri": "duygu çallak", "Paketlenme Tarihi": "", "Sevk - Posta Kodu": "09000", "Atanan Kullanıcı": "", "Fatura - Müşteri": "duygu çallak", "Satır Kargo Fiyat": "", "Ürün Maliyet Kur": "1,00000", "Fatura - Posta Kodu": "09000", "Satır Kargo Döviz": "", "Ana Ürün Stok Kodu": "1PUFZEM00604", "e-Fatura/e-Arşiv No": "", "Ürün Birim Maliyet": "899,90", "Birim Ağırlık (kg)": "", "Muhasebe Sipariş No ": "", "Pazaryeri Kullanıcı": "3676289", "Ürün Maliyet Döviz": "TL", "Ürün Toplam Maliyet": "899,90", "Paketleyen Kullanıcı": "", "Sipariş Satır Durumu": "Onaylandı", "Satır Özelleştirmesi": "", "e-Fatura/e-Arşiv Tarih": "", "Birim Pazaryeri İndirim": "", "Kargoya Son Teslim Tarihi": "24.01.2026 15:58", "Sipariş Oluşturma Tarihi": "19.01.2026 16:00"}, {"En": "", "Boy": "", "Mpn": "", "Raf": "", "Adet": "1", "Gtip": "", "Kâr": "2935.18690909090856", "Mail": "pf+axmkem32@trendyolmail.com", "Marka": "Zem", "Ürün": "Hippi Deri Puf, Taba TYC00468831285, one size [Renk:Taba]", "Mağaza": "ZEM HOME", "Varyant": "[Renk:Taba]", "Kategori": "Ev & Mobilya -> Mobilya -> Salon & Oturma Odası -> Kanepe & Koltuklar -> Puf & Bench", "Kdv Oran": "10", "Paket No": "3548352154", "Fatura No": "", "Pazaryeri": "Trendyol", "Sevk - VD": "", "Sevk - VN": "", "Stok Kodu": "1PUFZEM00433", "Birim Desi": "", "Satır Kur": "1,00000", "Sevk - İl": "Bursa", "Yükseklik": "", "Birim Fiyat": "5.499,90", "Fatura - VD": "", "Fatura - VN": "", "Sevk - TCKN": "", "Sip. Tarihi": "19.01.2026 15:46", "Sipariş No": "10892331447", "Ödeme Tipi": "Pazaryeri", "Ek Değerler": "", "Fatura - İl": "Bursa", "Kargo Barkod": "7270029750141258", "Renk Etiketi": "PUF & BENCH", "Satır Fiyat": "5.224,90", "Sevk - Adres": "Balat Mah. Ahi evran cd. Sude sok. Referans Açelya sitesi No:19 DAİRE:22Referans Açelya ", "Sevk - Ülke": "Türkiye", "Fatura - TCKN": "", "Gtin (Barkod)": "", "Satır Döviz": "TL", "Sevk - İlçe": "Nilüfer", "Birim İndirim": "275,00", "Fatura - Adres": "Balat Mah. Ahi evran cd. Sude sok. Referans Açelya sitesi No:19 DAİRE:22Referans Açelya ", "Fatura - Ülke": "Türkiye", "Kargo Firması": "Sürat Kargo", "Kargo Takip No": "", "Müşteri Notu": "", "Sevk - Telefon": "", "Stok Çarpanı": "1", "Fatura - İlçe": "Nilüfer", "Satır Komisyon": "", "Fatura - Telefon": "", "Satır Kargo Kur": "1,00000", "Sevk - Müşteri": "Beyza Tatlıcılar", "Paketlenme Tarihi": "", "Sevk - Posta Kodu": "16000", "Atanan Kullanıcı": "", "Fatura - Müşteri": "Beyza Tatlıcılar", "Satır Kargo Fiyat": "", "Ürün Maliyet Kur": "1,00000", "Fatura - Posta Kodu": "16000", "Satır Kargo Döviz": "", "Ana Ürün Stok Kodu": "1PUFZEM00433", "e-Fatura/e-Arşiv No": "", "Ürün Birim Maliyet": "1.996,19", "Birim Ağırlık (kg)": "", "Muhasebe Sipariş No ": "", "Pazaryeri Kullanıcı": "5819304", "Ürün Maliyet Döviz": "TL", "Ürün Toplam Maliyet": "1.996,19", "Paketleyen Kullanıcı": "", "Sipariş Satır Durumu": "Onaylandı", "Satır Özelleştirmesi": "", "e-Fatura/e-Arşiv Tarih": "", "Birim Pazaryeri İndirim": "", "Kargoya Son Teslim Tarihi": "24.01.2026 15:46", "Sipariş Oluşturma Tarihi": "19.01.2026 15:51"}, {"En": "", "Boy": "", "Mpn": "", "Raf": "", "Adet": "1", "Gtip": "", "Kâr": "8125.60833333333267", "Mail": "rkoxgb8mnp6.lu9slyy5k@notification.mirakl.net", "Marka": "", "Ürün": "Sevilla Bohem Üçlü Kanepe Natürel Ahşap, Kırık Beyaz", "Mağaza": "ZEM DİZAYN", "Varyant": "", "Kategori": "", "Kdv Oran": "20", "Paket No": "1085754570", "Fatura No": "", "Pazaryeri": "Koçtaş", "Sevk - VD": "", "Sevk - VN": "", "Stok Kodu": "00051997100", "Birim Desi": "", "Satır Kur": "1,00000", "Sevk - İl": "İZMİR", "Yükseklik": "", "Birim Fiyat": "10.934,91", "Fatura - VD": "", "Fatura - VN": "", "Sevk - TCKN": "", "Sip. Tarihi": "19.01.2026 15:37", "Sipariş No": "1085754570-A", "Ödeme Tipi": "Pazaryeri", "Ek Değerler": "", "Fatura - İl": "İZMİR", "Kargo Barkod": "", "Renk Etiketi": "BERJER", "Satır Fiyat": "10.934,91", "Sevk - Adres": "Karadut sokak apt no 9 kat 3 daire 5 Balçova izmir KORUTÜRK", "Sevk - Ülke": "Türkiye", "Fatura - TCKN": "", "Gtin (Barkod)": "", "Satır Döviz": "TL", "Sevk - İlçe": "BALÇOVA", "Birim İndirim": "0,00", "Fatura - Adres": "Karadut sokak apt no 9 kat 3 daire 5 Balçova izmir KORUTÜRK", "Fatura - Ülke": "Türkiye", "Kargo Firması": "", "Kargo Takip No": "", "Müşteri Notu": "", "Sevk - Telefon": "5301207135", "Stok Çarpanı": "1", "Fatura - İlçe": "BALÇOVA", "Satır Komisyon": "1.184,18", "Fatura - Telefon": "5301207135", "Satır Kargo Kur": "", "Sevk - Müşteri": "Evren Akin", "Paketlenme Tarihi": "", "Sevk - Posta Kodu": "35000", "Atanan Kullanıcı": "", "Fatura - Müşteri": "Evren Akin", "Satır Kargo Fiyat": "", "Ürün Maliyet Kur": "", "Fatura - Posta Kodu": "35000", "Satır Kargo Döviz": "", "Ana Ürün Stok Kodu": "", "e-Fatura/e-Arşiv No": "", "Ürün Birim Maliyet": "", "Birim Ağırlık (kg)": "", "Muhasebe Sipariş No ": "", "Pazaryeri Kullanıcı": "evrenakin2032@gmail.com", "Ürün Maliyet Döviz": "", "Ürün Toplam Maliyet": "", "Paketleyen Kullanıcı": "", "Sipariş Satır Durumu": "Onaylandı", "Satır Özelleştirmesi": "", "e-Fatura/e-Arşiv Tarih": "", "Birim Pazaryeri İndirim": "", "Kargoya Son Teslim Tarihi": "05.02.2026 00:00", "Sipariş Oluşturma Tarihi": "19.01.2026 16:26"}]}], "file_name": "TQIWF2ahJr8X64XpBoOLAWUMT0XnapT6pUwS1Ume.xlsx"}', '{"sheets": [{"name": "BERJER TOPLAM SİPARİŞ", "columns": ["Ürün", "Adet"], "row_count": 18, "sample_data": [{"Adet": "1", "Ürün": "Alaves Hazeran Jüt Çay Seti Ceviz Ahşap,sütlü Kahve (1 ADET IKİLİ BERJER 1 ADET TEKLİ BERJER) "}, {"Adet": "1", "Ürün": "Alaves Hazeran Jüt Çay Seti Ceviz Ahşap,sütlü Kahve (1 ADET IKİLİ BERJER 2 ADET TEKLİ BERJER) "}, {"Adet": "2", "Ürün": "Alaves Hazeranlı Bohem Berjer Ceviz Ahşap, Sütlü Kahve"}]}, {"name": "BERJER SİPARİŞ TAKİP", "columns": ["Pazaryeri", "Mağaza", "Sip. Tarihi", "Sipariş No", "Sevk - Müşteri", "Ürün", "Adet", "Müşteri Notu", "Kargoya Son Teslim Tarihi"], "row_count": 24, "sample_data": [{"Adet": "1", "Ürün": "Sevilla Bohem Üçlü Kanepe Natürel Ahşap, Kırık Beyaz", "Mağaza": "ZEM DİZAYN", "Pazaryeri": "Koçtaş", "Sip. Tarihi": "19.01.2026 15:37", "Sipariş No": "1085754570-A", "Müşteri Notu": "", "Sevk - Müşteri": "Evren Akin", "Kargoya Son Teslim Tarihi": "05.02.2026 00:00"}, {"Adet": "1", "Ürün": "Petra Çay Seti, Siyah Kazayağı/Taba Deri (1 ADET TEKLİ BERJER 1 ADET PUF) ZEMPTRC21, one size [Renk:Kahverengi]", "Mağaza": "ZEM HOME", "Pazaryeri": "Trendyol", "Sip. Tarihi": "19.01.2026 12:58", "Sipariş No": "10891880750", "Müşteri Notu": "", "Sevk - Müşteri": "havva custan", "Kargoya Son Teslim Tarihi": "02.02.2026 12:58"}, {"Adet": "1", "Ürün": "Tosca Bohem Çay Seti Ceviz Ahşap, Sütlü Kahve- 1 ADET İKİLİ BERJER + 2 ADET TEKLİ BERJER", "Mağaza": "ZEM DİZAYN - ÖZEL SİPARİŞ", "Pazaryeri": "PraPazar", "Sip. Tarihi": "19.01.2026 12:15", "Sipariş No": "15655", "Müşteri Notu": "", "Sevk - Müşteri": "Mustafa Deniz", "Kargoya Son Teslim Tarihi": "26.01.2026 22:00"}]}, {"name": "KÖŞE VE KANEPE TOPLAM SİPARİŞ", "columns": ["Ürün", "Adet"], "row_count": 6, "sample_data": [{"Adet": "3", "Ürün": "Legna Bohem Kanepe, Açık Krem "}, {"Adet": "2", "Ürün": "Legna Bohem Köşe Takımı, Açık Krem "}, {"Adet": "2", "Ürün": "Legna Bohem Köşe Takımı, Gri "}]}, {"name": "KÖŞE VE KANEPE SİPARİŞ TAKİP", "columns": ["Pazaryeri", "Mağaza", "Sip. Tarihi", "Sipariş No", "Sevk - Müşteri", "Ürün", "Adet", "Müşteri Notu", "Kargoya Son Teslim Tarihi"], "row_count": 10, "sample_data": [{"Adet": "1", "Ürün": "Zem Legna Bohem Köşe Takımı, Gri [Renk:Gri]", "Mağaza": "ZEM DİZAYN", "Pazaryeri": "HepsiBurada", "Sip. Tarihi": "19.01.2026 14:09", "Sipariş No": "4246213875", "Müşteri Notu": "", "Sevk - Müşteri": "Reyhani Yayla", "Kargoya Son Teslim Tarihi": "31.01.2026 23:59"}, {"Adet": "1", "Ürün": "Legna Bohem Köşe Takımı, Gri ZEMKŞLG, one size [Renk:Gri]", "Mağaza": "ZEM HOME", "Pazaryeri": "Trendyol", "Sip. Tarihi": "19.01.2026 12:11", "Sipariş No": "10891758785", "Müşteri Notu": "", "Sevk - Müşteri": "Dursun Ali Akdemir", "Kargoya Son Teslim Tarihi": "02.02.2026 12:11"}, {"Adet": "1", "Ürün": "Legna Bohem Köşe Takımı, Açık Krem ZEMKŞLG, one size [Renk:Açık Krem]", "Mağaza": "ZEM HOME", "Pazaryeri": "Trendyol", "Sip. Tarihi": "18.01.2026 08:33", "Sipariş No": "10888696679", "Müşteri Notu": "", "Sevk - Müşteri": "Belgüzar Akkaya", "Kargoya Son Teslim Tarihi": "02.02.2026 08:33"}]}, {"name": "PUF TOPLAM SİPARİŞ", "columns": ["Ürün", "Adet"], "row_count": 30, "sample_data": [{"Adet": "1", "Ürün": "Bella Petek Kalorifer Önü Ikili Minder Takımı Bordo"}, {"Adet": "5", "Ürün": "Contes Bohem Sandıklı Orta Sehpa Puf/Bench , Sütlü Kahve "}, {"Adet": "1", "Ürün": "Contes Bohem Sandıklı Orta Sehpa Puf/bench, Gri "}]}, {"name": "PUF SİPARİŞ TAKİP", "columns": ["Pazaryeri", "Mağaza", "Sip. Tarihi", "Sipariş No", "Sevk - Müşteri", "Ürün", "Adet", "Müşteri Notu", "Kargoya Son Teslim Tarihi"], "row_count": 89, "sample_data": [{"Adet": "1", "Ürün": "Long Line Sandıklı Puf , Gri Silver TYBZEMLNGS170414975, one size [Renk:Gr - Silver]", "Mağaza": "ZEM HOME", "Pazaryeri": "Trendyol", "Sip. Tarihi": "19.01.2026 15:57", "Sipariş No": "10892363783", "Müşteri Notu": "", "Sevk - Müşteri": "duygu çallak", "Kargoya Son Teslim Tarihi": "24.01.2026 15:58"}, {"Adet": "1", "Ürün": "Hippi Deri Puf, Taba TYC00468831285, one size [Renk:Taba]", "Mağaza": "ZEM HOME", "Pazaryeri": "Trendyol", "Sip. Tarihi": "19.01.2026 15:46", "Sipariş No": "10892331447", "Müşteri Notu": "", "Sevk - Müşteri": "Beyza Tatlıcılar", "Kargoya Son Teslim Tarihi": "24.01.2026 15:46"}, {"Adet": "1", "Ürün": "Fine Black-silver Puf ZEMLUNGEPUF01, one size [Renk:SİYAH]", "Mağaza": "ZEM HOME", "Pazaryeri": "Trendyol", "Sip. Tarihi": "19.01.2026 15:08", "Sipariş No": "10892230662", "Müşteri Notu": "", "Sevk - Müşteri": "BURCU ŞEKERCİ", "Kargoya Son Teslim Tarihi": "24.01.2026 15:08"}]}], "file_name": "GVbesOaZOW10RN2FdC2STGDWcSUFRW6Ycyrtyteb.xlsx"}', 'Bu profil, Entegratör sipariş Excel’ini (Siparişlerim-Detaylı) okuyarak sipariş satırlarını Renk Etiketi üzerinden standart bir GRUP alanına dönüştürür ve sadece üretime girecek grupları (BERJER, KÖŞE VE KANEPE, PUF) rapora dahil eder. Ardından normalize_product adımıyla Ürün metnini temizler ve aynı ürünün farklı yazımlarını tek isme indirger; böylece “TOPLAM SİPARİŞ” sayfalarında group_by = Ürün ile yapılan toplama gerçekten aynı ürünü tek satırda birleştirir.\n​\n\nNormalizasyon; “Zem …” öneki, one size, sondaki [Renk: …], ZEM... ile başlayan model kodları, LOUCA01 gibi ürün kodları ve TCY/TYZ/... gibi uzun alfanumerik kodları kaldırır; sonrasında boşluk ve noktalama düzeni normalize edilerek tek bir standart ürün adı üretilir. Çıktı, tek bir dosyada (GUNLUK-SIPARIS-OTOMATIK.xlsx) her grup için iki sayfa olacak şekilde üretilir: {GRUP} TOPLAM SİPARİŞ (Ürün, Adet toplam) ve {GRUP} SİPARİŞ TAKİP (Sip. Tarihi sıralı detay).', 'profile-samples/86O0QCy3lrBHli7sPeW5pFjL5ynWQ5cHXeOquZlP.xlsx', 'profile-samples/KfXVeZSC00wKmz2byyqup3rcmdYZFuYuESdP2nTG.xlsx', '{"job": {"name": "Sipariş Dönüşümü", "description": "Ürün normalizasyonu + grup bazlı günlük üretim çıktısı"}, "input": {"sheet_name": "Siparişlerim-Detaylı"}, "outputs": [{"sheets": [{"name": "{CATEGORY} TOPLAM SİPARİŞ", "type": "summary", "columns": ["Ürün", "Adet"], "group_by": "Ürün", "aggregate": [{"column": "Adet", "function": "SUM"}]}, {"name": "{CATEGORY} SİPARİŞ TAKİP", "type": "detail", "columns": ["Pazaryeri", "Mağaza", "Sip. Tarihi", "Sipariş No", "Sevk - Müşteri", "Ürün", "Adet", "Müşteri Notu", "Kargoya Son Teslim Tarihi"], "sort_by": "Sip. Tarihi"}], "filename_pattern": "GUNLUK-SIPARIS-OTOMATIK.xlsx"}], "version": "1.0", "transformations": [{"type": "map_column", "source": "Renk Etiketi", "target": "GRUP", "mapping": {"PUF": "PUF", "BERJER": "BERJER", "PUF & BENCH": "PUF", "KÖŞE & KANEPE": "KÖŞE VE KANEPE", "KÖŞE VE KANEPE": "KÖŞE VE KANEPE"}, "description": "Renk Etiketi → GRUP standardizasyonu"}, {"type": "filter", "condition": "GRUP IN [\'BERJER\', \'KÖŞE VE KANEPE\', \'PUF\']", "description": "Sadece üretime girecek grupları tut"}, {"type": "normalize_product", "rules": ["Baştaki \'Zem \' prefix\'ini kaldır", "Sondaki \'[Renk: ...]\' bilgisini kaldır", "\'one size\' ifadelerini kaldır", "\'ZEM\' ile başlayan model kodlarını kaldır (örn: ZEMLİNES, ZEMLOUCA, ZEMBELLA01, ZEMJRV, ZEMRCK01, ZEMLONGLİNE vb.)", "\'TYZ/TCY/TYC/TBZ/TBY/TCT\' ile başlayan uzun kodları kaldır (örn: TCY00468831285 vb.)", "Rakam içeren uzun alfanumerik kodları kaldır (örn: LOUCA01 vb.)", "Boşlukları normalize et (çoklu boşluk → tek boşluk) ve trim uygula"], "source": "Ürün", "target": "Ürün", "description": "Ürün adını temizle: kod/renk/beden ZEM/LOUCA01/TCY... kodlarını, one size ve [Renk:...] parçalarını kaldır parçalarını kaldır ve aynı ürünleri tek isimde birleştir"}]}', 1, 'ready', NULL, 1, '2026-01-19 20:31:23', '2026-01-19 21:28:02');

-- tablo yapısı dökülüyor zolm.reports
CREATE TABLE IF NOT EXISTS `reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `profile_id` bigint unsigned DEFAULT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','processing','success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reports_created_at_index` (`created_at`),
  KEY `reports_status_index` (`status`),
  KEY `reports_status_created_index` (`status`,`created_at`),
  KEY `reports_user_id_index` (`user_id`),
  KEY `reports_profile_id_index` (`profile_id`),
  CONSTRAINT `reports_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.reports: ~1 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `reports` (`id`, `user_id`, `profile_id`, `original_filename`, `status`, `error_message`, `created_at`, `updated_at`) VALUES
	(30, 1, 14, 'girdi siparisler.xlsx', 'success', NULL, '2026-01-19 21:28:34', '2026-01-19 21:28:36');

-- tablo yapısı dökülüyor zolm.report_files
CREATE TABLE IF NOT EXISTS `report_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint unsigned NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sheet_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_files_report_id_index` (`report_id`),
  CONSTRAINT `report_files_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.report_files: ~1 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `report_files` (`id`, `report_id`, `filename`, `file_path`, `sheet_type`, `created_at`, `updated_at`) VALUES
	(27, 30, 'GUNLUK-SIPARIS-OTOMATIK.xlsx', 'private/reports/30/GUNLUK-SIPARIS-OTOMATIK.xlsx', 'dynamic', '2026-01-19 21:28:36', '2026-01-19 21:28:36');

-- tablo yapısı dökülüyor zolm.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.roles: ~4 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `roles` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES
	(1, 'Admin', 'admin', '2026-01-19 08:16:41', '2026-01-19 08:16:41'),
	(2, 'Üretim Sorumlusu', 'uretim_sorumlusu', '2026-01-19 08:16:41', '2026-01-19 08:16:41'),
	(3, 'Operasyon Sorumlusu', 'operasyon_sorumlusu', '2026-01-19 08:16:41', '2026-01-19 08:16:41'),
	(4, 'CRM Sorumlusu', 'crm_sorumlusu', '2026-01-19 08:16:41', '2026-01-19 08:16:41');

-- tablo yapısı dökülüyor zolm.sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.sessions: ~2 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
	('aFzDqsWNUqXdbqBMIYwbiFfVfnNYIaxIXyjZvAF1', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoic0F2Y0U5NFBkQnpsMEFLd3hyQnY0cm5Tb0REa1c0ODhuSnRxenVRNSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly96b2xtLnRlc3QvcHJvZHVjdGlvbiI7czo1OiJyb3V0ZSI7czoxMDoicHJvZHVjdGlvbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7fQ==', 1768919781),
	('kikYqat8T2cGBrkB2sViIY9d8rUDTxiTWyphDqcZ', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiamg5eVg1bThqdGhyNERGU3JNb3VIRVlKb1VkT0EwbFA3eWtoWGNVVyI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyNjoiaHR0cDovL3pvbG0udGVzdC9vcGVyYXRpb24iO31zOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czoyNjoiaHR0cDovL3pvbG0udGVzdC9vcGVyYXRpb24iO3M6NToicm91dGUiO3M6OToib3BlcmF0aW9uIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1768916447);

-- tablo yapısı dökülüyor zolm.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','manager','operator') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operator',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- zolm.users: ~2 rows (yaklaşık) tablosu için veriler indiriliyor
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `role`, `is_active`, `last_login_at`, `avatar`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Admin', 'admin@zolm.test', 'admin', 1, NULL, NULL, NULL, '$2y$12$oVAa7m8k47VyJAzZa7zgDuGUHEtw18P8YJBc5pjbwWJg6C98TWB8K', '9kuwpJkmO1vSxZjOAczj5esmvUurgwv6oomG2SF1LYIJSbFohNCZkw5EtG59', '2026-01-19 08:16:41', '2026-01-19 08:16:41'),
	(2, NULL, 'Cuma Yıldırım', 'cyildirim@gmail.com', 'admin', 1, NULL, NULL, NULL, '$2y$12$l6Gy.fZaAysdZ/4xReLBf.CWjE8MRs7JuhednEZnMSwesmolTpS2e', NULL, '2026-01-19 22:00:29', '2026-01-19 22:00:29');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
