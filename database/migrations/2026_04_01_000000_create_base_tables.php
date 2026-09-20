<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 뼈대가 되는 네 표를 만든다 — users · patients · prescriptions · orders (2026-09-20 지시).
 *
 * 이 넷에는 만드는 장이 한 장도 없었다. 고치는 장(add)만 111 장이 쌓여 있어, 빈 DB 에
 * migrate 를 돌리면 두 번째 장에서 멈췄다.
 *
 *   2026_04_15_000002_add_created_by_to_prescriptions_table
 *     → SQLSTATE[42S02] Base table or view not found: 'prescriptions'
 *
 * DB 가 SQL 덤프로 세워지고 마이그레이션이 그 위에 얹혀 온 탓이다. 돌고 있는 서버는
 * 멀쩡했지만 새 서버ㆍ개발자 합류ㆍ시험 DB 재생성ㆍCI 어느 쪽도 저장소만으로는 설 수
 * 없었다.
 *
 * **여기 적힌 모양은 지금 모양이 아니라 2026-04 무렵의 모양이다.** 뒤따르는 장들이
 * 칸을 더하므로, 지금 모양을 그대로 넣으면 그 장들이 「이미 있는 칸」으로 깨진다.
 * 운영 스키마에서 **뒤에 더해지는 칸을 빼고**, 뒤에 지워지는 칸(resident_no ·
 * resident_no_ocr)은 도로 넣어 맞췄다.
 *
 * 외래키는 여기서 걸지 않는다. prescriptions 와 orders 가 서로를 가리키고(순환),
 * users 는 뒤에 생기는 permission_groups 를 가리킨다 — 마지막 장에서 한꺼번에 건다
 * (2026_09_20_000002_add_base_foreign_keys).
 *
 * 이미 표가 있는 서버에서는 아무 일도 하지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `users` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL,
                    `email` varchar(255) NOT NULL,
                    `is_active` tinyint(1) NOT NULL DEFAULT 1,
                    `email_verified_at` timestamp NULL DEFAULT NULL,
                    `password` varchar(255) NOT NULL,
                    `role` varchar(20) NOT NULL DEFAULT 'user',
                    `remember_token` varchar(100) DEFAULT NULL,
                    `fcm_token` varchar(512) DEFAULT NULL COMMENT 'FCM 디바이스 토큰 (백그라운드 푸시 알림)',
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('patients')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `patients` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL COMMENT '환자명',
                    `resident_no` varchar(20) DEFAULT NULL,
                    `birth_date` date DEFAULT NULL COMMENT '생년월일',
                    `gender` varchar(10) DEFAULT NULL COMMENT '성별',
                    `mobile` varchar(30) DEFAULT NULL COMMENT '휴대폰번호',
                    `phone` varchar(30) DEFAULT NULL COMMENT '일반 전화',
                    `address` varchar(300) DEFAULT NULL COMMENT '주소',
                    `health_insurance_no` varchar(20) DEFAULT NULL COMMENT '건강보험 번호',
                    `note` text DEFAULT NULL COMMENT '메모',
                    `is_nhis_eligible` tinyint(1) NOT NULL DEFAULT 0 COMMENT '건보 대상 여부',
                    `nhis_coverage_rate` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '건보 지원 비율',
                    `memo` text DEFAULT NULL COMMENT '메모',
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    `deleted_at` timestamp NULL DEFAULT NULL,
                    `email` varchar(190) DEFAULT NULL,
                    `sb_sci` varchar(50) DEFAULT NULL,
                    `nhis_reg_status` varchar(20) DEFAULT NULL,
                    `nhis_reg_date` date DEFAULT NULL,
                    `nhis_renew` varchar(100) DEFAULT NULL,
                    `nhis_renew_due` date DEFAULT NULL,
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
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('prescriptions')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `prescriptions` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `rx_number` varchar(30) NOT NULL COMMENT '처방번호 RX-YYYYMMDD-NNN',
                    `patient_id` bigint(20) unsigned DEFAULT NULL,
                    `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
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
                    `resident_no_ocr` varchar(255) DEFAULT NULL,
                    `mobile_ocr` varchar(30) DEFAULT NULL COMMENT 'OCR 추출 휴대전화',
                    `address_ocr` varchar(300) DEFAULT NULL COMMENT 'OCR 추출 주소',
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
                    `reviewed_by` bigint(20) unsigned DEFAULT NULL,
                    `reviewed_at` timestamp NULL DEFAULT NULL,
                    `review_memo` text DEFAULT NULL,
                    `admin_note` text DEFAULT NULL,
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    `deleted_at` timestamp NULL DEFAULT NULL,
                    `benefit_class` varchar(20) DEFAULT NULL,
                    `disease_class` varchar(100) DEFAULT NULL,
                    `uro_date` date DEFAULT NULL,
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
                    `dealer_type` varchar(50) DEFAULT NULL,
                    `caregiver_name` varchar(50) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('orders')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `orders` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `order_number` varchar(20) NOT NULL COMMENT '주문번호 ORD-NNNN',
                    `prescription_id` bigint(20) unsigned NOT NULL,
                    `patient_id` bigint(20) unsigned DEFAULT NULL,
                    `created_by` bigint(20) unsigned DEFAULT NULL,
                    `product_name` varchar(255) NOT NULL,
                    `product_code` varchar(255) DEFAULT NULL,
                    `quantity` int(10) unsigned NOT NULL,
                    `unit_price` decimal(10,2) NOT NULL,
                    `nhis_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `patient_copay` decimal(10,2) NOT NULL,
                    `shipping_fee` decimal(10,2) NOT NULL DEFAULT 3000.00,
                    `total_amount` decimal(10,2) NOT NULL,
                    `status` enum('pending','confirmed','allocated','picked','invoiced','shipping','delivered','cancelled') NOT NULL DEFAULT 'pending',
                    `withworks_so_no` varchar(50) DEFAULT NULL COMMENT 'Withworks SO 번호',
                    `withworks_so_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Withworks SO PK',
                    `withworks_status` varchar(50) DEFAULT NULL,
                    `withworks_status_label` varchar(50) DEFAULT NULL,
                    `withworks_status_at` timestamp NULL DEFAULT NULL,
                    `shipping_address` varchar(255) DEFAULT NULL,
                    `shipping_recipient` varchar(100) DEFAULT NULL COMMENT '배송지 받는 사람',
                    `tracking_number` varchar(255) DEFAULT NULL,
                    `estimated_delivery` date DEFAULT NULL,
                    `delivered_at` timestamp NULL DEFAULT NULL,
                    `nhis_claim_status` enum('pending','submitting','submitted','approved','rejected','on_hold','cancelled') NOT NULL DEFAULT 'pending',
                    `nhis_submitted_at` timestamp NULL DEFAULT NULL,
                    `nhis_approved_at` timestamp NULL DEFAULT NULL,
                    `nhis_reimbursement` decimal(10,2) DEFAULT NULL,
                    `latest_fax_log_id` bigint(20) unsigned DEFAULT NULL,
                    `nhis_rejection_reason` varchar(500) DEFAULT NULL,
                    `note` text DEFAULT NULL,
                    `tax_invoice_status` enum('not_issued','issued','cancelled') NOT NULL DEFAULT 'not_issued',
                    `tax_invoice_no` varchar(50) DEFAULT NULL,
                    `tax_invoice_type` enum('electronic','manual') NOT NULL DEFAULT 'electronic',
                    `tax_invoice_biz_name` varchar(100) DEFAULT NULL,
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
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        /* 마이그레이션이 아예 없던 표들 (2026-09-20 확인).

           라라벨 기본(users · cache · jobs · sessions · password_reset_tokens ·
           personal_access_tokens · failed_jobs · job_batches)과, 덤프로만 서 있던
           우리 표 다섯(activity_log · nhis_fax_logs · prescription_items ·
           shop_product_logs · shop_user_sessions)이다. 어느 장도 만들지 않아 빈 DB
           에서는 서지 않았다 — 운영 스키마 그대로 옮겨 적는다. */
        if (! Schema::hasTable('activity_log')) {
            DB::statement(<<<'SQL'
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('cache')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `cache` (
                    `key` varchar(255) NOT NULL,
                    `value` mediumtext NOT NULL,
                    `expiration` int(11) NOT NULL,
                    PRIMARY KEY (`key`),
                    KEY `cache_expiration_index` (`expiration`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('cache_locks')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `cache_locks` (
                    `key` varchar(255) NOT NULL,
                    `owner` varchar(255) NOT NULL,
                    `expiration` int(11) NOT NULL,
                    PRIMARY KEY (`key`),
                    KEY `cache_locks_expiration_index` (`expiration`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('failed_jobs')) {
            DB::statement(<<<'SQL'
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('job_batches')) {
            DB::statement(<<<'SQL'
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('jobs')) {
            DB::statement(<<<'SQL'
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('nhis_fax_logs')) {
            DB::statement(<<<'SQL'
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
                    KEY `nhis_fax_logs_sent_by_foreign` (`sent_by`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            DB::statement(<<<'SQL'
                CREATE TABLE `password_reset_tokens` (
                    `email` varchar(255) NOT NULL,
                    `token` varchar(255) NOT NULL,
                    `created_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            DB::statement(<<<'SQL'
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('prescription_items')) {
            DB::statement(<<<'SQL'
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
                    KEY `prescription_items_prescription_id_sort_order_index` (`prescription_id`,`sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('sessions')) {
            DB::statement(<<<'SQL'
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('shop_product_logs')) {
            DB::statement(<<<'SQL'
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }

        if (! Schema::hasTable('shop_user_sessions')) {
            DB::statement(<<<'SQL'
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL);
        }
    }

    public function down(): void
    {
        /* 되돌리지 않는다.

           이 넷은 시스템의 뼈대다. 지우면 그 위에 쌓인 모든 자료가 함께 사라지고,
           되살릴 길은 백업뿐이다. 한 장을 물리려다 DB 를 통째로 잃는 일을 막는다. */
    }
};
