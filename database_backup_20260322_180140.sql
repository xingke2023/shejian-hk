mysqldump: [Warning] Using a password on the command line interface can be insecure.
-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: laravel_app
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
mysqldump: Error: 'Access denied; you need (at least one of) the PROCESS privilege(s) for this operation' when trying to dump tablespaces

--
-- Table structure for table `ai_command_templates`
--

DROP TABLE IF EXISTS `ai_command_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_command_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned DEFAULT NULL,
  `intent` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '意图标识',
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '目标模块',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_phrases` json DEFAULT NULL COMMENT '触发词数组',
  `required_entities` json DEFAULT NULL COMMENT '必需实体字段列表',
  `optional_entities` json DEFAULT NULL COMMENT '可选实体字段列表',
  `action_handler` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '后端处理类/方法名',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_command_templates_organization_id_foreign` (`organization_id`),
  KEY `ai_command_templates_intent_is_active_index` (`intent`,`is_active`),
  CONSTRAINT `ai_command_templates_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_command_templates`
--

LOCK TABLES `ai_command_templates` WRITE;
/*!40000 ALTER TABLE `ai_command_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_command_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_forecast_models`
--

DROP TABLE IF EXISTS `ai_forecast_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_forecast_models` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sarima, lstm, xgboost, hybrid',
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0.0',
  `target_category_ids` json DEFAULT NULL COMMENT '适用商品分类ID列表',
  `hyperparameters` json DEFAULT NULL COMMENT '模型超参数',
  `feature_config` json DEFAULT NULL COMMENT '特征配置',
  `training_period_days` int NOT NULL DEFAULT '90' COMMENT '训练使用的历史天数',
  `forecast_horizon_days` int NOT NULL DEFAULT '7' COMMENT '预测未来天数',
  `accuracy_mape` decimal(6,4) DEFAULT NULL COMMENT '平均绝对百分比误差',
  `accuracy_rmse` decimal(10,4) DEFAULT NULL,
  `last_trained_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_forecast_models_organization_id_foreign` (`organization_id`),
  KEY `ai_forecast_models_store_id_foreign` (`store_id`),
  CONSTRAINT `ai_forecast_models_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_forecast_models_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_forecast_models`
--

LOCK TABLES `ai_forecast_models` WRITE;
/*!40000 ALTER TABLE `ai_forecast_models` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_forecast_models` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_forecast_results`
--

DROP TABLE IF EXISTS `ai_forecast_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_forecast_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `forecast_date` date NOT NULL COMMENT '预测目标日期',
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `predicted_qty` decimal(10,3) NOT NULL,
  `predicted_qty_low` decimal(10,3) DEFAULT NULL COMMENT '预测下界（80%置信区间）',
  `predicted_qty_high` decimal(10,3) DEFAULT NULL,
  `actual_qty` decimal(10,3) DEFAULT NULL COMMENT '实际销量（事后回填）',
  `forecast_error` decimal(10,4) DEFAULT NULL,
  `input_features` json DEFAULT NULL COMMENT '预测使用的特征值快照',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ai_forecast_results_product_id_foreign` (`product_id`),
  KEY `ai_forecast_results_store_id_product_id_forecast_date_index` (`store_id`,`product_id`,`forecast_date`),
  KEY `ai_forecast_results_model_id_generated_at_index` (`model_id`,`generated_at`),
  KEY `ai_forecast_results_forecast_date_index` (`forecast_date`),
  CONSTRAINT `ai_forecast_results_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `ai_forecast_models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_forecast_results_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_forecast_results_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_forecast_results`
--

LOCK TABLES `ai_forecast_results` WRITE;
/*!40000 ALTER TABLE `ai_forecast_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_forecast_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_messages`
--

DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `role` tinyint NOT NULL COMMENT '1:用户 2:AI助手',
  `input_type` tinyint NOT NULL DEFAULT '1' COMMENT '1:文字 2:语音 3:图片 4:混合',
  `raw_content` text COLLATE utf8mb4_unicode_ci COMMENT '原始文字输入',
  `voice_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_urls` json DEFAULT NULL,
  `transcribed_text` text COLLATE utf8mb4_unicode_ci COMMENT '语音转文字结果',
  `ocr_text` text COLLATE utf8mb4_unicode_ci COMMENT '图片OCR结果',
  `ai_response` text COLLATE utf8mb4_unicode_ci,
  `intent` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '识别的意图类型',
  `entities` json DEFAULT NULL COMMENT '提取的实体（商品名、数量等）',
  `confidence` decimal(5,4) DEFAULT NULL COMMENT '意图置信度',
  `dispatched_module` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分发到的功能模块',
  `dispatched_action_id` bigint unsigned DEFAULT NULL COMMENT '触发的业务记录ID',
  `processing_time_ms` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ai_messages_session_id_index` (`session_id`),
  KEY `ai_messages_intent_created_at_index` (`intent`,`created_at`),
  CONSTRAINT `ai_messages_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `ai_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_messages`
--

LOCK TABLES `ai_messages` WRITE;
/*!40000 ALTER TABLE `ai_messages` DISABLE KEYS */;
INSERT INTO `ai_messages` VALUES (1,1,1,1,'测试连接',NULL,NULL,NULL,NULL,NULL,'other','[]',NULL,NULL,NULL,NULL,'2026-03-21 22:59:53'),(2,1,2,1,NULL,NULL,NULL,NULL,NULL,'AI服务暂时不可用，请稍后重试。',NULL,NULL,NULL,'inventory',NULL,167,'2026-03-21 22:59:53');
/*!40000 ALTER TABLE `ai_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_order_reviews`
--

DROP TABLE IF EXISTS `ai_order_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_order_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `review_period_start` date NOT NULL,
  `review_period_end` date NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `total_products_reviewed` int NOT NULL DEFAULT '0',
  `avg_forecast_accuracy` decimal(6,4) DEFAULT NULL,
  `overstock_products` json DEFAULT NULL COMMENT '过量采购商品列表',
  `understock_products` json DEFAULT NULL COMMENT '短缺商品列表',
  `waste_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `adjustment_suggestions` json DEFAULT NULL COMMENT '模型调整建议参数',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `is_auto_generated` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ai_order_reviews_model_id_foreign` (`model_id`),
  KEY `ai_order_reviews_reviewed_by_foreign` (`reviewed_by`),
  KEY `ai_order_reviews_store_id_review_period_start_index` (`store_id`,`review_period_start`),
  CONSTRAINT `ai_order_reviews_model_id_foreign` FOREIGN KEY (`model_id`) REFERENCES `ai_forecast_models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_order_reviews_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_order_reviews_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_order_reviews`
--

LOCK TABLES `ai_order_reviews` WRITE;
/*!40000 ALTER TABLE `ai_order_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_order_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_sessions`
--

DROP TABLE IF EXISTS `ai_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `session_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` tinyint NOT NULL DEFAULT '1' COMMENT '1:APP语音 2:APP文字 3:企业微信 4:Web',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:进行中 2:已完成 3:异常',
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` timestamp NULL DEFAULT NULL,
  `context` json DEFAULT NULL COMMENT '多轮对话上下文',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_sessions_session_uuid_unique` (`session_uuid`),
  KEY `ai_sessions_user_id_foreign` (`user_id`),
  KEY `ai_sessions_store_id_user_id_index` (`store_id`,`user_id`),
  KEY `ai_sessions_started_at_index` (`started_at`),
  CONSTRAINT `ai_sessions_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_sessions`
--

LOCK TABLES `ai_sessions` WRITE;
/*!40000 ALTER TABLE `ai_sessions` DISABLE KEYS */;
INSERT INTO `ai_sessions` VALUES (1,1,1,'04a76b3d-2952-4160-a476-cf59b3e7bd14',2,1,'2026-03-21 22:59:53',NULL,NULL,'2026-03-21 22:59:53','2026-03-21 22:59:53');
/*!40000 ALTER TABLE `ai_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_records`
--

DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `work_date` date NOT NULL,
  `scheduled_start` time DEFAULT NULL,
  `scheduled_end` time DEFAULT NULL,
  `clock_in_at` timestamp NULL DEFAULT NULL,
  `clock_out_at` timestamp NULL DEFAULT NULL,
  `clock_in_source` tinyint DEFAULT NULL COMMENT '1:APP 2:企业微信 3:人工补录',
  `work_hours` decimal(4,2) DEFAULT NULL,
  `overtime_hours` decimal(4,2) NOT NULL DEFAULT '0.00',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:正常 2:迟到 3:早退 4:缺勤 5:请假',
  `exception_reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_records_employee_id_work_date_unique` (`employee_id`,`work_date`),
  KEY `attendance_records_approved_by_foreign` (`approved_by`),
  KEY `attendance_records_store_id_work_date_index` (`store_id`,`work_date`),
  CONSTRAINT `attendance_records_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_records_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_records`
--

LOCK TABLES `attendance_records` WRITE;
/*!40000 ALTER TABLE `attendance_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competitor_hot_products`
--

DROP TABLE IF EXISTS `competitor_hot_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitor_hot_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `competitor_id` bigint unsigned NOT NULL,
  `competitor_product_id` bigint unsigned NOT NULL,
  `identified_date` date NOT NULL,
  `heat_score` decimal(5,2) DEFAULT NULL,
  `evidence` json DEFAULT NULL COMMENT '热度证据',
  `our_product_id` bigint unsigned DEFAULT NULL,
  `recommendation` tinyint DEFAULT NULL COMMENT '1:引进建议 2:加量建议 3:已有无需操作',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `competitor_hot_products_competitor_product_id_foreign` (`competitor_product_id`),
  KEY `competitor_hot_products_our_product_id_foreign` (`our_product_id`),
  KEY `competitor_hot_products_reviewed_by_foreign` (`reviewed_by`),
  KEY `competitor_hot_products_competitor_id_identified_date_index` (`competitor_id`,`identified_date`),
  CONSTRAINT `competitor_hot_products_competitor_id_foreign` FOREIGN KEY (`competitor_id`) REFERENCES `competitors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competitor_hot_products_competitor_product_id_foreign` FOREIGN KEY (`competitor_product_id`) REFERENCES `competitor_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competitor_hot_products_our_product_id_foreign` FOREIGN KEY (`our_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competitor_hot_products_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competitor_hot_products`
--

LOCK TABLES `competitor_hot_products` WRITE;
/*!40000 ALTER TABLE `competitor_hot_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `competitor_hot_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competitor_price_records`
--

DROP TABLE IF EXISTS `competitor_price_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitor_price_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `competitor_product_id` bigint unsigned NOT NULL,
  `competitor_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `original_price` decimal(12,2) DEFAULT NULL COMMENT '划线价',
  `is_promotion` tinyint(1) NOT NULL DEFAULT '0',
  `collect_source` tinyint NOT NULL DEFAULT '1' COMMENT '1:人工录入 2:APP扫码 3:爬虫 4:第三方API',
  `collect_channel` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '采集凭证图片',
  `collected_by` bigint unsigned DEFAULT NULL,
  `collected_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `competitor_price_records_competitor_product_id_foreign` (`competitor_product_id`),
  KEY `competitor_price_records_collected_by_foreign` (`collected_by`),
  KEY `cpr_competitor_product_date` (`competitor_id`,`product_id`,`collected_at`),
  KEY `cpr_product_date` (`product_id`,`collected_at`),
  KEY `competitor_price_records_collected_at_index` (`collected_at`),
  CONSTRAINT `competitor_price_records_collected_by_foreign` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competitor_price_records_competitor_id_foreign` FOREIGN KEY (`competitor_id`) REFERENCES `competitors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competitor_price_records_competitor_product_id_foreign` FOREIGN KEY (`competitor_product_id`) REFERENCES `competitor_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competitor_price_records_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competitor_price_records`
--

LOCK TABLES `competitor_price_records` WRITE;
/*!40000 ALTER TABLE `competitor_price_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `competitor_price_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competitor_products`
--

DROP TABLE IF EXISTS `competitor_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitor_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `competitor_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `competitor_product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `competitor_product_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spec` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_confidence` decimal(5,4) DEFAULT NULL COMMENT '与自家商品匹配置信度',
  `is_manually_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `competitor_products_competitor_id_index` (`competitor_id`),
  KEY `competitor_products_product_id_index` (`product_id`),
  CONSTRAINT `competitor_products_competitor_id_foreign` FOREIGN KEY (`competitor_id`) REFERENCES `competitors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `competitor_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competitor_products`
--

LOCK TABLES `competitor_products` WRITE;
/*!40000 ALTER TABLE `competitor_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `competitor_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competitors`
--

DROP TABLE IF EXISTS `competitors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `competitors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `distance_to_store` decimal(8,2) DEFAULT NULL COMMENT '距最近自家门店（米）',
  `nearest_store_id` bigint unsigned DEFAULT NULL,
  `channels` json DEFAULT NULL COMMENT '情报采集渠道',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0:停止监控 1:正常监控',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `competitors_nearest_store_id_foreign` (`nearest_store_id`),
  KEY `competitors_organization_id_status_index` (`organization_id`,`status`),
  CONSTRAINT `competitors_nearest_store_id_foreign` FOREIGN KEY (`nearest_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `competitors_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competitors`
--

LOCK TABLES `competitors` WRITE;
/*!40000 ALTER TABLE `competitors` DISABLE KEYS */;
/*!40000 ALTER TABLE `competitors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `custom_report_templates`
--

DROP TABLE IF EXISTS `custom_report_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_report_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `data_sources` json DEFAULT NULL COMMENT '数据源配置',
  `filters` json DEFAULT NULL COMMENT '筛选条件配置',
  `columns` json DEFAULT NULL COMMENT '报表列定义',
  `chart_types` json DEFAULT NULL,
  `schedule_cron` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '定时生成表达式',
  `is_shared` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `custom_report_templates_created_by_foreign` (`created_by`),
  KEY `custom_report_templates_organization_id_is_shared_index` (`organization_id`,`is_shared`),
  CONSTRAINT `custom_report_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `custom_report_templates_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `custom_report_templates`
--

LOCK TABLES `custom_report_templates` WRITE;
/*!40000 ALTER TABLE `custom_report_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `custom_report_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dashboard_configs`
--

DROP TABLE IF EXISTS `dashboard_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dashboard_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` tinyint NOT NULL DEFAULT '3' COMMENT '1:个人 2:门店 3:区域 4:总部',
  `widgets` json DEFAULT NULL COMMENT '组件配置数组',
  `filters` json DEFAULT NULL COMMENT '默认筛选条件',
  `refresh_interval` int NOT NULL DEFAULT '0' COMMENT '自动刷新间隔（秒），0不刷新',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_configs_user_id_is_default_index` (`user_id`,`is_default`),
  KEY `dashboard_configs_store_id_scope_index` (`store_id`,`scope`),
  CONSTRAINT `dashboard_configs_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dashboard_configs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dashboard_configs`
--

LOCK TABLES `dashboard_configs` WRITE;
/*!40000 ALTER TABLE `dashboard_configs` DISABLE KEYS */;
/*!40000 ALTER TABLE `dashboard_configs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_store_history`
--

DROP TABLE IF EXISTS `employee_store_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_store_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `from_store_id` bigint unsigned DEFAULT NULL,
  `to_store_id` bigint unsigned NOT NULL,
  `effective_date` date NOT NULL,
  `reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_store_history_from_store_id_foreign` (`from_store_id`),
  KEY `employee_store_history_to_store_id_foreign` (`to_store_id`),
  KEY `employee_store_history_approved_by_foreign` (`approved_by`),
  KEY `employee_store_history_employee_id_effective_date_index` (`employee_id`,`effective_date`),
  CONSTRAINT `employee_store_history_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_store_history_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_store_history_from_store_id_foreign` FOREIGN KEY (`from_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_store_history_to_store_id_foreign` FOREIGN KEY (`to_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_store_history`
--

LOCK TABLES `employee_store_history` WRITE;
/*!40000 ALTER TABLE `employee_store_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_store_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `current_store_id` bigint unsigned DEFAULT NULL,
  `employee_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_card_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '加密存储',
  `gender` tinyint DEFAULT NULL COMMENT '1:男 2:女',
  `birth_date` date DEFAULT NULL,
  `education` tinyint DEFAULT NULL COMMENT '1:初中及以下 2:高中/中专 3:大专 4:本科 5:研究生',
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_level` tinyint NOT NULL DEFAULT '1' COMMENT '1:店员 2:主管 3:店长 4:区域 5:总部',
  `hire_date` date DEFAULT NULL,
  `contract_expire_date` date DEFAULT NULL,
  `probation_end_date` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:试用期 2:正式 3:离职 4:暂停',
  `resign_date` date DEFAULT NULL,
  `resign_reason` text COLLATE utf8mb4_unicode_ci,
  `base_salary` decimal(10,2) DEFAULT NULL,
  `emergency_contact` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `skills` json DEFAULT NULL COMMENT '技能标签',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employees_user_id_foreign` (`user_id`),
  KEY `employees_organization_id_status_index` (`organization_id`,`status`),
  KEY `employees_current_store_id_status_index` (`current_store_id`,`status`),
  KEY `employees_phone_index` (`phone`),
  KEY `employees_employee_no_index` (`employee_no`),
  CONSTRAINT `employees_current_store_id_foreign` FOREIGN KEY (`current_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_cogs` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否属于销售成本',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_categories_parent_id_foreign` (`parent_id`),
  KEY `expense_categories_organization_id_parent_id_index` (`organization_id`,`parent_id`),
  CONSTRAINT `expense_categories_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expense_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (1,1,NULL,'原材料采购','RAW',1,1,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(2,1,NULL,'水电费','UTIL',0,2,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(3,1,NULL,'人工费用','LAB',0,3,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(4,1,NULL,'耗材物料','SUP',0,4,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(5,1,NULL,'租金','RENT',0,5,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL);
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned NOT NULL,
  `expense_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `input_method` tinyint NOT NULL DEFAULT '1' COMMENT '1:手动录入 2:AI录入 3:系统自动',
  `ai_session_message_id` bigint unsigned DEFAULT NULL,
  `attachment_urls` json DEFAULT NULL COMMENT '凭证附件URL数组',
  `vendor_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `payment_method` tinyint NOT NULL DEFAULT '1' COMMENT '1:现金 2:转账 3:微信支付 4:支付宝 5:企业网银',
  `payment_status` tinyint NOT NULL DEFAULT '1' COMMENT '1:待支付 2:已支付 3:已报销',
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expenses_expense_no_unique` (`expense_no`),
  KEY `expenses_ai_session_message_id_foreign` (`ai_session_message_id`),
  KEY `expenses_created_by_foreign` (`created_by`),
  KEY `expenses_approved_by_foreign` (`approved_by`),
  KEY `expenses_store_id_expense_date_index` (`store_id`,`expense_date`),
  KEY `expenses_category_id_expense_date_index` (`category_id`,`expense_date`),
  KEY `expenses_payment_status_index` (`payment_status`),
  KEY `expenses_supplier_id_index` (`supplier_id`),
  CONSTRAINT `expenses_ai_session_message_id_foreign` FOREIGN KEY (`ai_session_message_id`) REFERENCES `ai_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,2,1,'EXP-XWH-001',3850.00,'2026-03-15',NULL,1,NULL,NULL,'新鲜直送农场',NULL,1,2,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(2,2,1,'EXP-XWH-002',2200.00,'2026-03-17',NULL,1,NULL,NULL,'港鲜肉类批发',NULL,1,2,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(3,2,2,'EXP-XWH-003',680.00,'2026-03-19',NULL,1,NULL,NULL,'中华电力',NULL,3,2,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(4,2,4,'EXP-XWH-004',320.50,'2026-03-20',NULL,1,NULL,NULL,'包装袋/托盘',NULL,1,2,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(5,2,1,'EXP-XWH-005',4100.00,'2026-03-21',NULL,1,NULL,NULL,'新鲜直送农场',NULL,2,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(6,3,1,'EXP-WCH-001',4500.00,'2026-03-16',NULL,1,NULL,NULL,'新鲜直送农场',NULL,1,2,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(7,3,1,'EXP-WCH-002',3100.00,'2026-03-18',NULL,1,NULL,NULL,'南海水产行',NULL,1,2,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(8,3,2,'EXP-WCH-003',750.00,'2026-03-19',NULL,1,NULL,NULL,'港灯',NULL,3,2,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(9,3,3,'EXP-WCH-004',8800.00,'2026-03-21',NULL,1,NULL,NULL,'3月上半月工资',NULL,2,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(10,3,5,'EXP-WCH-005',12000.00,'2026-03-21',NULL,1,NULL,NULL,'3月租金',NULL,2,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL);
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_monthly_summary`
--

DROP TABLE IF EXISTS `financial_monthly_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_monthly_summary` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `year` year NOT NULL,
  `month` tinyint NOT NULL,
  `total_revenue` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_cogs` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_profit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_profit_rate` decimal(6,4) DEFAULT NULL,
  `total_operating_expense` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_profit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_waste_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `avg_inventory_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `inventory_turnover` decimal(8,4) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_monthly_summary_store_id_year_month_unique` (`store_id`,`year`,`month`),
  CONSTRAINT `financial_monthly_summary_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_monthly_summary`
--

LOCK TABLES `financial_monthly_summary` WRITE;
/*!40000 ALTER TABLE `financial_monthly_summary` DISABLE KEYS */;
/*!40000 ALTER TABLE `financial_monthly_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `intelligence_reports`
--

DROP TABLE IF EXISTS `intelligence_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `intelligence_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `report_period_start` date NOT NULL,
  `report_period_end` date NOT NULL,
  `report_type` tinyint NOT NULL DEFAULT '1' COMMENT '1:周报 2:月报 3:专项分析',
  `price_gap_summary` json DEFAULT NULL,
  `hot_products_summary` json DEFAULT NULL,
  `ai_insights` text COLLATE utf8mb4_unicode_ci,
  `action_recommendations` json DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_auto_generated` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `intelligence_reports_store_id_foreign` (`store_id`),
  KEY `ir_org_type_period` (`organization_id`,`report_type`,`report_period_start`),
  CONSTRAINT `intelligence_reports_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `intelligence_reports_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `intelligence_reports`
--

LOCK TABLES `intelligence_reports` WRITE;
/*!40000 ALTER TABLE `intelligence_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `intelligence_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `current_qty` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT '当前库存量',
  `available_qty` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT '可用库存',
  `locked_qty` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT '锁定量（已下单未入库）',
  `avg_cost` decimal(12,4) NOT NULL DEFAULT '0.0000' COMMENT '移动加权平均成本',
  `last_in_at` timestamp NULL DEFAULT NULL,
  `last_out_at` timestamp NULL DEFAULT NULL,
  `last_counted_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_store_id_product_id_unique` (`store_id`,`product_id`),
  KEY `inventory_product_id_foreign` (`product_id`),
  KEY `inventory_store_id_current_qty_index` (`store_id`,`current_qty`),
  CONSTRAINT `inventory_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT INTO `inventory` VALUES (1,2,1,85.000,85.000,0.000,1.2000,'2026-03-20 07:02:06',NULL,NULL,'2026-03-22 08:02:06'),(2,2,2,42.000,42.000,0.000,3.5000,'2026-03-20 05:02:06',NULL,NULL,'2026-03-22 08:02:06'),(3,2,3,120.000,120.000,0.000,0.8000,'2026-03-20 09:02:06',NULL,NULL,'2026-03-22 08:02:06'),(4,2,4,60.000,60.000,0.000,2.5000,'2026-03-20 10:02:06',NULL,NULL,'2026-03-22 08:02:06'),(5,2,5,38.000,38.000,0.000,2.8000,'2026-03-20 08:02:06',NULL,NULL,'2026-03-22 08:02:06'),(6,2,6,95.000,95.000,0.000,1.0000,'2026-03-20 03:02:06',NULL,NULL,'2026-03-22 08:02:06'),(7,2,7,70.000,70.000,0.000,1.5000,'2026-03-20 18:02:06',NULL,NULL,'2026-03-22 08:02:06'),(8,2,8,55.000,55.000,0.000,4.5000,'2026-03-21 21:02:06',NULL,NULL,'2026-03-22 08:02:06'),(9,2,9,40.000,40.000,0.000,3.2000,'2026-03-21 01:02:06',NULL,NULL,'2026-03-22 08:02:06'),(10,2,10,30.000,30.000,0.000,3.8000,'2026-03-21 03:02:06',NULL,NULL,'2026-03-22 08:02:06'),(11,2,11,25.000,25.000,0.000,18.0000,'2026-03-21 07:02:06',NULL,NULL,'2026-03-22 08:02:06'),(12,2,12,18.000,18.000,0.000,12.5000,'2026-03-21 12:02:06',NULL,NULL,'2026-03-22 08:02:06'),(13,2,13,10.000,10.000,0.000,55.0000,'2026-03-21 22:02:06',NULL,NULL,'2026-03-22 08:02:06'),(14,2,14,15.000,15.000,0.000,22.0000,'2026-03-21 02:02:06',NULL,NULL,'2026-03-22 08:02:06'),(15,2,15,12.000,12.000,0.000,28.0000,'2026-03-21 09:02:06',NULL,NULL,'2026-03-22 08:02:06'),(16,2,16,8.000,8.000,0.000,65.0000,'2026-03-20 01:02:06',NULL,NULL,'2026-03-22 08:02:06'),(17,2,17,20.000,20.000,0.000,12.0000,'2026-03-21 15:02:06',NULL,NULL,'2026-03-22 08:02:06'),(18,2,18,30.000,30.000,0.000,2.5000,'2026-03-20 20:02:06',NULL,NULL,'2026-03-22 08:02:06'),(19,2,19,25.000,25.000,0.000,1.8000,'2026-03-21 14:02:06',NULL,NULL,'2026-03-22 08:02:06'),(20,2,20,200.000,200.000,0.000,2.2000,'2026-03-21 00:02:06',NULL,NULL,'2026-03-22 08:02:06'),(21,2,21,12.000,12.000,0.000,68.0000,'2026-03-20 03:02:06',NULL,NULL,'2026-03-22 08:02:06'),(22,3,1,60.000,60.000,0.000,1.2000,'2026-03-20 13:02:06',NULL,NULL,'2026-03-22 08:02:06'),(23,3,2,35.000,35.000,0.000,3.5000,'2026-03-20 01:02:06',NULL,NULL,'2026-03-22 08:02:06'),(24,3,3,90.000,90.000,0.000,0.8000,'2026-03-20 07:02:06',NULL,NULL,'2026-03-22 08:02:06'),(25,3,4,45.000,45.000,0.000,2.5000,'2026-03-20 00:02:06',NULL,NULL,'2026-03-22 08:02:06'),(26,3,5,28.000,28.000,0.000,2.8000,'2026-03-20 19:02:06',NULL,NULL,'2026-03-22 08:02:06'),(27,3,6,5.000,5.000,0.000,1.0000,'2026-03-21 10:02:06',NULL,NULL,'2026-03-22 08:02:06'),(28,3,7,50.000,50.000,0.000,1.5000,'2026-03-21 00:02:06',NULL,NULL,'2026-03-22 08:02:06'),(29,3,8,70.000,70.000,0.000,4.5000,'2026-03-20 21:02:06',NULL,NULL,'2026-03-22 08:02:06'),(30,3,9,3.000,3.000,0.000,3.2000,'2026-03-21 17:02:06',NULL,NULL,'2026-03-22 08:02:06'),(31,3,10,55.000,55.000,0.000,3.8000,'2026-03-20 06:02:06',NULL,NULL,'2026-03-22 08:02:06'),(32,3,11,30.000,30.000,0.000,18.0000,'2026-03-20 15:02:06',NULL,NULL,'2026-03-22 08:02:06'),(33,3,12,22.000,22.000,0.000,12.5000,'2026-03-20 20:02:06',NULL,NULL,'2026-03-22 08:02:06'),(34,3,13,8.000,8.000,0.000,55.0000,'2026-03-20 09:02:06',NULL,NULL,'2026-03-22 08:02:06'),(35,3,14,18.000,18.000,0.000,22.0000,'2026-03-21 18:02:06',NULL,NULL,'2026-03-22 08:02:06'),(36,3,15,15.000,15.000,0.000,28.0000,'2026-03-21 22:02:06',NULL,NULL,'2026-03-22 08:02:06'),(37,3,16,12.000,12.000,0.000,65.0000,'2026-03-20 19:02:06',NULL,NULL,'2026-03-22 08:02:06'),(38,3,17,25.000,25.000,0.000,12.0000,'2026-03-20 10:02:06',NULL,NULL,'2026-03-22 08:02:06'),(39,3,18,40.000,40.000,0.000,2.5000,'2026-03-21 20:02:06',NULL,NULL,'2026-03-22 08:02:06'),(40,3,19,18.000,18.000,0.000,1.8000,'2026-03-21 04:02:06',NULL,NULL,'2026-03-22 08:02:06'),(41,3,20,150.000,150.000,0.000,2.2000,'2026-03-21 02:02:06',NULL,NULL,'2026-03-22 08:02:06'),(42,3,21,8.000,8.000,0.000,68.0000,'2026-03-21 19:02:06',NULL,NULL,'2026-03-22 08:02:06');
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_count_items`
--

DROP TABLE IF EXISTS `inventory_count_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_count_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `count_sheet_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `system_qty` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT '系统账面数量',
  `counted_qty` decimal(10,3) DEFAULT NULL COMMENT '实盘数量',
  `variance_qty` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT '差异量',
  `unit_cost` decimal(12,4) DEFAULT NULL,
  `variance_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `variance_reason` tinyint DEFAULT NULL COMMENT '1:损耗 2:盗损 3:录入错误 4:其他',
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `counted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_count_items_counted_by_foreign` (`counted_by`),
  KEY `inventory_count_items_count_sheet_id_index` (`count_sheet_id`),
  KEY `inventory_count_items_product_id_count_sheet_id_index` (`product_id`,`count_sheet_id`),
  CONSTRAINT `inventory_count_items_count_sheet_id_foreign` FOREIGN KEY (`count_sheet_id`) REFERENCES `inventory_count_sheets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_count_items_counted_by_foreign` FOREIGN KEY (`counted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_count_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_count_items`
--

LOCK TABLES `inventory_count_items` WRITE;
/*!40000 ALTER TABLE `inventory_count_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_count_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_count_sheets`
--

DROP TABLE IF EXISTS `inventory_count_sheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_count_sheets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `sheet_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count_type` tinyint NOT NULL DEFAULT '1' COMMENT '1:全盘 2:部分盘 3:日常抽盘',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:待盘点 2:盘点中 3:待审核 4:已完成 5:已取消',
  `planned_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `total_variance_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_count_sheets_sheet_no_unique` (`sheet_no`),
  KEY `inventory_count_sheets_approved_by_foreign` (`approved_by`),
  KEY `inventory_count_sheets_created_by_foreign` (`created_by`),
  KEY `inventory_count_sheets_store_id_status_index` (`store_id`,`status`),
  CONSTRAINT `inventory_count_sheets_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_count_sheets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_count_sheets_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_count_sheets`
--

LOCK TABLES `inventory_count_sheets` WRITE;
/*!40000 ALTER TABLE `inventory_count_sheets` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_count_sheets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_transactions`
--

DROP TABLE IF EXISTS `inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `transaction_type` tinyint NOT NULL COMMENT '1:采购入库 2:销售出库 3:损耗 4:盘点调整 5:促销出库 6:调拨入 7:调拨出 8:退货入库',
  `qty_change` decimal(10,3) NOT NULL COMMENT '变动量（正入负出）',
  `qty_before` decimal(10,3) NOT NULL,
  `qty_after` decimal(10,3) NOT NULL,
  `unit_cost` decimal(12,4) DEFAULT NULL,
  `total_cost` decimal(12,2) DEFAULT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '关联单据类型',
  `reference_id` bigint unsigned DEFAULT NULL COMMENT '关联单据ID',
  `batch_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL COMMENT '批次到期日',
  `operator_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `inventory_transactions_product_id_foreign` (`product_id`),
  KEY `inventory_transactions_operator_id_foreign` (`operator_id`),
  KEY `inv_tx_store_product_date` (`store_id`,`product_id`,`created_at`),
  KEY `inv_tx_store_type_date` (`store_id`,`transaction_type`,`created_at`),
  KEY `inv_tx_reference` (`reference_type`,`reference_id`),
  KEY `inventory_transactions_expiry_date_index` (`expiry_date`),
  CONSTRAINT `inventory_transactions_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transactions_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_transactions`
--

LOCK TABLES `inventory_transactions` WRITE;
/*!40000 ALTER TABLE `inventory_transactions` DISABLE KEYS */;
INSERT INTO `inventory_transactions` VALUES (1,2,1,1,85.000,0.000,85.000,1.2000,102.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 19:02:06'),(2,2,2,1,42.000,0.000,42.000,3.5000,147.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 11:02:06'),(3,2,3,1,120.000,0.000,120.000,0.8000,96.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 01:02:06'),(4,2,4,1,60.000,0.000,60.000,2.5000,150.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 11:02:06'),(5,2,5,1,38.000,0.000,38.000,2.8000,106.40,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 03:02:06'),(6,2,6,1,95.000,0.000,95.000,1.0000,95.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 07:02:06'),(7,2,7,1,70.000,0.000,70.000,1.5000,105.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 18:02:06'),(8,2,8,1,55.000,0.000,55.000,4.5000,247.50,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 00:02:06'),(9,2,9,1,40.000,0.000,40.000,3.2000,128.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 13:02:06'),(10,2,10,1,30.000,0.000,30.000,3.8000,114.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 07:02:06'),(11,2,11,1,25.000,0.000,25.000,18.0000,450.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 18:02:06'),(12,2,12,1,18.000,0.000,18.000,12.5000,225.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 19:02:06'),(13,2,13,1,10.000,0.000,10.000,55.0000,550.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 02:02:06'),(14,2,14,1,15.000,0.000,15.000,22.0000,330.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 15:02:06'),(15,2,15,1,12.000,0.000,12.000,28.0000,336.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 07:02:06'),(16,2,16,1,8.000,0.000,8.000,65.0000,520.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 21:02:06'),(17,2,17,1,20.000,0.000,20.000,12.0000,240.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 07:02:06'),(18,2,18,1,30.000,0.000,30.000,2.5000,75.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 00:02:06'),(19,2,19,1,25.000,0.000,25.000,1.8000,45.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 10:02:06'),(20,2,20,1,200.000,0.000,200.000,2.2000,440.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 16:02:06'),(21,2,21,1,12.000,0.000,12.000,68.0000,816.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 15:02:06'),(22,3,1,1,60.000,0.000,60.000,1.2000,72.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 22:02:06'),(23,3,2,1,35.000,0.000,35.000,3.5000,122.50,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 14:02:06'),(24,3,3,1,90.000,0.000,90.000,0.8000,72.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 18:02:06'),(25,3,4,1,45.000,0.000,45.000,2.5000,112.50,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 09:02:06'),(26,3,5,1,28.000,0.000,28.000,2.8000,78.40,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 23:02:06'),(27,3,6,1,5.000,0.000,5.000,1.0000,5.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 15:02:06'),(28,3,7,1,50.000,0.000,50.000,1.5000,75.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 01:02:06'),(29,3,8,1,70.000,0.000,70.000,4.5000,315.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 09:02:06'),(30,3,9,1,3.000,0.000,3.000,3.2000,9.60,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 10:02:06'),(31,3,10,1,55.000,0.000,55.000,3.8000,209.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 01:02:06'),(32,3,11,1,30.000,0.000,30.000,18.0000,540.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 09:02:06'),(33,3,12,1,22.000,0.000,22.000,12.5000,275.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 08:02:06'),(34,3,13,1,8.000,0.000,8.000,55.0000,440.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 18:02:06'),(35,3,14,1,18.000,0.000,18.000,22.0000,396.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 20:02:06'),(36,3,15,1,15.000,0.000,15.000,28.0000,420.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 12:02:06'),(37,3,16,1,12.000,0.000,12.000,65.0000,780.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 08:02:06'),(38,3,17,1,25.000,0.000,25.000,12.0000,300.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 12:02:06'),(39,3,18,1,40.000,0.000,40.000,2.5000,100.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 06:02:06'),(40,3,19,1,18.000,0.000,18.000,1.8000,32.40,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 00:02:06'),(41,3,20,1,150.000,0.000,150.000,2.2000,330.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-20 22:02:06'),(42,3,21,1,8.000,0.000,8.000,68.0000,544.00,'seed',NULL,NULL,NULL,1,'演示初始库存','2026-03-21 17:02:06');
/*!40000 ALTER TABLE `inventory_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `leave_type` tinyint NOT NULL COMMENT '1:事假 2:病假 3:年假 4:婚假 5:产假/陪产假 6:其他',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(4,1) NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:待审批 2:已批准 3:已拒绝 4:已撤销',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `reject_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leave_requests_approved_by_foreign` (`approved_by`),
  KEY `leave_requests_employee_id_status_index` (`employee_id`,`status`),
  KEY `leave_requests_store_id_start_date_index` (`store_id`,`start_date`),
  CONSTRAINT `leave_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2025_11_29_220023_create_personal_access_tokens_table',1),(5,'2025_11_29_220222_create_posts_table',1),(6,'2026_03_16_000001_create_organizations_table',2),(7,'2026_03_16_000002_create_regions_table',2),(8,'2026_03_16_000003_create_stores_table',2),(9,'2026_03_16_000004_create_roles_permissions_tables',2),(10,'2026_03_16_000005_create_saas_integrations_tables',2),(11,'2026_03_16_000006_create_product_categories_table',2),(12,'2026_03_16_000007_create_products_tables',2),(14,'2026_03_16_000008_create_suppliers_tables',3),(15,'2026_03_16_000009_create_inventory_tables',3),(16,'2026_03_16_000010_create_ai_assistant_tables',3),(17,'2026_03_16_000011_create_ai_forecast_tables',3),(18,'2026_03_16_000012_create_promotion_tables',3),(19,'2026_03_16_000013_create_competitor_tables',4),(20,'2026_03_16_000014_create_finance_tables',4),(21,'2026_03_16_000015_create_hr_tables',4),(22,'2026_03_16_000016_create_dashboard_report_tables',4),(23,'2026_03_22_070800_add_is_admin_to_users_table',5),(24,'2026_03_22_100000_create_resumes_table',6),(25,'2026_03_22_082441_add_supplier_id_to_products_table',7);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL COMMENT '系统配置：功能开关、AI参数全局默认值',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organizations_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizations`
--

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT INTO `organizations` VALUES (1,'舌尖香港','STJXG',NULL,NULL,NULL,'2026-03-22 06:54:14','2026-03-22 06:54:14',NULL);
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模块标识',
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '如 inventory.product.create',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` tinyint NOT NULL DEFAULT '2' COMMENT '1:菜单 2:操作 3:数据',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_code_unique` (`code`),
  KEY `permissions_module_index` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (1,'App\\Models\\User',1,'auth_token','7ccc1389d66d4a7d19ee1c1caf974060542d7809a3e68e3a222bb76f44653445','[\"*\"]','2026-03-21 22:59:53',NULL,'2026-03-21 22:59:53','2026-03-21 22:59:53'),(2,'App\\Models\\User',1,'auth_token','a644b61170ca3ba215bbc804dc4ff27859aa781b24f9e63bfa14f0c74e90e8c9','[\"*\"]','2026-03-21 23:00:01',NULL,'2026-03-21 23:00:01','2026-03-21 23:00:01');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `posts_user_id_foreign` (`user_id`),
  CONSTRAINT `posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `posts`
--

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
INSERT INTO `posts` VALUES (1,1,'Eos et optio ea earum.','Dolor consequatur voluptatem deserunt reprehenderit et officiis. Eum dignissimos architecto sapiente saepe veniam rerum et.\n\nUt ducimus dolorem sed soluta veniam. Ullam sint asperiores qui ullam occaecati magni enim qui. Iure quos fuga et omnis exercitationem repellat maiores impedit.\n\nEt quis quo laboriosam magni et cumque et rerum. Magni ut excepturi minus aut non dicta. Distinctio aut et aut cum.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(2,1,'Aut harum eum nisi voluptatibus explicabo.','Deserunt dolor nihil dolorum temporibus quas. Blanditiis amet suscipit cupiditate autem. Atque ut perferendis quasi sit ipsa ab in omnis. Vel incidunt eos et numquam fuga omnis accusamus.\n\nIn doloribus quaerat quia minima necessitatibus fugiat. Officiis occaecati deleniti qui incidunt. Quas veniam voluptas ex ut magnam. Iste illo rem officia porro eveniet aut.\n\nAdipisci blanditiis soluta eos quidem dolorem consequuntur. Deserunt molestiae voluptatibus aut nam eaque. Velit veritatis harum dolorum quaerat repudiandae.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(3,1,'Est vitae placeat quia iusto magni facilis quia.','Tempora sint nam sunt id nostrum autem ducimus. Consectetur aperiam quam consequatur laborum aut asperiores qui. Laboriosam ipsa laboriosam rerum et et quia dignissimos error. Ullam eum distinctio ullam maiores sunt fuga quam ratione. Amet voluptatibus repellat tempora voluptatem explicabo iusto.\n\nCulpa autem a aperiam aspernatur dolorem eligendi. Veritatis ea non facilis.\n\nEsse repellat soluta autem a placeat rerum. Sed nemo nihil voluptates sunt natus consequatur enim. Rerum voluptas tempore in veritatis et.',0,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(4,1,'Nihil veniam repellat enim dicta rem.','Et fugit autem quasi vel aut asperiores. Omnis eum ut in sint. Beatae quidem et optio expedita assumenda id in. Tempora minus rem dolores eos.\n\nEt voluptas minima qui non vitae rem hic qui. Consequuntur laborum eligendi voluptatem qui. Quia quisquam qui facilis saepe nam. Laboriosam sit quia quis nostrum est sequi.\n\nEt sed voluptatem quis impedit non dolorem eos. Aut deserunt ipsa quia necessitatibus. Voluptate sit repellat aut perspiciatis ut voluptatem laboriosam. Itaque sunt qui eos amet.',0,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(5,1,'Laborum optio quia cupiditate aut magni quasi aliquam.','Distinctio hic accusantium consequatur soluta illo libero. Voluptatum pariatur nisi qui quo sed aperiam quia. Quisquam sit occaecati perspiciatis non temporibus ea.\n\nDoloremque consequatur omnis soluta voluptas. Atque cupiditate et quis enim optio et autem. Quam esse perspiciatis consectetur possimus facilis est asperiores ea. Voluptatum natus quis velit cupiditate.\n\nItaque culpa necessitatibus doloribus architecto. Ab sunt libero numquam assumenda. Voluptas fuga omnis ea itaque.',0,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(6,2,'Porro impedit magnam placeat molestiae rerum molestiae omnis.','Voluptatibus non possimus quasi sunt maxime aut reiciendis. Dolor est placeat et similique. Facilis officiis vel vel iste at consequatur.\n\nExercitationem sit quia itaque voluptatem sit ea laudantium. Quibusdam similique est itaque et quasi dicta. Eos error sint repellat nihil occaecati soluta.\n\nVoluptatem facere cumque voluptatem suscipit dolore assumenda. Suscipit corporis cum enim. Voluptas ex eligendi id et qui enim. Magnam sunt et qui. Ea error perferendis est qui.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(7,2,'Eligendi qui nihil ullam repellat incidunt quos.','Blanditiis a aut cumque optio ut repellendus explicabo neque. Tenetur quo esse quia consequuntur. Nisi laudantium quia ut quasi dolorem aut eveniet. Voluptatem et quia voluptatibus.\n\nIpsam eius velit iste repellendus unde ut aut. Beatae vero dolor quia itaque fugiat facilis et doloribus. Culpa optio eius id nulla deserunt corrupti animi. Non porro quos animi.\n\nConsequatur deleniti atque porro. Occaecati magni dolores architecto labore saepe. Repellat omnis perspiciatis et veritatis nesciunt facere ea.',0,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(8,2,'Ducimus molestiae voluptatem quibusdam quis et quasi officia.','Pariatur sit optio perferendis. Ut aliquid dignissimos aut sint aut. Reiciendis fuga quia earum error accusamus impedit. Natus facere nesciunt in corrupti tempora fugiat.\n\nIure corporis error in. Dolores quis et consectetur iusto. Numquam sequi sit consectetur voluptas voluptatum possimus.\n\nAccusantium id non in pariatur totam. Recusandae est quisquam aperiam totam quas magni ut et. Nisi eius ipsum corrupti accusantium cum amet. Aut praesentium tempore sequi eaque. Tenetur laborum ex culpa et consequatur.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(9,3,'Quibusdam consequatur consectetur corporis.','Rerum iusto nihil sit sunt qui fugit. Dolore maiores est distinctio.\n\nQuia officia atque error ad ea. Laudantium alias totam voluptas. Voluptate earum provident non maxime tempora voluptas ducimus.\n\nSint est recusandae voluptas hic quia consequatur. Sed suscipit nisi eligendi aspernatur molestiae et aut. Quasi alias et odio ipsa placeat labore amet. Repellat sit temporibus odio et aliquid iusto sed excepturi.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(10,3,'Numquam nam voluptatem esse veniam nemo at et.','Sed voluptatem et illo corporis et eaque inventore. Esse possimus libero repellendus libero. Eaque quisquam quod rerum temporibus ipsam veniam in.\n\nPerferendis est assumenda omnis in quo excepturi. A natus odit quia facilis. Officiis architecto vel vitae nihil sunt.\n\nNumquam expedita deserunt aut illum quo totam rerum. Iste eveniet vel mollitia enim. Et et facere quidem. Tenetur et dolorum repellendus quo dolor dolores. Quas ea qui quibusdam alias modi consequatur ut.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(11,4,'Voluptas et distinctio aperiam quos eligendi dolore.','Consequatur quisquam fugiat dolor aliquid. Quos labore molestias cumque voluptas eaque. Voluptas aut doloremque accusantium. Cupiditate ullam voluptates id et ullam.\n\nAut repellat et accusamus dicta. Dicta et et aliquid illo. Est quasi rerum rerum aliquid aliquam.\n\nConsequatur provident et autem omnis aut dignissimos incidunt ut. Dicta consequuntur quisquam quam nostrum dolorum magni aliquid adipisci. Similique et et eos iusto aut. Possimus voluptatem et eos qui soluta.',0,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(12,4,'Voluptatum quos repudiandae corporis laboriosam dolores sed voluptatem rem.','Pariatur eaque non dolores iure. Nihil et vitae enim rem sint.\n\nVoluptatem cupiditate tempore ea aut amet earum. A sequi architecto fuga voluptate. Recusandae labore sapiente repudiandae ducimus.\n\nQuisquam architecto deserunt tenetur ipsa eum. Quam id corrupti ipsum assumenda totam repellendus. Velit minus sunt sequi aut voluptatibus. Id eum eius sit inventore.',0,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(13,5,'Velit molestiae dolorem adipisci impedit illo nisi vel.','Aut iusto cum alias sapiente. Nostrum quaerat ipsum inventore est magnam. Mollitia facere unde aut at et sunt. Rerum quasi aut repudiandae omnis impedit voluptatem.\n\nAmet delectus eum voluptas rerum fugiat aspernatur. Sint perferendis a reiciendis adipisci dolorem beatae. Qui pariatur iusto explicabo voluptas unde est.\n\nError maxime quae est quos porro qui. Quis laudantium odit temporibus corporis explicabo aliquam expedita quis.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(14,5,'Quia nisi ut sit reprehenderit qui non distinctio.','Error nihil dolorem rerum et necessitatibus veritatis porro voluptates. Aspernatur ipsum et esse inventore et repudiandae sunt. Id doloribus laboriosam expedita dolore esse.\n\nRepellat ab eius voluptas. Qui reiciendis temporibus numquam dignissimos. Quia labore eveniet voluptatem molestias. Quisquam praesentium hic nobis dignissimos qui.\n\nEos sed doloremque consequuntur neque ab quia. Natus eaque et voluptatem atque assumenda. Voluptas et ab aut sapiente temporibus non. Cupiditate et accusantium quaerat corporis non.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(15,5,'Eum ab rerum est iure quia officia.','Qui nulla porro est laborum accusantium sint. Maiores perspiciatis et laudantium error. Officiis odit magni hic repellat. Ipsa est aliquid eos sequi quae fugit odio.\n\nCum et enim incidunt dignissimos earum sunt. Nesciunt sint occaecati est quo eveniet nostrum sunt. Voluptatem vero similique eius rerum omnis suscipit atque. Fugiat iste autem neque illum omnis accusamus alias voluptatem.\n\nDolorem nam impedit omnis nam aut est. Rem eaque qui deleniti ex. At magni quidem tempora quos.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(16,5,'Dolore rerum provident consequatur eius ratione amet.','Amet excepturi ut consequatur eligendi ut sint. Ratione odit non eum deleniti. Sed possimus eius quisquam.\n\nEst nisi quod non quia sapiente. Totam minima deserunt repellat quos et perspiciatis. Totam earum sunt omnis ut iusto et. Voluptate qui neque id sequi.\n\nQuae sunt vel aperiam ex laudantium esse qui. Nobis explicabo qui fuga. Quam neque quas et quidem ut eum rem vero. Quae ullam quia nam itaque.',0,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(17,6,'Ducimus saepe aut occaecati facilis doloremque.','Repudiandae minus aspernatur voluptatem natus perspiciatis enim non dolor. Laudantium dolore ut necessitatibus in quis provident. Molestiae qui sed ut dolor est similique porro. Earum eos vel est corrupti.\n\nPossimus ea sint rerum vero animi quaerat quibusdam. Sed animi nostrum atque sit molestiae quaerat dolorum. Nostrum omnis dolorem temporibus voluptate rerum.\n\nVoluptatem consequatur consectetur culpa est dolore. Qui officiis excepturi explicabo eveniet placeat error aspernatur.',1,'2026-03-15 07:56:52','2026-03-15 07:56:52'),(18,6,'Sunt delectus molestiae quae suscipit.','Qui aut quo a eligendi dolorem illum excepturi beatae. Vero magni dolor accusantium veritatis dolorem sed.\n\nAut nulla ipsum eligendi debitis. Reprehenderit voluptatibus labore nisi eligendi sunt sit consequatur. Natus natus expedita beatae odio. Illum qui exercitationem qui.\n\nAut veniam reiciendis totam. Et soluta distinctio libero. Nostrum quos ea est quaerat repellat. Ullam inventore quis sed quos quasi adipisci.',0,'2026-03-15 07:56:52','2026-03-15 07:56:52');
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `icon_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_categories_parent_id_foreign` (`parent_id`),
  KEY `product_categories_organization_id_parent_id_index` (`organization_id`,`parent_id`),
  CONSTRAINT `product_categories_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (1,1,NULL,'蔬菜','VEG',1,NULL,'2026-03-22 00:02:05','2026-03-22 00:02:05',NULL),(2,1,NULL,'水果','FRT',2,NULL,'2026-03-22 00:02:05','2026-03-22 00:02:05',NULL),(3,1,NULL,'肉类','MEAT',3,NULL,'2026-03-22 00:02:05','2026-03-22 00:02:05',NULL),(4,1,NULL,'水产','SEA',4,NULL,'2026-03-22 00:02:05','2026-03-22 00:02:05',NULL),(5,1,NULL,'豆制品','TOFU',5,NULL,'2026-03-22 00:02:05','2026-03-22 00:02:05',NULL),(6,1,NULL,'干货','DRY',6,NULL,'2026-03-22 00:02:05','2026-03-22 00:02:05',NULL);
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SKU编码',
  `barcode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '基本单位：斤/个/箱',
  `spec` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '规格描述：500g/袋',
  `image_urls` json DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `shelf_life_days` int DEFAULT NULL COMMENT '保质期天数',
  `storage_condition` tinyint NOT NULL DEFAULT '1' COMMENT '1:常温 2:冷藏 3:冷冻',
  `is_fresh` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否生鲜品',
  `min_order_qty` decimal(10,3) NOT NULL DEFAULT '1.000' COMMENT '最小采购量',
  `purchase_unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '采购单位：箱',
  `purchase_unit_qty` decimal(10,3) DEFAULT NULL COMMENT '采购单位含基本单位数量',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0:下架 1:正常 2:待审核',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_created_by_foreign` (`created_by`),
  KEY `products_organization_id_status_index` (`organization_id`,`status`),
  KEY `products_category_id_index` (`category_id`),
  KEY `products_barcode_index` (`barcode`),
  KEY `products_code_index` (`code`),
  KEY `products_supplier_id_foreign` (`supplier_id`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,1,1,'胡萝卜',NULL,NULL,NULL,'斤',NULL,NULL,NULL,14,1,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(2,1,1,1,'西兰花',NULL,NULL,NULL,'斤',NULL,NULL,NULL,5,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(3,1,1,1,'白菜',NULL,NULL,NULL,'斤',NULL,NULL,NULL,7,1,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(4,1,1,1,'番茄',NULL,NULL,NULL,'斤',NULL,NULL,NULL,7,1,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(5,1,1,1,'青椒',NULL,NULL,NULL,'斤',NULL,NULL,NULL,7,1,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(6,1,1,1,'土豆',NULL,NULL,NULL,'斤',NULL,NULL,NULL,30,1,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(7,1,1,1,'洋葱',NULL,NULL,NULL,'斤',NULL,NULL,NULL,30,1,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(8,1,2,1,'苹果',NULL,NULL,NULL,'斤',NULL,NULL,NULL,14,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(9,1,2,1,'香蕉',NULL,NULL,NULL,'斤',NULL,NULL,NULL,5,1,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(10,1,2,1,'橙子',NULL,NULL,NULL,'斤',NULL,NULL,NULL,14,1,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(11,1,3,2,'猪五花肉',NULL,NULL,NULL,'斤',NULL,NULL,NULL,3,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(12,1,3,2,'鸡胸肉',NULL,NULL,NULL,'斤',NULL,NULL,NULL,3,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(13,1,3,2,'牛腩',NULL,NULL,NULL,'斤',NULL,NULL,NULL,3,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(14,1,3,2,'猪排骨',NULL,NULL,NULL,'斤',NULL,NULL,NULL,3,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(15,1,4,3,'鲈鱼',NULL,NULL,NULL,'斤',NULL,NULL,NULL,1,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:05','2026-03-22 00:25:36',NULL),(16,1,4,3,'基围虾',NULL,NULL,NULL,'斤',NULL,NULL,NULL,1,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:06','2026-03-22 00:25:36',NULL),(17,1,4,3,'花蛤',NULL,NULL,NULL,'斤',NULL,NULL,NULL,1,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:06','2026-03-22 00:25:36',NULL),(18,1,5,1,'豆腐',NULL,NULL,NULL,'块',NULL,NULL,NULL,3,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:06','2026-03-22 00:25:36',NULL),(19,1,5,1,'豆芽',NULL,NULL,NULL,'斤',NULL,NULL,NULL,2,2,1,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:06','2026-03-22 00:25:36',NULL),(20,1,6,1,'大米',NULL,NULL,NULL,'斤',NULL,NULL,NULL,365,1,0,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:06','2026-03-22 00:25:36',NULL),(21,1,6,1,'食用油',NULL,NULL,NULL,'桶',NULL,NULL,NULL,540,1,0,1.000,NULL,NULL,1,NULL,'2026-03-22 00:02:06','2026-03-22 00:25:36',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_items`
--

DROP TABLE IF EXISTS `promotion_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `original_price` decimal(12,2) NOT NULL,
  `promotion_price` decimal(12,2) NOT NULL,
  `discount_rate` decimal(5,4) DEFAULT NULL,
  `ai_suggested_price` decimal(12,2) DEFAULT NULL,
  `cost_price` decimal(12,2) DEFAULT NULL COMMENT '成本价（防止亏本）',
  `stock_qty_at_start` decimal(10,3) DEFAULT NULL,
  `target_clear_qty` decimal(10,3) DEFAULT NULL,
  `actual_sold_qty` decimal(10,3) NOT NULL DEFAULT '0.000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promotion_items_product_id_foreign` (`product_id`),
  KEY `promotion_items_promotion_id_index` (`promotion_id`),
  CONSTRAINT `promotion_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_items_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_items`
--

LOCK TABLES `promotion_items` WRITE;
/*!40000 ALTER TABLE `promotion_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_reviews`
--

DROP TABLE IF EXISTS `promotion_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `total_revenue` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_profit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_profit_rate` decimal(6,4) DEFAULT NULL,
  `clear_rate` decimal(5,4) DEFAULT NULL COMMENT '清货率',
  `waste_amount_prevented` decimal(12,2) NOT NULL DEFAULT '0.00',
  `customer_traffic_change` decimal(6,4) DEFAULT NULL COMMENT '客流变化率',
  `ai_effectiveness_score` decimal(5,2) DEFAULT NULL,
  `lessons_learned` text COLLATE utf8mb4_unicode_ci,
  `recommendations` json DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `promotion_reviews_promotion_id_foreign` (`promotion_id`),
  KEY `promotion_reviews_store_id_index` (`store_id`),
  CONSTRAINT `promotion_reviews_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_reviews_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_reviews`
--

LOCK TABLES `promotion_reviews` WRITE;
/*!40000 ALTER TABLE `promotion_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_rules`
--

DROP TABLE IF EXISTS `promotion_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_type` tinyint NOT NULL COMMENT '1:库存阈值 2:临期天数 3:滞销天数 4:手动触发 5:节假日',
  `trigger_condition` json DEFAULT NULL COMMENT '触发条件参数',
  `promotion_type` tinyint NOT NULL COMMENT '1:折扣 2:满减 3:买赠 4:捆绑销售 5:限时特价',
  `pricing_strategy` tinyint NOT NULL DEFAULT '1' COMMENT '1:固定折扣 2:AI动态定价 3:清零定价',
  `max_discount_rate` decimal(5,4) DEFAULT NULL COMMENT '最大折扣率下限',
  `apply_to` tinyint NOT NULL DEFAULT '1' COMMENT '1:全品类 2:指定分类 3:指定商品',
  `apply_target_ids` json DEFAULT NULL,
  `auto_execute` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否自动执行',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promotion_rules_organization_id_is_active_index` (`organization_id`,`is_active`),
  CONSTRAINT `promotion_rules_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_rules`
--

LOCK TABLES `promotion_rules` WRITE;
/*!40000 ALTER TABLE `promotion_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotions`
--

DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `rule_id` bigint unsigned DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_source` tinyint NOT NULL DEFAULT '1' COMMENT '1:AI自动触发 2:店长手动 3:总部下发',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:待审核 2:进行中 3:已暂停 4:已结束 5:已取消',
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `ai_analysis` json DEFAULT NULL COMMENT 'AI触发时的分析快照',
  `total_sales_qty` decimal(10,3) NOT NULL DEFAULT '0.000',
  `total_sales_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_saved_waste_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promotions_rule_id_foreign` (`rule_id`),
  KEY `promotions_created_by_foreign` (`created_by`),
  KEY `promotions_approved_by_foreign` (`approved_by`),
  KEY `promotions_store_id_status_index` (`store_id`,`status`),
  KEY `promotions_store_id_started_at_ended_at_index` (`store_id`,`started_at`,`ended_at`),
  CONSTRAINT `promotions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotions_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `promotion_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotions_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotions`
--

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `forecast_result_id` bigint unsigned DEFAULT NULL,
  `suggested_qty` decimal(10,3) DEFAULT NULL COMMENT 'AI建议采购量',
  `ordered_qty` decimal(10,3) NOT NULL,
  `received_qty` decimal(10,3) NOT NULL DEFAULT '0.000',
  `unit_price` decimal(12,4) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `ai_suggestion_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_forecast_result_id_foreign` (`forecast_result_id`),
  KEY `purchase_order_items_purchase_order_id_index` (`purchase_order_id`),
  KEY `purchase_order_items_product_id_index` (`product_id`),
  CONSTRAINT `purchase_order_items_forecast_result_id_foreign` FOREIGN KEY (`forecast_result_id`) REFERENCES `ai_forecast_results` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `order_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_type` tinyint NOT NULL DEFAULT '1' COMMENT '1:AI建议单 2:手动创建 3:紧急补货',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:草稿 2:待审核 3:已确认 4:配送中 5:已收货 6:已取消',
  `forecast_session_id` bigint unsigned DEFAULT NULL COMMENT '关联预测会话',
  `expected_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_order_no_unique` (`order_no`),
  KEY `purchase_orders_created_by_foreign` (`created_by`),
  KEY `purchase_orders_approved_by_foreign` (`approved_by`),
  KEY `purchase_orders_store_id_status_index` (`store_id`,`status`),
  KEY `purchase_orders_supplier_id_status_index` (`supplier_id`,`status`),
  KEY `purchase_orders_expected_delivery_date_index` (`expected_delivery_date`),
  CONSTRAINT `purchase_orders_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `regions`
--

DROP TABLE IF EXISTS `regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `regions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `regions_parent_id_foreign` (`parent_id`),
  KEY `regions_manager_user_id_foreign` (`manager_user_id`),
  KEY `regions_organization_id_index` (`organization_id`),
  CONSTRAINT `regions_manager_user_id_foreign` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `regions_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `regions_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `regions`
--

LOCK TABLES `regions` WRITE;
/*!40000 ALTER TABLE `regions` DISABLE KEYS */;
INSERT INTO `regions` VALUES (1,1,NULL,'香港总区','HK',NULL,'2026-03-22 06:54:14','2026-03-22 06:54:14',NULL);
/*!40000 ALTER TABLE `regions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `report_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'daily_review, weekly_review, monthly_review, custom',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `data_snapshot` json DEFAULT NULL COMMENT '核心指标数据快照',
  `ai_analysis` text COLLATE utf8mb4_unicode_ci,
  `charts_config` json DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:生成中 2:已完成 3:失败',
  `is_auto_generated` tinyint(1) NOT NULL DEFAULT '1',
  `generated_by` bigint unsigned DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reports_generated_by_foreign` (`generated_by`),
  KEY `reports_store_type_period` (`store_id`,`report_type`,`period_start`),
  KEY `reports_organization_id_report_type_index` (`organization_id`,`report_type`),
  CONSTRAINT `reports_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resumes`
--

DROP TABLE IF EXISTS `resumes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resumes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` tinyint NOT NULL DEFAULT '0' COMMENT '0:未知 1:男 2:女',
  `age` tinyint unsigned DEFAULT NULL,
  `districts` json DEFAULT NULL COMMENT '意向工作区域，如["筲箕湾","柴湾"]',
  `work_types` json DEFAULT NULL COMMENT '工作类型，如["全职","小时工"]',
  `positions` json DEFAULT NULL COMMENT '意向岗位，如["收银员","理货员"]',
  `experience_years` decimal(3,1) DEFAULT NULL COMMENT '工作经验年数',
  `salary_min` int DEFAULT NULL COMMENT '薪资下限',
  `salary_max` int DEFAULT NULL COMMENT '薪资上限',
  `salary_unit` tinyint NOT NULL DEFAULT '1' COMMENT '1:月 2:日 3:小时',
  `education` tinyint DEFAULT NULL COMMENT '1:初中 2:高中 3:大专 4:本科',
  `availability_date` date DEFAULT NULL COMMENT '最早到岗日期',
  `languages` json DEFAULT NULL COMMENT '语言能力，如["粤语","普通话"]',
  `skills` json DEFAULT NULL COMMENT '技能标签，如["生鲜处理","收银"]',
  `raw_text` text COLLATE utf8mb4_unicode_ci COMMENT '原始输入文本',
  `source` tinyint NOT NULL DEFAULT '1' COMMENT '1:手动录入 2:AI解析 3:文件上传',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0:无效 1:求职中 2:已入职 3:暂不求职',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resumes_created_by_foreign` (`created_by`),
  KEY `resumes_organization_id_status_index` (`organization_id`,`status`),
  CONSTRAINT `resumes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `resumes_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resumes`
--

LOCK TABLES `resumes` WRITE;
/*!40000 ALTER TABLE `resumes` DISABLE KEYS */;
INSERT INTO `resumes` VALUES (1,1,'陈大文','91234567',1,28,'[\"筲箕湾\", \"柴湾\", \"西湾河\"]','[\"小时工\", \"兼职\"]','[\"收银员\", \"理货员\"]',3.0,60,75,3,2,NULL,'[\"粤语\", \"普通话\"]','[\"收银\", \"陈列\", \"生鲜处理\"]',NULL,1,1,'随时可上班，有超市工作经验',NULL,'2026-03-21 23:58:17','2026-03-21 23:58:17',NULL),(2,1,'李小红','92345678',2,32,'[\"西湾河\", \"鲗鱼涌\", \"天后\"]','[\"全职\"]','[\"收银员\", \"店务员\"]',5.0,14000,16000,1,2,NULL,'[\"粤语\", \"普通话\", \"英语\"]','[\"收银\", \"客服\", \"POS系统\"]',NULL,1,1,'有零售管理证书',NULL,'2026-03-21 23:58:17','2026-03-21 23:58:17',NULL),(3,1,'王志强','93456789',1,45,'[\"筲箕湾\", \"杏花邨\", \"柴湾\"]','[\"全职\", \"兼职\"]','[\"生鲜切配\", \"仓务员\"]',15.0,16000,20000,1,1,NULL,'[\"粤语\"]','[\"生鲜处理\", \"刀工\", \"冷链管理\", \"叉车\"]',NULL,2,1,'精通各类海鲜及肉类切配，有冷冻仓管理经验',NULL,'2026-03-21 23:58:17','2026-03-21 23:58:17',NULL),(4,1,'张美玲','94567890',2,25,'[\"铜锣湾\", \"天后\", \"北角\"]','[\"兼职\", \"小时工\"]','[\"收银员\", \"理货员\"]',1.0,65,80,3,3,NULL,'[\"粤语\", \"普通话\", \"英语\"]','[\"收银\", \"英语接待\"]',NULL,1,1,'大学在读，只限周末及节假日',NULL,'2026-03-21 23:58:17','2026-03-21 23:58:17',NULL),(5,1,'黄国强','95678901',1,38,'[\"旺角\", \"大角咀\", \"深水埗\"]','[\"全职\"]','[\"店长\", \"副店长\"]',12.0,22000,28000,1,3,NULL,'[\"粤语\", \"普通话\"]','[\"团队管理\", \"库存管理\", \"损耗控制\", \"采购\"]',NULL,1,1,'曾任超市副店长，管理15人团队',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(6,1,'林翠芬','96789012',2,50,'[\"元朗\", \"天水围\", \"屯门\"]','[\"全职\", \"兼职\"]','[\"收银员\", \"清洁员\"]',8.0,12000,14000,1,1,NULL,'[\"粤语\", \"普通话\"]','[\"收银\", \"清洁\", \"勤奋负责\"]',NULL,1,1,'工作态度认真，不介意早班',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(7,1,'吴浩明','97890123',1,22,'[\"观塘\", \"牛头角\", \"九龙湾\"]','[\"小时工\", \"兼职\"]','[\"理货员\", \"仓务员\"]',0.5,60,70,3,2,NULL,'[\"粤语\", \"普通话\"]','[\"体力充沛\", \"驾驶\"]',NULL,2,1,'刚毕业，积极学习中',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(8,1,'郑月娥','98901234',2,41,'[\"沙田\", \"大围\", \"火炭\"]','[\"全职\"]','[\"生鲜销售\", \"收银员\"]',10.0,15000,18000,1,2,NULL,'[\"粤语\", \"普通话\"]','[\"生鲜知识\", \"客户关系\", \"陈列\"]',NULL,1,1,'熟悉各类蔬果生鲜，有固定客户群',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(9,1,'何俊杰','99012345',1,35,'[\"荃湾\", \"葵芳\", \"葵涌\"]','[\"全职\"]','[\"副店长\", \"收银主管\"]',8.0,18000,22000,1,3,NULL,'[\"粤语\", \"普通话\", \"英语\"]','[\"收银主管\", \"排班\", \"库存管理\", \"员工培训\"]',NULL,1,1,'有连锁超市收银主管经验',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(10,1,'曾丽华','90123456',2,29,'[\"将军澳\", \"坑口\", \"宝琳\"]','[\"全职\", \"兼职\"]','[\"收银员\", \"理货员\"]',4.0,13500,15500,1,2,NULL,'[\"粤语\", \"普通话\"]','[\"收银\", \"理货\", \"快速结账\"]',NULL,2,1,'有便利店及超市工作经验，可做早班',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(11,1,'刘伟成','91239876',1,55,'[\"深水埗\", \"长沙湾\", \"荔枝角\"]','[\"全职\", \"兼职\"]','[\"仓务员\", \"搬运工\"]',20.0,14000,16000,1,1,NULL,'[\"粤语\"]','[\"体力好\", \"驾驶货车\", \"叉车证\"]',NULL,1,1,'持有叉车牌及货车驾驶执照',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(12,1,'谢嘉雯','92348765',2,24,'[\"筲箕湾\", \"西湾河\", \"柴湾\"]','[\"小时工\"]','[\"收银员\", \"理货员\"]',1.0,65,75,3,3,NULL,'[\"粤语\", \"普通话\", \"英语\"]','[\"收银\", \"良好服务态度\"]',NULL,2,1,'大学毕业，只做小时工，时间灵活',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(13,1,'邓家豪','93458764',1,30,'[\"大埔\", \"粉岭\", \"上水\"]','[\"全职\"]','[\"生鲜切配\", \"生鲜销售\"]',6.0,17000,20000,1,2,NULL,'[\"粤语\", \"普通话\"]','[\"刀工\", \"海鲜处理\", \"肉类分切\", \"食品安全证书\"]',NULL,1,1,'持有食品安全主任证书',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(14,1,'梁秀珍','94568763',2,47,'[\"元朗\", \"锦田\", \"天水围\"]','[\"兼职\", \"小时工\"]','[\"收银员\", \"清洁员\"]',6.0,60,70,3,1,NULL,'[\"粤语\"]','[\"收银\", \"清洁\", \"勤快\"]',NULL,1,1,'只接受元朗区工作，下午班优先',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(15,1,'陈志豪','95678762',1,27,'[\"屯门\", \"青衣\", \"荃湾\"]','[\"全职\"]','[\"理货员\", \"仓务员\"]',3.0,13000,15000,1,2,NULL,'[\"粤语\", \"普通话\"]','[\"理货\", \"库存盘点\", \"WMS系统\"]',NULL,2,1,'有物流仓库操作经验',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(16,1,'胡淑芳','96788761',2,36,'[\"铜锣湾\", \"湾仔\", \"跑马地\"]','[\"全职\", \"兼职\"]','[\"收银员\", \"客服\"]',9.0,15000,17000,1,3,NULL,'[\"粤语\", \"普通话\", \"英语\"]','[\"收银\", \"英语客服\", \"投诉处理\"]',NULL,1,1,'英文流利，适合旅游区门店',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(17,1,'冯志明','97898760',1,43,'[\"柴湾\", \"筲箕湾\", \"小西湾\"]','[\"全职\"]','[\"生鲜切配\", \"店务员\"]',18.0,18000,22000,1,1,NULL,'[\"粤语\", \"普通话\"]','[\"生鲜全品类\", \"蔬果陈列\", \"损耗管理\"]',NULL,1,1,'生鲜经验丰富，可带新人',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(18,1,'苏嘉怡','98908759',2,21,'[\"旺角\", \"油麻地\", \"佐敦\"]','[\"小时工\", \"兼职\"]','[\"收银员\", \"理货员\"]',0.0,60,68,3,2,NULL,'[\"粤语\", \"普通话\"]','[\"学习快\", \"有责任心\"]',NULL,2,1,'无经验亦可，希望从收银员做起',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(19,1,'许建国','99018758',1,52,'[\"九龙城\", \"土瓜湾\", \"马头围\"]','[\"全职\"]','[\"店长\", \"区域督导\"]',25.0,28000,35000,1,3,NULL,'[\"粤语\", \"普通话\"]','[\"多店管理\", \"人员培训\", \"采购谈判\", \"P&L管理\"]',NULL,1,1,'曾管理生鲜超市5间分店，熟悉连锁运营',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(20,1,'罗美仪','90128757',2,33,'[\"沙田\", \"马鞍山\", \"乌溪沙\"]','[\"全职\"]','[\"收银主管\", \"收银员\"]',7.0,16000,19000,1,2,NULL,'[\"粤语\", \"普通话\"]','[\"收银主管\", \"排班\", \"现金管理\", \"员工培训\"]',NULL,2,1,'有超市收银组长经验，可独立管理收银团队',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(21,1,'杨子龙','91237654',1,19,'[\"筲箕湾\", \"柴湾\", \"西湾河\"]','[\"小时工\"]','[\"理货员\", \"清洁员\"]',0.0,58,65,3,2,NULL,'[\"粤语\", \"普通话\"]','[\"勤力\", \"守时\"]',NULL,1,1,'在学生，只做周末，居住筲箕湾',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(22,1,'邝淑仪','92347653',2,40,'[\"天水围\", \"元朗\", \"屯门\"]','[\"全职\", \"兼职\"]','[\"收银员\", \"店务员\"]',11.0,13000,15000,1,1,NULL,'[\"粤语\", \"普通话\"]','[\"收银\", \"陈列\", \"熟悉新界西\"]',NULL,1,1,'新界西居住，不愿跨区',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(23,1,'谭柏林','93457652',1,26,'[\"观塘\", \"秀茂坪\", \"蓝田\"]','[\"全职\"]','[\"仓务员\", \"理货员\"]',2.0,13500,15000,1,2,NULL,'[\"粤语\", \"普通话\"]','[\"仓库管理\", \"收发货\", \"ERP系统\"]',NULL,2,1,'有冻仓经验',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(24,1,'方嘉颖','94567651',2,31,'[\"北角\", \"炮台山\", \"天后\"]','[\"兼职\", \"小时工\"]','[\"收银员\", \"生鲜销售\"]',5.0,70,85,3,3,NULL,'[\"粤语\", \"普通话\", \"英语\"]','[\"收银\", \"生鲜知识\", \"英语服务\"]',NULL,1,1,'全职妈妈，只做上午班小时工',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL),(25,1,'钟伟健','95677650',1,48,'[\"荃湾\", \"青衣\", \"葵芳\"]','[\"全职\"]','[\"生鲜切配\", \"副店长\"]',22.0,22000,26000,1,2,NULL,'[\"粤语\", \"普通话\"]','[\"生鲜全品类\", \"团队管理\", \"成本控制\", \"HACCP\"]',NULL,1,1,'持HACCP证书，曾任生鲜主管',NULL,'2026-03-21 23:58:18','2026-03-21 23:58:18',NULL);
/*!40000 ALTER TABLE `resumes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SUPER_ADMIN, REGION_BUYER, STORE_MANAGER, STORE_STAFF',
  `description` text COLLATE utf8mb4_unicode_ci,
  `scope` tinyint NOT NULL DEFAULT '3' COMMENT '1:总部 2:区域 3:门店',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_organization_id_code_unique` (`organization_id`,`code`),
  CONSTRAINT `roles_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saas_integrations`
--

DROP TABLE IF EXISTS `saas_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saas_integrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'wework, dingtalk, pos_system, erp...',
  `app_id` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_secret` text COLLATE utf8mb4_unicode_ci COMMENT '加密存储',
  `access_token` text COLLATE utf8mb4_unicode_ci COMMENT '加密存储',
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `webhook_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config` json DEFAULT NULL COMMENT '平台特定配置参数',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0:禁用 1:正常 2:故障',
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `saas_integrations_organization_id_platform_index` (`organization_id`,`platform`),
  KEY `saas_integrations_store_id_platform_index` (`store_id`,`platform`),
  CONSTRAINT `saas_integrations_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saas_integrations_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saas_integrations`
--

LOCK TABLES `saas_integrations` WRITE;
/*!40000 ALTER TABLE `saas_integrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `saas_integrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_records`
--

DROP TABLE IF EXISTS `salary_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `year` year NOT NULL,
  `month` tinyint NOT NULL,
  `work_days` decimal(4,1) NOT NULL DEFAULT '0.0',
  `actual_work_days` decimal(4,1) NOT NULL DEFAULT '0.0',
  `base_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `overtime_pay` decimal(10,2) NOT NULL DEFAULT '0.00',
  `performance_bonus` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sales_commission` decimal(10,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(10,2) NOT NULL DEFAULT '0.00',
  `social_insurance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `income_tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gross_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `net_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` tinyint NOT NULL DEFAULT '1' COMMENT '1:待发放 2:已发放 3:暂停',
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_records_employee_id_year_month_unique` (`employee_id`,`year`,`month`),
  KEY `salary_records_store_id_year_month_index` (`store_id`,`year`,`month`),
  CONSTRAINT `salary_records_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_records_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_records`
--

LOCK TABLES `salary_records` WRITE;
/*!40000 ALTER TABLE `salary_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `salary_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_daily_summary`
--

DROP TABLE IF EXISTS `sales_daily_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_daily_summary` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `sale_date` date NOT NULL,
  `sales_qty` decimal(10,3) NOT NULL DEFAULT '0.000',
  `sales_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sales_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_profit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `transaction_count` int NOT NULL DEFAULT '0',
  `avg_selling_price` decimal(12,4) DEFAULT NULL,
  `waste_qty` decimal(10,3) NOT NULL DEFAULT '0.000',
  `is_promotion_day` tinyint(1) NOT NULL DEFAULT '0',
  `weather_condition` tinyint DEFAULT NULL COMMENT '1:晴 2:阴 3:雨 4:雪 5:极端天气',
  `is_holiday` tinyint(1) NOT NULL DEFAULT '0',
  `holiday_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_daily_summary_store_id_product_id_sale_date_unique` (`store_id`,`product_id`,`sale_date`),
  KEY `sales_daily_summary_store_id_sale_date_index` (`store_id`,`sale_date`),
  KEY `sales_daily_summary_product_id_sale_date_index` (`product_id`,`sale_date`),
  KEY `sales_daily_summary_sale_date_is_holiday_index` (`sale_date`,`is_holiday`),
  CONSTRAINT `sales_daily_summary_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_daily_summary_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_daily_summary`
--

LOCK TABLES `sales_daily_summary` WRITE;
/*!40000 ALTER TABLE `sales_daily_summary` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_daily_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `schedule_date` date NOT NULL,
  `shift_start` time NOT NULL,
  `shift_end` time NOT NULL,
  `shift_type` tinyint NOT NULL DEFAULT '1' COMMENT '1:早班 2:中班 3:晚班 4:全天',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schedules_store_id_employee_id_schedule_date_unique` (`store_id`,`employee_id`,`schedule_date`),
  KEY `schedules_employee_id_foreign` (`employee_id`),
  KEY `schedules_created_by_foreign` (`created_by`),
  CONSTRAINT `schedules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `schedules_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedules_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('4aG3wRNE9YBmRnt1TRX7KpweIWevhpcrJgiRLCoD',NULL,'127.0.0.1','curl/7.81.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiZDByY1h3dEZrMktidzh4b29EVmp0dHNpNzBjeXZUNHlhVmtQZnExYyI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyNToiaHR0cDovLzAuMC4wLjA6ODA4MC9hZG1pbiI7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjI1OiJodHRwOi8vMC4wLjAuMDo4MDgwL2FkbWluIjtzOjU6InJvdXRlIjtzOjMwOiJmaWxhbWVudC5hZG1pbi5wYWdlcy5kYXNoYm9hcmQiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1774163162),('8nKdmN3uVsTJaAwpoDfM38qSjpCnyIGYxM6W3oCp',NULL,'127.0.0.1','curl/7.81.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRDNabGRtUTU4UnpVdG1yb1gyOWpkMzJ4WVFlUGlERlNMRGNwanNsZCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8wLjAuMC4wOjgwODAvYWRtaW4vbG9naW4iO3M6NToicm91dGUiO3M6MjU6ImZpbGFtZW50LmFkbWluLmF1dGgubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1774163234),('8ryBqjNZe9YynSSQjs8ty7J76DGiQ4N6RXH3TM9u',NULL,'136.0.213.103','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiaHhiUWhMd2lTQ2lvbkYwUmNyMWlZY3BvNlpaTjdDZlBqWVRsU0hLQSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjA6Imh0dHA6Ly84LjIxMC4xMTUuMTM0IjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1774167696),('DiZpAhFowEw3wPmj87w2fyB4Y9q1gTobF9uw1HTF',NULL,'113.215.189.220','Mozilla/5.0 (Linux; Android 9; ASUS_X00TD; Flow) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/359.0.0.288 Mobile Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNlFlR1ZQU0hvMmZBQ2pjVmpLSXpzaFRBSjd2elQ2cVpUdVpKa0xDcCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzA6Imh0dHA6Ly90ZXN0LnNqMjRsb3Zlcy5jb206ODA4MCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1774167723),('IRedekNP0rxoPUleCviwACnL10HHYW9JYItDcIX6',NULL,'113.215.188.159','Mozilla/5.0 (Linux; Android 9; ASUS_X00TD; Flow) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/359.0.0.288 Mobile Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTkZRSFpTZWh5ZHRHY3Jlc2tmdkFnNkJkV1M3ZWVZUDVhaVhWTVM1VCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzA6Imh0dHA6Ly90ZXN0LnNqMjRsb3Zlcy5jb206ODA4MCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1774167736),('r5ek24ZdIDNgBGu7CUDZoY7Gg0NpfHqwVrgj3sHf',8,'150.109.54.248','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','YTo3OntzOjY6Il90b2tlbiI7czo0MDoic25IRXYyZk0yNHhSMkh3WnNBWnMyOWl3UHd6dWJ2NUw3T3h2UFNFaCI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjQ2OiJodHRwOi8vdGVzdC5zajI0bG92ZXMuY29tOjgwODAvYWRtaW4vc3VwcGxpZXJzIjtzOjU6InJvdXRlIjtzOjQwOiJmaWxhbWVudC5hZG1pbi5yZXNvdXJjZXMuc3VwcGxpZXJzLmluZGV4Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6ODtzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2MDoiJDJ5JDEyJG44MzREazdJWmxSazN4Si5peU5WWU8zN2E2cU5oTkFUclV2S3hCM0lLN2VPZm56THp0d0J5IjtzOjY6InRhYmxlcyI7YTozOntzOjQxOiI3M2Y5ZmE1NTgzNGYwM2I2ZDUyMmViYjRmZDQwOGZlOF9wZXJfcGFnZSI7czozOiJhbGwiO3M6NDE6ImUxNDUyNzhlNDc5MzZmNzY4Y2Y5NGU3ZThlZDlmOWRiX3Blcl9wYWdlIjtzOjI6IjUwIjtzOjQxOiIwOTkxYjFkMjI4NjRlNzJjMGU4M2ViMGEyY2QwNzcyN19wZXJfcGFnZSI7czozOiJhbGwiO319',1774170714);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_products`
--

DROP TABLE IF EXISTS `store_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `shelf_position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '货架位置',
  `selling_price` decimal(12,2) DEFAULT NULL COMMENT '当前零售价',
  `min_stock_alert` decimal(10,3) DEFAULT NULL COMMENT '库存预警下限',
  `max_stock_limit` decimal(10,3) DEFAULT NULL COMMENT '库存上限',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_products_store_id_product_id_unique` (`store_id`,`product_id`),
  KEY `store_products_store_id_is_active_index` (`store_id`,`is_active`),
  KEY `store_products_product_id_index` (`product_id`),
  CONSTRAINT `store_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_products_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_products`
--

LOCK TABLES `store_products` WRITE;
/*!40000 ALTER TABLE `store_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `store_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stores`
--

DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `region_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `manager_user_id` bigint unsigned DEFAULT NULL,
  `business_hours` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0:关闭 1:正常 2:装修中',
  `settings` json DEFAULT NULL COMMENT '门店级配置，覆盖总部默认值',
  `opened_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stores_manager_user_id_foreign` (`manager_user_id`),
  KEY `stores_organization_id_status_index` (`organization_id`,`status`),
  KEY `stores_region_id_index` (`region_id`),
  CONSTRAINT `stores_manager_user_id_foreign` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stores_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stores_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stores`
--

LOCK TABLES `stores` WRITE;
/*!40000 ALTER TABLE `stores` DISABLE KEYS */;
INSERT INTO `stores` VALUES (1,1,1,'铜锣湾旗舰店','CWB001','香港铜锣湾',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-03-22 06:54:14','2026-03-22 06:54:14',NULL),(2,1,1,'西湾河店','XWH','西湾河筲箕湾道 18 号地铺',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-03-22 00:02:05','2026-03-22 00:02:05',NULL),(3,1,1,'湾仔店','WCH','湾仔骆克道 88 号地铺',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-03-22 00:02:05','2026-03-22 00:02:05',NULL);
/*!40000 ALTER TABLE `stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_price_history`
--

DROP TABLE IF EXISTS `supplier_price_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_price_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_product_id` bigint unsigned NOT NULL,
  `old_price` decimal(12,2) NOT NULL,
  `new_price` decimal(12,2) NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `change_reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `supplier_price_history_changed_by_foreign` (`changed_by`),
  KEY `supplier_price_history_supplier_product_id_effective_date_index` (`supplier_product_id`,`effective_date`),
  CONSTRAINT `supplier_price_history_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_price_history_supplier_product_id_foreign` FOREIGN KEY (`supplier_product_id`) REFERENCES `supplier_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_price_history`
--

LOCK TABLES `supplier_price_history` WRITE;
/*!40000 ALTER TABLE `supplier_price_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_price_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_products`
--

DROP TABLE IF EXISTS `supplier_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `supplier_product_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '供应商侧商品编码',
  `purchase_price` decimal(12,2) NOT NULL COMMENT '当前采购单价',
  `min_order_qty` decimal(10,3) NOT NULL DEFAULT '1.000',
  `delivery_lead_days` int DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为该商品首选供应商',
  `price_effective_date` date DEFAULT NULL,
  `price_expired_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_products_product_id_is_primary_index` (`product_id`,`is_primary`),
  KEY `supplier_products_supplier_id_index` (`supplier_id`),
  CONSTRAINT `supplier_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_products_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_products`
--

LOCK TABLES `supplier_products` WRITE;
/*!40000 ALTER TABLE `supplier_products` DISABLE KEYS */;
INSERT INTO `supplier_products` VALUES (1,1,1,NULL,1.20,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(2,1,2,NULL,3.50,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(3,1,3,NULL,0.80,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(4,1,4,NULL,2.50,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(5,1,5,NULL,2.80,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(6,1,6,NULL,1.00,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(7,1,7,NULL,1.50,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(8,1,8,NULL,4.50,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(9,1,9,NULL,3.20,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(10,1,10,NULL,3.80,10.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(11,2,11,NULL,18.00,5.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(12,2,12,NULL,12.50,5.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(13,2,13,NULL,55.00,5.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(14,2,14,NULL,22.00,5.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(15,3,15,NULL,28.00,5.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(16,3,16,NULL,65.00,5.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(17,3,17,NULL,12.00,5.000,1,1,NULL,NULL,'2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(18,1,18,NULL,2.50,5.000,1,1,NULL,NULL,'2026-03-22 00:27:41','2026-03-22 00:27:41',NULL),(19,1,19,NULL,1.80,5.000,1,1,NULL,NULL,'2026-03-22 00:27:41','2026-03-22 00:27:41',NULL),(20,1,20,NULL,2.20,5.000,1,1,NULL,NULL,'2026-03-22 00:27:41','2026-03-22 00:27:41',NULL),(21,1,21,NULL,68.00,5.000,1,1,NULL,NULL,'2026-03-22 00:27:41','2026-03-22 00:27:41',NULL);
/*!40000 ALTER TABLE `supplier_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_settlement_orders`
--

DROP TABLE IF EXISTS `supplier_settlement_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_settlement_orders` (
  `settlement_id` bigint unsigned NOT NULL,
  `purchase_order_id` bigint unsigned NOT NULL,
  `order_amount` decimal(12,2) NOT NULL,
  PRIMARY KEY (`settlement_id`,`purchase_order_id`),
  KEY `supplier_settlement_orders_purchase_order_id_foreign` (`purchase_order_id`),
  CONSTRAINT `supplier_settlement_orders_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_settlement_orders_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `supplier_settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_settlement_orders`
--

LOCK TABLES `supplier_settlement_orders` WRITE;
/*!40000 ALTER TABLE `supplier_settlement_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_settlement_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_settlements`
--

DROP TABLE IF EXISTS `supplier_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_settlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `organization_id` bigint unsigned NOT NULL,
  `settlement_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_purchase_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_return_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `settlement_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `outstanding_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1:待对账 2:已对账 3:部分付款 4:已结清 5:有争议',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `settled_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_settlements_settlement_no_unique` (`settlement_no`),
  KEY `supplier_settlements_organization_id_foreign` (`organization_id`),
  KEY `supplier_settlements_settled_by_foreign` (`settled_by`),
  KEY `supplier_settlements_supplier_id_status_index` (`supplier_id`,`status`),
  KEY `supplier_settlements_period_start_period_end_index` (`period_start`,`period_end`),
  CONSTRAINT `supplier_settlements_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_settlements_settled_by_foreign` FOREIGN KEY (`settled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_settlements_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_settlements`
--

LOCK TABLES `supplier_settlements` WRITE;
/*!40000 ALTER TABLE `supplier_settlements` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_settlements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_wechat` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_license` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_terms` tinyint NOT NULL DEFAULT '1' COMMENT '1:现款 2:月结 3:季结',
  `payment_days` int NOT NULL DEFAULT '0' COMMENT '账期天数',
  `delivery_lead_days` int NOT NULL DEFAULT '1' COMMENT '平均交货周期（天）',
  `rating` tinyint DEFAULT NULL COMMENT '1-5星评级',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '0:停用 1:正常',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suppliers_organization_id_status_index` (`organization_id`,`status`),
  CONSTRAINT `suppliers_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,1,'新鲜直送农场','SUP001','陈老板','9123 4567',NULL,NULL,NULL,2,30,1,5,1,'专供蔬菜水果，每日早上 7 点送货','2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(2,1,'港鲜肉类批发','SUP002','黄先生','9876 5432',NULL,NULL,NULL,1,0,1,4,1,'供应猪肉、鸡肉、牛肉，现款现货','2026-03-22 00:02:06','2026-03-22 00:02:06',NULL),(3,1,'南海水产行','SUP003','李姐','6688 9900',NULL,NULL,NULL,2,15,1,4,1,'鱼虾蟹贝类，当日早市配送','2026-03-22 00:02:06','2026-03-22 00:02:06',NULL);
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_store_roles`
--

DROP TABLE IF EXISTS `user_store_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_store_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `region_id` bigint unsigned DEFAULT NULL,
  `role_id` bigint unsigned NOT NULL,
  `granted_by` bigint unsigned DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expired_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_store_roles_region_id_foreign` (`region_id`),
  KEY `user_store_roles_role_id_foreign` (`role_id`),
  KEY `user_store_roles_granted_by_foreign` (`granted_by`),
  KEY `user_store_roles_user_id_store_id_index` (`user_id`,`store_id`),
  KEY `user_store_roles_store_id_role_id_index` (`store_id`,`role_id`),
  CONSTRAINT `user_store_roles_granted_by_foreign` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_store_roles_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_store_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_store_roles_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_store_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_store_roles`
--

LOCK TABLES `user_store_roles` WRITE;
/*!40000 ALTER TABLE `user_store_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_store_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Demo User','demo@example.com',0,'2026-03-15 07:56:51','$2y$12$b7y0NJbCSJGYK8i1Fx5FUu28LTEDhb2tskuSkoGvxUqN4z1iFAyJ2','kMV5C03Trm','2026-03-15 07:56:52','2026-03-15 07:56:52'),(2,'Mr. Erick Stracke DDS','uconn@example.com',0,'2026-03-15 07:56:52','$2y$12$b7y0NJbCSJGYK8i1Fx5FUu28LTEDhb2tskuSkoGvxUqN4z1iFAyJ2','gFg8KcsRXo','2026-03-15 07:56:52','2026-03-15 07:56:52'),(3,'Bonnie Kling','zakary.beatty@example.org',0,'2026-03-15 07:56:52','$2y$12$b7y0NJbCSJGYK8i1Fx5FUu28LTEDhb2tskuSkoGvxUqN4z1iFAyJ2','3i1nYWxH1S','2026-03-15 07:56:52','2026-03-15 07:56:52'),(4,'Isidro Kuhic','jacklyn21@example.org',0,'2026-03-15 07:56:52','$2y$12$b7y0NJbCSJGYK8i1Fx5FUu28LTEDhb2tskuSkoGvxUqN4z1iFAyJ2','5aPflhhhIf','2026-03-15 07:56:52','2026-03-15 07:56:52'),(5,'Ernest Ryan','clang@example.org',0,'2026-03-15 07:56:52','$2y$12$b7y0NJbCSJGYK8i1Fx5FUu28LTEDhb2tskuSkoGvxUqN4z1iFAyJ2','pE5vo5wTqp','2026-03-15 07:56:52','2026-03-15 07:56:52'),(6,'Marina Mraz','odie66@example.org',0,'2026-03-15 07:56:52','$2y$12$b7y0NJbCSJGYK8i1Fx5FUu28LTEDhb2tskuSkoGvxUqN4z1iFAyJ2','ovNcPtP9kb','2026-03-15 07:56:52','2026-03-15 07:56:52'),(8,'超级管理员','admin@sjtxg.com',1,NULL,'$2y$12$n834Dk7IZlRk3xJ.iyNVYO37a6qNhNATrUvKxB3IK7eOfnzLztwBy',NULL,'2026-03-21 23:08:58','2026-03-21 23:08:58');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wework_users`
--

DROP TABLE IF EXISTS `wework_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wework_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `wework_userid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wework_openid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_ids` json DEFAULT NULL,
  `bound_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wework_users_wework_userid_unique` (`wework_userid`),
  KEY `wework_users_user_id_index` (`user_id`),
  CONSTRAINT `wework_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wework_users`
--

LOCK TABLES `wework_users` WRITE;
/*!40000 ALTER TABLE `wework_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `wework_users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-22 18:01:41
