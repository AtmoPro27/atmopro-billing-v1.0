/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: demo_billing
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_logs`
--

DROP TABLE IF EXISTS `admin_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `admin_username` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_logs`
--

LOCK TABLES `admin_logs` WRITE;
/*!40000 ALTER TABLE `admin_logs` DISABLE KEYS */;
INSERT INTO `admin_logs` VALUES
(1,16,'admin','LOGIN','user','16','Login ke sistem','127.0.0.1','2026-10-08 00:55:25'),
(2,16,'admin','LOGIN','user','16','Login ke sistem','127.0.0.1','2026-10-08 09:54:07'),
(3,16,'admin','LOGIN','user','16','Login ke sistem','127.0.0.1','2026-10-08 11:34:45'),
(4,16,'admin','LOGIN','user','16','Login ke sistem','127.0.0.1','2026-10-08 14:25:54'),
(5,16,'admin','INVOICE_PAID','invoice','INV/202610/0001','Konfirmasi pembayaran via tiket oleh admin','127.0.0.1','2026-10-08 14:27:06'),
(6,16,'admin','LOGIN','user','16','Login ke sistem','127.0.0.1','2026-10-08 16:39:45'),
(7,16,'admin','LOGIN','user','16','Login ke sistem','127.0.0.1','2026-10-10 06:43:23'),
(8,16,'admin','LOGIN','user','16','Login ke sistem','127.0.0.1','2026-10-10 15:12:34');
/*!40000 ALTER TABLE `admin_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alarm_aktif`
--

DROP TABLE IF EXISTS `alarm_aktif`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alarm_aktif` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` int(11) NOT NULL,
  `id_pelanggan` varchar(50) NOT NULL,
  `tipe_alarm` enum('DOWN','RX_LEMAH') NOT NULL,
  `pesan_telegram_id` bigint(20) DEFAULT NULL,
  `chat_id` varchar(50) DEFAULT NULL,
  `mulai` datetime NOT NULL DEFAULT current_timestamp(),
  `selesai` datetime DEFAULT NULL,
  `status` enum('aktif','selesai') NOT NULL DEFAULT 'aktif',
  `penerima` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`penerima`)),
  `rx_normal_count` int(11) DEFAULT 0,
  `rx_last_check` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pelanggan_tipe` (`pelanggan_id`,`tipe_alarm`),
  KEY `idx_pelanggan_status` (`pelanggan_id`,`tipe_alarm`,`status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=438 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alarm_aktif`
--

LOCK TABLES `alarm_aktif` WRITE;
/*!40000 ALTER TABLE `alarm_aktif` DISABLE KEYS */;
/*!40000 ALTER TABLE `alarm_aktif` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bandwidth`
--

DROP TABLE IF EXISTS `bandwidth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bandwidth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_bandwidth` varchar(100) NOT NULL,
  `upload` int(11) NOT NULL,
  `download` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bandwidth`
--

LOCK TABLES `bandwidth` WRITE;
/*!40000 ALTER TABLE `bandwidth` DISABLE KEYS */;
/*!40000 ALTER TABLE `bandwidth` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `expense_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_profil`
--

DROP TABLE IF EXISTS `group_profil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_profil` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_group` varchar(100) NOT NULL,
  `ip_lokal` varchar(50) DEFAULT NULL,
  `ip_awal` varchar(50) DEFAULT NULL,
  `ip_akhir` varchar(50) DEFAULT NULL,
  `router_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `router_id` (`router_id`),
  CONSTRAINT `group_profil_ibfk_1` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`),
  CONSTRAINT `group_profil_ibfk_2` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_profil`
--

LOCK TABLES `group_profil` WRITE;
/*!40000 ALTER TABLE `group_profil` DISABLE KEYS */;
/*!40000 ALTER TABLE `group_profil` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `income_manual`
--

DROP TABLE IF EXISTS `income_manual`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `income_manual` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `keterangan` varchar(255) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `kategori` enum('pemasangan','penjualan','jasa','donasi','lainnya') DEFAULT 'lainnya',
  `tanggal` date NOT NULL,
  `catatan` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `income_manual_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `income_manual`
--

LOCK TABLES `income_manual` WRITE;
/*!40000 ALTER TABLE `income_manual` DISABLE KEYS */;
/*!40000 ALTER TABLE `income_manual` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `investor`
--

DROP TABLE IF EXISTS `investor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `investor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `modal` decimal(15,2) NOT NULL,
  `prosentase` decimal(5,2) NOT NULL DEFAULT 0.00,
  `bagi_hasil_per_bulan` decimal(15,2) DEFAULT 0.00,
  `bagi_hasil` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `investor`
--

LOCK TABLES `investor` WRITE;
/*!40000 ALTER TABLE `investor` DISABLE KEYS */;
/*!40000 ALTER TABLE `investor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice`
--

DROP TABLE IF EXISTS `invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` int(11) DEFAULT NULL,
  `no_invoice` varchar(50) DEFAULT NULL,
  `jumlah` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `tgl_terbit` datetime DEFAULT NULL,
  `tgl_jatuh_tempo` datetime DEFAULT NULL,
  `tgl_bayar` datetime DEFAULT NULL,
  `viewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_invoice` (`no_invoice`),
  KEY `pelanggan_id` (`pelanggan_id`),
  CONSTRAINT `invoice_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1935 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice`
--

LOCK TABLES `invoice` WRITE;
/*!40000 ALTER TABLE `invoice` DISABLE KEYS */;
INSERT INTO `invoice` VALUES
(1926,929,'INV/202610/0001',150000.00,'paid','2026-10-08 20:58:45','2026-11-07 20:58:45','2026-10-08 21:27:06','2026-10-08 13:59:26'),
(1933,930,'INV/202610/0002',200000.00,'paid','2026-10-08 21:00:01','2026-11-07 21:00:01','2026-10-08 22:45:30','2026-10-08 14:49:37'),
(1934,931,'INV/202610/0003',250000.00,'pending','2026-10-08 21:00:01','2026-11-07 21:00:01',NULL,NULL);
/*!40000 ALTER TABLE `invoice` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_aktivitas`
--

DROP TABLE IF EXISTS `log_aktivitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `aktivitas` varchar(255) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `log_aktivitas_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_aktivitas`
--

LOCK TABLES `log_aktivitas` WRITE;
/*!40000 ALTER TABLE `log_aktivitas` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_aktivitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_history`
--

DROP TABLE IF EXISTS `login_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_history`
--

LOCK TABLES `login_history` WRITE;
/*!40000 ALTER TABLE `login_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mikrotik_bandwidth`
--

DROP TABLE IF EXISTS `mikrotik_bandwidth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_bandwidth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `router_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `target` varchar(100) NOT NULL,
  `max_limit_down` varchar(50) DEFAULT NULL,
  `max_limit_up` varchar(50) DEFAULT NULL,
  `burst_limit_down` varchar(50) DEFAULT NULL,
  `burst_limit_up` varchar(50) DEFAULT NULL,
  `burst_threshold_down` varchar(50) DEFAULT NULL,
  `burst_threshold_up` varchar(50) DEFAULT NULL,
  `burst_time_down` varchar(50) DEFAULT NULL,
  `burst_time_up` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `router_id` (`router_id`),
  CONSTRAINT `mikrotik_bandwidth_ibfk_1` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotik_bandwidth`
--

LOCK TABLES `mikrotik_bandwidth` WRITE;
/*!40000 ALTER TABLE `mikrotik_bandwidth` DISABLE KEYS */;
/*!40000 ALTER TABLE `mikrotik_bandwidth` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mikrotik_ippool`
--

DROP TABLE IF EXISTS `mikrotik_ippool`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_ippool` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `router_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ranges` varchar(200) NOT NULL,
  `next_pool` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `router_id` (`router_id`),
  CONSTRAINT `mikrotik_ippool_ibfk_1` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotik_ippool`
--

LOCK TABLES `mikrotik_ippool` WRITE;
/*!40000 ALTER TABLE `mikrotik_ippool` DISABLE KEYS */;
/*!40000 ALTER TABLE `mikrotik_ippool` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mikrotik_ppp_profile`
--

DROP TABLE IF EXISTS `mikrotik_ppp_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mikrotik_ppp_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `router_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `local_address` varchar(50) DEFAULT NULL,
  `remote_address` varchar(100) DEFAULT NULL,
  `dns_server` varchar(100) DEFAULT NULL,
  `rate_limit` varchar(100) DEFAULT NULL,
  `session_timeout` varchar(50) DEFAULT NULL,
  `idle_timeout` varchar(50) DEFAULT NULL,
  `use_encryption` enum('yes','no') DEFAULT 'yes',
  PRIMARY KEY (`id`),
  KEY `router_id` (`router_id`),
  CONSTRAINT `mikrotik_ppp_profile_ibfk_1` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mikrotik_ppp_profile`
--

LOCK TABLES `mikrotik_ppp_profile` WRITE;
/*!40000 ALTER TABLE `mikrotik_ppp_profile` DISABLE KEYS */;
/*!40000 ALTER TABLE `mikrotik_ppp_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifikasi_pelanggan`
--

DROP TABLE IF EXISTS `notifikasi_pelanggan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifikasi_pelanggan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` int(11) DEFAULT NULL,
  `judul` varchar(200) DEFAULT NULL,
  `pesan` text DEFAULT NULL,
  `jenis` enum('info','warning','success','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pelanggan_id` (`pelanggan_id`),
  CONSTRAINT `notifikasi_pelanggan_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifikasi_pelanggan`
--

LOCK TABLES `notifikasi_pelanggan` WRITE;
/*!40000 ALTER TABLE `notifikasi_pelanggan` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifikasi_pelanggan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `olt`
--

DROP TABLE IF EXISTS `olt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_olt` varchar(100) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `port` int(11) DEFAULT 23,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `tipe` varchar(50) DEFAULT NULL,
  `merk` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olt`
--

LOCK TABLES `olt` WRITE;
/*!40000 ALTER TABLE `olt` DISABLE KEYS */;
/*!40000 ALTER TABLE `olt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ont`
--

DROP TABLE IF EXISTS `ont`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ont` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `olt_id` int(11) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `mac_address` varchar(50) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `olt_id` (`olt_id`),
  CONSTRAINT `ont_ibfk_1` FOREIGN KEY (`olt_id`) REFERENCES `olt` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ont`
--

LOCK TABLES `ont` WRITE;
/*!40000 ALTER TABLE `ont` DISABLE KEYS */;
/*!40000 ALTER TABLE `ont` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `bandwidth_down` int(11) NOT NULL,
  `bandwidth_up` int(11) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `router_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `router_id` (`router_id`),
  CONSTRAINT `packages_ibfk_1` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paket_internet`
--

DROP TABLE IF EXISTS `paket_internet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `paket_internet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_paket` varchar(100) NOT NULL,
  `profil_ppp` varchar(100) DEFAULT NULL,
  `harga` decimal(15,2) NOT NULL,
  `router_id` int(11) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `router_id` (`router_id`),
  CONSTRAINT `paket_internet_ibfk_1` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paket_internet`
--

LOCK TABLES `paket_internet` WRITE;
/*!40000 ALTER TABLE `paket_internet` DISABLE KEYS */;
INSERT INTO `paket_internet` VALUES
(1,'PAKET_10Mbps',NULL,150000.00,1,NULL,1,'2026-10-07 13:07:01'),
(2,'PAKET_20Mbps',NULL,250000.00,1,NULL,1,'2026-10-07 13:07:01'),
(3,'PAKET_50Mbps',NULL,500000.00,2,NULL,1,'2026-10-07 13:07:01');
/*!40000 ALTER TABLE `paket_internet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_gateway`
--

DROP TABLE IF EXISTS `payment_gateway`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_gateway` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `api_key` text DEFAULT NULL,
  `api_secret` text DEFAULT NULL,
  `merchant_id` varchar(100) DEFAULT NULL,
  `environment` enum('sandbox','production') DEFAULT 'sandbox',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_gateway`
--

LOCK TABLES `payment_gateway` WRITE;
/*!40000 ALTER TABLE `payment_gateway` DISABLE KEYS */;
INSERT INTO `payment_gateway` VALUES
(6,'Duitku',1,'8580764ad7b7e2717a665f11a6980f76','','DS36331','sandbox','2026-10-08 03:13:22','2026-10-08 21:45:06'),
(7,'Midtrans',0,NULL,NULL,NULL,'sandbox','2026-10-08 03:13:22','2026-10-08 03:13:22'),
(8,'Xendit',0,NULL,NULL,NULL,'sandbox','2026-10-08 03:13:22','2026-10-08 03:13:22'),
(9,'Tripay',0,NULL,NULL,NULL,'sandbox','2026-10-08 03:13:22','2026-10-08 03:13:22'),
(10,'Paytren',0,NULL,NULL,NULL,'sandbox','2026-10-08 03:13:22','2026-10-08 03:13:22');
/*!40000 ALTER TABLE `payment_gateway` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_log`
--

DROP TABLE IF EXISTS `payment_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `action` enum('pay','cancel') NOT NULL,
  `alasan` text DEFAULT NULL,
  `dilakukan_oleh` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `payment_log_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_log`
--

LOCK TABLES `payment_log` WRITE;
/*!40000 ALTER TABLE `payment_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_settings`
--

DROP TABLE IF EXISTS `payment_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_settings`
--

LOCK TABLES `payment_settings` WRITE;
/*!40000 ALTER TABLE `payment_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pelanggan`
--

DROP TABLE IF EXISTS `pelanggan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pelanggan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pelanggan` varchar(50) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `paket_id` int(11) DEFAULT NULL,
  `diperpanjang` datetime DEFAULT NULL,
  `jatuh_tempo` datetime DEFAULT NULL,
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `tipe_pelanggan` enum('reguler','vip','fasum','nardi') DEFAULT 'reguler',
  `is_synced` tinyint(1) DEFAULT 0,
  `status_aktif` enum('aktif','on_progress') DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_online_seconds` int(11) DEFAULT 0,
  `last_uptime` int(11) DEFAULT 0,
  `last_traffic` bigint(20) DEFAULT 0,
  `tgl_aktif` date DEFAULT NULL,
  `is_prorata` tinyint(1) DEFAULT 0,
  `is_online` int(11) DEFAULT 0,
  `serial_number` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `down_since` datetime DEFAULT NULL,
  `genieacs_device_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_pelanggan` (`id_pelanggan`),
  KEY `paket_id` (`paket_id`),
  CONSTRAINT `pelanggan_ibfk_1` FOREIGN KEY (`paket_id`) REFERENCES `paket_internet` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=932 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pelanggan`
--

LOCK TABLES `pelanggan` WRITE;
/*!40000 ALTER TABLE `pelanggan` DISABLE KEYS */;
INSERT INTO `pelanggan` VALUES
(929,'CUST001','Budi Santoso','Jl. Contoh No. 1, Jakarta','628123456789',1,NULL,'2027-01-31 23:59:59','active','reguler',0,'aktif','2026-10-07 13:07:06','2026-10-10 07:01:36',0,0,0,NULL,0,0,NULL,'$2y$10$ZY/ezOh4vMHRcbGTsy2MBerCC8qe2Pc7YMNsNzG5pxV5JshkX/f3u','2026-10-10 14:01:36',NULL),
(930,'CUST002','Siti Aminah','Jl. Contoh No. 2, Jakarta','628123456790',2,NULL,'2027-01-31 00:00:00','active','reguler',0,'aktif','2026-10-07 13:07:06','2026-10-10 07:01:36',0,0,0,NULL,0,0,NULL,'$2y$10$ZY/ezOh4vMHRcbGTsy2MBerCC8qe2Pc7YMNsNzG5pxV5JshkX/f3u','2026-10-10 14:01:36',NULL),
(931,'CUST003','Ahmad Wijaya','Jl. Contoh No. 3, Jakarta','628123456791',3,NULL,'2026-12-31 00:00:00','active','reguler',0,'aktif','2026-10-07 13:07:06','2026-10-10 07:01:36',0,0,0,NULL,0,0,NULL,'$2y$10$ZY/ezOh4vMHRcbGTsy2MBerCC8qe2Pc7YMNsNzG5pxV5JshkX/f3u','2026-10-10 14:01:36',NULL);
/*!40000 ALTER TABLE `pelanggan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pelanggan_traffic`
--

DROP TABLE IF EXISTS `pelanggan_traffic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pelanggan_traffic` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` int(11) NOT NULL,
  `total_bytes` bigint(20) NOT NULL DEFAULT 0,
  `bulan` varchar(7) NOT NULL,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pelanggan_bulan` (`pelanggan_id`,`bulan`),
  CONSTRAINT `pelanggan_traffic_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10011 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pelanggan_traffic`
--

LOCK TABLES `pelanggan_traffic` WRITE;
/*!40000 ALTER TABLE `pelanggan_traffic` DISABLE KEYS */;
/*!40000 ALTER TABLE `pelanggan_traffic` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengaturan`
--

DROP TABLE IF EXISTS `pengaturan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengaturan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_setting` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_setting` (`key_setting`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengaturan`
--

LOCK TABLES `pengaturan` WRITE;
/*!40000 ALTER TABLE `pengaturan` DISABLE KEYS */;
INSERT INTO `pengaturan` VALUES
(38,'company_name','Demo WIFI',NULL,'2026-10-07 13:07:11','2026-10-07 19:19:36'),
(39,'company_tagline','internet wus wus',NULL,'2026-10-07 13:07:11','2026-10-07 19:19:00'),
(40,'company_phone','628123456789',NULL,'2026-10-07 13:07:11','2026-10-07 13:07:11'),
(41,'telegram_notification_enabled','0',NULL,'2026-10-07 13:07:11','2026-10-07 13:07:11'),
(42,'telegram_bot_token','YOUR_BOT_TOKEN_HERE',NULL,'2026-10-07 13:07:11','2026-10-07 13:07:11');
/*!40000 ALTER TABLE `pengaturan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengeluaran`
--

DROP TABLE IF EXISTS `pengeluaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jenis` enum('rutin','maintenance','bagi_hasil','psb') NOT NULL,
  `investor_id` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `tanggal` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengeluaran`
--

LOCK TABLES `pengeluaran` WRITE;
/*!40000 ALTER TABLE `pengeluaran` DISABLE KEYS */;
/*!40000 ALTER TABLE `pengeluaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengeluaran_otomatis`
--

DROP TABLE IF EXISTS `pengeluaran_otomatis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengeluaran_otomatis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jenis` enum('rutin','bagi_hasil') NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengeluaran_otomatis`
--

LOCK TABLES `pengeluaran_otomatis` WRITE;
/*!40000 ALTER TABLE `pengeluaran_otomatis` DISABLE KEYS */;
/*!40000 ALTER TABLE `pengeluaran_otomatis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengumuman`
--

DROP TABLE IF EXISTS `pengumuman`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengumuman` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `isi` text NOT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengumuman`
--

LOCK TABLES `pengumuman` WRITE;
/*!40000 ALTER TABLE `pengumuman` DISABLE KEYS */;
/*!40000 ALTER TABLE `pengumuman` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profil_ppp`
--

DROP TABLE IF EXISTS `profil_ppp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profil_ppp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_profil` varchar(100) NOT NULL,
  `group_profil_id` int(11) DEFAULT NULL,
  `bandwidth_id` int(11) DEFAULT NULL,
  `harga` decimal(15,2) DEFAULT NULL,
  `masa_aktif` int(11) DEFAULT 30,
  `share_user` enum('yes','no') DEFAULT 'no',
  `router_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `group_profil_id` (`group_profil_id`),
  KEY `bandwidth_id` (`bandwidth_id`),
  KEY `router_id` (`router_id`),
  CONSTRAINT `profil_ppp_ibfk_1` FOREIGN KEY (`group_profil_id`) REFERENCES `group_profil` (`id`),
  CONSTRAINT `profil_ppp_ibfk_2` FOREIGN KEY (`bandwidth_id`) REFERENCES `bandwidth` (`id`),
  CONSTRAINT `profil_ppp_ibfk_3` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profil_ppp`
--

LOCK TABLES `profil_ppp` WRITE;
/*!40000 ALTER TABLE `profil_ppp` DISABLE KEYS */;
/*!40000 ALTER TABLE `profil_ppp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reset_log`
--

DROP TABLE IF EXISTS `reset_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reset_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reset_log`
--

LOCK TABLES `reset_log` WRITE;
/*!40000 ALTER TABLE `reset_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `reset_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_module` (`role`,`module`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `routers`
--

DROP TABLE IF EXISTS `routers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_router` varchar(100) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `port_api` int(11) DEFAULT 8728,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_connected` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_test` datetime DEFAULT NULL,
  `last_test_result` varchar(50) DEFAULT NULL,
  `last_test_message` text DEFAULT NULL,
  `connection_status` enum('online','offline','unknown') DEFAULT 'unknown',
  `last_check` datetime DEFAULT NULL,
  `last_check_message` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `routers`
--

LOCK TABLES `routers` WRITE;
/*!40000 ALTER TABLE `routers` DISABLE KEYS */;
INSERT INTO `routers` VALUES
(1,'ROUTER_PUSAT','192.168.1.1',8728,'admin','password','Kantor Pusat','active',NULL,'2026-10-07 13:06:48',NULL,NULL,NULL,'offline','2026-10-07 21:26:27',NULL),
(2,'ROUTER_CABANG','192.168.2.1',8728,'admin','password','Cabang','active',NULL,'2026-10-07 13:06:48',NULL,NULL,NULL,'offline','2026-10-07 21:26:42',NULL);
/*!40000 ALTER TABLE `routers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
(35,'genieacs_url','http://YOUR_ACS_IP:7557','2026-10-07 13:10:10','2026-10-07 19:53:30'),
(36,'genieacs_username','','2026-10-07 13:10:10','2026-10-07 13:10:10'),
(37,'genieacs_password','','2026-10-07 13:10:10','2026-10-07 13:10:10'),
(38,'genieacs_timeout','30','2026-10-07 13:10:10','2026-10-07 19:53:30');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings_log`
--

DROP TABLE IF EXISTS `settings_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_key` (`setting_key`),
  KEY `idx_time` (`changed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings_log`
--

LOCK TABLES `settings_log` WRITE;
/*!40000 ALTER TABLE `settings_log` DISABLE KEYS */;
INSERT INTO `settings_log` VALUES
(13,'genieacs_url','http://YOUR_ACS_IP:7557','http://YOUR_ACS_IP:7557','admin','2026-10-07 19:53:30'),
(14,'genieacs_timeout','30','30','admin','2026-10-07 19:53:30');
/*!40000 ALTER TABLE `settings_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_stats`
--

DROP TABLE IF EXISTS `system_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stat_key` varchar(50) DEFAULT NULL,
  `stat_value` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `stat_key` (`stat_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_stats`
--

LOCK TABLES `system_stats` WRITE;
/*!40000 ALTER TABLE `system_stats` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tagihan`
--

DROP TABLE IF EXISTS `tagihan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tagihan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pelanggan_id` int(11) DEFAULT NULL,
  `no_invoice` varchar(50) DEFAULT NULL,
  `periode_bulan` int(11) DEFAULT NULL,
  `periode_tahun` int(11) DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `status` enum('pending','paid','expired','cancelled') DEFAULT 'pending',
  `tgl_jatuh_tempo` date DEFAULT NULL,
  `tgl_bayar` date DEFAULT NULL,
  `metode_pembayaran` enum('cash','transfer','gateway') DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_invoice` (`no_invoice`),
  KEY `pelanggan_id` (`pelanggan_id`),
  CONSTRAINT `tagihan_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tagihan`
--

LOCK TABLES `tagihan` WRITE;
/*!40000 ALTER TABLE `tagihan` DISABLE KEYS */;
/*!40000 ALTER TABLE `tagihan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_logs`
--

DROP TABLE IF EXISTS `telegram_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` varchar(50) NOT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `response` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3017 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_logs`
--

LOCK TABLES `telegram_logs` WRITE;
/*!40000 ALTER TABLE `telegram_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_ticket_notifications`
--

DROP TABLE IF EXISTS `telegram_ticket_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_ticket_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_type` enum('payment','technical','alarm_down','alarm_rx','alarm_masal','alarm_masal_rx') NOT NULL,
  `role` varchar(50) NOT NULL,
  `chat_ids` varchar(500) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ticket_role` (`ticket_type`,`role`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_ticket_notifications`
--

LOCK TABLES `telegram_ticket_notifications` WRITE;
/*!40000 ALTER TABLE `telegram_ticket_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_ticket_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_replies`
--

DROP TABLE IF EXISTS `ticket_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_replies`
--

LOCK TABLES `ticket_replies` WRITE;
/*!40000 ALTER TABLE `ticket_replies` DISABLE KEYS */;
INSERT INTO `ticket_replies` VALUES
(66,103,16,'✅ Pembayaran untuk invoice INV/202610/0001 telah dikonfirmasi. Jatuh tempo telah diperpanjang ke bulan berikutnya. Terima kasih.',NULL,'2026-10-08 21:27:06');
/*!40000 ALTER TABLE `ticket_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_ticket` varchar(20) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `attachment` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_ticket` (`no_ticket`),
  KEY `pelanggan_id` (`pelanggan_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES
(103,'TCK/20261008/3641',929,'Konfirmasi Pembayaran - Invoice INV/202610/0001','Saya telah melakukan pembayaran untuk invoice INV/202610/0001 sebesar Rp 150.000\n\nDetail Pembayaran:\n- No Invoice: INV/202610/0001\n- Jumlah: Rp 150.000\n- Tanggal Transfer: 08-10-2026\n\nCatatan: sudah bayar\n\n📎 Bukti Pembayaran: https://your-domain.com/uploads/bukti_pembayaran/20261008_212512_air_1.jpg\n\nMohon segera dikonfirmasi. Terima kasih.','medium','closed','uploads/bukti_pembayaran/20261008_212512_air_1.jpg',NULL,'2026-10-08 21:25:12','2026-10-08 21:27:06');
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tiket`
--

DROP TABLE IF EXISTS `tiket`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiket` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_tiket` varchar(20) DEFAULT NULL,
  `pelanggan_id` int(11) DEFAULT NULL,
  `jenis` enum('pembayaran','isolir','gangguan','info') NOT NULL,
  `judul` varchar(200) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `status` enum('open','process','resolved','closed') DEFAULT 'open',
  `prioritas` enum('low','medium','high','urgent') DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_tiket` (`no_tiket`),
  KEY `pelanggan_id` (`pelanggan_id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `tiket_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`),
  CONSTRAINT `tiket_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tiket`
--

LOCK TABLES `tiket` WRITE;
/*!40000 ALTER TABLE `tiket` DISABLE KEYS */;
/*!40000 ALTER TABLE `tiket` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_keuangan`
--

DROP TABLE IF EXISTS `transaksi_keuangan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaksi_keuangan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jenis` enum('income','expense') NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `bukti_file` varchar(255) DEFAULT NULL,
  `tagihan_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `tagihan_id` (`tagihan_id`),
  CONSTRAINT `transaksi_keuangan_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `transaksi_keuangan_ibfk_2` FOREIGN KEY (`tagihan_id`) REFERENCES `tagihan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_keuangan`
--

LOCK TABLES `transaksi_keuangan` WRITE;
/*!40000 ALTER TABLE `transaksi_keuangan` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaksi_keuangan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` enum('super_admin','kasir','teknisi','pelanggan') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `telegram_chat_id` varchar(50) DEFAULT NULL,
  `receive_telegram_notification` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(16,'admin','$2y$10$A9oZE8wmQIHduN5sf1tDwuANVDOLWzZnEK7axx5xB2bOugJ/.Zpaa','Administrator','super_admin','admin@demo.local','628123456789',NULL,1,1,NULL,'2026-10-07 13:03:59'),
(17,'teknisi','$2y$10$A9oZE8wmQIHduN5sf1tDwuANVDOLWzZnEK7axx5xB2bOugJ/.Zpaa','Teknisi Demo','teknisi','teknisi@demo.local','628123456790',NULL,1,1,NULL,'2026-10-07 13:03:59');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wa_logs`
--

DROP TABLE IF EXISTS `wa_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `response` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wa_logs`
--

LOCK TABLES `wa_logs` WRITE;
/*!40000 ALTER TABLE `wa_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `wa_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wa_settings`
--

DROP TABLE IF EXISTS `wa_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_url` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `sender_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wa_settings`
--

LOCK TABLES `wa_settings` WRITE;
/*!40000 ALTER TABLE `wa_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `wa_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wa_templates`
--

DROP TABLE IF EXISTS `wa_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `template_message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wa_templates`
--

LOCK TABLES `wa_templates` WRITE;
/*!40000 ALTER TABLE `wa_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `wa_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wa_ticket_notifications`
--

DROP TABLE IF EXISTS `wa_ticket_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wa_ticket_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_type` enum('payment','technical') NOT NULL,
  `role` varchar(50) NOT NULL,
  `phone_numbers` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ticket_role` (`ticket_type`,`role`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wa_ticket_notifications`
--

LOCK TABLES `wa_ticket_notifications` WRITE;
/*!40000 ALTER TABLE `wa_ticket_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `wa_ticket_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'demo_billing'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-11  0:05:30

-- ============================================================
-- UPDATE v1.2: Kolom tambahan untuk tabel invoice
-- ============================================================

ALTER TABLE `invoice`
  ADD COLUMN IF NOT EXISTS `jumlah_unik` decimal(15,2) DEFAULT NULL AFTER `jumlah`,
  ADD COLUMN IF NOT EXISTS `metode_bayar` enum('TUNAI','TRANSFER','PAYMENT GATEWAY') DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `paid_by` varchar(100) DEFAULT NULL AFTER `tgl_bayar`,
  ADD COLUMN IF NOT EXISTS `jatuh_tempo_sebelum` datetime DEFAULT NULL AFTER `tgl_jatuh_tempo`;
