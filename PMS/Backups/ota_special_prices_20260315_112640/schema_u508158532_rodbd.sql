-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 03, 2026 at 03:37 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u508158532_rodbd`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity`
--

CREATE TABLE `activity` (
  `id_activity` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `id_sale_item_catalog` bigint(20) DEFAULT NULL,
  `code` varchar(32) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `type` enum('tour','vibe') NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `base_price_cents` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT 'MXN',
  `capacity_default` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_booking`
--

CREATE TABLE `activity_booking` (
  `id_booking` bigint(20) NOT NULL,
  `id_activity` bigint(20) NOT NULL,
  `id_reservation` bigint(20) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `num_adults` int(11) NOT NULL DEFAULT 1,
  `num_children` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','confirmed','cancelled','no_show','completed') NOT NULL DEFAULT 'confirmed',
  `price_cents` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT 'MXN',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_booking_reservation`
--

CREATE TABLE `activity_booking_reservation` (
  `id_booking` bigint(20) NOT NULL,
  `id_reservation` bigint(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_user`
--

CREATE TABLE `app_user` (
  `id_user` bigint(20) NOT NULL,
  `id_reg` bigint(20) DEFAULT NULL,
  `id_company` bigint(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` text DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `names` varchar(255) NOT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `maiden_name` varchar(255) DEFAULT NULL,
  `full_name` varchar(600) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `locale` varchar(20) DEFAULT 'es-MX',
  `timezone` varchar(64) DEFAULT 'America/Mexico_City',
  `is_owner` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_amenities`
--

CREATE TABLE `category_amenities` (
  `id_category_amenities` bigint(20) NOT NULL,
  `id_category` bigint(20) NOT NULL,
  `has_air_conditioning` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Aire acondicionado (AC)',
  `has_fan` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Ventilador',
  `has_tv` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Televisión (smart TV o con cable)',
  `has_private_wifi` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wi-Fi privado',
  `has_minibar` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Minibar o refrigerador pequeño',
  `has_safe_box` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Caja fuerte',
  `has_workspace` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Escritorio o espacio de trabajo',
  `includes_bedding_towels` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Ropa de cama y toallas',
  `has_iron_board` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Plancha y tabla de planchar',
  `has_closet_rack` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Closet o perchero',
  `has_private_balcony_terrace` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Balcón o terraza privada',
  `has_view` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Vista (mar, jardín, piscina, ciudad, montaña)',
  `has_private_entrance` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Entrada independiente',
  `has_hot_water` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Agua caliente',
  `includes_toiletries` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Artículos de aseo',
  `has_hairdryer` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Secadora de cabello',
  `includes_clean_towels` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Toallas limpias',
  `has_coffee_tea_kettle` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Cafetera / tetera / hervidor eléctrico',
  `has_basic_utensils` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Utensilios básicos',
  `has_basic_food_items` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Productos básicos (sal, aceite, café, azúcar)',
  `is_private` tinyint(4) DEFAULT 0,
  `is_shared` tinyint(4) DEFAULT 0,
  `has_shared_bathroom` tinyint(4) DEFAULT 0,
  `has_private_bathroom` tinyint(4) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_bed_config`
--

CREATE TABLE `category_bed_config` (
  `id_bed_config` bigint(20) NOT NULL,
  `id_category` bigint(20) NOT NULL,
  `bed_type` enum('individual','matrimonial','queen','king') NOT NULL,
  `bed_count` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_calendar_amenity_display`
--

CREATE TABLE `category_calendar_amenity_display` (
  `id_category_calendar_amenity_display` bigint(20) NOT NULL,
  `id_category` bigint(20) NOT NULL,
  `amenity_key` varchar(64) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company`
--

CREATE TABLE `company` (
  `id_company` bigint(20) NOT NULL,
  `code` varchar(100) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `billing_email` varchar(255) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `postal_code` varchar(30) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `default_currency` varchar(10) DEFAULT 'MXN',
  `default_timezone` varchar(64) DEFAULT 'America/Mexico_City',
  `default_language` varchar(10) DEFAULT 'es',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folio`
--

CREATE TABLE `folio` (
  `id_folio` bigint(20) NOT NULL,
  `id_reservation` bigint(20) NOT NULL,
  `folio_name` varchar(255) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open' COMMENT 'original: folio_status_enum',
  `currency` varchar(10) DEFAULT 'MXN',
  `total_cents` int(11) DEFAULT 0,
  `balance_cents` int(11) DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `bill_to_type` varchar(64) DEFAULT NULL,
  `bill_to_id` bigint(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guest`
--

CREATE TABLE `guest` (
  `id_guest` bigint(20) NOT NULL,
  `id_user` bigint(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `names` varchar(255) NOT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `maiden_name` varchar(255) DEFAULT NULL,
  `full_name` varchar(600) DEFAULT NULL,
  `nationality` varchar(120) DEFAULT NULL,
  `country_residence` varchar(120) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `doc_type` varchar(50) DEFAULT NULL,
  `doc_number` varchar(100) DEFAULT NULL,
  `doc_country` varchar(120) DEFAULT NULL,
  `doc_expiry` date DEFAULT NULL,
  `language` varchar(10) DEFAULT 'es',
  `marketing_opt_in` tinyint(1) DEFAULT 0,
  `blacklisted` tinyint(1) DEFAULT 0,
  `blacklist_reason` text DEFAULT NULL,
  `loyalty_id` varchar(120) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `cc_token` varchar(255) DEFAULT NULL,
  `cc_brand` varchar(50) DEFAULT NULL,
  `cc_last4` varchar(10) DEFAULT NULL,
  `cc_exp` varchar(10) DEFAULT NULL,
  `pci_vault_provider` varchar(120) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `postal_code` varchar(30) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `notes_internal` text DEFAULT NULL,
  `notes_guest` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `line_item`
--

CREATE TABLE `line_item` (
  `id_line_item` bigint(20) NOT NULL,
  `item_type` enum('sale_item','tax_item','payment','obligation','income') NOT NULL,
  `id_user` bigint(20) DEFAULT NULL,
  `id_folio` bigint(20) NOT NULL,
  `id_line_item_catalog` bigint(20) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `service_date` date DEFAULT NULL,
  `quantity` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `unit_price_cents` int(11) NOT NULL DEFAULT 0,
  `amount_cents` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) DEFAULT 'MXN',
  `discount_amount_cents` int(11) NOT NULL DEFAULT 0,
  `revenue_account_code` varchar(120) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'posted',
  `external_ref` varchar(255) DEFAULT NULL,
  `method` varchar(64) DEFAULT NULL,
  `fx_rate` decimal(18,6) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `card_authorized_at` datetime DEFAULT NULL,
  `card_captured_at` datetime DEFAULT NULL,
  `refunded_total_cents` int(11) NOT NULL DEFAULT 0,
  `paid_cents` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `line_item_catalog`
--

CREATE TABLE `line_item_catalog` (
  `id_line_item_catalog` bigint(20) NOT NULL,
  `catalog_type` enum('sale_item','tax_rule','obligation','income','payment') NOT NULL,
  `id_category` bigint(20) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `default_unit_price_cents` int(11) NOT NULL DEFAULT 0,
  `show_in_folio` tinyint(1) NOT NULL DEFAULT 1,
  `allow_negative` tinyint(1) NOT NULL DEFAULT 0,
  `default_amount_cents` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `line_item_catalog_calc`
--

CREATE TABLE `line_item_catalog_calc` (
  `id_line_item_catalog` bigint(20) NOT NULL,
  `id_parent_line_item_catalog` bigint(20) NOT NULL,
  `id_component_line_item_catalog` bigint(20) NOT NULL,
  `is_positive` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `line_item_catalog_parent`
--

CREATE TABLE `line_item_catalog_parent` (
  `id_sale_item_catalog` bigint(20) NOT NULL,
  `id_parent_sale_item_catalog` bigint(20) NOT NULL,
  `add_to_father_total` tinyint(1) NOT NULL DEFAULT 1,
  `show_in_folio_relation` tinyint(1) DEFAULT NULL,
  `percent_value` decimal(12,6) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `line_item_hierarchy`
--

CREATE TABLE `line_item_hierarchy` (
  `id_line_item_hierarchy` bigint(20) NOT NULL,
  `id_line_item_child` bigint(20) NOT NULL,
  `id_line_item_parent` bigint(20) NOT NULL,
  `relation_kind` enum('derived_percent','legacy_backfill','manual','derived') NOT NULL DEFAULT 'derived_percent',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_template`
--

CREATE TABLE `message_template` (
  `id_message_template` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `code` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `obligation_payment_log`
--

CREATE TABLE `obligation_payment_log` (
  `id_obligation_payment_log` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_line_item` bigint(20) NOT NULL,
  `id_folio` bigint(20) NOT NULL,
  `id_reservation` bigint(20) DEFAULT NULL,
  `id_obligation_payment_method` bigint(20) NOT NULL,
  `payment_mode` varchar(16) NOT NULL,
  `amount_input_cents` int(11) NOT NULL DEFAULT 0,
  `amount_applied_cents` int(11) NOT NULL DEFAULT 0,
  `paid_before_cents` int(11) NOT NULL DEFAULT 0,
  `paid_after_cents` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `occupancy_snapshot`
--

CREATE TABLE `occupancy_snapshot` (
  `id_occupancy_snapshot` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `snapshot_date` date NOT NULL,
  `id_category` bigint(20) DEFAULT NULL,
  `rooms_total` int(11) NOT NULL DEFAULT 0,
  `rooms_sold` int(11) NOT NULL DEFAULT 0,
  `occupancy_pct` decimal(6,2) NOT NULL DEFAULT 0.00,
  `as_of_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ota_account`
--

CREATE TABLE `ota_account` (
  `id_ota_account` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `platform` varchar(32) NOT NULL DEFAULT 'other',
  `ota_name` varchar(150) NOT NULL,
  `color_hex` varchar(16) DEFAULT NULL,
  `external_code` varchar(120) DEFAULT NULL,
  `contact_email` varchar(190) DEFAULT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'America/Mexico_City',
  `notes` text DEFAULT NULL,
  `id_service_fee_payment_catalog` bigint(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ota_account_info_catalog`
--

CREATE TABLE `ota_account_info_catalog` (
  `id_ota_account_info_catalog` bigint(20) NOT NULL,
  `id_ota_account` bigint(20) NOT NULL,
  `id_line_item_catalog` bigint(20) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `display_alias` varchar(160) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ota_account_lodging_catalog`
--

CREATE TABLE `ota_account_lodging_catalog` (
  `id_ota_account_lodging_catalog` bigint(20) NOT NULL,
  `id_ota_account` bigint(20) NOT NULL,
  `id_line_item_catalog` bigint(20) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ota_ical_event`
--

CREATE TABLE `ota_ical_event` (
  `id_ota_ical_event` bigint(20) NOT NULL,
  `id_ota_ical_feed` bigint(20) NOT NULL,
  `uid` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `sequence` int(11) NOT NULL DEFAULT 0,
  `dtstamp` datetime DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL,
  `raw_vevent` longtext DEFAULT NULL,
  `hash_sha256` char(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ota_ical_event_map`
--

CREATE TABLE `ota_ical_event_map` (
  `id_ota_ical_event_map` bigint(20) NOT NULL,
  `id_ota_ical_feed` bigint(20) NOT NULL,
  `uid` varchar(255) NOT NULL,
  `entity_type` enum('room_block','reservation') NOT NULL DEFAULT 'room_block',
  `entity_id` bigint(20) NOT NULL,
  `link_status` enum('linked','ignored') NOT NULL DEFAULT 'linked',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ota_ical_feed`
--

CREATE TABLE `ota_ical_feed` (
  `id_ota_ical_feed` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `id_room` bigint(20) DEFAULT NULL,
  `id_category` bigint(20) DEFAULT NULL,
  `platform` enum('airbnb','booking','expedia','vrbo','otro') NOT NULL DEFAULT 'otro',
  `feed_name` varchar(255) DEFAULT NULL,
  `import_url` varchar(1000) DEFAULT NULL,
  `import_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `import_ignore_our_uids` tinyint(1) NOT NULL DEFAULT 1,
  `export_token` char(32) DEFAULT NULL,
  `export_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `export_summary_mode` enum('reserved','reservation_code','guest_name') NOT NULL DEFAULT 'reserved',
  `export_include_reservations` tinyint(1) NOT NULL DEFAULT 1,
  `export_include_room_blocks` tinyint(1) NOT NULL DEFAULT 1,
  `timezone` varchar(64) DEFAULT NULL,
  `sync_interval_minutes` int(11) NOT NULL DEFAULT 30,
  `last_sync_at` datetime DEFAULT NULL,
  `last_success_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `http_etag` varchar(255) DEFAULT NULL,
  `http_last_modified` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_ota_account` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `ota_ical_feed`
--
DELIMITER $$
CREATE TRIGGER `trg_ota_ical_feed_bi` BEFORE INSERT ON `ota_ical_feed` FOR EACH ROW BEGIN
  -- Validar scope: exactamente uno (room o category)
  IF (NEW.id_room IS NULL AND NEW.id_category IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota_ical_feed: Debes definir id_room o id_category (uno de los dos).';
  END IF;

  IF (NEW.id_room IS NOT NULL AND NEW.id_category IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota_ical_feed: No puedes definir id_room e id_category al mismo tiempo.';
  END IF;

  -- Si se habilita export y no hay token, generarlo
  IF (NEW.export_enabled = 1 AND (NEW.export_token IS NULL OR NEW.export_token = '')) THEN
    SET NEW.export_token = REPLACE(UUID(), '-', '');
  END IF;

  -- Si se habilita import, import_url es obligatorio
  IF (NEW.import_enabled = 1 AND (NEW.import_url IS NULL OR NEW.import_url = '')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota_ical_feed: import_url es obligatorio cuando import_enabled=1.';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_ota_ical_feed_bu` BEFORE UPDATE ON `ota_ical_feed` FOR EACH ROW BEGIN
  IF (NEW.id_room IS NULL AND NEW.id_category IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota_ical_feed: Debes definir id_room o id_category (uno de los dos).';
  END IF;

  IF (NEW.id_room IS NOT NULL AND NEW.id_category IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota_ical_feed: No puedes definir id_room e id_category al mismo tiempo.';
  END IF;

  IF (NEW.export_enabled = 1 AND (NEW.export_token IS NULL OR NEW.export_token = '')) THEN
    SET NEW.export_token = REPLACE(UUID(), '-', '');
  END IF;

  IF (NEW.import_enabled = 1 AND (NEW.import_url IS NULL OR NEW.import_url = '')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota_ical_feed: import_url es obligatorio cuando import_enabled=1.';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `permission`
--

CREATE TABLE `permission` (
  `id_permission` bigint(20) NOT NULL,
  `code` varchar(100) NOT NULL,
  `permission_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `resource` varchar(255) DEFAULT NULL,
  `action` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pms_company_theme`
--

CREATE TABLE `pms_company_theme` (
  `id_company` bigint(20) NOT NULL,
  `theme_code` varchar(32) NOT NULL DEFAULT 'default',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pms_settings`
--

CREATE TABLE `pms_settings` (
  `id_setting` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `pricing_strategy` varchar(32) NOT NULL DEFAULT 'use_bases',
  `google_drive_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `google_drive_client_id` varchar(255) DEFAULT NULL,
  `google_drive_client_secret` text DEFAULT NULL,
  `google_drive_refresh_token` text DEFAULT NULL,
  `google_drive_folder_id` varchar(255) DEFAULT NULL,
  `google_drive_spreadsheet_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pms_settings_interest_catalog`
--

CREATE TABLE `pms_settings_interest_catalog` (
  `id_setting_interest` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `id_sale_item_catalog` bigint(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pms_settings_lodging_catalog`
--

CREATE TABLE `pms_settings_lodging_catalog` (
  `id_setting_lodging` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `id_sale_item_catalog` bigint(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pms_settings_obligation_payment_method`
--

CREATE TABLE `pms_settings_obligation_payment_method` (
  `id_obligation_payment_method` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `method_name` varchar(120) NOT NULL,
  `method_description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pms_settings_payment_catalog`
--

CREATE TABLE `pms_settings_payment_catalog` (
  `id_setting_payment` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `id_sale_item_catalog` bigint(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pms_settings_payment_method`
--

CREATE TABLE `pms_settings_payment_method` (
  `id_payment_method` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `method_name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property`
--

CREATE TABLE `property` (
  `id_property` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(120) DEFAULT NULL,
  `postal_code` varchar(30) DEFAULT NULL,
  `country` varchar(120) DEFAULT NULL,
  `latitude` decimal(12,8) DEFAULT NULL,
  `longitude` decimal(12,8) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT 'America/Mexico_City',
  `currency` varchar(10) DEFAULT 'MXN',
  `check_out_time` timestamp NOT NULL,
  `id_owner_payment_obligation_catalog` bigint(20) DEFAULT NULL,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `default_language` varchar(10) DEFAULT 'es',
  `cancellation_policy_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cancellation_policy_json`)),
  `house_rules_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`house_rules_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_amenities`
--

CREATE TABLE `property_amenities` (
  `id_property_amenities` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `has_wifi` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Wi-Fi (áreas comunes o todo el hospedaje)',
  `has_parking` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Estacionamiento (gratuito o privado)',
  `has_shared_kitchen` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Cocina compartida / común',
  `has_dining_area` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Zona de comedor / desayunador',
  `has_cleaning_service` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Servicio de limpieza',
  `has_shared_laundry` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Lavadora/secadora compartida',
  `has_purified_water` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Agua purificada disponible',
  `has_security_24h` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Seguridad 24h / cámaras',
  `has_self_checkin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Recepción o check-in autónomo',
  `has_pool` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Piscina',
  `has_jacuzzi` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Jacuzzi / hidromasaje',
  `has_garden_patio` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Jardín o patio',
  `has_terrace_rooftop` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Terraza / rooftop',
  `has_hammocks_loungers` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Hamacas o camastros',
  `has_bbq_area` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Zona de parrilla / BBQ',
  `has_beach_access` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Acceso directo a la playa',
  `has_panoramic_views` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Vistas panorámicas (mar, montaña, ciudad)',
  `has_outdoor_lounge` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Áreas de descanso / lounge exterior',
  `offers_airport_transfers` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Traslados al aeropuerto',
  `offers_tours_activities` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Tours y actividades',
  `has_breakfast_available` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Desayuno incluido o disponible',
  `offers_bike_rental` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Alquiler de bicicletas / equipo deportivo',
  `has_luggage_storage` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Guardaequipaje',
  `is_pet_friendly` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Pet-friendly',
  `has_accessible_spaces` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Espacios accesibles movilidad reducida',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rateplan`
--

CREATE TABLE `rateplan` (
  `id_rateplan` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'MXN',
  `refundable` tinyint(1) NOT NULL DEFAULT 1,
  `cancel_policy_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cancel_policy_json`)),
  `deposit_policy_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deposit_policy_json`)),
  `rules_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rules_json`)),
  `min_stay_default` int(11) DEFAULT NULL,
  `max_stay_default` int(11) DEFAULT NULL,
  `cta_default` tinyint(1) DEFAULT NULL,
  `ctd_default` tinyint(1) DEFAULT NULL,
  `stop_sell_default` tinyint(1) DEFAULT NULL,
  `effective_from` date NOT NULL DEFAULT curdate(),
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rateplan_modifier`
--

CREATE TABLE `rateplan_modifier` (
  `id_rateplan_modifier` bigint(20) NOT NULL,
  `id_rateplan` bigint(20) NOT NULL,
  `modifier_name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `apply_mode` enum('stack','best_for_guest','best_for_property','override') NOT NULL DEFAULT 'stack',
  `price_action` enum('add_pct','add_cents','set_price') NOT NULL DEFAULT 'add_pct',
  `add_pct` decimal(8,3) DEFAULT NULL,
  `add_cents` int(11) DEFAULT NULL,
  `set_price_cents` int(11) DEFAULT NULL,
  `clamp_min_cents` int(11) DEFAULT NULL,
  `clamp_max_cents` int(11) DEFAULT NULL,
  `respect_category_min` tinyint(1) NOT NULL DEFAULT 1,
  `is_always_on` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rateplan_modifier_condition`
--

CREATE TABLE `rateplan_modifier_condition` (
  `id_rateplan_modifier_condition` bigint(20) NOT NULL,
  `id_rateplan_modifier` bigint(20) NOT NULL,
  `condition_type` varchar(64) NOT NULL,
  `operator_key` varchar(16) NOT NULL DEFAULT 'eq',
  `value_number` decimal(12,4) DEFAULT NULL,
  `value_number_to` decimal(12,4) DEFAULT NULL,
  `value_text` varchar(255) DEFAULT NULL,
  `value_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `rateplan_modifier_schedule`
--

CREATE TABLE `rateplan_modifier_schedule` (
  `id_rateplan_modifier_schedule` bigint(20) NOT NULL,
  `id_rateplan_modifier` bigint(20) NOT NULL,
  `schedule_type` enum('range','rrule') NOT NULL DEFAULT 'range',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `schedule_rrule` varchar(255) DEFAULT NULL,
  `exdates_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `rateplan_modifier_scope`
--

CREATE TABLE `rateplan_modifier_scope` (
  `id_rateplan_modifier_scope` bigint(20) NOT NULL,
  `id_rateplan_modifier` bigint(20) NOT NULL,
  `id_category` bigint(20) DEFAULT NULL,
  `id_room` bigint(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rateplan_override`
--

CREATE TABLE `rateplan_override` (
  `id_rateplan_override` bigint(20) NOT NULL,
  `id_rateplan` bigint(20) NOT NULL,
  `id_category` bigint(20) DEFAULT NULL,
  `id_room` bigint(20) DEFAULT NULL,
  `override_date` date NOT NULL,
  `price_cents` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rateplan_pricing`
--

CREATE TABLE `rateplan_pricing` (
  `id_rateplan_pricing` bigint(20) NOT NULL,
  `id_rateplan` bigint(20) NOT NULL,
  `base_adjust_pct` decimal(6,2) NOT NULL DEFAULT 0.00,
  `use_season` tinyint(4) NOT NULL DEFAULT 1,
  `use_occupancy` tinyint(4) NOT NULL DEFAULT 1,
  `occupancy_low_threshold` decimal(6,2) NOT NULL DEFAULT 40.00,
  `occupancy_mid_low_threshold` decimal(6,2) NOT NULL DEFAULT 55.00,
  `occupancy_mid_high_threshold` decimal(6,2) NOT NULL DEFAULT 70.00,
  `occupancy_high_threshold` decimal(6,2) NOT NULL DEFAULT 80.00,
  `low_occupancy_adjust_pct` decimal(6,2) NOT NULL DEFAULT -15.00,
  `mid_low_occupancy_adjust_pct` decimal(6,2) NOT NULL DEFAULT -5.00,
  `mid_high_occupancy_adjust_pct` decimal(6,2) NOT NULL DEFAULT 10.00,
  `high_occupancy_adjust_pct` decimal(6,2) NOT NULL DEFAULT 20.00,
  `weekend_adjust_pct` decimal(6,2) NOT NULL DEFAULT 0.00,
  `max_discount_pct` decimal(6,2) NOT NULL DEFAULT 30.00,
  `max_markup_pct` decimal(6,2) NOT NULL DEFAULT 40.00,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rateplan_season`
--

CREATE TABLE `rateplan_season` (
  `id_rateplan_season` bigint(20) NOT NULL,
  `id_rateplan` bigint(20) NOT NULL,
  `season_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `adjust_pct` decimal(6,2) NOT NULL DEFAULT 0.00,
  `priority` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refund`
--

CREATE TABLE `refund` (
  `id_refund` bigint(20) NOT NULL,
  `id_payment` bigint(20) NOT NULL,
  `id_user` bigint(20) DEFAULT NULL,
  `amount_cents` int(11) NOT NULL,
  `currency` varchar(10) DEFAULT 'MXN',
  `reference` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `refunded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_config`
--

CREATE TABLE `report_config` (
  `id_report_config` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `report_key` varchar(64) NOT NULL,
  `report_name` varchar(120) DEFAULT NULL,
  `report_type` varchar(32) NOT NULL DEFAULT 'reservation',
  `line_item_type_scope` varchar(32) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `column_order` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_config_column`
--

CREATE TABLE `report_config_column` (
  `id_report_config_column` bigint(20) NOT NULL,
  `id_report_config` bigint(20) NOT NULL,
  `column_key` varchar(160) NOT NULL,
  `column_source` varchar(32) NOT NULL DEFAULT 'field',
  `source_field_key` varchar(120) DEFAULT NULL,
  `id_line_item_catalog` bigint(20) DEFAULT NULL,
  `display_name` varchar(160) NOT NULL,
  `display_category` varchar(80) DEFAULT NULL,
  `data_type` varchar(32) NOT NULL DEFAULT 'text',
  `aggregation` varchar(32) NOT NULL DEFAULT 'none',
  `format_hint` varchar(64) DEFAULT NULL,
  `order_index` int(11) NOT NULL DEFAULT 1,
  `is_visible` tinyint(4) NOT NULL DEFAULT 1,
  `is_filterable` tinyint(4) NOT NULL DEFAULT 1,
  `filter_operator_default` varchar(32) DEFAULT NULL,
  `legacy_role` varchar(32) DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_config_filter`
--

CREATE TABLE `report_config_filter` (
  `id_report_config_filter` bigint(20) NOT NULL,
  `id_report_config` bigint(20) NOT NULL,
  `filter_key` varchar(160) NOT NULL,
  `operator_key` varchar(32) NOT NULL DEFAULT 'eq',
  `value_text` text DEFAULT NULL,
  `value_from_text` varchar(255) DEFAULT NULL,
  `value_to_text` varchar(255) DEFAULT NULL,
  `value_list_text` text DEFAULT NULL,
  `logic_join` varchar(8) NOT NULL DEFAULT 'AND',
  `order_index` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_config_item_legacy`
--

CREATE TABLE `report_config_item_legacy` (
  `id_report_config_item` bigint(20) NOT NULL,
  `id_report_config` bigint(20) NOT NULL,
  `id_sale_item_catalog` bigint(20) NOT NULL,
  `role` varchar(32) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_field_catalog`
--

CREATE TABLE `report_field_catalog` (
  `id_report_field_catalog` bigint(20) NOT NULL,
  `report_type` varchar(32) NOT NULL,
  `field_key` varchar(120) NOT NULL,
  `field_label` varchar(160) NOT NULL,
  `field_group` varchar(80) NOT NULL,
  `data_type` varchar(32) NOT NULL DEFAULT 'text',
  `supports_filter` tinyint(4) NOT NULL DEFAULT 1,
  `supports_sort` tinyint(4) NOT NULL DEFAULT 1,
  `is_default` tinyint(4) NOT NULL DEFAULT 0,
  `default_order` int(11) NOT NULL DEFAULT 0,
  `select_expression` varchar(255) NOT NULL,
  `filter_expression` varchar(255) DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation`
--

CREATE TABLE `reservation` (
  `id_reservation` bigint(20) NOT NULL,
  `id_user` bigint(20) NOT NULL,
  `id_guest` bigint(20) DEFAULT NULL,
  `id_room` bigint(20) DEFAULT NULL,
  `id_property` bigint(20) NOT NULL,
  `id_category` bigint(20) DEFAULT NULL,
  `id_rateplan` bigint(20) DEFAULT NULL,
  `code` varchar(100) NOT NULL,
  `status` enum('apartado','confirmado','en casa','salida','no-show','cancelada') NOT NULL DEFAULT 'confirmado',
  `source` varchar(120) DEFAULT NULL,
  `id_ota_account` bigint(20) DEFAULT NULL,
  `id_reservation_source` bigint(20) DEFAULT NULL,
  `channel_ref` varchar(255) DEFAULT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `nights` int(11) GENERATED ALWAYS AS (greatest(0,to_days(`check_out_date`) - to_days(`check_in_date`))) STORED,
  `eta` time DEFAULT NULL,
  `etd` time DEFAULT NULL,
  `checkin_at` datetime DEFAULT NULL,
  `checkout_at` datetime DEFAULT NULL,
  `adults` int(11) DEFAULT 2,
  `children` int(11) DEFAULT 0,
  `infants` int(11) DEFAULT 0,
  `currency` varchar(10) DEFAULT 'MXN',
  `total_price_cents` int(11) DEFAULT 0,
  `balance_due_cents` int(11) DEFAULT 0,
  `deposit_due_cents` int(11) DEFAULT 0,
  `deposit_due_at` datetime DEFAULT NULL,
  `rate_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rate_snapshot_json`)),
  `price_breakdown_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`price_breakdown_json`)),
  `cancel_reason` text DEFAULT NULL,
  `canceled_at` datetime DEFAULT NULL,
  `hold_until` datetime DEFAULT NULL,
  `notes_guest` text DEFAULT NULL,
  `notes_internal` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_group`
--

CREATE TABLE `reservation_group` (
  `id_reservation_group` bigint(20) NOT NULL,
  `id_company` bigint(20) DEFAULT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `group_code` varchar(100) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `arrival_window_start` date DEFAULT NULL,
  `arrival_window_end` date DEFAULT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(100) DEFAULT NULL,
  `billing_notes` text DEFAULT NULL,
  `rooming_list_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rooming_list_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_group_member`
--

CREATE TABLE `reservation_group_member` (
  `id_reservation_group_member` bigint(20) NOT NULL,
  `id_reservation_group` bigint(20) NOT NULL,
  `id_reservation` bigint(20) NOT NULL,
  `role_in_group` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_interest`
--

CREATE TABLE `reservation_interest` (
  `id_reservation` bigint(20) NOT NULL,
  `id_sale_item_catalog` bigint(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_message_log`
--

CREATE TABLE `reservation_message_log` (
  `id_reservation_message_log` bigint(20) NOT NULL,
  `id_reservation` bigint(20) NOT NULL,
  `id_message_template` bigint(20) NOT NULL,
  `sent_at` datetime NOT NULL,
  `sent_by` bigint(20) DEFAULT NULL,
  `sent_to_phone` varchar(32) DEFAULT NULL,
  `message_title` varchar(255) NOT NULL,
  `message_body` text NOT NULL,
  `channel` varchar(32) NOT NULL DEFAULT 'whatsapp',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_note`
--

CREATE TABLE `reservation_note` (
  `id_reservation_note` bigint(20) NOT NULL,
  `id_reservation` bigint(20) NOT NULL,
  `note_type` varchar(16) NOT NULL DEFAULT 'internal',
  `note_text` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_source_catalog`
--

CREATE TABLE `reservation_source_catalog` (
  `id_reservation_source` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `source_name` varchar(120) NOT NULL,
  `source_code` varchar(24) DEFAULT NULL,
  `color_hex` varchar(16) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `id_role` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permission`
--

CREATE TABLE `role_permission` (
  `id_role_permission` bigint(20) NOT NULL,
  `id_role` bigint(20) NOT NULL,
  `id_permission` bigint(20) NOT NULL,
  `allow` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `id_room` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `id_roomin` bigint(20) DEFAULT NULL,
  `id_category` bigint(20) DEFAULT NULL,
  `id_rateplan` bigint(20) DEFAULT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `capacity_total` int(11) DEFAULT NULL,
  `max_adults` int(11) DEFAULT NULL,
  `max_children` int(11) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'vacant' COMMENT 'original: room_status_enum',
  `housekeeping_status` varchar(32) DEFAULT 'clean' COMMENT 'original: hk_status_enum',
  `floor` varchar(64) DEFAULT NULL,
  `building` varchar(120) DEFAULT NULL,
  `bed_config` varchar(255) DEFAULT NULL,
  `amenities_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amenities_json`)),
  `color_hex` varchar(16) DEFAULT NULL,
  `order_index` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roomcategory`
--

CREATE TABLE `roomcategory` (
  `id_category` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `id_rateplan` bigint(20) DEFAULT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `base_occupancy` int(11) DEFAULT 2,
  `max_occupancy` int(11) DEFAULT 2,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `default_base_price_cents` int(11) DEFAULT NULL,
  `min_price_cents` int(11) DEFAULT NULL,
  `default_floor_cents` int(11) DEFAULT NULL,
  `default_ceil_cents` int(11) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `color_hex` varchar(16) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `room_block`
--

CREATE TABLE `room_block` (
  `id_room_block` bigint(20) NOT NULL,
  `id_room` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `id_user` bigint(20) DEFAULT NULL,
  `code` varchar(64) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_item_category`
--

CREATE TABLE `sale_item_category` (
  `id_sale_item_category` bigint(20) NOT NULL,
  `id_company` bigint(20) NOT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `id_parent_sale_item_category` bigint(20) DEFAULT NULL,
  `category_name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tmp_reservation_import_input`
--

CREATE TABLE `tmp_reservation_import_input` (
  `id_import` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `id_room` bigint(20) NOT NULL,
  `guest_name` varchar(255) NOT NULL,
  `amount_raw` varchar(64) DEFAULT NULL,
  `check_in_raw` varchar(20) NOT NULL,
  `check_out_raw` varchar(20) NOT NULL,
  `origin_raw` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tmp_reservation_import_log`
--

CREATE TABLE `tmp_reservation_import_log` (
  `id_log` bigint(20) NOT NULL,
  `id_import` bigint(20) NOT NULL,
  `result_status` enum('inserted','skipped','error') NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `id_property` bigint(20) DEFAULT NULL,
  `id_room` bigint(20) DEFAULT NULL,
  `resolved_room_id` bigint(20) DEFAULT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `check_in_date` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `origin_raw` varchar(120) DEFAULT NULL,
  `source_used` varchar(120) DEFAULT NULL,
  `id_ota_account` bigint(20) DEFAULT NULL,
  `id_reservation_source` bigint(20) DEFAULT NULL,
  `total_override_cents` int(11) DEFAULT NULL,
  `id_reservation` bigint(20) DEFAULT NULL,
  `reservation_code` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_property`
--

CREATE TABLE `user_property` (
  `id_user_property` bigint(20) NOT NULL,
  `id_user` bigint(20) NOT NULL,
  `id_property` bigint(20) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_role`
--

CREATE TABLE `user_role` (
  `id_user_role` bigint(20) NOT NULL,
  `id_user` bigint(20) NOT NULL,
  `id_role` bigint(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity`
--
ALTER TABLE `activity`
  ADD PRIMARY KEY (`id_activity`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_act_company` (`id_company`),
  ADD KEY `idx_act_property` (`id_property`),
  ADD KEY `idx_activity_sale_item_catalog` (`id_sale_item_catalog`);

--
-- Indexes for table `activity_booking`
--
ALTER TABLE `activity_booking`
  ADD PRIMARY KEY (`id_booking`),
  ADD KEY `idx_ab_activity` (`id_activity`,`scheduled_at`),
  ADD KEY `idx_ab_res` (`id_reservation`),
  ADD KEY `idx_ab_user` (`created_by`),
  ADD KEY `idx_ab_schedule` (`scheduled_at`),
  ADD KEY `idx_ab_status` (`status`,`scheduled_at`);

--
-- Indexes for table `activity_booking_reservation`
--
ALTER TABLE `activity_booking_reservation`
  ADD PRIMARY KEY (`id_booking`,`id_reservation`),
  ADD KEY `idx_abr_booking` (`id_booking`),
  ADD KEY `idx_abr_reservation` (`id_reservation`),
  ADD KEY `idx_abr_active` (`is_active`,`deleted_at`),
  ADD KEY `fk_abr_created_by` (`created_by`);

--
-- Indexes for table `app_user`
--
ALTER TABLE `app_user`
  ADD PRIMARY KEY (`id_user`),
  ADD KEY `fk_app_user_company` (`id_company`),
  ADD KEY `fk_app_user_id_reg` (`id_reg`);

--
-- Indexes for table `category_amenities`
--
ALTER TABLE `category_amenities`
  ADD PRIMARY KEY (`id_category_amenities`),
  ADD UNIQUE KEY `uk_catamen_category` (`id_category`),
  ADD KEY `fk_catamen_created_by` (`created_by`);

--
-- Indexes for table `category_bed_config`
--
ALTER TABLE `category_bed_config`
  ADD PRIMARY KEY (`id_bed_config`),
  ADD KEY `idx_category_bed_config_category` (`id_category`);

--
-- Indexes for table `category_calendar_amenity_display`
--
ALTER TABLE `category_calendar_amenity_display`
  ADD PRIMARY KEY (`id_category_calendar_amenity_display`),
  ADD UNIQUE KEY `uq_category_calendar_amenity` (`id_category`,`amenity_key`),
  ADD KEY `idx_category_calendar_order` (`id_category`,`display_order`);

--
-- Indexes for table `company`
--
ALTER TABLE `company`
  ADD PRIMARY KEY (`id_company`),
  ADD UNIQUE KEY `uk_company_code` (`code`);

--
-- Indexes for table `folio`
--
ALTER TABLE `folio`
  ADD PRIMARY KEY (`id_folio`),
  ADD KEY `fk_folio_reservation` (`id_reservation`),
  ADD KEY `fk_folio_created_by` (`created_by`);

--
-- Indexes for table `guest`
--
ALTER TABLE `guest`
  ADD PRIMARY KEY (`id_guest`),
  ADD UNIQUE KEY `uk_guest_email` (`email`),
  ADD KEY `fk_guest_user` (`id_user`),
  ADD KEY `fk_guest_created_by` (`created_by`);

--
-- Indexes for table `line_item`
--
ALTER TABLE `line_item`
  ADD PRIMARY KEY (`id_line_item`),
  ADD KEY `idx_li_type` (`item_type`),
  ADD KEY `idx_li_folio` (`id_folio`),
  ADD KEY `idx_li_line_item_catalog` (`id_line_item_catalog`),
  ADD KEY `idx_li_user` (`id_user`),
  ADD KEY `idx_li_created_by` (`created_by`);

--
-- Indexes for table `line_item_catalog`
--
ALTER TABLE `line_item_catalog`
  ADD PRIMARY KEY (`id_line_item_catalog`),
  ADD KEY `idx_lic_type` (`catalog_type`),
  ADD KEY `idx_lic_category` (`id_category`);

--
-- Indexes for table `line_item_catalog_calc`
--
ALTER TABLE `line_item_catalog_calc`
  ADD PRIMARY KEY (`id_line_item_catalog`,`id_parent_line_item_catalog`,`id_component_line_item_catalog`),
  ADD KEY `idx_licc_parent` (`id_parent_line_item_catalog`),
  ADD KEY `idx_licc_component` (`id_component_line_item_catalog`);

--
-- Indexes for table `line_item_catalog_parent`
--
ALTER TABLE `line_item_catalog_parent`
  ADD PRIMARY KEY (`id_sale_item_catalog`,`id_parent_sale_item_catalog`),
  ADD KEY `fk_licp_parent` (`id_parent_sale_item_catalog`),
  ADD KEY `fk_licp_child` (`id_sale_item_catalog`);

--
-- Indexes for table `line_item_hierarchy`
--
ALTER TABLE `line_item_hierarchy`
  ADD PRIMARY KEY (`id_line_item_hierarchy`),
  ADD UNIQUE KEY `uq_line_item_hierarchy_child` (`id_line_item_child`),
  ADD KEY `idx_line_item_hierarchy_parent` (`id_line_item_parent`),
  ADD KEY `idx_line_item_hierarchy_active` (`is_active`,`deleted_at`);

--
-- Indexes for table `message_template`
--
ALTER TABLE `message_template`
  ADD PRIMARY KEY (`id_message_template`),
  ADD UNIQUE KEY `uk_message_template_company_code` (`id_company`,`code`),
  ADD KEY `idx_message_template_property` (`id_property`);

--
-- Indexes for table `obligation_payment_log`
--
ALTER TABLE `obligation_payment_log`
  ADD PRIMARY KEY (`id_obligation_payment_log`),
  ADD KEY `idx_opl_company_created` (`id_company`,`created_at`),
  ADD KEY `idx_opl_line_item` (`id_line_item`,`created_at`),
  ADD KEY `idx_opl_reservation` (`id_reservation`,`created_at`),
  ADD KEY `idx_opl_method` (`id_obligation_payment_method`,`created_at`);

--
-- Indexes for table `occupancy_snapshot`
--
ALTER TABLE `occupancy_snapshot`
  ADD PRIMARY KEY (`id_occupancy_snapshot`),
  ADD KEY `idx_os_property_date` (`id_property`,`snapshot_date`),
  ADD KEY `idx_os_property_category_date` (`id_property`,`id_category`,`snapshot_date`),
  ADD KEY `fk_os_category` (`id_category`);

--
-- Indexes for table `ota_account`
--
ALTER TABLE `ota_account`
  ADD PRIMARY KEY (`id_ota_account`),
  ADD KEY `idx_ota_account_company` (`id_company`,`is_active`,`deleted_at`),
  ADD KEY `idx_ota_account_property` (`id_property`,`is_active`,`deleted_at`),
  ADD KEY `idx_ota_account_platform` (`platform`,`is_active`,`deleted_at`),
  ADD KEY `idx_ota_account_service_fee_catalog` (`id_service_fee_payment_catalog`);

--
-- Indexes for table `ota_account_info_catalog`
--
ALTER TABLE `ota_account_info_catalog`
  ADD PRIMARY KEY (`id_ota_account_info_catalog`),
  ADD UNIQUE KEY `uq_ota_account_info_catalog` (`id_ota_account`,`id_line_item_catalog`),
  ADD KEY `idx_ota_account_info_catalog_ota` (`id_ota_account`,`is_active`,`deleted_at`),
  ADD KEY `idx_ota_account_info_catalog_catalog` (`id_line_item_catalog`,`is_active`,`deleted_at`);

--
-- Indexes for table `ota_account_lodging_catalog`
--
ALTER TABLE `ota_account_lodging_catalog`
  ADD PRIMARY KEY (`id_ota_account_lodging_catalog`),
  ADD UNIQUE KEY `uq_ota_account_lodging` (`id_ota_account`,`id_line_item_catalog`),
  ADD KEY `idx_ota_account_lodging_ota` (`id_ota_account`,`is_active`,`deleted_at`),
  ADD KEY `idx_ota_account_lodging_catalog` (`id_line_item_catalog`,`is_active`,`deleted_at`);

--
-- Indexes for table `ota_ical_event`
--
ALTER TABLE `ota_ical_event`
  ADD PRIMARY KEY (`id_ota_ical_event`),
  ADD UNIQUE KEY `uk_ota_ical_event_uid` (`id_ota_ical_feed`,`uid`),
  ADD KEY `idx_ota_ical_event_range` (`start_date`,`end_date`);

--
-- Indexes for table `ota_ical_event_map`
--
ALTER TABLE `ota_ical_event_map`
  ADD PRIMARY KEY (`id_ota_ical_event_map`),
  ADD UNIQUE KEY `uk_ota_ical_event_map_uid` (`id_ota_ical_feed`,`uid`),
  ADD KEY `idx_ota_ical_event_map_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `ota_ical_feed`
--
ALTER TABLE `ota_ical_feed`
  ADD PRIMARY KEY (`id_ota_ical_feed`),
  ADD UNIQUE KEY `uk_ota_ical_feed_export_token` (`export_token`),
  ADD UNIQUE KEY `uk_ota_ical_feed_room_platform` (`id_room`,`platform`),
  ADD UNIQUE KEY `uk_ota_ical_feed_category_platform` (`id_category`,`platform`),
  ADD KEY `idx_ota_ical_feed_company` (`id_company`),
  ADD KEY `idx_ota_ical_feed_property` (`id_property`),
  ADD KEY `idx_ota_ical_feed_platform` (`platform`),
  ADD KEY `fk_ota_ical_feed_created_by` (`created_by`),
  ADD KEY `idx_ota_ical_feed_ota_account` (`id_ota_account`);

--
-- Indexes for table `permission`
--
ALTER TABLE `permission`
  ADD PRIMARY KEY (`id_permission`),
  ADD UNIQUE KEY `uk_permission_code` (`code`),
  ADD KEY `fk_permission_created_by` (`created_by`);

--
-- Indexes for table `pms_company_theme`
--
ALTER TABLE `pms_company_theme`
  ADD PRIMARY KEY (`id_company`);

--
-- Indexes for table `pms_settings`
--
ALTER TABLE `pms_settings`
  ADD PRIMARY KEY (`id_setting`),
  ADD KEY `idx_ps_company_property` (`id_company`,`id_property`),
  ADD KEY `idx_pms_settings_scope` (`id_company`,`id_property`,`id_setting`);

--
-- Indexes for table `pms_settings_interest_catalog`
--
ALTER TABLE `pms_settings_interest_catalog`
  ADD PRIMARY KEY (`id_setting_interest`),
  ADD UNIQUE KEY `uq_psic_company_property_catalog` (`id_company`,`id_property`,`id_sale_item_catalog`),
  ADD KEY `idx_psic_company_property` (`id_company`,`id_property`),
  ADD KEY `idx_psic_catalog` (`id_sale_item_catalog`);

--
-- Indexes for table `pms_settings_lodging_catalog`
--
ALTER TABLE `pms_settings_lodging_catalog`
  ADD PRIMARY KEY (`id_setting_lodging`),
  ADD UNIQUE KEY `uq_pslc_company_property_catalog` (`id_company`,`id_property`,`id_sale_item_catalog`),
  ADD KEY `idx_pslc_company_property` (`id_company`,`id_property`),
  ADD KEY `idx_pslc_catalog` (`id_sale_item_catalog`);

--
-- Indexes for table `pms_settings_obligation_payment_method`
--
ALTER TABLE `pms_settings_obligation_payment_method`
  ADD PRIMARY KEY (`id_obligation_payment_method`),
  ADD KEY `idx_psopm_company_active` (`id_company`,`is_active`,`deleted_at`),
  ADD KEY `idx_psopm_name` (`id_company`,`method_name`);

--
-- Indexes for table `pms_settings_payment_catalog`
--
ALTER TABLE `pms_settings_payment_catalog`
  ADD PRIMARY KEY (`id_setting_payment`),
  ADD UNIQUE KEY `uq_pspc_company_property_catalog` (`id_company`,`id_property`,`id_sale_item_catalog`),
  ADD KEY `idx_pspc_company_property` (`id_company`,`id_property`),
  ADD KEY `idx_pspc_catalog` (`id_sale_item_catalog`);

--
-- Indexes for table `pms_settings_payment_method`
--
ALTER TABLE `pms_settings_payment_method`
  ADD PRIMARY KEY (`id_payment_method`),
  ADD KEY `idx_pspm_company_property_active` (`id_company`,`id_property`,`is_active`),
  ADD KEY `idx_pspm_method_name` (`method_name`);

--
-- Indexes for table `property`
--
ALTER TABLE `property`
  ADD PRIMARY KEY (`id_property`),
  ADD KEY `fk_property_company` (`id_company`),
  ADD KEY `idx_property_owner_payment_obligation_catalog` (`id_owner_payment_obligation_catalog`);

--
-- Indexes for table `property_amenities`
--
ALTER TABLE `property_amenities`
  ADD PRIMARY KEY (`id_property_amenities`),
  ADD UNIQUE KEY `uk_propamen_property` (`id_property`),
  ADD KEY `fk_propamen_created_by` (`created_by`);

--
-- Indexes for table `rateplan`
--
ALTER TABLE `rateplan`
  ADD PRIMARY KEY (`id_rateplan`),
  ADD KEY `fk_rateplan_property` (`id_property`),
  ADD KEY `fk_rateplan_created_by` (`created_by`);

--
-- Indexes for table `rateplan_modifier`
--
ALTER TABLE `rateplan_modifier`
  ADD PRIMARY KEY (`id_rateplan_modifier`),
  ADD KEY `idx_rpm_rateplan` (`id_rateplan`),
  ADD KEY `idx_rpm_active` (`is_active`,`deleted_at`),
  ADD KEY `idx_rpm_priority` (`priority`);

--
-- Indexes for table `rateplan_modifier_condition`
--
ALTER TABLE `rateplan_modifier_condition`
  ADD PRIMARY KEY (`id_rateplan_modifier_condition`),
  ADD KEY `idx_rpmc_modifier` (`id_rateplan_modifier`),
  ADD KEY `idx_rpmc_type` (`condition_type`,`operator_key`);

--
-- Indexes for table `rateplan_modifier_schedule`
--
ALTER TABLE `rateplan_modifier_schedule`
  ADD PRIMARY KEY (`id_rateplan_modifier_schedule`),
  ADD KEY `idx_rpms_modifier` (`id_rateplan_modifier`),
  ADD KEY `idx_rpms_type_dates` (`schedule_type`,`start_date`,`end_date`);

--
-- Indexes for table `rateplan_modifier_scope`
--
ALTER TABLE `rateplan_modifier_scope`
  ADD PRIMARY KEY (`id_rateplan_modifier_scope`),
  ADD KEY `idx_rpmsc_modifier` (`id_rateplan_modifier`),
  ADD KEY `idx_rpmsc_category` (`id_category`),
  ADD KEY `idx_rpmsc_room` (`id_room`);

--
-- Indexes for table `rateplan_override`
--
ALTER TABLE `rateplan_override`
  ADD PRIMARY KEY (`id_rateplan_override`),
  ADD KEY `idx_rateplan_override_date` (`id_rateplan`,`override_date`),
  ADD KEY `idx_rateplan_override_category` (`id_category`,`override_date`),
  ADD KEY `idx_rateplan_override_room` (`id_room`,`override_date`);

--
-- Indexes for table `rateplan_pricing`
--
ALTER TABLE `rateplan_pricing`
  ADD PRIMARY KEY (`id_rateplan_pricing`),
  ADD UNIQUE KEY `uk_rateplan_pricing` (`id_rateplan`);

--
-- Indexes for table `rateplan_season`
--
ALTER TABLE `rateplan_season`
  ADD PRIMARY KEY (`id_rateplan_season`),
  ADD KEY `idx_rateplan_season_range` (`id_rateplan`,`start_date`,`end_date`);

--
-- Indexes for table `refund`
--
ALTER TABLE `refund`
  ADD PRIMARY KEY (`id_refund`),
  ADD KEY `fk_refund_payment` (`id_payment`),
  ADD KEY `fk_refund_user` (`id_user`),
  ADD KEY `fk_refund_created_by` (`created_by`);

--
-- Indexes for table `report_config`
--
ALTER TABLE `report_config`
  ADD PRIMARY KEY (`id_report_config`),
  ADD UNIQUE KEY `uk_report_config_company_key` (`id_company`,`report_key`);

--
-- Indexes for table `report_config_column`
--
ALTER TABLE `report_config_column`
  ADD PRIMARY KEY (`id_report_config_column`),
  ADD UNIQUE KEY `uk_report_config_column_key` (`id_report_config`,`column_key`),
  ADD KEY `idx_report_config_column_report` (`id_report_config`),
  ADD KEY `idx_report_config_column_catalog` (`id_line_item_catalog`),
  ADD KEY `idx_report_config_column_active` (`id_report_config`,`is_active`,`order_index`);

--
-- Indexes for table `report_config_filter`
--
ALTER TABLE `report_config_filter`
  ADD PRIMARY KEY (`id_report_config_filter`),
  ADD KEY `idx_report_config_filter_report` (`id_report_config`,`is_active`,`order_index`),
  ADD KEY `idx_report_config_filter_key` (`id_report_config`,`filter_key`);

--
-- Indexes for table `report_config_item_legacy`
--
ALTER TABLE `report_config_item_legacy`
  ADD PRIMARY KEY (`id_report_config_item`),
  ADD UNIQUE KEY `uk_report_config_item` (`id_report_config`,`id_sale_item_catalog`,`role`),
  ADD KEY `idx_report_config_item_report` (`id_report_config`),
  ADD KEY `idx_report_config_item_catalog` (`id_sale_item_catalog`);

--
-- Indexes for table `report_field_catalog`
--
ALTER TABLE `report_field_catalog`
  ADD PRIMARY KEY (`id_report_field_catalog`),
  ADD UNIQUE KEY `uk_report_field_catalog` (`report_type`,`field_key`),
  ADD KEY `idx_report_field_catalog_group` (`report_type`,`field_group`,`is_active`);

--
-- Indexes for table `reservation`
--
ALTER TABLE `reservation`
  ADD PRIMARY KEY (`id_reservation`),
  ADD KEY `fk_res_user` (`id_user`),
  ADD KEY `fk_res_guest` (`id_guest`),
  ADD KEY `fk_res_room` (`id_room`),
  ADD KEY `fk_res_property` (`id_property`),
  ADD KEY `fk_res_category` (`id_category`),
  ADD KEY `fk_res_rateplan` (`id_rateplan`),
  ADD KEY `fk_res_created_by` (`created_by`),
  ADD KEY `idx_reservation_ota_account` (`id_ota_account`),
  ADD KEY `idx_reservation_source_id` (`id_reservation_source`);

--
-- Indexes for table `reservation_group`
--
ALTER TABLE `reservation_group`
  ADD PRIMARY KEY (`id_reservation_group`),
  ADD KEY `fk_resgrp_company` (`id_company`),
  ADD KEY `fk_resgrp_property` (`id_property`),
  ADD KEY `fk_resgrp_created_by` (`created_by`);

--
-- Indexes for table `reservation_group_member`
--
ALTER TABLE `reservation_group_member`
  ADD PRIMARY KEY (`id_reservation_group_member`),
  ADD KEY `fk_resgrpmem_group` (`id_reservation_group`),
  ADD KEY `fk_resgrpmem_res` (`id_reservation`),
  ADD KEY `fk_resgrpmem_created_by` (`created_by`);

--
-- Indexes for table `reservation_interest`
--
ALTER TABLE `reservation_interest`
  ADD PRIMARY KEY (`id_reservation`,`id_sale_item_catalog`),
  ADD KEY `fk_reservation_interest_catalog` (`id_sale_item_catalog`),
  ADD KEY `fk_reservation_interest_created_by` (`created_by`);

--
-- Indexes for table `reservation_message_log`
--
ALTER TABLE `reservation_message_log`
  ADD PRIMARY KEY (`id_reservation_message_log`),
  ADD UNIQUE KEY `uk_reservation_message_template` (`id_reservation`,`id_message_template`),
  ADD KEY `idx_reservation_message_log_res` (`id_reservation`),
  ADD KEY `idx_reservation_message_log_template` (`id_message_template`),
  ADD KEY `fk_reservation_message_log_user` (`sent_by`);

--
-- Indexes for table `reservation_note`
--
ALTER TABLE `reservation_note`
  ADD PRIMARY KEY (`id_reservation_note`),
  ADD KEY `idx_reservation_note_res` (`id_reservation`),
  ADD KEY `idx_reservation_note_type` (`note_type`);

--
-- Indexes for table `reservation_source_catalog`
--
ALTER TABLE `reservation_source_catalog`
  ADD PRIMARY KEY (`id_reservation_source`),
  ADD UNIQUE KEY `uq_reservation_source_scope_name` (`id_company`,`id_property`,`source_name`),
  ADD KEY `idx_reservation_source_company_property_active` (`id_company`,`id_property`,`is_active`,`deleted_at`),
  ADD KEY `fk_reservation_source_property` (`id_property`),
  ADD KEY `idx_reservation_source_company_code` (`id_company`,`source_code`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id_role`),
  ADD KEY `fk_role_property` (`id_property`),
  ADD KEY `fk_role_created_by` (`created_by`);

--
-- Indexes for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD PRIMARY KEY (`id_role_permission`),
  ADD KEY `fk_roleperm_role` (`id_role`),
  ADD KEY `fk_roleperm_permission` (`id_permission`),
  ADD KEY `fk_roleperm_created_by` (`created_by`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`id_room`),
  ADD KEY `fk_room_property` (`id_property`),
  ADD KEY `fk_room_roomin` (`id_roomin`),
  ADD KEY `fk_room_category` (`id_category`),
  ADD KEY `fk_room_rateplan` (`id_rateplan`),
  ADD KEY `fk_room_created_by` (`created_by`);

--
-- Indexes for table `roomcategory`
--
ALTER TABLE `roomcategory`
  ADD PRIMARY KEY (`id_category`),
  ADD KEY `fk_roomcategory_property` (`id_property`),
  ADD KEY `fk_roomcategory_rateplan` (`id_rateplan`),
  ADD KEY `fk_roomcategory_created_by` (`created_by`);

--
-- Indexes for table `room_block`
--
ALTER TABLE `room_block`
  ADD PRIMARY KEY (`id_room_block`),
  ADD KEY `idx_roomblock_room` (`id_room`),
  ADD KEY `idx_roomblock_property` (`id_property`),
  ADD KEY `idx_roomblock_dates` (`start_date`,`end_date`),
  ADD KEY `idx_roomblock_user` (`id_user`);

--
-- Indexes for table `sale_item_category`
--
ALTER TABLE `sale_item_category`
  ADD PRIMARY KEY (`id_sale_item_category`),
  ADD KEY `fk_sicat_company` (`id_company`),
  ADD KEY `fk_sicat_property` (`id_property`),
  ADD KEY `fk_sicat_parent` (`id_parent_sale_item_category`);

--
-- Indexes for table `tmp_reservation_import_input`
--
ALTER TABLE `tmp_reservation_import_input`
  ADD PRIMARY KEY (`id_import`),
  ADD KEY `idx_input_property_room` (`id_property`,`id_room`),
  ADD KEY `idx_input_dates` (`check_in_raw`,`check_out_raw`);

--
-- Indexes for table `tmp_reservation_import_log`
--
ALTER TABLE `tmp_reservation_import_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_log_import` (`id_import`),
  ADD KEY `idx_log_status` (`result_status`);

--
-- Indexes for table `user_property`
--
ALTER TABLE `user_property`
  ADD PRIMARY KEY (`id_user_property`),
  ADD KEY `fk_userprop_user` (`id_user`),
  ADD KEY `fk_userprop_property` (`id_property`),
  ADD KEY `fk_userprop_created_by` (`created_by`);

--
-- Indexes for table `user_role`
--
ALTER TABLE `user_role`
  ADD PRIMARY KEY (`id_user_role`),
  ADD KEY `fk_userrole_user` (`id_user`),
  ADD KEY `fk_userrole_role` (`id_role`),
  ADD KEY `fk_userrole_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity`
--
ALTER TABLE `activity`
  MODIFY `id_activity` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_booking`
--
ALTER TABLE `activity_booking`
  MODIFY `id_booking` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_user`
--
ALTER TABLE `app_user`
  MODIFY `id_user` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_amenities`
--
ALTER TABLE `category_amenities`
  MODIFY `id_category_amenities` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_bed_config`
--
ALTER TABLE `category_bed_config`
  MODIFY `id_bed_config` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_calendar_amenity_display`
--
ALTER TABLE `category_calendar_amenity_display`
  MODIFY `id_category_calendar_amenity_display` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company`
--
ALTER TABLE `company`
  MODIFY `id_company` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folio`
--
ALTER TABLE `folio`
  MODIFY `id_folio` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guest`
--
ALTER TABLE `guest`
  MODIFY `id_guest` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `line_item`
--
ALTER TABLE `line_item`
  MODIFY `id_line_item` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `line_item_catalog`
--
ALTER TABLE `line_item_catalog`
  MODIFY `id_line_item_catalog` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `line_item_hierarchy`
--
ALTER TABLE `line_item_hierarchy`
  MODIFY `id_line_item_hierarchy` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_template`
--
ALTER TABLE `message_template`
  MODIFY `id_message_template` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `obligation_payment_log`
--
ALTER TABLE `obligation_payment_log`
  MODIFY `id_obligation_payment_log` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `occupancy_snapshot`
--
ALTER TABLE `occupancy_snapshot`
  MODIFY `id_occupancy_snapshot` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ota_account`
--
ALTER TABLE `ota_account`
  MODIFY `id_ota_account` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ota_account_info_catalog`
--
ALTER TABLE `ota_account_info_catalog`
  MODIFY `id_ota_account_info_catalog` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ota_account_lodging_catalog`
--
ALTER TABLE `ota_account_lodging_catalog`
  MODIFY `id_ota_account_lodging_catalog` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ota_ical_event`
--
ALTER TABLE `ota_ical_event`
  MODIFY `id_ota_ical_event` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ota_ical_event_map`
--
ALTER TABLE `ota_ical_event_map`
  MODIFY `id_ota_ical_event_map` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ota_ical_feed`
--
ALTER TABLE `ota_ical_feed`
  MODIFY `id_ota_ical_feed` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permission`
--
ALTER TABLE `permission`
  MODIFY `id_permission` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pms_settings`
--
ALTER TABLE `pms_settings`
  MODIFY `id_setting` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pms_settings_interest_catalog`
--
ALTER TABLE `pms_settings_interest_catalog`
  MODIFY `id_setting_interest` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pms_settings_lodging_catalog`
--
ALTER TABLE `pms_settings_lodging_catalog`
  MODIFY `id_setting_lodging` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pms_settings_obligation_payment_method`
--
ALTER TABLE `pms_settings_obligation_payment_method`
  MODIFY `id_obligation_payment_method` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pms_settings_payment_catalog`
--
ALTER TABLE `pms_settings_payment_catalog`
  MODIFY `id_setting_payment` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pms_settings_payment_method`
--
ALTER TABLE `pms_settings_payment_method`
  MODIFY `id_payment_method` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property`
--
ALTER TABLE `property`
  MODIFY `id_property` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property_amenities`
--
ALTER TABLE `property_amenities`
  MODIFY `id_property_amenities` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rateplan`
--
ALTER TABLE `rateplan`
  MODIFY `id_rateplan` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rateplan_modifier`
--
ALTER TABLE `rateplan_modifier`
  MODIFY `id_rateplan_modifier` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rateplan_modifier_condition`
--
ALTER TABLE `rateplan_modifier_condition`
  MODIFY `id_rateplan_modifier_condition` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rateplan_modifier_schedule`
--
ALTER TABLE `rateplan_modifier_schedule`
  MODIFY `id_rateplan_modifier_schedule` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rateplan_modifier_scope`
--
ALTER TABLE `rateplan_modifier_scope`
  MODIFY `id_rateplan_modifier_scope` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rateplan_override`
--
ALTER TABLE `rateplan_override`
  MODIFY `id_rateplan_override` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rateplan_pricing`
--
ALTER TABLE `rateplan_pricing`
  MODIFY `id_rateplan_pricing` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rateplan_season`
--
ALTER TABLE `rateplan_season`
  MODIFY `id_rateplan_season` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refund`
--
ALTER TABLE `refund`
  MODIFY `id_refund` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_config`
--
ALTER TABLE `report_config`
  MODIFY `id_report_config` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_config_column`
--
ALTER TABLE `report_config_column`
  MODIFY `id_report_config_column` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_config_filter`
--
ALTER TABLE `report_config_filter`
  MODIFY `id_report_config_filter` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_config_item_legacy`
--
ALTER TABLE `report_config_item_legacy`
  MODIFY `id_report_config_item` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_field_catalog`
--
ALTER TABLE `report_field_catalog`
  MODIFY `id_report_field_catalog` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation`
--
ALTER TABLE `reservation`
  MODIFY `id_reservation` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_group`
--
ALTER TABLE `reservation_group`
  MODIFY `id_reservation_group` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_group_member`
--
ALTER TABLE `reservation_group_member`
  MODIFY `id_reservation_group_member` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_message_log`
--
ALTER TABLE `reservation_message_log`
  MODIFY `id_reservation_message_log` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_note`
--
ALTER TABLE `reservation_note`
  MODIFY `id_reservation_note` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_source_catalog`
--
ALTER TABLE `reservation_source_catalog`
  MODIFY `id_reservation_source` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `id_role` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permission`
--
ALTER TABLE `role_permission`
  MODIFY `id_role_permission` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `id_room` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roomcategory`
--
ALTER TABLE `roomcategory`
  MODIFY `id_category` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `room_block`
--
ALTER TABLE `room_block`
  MODIFY `id_room_block` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_item_category`
--
ALTER TABLE `sale_item_category`
  MODIFY `id_sale_item_category` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tmp_reservation_import_input`
--
ALTER TABLE `tmp_reservation_import_input`
  MODIFY `id_import` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tmp_reservation_import_log`
--
ALTER TABLE `tmp_reservation_import_log`
  MODIFY `id_log` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_property`
--
ALTER TABLE `user_property`
  MODIFY `id_user_property` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_role`
--
ALTER TABLE `user_role`
  MODIFY `id_user_role` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity`
--
ALTER TABLE `activity`
  ADD CONSTRAINT `fk_act_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_act_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `activity_booking`
--
ALTER TABLE `activity_booking`
  ADD CONSTRAINT `fk_ab_activity` FOREIGN KEY (`id_activity`) REFERENCES `activity` (`id_activity`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ab_user` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activity_booking_reservation` FOREIGN KEY (`id_reservation`) REFERENCES `reservation` (`id_reservation`) ON UPDATE CASCADE;

--
-- Constraints for table `activity_booking_reservation`
--
ALTER TABLE `activity_booking_reservation`
  ADD CONSTRAINT `fk_abr_booking` FOREIGN KEY (`id_booking`) REFERENCES `activity_booking` (`id_booking`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abr_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abr_reservation` FOREIGN KEY (`id_reservation`) REFERENCES `reservation` (`id_reservation`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `app_user`
--
ALTER TABLE `app_user`
  ADD CONSTRAINT `fk_app_user_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`),
  ADD CONSTRAINT `fk_app_user_id_reg` FOREIGN KEY (`id_reg`) REFERENCES `app_user` (`id_user`);

--
-- Constraints for table `category_amenities`
--
ALTER TABLE `category_amenities`
  ADD CONSTRAINT `fk_catamen_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_catamen_roomcategory` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `category_bed_config`
--
ALTER TABLE `category_bed_config`
  ADD CONSTRAINT `fk_category_bed_config_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`) ON DELETE CASCADE;

--
-- Constraints for table `category_calendar_amenity_display`
--
ALTER TABLE `category_calendar_amenity_display`
  ADD CONSTRAINT `fk_category_calendar_display_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `folio`
--
ALTER TABLE `folio`
  ADD CONSTRAINT `fk_folio_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_folio_reservation` FOREIGN KEY (`id_reservation`) REFERENCES `reservation` (`id_reservation`);

--
-- Constraints for table `guest`
--
ALTER TABLE `guest`
  ADD CONSTRAINT `fk_guest_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_guest_user` FOREIGN KEY (`id_user`) REFERENCES `app_user` (`id_user`);

--
-- Constraints for table `message_template`
--
ALTER TABLE `message_template`
  ADD CONSTRAINT `fk_message_template_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`),
  ADD CONSTRAINT `fk_message_template_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`);

--
-- Constraints for table `occupancy_snapshot`
--
ALTER TABLE `occupancy_snapshot`
  ADD CONSTRAINT `fk_os_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_os_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ota_ical_event`
--
ALTER TABLE `ota_ical_event`
  ADD CONSTRAINT `fk_ota_ical_event_feed` FOREIGN KEY (`id_ota_ical_feed`) REFERENCES `ota_ical_feed` (`id_ota_ical_feed`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ota_ical_event_map`
--
ALTER TABLE `ota_ical_event_map`
  ADD CONSTRAINT `fk_ota_ical_event_map_feed` FOREIGN KEY (`id_ota_ical_feed`) REFERENCES `ota_ical_feed` (`id_ota_ical_feed`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ota_ical_feed`
--
ALTER TABLE `ota_ical_feed`
  ADD CONSTRAINT `fk_ota_ical_feed_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ota_ical_feed_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ota_ical_feed_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ota_ical_feed_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ota_ical_feed_room` FOREIGN KEY (`id_room`) REFERENCES `room` (`id_room`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `permission`
--
ALTER TABLE `permission`
  ADD CONSTRAINT `fk_permission_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`);

--
-- Constraints for table `pms_company_theme`
--
ALTER TABLE `pms_company_theme`
  ADD CONSTRAINT `fk_pms_company_theme_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`);

--
-- Constraints for table `property`
--
ALTER TABLE `property`
  ADD CONSTRAINT `fk_property_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`);

--
-- Constraints for table `property_amenities`
--
ALTER TABLE `property_amenities`
  ADD CONSTRAINT `fk_propamen_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_propamen_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `rateplan`
--
ALTER TABLE `rateplan`
  ADD CONSTRAINT `fk_rateplan_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_rateplan_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`);

--
-- Constraints for table `rateplan_modifier`
--
ALTER TABLE `rateplan_modifier`
  ADD CONSTRAINT `fk_rpm_rateplan` FOREIGN KEY (`id_rateplan`) REFERENCES `rateplan` (`id_rateplan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `rateplan_modifier_condition`
--
ALTER TABLE `rateplan_modifier_condition`
  ADD CONSTRAINT `fk_rpmc_modifier` FOREIGN KEY (`id_rateplan_modifier`) REFERENCES `rateplan_modifier` (`id_rateplan_modifier`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `rateplan_modifier_schedule`
--
ALTER TABLE `rateplan_modifier_schedule`
  ADD CONSTRAINT `fk_rpms_modifier` FOREIGN KEY (`id_rateplan_modifier`) REFERENCES `rateplan_modifier` (`id_rateplan_modifier`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `rateplan_modifier_scope`
--
ALTER TABLE `rateplan_modifier_scope`
  ADD CONSTRAINT `fk_rpmsc_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rpmsc_modifier` FOREIGN KEY (`id_rateplan_modifier`) REFERENCES `rateplan_modifier` (`id_rateplan_modifier`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rpmsc_room` FOREIGN KEY (`id_room`) REFERENCES `room` (`id_room`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `rateplan_override`
--
ALTER TABLE `rateplan_override`
  ADD CONSTRAINT `fk_rateplan_override_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`),
  ADD CONSTRAINT `fk_rateplan_override_rateplan` FOREIGN KEY (`id_rateplan`) REFERENCES `rateplan` (`id_rateplan`),
  ADD CONSTRAINT `fk_rateplan_override_room` FOREIGN KEY (`id_room`) REFERENCES `room` (`id_room`);

--
-- Constraints for table `rateplan_pricing`
--
ALTER TABLE `rateplan_pricing`
  ADD CONSTRAINT `fk_rateplan_pricing_rateplan` FOREIGN KEY (`id_rateplan`) REFERENCES `rateplan` (`id_rateplan`);

--
-- Constraints for table `rateplan_season`
--
ALTER TABLE `rateplan_season`
  ADD CONSTRAINT `fk_rateplan_season_rateplan` FOREIGN KEY (`id_rateplan`) REFERENCES `rateplan` (`id_rateplan`);

--
-- Constraints for table `refund`
--
ALTER TABLE `refund`
  ADD CONSTRAINT `fk_refund_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_refund_user` FOREIGN KEY (`id_user`) REFERENCES `app_user` (`id_user`);

--
-- Constraints for table `report_config_column`
--
ALTER TABLE `report_config_column`
  ADD CONSTRAINT `fk_report_config_column_report` FOREIGN KEY (`id_report_config`) REFERENCES `report_config` (`id_report_config`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `report_config_filter`
--
ALTER TABLE `report_config_filter`
  ADD CONSTRAINT `fk_report_config_filter_report` FOREIGN KEY (`id_report_config`) REFERENCES `report_config` (`id_report_config`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `report_config_item_legacy`
--
ALTER TABLE `report_config_item_legacy`
  ADD CONSTRAINT `fk_report_config_item_report` FOREIGN KEY (`id_report_config`) REFERENCES `report_config` (`id_report_config`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reservation`
--
ALTER TABLE `reservation`
  ADD CONSTRAINT `fk_res_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`),
  ADD CONSTRAINT `fk_res_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_res_guest` FOREIGN KEY (`id_guest`) REFERENCES `guest` (`id_guest`),
  ADD CONSTRAINT `fk_res_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`),
  ADD CONSTRAINT `fk_res_rateplan` FOREIGN KEY (`id_rateplan`) REFERENCES `rateplan` (`id_rateplan`),
  ADD CONSTRAINT `fk_res_room` FOREIGN KEY (`id_room`) REFERENCES `room` (`id_room`),
  ADD CONSTRAINT `fk_res_user` FOREIGN KEY (`id_user`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_reservation_source_catalog` FOREIGN KEY (`id_reservation_source`) REFERENCES `reservation_source_catalog` (`id_reservation_source`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `reservation_group`
--
ALTER TABLE `reservation_group`
  ADD CONSTRAINT `fk_resgrp_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`),
  ADD CONSTRAINT `fk_resgrp_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_resgrp_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`);

--
-- Constraints for table `reservation_group_member`
--
ALTER TABLE `reservation_group_member`
  ADD CONSTRAINT `fk_resgrpmem_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_resgrpmem_group` FOREIGN KEY (`id_reservation_group`) REFERENCES `reservation_group` (`id_reservation_group`),
  ADD CONSTRAINT `fk_resgrpmem_res` FOREIGN KEY (`id_reservation`) REFERENCES `reservation` (`id_reservation`);

--
-- Constraints for table `reservation_interest`
--
ALTER TABLE `reservation_interest`
  ADD CONSTRAINT `fk_reservation_interest_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_reservation_interest_reservation` FOREIGN KEY (`id_reservation`) REFERENCES `reservation` (`id_reservation`);

--
-- Constraints for table `reservation_message_log`
--
ALTER TABLE `reservation_message_log`
  ADD CONSTRAINT `fk_reservation_message_log_reservation` FOREIGN KEY (`id_reservation`) REFERENCES `reservation` (`id_reservation`),
  ADD CONSTRAINT `fk_reservation_message_log_template` FOREIGN KEY (`id_message_template`) REFERENCES `message_template` (`id_message_template`),
  ADD CONSTRAINT `fk_reservation_message_log_user` FOREIGN KEY (`sent_by`) REFERENCES `app_user` (`id_user`);

--
-- Constraints for table `reservation_note`
--
ALTER TABLE `reservation_note`
  ADD CONSTRAINT `fk_reservation_note_reservation` FOREIGN KEY (`id_reservation`) REFERENCES `reservation` (`id_reservation`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reservation_source_catalog`
--
ALTER TABLE `reservation_source_catalog`
  ADD CONSTRAINT `fk_reservation_source_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservation_source_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `role`
--
ALTER TABLE `role`
  ADD CONSTRAINT `fk_role_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_role_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`);

--
-- Constraints for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD CONSTRAINT `fk_roleperm_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_roleperm_permission` FOREIGN KEY (`id_permission`) REFERENCES `permission` (`id_permission`),
  ADD CONSTRAINT `fk_roleperm_role` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`);

--
-- Constraints for table `room`
--
ALTER TABLE `room`
  ADD CONSTRAINT `fk_room_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`),
  ADD CONSTRAINT `fk_room_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_room_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`),
  ADD CONSTRAINT `fk_room_rateplan` FOREIGN KEY (`id_rateplan`) REFERENCES `rateplan` (`id_rateplan`),
  ADD CONSTRAINT `fk_room_roomin` FOREIGN KEY (`id_roomin`) REFERENCES `room` (`id_room`);

--
-- Constraints for table `roomcategory`
--
ALTER TABLE `roomcategory`
  ADD CONSTRAINT `fk_roomcategory_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_roomcategory_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`),
  ADD CONSTRAINT `fk_roomcategory_rateplan` FOREIGN KEY (`id_rateplan`) REFERENCES `rateplan` (`id_rateplan`);

--
-- Constraints for table `room_block`
--
ALTER TABLE `room_block`
  ADD CONSTRAINT `fk_roomblock_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_roomblock_room` FOREIGN KEY (`id_room`) REFERENCES `room` (`id_room`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_roomblock_user` FOREIGN KEY (`id_user`) REFERENCES `app_user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sale_item_category`
--
ALTER TABLE `sale_item_category`
  ADD CONSTRAINT `fk_sicat_parent` FOREIGN KEY (`id_parent_sale_item_category`) REFERENCES `sale_item_category` (`id_sale_item_category`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `user_property`
--
ALTER TABLE `user_property`
  ADD CONSTRAINT `fk_userprop_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_userprop_property` FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`),
  ADD CONSTRAINT `fk_userprop_user` FOREIGN KEY (`id_user`) REFERENCES `app_user` (`id_user`);

--
-- Constraints for table `user_role`
--
ALTER TABLE `user_role`
  ADD CONSTRAINT `fk_userrole_created_by` FOREIGN KEY (`created_by`) REFERENCES `app_user` (`id_user`),
  ADD CONSTRAINT `fk_userrole_role` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`),
  ADD CONSTRAINT `fk_userrole_user` FOREIGN KEY (`id_user`) REFERENCES `app_user` (`id_user`);

--
-- RBAC rollout compatibility (global roles + authz mode/audit tables)
--
ALTER TABLE `role`
  MODIFY `id_property` bigint(20) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `pms_authz_config` (
  `id_company` bigint(20) NOT NULL,
  `authz_mode` enum('audit','enforce') NOT NULL DEFAULT 'audit',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id_company`),
  KEY `idx_authz_mode` (`authz_mode`),
  CONSTRAINT `fk_authzcfg_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`),
  CONSTRAINT `fk_authzcfg_user` FOREIGN KEY (`updated_by`) REFERENCES `app_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pms_authz_audit` (
  `id_authz_audit` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_company` bigint(20) DEFAULT NULL,
  `id_user` bigint(20) DEFAULT NULL,
  `permission_code` varchar(100) NOT NULL,
  `property_code` varchar(100) DEFAULT NULL,
  `authz_mode` enum('audit','enforce') NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `context_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_authz_audit`),
  KEY `idx_authzaudit_company_created` (`id_company`,`created_at`),
  KEY `idx_authzaudit_user_created` (`id_user`,`created_at`),
  KEY `idx_authzaudit_perm_created` (`permission_code`,`created_at`),
  CONSTRAINT `fk_authzaudit_company` FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`),
  CONSTRAINT `fk_authzaudit_user` FOREIGN KEY (`id_user`) REFERENCES `app_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
