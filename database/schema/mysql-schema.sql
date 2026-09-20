/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `causer_type` varchar(255) DEFAULT NULL,
  `causer_id` bigint(20) unsigned DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `batch_uuid` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(200) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'manager',
  `token` varchar(64) NOT NULL,
  `invited_by` bigint(20) unsigned NOT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_invitations_token_unique` (`token`),
  KEY `admin_invitations_invited_by_foreign` (`invited_by`),
  KEY `admin_invitations_email_index` (`email`),
  CONSTRAINT `admin_invitations_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transaction_splits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_transaction_splits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `bank_transaction_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `amount` bigint(20) NOT NULL,
  `memo` varchar(300) DEFAULT NULL,
  `staff_memo` varchar(300) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_transaction_splits_order_id_foreign` (`order_id`),
  KEY `bank_transaction_splits_patient_id_foreign` (`patient_id`),
  KEY `bank_transaction_splits_created_by_foreign` (`created_by`),
  KEY `bank_transaction_splits_bank_transaction_id_index` (`bank_transaction_id`),
  CONSTRAINT `bank_transaction_splits_bank_transaction_id_foreign` FOREIGN KEY (`bank_transaction_id`) REFERENCES `bank_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_transaction_splits_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_transaction_splits_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_transaction_splits_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tid` varchar(100) NOT NULL,
  `bank_code` varchar(10) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `trade_date` date DEFAULT NULL,
  `traded_at` datetime DEFAULT NULL,
  `trade_serial` varchar(30) DEFAULT NULL,
  `amount_in` bigint(20) NOT NULL DEFAULT 0,
  `amount_out` bigint(20) NOT NULL DEFAULT 0,
  `balance` bigint(20) DEFAULT NULL,
  `remark1` varchar(200) DEFAULT NULL,
  `remark2` varchar(200) DEFAULT NULL,
  `remark3` varchar(200) DEFAULT NULL,
  `remark4` varchar(200) DEFAULT NULL,
  `bank_memo` varchar(500) DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `kind` varchar(20) DEFAULT NULL,
  `matched_by` bigint(20) unsigned DEFAULT NULL,
  `matched_at` timestamp NULL DEFAULT NULL,
  `staff_memo` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_transactions_tid_unique` (`tid`),
  KEY `bank_transactions_patient_id_foreign` (`patient_id`),
  KEY `bank_transactions_matched_by_foreign` (`matched_by`),
  KEY `bank_transactions_trade_date_id_index` (`trade_date`,`id`),
  KEY `bank_transactions_order_id_index` (`order_id`),
  CONSTRAINT `bank_transactions_matched_by_foreign` FOREIGN KEY (`matched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_transactions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_office_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_office_areas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `billing_office_id` bigint(20) unsigned NOT NULL,
  `sido` varchar(30) DEFAULT NULL,
  `sigungu` varchar(40) DEFAULT NULL,
  `emd` varchar(40) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bo_area_unique` (`billing_office_id`,`sido`,`sigungu`,`emd`),
  KEY `billing_office_areas_billing_office_id_index` (`billing_office_id`),
  KEY `billing_office_areas_emd_index` (`emd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_offices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(10) NOT NULL DEFAULT 'nhis',
  `region` varchar(40) DEFAULT NULL,
  `office_name` varchar(100) NOT NULL,
  `dept` varchar(100) DEFAULT NULL,
  `manager_name` varchar(40) DEFAULT NULL,
  `title` varchar(40) DEFAULT NULL,
  `duty` varchar(200) DEFAULT NULL,
  `tel` varchar(40) DEFAULT NULL,
  `fax` varchar(40) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `note` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_offices_kind_is_active_index` (`kind`,`is_active`),
  KEY `billing_offices_office_name_index` (`office_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cashbill_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashbill_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `corp_num` varchar(20) NOT NULL COMMENT '사업자번호',
  `mgt_key` varchar(24) NOT NULL COMMENT '관리번호',
  `item_key` varchar(50) DEFAULT NULL COMMENT '팝빌 내부 키',
  `trade_type` varchar(20) DEFAULT NULL COMMENT '승인거래|취소거래',
  `trade_usage` varchar(20) DEFAULT NULL COMMENT '소득공제용|지출증빙용',
  `taxation_type` varchar(20) DEFAULT NULL COMMENT '과세|비과세',
  `total_amount` bigint(20) NOT NULL DEFAULT 0 COMMENT '합계금액',
  `supply_cost` bigint(20) NOT NULL DEFAULT 0 COMMENT '공급가액',
  `tax` bigint(20) NOT NULL DEFAULT 0 COMMENT '부가세',
  `service_fee` bigint(20) NOT NULL DEFAULT 0 COMMENT '봉사료',
  `issue_dt` varchar(14) DEFAULT NULL COMMENT '발행일시 YYYYMMDDHHmmss',
  `trade_dt` varchar(14) DEFAULT NULL COMMENT '거래일시',
  `trade_date` varchar(8) DEFAULT NULL COMMENT '거래일자 YYYYMMDD',
  `reg_dt` varchar(14) DEFAULT NULL COMMENT '등록일시',
  `state_code` smallint(6) NOT NULL DEFAULT 0 COMMENT '100임시|200대기|300발행|400취소',
  `state_dt` varchar(14) DEFAULT NULL COMMENT '상태변경일시',
  `state_memo` varchar(300) DEFAULT NULL,
  `identity_num` varchar(30) DEFAULT NULL COMMENT '신분확인번호',
  `customer_name` varchar(100) DEFAULT NULL COMMENT '고객명',
  `item_name` varchar(200) DEFAULT NULL COMMENT '품목명',
  `order_number` varchar(50) DEFAULT NULL COMMENT '주문번호',
  `email` varchar(100) DEFAULT NULL,
  `hp` varchar(30) DEFAULT NULL,
  `confirm_num` varchar(50) DEFAULT NULL COMMENT '국세청 승인번호',
  `org_confirm_num` varchar(50) DEFAULT NULL COMMENT '원본 국세청 승인번호(취소거래)',
  `org_trade_date` varchar(8) DEFAULT NULL COMMENT '원본 거래일자(취소거래)',
  `nts_result` tinyint(4) DEFAULT NULL COMMENT '0전송전|1전송중|2성공|3실패',
  `nts_result_dt` varchar(14) DEFAULT NULL,
  `nts_result_code` varchar(10) DEFAULT NULL,
  `nts_result_message` varchar(300) DEFAULT NULL,
  `nts_send_dt` varchar(14) DEFAULT NULL,
  `franchise_corp_num` varchar(20) DEFAULT NULL,
  `franchise_corp_name` varchar(100) DEFAULT NULL,
  `franchise_ceo_name` varchar(50) DEFAULT NULL,
  `franchise_addr` varchar(300) DEFAULT NULL,
  `franchise_tel` varchar(30) DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL COMMENT '마지막 팝빌 동기화 시각',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cashbill_records_corp_num_mgt_key_unique` (`corp_num`,`mgt_key`),
  KEY `cashbill_records_trade_dt_index` (`trade_dt`),
  KEY `cashbill_records_state_code_index` (`state_code`),
  KEY `cashbill_records_nts_result_index` (`nts_result`),
  KEY `cashbill_records_order_id_foreign` (`order_id`),
  CONSTRAINT `cashbill_records_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_room_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `reply_to_id` bigint(20) unsigned DEFAULT NULL,
  `thread_root_id` bigint(20) unsigned DEFAULT NULL,
  `thread_at` timestamp NULL DEFAULT NULL,
  `body` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(255) DEFAULT NULL,
  `attachment_size` bigint(20) unsigned DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_chat_room_id_foreign` (`chat_room_id`),
  KEY `chat_messages_user_id_foreign` (`user_id`),
  KEY `chat_messages_reply_to_id_foreign` (`reply_to_id`),
  KEY `chat_msg_thread_idx` (`chat_room_id`,`thread_at`,`thread_root_id`,`id`),
  CONSTRAINT `chat_messages_chat_room_id_foreign` FOREIGN KEY (`chat_room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_reply_to_id_foreign` FOREIGN KEY (`reply_to_id`) REFERENCES `chat_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_room_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_room_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_room_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_room_users_chat_room_id_user_id_unique` (`chat_room_id`,`user_id`),
  KEY `chat_room_users_user_id_foreign` (`user_id`),
  CONSTRAINT `chat_room_users_chat_room_id_foreign` FOREIGN KEY (`chat_room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_room_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `shop_user_name` varchar(100) DEFAULT NULL,
  `shop_user_phone` varchar(30) DEFAULT NULL,
  `shop_user_email` varchar(255) DEFAULT NULL,
  `type` enum('direct','group') NOT NULL DEFAULT 'direct',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `common_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `common_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(40) NOT NULL,
  `kind` varchar(40) DEFAULT NULL,
  `code` varchar(60) NOT NULL,
  `label` varchar(100) NOT NULL,
  `note` varchar(200) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `common_codes_group_code_unique` (`group`,`code`),
  KEY `common_codes_group_kind_is_active_index` (`group`,`kind`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delegation_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `delegation_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider_name` varchar(150) DEFAULT NULL,
  `provider_biz_no` varchar(40) DEFAULT NULL,
  `provider_ceo` varchar(50) DEFAULT NULL,
  `provider_phone` varchar(40) DEFAULT NULL,
  `account_receiver` varchar(100) DEFAULT NULL,
  `account_bank` varchar(50) DEFAULT NULL,
  `account_holder` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `period_years` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `sig_x` decimal(6,2) NOT NULL DEFAULT 164.00,
  `sig_y` decimal(6,2) NOT NULL DEFAULT 266.00,
  `sig_w` decimal(6,2) NOT NULL DEFAULT 28.00,
  `field_positions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`field_positions`)),
  `gsig_x` double DEFAULT NULL COMMENT '보호자 서명 X (mm)',
  `gsig_y` double DEFAULT NULL,
  `gsig_w` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delegation_signs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `delegation_signs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `src_no` int(10) unsigned DEFAULT NULL,
  `source` varchar(10) NOT NULL DEFAULT 'list',
  `customer_name` varchar(100) NOT NULL,
  `dealer_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `guardian_phone` varchar(20) DEFAULT NULL,
  `main_contact` varchar(10) NOT NULL DEFAULT 'patient',
  `resident_no` text DEFAULT NULL,
  `resident_no_masked` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `guardian_name` varchar(50) DEFAULT NULL,
  `guardian_relation` varchar(20) DEFAULT NULL,
  `guardian_birth_date` date DEFAULT NULL,
  `guardian_signature_data` longtext DEFAULT NULL,
  `guardian_sign_path` varchar(255) DEFAULT NULL,
  `guardian_id_path` varchar(255) DEFAULT NULL,
  `guardian_id_mime` varchar(50) DEFAULT NULL,
  `next_repurchase_at` date DEFAULT NULL,
  `last_register_at` date DEFAULT NULL,
  `rx_days` smallint(5) unsigned DEFAULT NULL,
  `last_confirm_at` date DEFAULT NULL,
  `src_status` varchar(30) DEFAULT NULL,
  `rx_type` varchar(30) DEFAULT NULL,
  `benefit_class` varchar(30) DEFAULT NULL,
  `last_sale_status` varchar(30) DEFAULT NULL,
  `token` varchar(32) DEFAULT NULL,
  `sent_to` varchar(20) DEFAULT NULL,
  `sent_by_id` bigint(20) unsigned DEFAULT NULL,
  `sent_by_name` varchar(50) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','sent','signed','declined') NOT NULL DEFAULT 'pending',
  `agree_delegation` tinyint(1) NOT NULL DEFAULT 0,
  `agree_privacy` tinyint(1) NOT NULL DEFAULT 0,
  `agree_marketing` tinyint(1) NOT NULL DEFAULT 0,
  `signed_at` timestamp NULL DEFAULT NULL,
  `sign_path` varchar(255) DEFAULT NULL,
  `sign_filename` varchar(120) DEFAULT NULL,
  `sign_base64` longtext DEFAULT NULL,
  `nice_verified_at` timestamp NULL DEFAULT NULL,
  `nice_name` varchar(60) DEFAULT NULL,
  `nice_birthdate` varchar(8) DEFAULT NULL,
  `nice_gender` varchar(1) DEFAULT NULL,
  `nice_mobile` varchar(20) DEFAULT NULL,
  `nice_ci` varchar(200) DEFAULT NULL,
  `nice_di` varchar(100) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delegation_signs_token_unique` (`token`),
  KEY `delegation_signs_customer_name_index` (`customer_name`),
  KEY `delegation_signs_signed_at_index` (`signed_at`),
  KEY `delegation_signs_status_index` (`status`),
  KEY `delegation_signs_dealer_name_index` (`dealer_name`),
  KEY `delegation_signs_next_repurchase_at_index` (`next_repurchase_at`),
  KEY `delegation_signs_source_index` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `error_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `error_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fingerprint` varchar(64) NOT NULL,
  `source` varchar(10) NOT NULL DEFAULT 'server',
  `level` varchar(20) NOT NULL DEFAULT 'error',
  `kind` varchar(80) NOT NULL,
  `exception` varchar(200) DEFAULT NULL,
  `http_status` smallint(5) unsigned DEFAULT NULL,
  `message` text DEFAULT NULL,
  `file` varchar(300) DEFAULT NULL,
  `line` int(10) unsigned DEFAULT NULL,
  `col` int(10) unsigned DEFAULT NULL,
  `trace` longtext DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `http_method` varchar(10) DEFAULT NULL,
  `route_name` varchar(120) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(60) DEFAULT NULL,
  `input` longtext DEFAULT NULL,
  `hit` int(10) unsigned NOT NULL DEFAULT 1,
  `first_at` timestamp NULL DEFAULT NULL,
  `last_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `checked_by` bigint(20) unsigned DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  `memo` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `error_logs_user_id_foreign` (`user_id`),
  KEY `error_logs_checked_by_foreign` (`checked_by`),
  KEY `error_logs_kind_last_at_index` (`kind`,`last_at`),
  KEY `error_logs_status_last_at_index` (`status`,`last_at`),
  KEY `error_logs_fingerprint_index` (`fingerprint`),
  KEY `error_logs_level_index` (`level`),
  KEY `error_logs_kind_index` (`kind`),
  KEY `error_logs_http_status_index` (`http_status`),
  KEY `error_logs_last_at_index` (`last_at`),
  KEY `error_logs_status_index` (`status`),
  KEY `error_logs_source_index` (`source`),
  CONSTRAINT `error_logs_checked_by_foreign` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `error_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fax_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fax_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) unsigned DEFAULT NULL,
  `corp_num` varchar(20) NOT NULL COMMENT '사업자번호',
  `receipt_num` varchar(50) NOT NULL COMMENT '팝빌 접수번호',
  `sender` varchar(30) NOT NULL COMMENT '발신번호',
  `sender_name` varchar(100) DEFAULT NULL COMMENT '발신자명',
  `title` varchar(200) DEFAULT NULL COMMENT '팩스 제목',
  `receivers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '수신자 목록 [{rcv,rcvnm}]' CHECK (json_valid(`receivers`)),
  `fax_no` varchar(20) DEFAULT NULL COMMENT '수신 팩스번호',
  `recipient_type` varchar(20) DEFAULT NULL COMMENT '수신처 유형 nhis|hira|custom',
  `documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '전송 서류 목록' CHECK (json_valid(`documents`)),
  `attachment_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '팩스에 포함된 처방전 첨부 문서 ID 목록' CHECK (json_valid(`attachment_ids`)),
  `pdf_path` varchar(500) DEFAULT NULL COMMENT '저장된 합본 PDF 경로 (storage/app/public 기준)',
  `file_names` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '첨부 파일명 목록' CHECK (json_valid(`file_names`)),
  `reserve_dt` varchar(14) DEFAULT NULL COMMENT '예약일시 YYYYMMDDHHmmss',
  `request_num` varchar(50) DEFAULT NULL COMMENT '임의 접수번호',
  `popbill_state` tinyint(4) NOT NULL DEFAULT 0 COMMENT '팝빌 전송상태 0=대기 1=전송중 2=성공 3=실패 4=취소',
  `popbill_result` int(11) DEFAULT NULL COMMENT '팝빌 결과코드',
  `synced_at` timestamp NULL DEFAULT NULL COMMENT '마지막 팝빌 동기화 시각',
  `sent_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fax_histories_receipt_num_unique` (`receipt_num`),
  KEY `fax_histories_sent_by_foreign` (`sent_by`),
  KEY `fax_histories_prescription_id_foreign` (`prescription_id`),
  CONSTRAINT `fax_histories_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fax_histories_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fcm_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fcm_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `type` varchar(40) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `sent` tinyint(1) NOT NULL DEFAULT 1,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fcm_notifications_user_id_id_index` (`user_id`,`id`),
  KEY `fcm_notifications_type_index` (`type`),
  CONSTRAINT `fcm_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hospitals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hospitals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `tel` varchar(30) DEFAULT NULL,
  `fax` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `department` varchar(60) DEFAULT NULL,
  `memo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hospitals_created_by_foreign` (`created_by`),
  KEY `hospitals_name_index` (`name`),
  KEY `hospitals_code_index` (`code`),
  CONSTRAINT `hospitals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inquiries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'general',
  `reply_channel` varchar(10) DEFAULT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `answer` text DEFAULT NULL,
  `action_note` text DEFAULT NULL,
  `answered_by` bigint(20) unsigned DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inquiries_user_id_foreign` (`user_id`),
  KEY `inquiries_answered_by_foreign` (`answered_by`),
  KEY `inquiries_patient_id_foreign` (`patient_id`),
  CONSTRAINT `inquiries_answered_by_foreign` FOREIGN KEY (`answered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inquiries_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inquiries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inquiry_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inquiry_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inquiry_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `body` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_size` bigint(20) unsigned DEFAULT NULL,
  `is_image` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inquiry_messages_inquiry_id_foreign` (`inquiry_id`),
  KEY `inquiry_messages_user_id_foreign` (`user_id`),
  CONSTRAINT `inquiry_messages_inquiry_id_foreign` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inquiry_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `local_claim_dispatches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `local_claim_dispatches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `local_gov` varchar(60) DEFAULT NULL,
  `registered_no` varchar(50) DEFAULT NULL,
  `sent_date` date DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `receipt_name` varchar(190) DEFAULT NULL,
  `memo` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `local_claim_dispatches_created_by_foreign` (`created_by`),
  KEY `local_claim_dispatches_order_id_sent_date_index` (`order_id`,`sent_date`),
  CONSTRAINT `local_claim_dispatches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `local_claim_dispatches_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_otp_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_otp_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `code` varchar(6) NOT NULL,
  `pending_token` varchar(64) DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `login_otp_tokens_pending_token_unique` (`pending_token`),
  KEY `login_otp_tokens_user_id_expires_at_index` (`user_id`,`expires_at`),
  KEY `login_otp_tokens_pending_token_index` (`pending_token`),
  CONSTRAINT `login_otp_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `master_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `master_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(30) NOT NULL COMMENT 'hospital | dealer',
  `code` varchar(60) DEFAULT NULL COMMENT '요양기관번호 · 거래처코드',
  `name` varchar(150) NOT NULL,
  `biz_no` varchar(40) DEFAULT NULL,
  `ceo` varchar(60) DEFAULT NULL,
  `manager` varchar(60) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `fax` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` varchar(300) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `master_items_created_by_foreign` (`created_by`),
  KEY `master_items_category_is_active_index` (`category`,`is_active`),
  KEY `master_items_category_code_index` (`category`,`code`),
  KEY `master_items_name_index` (`name`),
  CONSTRAINT `master_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel` enum('sms','alimtalk') NOT NULL,
  `template_code` varchar(60) DEFAULT NULL,
  `template_label` varchar(100) DEFAULT NULL COMMENT '당시 이름. 유형이 바뀌어도 이력은 그대로 읽힌다',
  `content` text DEFAULT NULL COMMENT '실제로 나간 본문',
  `total` int(10) unsigned NOT NULL DEFAULT 0,
  `success_count` int(10) unsigned NOT NULL DEFAULT 0,
  `fail_count` int(10) unsigned NOT NULL DEFAULT 0,
  `receivers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '수신자 [{rcv,rcvnm,patient_id}]' CHECK (json_valid(`receivers`)),
  `receipt_nums` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '업체 접수번호 목록 (묶음마다 하나)' CHECK (json_valid(`receipt_nums`)),
  `error` text DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'messages' COMMENT '어느 화면에서 보냈나',
  `prescription_id` bigint(20) unsigned DEFAULT NULL,
  `sent_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `message_histories_prescription_id_foreign` (`prescription_id`),
  KEY `message_histories_sent_by_foreign` (`sent_by`),
  KEY `message_histories_channel_created_at_index` (`channel`,`created_at`),
  CONSTRAINT `message_histories_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `message_histories_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel` enum('sms','alimtalk') NOT NULL COMMENT 'sms=문자, alimtalk=카카오 알림톡',
  `code` varchar(60) NOT NULL COMMENT '유형 코드',
  `ats_template_code` varchar(60) DEFAULT NULL,
  `label` varchar(100) NOT NULL COMMENT '화면에 보이는 이름',
  `description` varchar(200) DEFAULT NULL COMMENT '언제 쓰는지',
  `body` text DEFAULT NULL COMMENT '본문. #{고객명} 같은 자리표시자를 쓴다',
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_templates_channel_code_unique` (`channel`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nhis_fax_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nhis_fax_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `sent_by` bigint(20) unsigned DEFAULT NULL,
  `fax_number` varchar(30) NOT NULL COMMENT '수신 팩스번호(공단)',
  `sender_number` varchar(30) DEFAULT NULL COMMENT '발신 팩스번호',
  `document_title` varchar(200) DEFAULT NULL COMMENT '청구서 제목',
  `claim_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '청구 금액',
  `nhis_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '건보 부담금',
  `patient_copay` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '환자 부담금',
  `status` enum('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
  `reference_no` varchar(100) DEFAULT NULL COMMENT '팩스 전송 참조번호',
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `nhis_result` enum('pending','approved','rejected','partial') NOT NULL DEFAULT 'pending',
  `approved_amount` decimal(10,2) DEFAULT NULL COMMENT '승인 금액',
  `nhis_message` text DEFAULT NULL COMMENT '공단 회신 메시지',
  `nhis_result_at` timestamp NULL DEFAULT NULL,
  `raw_payload` text DEFAULT NULL COMMENT 'API 요청/응답 원문(JSON)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nhis_fax_logs_order_id_index` (`order_id`),
  KEY `nhis_fax_logs_status_index` (`status`),
  KEY `nhis_fax_logs_sent_by_foreign` (`sent_by`),
  CONSTRAINT `nhis_fax_logs_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nhis_fax_logs_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nice_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nice_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` varchar(190) NOT NULL DEFAULT '',
  `client_secret` text DEFAULT NULL,
  `product_id` varchar(100) NOT NULL DEFAULT '',
  `enforce` tinyint(1) NOT NULL DEFAULT 1,
  `match_name` tinyint(1) NOT NULL DEFAULT 1,
  `match_birth` tinyint(1) NOT NULL DEFAULT 1,
  `api_base` varchar(190) NOT NULL DEFAULT '',
  `standard_url` varchar(190) NOT NULL DEFAULT '',
  `tested_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notice_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notice_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notice_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `notice_reads_notice_id_user_id_unique` (`notice_id`,`user_id`),
  KEY `notice_reads_user_id_foreign` (`user_id`),
  CONSTRAINT `notice_reads_notice_id_foreign` FOREIGN KEY (`notice_id`) REFERENCES `notices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notice_reads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `views` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notices_created_by_foreign` (`created_by`),
  CONSTRAINT `notices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ocr_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocr_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(20) NOT NULL DEFAULT 'textract',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_item_lots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_item_lots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint(20) unsigned NOT NULL,
  `lot_no` varchar(100) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_item_lots_unique` (`order_item_id`,`lot_no`),
  CONSTRAINT `order_item_lots_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `product_price` decimal(12,2) DEFAULT NULL,
  `insurance_price` decimal(12,2) DEFAULT NULL,
  `nhis_amount` decimal(12,2) DEFAULT NULL,
  `patient_copay` decimal(12,2) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_sort_order_index` (`order_id`,`sort_order`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_return_id` bigint(20) unsigned NOT NULL,
  `order_item_id` bigint(20) unsigned DEFAULT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `product_name` varchar(200) DEFAULT NULL,
  `lot_no` varchar(200) DEFAULT NULL,
  `ordered_quantity` int(11) NOT NULL DEFAULT 0,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit_price` int(11) NOT NULL DEFAULT 0,
  `copay` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_return_items_order_return_id_index` (`order_return_id`),
  CONSTRAINT `order_return_items_order_return_id_foreign` FOREIGN KEY (`order_return_id`) REFERENCES `order_returns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_return_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_return_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_return_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_return_logs_order_return_id_foreign` (`order_return_id`),
  KEY `order_return_logs_created_by_foreign` (`created_by`),
  CONSTRAINT `order_return_logs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_return_logs_order_return_id_foreign` FOREIGN KEY (`order_return_id`) REFERENCES `order_returns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(30) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `type` varchar(20) NOT NULL,
  `subtype` varchar(20) DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `reason_code` varchar(30) NOT NULL,
  `reason_text` varchar(500) DEFAULT NULL,
  `shipping_burden` varchar(20) DEFAULT NULL,
  `collect_method` varchar(20) DEFAULT NULL,
  `collect_tracking_no` varchar(50) DEFAULT NULL,
  `arrived_at` timestamp NULL DEFAULT NULL,
  `inspect_confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `inspect_confirmed_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `payment_checked_at` timestamp NULL DEFAULT NULL,
  `order_confirmed_at` timestamp NULL DEFAULT NULL,
  `exchange_product` varchar(200) DEFAULT NULL,
  `exchange_quantity` int(11) DEFAULT NULL,
  `reship_address` varchar(300) DEFAULT NULL,
  `refund_method` varchar(20) DEFAULT NULL,
  `refund_bank` varchar(50) DEFAULT NULL,
  `refund_account` varchar(50) DEFAULT NULL,
  `refund_holder` varchar(50) DEFAULT NULL,
  `card_issuer` varchar(50) DEFAULT NULL,
  `card_expiry` varchar(7) DEFAULT NULL,
  `refund_approval_no` varchar(50) DEFAULT NULL,
  `card_cancelled_at` timestamp NULL DEFAULT NULL,
  `bank_cancelled_at` timestamp NULL DEFAULT NULL,
  `handling_branch` varchar(100) DEFAULT NULL,
  `refund_agency` varchar(200) DEFAULT NULL,
  `refund_cash_receipt_no` varchar(50) DEFAULT NULL,
  `refund_cash_receipt_type` varchar(20) DEFAULT NULL,
  `memo` varchar(500) DEFAULT NULL,
  `staff_memo` varchar(500) DEFAULT NULL,
  `refund_amount` int(11) DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `credit_issued_at` timestamp NULL DEFAULT NULL,
  `credit_note` varchar(500) DEFAULT NULL,
  `is_partial` tinyint(1) NOT NULL DEFAULT 0,
  `withworks_so_no` varchar(50) DEFAULT NULL,
  `withworks_so_id` bigint(20) unsigned DEFAULT NULL,
  `withworks_so_type` varchar(10) DEFAULT NULL,
  `withworks_status` varchar(50) DEFAULT NULL,
  `withworks_status_label` varchar(100) DEFAULT NULL,
  `withworks_sent_at` timestamp NULL DEFAULT NULL,
  `withworks_error` varchar(500) DEFAULT NULL,
  `pl3_status` varchar(30) DEFAULT NULL,
  `pl3_status_label` varchar(50) DEFAULT NULL,
  `pl3_status_at` timestamp NULL DEFAULT NULL,
  `pl3_note` text DEFAULT NULL,
  `pl3_note_at` timestamp NULL DEFAULT NULL,
  `adjust_so_no` varchar(50) DEFAULT NULL,
  `adjust_amount` int(11) DEFAULT NULL,
  `adjust_direction` varchar(10) DEFAULT NULL,
  `adjusted_at` timestamp NULL DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_returns_receipt_no_unique` (`receipt_no`),
  KEY `order_returns_assigned_user_id_foreign` (`assigned_user_id`),
  KEY `order_returns_created_by_foreign` (`created_by`),
  KEY `order_returns_type_status_index` (`type`,`status`),
  KEY `order_returns_order_id_index` (`order_id`),
  KEY `order_returns_withworks_so_no_index` (`withworks_so_no`),
  KEY `order_returns_inspect_confirmed_by_foreign` (`inspect_confirmed_by`),
  KEY `order_returns_approved_by_foreign` (`approved_by`),
  CONSTRAINT `order_returns_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_returns_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_returns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_returns_inspect_confirmed_by_foreign` FOREIGN KEY (`inspect_confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_returns_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) NOT NULL COMMENT '주문번호 ORD-NNNN',
  `prescription_id` bigint(20) unsigned NOT NULL,
  `parent_order_id` bigint(20) unsigned DEFAULT NULL,
  `order_kind` varchar(10) NOT NULL DEFAULT 'origin',
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `operation_user_id` bigint(20) unsigned DEFAULT NULL,
  `closing_checked_at` timestamp NULL DEFAULT NULL,
  `closing_checked_by` bigint(20) unsigned DEFAULT NULL,
  `reference_note` varchar(500) DEFAULT NULL,
  `settle_status` enum('open','closed','confirmed','rejected','on_hold','cancelled') NOT NULL DEFAULT 'open',
  `settle_status_at` timestamp NULL DEFAULT NULL,
  `settle_status_by` bigint(20) unsigned DEFAULT NULL,
  `settle_reason` varchar(300) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_code` varchar(255) DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `nhis_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `patient_copay` decimal(10,2) NOT NULL,
  `deposit_confirmed_at` timestamp NULL DEFAULT NULL,
  `deposit_confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `deposit_amount` bigint(20) unsigned DEFAULT NULL,
  `deposit_note` varchar(200) DEFAULT NULL,
  `pay_method` varchar(20) DEFAULT NULL,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 3000.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','allocated','picked','invoiced','shipping','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `cancel_state` varchar(20) DEFAULT NULL,
  `cancel_requested_at` timestamp NULL DEFAULT NULL,
  `cancel_requested_by` bigint(20) unsigned DEFAULT NULL,
  `cancel_reason` varchar(200) DEFAULT NULL,
  `cancel_done_at` timestamp NULL DEFAULT NULL,
  `amend_state` varchar(20) DEFAULT NULL,
  `amend_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amend_payload`)),
  `amend_requested_at` timestamp NULL DEFAULT NULL,
  `amend_requested_by` bigint(20) unsigned DEFAULT NULL,
  `amend_note` varchar(300) DEFAULT NULL,
  `withworks_so_no_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`withworks_so_no_history`)),
  `so_type` varchar(10) DEFAULT NULL COMMENT 'Withworks 판매 유형 코드 (1013=CE판매, 1016=개인판매, 1022=샘플판매)',
  `withworks_so_no` varchar(50) DEFAULT NULL COMMENT 'Withworks SO 번호',
  `withworks_so_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Withworks SO PK',
  `withworks_status` varchar(50) DEFAULT NULL,
  `withworks_status_label` varchar(50) DEFAULT NULL,
  `withworks_status_at` timestamp NULL DEFAULT NULL,
  `withworks_warehouse` varchar(100) DEFAULT NULL COMMENT '출고창고 이름 — 위드웍스 웹훅이 알려 준다',
  `withworks_deliver_warehouse` varchar(100) DEFAULT NULL COMMENT '납품창고 이름 — 위드웍스 웹훅이 알려 준다',
  `withworks_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '위드웍스가 웹훅으로 알려 준 판매현황 값 — 우리가 만들지 않는 것들' CHECK (json_valid(`withworks_meta`)),
  `withworks_ship_no` varchar(50) DEFAULT NULL,
  `withworks_ship_status` varchar(50) DEFAULT NULL,
  `withworks_ship_status_label` varchar(100) DEFAULT NULL,
  `withworks_tracking_no` varchar(100) DEFAULT NULL,
  `withworks_ship_at` timestamp NULL DEFAULT NULL,
  `statement_date` date DEFAULT NULL,
  `shipped_at` date DEFAULT NULL,
  `shipping_address` varchar(255) DEFAULT NULL,
  `shipping_address_detail` varchar(200) DEFAULT NULL,
  `shipping_recipient` varchar(100) DEFAULT NULL COMMENT '배송지 받는 사람',
  `shipping_postcode` varchar(10) DEFAULT NULL,
  `tracking_number` varchar(255) DEFAULT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `nhis_claim_status` enum('pending','submitting','submitted','approved','rejected','on_hold','cancelled') NOT NULL DEFAULT 'pending',
  `nhis_submitted_at` timestamp NULL DEFAULT NULL,
  `nhis_approved_at` timestamp NULL DEFAULT NULL,
  `nhis_reimbursement` decimal(10,2) DEFAULT NULL,
  `latest_fax_log_id` bigint(20) unsigned DEFAULT NULL,
  `nhis_rejection_reason` varchar(500) DEFAULT NULL,
  `nhis_reject_stage` varchar(20) DEFAULT NULL,
  `claim_ready` tinyint(1) NOT NULL DEFAULT 0,
  `claim_missing` varchar(255) DEFAULT NULL,
  `claim_checked_at` timestamp NULL DEFAULT NULL,
  `note` text DEFAULT NULL,
  `warehouse_note` varchar(500) DEFAULT NULL,
  `ship_request_date` date DEFAULT NULL,
  `tax_invoice_status` enum('not_issued','issued','cancelled') NOT NULL DEFAULT 'not_issued',
  `tax_invoice_no` varchar(50) DEFAULT NULL,
  `tax_invoice_mgt_key` varchar(24) DEFAULT NULL,
  `tax_invoice_type` enum('electronic','manual') NOT NULL DEFAULT 'electronic',
  `tax_invoice_purpose` varchar(10) DEFAULT NULL,
  `tax_invoice_biz_name` varchar(100) DEFAULT NULL,
  `tax_invoice_ceo_name` varchar(50) DEFAULT NULL,
  `tax_invoice_biz_no` varchar(20) DEFAULT NULL,
  `tax_invoice_email` varchar(100) DEFAULT NULL,
  `tax_invoice_supply` decimal(10,2) DEFAULT NULL COMMENT '공급가액',
  `tax_invoice_vat` decimal(10,2) DEFAULT NULL COMMENT '부가세',
  `tax_invoice_issued_at` timestamp NULL DEFAULT NULL,
  `tax_invoice_cancelled_at` timestamp NULL DEFAULT NULL,
  `cash_receipt_status` enum('not_issued','issued','cancelled') NOT NULL DEFAULT 'not_issued',
  `cash_receipt_no` varchar(50) DEFAULT NULL,
  `cash_receipt_type` enum('income_deduction','business_expense') NOT NULL DEFAULT 'income_deduction' COMMENT '소득공제/지출증빙',
  `cash_receipt_identifier` varchar(30) DEFAULT NULL COMMENT '휴대폰번호 또는 사업자번호',
  `cash_receipt_amount` decimal(10,2) DEFAULT NULL,
  `cash_receipt_issued_at` timestamp NULL DEFAULT NULL,
  `cash_receipt_cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_prescription_id_foreign` (`prescription_id`),
  KEY `orders_patient_id_foreign` (`patient_id`),
  KEY `orders_created_by_foreign` (`created_by`),
  KEY `orders_claim_ready_idx` (`claim_ready`),
  KEY `orders_deposit_confirmed_at_index` (`deposit_confirmed_at`),
  KEY `orders_operation_user_id_foreign` (`operation_user_id`),
  KEY `orders_closing_checked_by_foreign` (`closing_checked_by`),
  KEY `orders_settle_status_by_foreign` (`settle_status_by`),
  KEY `orders_parent_order_id_index` (`parent_order_id`),
  KEY `orders_order_kind_index` (`order_kind`),
  KEY `orders_cancel_state_index` (`cancel_state`),
  KEY `orders_amend_state_index` (`amend_state`),
  CONSTRAINT `orders_closing_checked_by_foreign` FOREIGN KEY (`closing_checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_operation_user_id_foreign` FOREIGN KEY (`operation_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_settle_status_by_foreign` FOREIGN KEY (`settle_status_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `patient_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint(20) unsigned NOT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `address` varchar(300) DEFAULT NULL,
  `address_detail` varchar(200) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_addresses_created_by_foreign` (`created_by`),
  KEY `patient_addresses_patient_id_id_index` (`patient_id`,`id`),
  CONSTRAINT `patient_addresses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patient_addresses_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '환자명',
  `care_type` char(2) DEFAULT NULL,
  `resident_no_enc` varbinary(512) DEFAULT NULL,
  `resident_no_hash` char(64) DEFAULT NULL COMMENT 'HMAC-SHA256(정규화 RRN, pepper) 조회용',
  `resident_no_masked` varchar(20) DEFAULT NULL COMMENT '표시용 900101-1******',
  `birth_date` date DEFAULT NULL COMMENT '생년월일',
  `gender` varchar(10) DEFAULT NULL COMMENT '성별',
  `mobile` varchar(30) DEFAULT NULL COMMENT '휴대폰번호',
  `phone` varchar(30) DEFAULT NULL COMMENT '일반 전화',
  `main_contact` varchar(10) DEFAULT NULL,
  `marketing_consent` varchar(10) DEFAULT NULL,
  `marketing_consent_by` bigint(20) unsigned DEFAULT NULL,
  `marketing_consent_at` timestamp NULL DEFAULT NULL,
  `address` varchar(300) DEFAULT NULL COMMENT '주소',
  `postcode` varchar(10) DEFAULT NULL,
  `address_detail` varchar(200) DEFAULT NULL,
  `health_insurance_no` varchar(20) DEFAULT NULL COMMENT '건강보험 번호',
  `note` text DEFAULT NULL COMMENT '메모',
  `is_nhis_eligible` tinyint(1) NOT NULL DEFAULT 0 COMMENT '건보 대상 여부',
  `nhis_coverage_rate` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '건보 지원 비율',
  `memo` text DEFAULT NULL COMMENT '메모',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `rrn_purpose` varchar(40) DEFAULT NULL COMMENT '처리근거: nhis_claim_form',
  `rrn_retention_basis_at` date DEFAULT NULL COMMENT '기산점(최종 주문·청구일)',
  `rrn_retention_until` date DEFAULT NULL COMMENT '폐기 예정일 = 기산점 + RoPA 기재 연수',
  `rrn_destroyed_at` timestamp NULL DEFAULT NULL COMMENT '폐기 배치가 실제로 지운 시각',
  `email` varchar(190) DEFAULT NULL,
  `fax` varchar(30) DEFAULT NULL,
  `managed_customer` tinyint(3) unsigned DEFAULT NULL,
  `sb_sci` varchar(50) DEFAULT NULL,
  `nhis_reg_status` varchar(20) DEFAULT NULL,
  `nhis_reg_date` date DEFAULT NULL,
  `nhis_renew` varchar(100) DEFAULT NULL,
  `nhis_renew_due` date DEFAULT NULL,
  `nhis_renew_told_at` timestamp NULL DEFAULT NULL,
  `nhis_agree_start` date DEFAULT NULL,
  `nhis_agree_end` date DEFAULT NULL,
  `basic_reeval` varchar(100) DEFAULT NULL,
  `basic_reeval_due` date DEFAULT NULL,
  `cash_receipt_no` varchar(50) DEFAULT NULL,
  `deduction` varchar(20) DEFAULT NULL,
  `new_patient_date` date DEFAULT NULL,
  `guardian_name` varchar(50) DEFAULT NULL,
  `guardian_relation` varchar(50) DEFAULT NULL,
  `guardian_birth_date` date DEFAULT NULL,
  `guardian_phone` varchar(40) DEFAULT NULL,
  `remitter_name` varchar(50) DEFAULT NULL,
  `pay_method` varchar(20) DEFAULT NULL,
  `va_bank` varchar(40) DEFAULT NULL,
  `va_account` varchar(40) DEFAULT NULL,
  `va_holder` varchar(60) DEFAULT NULL,
  `va_due_at` datetime DEFAULT NULL,
  `va_order_id` bigint(20) unsigned DEFAULT NULL,
  `contact_channel` varchar(20) DEFAULT NULL,
  `contact_status` varchar(20) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patients_resident_no_hash_index` (`resident_no_hash`),
  KEY `patients_nhis_agree_end_idx` (`nhis_agree_end`),
  KEY `patients_new_patient_date_idx` (`new_patient_date`),
  KEY `patients_care_type_index` (`care_type`),
  KEY `patients_created_by_foreign` (`created_by`),
  KEY `patients_updated_by_foreign` (`updated_by`),
  CONSTRAINT `patients_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patients_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `method` varchar(20) NOT NULL,
  `amount` int(10) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'sent',
  `channel` varchar(20) DEFAULT NULL,
  `receiver` varchar(20) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `payment_key` varchar(200) DEFAULT NULL,
  `toss_order_id` varchar(64) DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_links_token_unique` (`token`),
  KEY `payment_links_created_by_foreign` (`created_by`),
  KEY `payment_links_order_id_created_at_index` (`order_id`,`created_at`),
  KEY `payment_links_status_index` (`status`),
  CONSTRAINT `payment_links_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_links_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_group_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_group_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `permission_group_id` bigint(20) unsigned NOT NULL,
  `page_key` varchar(60) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 0,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_update` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `can_send` tinyint(1) NOT NULL DEFAULT 0,
  `can_approve` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_group_pages_permission_group_id_page_key_unique` (`permission_group_id`,`page_key`),
  CONSTRAINT `permission_group_pages_permission_group_id_foreign` FOREIGN KEY (`permission_group_id`) REFERENCES `permission_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `is_full_access` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_groups_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `popbill_taxinvoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `popbill_taxinvoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `corp_num` varchar(20) NOT NULL,
  `mgt_key_type` varchar(10) NOT NULL DEFAULT 'SELL',
  `mgt_key` varchar(100) NOT NULL,
  `item_key` varchar(50) DEFAULT NULL,
  `state_code` int(11) NOT NULL DEFAULT 0,
  `state_dt` varchar(20) DEFAULT NULL,
  `tax_type` varchar(20) DEFAULT NULL,
  `purpose_type` varchar(20) DEFAULT NULL,
  `issue_type` varchar(20) DEFAULT NULL,
  `write_date` varchar(8) DEFAULT NULL,
  `issue_dt` varchar(20) DEFAULT NULL,
  `invoicer_corp_num` varchar(20) DEFAULT NULL,
  `invoicer_corp_name` varchar(100) DEFAULT NULL,
  `invoicer_ceo_name` varchar(50) DEFAULT NULL,
  `invoicee_corp_num` varchar(20) DEFAULT NULL,
  `invoicee_corp_name` varchar(100) DEFAULT NULL,
  `invoicee_ceo_name` varchar(50) DEFAULT NULL,
  `supply_cost_total` bigint(20) NOT NULL DEFAULT 0,
  `tax_total` bigint(20) NOT NULL DEFAULT 0,
  `total_amount` bigint(20) NOT NULL DEFAULT 0,
  `nts_confirm_num` varchar(50) DEFAULT NULL,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `popbill_taxinvoices_corp_num_mgt_key_type_mgt_key_unique` (`corp_num`,`mgt_key_type`,`mgt_key`),
  KEY `popbill_taxinvoices_corp_num_mgt_key_type_write_date_index` (`corp_num`,`mgt_key_type`,`write_date`),
  KEY `popbill_taxinvoices_is_final_synced_at_index` (`is_final`,`synced_at`),
  KEY `popbill_taxinvoices_order_id_foreign` (`order_id`),
  CONSTRAINT `popbill_taxinvoices_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescription_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `overlay_source_path` varchar(255) DEFAULT NULL,
  `overlay_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`overlay_fields`)),
  `img_brightness` smallint(6) NOT NULL DEFAULT 0,
  `img_contrast` smallint(6) NOT NULL DEFAULT 0,
  `file_original_name` varchar(255) DEFAULT NULL,
  `file_mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `doc_type` varchar(60) NOT NULL DEFAULT 'other',
  `doc_label` varchar(50) DEFAULT NULL,
  `ocr_raw_text` text DEFAULT NULL,
  `ocr_confidence` tinyint(4) NOT NULL DEFAULT 0,
  `display_order` tinyint(4) NOT NULL DEFAULT 0,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_attachments_prescription_id_foreign` (`prescription_id`),
  KEY `prescription_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `prescription_attachments_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescription_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `kind` varchar(20) NOT NULL DEFAULT 'delegation',
  `patient_name` varchar(100) NOT NULL,
  `patient_mobile` varchar(20) NOT NULL,
  `is_minor` tinyint(1) NOT NULL DEFAULT 0 COMMENT '서명 요청 시점의 만 나이로 판정',
  `patient_birth_date` date DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_relation` varchar(50) DEFAULT NULL,
  `guardian_birth_date` date DEFAULT NULL,
  `guardian_phone` varchar(40) DEFAULT NULL,
  `guardian_signature_data` longtext DEFAULT NULL,
  `guardian_id_path` varchar(255) DEFAULT NULL,
  `guardian_id_mime` varchar(60) DEFAULT NULL,
  `patient_id_path` varchar(255) DEFAULT NULL,
  `patient_id_mime` varchar(100) DEFAULT NULL,
  `signature_data` longtext DEFAULT NULL,
  `final_agreements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`final_agreements`)),
  `nice_verified_at` timestamp NULL DEFAULT NULL,
  `nice_name` varchar(100) DEFAULT NULL,
  `nice_birthdate` varchar(8) DEFAULT NULL,
  `nice_gender` varchar(4) DEFAULT NULL,
  `nice_nation` varchar(4) DEFAULT NULL,
  `nice_mobileco` varchar(8) DEFAULT NULL,
  `nice_mobile` varchar(20) DEFAULT NULL,
  `nice_authtype` varchar(8) DEFAULT NULL,
  `nice_response_no` varchar(64) DEFAULT NULL,
  `nice_ci` text DEFAULT NULL,
  `nice_di` text DEFAULT NULL,
  `status` enum('pending','agreed','declined','expired') NOT NULL DEFAULT 'pending',
  `sent_by` bigint(20) unsigned DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_consents_token_unique` (`token`),
  KEY `prescription_consents_token_expires_at_index` (`token`,`expires_at`),
  KEY `prescription_consents_prescription_id_status_index` (`prescription_id`,`status`),
  CONSTRAINT `prescription_consents_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescription_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) unsigned NOT NULL,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_documents_created_by_foreign` (`created_by`),
  KEY `prescription_documents_prescription_id_type_index` (`prescription_id`,`type`),
  KEY `prescription_documents_patient_id_index` (`patient_id`),
  KEY `prescription_documents_created_at_index` (`created_at`),
  CONSTRAINT `prescription_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescription_documents_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescription_documents_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) unsigned NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_code` varchar(255) DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `product_price` decimal(10,2) DEFAULT NULL,
  `insurance_price` decimal(10,2) DEFAULT NULL,
  `nhis_status` enum('eligible','ineligible','partial') DEFAULT NULL,
  `nhis_amount` decimal(10,2) DEFAULT NULL,
  `patient_copay` decimal(10,2) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_items_prescription_id_sort_order_index` (`prescription_id`,`sort_order`),
  CONSTRAINT `prescription_items_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescription_memos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_memos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `content` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `pin_x` double DEFAULT NULL,
  `pin_y` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_memos_prescription_id_foreign` (`prescription_id`),
  KEY `prescription_memos_user_id_foreign` (`user_id`),
  CONSTRAINT `prescription_memos_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_memos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescription_reupload_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_reupload_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) unsigned NOT NULL,
  `attachment_id` bigint(20) unsigned DEFAULT NULL,
  `doc_label` varchar(60) DEFAULT NULL,
  `reason` varchar(20) NOT NULL,
  `memo` varchar(500) DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `requested_by_name` varchar(50) DEFAULT NULL,
  `requested_at` timestamp NOT NULL,
  `target_user_id` bigint(20) unsigned DEFAULT NULL,
  `target_user_name` varchar(50) DEFAULT NULL,
  `fcm_sent` tinyint(1) NOT NULL DEFAULT 0,
  `fcm_error` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_attachment_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_reupload_requests_prescription_id_resolved_at_index` (`prescription_id`,`resolved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rx_number` varchar(30) NOT NULL COMMENT '처방번호 RX-YYYYMMDD-NNN',
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_original_name` varchar(255) DEFAULT NULL,
  `image_mime_type` varchar(50) DEFAULT NULL,
  `img_brightness` smallint(6) NOT NULL DEFAULT 0,
  `img_contrast` smallint(6) NOT NULL DEFAULT 0,
  `image_size` int(10) unsigned DEFAULT NULL,
  `upload_source` enum('mobile','web') NOT NULL DEFAULT 'web',
  `registration_no` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `is_reissue` tinyint(1) NOT NULL DEFAULT 0,
  `patient_name_ocr` varchar(255) DEFAULT NULL,
  `resident_no_ocr_enc` varbinary(512) DEFAULT NULL,
  `resident_no_ocr_masked` varchar(20) DEFAULT NULL,
  `mobile_ocr` varchar(30) DEFAULT NULL COMMENT 'OCR 추출 휴대전화',
  `address_ocr` varchar(300) DEFAULT NULL COMMENT 'OCR 추출 주소',
  `postcode` varchar(10) DEFAULT NULL,
  `address_detail` varchar(200) DEFAULT NULL,
  `hospital_name` varchar(255) DEFAULT NULL,
  `hospital_code` varchar(255) DEFAULT NULL,
  `doctor_name` varchar(255) DEFAULT NULL,
  `specialty` varchar(255) DEFAULT NULL,
  `license_no` varchar(255) DEFAULT NULL,
  `specialist_no` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `disease_name` text DEFAULT NULL,
  `disease_code` varchar(200) DEFAULT NULL,
  `daily_count` smallint(5) unsigned DEFAULT NULL,
  `total_days` smallint(5) unsigned DEFAULT NULL,
  `total_count` int(10) unsigned DEFAULT NULL,
  `usage_period` varchar(255) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `repurchase_date` date DEFAULT NULL,
  `ocr_raw_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ocr_raw_data`)),
  `ocr_confidence` decimal(5,2) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_code` varchar(255) DEFAULT NULL,
  `quantity` int(10) unsigned DEFAULT NULL,
  `nhis_status` enum('eligible','ineligible','partial') DEFAULT NULL,
  `product_price` decimal(10,2) DEFAULT NULL,
  `insurance_price` decimal(10,2) DEFAULT NULL COMMENT '보험가 (NHIS 급여 계산 기준가)',
  `nhis_amount` decimal(10,2) DEFAULT NULL,
  `patient_copay` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','ocr_processing','ocr_done','review_needed','review_requested','review_hold','review_resent','approved','rejected','ordered') NOT NULL DEFAULT 'pending',
  `is_blank_draft` tinyint(1) NOT NULL DEFAULT 0 COMMENT '신규 등록 직후의 빈 초안 — 저장되면 해제되고 목록에 나타난다',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_memo` text DEFAULT NULL,
  `reference_note` text DEFAULT NULL,
  `review_request_memo` text DEFAULT NULL,
  `input_review_status` varchar(20) DEFAULT NULL,
  `input_review_requested_at` timestamp NULL DEFAULT NULL,
  `input_review_requested_by` bigint(20) unsigned DEFAULT NULL,
  `input_review_request_memo` varchar(500) DEFAULT NULL,
  `input_review_approved_at` timestamp NULL DEFAULT NULL,
  `input_review_approved_by` bigint(20) unsigned DEFAULT NULL,
  `input_review_memo` varchar(500) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `counseling_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`counseling_data`)),
  `kakao_sent_at` timestamp NULL DEFAULT NULL,
  `sms_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `benefit_class` varchar(20) DEFAULT NULL,
  `billing_strategy` varchar(40) DEFAULT NULL,
  `claim_agency` varchar(20) DEFAULT NULL,
  `billing_office_id` bigint(20) unsigned DEFAULT NULL,
  `local_gov` varchar(60) DEFAULT NULL,
  `disease_class` varchar(100) DEFAULT NULL,
  `disease_grade` varchar(10) DEFAULT NULL,
  `uro_date` date DEFAULT NULL,
  `uro_findings` varchar(200) DEFAULT NULL,
  `diagnosis_date` date DEFAULT NULL,
  `rx_use_period` int(11) DEFAULT NULL,
  `rx_end_date` date DEFAULT NULL,
  `purchase_type` varchar(20) DEFAULT NULL,
  `next_repurchase` date DEFAULT NULL,
  `five_program` varchar(10) DEFAULT NULL,
  `five_110days` varchar(50) DEFAULT NULL,
  `daily_use_qty` int(11) DEFAULT NULL,
  `order_manager` varchar(50) DEFAULT NULL,
  `special_case` varchar(50) DEFAULT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `pay_date` date DEFAULT NULL,
  `buy_date` date DEFAULT NULL,
  `use_start_date` date DEFAULT NULL,
  `benefit_end_date` date DEFAULT NULL,
  `inmarket_due` date DEFAULT NULL,
  `last_confirmed_qty` int(11) DEFAULT NULL,
  `diverticulums` varchar(10) DEFAULT NULL,
  `counsel_no` varchar(50) DEFAULT NULL,
  `counsel_date` datetime DEFAULT NULL,
  `counsel_type` varchar(10) DEFAULT NULL,
  `counsel_acc_add_type` varchar(10) DEFAULT NULL,
  `counsel_status` varchar(10) DEFAULT NULL,
  `counsel_call_no` varchar(30) DEFAULT NULL,
  `counsel_re_date` date DEFAULT NULL,
  `counsel_contents` text DEFAULT NULL,
  `counsel_order_id` bigint(20) unsigned DEFAULT NULL,
  `dealer_type` varchar(50) DEFAULT NULL,
  `caregiver_name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescriptions_rx_number_unique` (`rx_number`),
  KEY `prescriptions_assigned_user_id_foreign` (`assigned_user_id`),
  KEY `prescriptions_reviewed_by_foreign` (`reviewed_by`),
  KEY `prescriptions_status_created_at_index` (`status`,`created_at`),
  KEY `prescriptions_patient_id_index` (`patient_id`),
  KEY `prescriptions_rx_number_index` (`rx_number`),
  KEY `prescriptions_created_by_foreign` (`created_by`),
  KEY `prescriptions_is_blank_draft_created_by_index` (`is_blank_draft`,`created_by`),
  KEY `prescriptions_updated_by_foreign` (`updated_by`),
  KEY `rx_benefit_class_idx` (`benefit_class`),
  KEY `rx_purchase_type_idx` (`purchase_type`),
  KEY `rx_end_date_idx` (`rx_end_date`),
  KEY `rx_next_repurchase_idx` (`next_repurchase`),
  KEY `rx_counsel_no_idx` (`counsel_no`),
  KEY `rx_claim_agency_idx` (`claim_agency`),
  KEY `prescriptions_counsel_order_id_foreign` (`counsel_order_id`),
  KEY `prescriptions_billing_strategy_index` (`billing_strategy`),
  KEY `prescriptions_billing_office_id_index` (`billing_office_id`),
  KEY `prescriptions_input_review_status_idx` (`input_review_status`),
  CONSTRAINT `prescriptions_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_counsel_order_id_foreign` FOREIGN KEY (`counsel_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `privacy_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `privacy_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'mobile',
  `name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `phone2` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `zip` varchar(10) DEFAULT NULL,
  `addr1` varchar(200) DEFAULT NULL,
  `addr2` varchar(200) DEFAULT NULL,
  `insurance` varchar(30) DEFAULT NULL,
  `support_qualify` varchar(40) DEFAULT NULL,
  `birth` varchar(20) DEFAULT NULL,
  `product` varchar(40) DEFAULT NULL,
  `hospital` varchar(100) DEFAULT NULL,
  `surgery_date` varchar(20) DEFAULT NULL,
  `stoma_type` varchar(20) DEFAULT NULL,
  `stoma_kind` varchar(20) DEFAULT NULL,
  `agree_general` varchar(12) DEFAULT NULL,
  `agree_sensitive` varchar(12) DEFAULT NULL,
  `agree_third_party` varchar(12) DEFAULT NULL,
  `agree_marketing` varchar(12) DEFAULT NULL,
  `agree_marketing_sensitive` varchar(12) DEFAULT NULL,
  `agree_third_sensitive` varchar(12) DEFAULT NULL,
  `agree_ads` varchar(12) DEFAULT NULL,
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `admin_memo` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `privacy_consents_type_index` (`type`),
  KEY `privacy_consents_submitted_at_index` (`submitted_at`),
  KEY `privacy_consents_patient_id_index` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_reasons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `label` varchar(60) NOT NULL,
  `adjusts_amount` tinyint(1) NOT NULL DEFAULT 1,
  `includes_issue` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_reasons_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sample_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sample_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_order_id` bigint(20) unsigned NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` int(11) NOT NULL DEFAULT 0,
  `amount` int(11) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sample_order_items_sample_order_id_index` (`sample_order_id`),
  CONSTRAINT `sample_order_items_sample_order_id_foreign` FOREIGN KEY (`sample_order_id`) REFERENCES `sample_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sample_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sample_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sample_no` varchar(30) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT '6001',
  `patient_id` bigint(20) unsigned DEFAULT NULL,
  `requester_id` bigint(20) unsigned DEFAULT NULL,
  `requester_name` varchar(100) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `recipient_name` varchar(100) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `address` varchar(300) DEFAULT NULL,
  `address_detail` varchar(200) DEFAULT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `purpose` varchar(200) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `total_qty` int(11) NOT NULL DEFAULT 0,
  `total_amount` int(11) NOT NULL DEFAULT 0,
  `withworks_so_no` varchar(50) DEFAULT NULL,
  `withworks_so_id` bigint(20) unsigned DEFAULT NULL,
  `withworks_status` varchar(50) DEFAULT NULL,
  `withworks_status_label` varchar(100) DEFAULT NULL,
  `withworks_sent_at` timestamp NULL DEFAULT NULL,
  `withworks_error` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sample_orders_sample_no_unique` (`sample_no`),
  KEY `sample_orders_created_by_foreign` (`created_by`),
  KEY `sample_orders_type_index` (`type`),
  KEY `sample_orders_status_index` (`status`),
  KEY `sample_orders_withworks_so_no_index` (`withworks_so_no`),
  KEY `sample_orders_patient_id_foreign` (`patient_id`),
  KEY `sample_orders_requester_id_foreign` (`requester_id`),
  CONSTRAINT `sample_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sample_orders_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sample_orders_requester_id_foreign` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(20) NOT NULL DEFAULT 'improve',
  `priority` varchar(10) NOT NULL DEFAULT 'normal',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `page_label` varchar(100) DEFAULT NULL,
  `page_url` varchar(300) DEFAULT NULL,
  `answer` text DEFAULT NULL,
  `answered_by` bigint(20) unsigned DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_requests_user_id_foreign` (`user_id`),
  KEY `service_requests_answered_by_foreign` (`answered_by`),
  KEY `service_requests_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `service_requests_answered_by_foreign` FOREIGN KEY (`answered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(40) NOT NULL,
  `key` varchar(60) NOT NULL,
  `value` text DEFAULT NULL,
  `is_secret` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_group_key_unique` (`group`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shop_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_order_id` bigint(20) unsigned NOT NULL COMMENT 'ce-shop orders.id',
  `order_number` varchar(50) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_company` varchar(100) DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `delivery_method` varchar(20) DEFAULT NULL,
  `delivery_name` varchar(100) DEFAULT NULL,
  `delivery_phone` varchar(30) DEFAULT NULL,
  `delivery_zipcode` varchar(10) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `delivery_note` text DEFAULT NULL,
  `buyer_note` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'confirmed',
  `withworks_so_no` varchar(50) DEFAULT NULL,
  `withworks_so_id` bigint(20) unsigned DEFAULT NULL,
  `admin_memo` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_orders_shop_order_id_unique` (`shop_order_id`),
  UNIQUE KEY `shop_orders_order_number_unique` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shop_product_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_product_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_user_id` int(10) unsigned NOT NULL,
  `shop_user_name` varchar(100) DEFAULT '',
  `shop_user_email` varchar(255) DEFAULT '',
  `product_id` bigint(20) unsigned NOT NULL,
  `product_name` varchar(255) DEFAULT '',
  `log_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_daily` (`shop_user_id`,`product_id`,`log_date`),
  KEY `idx_user` (`shop_user_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shop_user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_user_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_user_id` int(10) unsigned NOT NULL,
  `shop_user_name` varchar(100) DEFAULT '',
  `shop_user_email` varchar(255) DEFAULT '',
  `shop_user_role` varchar(50) DEFAULT '',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_logout_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `ip` varchar(45) DEFAULT '',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`shop_user_id`),
  KEY `idx_activity` (`last_activity_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `toss_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `toss_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `payment_key` varchar(200) DEFAULT NULL,
  `toss_order_id` varchar(100) DEFAULT NULL,
  `method` varchar(50) NOT NULL DEFAULT 'VIRTUAL_ACCOUNT',
  `status` varchar(50) NOT NULL DEFAULT 'READY',
  `amount` int(10) unsigned NOT NULL DEFAULT 0,
  `bank` varchar(10) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `deposited_at` timestamp NULL DEFAULT NULL,
  `canceled_at` timestamp NULL DEFAULT NULL,
  `cancel_amount` bigint(20) unsigned DEFAULT NULL,
  `cancel_reason` varchar(200) DEFAULT NULL,
  `raw_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_response`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `toss_payments_order_id_unique` (`order_id`),
  UNIQUE KEY `toss_payments_payment_key_unique` (`payment_key`),
  CONSTRAINT `toss_payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'page',
  `action` varchar(40) DEFAULT NULL COMMENT 'view|export|unmask|update|delete',
  `target_type` varchar(60) DEFAULT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `record_count` int(10) unsigned DEFAULT NULL COMMENT '다운로드·조회 건수',
  `reason_code` varchar(40) DEFAULT NULL COMMENT 'FR-036 다운로드·마스킹 해제 사유',
  `reason_text` varchar(300) DEFAULT NULL,
  `retention_until` date DEFAULT NULL COMMENT '생성일 + 3년 (PIPC 고시 2023-6)',
  `menu_name` varchar(80) DEFAULT NULL,
  `route_name` varchar(100) DEFAULT NULL,
  `url` varchar(300) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_activity_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `ual_action_created_index` (`action`,`created_at`),
  CONSTRAINT `user_activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `toured_pages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`toured_pages`)),
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `access_scope` varchar(8) NOT NULL DEFAULT 'both',
  `permission_group_id` bigint(20) unsigned DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `fcm_token` varchar(512) DEFAULT NULL COMMENT 'FCM 디바이스 토큰 (백그라운드 푸시 알림)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_permission_group_id_foreign` (`permission_group_id`),
  CONSTRAINT `users_permission_group_id_foreign` FOREIGN KEY (`permission_group_id`) REFERENCES `permission_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` bigint(20) unsigned DEFAULT NULL,
  `provider` varchar(30) NOT NULL,
  `event_code` varchar(80) DEFAULT NULL,
  `direction` varchar(10) NOT NULL DEFAULT 'inbound',
  `url` varchar(500) DEFAULT NULL,
  `http_method` varchar(10) NOT NULL DEFAULT 'POST',
  `ok` tinyint(1) NOT NULL DEFAULT 0,
  `http_status` smallint(5) unsigned DEFAULT NULL,
  `signature_ok` tinyint(1) DEFAULT NULL,
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
  `payload` longtext DEFAULT NULL,
  `response` longtext DEFAULT NULL,
  `error` text DEFAULT NULL,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `ref` varchar(60) DEFAULT NULL,
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhook_logs_webhook_id_foreign` (`webhook_id`),
  KEY `webhook_logs_provider_direction_index` (`provider`,`direction`),
  KEY `webhook_logs_ok_occurred_at_index` (`ok`,`occurred_at`),
  KEY `webhook_logs_provider_index` (`provider`),
  KEY `webhook_logs_ref_index` (`ref`),
  KEY `webhook_logs_occurred_at_index` (`occurred_at`),
  CONSTRAINT `webhook_logs_webhook_id_foreign` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_params`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_params` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` bigint(20) unsigned NOT NULL,
  `position` varchar(10) NOT NULL DEFAULT 'body',
  `name` varchar(100) NOT NULL,
  `data_type` varchar(20) NOT NULL DEFAULT 'string',
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `sample` varchar(200) DEFAULT NULL,
  `description` varchar(300) DEFAULT NULL,
  `sort` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhook_params_webhook_id_sort_index` (`webhook_id`,`sort`),
  CONSTRAINT `webhook_params_webhook_id_foreign` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhooks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `event_code` varchar(80) DEFAULT NULL,
  `direction` varchar(10) NOT NULL DEFAULT 'inbound',
  `url` varchar(500) NOT NULL,
  `http_method` varchar(10) NOT NULL DEFAULT 'POST',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `secret_env` varchar(60) DEFAULT NULL,
  `description` varchar(300) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `sort` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhooks_created_by_foreign` (`created_by`),
  KEY `webhooks_updated_by_foreign` (`updated_by`),
  KEY `webhooks_provider_direction_index` (`provider`,`direction`),
  KEY `webhooks_provider_index` (`provider`),
  CONSTRAINT `webhooks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `webhooks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `withworks_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `withworks_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(100) NOT NULL,
  `event` varchar(50) NOT NULL,
  `ce_order_number` varchar(50) DEFAULT NULL,
  `so_no` varchar(50) DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `status_label` varchar(100) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withworks_events_event_id_unique` (`event_id`),
  KEY `withworks_events_order_id_foreign` (`order_id`),
  KEY `withworks_events_ce_order_number_occurred_at_index` (`ce_order_number`,`occurred_at`),
  KEY `withworks_events_ce_order_number_index` (`ce_order_number`),
  CONSTRAINT `withworks_events_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `withworks_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `withworks_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mode` varchar(20) NOT NULL DEFAULT 'test',
  `test_api_url` varchar(190) DEFAULT NULL,
  `test_api_token` text DEFAULT NULL,
  `test_account_id` varchar(30) DEFAULT NULL,
  `prod_api_url` varchar(190) DEFAULT NULL,
  `prod_api_token` text DEFAULT NULL,
  `prod_account_id` varchar(30) DEFAULT NULL,
  `webhook_url` varchar(190) DEFAULT NULL,
  `webhook_secret` varchar(190) DEFAULT NULL,
  `so_type` varchar(20) NOT NULL DEFAULT '5001',
  `return_so_type` varchar(20) NOT NULL DEFAULT '5004',
  `cancel_so_type` varchar(10) DEFAULT NULL,
  `exchange_so_type` varchar(10) DEFAULT NULL,
  `adjust_so_type` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ww_customer_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ww_customer_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ww_id` bigint(20) unsigned DEFAULT NULL,
  `address_code` varchar(255) DEFAULT NULL,
  `address_name` varchar(255) DEFAULT NULL,
  `address_type` varchar(255) DEFAULT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `top_account_id` bigint(20) unsigned DEFAULT NULL,
  `public_yn` varchar(255) DEFAULT NULL,
  `priority` int(11) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `zipcode` varchar(255) DEFAULT NULL,
  `address_line_1` text DEFAULT NULL,
  `address_line_2` text DEFAULT NULL,
  `address_line_4` varchar(255) DEFAULT NULL,
  `address_line_3` varchar(255) DEFAULT NULL,
  `contact1` varchar(255) DEFAULT NULL,
  `contact2` varchar(255) DEFAULT NULL,
  `phone1` varchar(255) DEFAULT NULL,
  `phone2` varchar(255) DEFAULT NULL,
  `fax1` varchar(255) DEFAULT NULL,
  `fax2` varchar(255) DEFAULT NULL,
  `memo` text DEFAULT NULL,
  `udf1` varchar(50) DEFAULT NULL,
  `udf10` varchar(50) DEFAULT NULL,
  `if_status` varchar(255) DEFAULT NULL,
  `city_or_state` varchar(255) DEFAULT NULL,
  `latitude` decimal(44,30) DEFAULT NULL,
  `longitude` decimal(44,30) DEFAULT NULL,
  `use_yn` varchar(255) DEFAULT NULL,
  `test_yn` varchar(20) DEFAULT NULL,
  `registrant_ip` varchar(255) DEFAULT NULL,
  `edit_user_ip` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `imported_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ww_customer_addresses_ww_id_unique` (`ww_id`),
  KEY `ww_customer_addresses_account_id_index` (`account_id`),
  KEY `ww_customer_addresses_zipcode_index` (`zipcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ww_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ww_customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ww_id` bigint(20) unsigned DEFAULT NULL,
  `biz_type` varchar(20) DEFAULT NULL,
  `cid` varchar(20) DEFAULT NULL,
  `control_call` varchar(20) DEFAULT NULL,
  `view_call` varchar(20) DEFAULT NULL,
  `lang_call` varchar(20) DEFAULT NULL,
  `top_account_id` int(11) DEFAULT NULL,
  `multi_industry` tinyint(4) DEFAULT NULL,
  `plan_id` bigint(20) unsigned DEFAULT NULL,
  `use_taskchain` tinyint(4) DEFAULT NULL,
  `ship_alarm` tinyint(4) DEFAULT NULL,
  `account_code` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `detailed_description` text DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `lang_code` varchar(255) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `tax_address_id` varchar(255) DEFAULT NULL,
  `tax_address_use_yn` char(1) DEFAULT NULL,
  `email_1` varchar(255) DEFAULT NULL,
  `phone_1` varchar(255) DEFAULT NULL,
  `fax_1` varchar(255) DEFAULT NULL,
  `email_2` varchar(255) DEFAULT NULL,
  `phone_2` varchar(255) DEFAULT NULL,
  `fax_2` varchar(255) DEFAULT NULL,
  `tax_email` varchar(50) DEFAULT NULL,
  `erp_cd` varchar(50) DEFAULT NULL,
  `erp_nm` varchar(50) DEFAULT NULL,
  `customer_type` varchar(5) DEFAULT NULL,
  `resident_no` varchar(50) DEFAULT NULL,
  `business_type` varchar(50) DEFAULT NULL,
  `business_item` varchar(50) DEFAULT NULL,
  `account_type` varchar(255) DEFAULT NULL,
  `use_yn` varchar(255) DEFAULT NULL,
  `test_yn` varchar(20) DEFAULT NULL,
  `packaging_yn` varchar(20) DEFAULT NULL,
  `industry` varchar(50) DEFAULT NULL,
  `tax_yn` varchar(20) DEFAULT NULL,
  `special_yn` varchar(20) DEFAULT NULL,
  `sales_partner_yn` varchar(20) DEFAULT NULL,
  `hospital_account_required_yn` varchar(1) DEFAULT NULL,
  `auto_credit_yn` varchar(1) DEFAULT NULL,
  `rep_code` varchar(50) DEFAULT NULL,
  `rep_name` varchar(100) DEFAULT NULL,
  `rep_manager` varchar(100) DEFAULT NULL,
  `udf1` varchar(100) DEFAULT NULL,
  `udf2` varchar(100) DEFAULT NULL,
  `udf3` varchar(100) DEFAULT NULL,
  `udf4` varchar(100) DEFAULT NULL,
  `udf5` varchar(100) DEFAULT NULL,
  `udf6` varchar(100) DEFAULT NULL,
  `udf7` varchar(100) DEFAULT NULL,
  `udf8` varchar(100) DEFAULT NULL,
  `udf9` varchar(100) DEFAULT NULL,
  `udf10` varchar(100) DEFAULT NULL,
  `udf11` varchar(100) DEFAULT NULL,
  `udf12` varchar(100) DEFAULT NULL,
  `udf13` varchar(100) DEFAULT NULL,
  `udf14` varchar(100) DEFAULT NULL,
  `udf15` varchar(100) DEFAULT NULL,
  `udf16` varchar(100) DEFAULT NULL,
  `udf17` varchar(100) DEFAULT NULL,
  `udf18` varchar(100) DEFAULT NULL,
  `udf19` varchar(100) DEFAULT NULL,
  `udf20` varchar(100) DEFAULT NULL,
  `udf21` varchar(255) DEFAULT NULL,
  `udf22` varchar(255) DEFAULT NULL,
  `udf23` varchar(255) DEFAULT NULL,
  `close_flag_date` date DEFAULT NULL,
  `client_id` varchar(255) DEFAULT NULL,
  `manager1` varchar(255) DEFAULT NULL,
  `manager2` varchar(255) DEFAULT NULL,
  `manager1_position` varchar(255) DEFAULT NULL,
  `manager2_position` varchar(255) DEFAULT NULL,
  `manager1_phone_number` varchar(255) DEFAULT NULL,
  `manager2_phone_number` varchar(255) DEFAULT NULL,
  `registrant_ip` varchar(255) DEFAULT NULL,
  `edit_user_ip` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `pi_del_flag` varchar(1) DEFAULT NULL,
  `imported_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ww_customers_ww_id_unique` (`ww_id`),
  KEY `ww_customers_account_code_index` (`account_code`),
  KEY `ww_customers_account_name_index` (`account_name`),
  KEY `ww_customers_address_id_index` (`address_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ww_prescription_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ww_prescription_infos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ww_id` bigint(20) unsigned DEFAULT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `to_account_id` bigint(20) unsigned DEFAULT NULL,
  `ref_account_id` bigint(20) unsigned DEFAULT NULL,
  `add_no` varchar(255) DEFAULT NULL,
  `descr` varchar(1000) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `reg_date` date DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `so_account_id` bigint(20) unsigned DEFAULT NULL,
  `five` varchar(255) DEFAULT NULL,
  `five_program` varchar(255) DEFAULT NULL,
  `privacy` varchar(255) DEFAULT NULL,
  `diverticulums` varchar(255) DEFAULT NULL,
  `udf1` varchar(255) DEFAULT NULL,
  `udf2` varchar(255) DEFAULT NULL,
  `udf3` varchar(255) DEFAULT NULL,
  `udf4` varchar(255) DEFAULT NULL,
  `udf5` varchar(255) DEFAULT NULL,
  `udf6` varchar(255) DEFAULT NULL,
  `udf7` varchar(255) DEFAULT NULL,
  `udf8` varchar(255) DEFAULT NULL,
  `udf9` varchar(255) DEFAULT NULL,
  `udf10` varchar(255) DEFAULT NULL,
  `udf11` varchar(255) DEFAULT NULL,
  `udf12` varchar(255) DEFAULT NULL,
  `udf13` varchar(255) DEFAULT NULL,
  `udf14` varchar(255) DEFAULT NULL,
  `udf15` varchar(255) DEFAULT NULL,
  `udf16` varchar(255) DEFAULT NULL,
  `udf17` varchar(255) DEFAULT NULL,
  `udf18` varchar(255) DEFAULT NULL,
  `udf19` varchar(255) DEFAULT NULL,
  `udf20` varchar(255) DEFAULT NULL,
  `udf21` varchar(255) DEFAULT NULL,
  `udf22` varchar(255) DEFAULT NULL,
  `udf23` varchar(255) DEFAULT NULL,
  `udf24` varchar(255) DEFAULT NULL,
  `udf25` varchar(255) DEFAULT NULL,
  `udf26` varchar(255) DEFAULT NULL,
  `udf27` varchar(255) DEFAULT NULL,
  `udf28` varchar(255) DEFAULT NULL,
  `udf29` varchar(255) DEFAULT NULL,
  `udf30` varchar(255) DEFAULT NULL,
  `udf31` varchar(255) DEFAULT NULL,
  `udf32` varchar(255) DEFAULT NULL,
  `udf33` varchar(255) DEFAULT NULL,
  `udf34` varchar(255) DEFAULT NULL,
  `udf35` varchar(255) DEFAULT NULL,
  `udf36` varchar(255) DEFAULT NULL,
  `udf37` varchar(255) DEFAULT NULL,
  `udf38` varchar(255) DEFAULT NULL,
  `udf39` varchar(255) DEFAULT NULL,
  `udf42` varchar(255) DEFAULT NULL,
  `udf43` varchar(255) DEFAULT NULL,
  `udf45` varchar(255) DEFAULT NULL,
  `udf48` varchar(255) DEFAULT NULL,
  `udf49` varchar(255) DEFAULT NULL,
  `udf50` varchar(255) DEFAULT NULL,
  `registrant_ip` varchar(255) DEFAULT NULL,
  `edit_user_ip` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `imported_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ww_prescription_infos_ww_id_unique` (`ww_id`),
  KEY `ww_prescription_infos_account_id_index` (`account_id`),
  KEY `ww_prescription_infos_to_account_id_index` (`to_account_id`),
  KEY `ww_prescription_infos_add_no_index` (`add_no`),
  KEY `ww_prescription_infos_reg_date_index` (`reg_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2024_01_01_000002_create_patients_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2024_01_01_000003_create_prescriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2024_01_01_000004_create_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_04_13_050055_add_role_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_04_13_052802_create_activity_log_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_04_13_052803_add_event_column_to_activity_log_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_04_13_052804_add_batch_uuid_column_to_activity_log_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_04_13_000001_add_insurance_price_to_prescriptions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_04_13_000002_expand_disease_columns_in_prescriptions',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_04_13_000003_create_prescription_items_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_04_14_074709_create_personal_access_tokens_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_04_14_000001_create_chat_tables',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_04_15_000001_create_toss_payments_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_04_15_000002_add_created_by_to_prescriptions_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_04_15_000003_add_address_fields_to_prescriptions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_04_15_000004_add_repurchase_date_to_prescriptions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_04_16_000001_create_notices_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_04_16_000002_create_inquiries_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_04_16_000003_create_inquiry_messages_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_04_16_000004_create_notice_reads_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_04_17_000001_add_so_type_to_orders_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_04_20_061939_create_shop_orders_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_05_08_000001_create_fax_histories_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_05_08_100001_create_cashbill_records_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_05_08_000002_add_popbill_fields_to_fax_histories',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_05_08_200001_create_popbill_taxinvoices_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_05_08_201311_add_tax_invoice_ceo_name_to_orders_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_04_19_000001_add_toured_pages_to_users_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_04_19_000002_create_user_activity_logs_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_04_22_000001_create_institutional_notices_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_05_09_000001_add_phone_to_users_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_05_09_000002_create_login_otp_tokens_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_05_09_000003_add_pending_token_to_login_otp_tokens_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_05_09_100001_create_prescription_memos_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_05_09_200002_create_prescription_consents_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_05_09_300001_add_counseling_data_to_prescriptions_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_05_10_000001_add_pdf_path_to_prescription_consents',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_05_10_000001_add_withworks_status_to_orders_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_05_10_103335_add_pdf_and_prescription_to_fax_histories',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_05_10_112635_add_withworks_ship_to_orders',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_05_10_200001_add_notification_sent_at_to_prescriptions_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_05_10_220000_create_prescription_documents_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_05_11_145516_create_admin_invitations_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_05_11_200000_create_prescription_attachments_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_05_11_210000_add_attachment_ids_to_fax_histories',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_06_30_000001_create_privacy_consents_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_06_30_000002_create_delegation_settings_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_07_27_000001_add_nice_identity_to_prescription_consents',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_07_27_000002_create_ocr_settings_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_07_28_000001_create_nice_settings_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_07_30_000001_create_permission_groups_tables',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_07_30_000002_create_service_requests_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_08_03_000001_add_is_blank_draft_to_prescriptions_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_08_03_100000_add_audit_columns_to_user_activity_logs',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_08_03_100001_add_encrypted_resident_no_columns',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_08_10_100000_drop_plain_resident_no_columns',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_08_04_000001_create_settings_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_08_06_000001_add_updated_by_to_prescriptions_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_08_12_000001_create_message_tables',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_08_12_000002_add_delegation_fields_and_guardian',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_08_12_000003_add_guardian_birth_date',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_08_12_000004_create_master_items_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_08_12_000005_add_guardian_phone',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_08_15_000001_add_reply_edit_delete_to_chat_messages',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_08_15_000002_add_source_and_admin_memo_to_privacy_consents',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_08_15_000003_promote_counseling_json_to_columns',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_08_15_000004_add_remaining_counseling_columns',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_08_15_000005_create_order_items_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_08_15_000006_add_claim_agency_to_prescriptions',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_08_15_000007_add_claim_readiness_to_orders',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_08_15_000008_create_withworks_events_table',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_08_15_000009_widen_withworks_status_columns',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_08_15_000010_create_local_claim_dispatches_table',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_08_15_000011_create_order_returns_tables',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_08_17_000001_create_withworks_settings_table',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_08_17_000001_add_withworks_to_order_returns',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_08_17_000002_create_sample_orders_tables',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_08_17_000003_add_patient_to_sample_orders',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_08_17_000004_allow_null_order_no_on_withworks_events',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_08_18_000001_create_payment_links_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_08_18_000002_add_requester_to_sample_orders',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_08_18_000003_add_cancelled_to_nhis_claim_status',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_08_19_000001_add_counsel_order_to_prescriptions',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_08_19_000002_add_postcode_to_patients',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_08_19_000003_create_common_codes_table',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_08_19_000004_widen_doc_type_on_prescription_attachments',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_08_23_000001_add_patient_id_to_privacy_consents',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_08_23_000002_add_care_type_to_patients',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_08_23_000003_widen_disease_class_on_prescriptions',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_08_23_000004_add_billing_strategy_to_prescriptions',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2026_08_23_000005_move_orders_to_current_so_type',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2026_08_24_000001_add_review_requested_to_prescription_status',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2026_08_24_000002_add_ats_template_code_to_message_templates',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2026_08_24_000003_create_billing_offices',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2026_08_24_000004_add_manager_name_to_billing_offices',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2026_08_24_000005_add_billing_office_to_prescriptions',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_08_24_000006_add_deposit_confirm_to_orders',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_08_24_000007_add_pay_method_to_orders',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2026_08_28_000001_add_shipping_postcode_to_orders',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2026_08_29_000001_extend_order_returns_for_unicorn_flow',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2026_08_29_000002_extend_inquiries_for_patient_list',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2026_08_29_000003_extend_patients_for_master_screen',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2026_08_29_000004_add_disease_grade_and_uro_findings',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2026_08_31_000001_add_shipped_at_and_item_lots',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2026_08_31_000002_add_pl3_status_to_order_returns',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2026_08_31_000003_extend_order_returns_for_refund_detail',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2026_08_31_000004_add_return_progress_message_template',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2026_08_31_000005_create_bank_transactions_table',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2026_08_31_000006_link_popbill_records_to_orders',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2026_08_31_000007_add_operation_fields_to_orders',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2026_08_31_000008_create_return_reasons_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2026_08_31_000009_extend_nhis_claim_status',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2026_08_31_000010_add_settle_status_to_orders',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2026_09_01_000001_create_hospitals_table',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2026_09_03_000001_add_privacy_consent_doc_type',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2026_09_03_000002_add_sent_by_to_prescription_consents',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2026_09_03_000003_shipping_burden_is_ours',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2026_09_03_000004_add_adjust_amount_to_order_returns',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2026_09_03_000005_drop_shipping_burden',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2026_09_03_000006_add_pay_method_to_patients',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2026_09_04_000001_widen_order_status_enum',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2026_09_05_000001_add_nhis_renew_told_at_to_patients',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2026_09_06_000001_add_cancel_columns_to_toss_payments',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2026_09_06_000002_add_pl3_note_to_order_returns',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2026_09_07_000001_add_access_scope_to_users_table',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2026_09_07_000001_add_withworks_warehouse_to_orders',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_09_07_000002_add_withworks_meta_to_orders',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_09_07_000001_allow_sigungu_wide_billing_area',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_09_09_000001_add_warehouse_note_to_orders',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_09_09_000002_add_review_request_memo_to_prescriptions',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_09_09_000003_add_id_card_to_prescription_consents',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2026_09_09_000004_add_statement_date_to_orders',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2026_09_09_000005_add_main_contact_to_patients',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_09_09_000006_add_image_tune_to_prescription_docs',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_09_09_000007_add_benefit_dates_to_prescriptions',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2026_09_09_000008_add_marketing_consent_to_patients',120);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2026_09_10_000001_clear_blank_draft_on_filled_prescriptions',121);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2026_09_10_000002_add_agree_ads_to_privacy_consents',122);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2026_09_10_000003_add_final_agreements_to_prescription_consents',123);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2026_09_10_000004_create_webhook_tables',124);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2026_09_10_000005_seed_popbill_webhooks',125);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2026_09_10_000006_split_withworks_webhooks',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2026_09_10_000007_recount_benefit_end_dates',127);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2026_09_10_000008_add_reference_note_to_prescriptions',128);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2026_09_11_000001_create_error_logs_table',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2026_09_11_000002_add_source_to_error_logs',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_09_12_000001_create_delegation_signs_table',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2026_09_12_000002_add_source_fields_to_delegation_signs',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2026_09_12_000003_drop_phone2_from_delegation_signs',133);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2026_09_12_100000_create_prescription_reupload_requests_table',134);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2026_09_12_000001_create_fcm_notifications_table',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2026_09_13_000001_add_confirm_request_fields',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2026_09_14_000001_add_source_to_delegation_signs',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2026_09_14_000002_add_guardian_contact_to_delegation_signs',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2026_09_14_000003_add_tax_invoice_purpose_to_orders',139);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_09_14_000004_add_extra_order_to_orders',140);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_09_14_000005_add_cancel_request_to_orders',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_09_15_000001_add_resident_to_delegation_signs',142);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2026_09_15_000002_add_guardian_sign_to_delegation_signs',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2026_09_15_210000_add_review_hold_resent_to_prescriptions_status',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2026_09_15_230000_add_amend_state_to_orders',145);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2026_09_16_000100_rewrite_webhook_param_descriptions',146);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2026_09_16_120000_split_input_review_from_file_review',147);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2026_09_17_140000_add_registration_overlay_to_prescription_attachments',148);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2026_09_18_180000_add_tax_invoice_mgt_key_to_orders',149);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2026_09_18_220000_create_withworks_mirror_tables',150);
