-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: MyField
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
-- Table structure for table `booking`
--

DROP TABLE IF EXISTS `booking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking` (
  `id_booking` int(11) NOT NULL AUTO_INCREMENT,
  `id_pengguna` int(11) NOT NULL,
  `id_lapangan` int(11) NOT NULL,
  `tanggal_main` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `status_booking` enum('pending','dikonfirmasi','selesai','dibatalkan') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_booking`),
  KEY `FK_booking_pengguna` (`id_pengguna`),
  KEY `FK_booking_lapangan` (`id_lapangan`),
  CONSTRAINT `FK_booking_lapangan` FOREIGN KEY (`id_lapangan`) REFERENCES `lapangan` (`id_lapangan`) ON DELETE CASCADE,
  CONSTRAINT `FK_booking_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking`
--

LOCK TABLES `booking` WRITE;
/*!40000 ALTER TABLE `booking` DISABLE KEYS */;
INSERT INTO `booking` VALUES 
  (1,1,1,'2026-05-20','09:00:00','10:00:00',150000.00,'dikonfirmasi','2026-05-18 10:07:38'),
  (2,2,1,'2026-05-20','19:00:00','20:00:00',150000.00,'pending','2026-05-18 10:07:38'),
  (3,3,2,'2026-05-20','16:00:00','17:00:00',130000.00,'selesai','2026-05-18 10:07:38'),
  (4,1,5,'2026-05-20','19:00:00','21:00:00',400000.00,'selesai','2026-05-18 10:07:38');
/*!40000 ALTER TABLE `booking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jadwal_lapangan`
--

DROP TABLE IF EXISTS `jadwal_lapangan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jadwal_lapangan` (
  `id_jadwal_lapangan` int(11) NOT NULL AUTO_INCREMENT,
  `id_lapangan` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `status_slot` enum('kosong','terisi','booked') NOT NULL,
  PRIMARY KEY (`id_jadwal_lapangan`),
  KEY `FK_jadwal_lapangan_lapangan` (`id_lapangan`),
  CONSTRAINT `FK_jadwal_lapangan_lapangan` FOREIGN KEY (`id_lapangan`) REFERENCES `lapangan` (`id_lapangan`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jadwal_lapangan`
--

LOCK TABLES `jadwal_lapangan` WRITE;
/*!40000 ALTER TABLE `jadwal_lapangan` DISABLE KEYS */;
INSERT INTO `jadwal_lapangan` VALUES 
  (1,1,'2026-05-20','08:00:00','09:00:00','kosong'),
  (2,1,'2026-05-20','09:00:00','10:00:00','booked'),
  (3,1,'2026-05-20','19:00:00','20:00:00','booked'),
  (4,2,'2026-05-20','16:00:00','17:00:00','booked'),
  (5,3,'2026-05-20','14:00:00','15:00:00','kosong'),
  (6,5,'2026-05-20','19:00:00','21:00:00','booked');
/*!40000 ALTER TABLE `jadwal_lapangan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lapangan`
--

DROP TABLE IF EXISTS `lapangan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lapangan` (
  `id_lapangan` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lapangan` varchar(255) NOT NULL,
  `jenis_olahraga` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga_per_jam` decimal(10,2) NOT NULL,
  `status` enum('tersedia','pemeliharaan','tidak_tersedia') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_lapangan`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lapangan`
--

LOCK TABLES `lapangan` WRITE;
/*!40000 ALTER TABLE `lapangan` DISABLE KEYS */;
INSERT INTO `lapangan` VALUES 
  (1,'Lapangan Futsal A (Vinyl)','Futsal','Lapangan futsal indoor menggunakan lantai vinyl standar internasional.',150000.00,'tersedia','2026-05-18 10:07:23'),
  (2,'Lapangan Futsal B (Rumput)','Futsal','Lapangan futsal semi-outdoor dengan rumput sintetis premium.',130000.00,'tersedia','2026-05-18 10:07:23'),
  (3,'Lapangan Badminton 1','Badminton','Lapangan bulu tangkis menggunakan karpet lapangan Yonex.',60000.00,'tersedia','2026-05-18 10:07:23'),
  (4,'Lapangan Badminton 2','Badminton','Lapangan bulu tangkis lantai kayu, pencahayaan sangat baik.',50000.00,'pemeliharaan','2026-05-18 10:07:23'),
  (5,'Lapangan Basket Main Court','Basket','Lapangan basket full-court kayu jati indoor dengan ring hidrolik.',200000.00,'tersedia','2026-05-18 10:07:23');
/*!40000 ALTER TABLE `lapangan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pembayaran`
--

DROP TABLE IF EXISTS `pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pembayaran` (
  `id_pembayaran` int(11) NOT NULL AUTO_INCREMENT,
  `id_booking` int(11) NOT NULL,
  `jumlah_bayar` decimal(10,2) NOT NULL,
  `metode_bayar` enum('transfer_bank','e_wallet','tunai') NOT NULL,
  `status_bayar` enum('belum_bayar','pending','lunas','gagal') NOT NULL,
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pembayaran`),
  KEY `FK_pembayaran_booking` (`id_booking`),
  CONSTRAINT `FK_pembayaran_booking` FOREIGN KEY (`id_booking`) REFERENCES `booking` (`id_booking`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pembayaran`
--

LOCK TABLES `pembayaran` WRITE;
/*!40000 ALTER TABLE `pembayaran` DISABLE KEYS */;
INSERT INTO `pembayaran` VALUES 
  (1,1,150000.00,'transfer_bank','lunas','bukti_tf_001.png','2026-05-18 10:07:47'),
  (2,2,150000.00,'e_wallet','pending',NULL,'2026-05-18 10:07:47'),
  (3,3,130000.00,'transfer_bank','lunas','bukti_tf_003.png','2026-05-18 10:07:47'),
  (4,4,400000.00,'transfer_bank','lunas','bukti_tf_004.png','2026-05-18 10:07:47');
/*!40000 ALTER TABLE `pembayaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengguna`
--

DROP TABLE IF EXISTS `pengguna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengguna` (
  `id_pengguna` int(11) NOT NULL AUTO_INCREMENT,
  `nama_pengguna` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `role` enum('admin','customer','pemilik') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pengguna`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengguna`
--

LOCK TABLES `pengguna` WRITE;
/*!40000 ALTER TABLE `pengguna` DISABLE KEYS */;
INSERT INTO `pengguna` VALUES 
  (1,'Ahmad Fauzi','ahmad.fauzi@email.com','password123','081234567890','customer','2026-05-18 10:07:06'),
  (2,'Budi Santoso','budi.santoso@email.com','budi2026','082198765432','customer','2026-05-18 10:07:06'),
  (3,'Siti Aminah','siti.aminah@email.com','sitioke123','085711223344','customer','2026-05-18 10:07:06'),
  (4,'Andika Pratama','andika.pemilik@email.com','ownerpass','081399887766','pemilik','2026-05-18 10:07:06'),
  (5,'Admin Super','admin.booking@email.com','adminsecure','081122334455','admin','2026-05-18 10:07:06');
/*!40000 ALTER TABLE `pengguna` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ulasan`
--

DROP TABLE IF EXISTS `ulasan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ulasan` (
  `id_ulasan` int(11) NOT NULL AUTO_INCREMENT,
  `id_booking` int(11) NOT NULL,
  `id_lapangan` int(11) NOT NULL,
  `id_pengguna` int(11) NOT NULL,
  `rating` decimal(2,1) NOT NULL,
  `komentar` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ulasan`),
  KEY `FK_ulasan_booking` (`id_booking`),
  KEY `FK_ulasan_lapangan` (`id_lapangan`),
  KEY `FK_ulasan_pengguna` (`id_pengguna`),
  CONSTRAINT `FK_ulasan_booking` FOREIGN KEY (`id_booking`) REFERENCES `booking` (`id_booking`) ON DELETE CASCADE,
  CONSTRAINT `FK_ulasan_lapangan` FOREIGN KEY (`id_lapangan`) REFERENCES `lapangan` (`id_lapangan`) ON DELETE CASCADE,
  CONSTRAINT `FK_ulasan_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ulasan`
--

LOCK TABLES `ulasan` WRITE;
/*!40000 ALTER TABLE `ulasan` DISABLE KEYS */;
INSERT INTO `ulasan` VALUES 
  (1,3,2,3,4.5,'Lapangannya bagus dan bersih, tapi parkirannya agak sempit kalau sore.','2026-05-18 10:07:55'),
  (2,4,5,1,5.0,'Fasilitas ring basket mantap banget, lantai kayu empuk ga licin. Worth it!','2026-05-18 10:07:55');
/*!40000 ALTER TABLE `ulasan` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-18 17:16:08
