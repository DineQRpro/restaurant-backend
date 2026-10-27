/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (aarch64)
--
-- Host: localhost    Database: db
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-ubu2404-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `tbl_bill`
--

DROP TABLE IF EXISTS `tbl_bill`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_bill` (
  `bill_id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_cust_session_id` varchar(255) DEFAULT NULL,
  `bill_table_id` varchar(150) DEFAULT NULL,
  `bill_order_id` varchar(150) DEFAULT NULL,
  `bill_total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bill_pay_status` int(11) DEFAULT NULL COMMENT 'Paid: 1, Credit: 2, NC:3, Cancel: 4',
  `bill_pay_method` int(11) DEFAULT NULL COMMENT '1. Cash 2. UPI 3. Card 4. Split 5. Non-Chargeable.',
  `bill_sgst` decimal(10,2) DEFAULT NULL,
  `bill_cgst` decimal(10,2) DEFAULT NULL,
  `bill_vat` decimal(10,2) DEFAULT NULL,
  `bill_gd_total` decimal(10,2) DEFAULT NULL,
  `additional_charges` decimal(10,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `discount_id` int(11) DEFAULT NULL,
  `other_discount_comments` text DEFAULT NULL,
  `other_discount_value` int(11) DEFAULT NULL,
  `taxable_total` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `round_off` decimal(10,2) DEFAULT 0.00,
  `payment_gateway` varchar(255) DEFAULT NULL COMMENT 'dinepro,nondinepro',
  `gateway_name` varchar(255) DEFAULT NULL,
  `bill_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bill_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`bill_id`),
  UNIQUE KEY `bill_order_id` (`bill_order_id`),
  KEY `idx_bill_order_pay_status` (`bill_order_id`,`bill_pay_status`)
) ENGINE=InnoDB AUTO_INCREMENT=1397 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_bill_payments`
--

DROP TABLE IF EXISTS `tbl_bill_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_bill_payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `payment_method` int(11) DEFAULT NULL,
  `payment_gateway` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(150) DEFAULT NULL,
  `payment_status` int(11) DEFAULT NULL,
  `paid_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `bill_id` (`bill_id`),
  CONSTRAINT `tbl_bill_payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `tbl_bill` (`bill_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1360 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_category`
--

DROP TABLE IF EXISTS `tbl_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_title` varchar(255) DEFAULT NULL,
  `category_desc` varchar(255) DEFAULT NULL,
  `category_image` varchar(255) DEFAULT NULL,
  `cat_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cat_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_combo`
--

DROP TABLE IF EXISTS `tbl_combo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_combo` (
  `combo_id` int(11) NOT NULL AUTO_INCREMENT,
  `combo_name` varchar(255) DEFAULT NULL,
  `combo_price` decimal(10,2) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` int(11) DEFAULT 0,
  `deleted` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`combo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3002 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_combo_products`
--

DROP TABLE IF EXISTS `tbl_combo_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_combo_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `combo_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `status` int(11) DEFAULT 0,
  `deleted` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `combo_id` (`combo_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `tbl_combo_products_ibfk_1` FOREIGN KEY (`combo_id`) REFERENCES `tbl_combo` (`combo_id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_combo_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `tbl_products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_dineprovendor`
--

DROP TABLE IF EXISTS `tbl_dineprovendor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_dineprovendor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `table_id` varchar(255) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `dinepro_id` varchar(255) NOT NULL,
  `cgst_amount` varchar(255) DEFAULT NULL,
  `sgst_amount` varchar(255) DEFAULT NULL,
  `vat_amount` varchar(255) DEFAULT NULL,
  `amount` decimal(10,0) NOT NULL,
  `pay_status` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `deleted` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_dineqrpro`
--

DROP TABLE IF EXISTS `tbl_dineqrpro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_dineqrpro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_gateway_id` int(11) DEFAULT NULL,
  `gateway_type` int(11) NOT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `vendor_id` varchar(255) NOT NULL,
  `schedule_option` int(11) NOT NULL,
  `account_number` varchar(255) NOT NULL,
  `account_holder` text NOT NULL,
  `ifsc` varchar(255) NOT NULL,
  `account_type` varchar(255) NOT NULL,
  `business_type` varchar(255) NOT NULL,
  `uidai` text NOT NULL,
  `gst` text NOT NULL,
  `cin` text NOT NULL,
  `pan` text NOT NULL,
  `passport_number` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `deleted` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_discounts`
--

DROP TABLE IF EXISTS `tbl_discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_discounts` (
  `discount_id` int(11) NOT NULL AUTO_INCREMENT,
  `discount_name` varchar(150) DEFAULT NULL,
  `discount_type` enum('custom','fixed') DEFAULT NULL,
  `discount_value` decimal(10,2) DEFAULT NULL,
  `discount_min_level` int(11) DEFAULT NULL,
  `discount_max_level` int(11) DEFAULT NULL,
  `applies_to` enum('product','order') DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`discount_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_grocery_category`
--

DROP TABLE IF EXISTS `tbl_grocery_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_grocery_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `deleted` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_grocery_debit`
--

DROP TABLE IF EXISTS `tbl_grocery_debit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_grocery_debit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grocery_id` int(11) DEFAULT NULL,
  `grocery_quantity` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT 0,
  `deleted` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_grocery_item_batch`
--

DROP TABLE IF EXISTS `tbl_grocery_item_batch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_grocery_item_batch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grocery_id` int(11) NOT NULL,
  `grocery_quantity` int(11) DEFAULT NULL,
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `vendor_code` varchar(255) NOT NULL,
  `batch_no` varchar(255) NOT NULL,
  `bill_no` varchar(255) NOT NULL,
  `bill_date` datetime NOT NULL,
  `bill_payment_status` int(11) NOT NULL,
  `priority` int(11) DEFAULT NULL,
  `expiry_datetime` date DEFAULT NULL,
  `status` int(11) DEFAULT 1,
  `deleted` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_grocery_item_order`
--

DROP TABLE IF EXISTS `tbl_grocery_item_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_grocery_item_order` (
  `id` int(11) NOT NULL,
  `grocery_id` int(11) NOT NULL,
  `grocery_quantity` int(11) DEFAULT NULL,
  `price_per_unit` decimal(10,2) DEFAULT NULL,
  `vendor_code` varchar(255) NOT NULL,
  `batch_no` varchar(255) NOT NULL,
  `bill_no` varchar(255) NOT NULL,
  `bill_date` datetime NOT NULL,
  `bill_payment_status` int(11) NOT NULL,
  `priority` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT 1,
  `deleted` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_grocery_items`
--

DROP TABLE IF EXISTS `tbl_grocery_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_grocery_items` (
  `grocery_id` int(11) NOT NULL AUTO_INCREMENT,
  `grocery_name` varchar(100) NOT NULL,
  `quantity_type_id` int(11) DEFAULT NULL,
  `grocery_quantity` int(11) DEFAULT NULL,
  `reorder_level` int(11) DEFAULT NULL,
  `max_limit` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT 1,
  `deleted` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`grocery_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_grocery_quantity_types`
--

DROP TABLE IF EXISTS `tbl_grocery_quantity_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_grocery_quantity_types` (
  `quantity_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `quantity_unit` varchar(20) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `deleted` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`quantity_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_happy_hours`
--

DROP TABLE IF EXISTS `tbl_happy_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_happy_hours` (
  `happy_hour_id` int(11) NOT NULL AUTO_INCREMENT,
  `happy_hour_name` varchar(100) DEFAULT NULL,
  `happy_hour_price` decimal(10,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `frequency` enum('daily','weekly','monthly') DEFAULT 'daily',
  `status` tinyint(4) DEFAULT 0,
  `deleted` tinyint(4) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`happy_hour_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20002 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_happy_hours_products`
--

DROP TABLE IF EXISTS `tbl_happy_hours_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_happy_hours_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `happy_hour_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `status` int(11) DEFAULT 0,
  `deleted` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_order`
--

DROP TABLE IF EXISTS `tbl_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_order` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_table_id` varchar(150) DEFAULT NULL,
  `order_waiter_id` int(11) DEFAULT NULL,
  `order_session_id` varchar(150) DEFAULT NULL,
  `order_total_amnt` varchar(50) DEFAULT NULL,
  `order_total_tax` varchar(50) DEFAULT NULL,
  `order_grand_total` varchar(50) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `discount_id` int(11) DEFAULT NULL,
  `other_discount_comments` text DEFAULT NULL,
  `order_status` int(11) DEFAULT NULL COMMENT 'Order Placed: 1, Accepted: 2, Rejected: 3, Preparing: 4, Ready: 5, Served: 6, Finished: 7, Acknowledged: 8',
  `print_status` int(11) DEFAULT 0,
  `order_tables_id` int(11) DEFAULT NULL COMMENT 'from tbl_tables id(primary key)',
  `tbls_merged` text DEFAULT NULL COMMENT 'Comma-separated merged physical table ids (tbl_tbid)',
  `order_user_status` int(11) DEFAULT 2,
  `order_spinstruction` text DEFAULT NULL,
  `order_vendor_name` varchar(50) DEFAULT NULL,
  `notification_sent` int(11) DEFAULT 0,
  `order_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `idx_order_session_id` (`order_session_id`),
  KEY `idx_order_session_user_status` (`order_session_id`,`order_user_status`),
  KEY `idx_order_tables_id` (`order_tables_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1416 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_order_items`
--

DROP TABLE IF EXISTS `tbl_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_order_items` (
  `order_items_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_items_main_id` int(11) DEFAULT NULL,
  `item_type` varchar(100) DEFAULT NULL,
  `order_product_id` int(11) DEFAULT NULL,
  `order_items_qty` int(11) DEFAULT NULL,
  `item_packing_charge` int(11) DEFAULT NULL,
  `order_items_amnt` varchar(150) DEFAULT NULL,
  `order_items_user_status` int(11) NOT NULL DEFAULT 2,
  `kitchen_status` int(11) NOT NULL DEFAULT 0 COMMENT '0-pending, 1-ready, 2-served',
  `order_items_spinstruction` varchar(150) DEFAULT NULL,
  `order_items_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_items_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_items_id`),
  KEY `idx_order_items_main_id` (`order_items_main_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6971 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_order_sufficient`
--

DROP TABLE IF EXISTS `tbl_order_sufficient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_order_sufficient` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `users_restreg_id` int(11) NOT NULL,
  `total_order_sufficient` int(11) NOT NULL,
  `total_order_insufficient` int(11) NOT NULL,
  `status` int(11) NOT NULL COMMENT '0 active, 1 inactive',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_orderpad`
--

DROP TABLE IF EXISTS `tbl_orderpad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_orderpad` (
  `orderpad_id` int(11) NOT NULL AUTO_INCREMENT,
  `orderpad_session_id` varchar(255) DEFAULT NULL,
  `orderpad_item` int(11) DEFAULT NULL,
  `item_type` varchar(100) DEFAULT NULL,
  `orderpad_item_qty` int(11) DEFAULT NULL,
  `oprice` int(11) DEFAULT NULL,
  `orderpad_created_by` timestamp NOT NULL DEFAULT current_timestamp(),
  `orderpad_updated_by` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`orderpad_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_payment_gateway`
--

DROP TABLE IF EXISTS `tbl_payment_gateway`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_payment_gateway` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_type` int(11) DEFAULT NULL,
  `payment_gateway_name` text DEFAULT NULL,
  `app_key` text DEFAULT NULL,
  `secret_key` text DEFAULT NULL,
  `deleted` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1,
  `active` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_product_images`
--

DROP TABLE IF EXISTS `tbl_product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_product_images` (
  `product_img_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_img_productid` int(11) DEFAULT NULL,
  `product_img_name` varchar(255) DEFAULT NULL,
  `product_img_size` int(11) DEFAULT NULL,
  `product_img_type` varchar(50) DEFAULT NULL,
  `product_img_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_img_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_img_id`)
) ENGINE=InnoDB AUTO_INCREMENT=378 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_products`
--

DROP TABLE IF EXISTS `tbl_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_title` varchar(255) DEFAULT NULL,
  `product_desc` longtext DEFAULT NULL,
  `chef_special` int(11) DEFAULT NULL,
  `product_main_category` int(11) DEFAULT NULL,
  `product_category` int(11) DEFAULT NULL,
  `product_original_price` int(11) DEFAULT NULL,
  `product_avail` int(11) DEFAULT 1 COMMENT '1: Available, 2: Not Available',
  `priority` int(11) NOT NULL DEFAULT 4,
  `product_spicy` int(11) NOT NULL DEFAULT 0 COMMENT '0-light, 1 medium 2 more ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=378 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_receipt_items`
--

DROP TABLE IF EXISTS `tbl_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_receipt_items` (
  `receipt_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_id` int(10) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`receipt_item_id`),
  KEY `idx_receipt_items_receipt_id` (`receipt_id`),
  CONSTRAINT `fk_receipt_items_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `tbl_receipts` (`receipt_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_receipts`
--

DROP TABLE IF EXISTS `tbl_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_receipts` (
  `receipt_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_date` date NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `program` text DEFAULT NULL,
  `other_instructions` text DEFAULT NULL,
  `pax_count` int(10) unsigned NOT NULL DEFAULT 0,
  `advance_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `include_gst` tinyint(1) NOT NULL DEFAULT 0,
  `gst_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`receipt_id`),
  KEY `idx_receipt_date` (`receipt_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_rstdetails`
--

DROP TABLE IF EXISTS `tbl_rstdetails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_rstdetails` (
  `rstdetails_id` int(11) NOT NULL AUTO_INCREMENT,
  `rstdetails_logo` varchar(255) DEFAULT 'logo.jpg',
  `rstdetails_name` varchar(255) DEFAULT NULL,
  `rstdetails_address` varchar(255) DEFAULT NULL,
  `rstdetails_pincode` varchar(255) DEFAULT NULL,
  `rstdetails_city` varchar(255) DEFAULT NULL,
  `rstdetails_state` varchar(255) DEFAULT NULL,
  `rstdetails_country` varchar(255) DEFAULT NULL,
  `rstdetails_landline` varchar(255) DEFAULT NULL,
  `rstdetails_mobileno` varchar(255) DEFAULT NULL,
  `rstdetails_countrycode` varchar(255) DEFAULT NULL,
  `rstdetails_website` varchar(255) DEFAULT NULL,
  `rstdetails_email` varchar(255) DEFAULT NULL,
  `rstdetails_gst` int(11) DEFAULT NULL,
  `rstdetails_cgst` decimal(10,2) DEFAULT NULL,
  `rstdetails_sgst` decimal(10,2) DEFAULT NULL,
  `rstdetails_vat` int(11) DEFAULT NULL,
  `rstdetails_tax` int(11) DEFAULT NULL,
  `rstdetails_currency` varchar(255) DEFAULT NULL,
  `rstdetails_gstinno` varchar(255) DEFAULT NULL,
  `rstdetails_vatnumber` varchar(255) DEFAULT NULL,
  `take_way` int(11) DEFAULT NULL,
  `online_order` int(11) DEFAULT NULL,
  `rstdetails_created_at` timestamp NULL DEFAULT current_timestamp(),
  `rstdetails_updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rstdetails_id`),
  UNIQUE KEY `rstdetails_mobileno` (`rstdetails_mobileno`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_tables`
--

DROP TABLE IF EXISTS `tbl_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_tables` (
  `tables_id` int(11) NOT NULL AUTO_INCREMENT,
  `tables_number` varchar(150) DEFAULT NULL,
  `tables_desc` varchar(255) DEFAULT NULL,
  `tables_qr` varchar(255) DEFAULT NULL,
  `tbl_tbid` varchar(255) DEFAULT NULL,
  `tables_chair_count` varchar(50) DEFAULT NULL,
  `tables_reserved_chair` int(11) DEFAULT NULL,
  `tables_customer_name` text DEFAULT NULL,
  `tables_chair_numbers` varchar(255) DEFAULT NULL,
  `tables_mobile_number` varchar(255) DEFAULT NULL,
  `tables_session_id` varchar(255) DEFAULT NULL,
  `order_segment` int(11) DEFAULT NULL COMMENT '2=Dine-In,5=Candle Light Dinner,6=Couple Dinning',
  `tables_user_status` int(11) DEFAULT 2 COMMENT '2 :user,1: staff',
  `tab_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tab_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tables_id`),
  KEY `idx_tables_session_id` (`tables_session_id`),
  KEY `idx_tables_tbl_tbid` (`tbl_tbid`)
) ENGINE=InnoDB AUTO_INCREMENT=1576 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_tables_notify`
--

DROP TABLE IF EXISTS `tbl_tables_notify`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_tables_notify` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tables_id` int(11) NOT NULL,
  `tables_session_id` varchar(100) NOT NULL,
  `notify` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '1 active, 0 deactive',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_tables_status`
--

DROP TABLE IF EXISTS `tbl_tables_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_tables_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) DEFAULT NULL,
  `waiter_id` int(11) DEFAULT NULL,
  `table_status` int(11) DEFAULT NULL COMMENT '//1 available, 2 occupied, 3 reserved, 4 needs cleaning',
  `deleted` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=982 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_tables_status_temp`
--

DROP TABLE IF EXISTS `tbl_tables_status_temp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_tables_status_temp` (
  `id` int(11) NOT NULL,
  `table_id` int(11) DEFAULT NULL,
  `waiter_id` int(11) DEFAULT NULL,
  `table_status` int(11) DEFAULT NULL COMMENT '//1 available, 2 occupied, 3 reserved, 4 needs cleaning',
  `deleted` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_upipay`
--

DROP TABLE IF EXISTS `tbl_upipay`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_upipay` (
  `upipay_id` int(11) NOT NULL AUTO_INCREMENT,
  `upipay_name` varchar(255) NOT NULL,
  `upipay_upiid` varchar(255) NOT NULL,
  `upipay_payment_reference` varchar(255) NOT NULL,
  `upipay_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `upipay_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`upipay_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_users`
--

DROP TABLE IF EXISTS `tbl_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `users_restreg_id` int(11) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` tinyint(4) DEFAULT 4 COMMENT 'Admin: 1, Kitchen: 2, Counter: 3, Staff: 4',
  `user_status` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active: 1, Deactive: 2',
  `user_subscribe_status` tinyint(4) NOT NULL DEFAULT 2 COMMENT 'Subscribed: 1, Not Subscribed: 2',
  `user_subscribe_expiry` timestamp NOT NULL,
  `last_login` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_vendor`
--

DROP TABLE IF EXISTS `tbl_vendor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_vendor` (
  `vendor_id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_name` varchar(255) NOT NULL,
  `vendor_code` varchar(255) NOT NULL,
  `vendor_desc` varchar(255) NOT NULL,
  `vendor_phone` varchar(255) NOT NULL,
  `vendor_address` text NOT NULL,
  `bank_account_no` varchar(255) DEFAULT NULL,
  `ifsc_code` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `gst_no` varchar(255) DEFAULT NULL,
  `vendor_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `vendor_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`vendor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_waiter_serve_table`
--

DROP TABLE IF EXISTS `tbl_waiter_serve_table`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_waiter_serve_table` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `waiter_id` int(11) DEFAULT NULL,
  `t_id` int(11) DEFAULT NULL,
  `table_id` text DEFAULT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `deleted` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=879 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbl_waiter_served`
--

DROP TABLE IF EXISTS `tbl_waiter_served`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_waiter_served` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `waiter_id` int(11) DEFAULT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-10-16 13:02:22
