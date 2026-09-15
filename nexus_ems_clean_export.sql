-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: nexus_ems_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applications` (
  `application_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `application_no` varchar(30) NOT NULL,
  `competition_id` bigint(20) DEFAULT NULL COMMENT 'NULL for event-based registrations',
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `student_id` bigint(20) NOT NULL,
  `application_type` enum('Individual','Team') NOT NULL,
  `application_status` enum('Pending','Approved','Rejected','Cancelled','Withdrawn') NOT NULL DEFAULT 'Pending',
  `approval_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` bigint(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `uq_application_no` (`application_no`),
  UNIQUE KEY `uq_student_competition` (`student_id`,`competition_id`),
  KEY `idx_application_student` (`student_id`),
  KEY `idx_application_competition` (`competition_id`),
  KEY `idx_application_status` (`application_status`),
  KEY `fk_application_approved_by` (`approved_by`),
  KEY `idx_app_symposium_event` (`symposium_event_id`),
  CONSTRAINT `fk_application_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_application_competition` FOREIGN KEY (`competition_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_application_symposium_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attendance_records`
--

DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_records` (
  `attendance_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) NOT NULL,
  `competition_id` bigint(20) DEFAULT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `application_id` bigint(20) NOT NULL,
  `student_id` bigint(20) NOT NULL,
  `attendance_status` enum('Present','Absent','Late') NOT NULL DEFAULT 'Absent',
  `marked_by` bigint(20) NOT NULL,
  `marked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `coordinator_notes` varchar(500) DEFAULT NULL,
  `sync_source` enum('Online','Offline') NOT NULL DEFAULT 'Online',
  `client_device_id` varchar(100) DEFAULT NULL,
  `client_timestamp` datetime DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `uq_attendance` (`session_id`,`student_id`),
  KEY `idx_ar_competition` (`competition_id`),
  KEY `idx_ar_student` (`student_id`),
  KEY `idx_ar_session` (`session_id`),
  KEY `idx_ar_status` (`attendance_status`),
  KEY `fk_ar_marked_by` (`marked_by`),
  KEY `idx_att_rec_symp_event` (`symposium_event_id`),
  KEY `fk_ar_application` (`application_id`),
  CONSTRAINT `fk_ar_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_competition` FOREIGN KEY (`competition_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_marked_by` FOREIGN KEY (`marked_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_ar_session` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`session_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `fk_att_rec_symp_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=277 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attendance_sessions`
--

DROP TABLE IF EXISTS `attendance_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_sessions` (
  `session_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `competition_id` bigint(20) DEFAULT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `opened_by` bigint(20) NOT NULL,
  `opened_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_by` bigint(20) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `idx_as_competition` (`competition_id`),
  KEY `idx_as_status` (`status`),
  KEY `fk_as_opened_by` (`opened_by`),
  KEY `fk_as_closed_by` (`closed_by`),
  KEY `idx_att_sess_symp_event` (`symposium_event_id`),
  CONSTRAINT `fk_as_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_as_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`competition_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_as_opened_by` FOREIGN KEY (`opened_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_att_sess_symp_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `audit_log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `action` varchar(100) NOT NULL,
  `module_name` varchar(100) DEFAULT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` bigint(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_log_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_time` (`action_time`),
  KEY `idx_record_id` (`record_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1363 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificate_event_config`
--

DROP TABLE IF EXISTS `certificate_event_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificate_event_config` (
  `config_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `symposium_event_id` bigint(20) NOT NULL,
  `template_id` bigint(20) NOT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `uq_cec_event` (`symposium_event_id`),
  KEY `idx_cec_template` (`template_id`),
  KEY `fk_cec_created_by` (`created_by`),
  CONSTRAINT `fk_cec_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cec_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cec_template` FOREIGN KEY (`template_id`) REFERENCES `certificate_templates` (`certificate_template_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificate_template_versions`
--

DROP TABLE IF EXISTS `certificate_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificate_template_versions` (
  `version_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `certificate_template_id` bigint(20) NOT NULL,
  `version_number` int(11) NOT NULL DEFAULT 1,
  `field_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of field definition objects' CHECK (json_valid(`field_config`)),
  `locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = locked after first certificate generated from this version',
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version_id`),
  KEY `idx_ctv_template` (`certificate_template_id`),
  KEY `idx_ctv_locked` (`locked`),
  KEY `fk_ctv_created_by` (`created_by`),
  CONSTRAINT `fk_ctv_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ctv_template` FOREIGN KEY (`certificate_template_id`) REFERENCES `certificate_templates` (`certificate_template_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificate_templates`
--

DROP TABLE IF EXISTS `certificate_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificate_templates` (
  `certificate_template_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL COMMENT 'Relative path from project root, e.g. storage/certificate_templates/tpl_1_abc12345.pdf',
  `file_hash` char(64) NOT NULL COMMENT 'SHA-256 of the original uploaded PDF',
  `page_width_pt` decimal(8,2) NOT NULL COMMENT 'PDF page width in points (1 pt = 1/72 inch)',
  `page_height_pt` decimal(8,2) NOT NULL COMMENT 'PDF page height in points',
  `page_orientation` enum('Portrait','Landscape') NOT NULL DEFAULT 'Portrait',
  `rank_display_mode` enum('checkboxes','label','none') NOT NULL DEFAULT 'checkboxes' COMMENT 'checkboxes=three tick marks, label=text rank label, none=no rank display',
  `team_cert_mode` enum('per_member','per_team') DEFAULT NULL COMMENT 'NULL = not configured for team events',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`certificate_template_id`),
  KEY `idx_ct_active` (`is_active`),
  KEY `idx_ct_archived` (`is_archived`),
  KEY `idx_ct_created` (`created_by`),
  CONSTRAINT `fk_ct_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `certificate_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) NOT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `certificate_number` varchar(50) NOT NULL,
  `certificate_type` enum('Participation','Winner','Runner') NOT NULL,
  `verification_hash` char(64) NOT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `verification_url` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`certificate_id`),
  UNIQUE KEY `uq_certificate_number` (`certificate_number`),
  UNIQUE KEY `uq_certificate_hash` (`verification_hash`),
  UNIQUE KEY `uq_certificate_application` (`application_id`),
  KEY `idx_certificate_application` (`application_id`),
  KEY `idx_certificate_number` (`certificate_number`),
  KEY `idx_cert_symp_event` (`symposium_event_id`),
  CONSTRAINT `fk_cert_symp_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_certificate_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competition_coordinators`
--

DROP TABLE IF EXISTS `competition_coordinators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competition_coordinators` (
  `coordinator_assignment_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `competition_id` bigint(20) NOT NULL,
  `coordinator_type` enum('Staff Coordinator','Event Coordinator','Student Coordinator','Judge') NOT NULL DEFAULT 'Staff Coordinator',
  `user_id` bigint(20) NOT NULL,
  `responsibility` enum('Coordinator','Incharge') NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`coordinator_assignment_id`),
  UNIQUE KEY `uq_competition_user` (`competition_id`,`user_id`),
  UNIQUE KEY `uq_competition_responsibility` (`competition_id`,`responsibility`),
  KEY `fk_cc_user` (`user_id`),
  KEY `idx_cc_type` (`coordinator_type`),
  CONSTRAINT `fk_cc_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`competition_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competition_evaluations`
--

DROP TABLE IF EXISTS `competition_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competition_evaluations` (
  `evaluation_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) NOT NULL,
  `judge_id` bigint(20) NOT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `mark` decimal(4,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('Draft','Submitted') NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`evaluation_id`),
  UNIQUE KEY `uq_eval_app_judge` (`application_id`,`judge_id`),
  KEY `fk_ce_application` (`application_id`),
  KEY `fk_ce_judge` (`judge_id`),
  KEY `idx_ce_symp_event` (`symposium_event_id`),
  CONSTRAINT `fk_ce_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ce_judge` FOREIGN KEY (`judge_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ce_symp_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competition_judges`
--

DROP TABLE IF EXISTS `competition_judges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competition_judges` (
  `judge_assignment_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `competition_id` bigint(20) DEFAULT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `is_coordinator_judge` enum('Yes','No') NOT NULL DEFAULT 'No',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `assigned_by` bigint(20) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `removed_by` bigint(20) DEFAULT NULL,
  `removed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`judge_assignment_id`),
  KEY `fk_cj_competition` (`competition_id`),
  KEY `fk_cj_user` (`user_id`),
  KEY `fk_cj_assigned_by` (`assigned_by`),
  KEY `fk_cj_removed_by` (`removed_by`),
  KEY `idx_cj_symp_event` (`symposium_event_id`),
  CONSTRAINT `fk_cj_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cj_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`competition_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cj_removed_by` FOREIGN KEY (`removed_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cj_symp_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cj_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competition_results`
--

DROP TABLE IF EXISTS `competition_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competition_results` (
  `result_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) NOT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `total_score` decimal(6,2) NOT NULL,
  `average_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `rank_position` int(11) DEFAULT NULL,
  `result_status` enum('Qualified','Winner','Runner','Participant','Disqualified','First Place','Second Place','Third Place') NOT NULL DEFAULT 'Participant',
  `evaluated_by` bigint(20) DEFAULT NULL,
  `evaluation_time` datetime NOT NULL,
  `remarks` text DEFAULT NULL,
  `statistical_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`statistical_snapshot`)),
  `snapshot_hash` varchar(64) DEFAULT NULL,
  `judge_marks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`judge_marks`)),
  `published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `manual_rank_override` int(11) DEFAULT NULL COMMENT 'Coordinator-assigned rank when the engine produces a perfect tie. Overrides rank_position on publish.',
  `manual_rank_reason` varchar(255) DEFAULT NULL COMMENT 'Optional reason/note provided by the coordinator when setting a manual rank override.',
  `override_by` bigint(20) DEFAULT NULL COMMENT 'user_id of the staff member who set the manual rank override.',
  `override_at` datetime DEFAULT NULL COMMENT 'Timestamp when the manual rank override was last saved.',
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `uq_result_application` (`application_id`),
  KEY `idx_result_application` (`application_id`),
  KEY `idx_result_rank` (`rank_position`),
  KEY `idx_cr_symp_event` (`symposium_event_id`),
  KEY `fk_result_evaluator` (`evaluated_by`),
  CONSTRAINT `fk_cr_symp_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_result_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_result_evaluator` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competition_rules`
--

DROP TABLE IF EXISTS `competition_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competition_rules` (
  `rule_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `competition_id` bigint(20) NOT NULL,
  `section` enum('Eligibility','Topics','Judging Criteria','Allowed Software','Required Materials','Prohibited Items','Submission Format','Time Limit','Additional Instructions','General Rules','Tie Break Rules','Online Guidelines') NOT NULL,
  `content` text NOT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rule_id`),
  KEY `idx_crule_competition` (`competition_id`),
  KEY `idx_crule_section` (`section`,`display_order`),
  CONSTRAINT `fk_rule_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`competition_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competition_stages`
--

DROP TABLE IF EXISTS `competition_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competition_stages` (
  `stage_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `competition_id` bigint(20) DEFAULT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `stage_name` enum('Digital Prelims','Offline Prelims','Quarter Finals','Semi Finals','Finals','Custom') NOT NULL,
  `custom_name` varchar(100) DEFAULT NULL,
  `stage_order` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `stage_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`stage_id`),
  KEY `fk_stage_venue` (`venue_id`),
  KEY `idx_cstage_competition` (`competition_id`,`stage_order`),
  CONSTRAINT `fk_stage_competition` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`competition_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stage_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competition_submissions`
--

DROP TABLE IF EXISTS `competition_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competition_submissions` (
  `submission_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) NOT NULL,
  `original_file_name` varchar(255) NOT NULL,
  `stored_file_name` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`submission_id`),
  KEY `idx_submission_application` (`application_id`),
  CONSTRAINT `fk_submission_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competition_types`
--

DROP TABLE IF EXISTS `competition_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competition_types` (
  `competition_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_code` varchar(20) NOT NULL,
  `type_name` varchar(120) NOT NULL,
  `category` enum('Technical','Non-Technical') NOT NULL,
  `is_team_event` tinyint(1) NOT NULL DEFAULT 0,
  `supports_upload` tinyint(1) NOT NULL DEFAULT 0,
  `supports_prelims` tinyint(1) NOT NULL DEFAULT 0,
  `supports_batch` tinyint(1) NOT NULL DEFAULT 0,
  `default_team_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`competition_type_id`),
  UNIQUE KEY `uq_type_code` (`type_code`),
  CONSTRAINT `chk_team_size` CHECK (`default_team_size` between 1 and 10)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `competitions`
--

DROP TABLE IF EXISTS `competitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competitions` (
  `competition_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `symposium_id` bigint(20) NOT NULL,
  `competition_type_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `competition_code` varchar(30) NOT NULL,
  `status` enum('Draft','Scheduled','Registration Open','Registration Closed','Running','Completed','Cancelled') NOT NULL DEFAULT 'Draft',
  `evaluation_status` enum('Pending','Open','Closed','Published','Locked') NOT NULL DEFAULT 'Pending',
  `competition_mode` enum('Offline','Online','Hybrid') NOT NULL DEFAULT 'Offline',
  `category` enum('Technical','Non-Technical') NOT NULL DEFAULT 'Technical',
  `session` enum('FN','AN','Full Day') NOT NULL DEFAULT 'FN',
  `participation_type` enum('Individual','Team','Both') NOT NULL DEFAULT 'Individual',
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `max_participants` smallint(5) unsigned DEFAULT NULL,
  `registration_limit` smallint(5) unsigned DEFAULT NULL,
  `eligibility_note` text DEFAULT NULL,
  `has_prelims` tinyint(1) NOT NULL DEFAULT 0,
  `has_digital_prelims` tinyint(1) NOT NULL DEFAULT 0,
  `prelim_type` enum('MCQ','File Submission','Coding Test','Abstract Screening','Portfolio Review','Custom') DEFAULT NULL,
  `supports_evaluation` tinyint(1) NOT NULL DEFAULT 1,
  `supports_attendance` tinyint(1) NOT NULL DEFAULT 1,
  `supports_qr` tinyint(1) NOT NULL DEFAULT 0,
  `supports_certificates` tinyint(1) NOT NULL DEFAULT 1,
  `supports_tie_break` tinyint(1) NOT NULL DEFAULT 0,
  `supports_waiting_list` tinyint(1) NOT NULL DEFAULT 0,
  `judging_method` enum('Marks','Rubrics','Voting','Mixed') NOT NULL DEFAULT 'Marks',
  `online_platform` enum('Google Meet','Microsoft Teams','Zoom','Other') DEFAULT NULL,
  `meeting_link` varchar(512) DEFAULT NULL,
  `submission_url` varchar(512) DEFAULT NULL,
  `max_team_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `min_team_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `event_date` date NOT NULL,
  `reporting_time` time NOT NULL,
  `start_time` time NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `end_time` time NOT NULL,
  `registration_deadline` datetime NOT NULL,
  `submission_deadline` datetime DEFAULT NULL,
  `maximum_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `display_order` int(11) NOT NULL DEFAULT 1,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `published_by` bigint(20) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) DEFAULT NULL,
  `rules` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_modified_by` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`competition_id`),
  UNIQUE KEY `uq_competition_code` (`competition_code`),
  KEY `idx_competition_symposium` (`symposium_id`),
  KEY `idx_competition_type` (`competition_type_id`),
  KEY `idx_competition_venue` (`venue_id`),
  KEY `idx_competition_date` (`event_date`),
  KEY `fk_competition_created_by` (`created_by`),
  KEY `fk_competition_last_modified_by` (`last_modified_by`),
  KEY `fk_competition_deleted_by` (`deleted_by`),
  KEY `fk_competition_archived_by` (`archived_by`),
  KEY `fk_competition_published_by` (`published_by`),
  KEY `idx_competition_status` (`status`),
  KEY `idx_competition_mode` (`competition_mode`),
  KEY `idx_competition_category` (`category`),
  KEY `idx_competition_archived` (`is_archived`),
  KEY `idx_competition_deleted` (`is_deleted`),
  CONSTRAINT `fk_competition_archived_by` FOREIGN KEY (`archived_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_competition_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_competition_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_competition_last_modified_by` FOREIGN KEY (`last_modified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_competition_published_by` FOREIGN KEY (`published_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_competition_symposium` FOREIGN KEY (`symposium_id`) REFERENCES `symposiums` (`symposium_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_competition_type` FOREIGN KEY (`competition_type_id`) REFERENCES `competition_types` (`competition_type_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_competition_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=804 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `department_approvers`
--

DROP TABLE IF EXISTS `department_approvers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `department_approvers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `approver_department_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_approver` (`department_id`,`approver_department_id`),
  KEY `fk_da_approver` (`approver_department_id`),
  CONSTRAINT `fk_da_approver` FOREIGN KEY (`approver_department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_da_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_code` varchar(10) NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `short_name` varchar(30) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uq_department_code` (`department_code`),
  UNIQUE KEY `uq_department_name` (`department_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_queue`
--

DROP TABLE IF EXISTS `email_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_queue` (
  `queue_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `symposium_id` bigint(20) NOT NULL,
  `trigger_event` varchar(80) NOT NULL COMMENT 'principal_approved | registration_open | registration_reminder | registration_closed',
  `triggered_by` bigint(20) DEFAULT NULL COMMENT 'user_id who triggered the event',
  `student_id` bigint(20) DEFAULT NULL COMMENT 'target student',
  `recipient_email` varchar(120) NOT NULL,
  `recipient_name` varchar(120) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `html_body` longtext NOT NULL COMMENT 'rendered HTML stored so worker needs no DB joins',
  `status` enum('pending','processing','sent','failed','dead') NOT NULL DEFAULT 'pending' COMMENT 'dead = exceeded max_attempts',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `next_attempt_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'worker skips rows not yet due',
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`queue_id`),
  KEY `idx_status_next` (`status`,`next_attempt_at`),
  KEY `idx_symposium` (`symposium_id`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DB-backed email queue with retry logic';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_send_log`
--

DROP TABLE IF EXISTS `email_send_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_send_log` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `symposium_id` bigint(20) NOT NULL,
  `trigger_event` varchar(80) NOT NULL,
  `triggered_by` bigint(20) DEFAULT NULL,
  `recipient_email` varchar(120) NOT NULL,
  `recipient_name` varchar(120) DEFAULT NULL,
  `student_id` bigint(20) DEFAULT NULL,
  `status` enum('Sent','Failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_symposium` (`symposium_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permanent audit log of every email send attempt';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_assignments`
--

DROP TABLE IF EXISTS `event_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_assignments` (
  `assignment_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `symposium_event_id` bigint(20) NOT NULL,
  `assignment_type` enum('Faculty Coordinator','Judge','Student Coordinator','Venue') NOT NULL,
  `assigned_user_id` bigint(20) DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `assigned_by` bigint(20) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`assignment_id`),
  KEY `idx_ea_event` (`symposium_event_id`),
  KEY `idx_ea_type` (`assignment_type`),
  KEY `idx_ea_user` (`assigned_user_id`),
  KEY `fk_ea_assigner` (`assigned_by`),
  CONSTRAINT `fk_ea_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_ea_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `faculty_assignments`
--

DROP TABLE IF EXISTS `faculty_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculty_assignments` (
  `assignment_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `symposium_event_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `assignment_role` varchar(60) NOT NULL DEFAULT 'Faculty Incharge',
  `assigned_by` bigint(20) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `removed_by` bigint(20) DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uq_fa_event_user` (`symposium_event_id`,`user_id`),
  KEY `idx_fa_user_active` (`user_id`,`is_active`),
  KEY `idx_fa_event_active` (`symposium_event_id`,`is_active`),
  KEY `idx_fa_assigned_by` (`assigned_by`),
  KEY `idx_fa_removed_by` (`removed_by`),
  CONSTRAINT `fk_fa_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_fa_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fa_removed_by` FOREIGN KEY (`removed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Faculty In-Charge assignments for symposium events. Resource Allocation Module.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `generated_certificates`
--

DROP TABLE IF EXISTS `generated_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generated_certificates` (
  `certificate_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `certificate_number` varchar(50) NOT NULL COMMENT 'e.g. CERT-2026-000001',
  `verification_token` char(64) DEFAULT NULL COMMENT 'Cryptographically random opaque token for public verification URL. NULL for legacy records only.',
  `certificate_hash` char(64) DEFAULT NULL COMMENT 'SHA-256 of deterministic canonical payload snapshot captured at issuance. NULL for legacy records only.',
  `canonical_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Immutable snapshot of certificate data at issuance. Source of truth for certificate_hash recomputation during verification.' CHECK (json_valid(`canonical_snapshot`)),
  `symposium_event_id` bigint(20) NOT NULL,
  `application_id` bigint(20) NOT NULL,
  `recipient_student_id` bigint(20) DEFAULT NULL,
  `result_id` bigint(20) NOT NULL,
  `template_id` bigint(20) NOT NULL,
  `version_id` bigint(20) NOT NULL,
  `recipient_name` varchar(150) NOT NULL COMMENT 'Denormalised name for display (not source of truth)',
  `rank_position` int(11) DEFAULT NULL COMMENT 'From competition_results at generation time',
  `result_status` varchar(50) NOT NULL COMMENT 'From competition_results at generation time',
  `file_path` varchar(255) NOT NULL COMMENT 'Relative path: storage/certificates/certificate_{id}.pdf',
  `file_hash` char(64) DEFAULT NULL COMMENT 'SHA-256 of the final generated PDF bytes',
  `generation_status` enum('Generated','Failed','Regenerated') NOT NULL,
  `generation_notes` text DEFAULT NULL COMMENT 'Error message or regeneration reason',
  `generated_by` bigint(20) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `regenerated_count` int(11) NOT NULL DEFAULT 0,
  `previous_cert_id` bigint(20) DEFAULT NULL COMMENT 'Self-ref: points to replaced record on regeneration',
  PRIMARY KEY (`certificate_id`),
  UNIQUE KEY `uq_certificate_number` (`certificate_number`),
  UNIQUE KEY `uq_certificate_idempotency` (`symposium_event_id`,`result_id`,`recipient_student_id`,`version_id`),
  UNIQUE KEY `uq_gc_verification_token` (`verification_token`),
  KEY `idx_gc_event` (`symposium_event_id`),
  KEY `idx_gc_application` (`application_id`),
  KEY `idx_gc_result` (`result_id`),
  KEY `idx_gc_template` (`template_id`),
  KEY `idx_gc_version` (`version_id`),
  KEY `idx_gc_status` (`generation_status`),
  KEY `fk_gc_generated_by` (`generated_by`),
  KEY `fk_generated_cert_recipient` (`recipient_student_id`),
  KEY `idx_gc_verification_token` (`verification_token`),
  CONSTRAINT `fk_gc_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_gc_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_gc_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_gc_result` FOREIGN KEY (`result_id`) REFERENCES `competition_results` (`result_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_gc_template` FOREIGN KEY (`template_id`) REFERENCES `certificate_templates` (`certificate_template_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_gc_version` FOREIGN KEY (`version_id`) REFERENCES `certificate_template_versions` (`version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_generated_cert_recipient` FOREIGN KEY (`recipient_student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `master_event_rules`
--

DROP TABLE IF EXISTS `master_event_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `master_event_rules` (
  `rule_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `section` enum('Eligibility','Topics','Materials Required','Judging Criteria','Restrictions','Disqualification','Notes') NOT NULL,
  `rule_text` text NOT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`rule_id`),
  KEY `idx_mer_event` (`event_id`,`section`,`display_order`),
  CONSTRAINT `fk_mer_event` FOREIGN KEY (`event_id`) REFERENCES `master_events` (`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `master_events`
--

DROP TABLE IF EXISTS `master_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `master_events` (
  `event_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `parent_event_id` bigint(20) DEFAULT NULL,
  `event_code` varchar(20) NOT NULL,
  `event_name` varchar(150) NOT NULL,
  `category` enum('Technical','Non-Technical') NOT NULL DEFAULT 'Technical',
  `participation_type` enum('Individual','Team','Both') NOT NULL DEFAULT 'Individual',
  `min_team_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `max_team_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `duration_type` enum('minutes','time_slot') NOT NULL DEFAULT 'minutes',
  `duration_minutes` int(11) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `duration_days` int(11) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `default_instructions` text DEFAULT NULL,
  `requires_prelims` tinyint(1) NOT NULL DEFAULT 0,
  `supports_registration` tinyint(1) NOT NULL DEFAULT 1,
  `supports_attendance` tinyint(1) NOT NULL DEFAULT 1,
  `supports_evaluation` tinyint(1) NOT NULL DEFAULT 1,
  `supports_certificates` tinyint(1) NOT NULL DEFAULT 1,
  `supports_tie_break` tinyint(1) NOT NULL DEFAULT 0,
  `judging_method` enum('Marks','Rubrics','Voting','Mixed') NOT NULL DEFAULT 'Marks',
  `maximum_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `status` enum('Draft','Published','Archived') NOT NULL DEFAULT 'Draft',
  `effective_from` date DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`event_id`),
  UNIQUE KEY `uq_event_code` (`event_code`),
  KEY `idx_me_category` (`category`),
  KEY `idx_me_status` (`status`),
  KEY `idx_me_code` (`event_code`),
  KEY `fk_me_parent` (`parent_event_id`),
  KEY `fk_me_created_by` (`created_by`),
  KEY `fk_me_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_me_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_me_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_me_parent` FOREIGN KEY (`parent_event_id`) REFERENCES `master_events` (`event_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('Student','User') NOT NULL,
  `recipient_id` bigint(20) NOT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `notification_title` varchar(150) NOT NULL,
  `notification_message` text NOT NULL,
  `notification_type` varchar(60) DEFAULT NULL,
  `notification_ref_key` varchar(120) DEFAULT NULL,
  `delivery_channel` enum('System','Email','WhatsApp') NOT NULL DEFAULT 'System',
  `delivery_status` enum('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  UNIQUE KEY `uq_notif_idempotency` (`recipient_type`,`recipient_id`,`notification_type`,`notification_ref_key`),
  KEY `idx_notification_recipient` (`recipient_type`,`recipient_id`),
  KEY `idx_notification_status` (`delivery_status`),
  KEY `idx_recipient_id` (`recipient_id`),
  KEY `idx_notif_symp_event` (`symposium_event_id`),
  CONSTRAINT `fk_notif_symp_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=471 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `offline_sync_queue`
--

DROP TABLE IF EXISTS `offline_sync_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `offline_sync_queue` (
  `queue_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `payload_hash` varchar(64) NOT NULL,
  `records_submitted` int(11) NOT NULL DEFAULT 0,
  `records_succeeded` int(11) NOT NULL DEFAULT 0,
  `records_failed` int(11) NOT NULL DEFAULT 0,
  `records_conflict` int(11) NOT NULL DEFAULT 0,
  `sync_status` enum('Processing','Completed','Failed','Partial') NOT NULL DEFAULT 'Processing',
  `error_message` text DEFAULT NULL,
  `client_device_id` varchar(100) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`queue_id`),
  KEY `idx_osq_user` (`user_id`),
  KEY `idx_osq_status` (`sync_status`),
  KEY `fk_osq_symposium_event` (`symposium_event_id`),
  CONSTRAINT `fk_osq_symposium_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_osq_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reports_archive`
--

DROP TABLE IF EXISTS `reports_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports_archive` (
  `report_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `report_name` varchar(150) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `symposium_event_id` bigint(20) DEFAULT NULL,
  `generated_by` bigint(20) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) NOT NULL,
  PRIMARY KEY (`report_id`),
  KEY `idx_report_generated_by` (`generated_by`),
  KEY `idx_ra_symp_event` (`symposium_event_id`),
  CONSTRAINT `fk_ra_symp_event` FOREIGN KEY (`symposium_event_id`) REFERENCES `symposium_events` (`symposium_event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_report_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `student_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `register_number` varchar(25) NOT NULL,
  `roll_number` varchar(25) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `dob` date DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `academic_year` tinyint(3) unsigned NOT NULL,
  `semester` tinyint(3) unsigned NOT NULL,
  `section` char(1) DEFAULT NULL,
  `admission_year` year(4) NOT NULL,
  `graduation_year` year(4) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `account_status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `uq_register_number` (`register_number`),
  UNIQUE KEY `uq_student_email` (`email`),
  UNIQUE KEY `uq_student_phone` (`phone`),
  KEY `idx_students_department` (`department_id`),
  KEY `idx_students_year` (`academic_year`),
  CONSTRAINT `fk_students_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `symposium_approvals`
--

DROP TABLE IF EXISTS `symposium_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `symposium_approvals` (
  `approval_id` int(11) NOT NULL AUTO_INCREMENT,
  `symposium_id` bigint(20) NOT NULL,
  `approval_level` varchar(50) NOT NULL,
  `approval_stage` varchar(50) NOT NULL,
  `approval_order` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `approver_user_id` bigint(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Revision Requested','Cancelled') NOT NULL DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`approval_id`),
  KEY `fk_approval_approver` (`approver_user_id`),
  KEY `fk_approval_dept` (`department_id`),
  KEY `idx_approval_status` (`symposium_id`,`status`),
  KEY `idx_approval_order` (`symposium_id`,`approval_order`),
  CONSTRAINT `fk_approval_approver` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_approval_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  CONSTRAINT `fk_approval_symposium` FOREIGN KEY (`symposium_id`) REFERENCES `symposiums` (`symposium_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `symposium_departments`
--

DROP TABLE IF EXISTS `symposium_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `symposium_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symposium_id` bigint(20) NOT NULL,
  `department_id` int(11) NOT NULL,
  `department_role` varchar(50) NOT NULL DEFAULT 'Organizer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_symposium_department` (`symposium_id`,`department_id`),
  KEY `idx_symdept_symposium_id` (`symposium_id`),
  KEY `idx_symdept_department_id` (`department_id`),
  CONSTRAINT `fk_symdept_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  CONSTRAINT `fk_symdept_symposium` FOREIGN KEY (`symposium_id`) REFERENCES `symposiums` (`symposium_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `symposium_events`
--

DROP TABLE IF EXISTS `symposium_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `symposium_events` (
  `symposium_event_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `symposium_id` bigint(20) NOT NULL,
  `event_id` bigint(20) NOT NULL,
  `master_version_used` varchar(10) NOT NULL DEFAULT '1.0',
  `event_code` varchar(20) NOT NULL,
  `event_name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category` enum('Technical','Non-Technical') NOT NULL,
  `participation_type` enum('Individual','Team','Both') NOT NULL,
  `min_team_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `max_team_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `snapshot_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot_rules`)),
  `snapshot_instructions` text DEFAULT NULL,
  `snapshot_evaluation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot_evaluation`)),
  `supports_registration` tinyint(1) NOT NULL DEFAULT 1,
  `supports_attendance` tinyint(1) NOT NULL DEFAULT 1,
  `supports_evaluation` tinyint(1) NOT NULL DEFAULT 1,
  `supports_certificates` tinyint(1) NOT NULL DEFAULT 1,
  `prelim_decision` enum('Pending','Required','Not Required') NOT NULL DEFAULT 'Pending',
  `judging_method` enum('Marks','Rubrics','Voting','Mixed') NOT NULL DEFAULT 'Marks',
  `maximum_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `venue_id` int(11) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `session` enum('FN','AN','Full Day') DEFAULT NULL,
  `reporting_time` time DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `registration_start` datetime DEFAULT NULL,
  `registration_end` datetime DEFAULT NULL,
  `max_participants` smallint(5) unsigned DEFAULT NULL,
  `event_mode` enum('Offline','Online','Hybrid') NOT NULL DEFAULT 'Offline',
  `faculty_coordinator_id` bigint(20) DEFAULT NULL,
  `custom_rules_override` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_rules_override`)),
  `custom_notes` text DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 1,
  `status` enum('Draft','Published','Registration Open','Registration Closed','Running','Completed','Cancelled') NOT NULL DEFAULT 'Draft',
  `schedule_status` enum('Unscheduled','Scheduled','Rescheduled','Locked') NOT NULL DEFAULT 'Unscheduled',
  `scheduled_at` datetime DEFAULT NULL,
  `scheduled_by` bigint(20) DEFAULT NULL,
  `rescheduled_at` datetime DEFAULT NULL,
  `rescheduled_by` bigint(20) DEFAULT NULL,
  `reschedule_reason` text DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`symposium_event_id`),
  UNIQUE KEY `uq_sym_event` (`symposium_id`,`event_id`),
  KEY `idx_se_symposium` (`symposium_id`),
  KEY `idx_se_event` (`event_id`),
  KEY `idx_se_venue` (`venue_id`),
  KEY `idx_se_date` (`event_date`),
  KEY `idx_se_status` (`status`),
  KEY `idx_se_coord` (`faculty_coordinator_id`),
  KEY `fk_se_created_by` (`created_by`),
  CONSTRAINT `fk_se_coord` FOREIGN KEY (`faculty_coordinator_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_se_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_se_event` FOREIGN KEY (`event_id`) REFERENCES `master_events` (`event_id`),
  CONSTRAINT `fk_se_symposium` FOREIGN KEY (`symposium_id`) REFERENCES `symposiums` (`symposium_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_se_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`),
  CONSTRAINT `chk_se_time` CHECK (`end_time` > `start_time`),
  CONSTRAINT `chk_se_team_size` CHECK (`max_team_size` >= `min_team_size`)
) ENGINE=InnoDB AUTO_INCREMENT=334 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `symposiums`
--

DROP TABLE IF EXISTS `symposiums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `symposiums` (
  `symposium_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `symposium_code` varchar(30) NOT NULL,
  `title` varchar(150) NOT NULL,
  `symposium_type` enum('Intra Department','Inter Department') NOT NULL DEFAULT 'Intra Department',
  `organizing_department_id` int(11) DEFAULT NULL,
  `academic_year` year(4) NOT NULL,
  `description` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `submitted_by` bigint(20) DEFAULT NULL,
  `brochure_path` varchar(255) DEFAULT NULL,
  `circular_path` varchar(255) DEFAULT NULL,
  `registration_start` datetime NOT NULL,
  `registration_end` datetime NOT NULL,
  `event_start_date` date NOT NULL,
  `event_end_date` date NOT NULL,
  `status` enum('Draft','Submitted','Pending HOD Approval','Pending Principal Approval','Approved','Scheduling Complete','Registration Open','Registration Closed','Completed','Cancelled','Rejected by HOD','Rejected by Principal') NOT NULL DEFAULT 'Draft',
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`symposium_id`),
  KEY `idx_symposium_year` (`academic_year`),
  KEY `idx_symposium_status` (`status`),
  KEY `fk_symposium_created_by` (`created_by`),
  KEY `fk_symposium_department` (`organizing_department_id`),
  KEY `fk_symposium_submitted_by` (`submitted_by`),
  CONSTRAINT `fk_symposium_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_symposium_department` FOREIGN KEY (`organizing_department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_symposium_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`),
  KEY `fk_system_updated_by` (`updated_by`),
  CONSTRAINT `fk_system_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `team_members`
--

DROP TABLE IF EXISTS `team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_members` (
  `team_member_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) NOT NULL,
  `student_id` bigint(20) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`team_member_id`),
  UNIQUE KEY `uq_team_student` (`team_id`,`student_id`),
  KEY `idx_team_member_team` (`team_id`),
  KEY `idx_team_member_student` (`student_id`),
  CONSTRAINT `fk_team_member_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_team_member_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `team_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `application_id` bigint(20) NOT NULL,
  `deprecated_team_name` varchar(100) DEFAULT NULL,
  `manager_student_id` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`team_id`),
  KEY `idx_team_application` (`application_id`),
  KEY `idx_team_manager` (`manager_student_id`),
  CONSTRAINT `fk_team_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_manager` FOREIGN KEY (`manager_student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','Principal','HOD','Staff','Staff Coordinator','Student Coordinator') NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `account_status` enum('Active','Inactive','Blocked') NOT NULL DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_employee_id` (`employee_id`),
  UNIQUE KEY `uq_user_email` (`email`),
  UNIQUE KEY `uq_user_phone` (`phone`),
  KEY `idx_users_department` (`department_id`),
  KEY `idx_users_role` (`role`),
  CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `venues`
--

DROP TABLE IF EXISTS `venues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `venues` (
  `venue_id` int(11) NOT NULL AUTO_INCREMENT,
  `venue_code` varchar(20) NOT NULL,
  `venue_name` varchar(120) NOT NULL,
  `building_name` varchar(100) NOT NULL,
  `floor` varchar(30) DEFAULT NULL,
  `seating_capacity` smallint(5) unsigned NOT NULL,
  `is_computer_lab` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`venue_id`),
  UNIQUE KEY `uq_venue_code` (`venue_code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-15 21:22:54
USE nexus_ems_db;

-- =====================================================
-- MASTER DATA : DEPARTMENTS
-- =====================================================

INSERT INTO departments
(department_code, department_name, short_name)
VALUES
('CS', 'Bachelor of Science in Computer Science', 'B.Sc. CS'),
('CA', 'Bachelor of Computer Applications', 'BCA');



-- =====================================================
-- MASTER DATA : VENUES
-- =====================================================

INSERT INTO venues
(venue_code, venue_name, building_name, floor, seating_capacity, is_computer_lab)
VALUES

('LAB1','Computer Laboratory 1','Computer Science Block','Ground Floor',60,1),

('LAB2','Computer Laboratory 2','Computer Science Block','First Floor',60,1),

('LAB3','Computer Laboratory 3','Computer Science Block','Second Floor',60,1),

('SEMHALL','Seminar Hall','Main Block','Ground Floor',250,0),

('AUDITORIUM','College Auditorium','Main Block','Ground Floor',600,0),

('A101','Classroom A101','Academic Block','First Floor',80,0),

('A102','Classroom A102','Academic Block','First Floor',80,0),

('OPENSTAGE','Open Air Stage','College Campus','Ground',500,0);



-- =====================================================
-- MASTER DATA : COMPETITION TYPES
-- =====================================================

INSERT INTO competition_types
(type_code,
type_name,
category,
is_team_event,

supports_prelims,
supports_batch,
default_team_size)

VALUES

('COD',
'Coding',
'Technical',
0,
0,
1,
1),

('DBG',
'Debugging',
'Technical',
0,
0,
1,
1),

('QUIZ',
'Technical Quiz',
'Technical',
1,

1,
1,
2),

('PAPER',
'Paper Presentation',
'Technical',
1,
1,
0,
2),

('POSTER',
'Poster Presentation',
'Technical',
1,
1,
0,
2),

('WEB',
'Web Design',
'Technical',
1,
0,
0,
2),

('UIUX',
'UI / UX Design',
'Technical',
1,
1,
0,
2),

('PHOTO',
'Photography',
'Non-Technical',
0,
1,
0,
1),

('FILM',
'Short Film',
'Non-Technical',
1,
1,
0,
0,
5),

('DRAW',
'Drawing',
'Non-Technical',
0,
0,
0,
0,
1),

('EWASTE',
'E-Waste Innovation',
'Technical',
1,
0,
0,
0,
3),

('COOK',
'Traditional Cooking',
'Non-Technical',
1,
0,
0,
0,
4),

('MEME',
'Meme Creation',
'Non-Technical',
1,
1,
0,
0,
2),

('TREASURE',
'Treasure Hunt',
'Non-Technical',
1,
0,
0,
0,
5),

('CONNECTION',
'Connections',
'Non-Technical',
1,
0,
0,
0,
4);



-- =====================================================
-- MASTER DATA : SYSTEM SETTINGS
-- =====================================================

INSERT INTO system_settings
(setting_key,
setting_value,
description)

VALUES

('COLLEGE_NAME',
'Government Arts and Science College, Veerapandi, Theni',
'College Name'),

('COLLEGE_SHORT_NAME',
'GASC',
'College Short Name'),

('SYSTEM_NAME',
'NexusCore Symposium Platform',
'System Name'),

('CURRENT_ACADEMIC_YEAR',
'2026',
'Current Academic Year'),

('CERTIFICATE_PREFIX',
'NEXUS',
'Certificate Number Prefix'),

('APPLICATION_PREFIX',
'APP',
'Application Number Prefix'),

('DEFAULT_TIMEZONE',
'Asia/Kolkata',
'System Time Zone'),

('REGISTRATION_STATUS',
'OPEN',
'Global Registration Status'),

('MAX_LOGIN_ATTEMPTS',
'5',
'Maximum Login Attempts'),

('SESSION_TIMEOUT',
'30',
'Session Timeout in Minutes'),

('PASSWORD_MIN_LENGTH',
'8',
'Minimum Password Length'),

('ALLOW_MULTIPLE_LOGIN',
'NO',
'Allow Multiple Sessions'),

('ENABLE_AUDIT_LOG',
'YES',
'Enable Audit Logging'),

('ENABLE_NOTIFICATIONS',
'YES',
'Enable Notification Service'),

('ENABLE_CERTIFICATE_VERIFICATION',
'YES',
'Enable QR Certificate Verification');
