-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: guidemate
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
-- Current Database: `guidemate`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `guidemate` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `guidemate`;

--
-- Table structure for table `api_tokens`
--

DROP TABLE IF EXISTS `api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_tokens_token` (`token`),
  KEY `idx_api_tokens_user` (`user_id`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_tokens`
--

LOCK TABLES `api_tokens` WRITE;
/*!40000 ALTER TABLE `api_tokens` DISABLE KEYS */;
INSERT INTO `api_tokens` VALUES (1,2,'8f2dae86bab6437055c6c97a03e140cfbf966227e8b56b010768a2919d4ece96','2026-07-15 20:15:47','2026-06-15 12:15:47'),(2,2,'096b1d06632ffb5e91642fa02f0239870cce64e24dfb68e67a20fcc4e03398de','2026-07-15 20:16:00','2026-06-15 12:16:00'),(3,2,'706a768748a7ef3cc95b3b281db8fbae4a3ec448578e5ab4e90171336be7bcc1','2026-07-15 20:45:15','2026-06-15 12:45:15'),(4,2,'e3dfd8c2cf4ac3ec824959191e69893513566f4d73134d02576d65f76dd50131','2026-07-15 20:48:27','2026-06-15 12:48:27'),(5,2,'51c01028697917fd704e2dc3f31e61759687e42d08cf0d8db849425a8e14bbc0','2026-07-15 21:15:56','2026-06-15 13:15:56'),(6,2,'d7c5a4dc4092980357f7ccf49ad1e4d824a7e2058ab8d926390099c8f49b3f3f','2026-07-15 21:50:34','2026-06-15 13:50:34'),(7,2,'d3a647dcb3e6031d1e1ce7b4eb3124a631d8e53964514c17067749d6ef340a2c','2026-07-15 22:32:11','2026-06-15 14:32:11'),(8,2,'cb73e1cd494d2ad8ca7f2fa45e1eeaf2f5ac8d5c68f4b92ec3db705fc1506191','2026-07-15 22:35:34','2026-06-15 14:35:34'),(9,2,'23a7e24a011199b37cf132efc2227f1440d046f72f234c5f92bee44896cec544','2026-07-15 22:42:54','2026-06-15 14:42:54'),(10,2,'2a71210d7b0e6659e8927b3a1f486b5643543d112e41ab09d6eef7ee0bed1eda','2026-07-15 22:44:50','2026-06-15 14:44:50'),(11,2,'f3c93e4958bd85bc6bbc1e7aa3e0bb47bf4cb9f521b6231175a9deccc20f037f','2026-07-15 22:48:11','2026-06-15 14:48:11'),(12,2,'5540e7b6870160958cd62bc139cf2059e4220cc3c8e81a2e3955be69b43edcea','2026-07-15 22:48:48','2026-06-15 14:48:48'),(13,4,'12e157ac05d7a59cf8e8d9d2925f93597ef4ab9438da3bf61b14cc6dfc277689','2026-07-16 13:51:17','2026-06-16 05:51:17'),(14,4,'36e102015abbf9e0ed9d74d234f27c10bf0b6dd9cbe53a9e9d86b6e7521a1143','2026-07-16 13:52:18','2026-06-16 05:52:18'),(15,2,'1d484ef97d62b7067d5654b82376c7956692ce812263855e5c329ca401e5ccc5','2026-07-16 14:57:52','2026-06-16 06:57:52'),(16,2,'8fc3c94944d9282b4bb0e1f36a7a1402f2da56e6a6cfca64b33e65bb9f3fcff6','2026-07-16 15:04:43','2026-06-16 07:04:43'),(17,2,'cf7496279f5b5dc483b45f55f29fb39ffb718fdec9f07428602627864b378768','2026-07-16 15:12:37','2026-06-16 07:12:37'),(18,2,'1bce5b70539430dd0b7facb1fe300f589545e2a299b58c21ed6c0b3b189df12d','2026-07-16 15:15:16','2026-06-16 07:15:16'),(19,2,'bb4e37b635ed984109b8d7848f4a467908f62fafdd5f5acabe5dfdae8910bffa','2026-07-16 15:18:16','2026-06-16 07:18:16'),(20,5,'ebba8c228f87ed7099c2196b7b02448d571c479aedad3df77490f2ed52ee1ae9','2026-07-16 15:19:09','2026-06-16 07:19:09'),(22,2,'069559a09e19e610326af85636350cf359dab1b845295e29162f22a2c119ee80','2026-07-21 23:07:11','2026-06-21 15:07:11'),(23,2,'b2c09ad065b67d3259263f3b9d13c49df11f4014b5d33a22932be5069fb7bad3','2026-07-21 23:09:48','2026-06-21 15:09:48'),(24,2,'d964012f0b1b582fcf1d780bed69518ecbc3407acf4d259855ed8774e3a6d3ef','2026-07-21 23:10:06','2026-06-21 15:10:06'),(25,2,'87c4649c5eef8a640da61a9b288a49ecd070720bf677a9a2d5848a99ab9449c1','2026-07-21 23:15:34','2026-06-21 15:15:34'),(26,2,'5ac517357655fdb1ef5b174fc756cef89400c9c73df9dd9764e0325da8433931','2026-07-30 03:24:23','2026-06-29 19:24:23'),(27,2,'2f6ef26e5875fceccdf5c9e90cdd969081de0c5a191f99711ccfa148cede4f63','2026-07-30 03:26:20','2026-06-29 19:26:20'),(28,2,'394441acdf96ed4dd206231d6f3b561fd18be9ea6c5581d9359e4c0ce1d43bb2','2026-07-30 03:29:23','2026-06-29 19:29:23'),(29,2,'1d4ba90c4c3e7881f31ba3ccf61e1c9b08a93413d1f79431cd83a190d3c0200e','2026-07-30 03:30:04','2026-06-29 19:30:04'),(30,2,'0e800289a055d97731b3208e810ac7ea03543e7b6dcdcf10d09b665a0e1a08d7','2026-07-30 03:31:41','2026-06-29 19:31:41'),(31,2,'8a92444589ea5c807845fffa09a671bb60e8c89142ccaba6bc6b9778481bd84e','2026-07-30 03:34:35','2026-06-29 19:34:35'),(32,2,'d11d305f9a9783cc11e543dfa6f72dea64bbe8d82b7bf383f5d865e0a4250f48','2026-07-30 03:37:23','2026-06-29 19:37:23'),(33,2,'ef385fcb462e9776f84ab6985860435b1ca63e54ef015be36044c92a789174a9','2026-07-30 03:38:26','2026-06-29 19:38:26'),(34,2,'4d9f69152bd0eb0ad5f886acdc31cb1285bbbad330b33f74c04a76829c0167de','2026-07-30 03:38:41','2026-06-29 19:38:41'),(35,2,'813a2564d46c1f307044931b2ca0fc42d509ec9d08bcb97ae3a5611af2a12e8f','2026-07-30 03:39:50','2026-06-29 19:39:50'),(36,2,'db52705476518ef296185d921817c2eca3f4164d9ca92919917f7e4219d7e9ca','2026-07-30 03:42:32','2026-06-29 19:42:32'),(37,2,'0c0449c989413b3465a8c40fe2f43a4e4d9309c99fe11a6ea1ddce04695e928e','2026-07-30 03:45:14','2026-06-29 19:45:14'),(38,2,'bb0f4fb6feec41bc258d49a8a63792cd711e21689b3b9bcdfbdcae5147b32433','2026-07-30 03:45:44','2026-06-29 19:45:44'),(39,2,'2f2b9f3e761be6099f9eacd956b1b9ba9ac8e84acc17216f7581eafd42918516','2026-07-30 03:46:28','2026-06-29 19:46:28'),(40,2,'f681f73964e2b6fc9936d75dde9c08eb73c25ca2f80d14c2ea913623c9ad4c7d','2026-07-30 03:47:22','2026-06-29 19:47:22'),(41,2,'99316d2aa4fad31e14a38779b416c8175e1100684af476b80d0e3a5da74e4a28','2026-07-30 03:48:56','2026-06-29 19:48:56'),(42,2,'18ab15dc63b7ea94543c1295c763e4b66cefbbec889fb7089ed6df165a72277a','2026-07-30 03:49:49','2026-06-29 19:49:49'),(43,2,'c01fcebfbb364131bfe7ecf62ea823a18c253d523e450cea84d99348302b67c5','2026-07-30 03:52:27','2026-06-29 19:52:27'),(44,2,'a1a116057ac42e1637a2aae24c735a81b80b97c8322b8713af4ddc710d3b23a4','2026-07-30 04:04:03','2026-06-29 20:04:03'),(46,2,'bf5235b1bf253b7aa78f52be12e5588df0a6a65a7925657bd48ca6e4f98408cb','2026-07-30 04:10:04','2026-06-29 20:10:04'),(47,2,'e74bc71297fdaf18354f118d87ff1d87701892d3f33df3d628c1096f0e00e1e0','2026-07-30 04:11:32','2026-06-29 20:11:32'),(49,2,'8ca053481b03bec3e5e26560c557509c495e3a738fefb89dfa3c3af6f4ec5dff','2026-07-30 04:16:56','2026-06-29 20:16:56'),(50,2,'90bfbcee19bcbfd15412e100906c49f07733094bd4f8eb7d31e034463bfaf038','2026-07-30 04:21:25','2026-06-29 20:21:25'),(52,2,'a8c0baf8c14a6c990a360cc5c7ee59aaaa0476257811db86e1780fab8b4784ad','2026-07-30 04:28:57','2026-06-29 20:28:57'),(53,2,'df164628e7d998c3b44af2af2b8654cbeb6e8f014092f609c3bcbe5f08e0038c','2026-07-30 04:29:38','2026-06-29 20:29:38'),(56,2,'2a7223e2b78919d4fb35025c06e743f71ac888962d9f16805e22c1f4d4fa8e34','2026-07-30 04:33:11','2026-06-29 20:33:11'),(57,2,'157ab46c83e6fe8c1cd930eb0d3d9870c7df671a55c38154f2a5b289f416c542','2026-07-30 04:34:46','2026-06-29 20:34:46'),(58,2,'6b57ae0608e4e431acf72298a5f5cf651bb4a2edc020f897e7e3211f19f768a1','2026-07-30 04:38:18','2026-06-29 20:38:18'),(59,2,'983f92f651cfc15e3128de345c6aff25e8eca6cdcc35d8e1e8525ff68ed0db71','2026-07-30 04:49:25','2026-06-29 20:49:25'),(61,2,'182ec4787bd4c9d638a046d01f9f7b004f3c6aa1160f9ead98f5b991aafe8518','2026-07-30 04:50:33','2026-06-29 20:50:33'),(63,2,'214061e2edc8add34edcab364e6554490afe6dd2edb1c3de830972010bfefcfa','2026-07-30 04:55:42','2026-06-29 20:55:42'),(65,2,'a4057b38ff261ddd59a33be8820fd43da2fcd1f9f991ae62d091033b93dbf3ae','2026-07-30 05:00:43','2026-06-29 21:00:43'),(66,2,'c716ae032e3c3cda13733b09869be5aad218ff9e0e06a8b06960936910bcc5a3','2026-07-30 05:01:56','2026-06-29 21:01:56'),(67,2,'5ea24fbc6e98dc0e5955fa7d35b61e07dbb1e65fbf24b50756e352ef0b4d6d75','2026-07-30 05:05:18','2026-06-29 21:05:18'),(68,2,'5c059b31123ad751dc10304eb879faab8176022e6fbdc06629b4e71d85ef2bb5','2026-07-30 05:06:19','2026-06-29 21:06:19'),(69,2,'a03c6206c7d70d98072fbc9d693ea211798879fa838baa2ecab333347051a433','2026-07-30 05:06:55','2026-06-29 21:06:55'),(70,6,'b71a95778adf312ddb25cc4b9bb413b8ba1d6d62fe284d37aae373e0ff4988a8','2026-07-30 05:21:42','2026-06-29 21:21:42'),(71,6,'f87eef1be337a7914b68ea7831831119d200f8e5871118ee934e725dd597d8af','2026-07-30 05:22:01','2026-06-29 21:22:01'),(72,2,'8adf7b28cf39aece449b4374b8ab7a36235392e6ddb28146c661e32938527c1a','2026-07-30 05:27:38','2026-06-29 21:27:38'),(74,2,'f66f83f0366defb6c7705839a837dc25e74082b9171134122ffc2aa684001c8a','2026-07-30 05:32:15','2026-06-29 21:32:15'),(75,2,'080a55838f05bc1ea948e53d4b4136d1b7e5cdff33f6692038743b5aa6346707','2026-07-30 05:34:16','2026-06-29 21:34:16'),(76,2,'8082b12a8676eda6135f1777889c5de11c20d6c08c882dfef2ab24d9294841bd','2026-07-30 05:42:06','2026-06-29 21:42:06'),(77,2,'4954a9b9cbc488ddf9e4575202c87bd735cfe236aaa417327ef3699447b708a4','2026-07-30 05:45:00','2026-06-29 21:45:00'),(78,2,'09f9033ef9b9a70c5ebe93052090dc89b1e5f72b34048d79c7c8e4fd137d8aa8','2026-07-30 06:05:31','2026-06-29 22:05:31'),(79,2,'3613d9cd0e019e250f7b707badfb347360fbc45416956b3f8a9cbae0dc092fba','2026-07-30 06:13:43','2026-06-29 22:13:43'),(80,2,'a92b5977828bc23e6a4c846043b69f73ea034d636d853b1b447f9fa8724a7543','2026-07-30 06:25:27','2026-06-29 22:25:27'),(81,2,'67e6809723617416f3197e06fd7f437f595e7baeb8eb9462bac1274b0dd80c8c','2026-07-30 06:27:27','2026-06-29 22:27:27'),(82,2,'40dcbe91626660b67a32e959a5fb7860acbf6950ced7692bac79e47957d3b5ee','2026-07-30 06:44:48','2026-06-29 22:44:48'),(83,2,'ab3127dc7c7585b54d2fa03f3ef1630eca8e9fead7be4003fa213be57fc6ac5e','2026-07-30 06:54:50','2026-06-29 22:54:50'),(84,2,'04aa142febb4dba502bdab108b5cfcdb4be62bd15eebd6b84aa8eedf5dd9da45','2026-07-30 07:15:44','2026-06-29 23:15:44'),(85,2,'e40df003f97e3c6041b691d52862eb1f9c40fe78d3468c39ba278274e12cfc66','2026-07-30 12:11:26','2026-06-30 04:11:26'),(86,2,'4c06cfd5aa3c73db586ec5c7f06ea1116781b16a2fa7e41e7d53afafaecf8ac0','2026-07-30 12:13:21','2026-06-30 04:13:21'),(87,2,'47ac7ec51113f44734a12e3db8a2081702accad83a2f336e25927931e0407809','2026-07-30 12:14:02','2026-06-30 04:14:02'),(88,2,'6b06abc3b2f54aae3a926a10514b1898aac94b8ac58151826a9fea2c5a898c28','2026-07-30 12:15:43','2026-06-30 04:15:43'),(89,2,'a1080bd2eb12c466f6e3459f3ac11e919c9312d364635a4c18dbbc70e5fda3ef','2026-07-30 12:19:17','2026-06-30 04:19:17'),(90,2,'6a05393916c6fae216560b38c3a5aee1a4035a542e1522a5c0b7c74164799e96','2026-07-30 12:20:34','2026-06-30 04:20:34'),(91,2,'e53e61af2a3cdf069495f4b4945c220df2420f6e5ab6fd6bc127e138c67a5f22','2026-07-30 12:26:59','2026-06-30 04:26:59'),(92,2,'d8450f8c475bf01c2f5c894ec8e113cb476e303b456157c6df0aea011f6e5394','2026-07-30 12:28:33','2026-06-30 04:28:33'),(93,2,'dca722820179ab3194630c7e3bd2502b5cc53d0134d7ea96c580e647b0007da4','2026-07-30 12:29:16','2026-06-30 04:29:16'),(94,2,'a9283edb0dd93a6a0f0b10598a5ffcdfbe2fdc4a2a86a335f63be7084810e41d','2026-07-30 12:32:49','2026-06-30 04:32:49'),(95,2,'fb063dcf70b96694ac0ff81fab8e3c28a5718a82230a1effe01b0a5e7cf631a0','2026-07-30 12:35:01','2026-06-30 04:35:01'),(96,2,'6d7b5134dcba11134e2cbce17547ebb3adbd5dbc3c9b40026575425d437f44ae','2026-07-30 12:48:14','2026-06-30 04:48:14'),(97,2,'25a79a2e2bcf6a3fca943ab32fff2cbdd1c98d70b7fcce731117145f15e15b71','2026-07-30 12:56:19','2026-06-30 04:56:19'),(98,2,'0d2e41c5805b18b9463ec15d73ddd9243f499c4d4ec3946e27d7eaddf93211ae','2026-07-30 12:59:25','2026-06-30 04:59:25'),(99,2,'7b96e8d550e6057d5e827986409337f4a7d3f1cb35de5124e58f370767ec24b9','2026-07-30 13:11:21','2026-06-30 05:11:21'),(100,2,'8dc576947e4bb492f1b37b4e1718ea14527d04e87039732f0ce2f80becf65baf','2026-07-30 13:13:39','2026-06-30 05:13:39'),(101,2,'40ad084e111a29be9affdf9704ae62194f07208ed5a1d7b6908930d2b3e1dd16','2026-07-30 13:14:57','2026-06-30 05:14:57'),(102,2,'bbe58d4109944998b777b391a0b43f5989ded6926082976cf50c65e5f5001c8d','2026-07-30 13:17:04','2026-06-30 05:17:04'),(103,2,'33e8d3d6d6c435085bcd2b781e4f124766253e3f5f9ded9679ea209ddb593e36','2026-07-30 13:34:20','2026-06-30 05:34:20'),(104,2,'d5c5227fac93304944c5d749b1ae2ff3a77fbf75bfdcbf5330e30e8367d91f68','2026-07-30 14:12:01','2026-06-30 06:12:01'),(105,6,'4f3566c52162642489e1fd449ff4899dc9b8dbe425a9f9a3db4f9366c66476a6','2026-07-30 14:15:21','2026-06-30 06:15:21'),(106,2,'a223e8f143b9a2a710da7127ba475c41a6ec718a8ab1a0b3678bd01e7e5f108f','2026-07-30 14:15:31','2026-06-30 06:15:31'),(107,7,'fa1b8c8e62ca38fd8d29f19dcfe73a91b4250ca92ee2a226820f30a844e2f011','2026-07-30 14:15:50','2026-06-30 06:15:50'),(108,7,'4015629dfe9f6ad2a54902513ea59eff79aae09e738389e37d1d60ff210f125a','2026-07-30 14:16:34','2026-06-30 06:16:34'),(109,7,'e417e2a8003b53e978c53b49081458aaa5cc2a5871d544ea1fab9f38839e692b','2026-07-30 14:19:37','2026-06-30 06:19:37'),(110,6,'1889d6dbf212b79816fa8ccded21d8002c4078714c793e8683568ecb7f570521','2026-07-30 14:25:51','2026-06-30 06:25:51'),(111,6,'d6755ebc39470d342de886564e3df60ecc3bb7b1b6b50a696fd6c3dd56890dbd','2026-07-30 14:26:56','2026-06-30 06:26:56'),(112,7,'222f206095928b703415d720027615203607377590e21a2b2483ccdd7d6671f0','2026-07-30 14:38:22','2026-06-30 06:38:22'),(113,2,'f12f4e290082fd7fe66066b882a7c400f89169b1fc77ae4ba84f03873055f3b4','2026-07-31 12:54:46','2026-07-01 04:54:46'),(114,2,'26171cb4214296790f3ca3bb926a49550e781403bba56358911a895044a997e2','2026-07-31 13:19:10','2026-07-01 05:19:10'),(115,2,'e67b8a8d66546de0ae1d3d1346f85c5eb44629ab18c72e423ea80caa68074965','2026-08-05 20:22:09','2026-07-06 12:22:09'),(116,2,'2fb9bc6a2d87148c1695f4b2690fec340081fb8c65b092bf8d2efdb1c2f53795','2026-08-05 20:31:27','2026-07-06 12:31:27'),(117,2,'894ed69464d73013818574450b391deb1c10303e1d5f39ef48b5bee07738c2db','2026-08-05 20:36:49','2026-07-06 12:36:49'),(118,6,'855b718c800a9fc3236ce4137a8627941ac2f1f72aad0bc2310f3dbfe40acc1a','2026-08-05 20:43:57','2026-07-06 12:43:57'),(119,6,'f9440b59b427aba34595ec34aae9ab843230e3aee4794cd556b5d9202dad7f9c','2026-08-05 20:44:18','2026-07-06 12:44:18'),(120,6,'b70d34af66a5b2cfa03f862307eb06b027997abb14d8105852e52de634ca7a5c','2026-08-05 22:44:11','2026-07-06 14:44:11'),(121,6,'67676d3c8ac90b0b0f9b7cd86a693c82d86824d3a7b577465a6a59b573d9189f','2026-08-05 22:44:38','2026-07-06 14:44:38'),(122,2,'20dc0f41e51cade64e2c1df6403fb39519d618ffc3a18b468834eaf590b17539','2026-08-05 22:45:07','2026-07-06 14:45:07'),(123,2,'e3c8da0b292d58497ece97f628f4f351588a1da207ee5b5ebad82deeb95bfb94','2026-08-05 22:48:35','2026-07-06 14:48:35'),(124,2,'f9b1a99699d23044e0454dc5aea96a9beb5bd6857cb516a435487adbcf04bf1b','2026-08-05 22:50:36','2026-07-06 14:50:36'),(127,2,'5f37bf1039e23313a768e67f2f4d7ba7f689873d10c3d9fae9762d6c204136b5','2026-08-05 22:56:44','2026-07-06 14:56:44'),(128,2,'c93bbc367ced66110867b0d18e0e43cf5c2dfc71000d2371f38d210cffc73f3e','2026-08-05 23:03:58','2026-07-06 15:03:58'),(130,2,'4351adb93a641078054589a2b0f087ab6a9c51e1dcf4be629b7e1481c77560b3','2026-08-05 23:19:13','2026-07-06 15:19:13'),(131,2,'0406570b5d264575901b33923262d7ca2c551ce87478b949b92376c94cd6ba20','2026-08-05 23:22:58','2026-07-06 15:22:58'),(133,2,'fd34db84602ac64326974f9a85726fd4880325d50ef2ffe5e49ba84e30194aae','2026-08-05 23:43:24','2026-07-06 15:43:24'),(134,2,'ca9bba277679f2aa31fb59b8fcd05f9fafa242c54baa00850c81d6496d1fc519','2026-08-05 23:46:00','2026-07-06 15:46:00'),(135,2,'2163a495231316228f2b8512812f2a340a690fff2b4c094325eb8ad7e1910b6d','2026-08-06 00:06:09','2026-07-06 16:06:09'),(136,2,'1820bad129efea26ca2c4b4b09e84bf4aa6cec1dcf36d27be8d951ef7c918bea','2026-08-06 00:29:07','2026-07-06 16:29:07'),(137,2,'8afa5bd6d622651d9762c59cf48c83842ac5f1db187cf205823dff06116468c5','2026-08-06 00:31:19','2026-07-06 16:31:19'),(138,2,'2c650449c6850262c731bb3c3965117f25a6acbfc3851176c7b222e906ea8d70','2026-08-06 00:41:26','2026-07-06 16:41:26'),(139,2,'7b7a98f2423e00745d9ed9edbc5aa180ee848ba706ca6cc69250fc8a2e4b5aa7','2026-08-06 00:44:29','2026-07-06 16:44:29'),(142,2,'ed473d831b3c48bf37a31dfc3bbb0bbae8eeea0522eb2bdeff52efbbf5e0dff6','2026-08-06 01:26:39','2026-07-06 17:26:39'),(143,2,'e77a46f88dfa04fce53eb728fcfc8a78f1a62e21e52e720e285fc4b3978391f0','2026-08-06 01:35:28','2026-07-06 17:35:28'),(144,2,'689d5728dc75849e28e0425b14e049bc71307b5e25dd3f5b0799eb317121e705','2026-08-06 14:26:00','2026-07-07 06:26:00'),(145,2,'39d4985cf0651750c3ba43904ff46e9188cf35f1599196ef4789ffbffce41ae3','2026-08-06 15:21:05','2026-07-07 07:21:05'),(146,2,'a6efda3d840bbd2790a0f42cb8ab8d2ba7153214a720ad3aee2a15fbee898feb','2026-08-08 02:40:42','2026-07-08 18:40:42'),(147,2,'0f1b08bdc547c4c1cffd14f00e83299171cb24fbade17e9e454c0ab303a97c3f','2026-08-08 03:14:31','2026-07-08 19:14:31'),(148,2,'8361d916b0c091b6c3d03c30afe77facd9c722f173429259ee8bfeda7e0f9958','2026-08-08 03:17:27','2026-07-08 19:17:27'),(149,2,'6eb13bd8eeab8325ff3d54d9e1cf052289d1f627d8599d65cc4a943e50f776d3','2026-10-02 16:14:47','2026-09-02 08:14:47'),(150,2,'4504b93d32a3f302331c0c5163317d8f57e78f622e25a6e1abc53e113f8cc636','2026-10-02 17:29:09','2026-09-02 09:29:09'),(151,10,'f33b9ad54b4ae75b8134ee87f11e1d9036e3c79d35a881d12407152e7baa3785','2026-10-02 17:53:06','2026-09-02 09:53:06'),(153,11,'60b2e407ba7301ed070b4dd7c902bc666b600616b9282d1187065b14f818f824','2026-10-02 17:54:35','2026-09-02 09:54:35'),(154,11,'4fbc3035f72db94f26656a5799cb301a2dc18866ec75e070038d18bb08230dc9','2026-10-02 17:54:51','2026-09-02 09:54:51'),(155,2,'94fadb732f96ba7d3d00b9a9915691b2c4128830a099e5f0f0475c458190fd63','2026-10-17 00:56:13','2026-09-16 16:56:13'),(156,2,'d365dd5e5e250ea8d973c988fe90a44b13862f3ae78c2fcb9e0246461c743d78','2026-10-17 01:10:07','2026-09-16 17:10:07'),(157,2,'f03ba8806b582d605758488e14a840df3d5747d93c8690f100ed65d1537467da','2026-10-17 02:02:45','2026-09-16 18:02:45'),(158,2,'b1b4ba0e5c83b15fd8b45a24c08b7b9a60c4451f66acfd36a98af0d868ae9520','2026-10-17 02:11:29','2026-09-16 18:11:29'),(159,2,'6acbcbd8915caee69c6348b0c105f60a77758429b61d9479d0b0ec6354e8b201','2026-10-17 02:49:37','2026-09-16 18:49:37'),(160,2,'6c755c4a92ec4e4415ff9b809f9f05deed6734e229d6790a0bd78ec2f290a03e','2026-10-17 02:57:54','2026-09-16 18:57:54'),(177,16,'3640b55f57ce1015fe8e6d40a1b2eb9683979b797bc638df3c427703de8c564a','2026-10-17 04:25:54','2026-09-16 20:25:54'),(178,17,'2b4a53445b773ba1a4f83cf2e3898be8f121f0cd90b433299f25bf734ee5114c','2026-10-17 04:28:29','2026-09-16 20:28:29'),(181,17,'8db7a33cadb1302fcde11b6e2273f60c99f5a588d92310b200f702842e864734','2026-10-17 04:34:33','2026-09-16 20:34:33'),(182,17,'bf9073d43d2f1c9f65b6c876511db6e6d9d48e97f3c3a7bb69e55b6444b590ae','2026-10-17 04:37:17','2026-09-16 20:37:17'),(187,17,'a8b46bbc27163e6aadcc68e6b7342957cf3f20a717476be17ae814f14d8984fa','2026-10-17 04:56:59','2026-09-16 20:56:59'),(188,15,'7ebcc46b989da407f52c4ce3d383eda60110bdfab7456836348d6a92ab7fb23c','2026-10-17 05:09:44','2026-09-16 21:09:44'),(189,2,'79191a6d088de1cfc9171502277a9785d60945832a37d5d8710d64c093963af9','2026-10-18 13:52:06','2026-09-18 05:52:06'),(190,2,'94c396b06e3c051be3d09a822f298509452b552fbba424ea5f66a2e92ec11f0f','2026-10-21 08:23:52','2026-09-21 00:23:52'),(191,2,'7cdc31acdf70464773bb3bf61d8096e3732bff90c602fa0e9291da1689b5c611','2026-10-21 08:32:22','2026-09-21 00:32:22'),(194,2,'48ee6c140b739e0dab27456dce33b46567c53241ddf34adba1a417a8e2d83eac','2026-10-27 22:26:58','2026-09-27 14:26:58'),(195,2,'f89067c15d86c12e8cc107b94377f07c1122048601f6397c7319c5efb02aec0e','2026-10-27 23:05:55','2026-09-27 15:05:55'),(196,2,'715bb7a168b597c0c58b5dc8b87084c0a87244bd0644fcbc0f452e516d4bf9af','2026-10-27 23:29:53','2026-09-27 15:29:53'),(197,2,'734ce230b4102cba9569fccd91b557ca45eefd8cad7271e1a27cfbfb2a63ad1c','2026-10-27 23:37:30','2026-09-27 15:37:30'),(198,2,'4be7be850b9c4a8f7520433104cc5ef808de247bdd00fc1217df5a3a388afeab','2026-10-27 23:54:13','2026-09-27 15:54:13'),(199,2,'265e1ef5e752d9ee62614259565ae2003e34e6ecd853cdc6a9005251de97c804','2026-10-28 13:23:41','2026-09-28 05:23:41'),(200,10,'f9edaeef73549f6ada56d4941c4821addce341159e29dba8a8c84e7394566867','2026-10-28 13:28:00','2026-09-28 05:28:00'),(202,19,'1d656afada45b043ecf3cda8e8aa6cff439df56155817e8141dbae93b692b429','2026-10-28 13:33:47','2026-09-28 05:33:47'),(203,19,'11e49b9b4fc9f56a21a4afca5f2d2908fda58ef1e33beaddd3d5f3e5381811ad','2026-10-28 13:34:08','2026-09-28 05:34:08'),(204,10,'43b14dabbc742efd8850e6ce62fcf93492af2c5d6073313acbd5a33a49c1aeb5','2026-10-28 13:57:07','2026-09-28 05:57:07'),(205,10,'7f1013821688a851a194e102571335474a161fba3fafb021487764a79174d120','2026-10-28 14:04:02','2026-09-28 06:04:02'),(206,20,'c1e90c3396e42e3af8796bd272bbf35a353a13735472f8871e6e7f2b4d752564','2026-10-28 14:22:18','2026-09-28 06:22:18'),(207,20,'1f8cbac13f63018af91ca0f3b31c8dbbf61d72dfba58f1976a8a14ad5a1f74b4','2026-10-28 14:22:23','2026-09-28 06:22:23'),(208,2,'f7c2b94364552e67808a285ccd1ed5084df3acee10bde7f1bba429d59a468cdf','2026-10-29 12:21:02','2026-09-29 04:21:02'),(209,2,'5aebef02e57c64ef65b1eadd0783a15203c53efbfa3c9e81b74cf95f7fa87acc','2026-10-29 12:38:19','2026-09-29 04:38:19'),(210,2,'e5e2d0f00e9b65afcfc6fcef927778023007db6c6324f4677427c8aac1adea91','2026-10-29 12:52:19','2026-09-29 04:52:19'),(211,2,'fc2b52016d71d32769290bc14a792be9eaf5ebdfdad006cf69d41dc4af79bc76','2026-10-29 13:06:52','2026-09-29 05:06:52'),(212,2,'9f2cc12fd409afee9c3b082c4df9e2b88eb9b45195eed275ee910cc5aebae5c9','2026-10-29 15:09:56','2026-09-29 07:09:56'),(213,2,'3dfad6f98af7a11e1a40d95713a901704bd89062840581fc773c269e35a7badb','2026-10-29 15:36:42','2026-09-29 07:36:42'),(214,2,'2698745954a0727050ff2e3325d2ee30178a7e995d027871dea58f4085e5ac73','2026-10-29 15:40:53','2026-09-29 07:40:53'),(215,2,'910f788cab8b4bf1bfe322ed2be7acde4939939834e96e91fa91854f2171a381','2026-10-29 15:43:10','2026-09-29 07:43:10'),(216,2,'c6844d6663345fb8c273d3f984723557a28fc3d25146806f6b86bf61a7a33147','2026-10-29 15:53:04','2026-09-29 07:53:04'),(217,2,'0b2b2cea0a599341ed29be39bd967762a1ac0f1beddb1ed187ea47a4948ae521','2026-10-29 16:01:46','2026-09-29 08:01:46'),(218,2,'b7660547817d664a3c8a12042c554d01827ed829cf7518dbb77785200c4a989d','2026-10-29 16:09:04','2026-09-29 08:09:04'),(219,2,'47ee2884cef2082b47648ae103821bf2f952bcca5720e625973b474952c4afbc','2026-10-29 16:19:00','2026-09-29 08:19:00'),(220,2,'96c654e0011315b26a825a5b93a39d692e248ec36e0b089e5d9a63a89f3247de','2026-10-29 16:34:15','2026-09-29 08:34:15'),(222,2,'f11f8088571d6f76f66c7db7da3a4715dfd3d991578ba2d2943a1b36ba22becf','2026-10-29 16:55:07','2026-09-29 08:55:07');
/*!40000 ALTER TABLE `api_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_feedback`
--

DROP TABLE IF EXISTS `app_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_feedback` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `category` varchar(40) NOT NULL DEFAULT 'general',
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_app_feedback_created` (`created_at`),
  KEY `idx_app_feedback_status` (`status`),
  KEY `fk_app_feedback_user` (`user_id`),
  CONSTRAINT `fk_app_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_feedback`
--

LOCK TABLES `app_feedback` WRITE;
/*!40000 ALTER TABLE `app_feedback` DISABLE KEYS */;
INSERT INTO `app_feedback` VALUES (2,2,'Jalel Mauc','jalelmauc@gmail.com',5,'feature','It have been some bugs for some features','reviewed','2026-07-06 15:25:18'),(4,2,'Jalel Mauc','jalelmauc@gmail.com',0,'praise','xksksxiisisisi','archived','2026-07-07 06:27:29');
/*!40000 ALTER TABLE `app_feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_logs_admin_fk` (`admin_id`),
  CONSTRAINT `audit_logs_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'user.toggle_active','user',4,'{\"active\":false}','2026-06-16 05:56:06'),(2,1,'user.toggle_active','user',2,'{\"active\":false}','2026-06-16 07:14:55'),(3,1,'user.toggle_active','user',2,'{\"active\":true}','2026-06-16 07:15:13'),(4,1,'user.toggle_active','user',2,'{\"active\":false}','2026-06-16 07:15:39'),(5,1,'user.toggle_active','user',2,'{\"active\":true}','2026-06-16 07:18:12'),(6,1,'user.toggle_active','user',5,'{\"active\":false}','2026-06-16 07:19:13'),(7,1,'user.toggle_active','user',5,'{\"active\":true}','2026-06-16 07:19:57'),(8,1,'user.toggle_active','user',5,'{\"active\":false}','2026-06-16 07:21:22'),(9,1,'user.toggle_active','user',5,'{\"active\":true}','2026-06-16 07:21:25'),(10,1,'user.toggle_active','user',7,'{\"active\":false}','2026-06-30 06:17:49'),(11,1,'user.toggle_active','user',7,'{\"active\":true}','2026-06-30 06:18:09'),(12,1,'feedback.status','app_feedback',2,'{\"status\":\"reviewed\"}','2026-07-06 15:26:15'),(13,1,'feedback.status','app_feedback',2,'{\"status\":\"reviewed\"}','2026-07-06 15:26:23'),(14,1,'feedback.status','app_feedback',4,'{\"status\":\"archived\"}','2026-07-07 06:32:14'),(15,1,'feedback.status','app_feedback',4,'{\"status\":\"archived\"}','2026-07-07 06:32:20'),(16,1,'feedback.status','app_feedback',4,'{\"status\":\"reviewed\"}','2026-07-07 06:32:21'),(17,1,'feedback.status','app_feedback',4,'{\"status\":\"archived\"}','2026-07-07 06:32:34'),(18,1,'feedback.status','app_feedback',4,'{\"status\":\"reviewed\"}','2026-07-07 06:32:46'),(19,1,'feedback.status','app_feedback',4,'{\"status\":\"archived\"}','2026-07-07 06:32:55'),(20,1,'user.toggle_active','user',6,'{\"active\":false}','2026-07-07 06:40:20'),(21,1,'user.toggle_active','user',5,'{\"active\":false}','2026-07-07 06:40:24'),(22,1,'user.toggle_active','user',7,'{\"active\":false}','2026-07-07 06:40:26'),(23,1,'rental.status','rental_request',2,'{\"status\":\"cancelled\"}','2026-07-07 06:45:33'),(24,1,'rental.status','rental_request',1,'{\"status\":\"approved\"}','2026-07-07 06:45:38'),(25,1,'dispute.refund','dispute',1,NULL,'2026-09-16 18:01:10'),(26,1,'listing.status','listing',9,'{\"status\":\"approved\"}','2026-09-21 00:42:40'),(27,1,'user.toggle_active','user',5,'{\"active\":true}','2026-09-28 05:27:30'),(28,1,'user.toggle_active','user',10,'{\"active\":false}','2026-09-28 05:32:40'),(29,1,'user.toggle_active','user',10,'{\"active\":true}','2026-09-28 05:33:07'),(30,1,'user.toggle_active','user',5,'{\"active\":false}','2026-09-28 05:33:12'),(31,1,'dispute.warning','dispute',2,NULL,'2026-09-28 06:05:54'),(32,1,'dispute.refund','dispute',3,NULL,'2026-09-28 06:08:30'),(33,1,'dispute.warning','dispute',4,NULL,'2026-09-29 05:28:14'),(34,1,'guide.clear_warning','user',3,NULL,'2026-09-29 07:04:32'),(35,1,'guide.clear_warning','user',18,NULL,'2026-09-29 07:04:35'),(36,1,'user.toggle_active','user',11,'{\"active\":false}','2026-09-29 09:14:15'),(37,1,'user.toggle_active','user',20,'{\"active\":false}','2026-09-29 09:14:18'),(38,1,'user.toggle_active','user',19,'{\"active\":false}','2026-09-29 09:14:21'),(39,1,'user.toggle_active','user',10,'{\"active\":false}','2026-09-29 09:14:29'),(40,1,'user.toggle_active','user',16,'{\"active\":false}','2026-09-29 09:14:43'),(41,1,'user.toggle_active','user',18,'{\"active\":false}','2026-09-29 09:14:45'),(42,1,'user.toggle_active','user',17,'{\"active\":false}','2026-09-29 09:14:47'),(43,1,'user.toggle_active','user',15,'{\"active\":false}','2026-09-29 09:14:49'),(44,1,'user.toggle_active','user',14,'{\"active\":false}','2026-09-29 09:14:51'),(45,1,'user.toggle_active','user',13,'{\"active\":false}','2026-09-29 09:14:53'),(46,1,'user.toggle_active','user',12,'{\"active\":false}','2026-09-29 09:14:54'),(47,1,'user.toggle_active','user',12,'{\"active\":true}','2026-09-29 09:14:59'),(48,1,'user.toggle_active','user',18,'{\"active\":true}','2026-09-29 09:15:02'),(49,1,'guide.approve','user',12,NULL,'2026-09-29 11:36:42');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time DEFAULT NULL,
  `guests` int(11) NOT NULL DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','confirmed','completed','cancelled','disputed','refunded') NOT NULL DEFAULT 'pending',
  `notes` varchar(500) DEFAULT NULL,
  `verify_token` varchar(64) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `promo_code_id` int(10) unsigned DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bookings_listing_fk` (`listing_id`),
  KEY `bookings_user_fk` (`user_id`),
  CONSTRAINT `bookings_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,2,2,'2026-07-01','19:00:00',2,2000.00,'disputed','Paid via GCash / e-Wallet QR · Ref: 3849292929','91d41b13d874dc2a0df22479e7d5d9ef',NULL,NULL,0.00,0,'2026-06-29 19:52:53','2026-09-29 05:26:46'),(2,5,2,'2026-07-15',NULL,10,1000.00,'refunded','Paid via GCash / e-Wallet QR · Ref: 93933922920','5639baebc198c1ce68a6b6f821eb3393',NULL,NULL,0.00,0,'2026-07-06 17:33:26','2026-07-06 17:34:14'),(3,5,2,'2026-07-15',NULL,10,1000.00,'confirmed','Paid via GCash / e-Wallet QR · Ref: 9494020202020','fe4fcb8c6080ed60c90a0f9fc3cd5d5a',NULL,NULL,0.00,0,'2026-07-06 17:35:53','2026-07-06 17:35:53'),(4,1,2,'2026-07-16',NULL,12,9600.00,'confirmed','Paid via GCash / e-Wallet QR · Ref: 938329292992','9f7bcf388ac32a1500a0d977dd2ded38',NULL,NULL,0.00,0,'2026-07-08 18:56:43','2026-07-08 18:56:43'),(5,6,2,'2026-07-16',NULL,1,50.00,'refunded','Paid via GCash / e-Wallet QR · Ref: 3838299229','1f7d59ce1aa0f1ef66e4d4b78fdc3cbf',NULL,NULL,0.00,0,'2026-07-08 18:58:28','2026-09-16 18:01:10'),(6,3,11,'2026-09-03','07:00:00',50,6000.00,'confirmed','Paid via GCash / e-Wallet QR · Ref: 20283728291','e4adfae83f392e45819702e297325ab8',NULL,NULL,0.00,0,'2026-09-02 09:57:48','2026-09-02 09:57:48'),(7,4,11,'2026-09-03','09:00:00',5,500.00,'confirmed','Paid via GCash / e-Wallet QR · Ref: ushshdbe','35d0328ea810c158ef247a30d361a735',NULL,NULL,0.00,0,'2026-09-02 10:01:37','2026-09-02 10:01:37'),(8,5,15,'2026-09-30','07:00:00',10,1000.00,'refunded','Paid via GCash / e-Wallet QR · Ref: 293929292929','957ab9dedfcfa917ff20beeb64ef3cc8',NULL,NULL,0.00,0,'2026-09-16 21:16:37','2026-09-28 06:18:06'),(9,1,15,'2026-09-17','06:00:00',10,8000.00,'completed','Paid via GCash / e-Wallet QR · Ref: 994922929','09b80e6eec11b10768919f9fd7a70dbe',NULL,NULL,0.00,0,'2026-09-16 21:17:45','2026-09-28 06:17:38'),(10,15,2,'2026-09-28','08:00:00',1,3200.00,'confirmed','Paid via GCash / e-Wallet QR · Ref: 0964575358885','2a9a708345ec36e0dc6284a7b5712504',NULL,NULL,0.00,0,'2026-09-28 05:24:32','2026-09-28 05:24:32'),(11,1,10,'2026-09-28','06:00:00',1,800.00,'refunded','Paid via InstaPay QR · Ref: 12334567','101ad1004bd02f504814e92f880f6eb4',NULL,NULL,0.00,0,'2026-09-28 05:32:37','2026-09-28 06:08:30'),(12,1,19,'2026-09-29','06:00:00',1,800.00,'completed','Paid via GCash / e-Wallet QR · Ref: 3736482','11c8bc60d19d5cb6f9a5b99bf59f7554',NULL,NULL,0.00,0,'2026-09-28 05:37:24','2026-09-28 06:17:41'),(13,11,10,'2026-09-28','06:00:00',1,2800.00,'disputed','Paid via GCash / e-Wallet QR · Ref: 1234567','a9b43ae1eed5438eb7f37a60da54e4de',NULL,NULL,0.00,0,'2026-09-28 05:57:41','2026-09-28 06:05:03'),(14,1,20,'2026-09-29','08:00:00',3,2400.00,'refunded','Paid via GCash / e-Wallet QR · Ref: 28282','511e3ac125da4f8a0fecbdec99f7f572',NULL,NULL,0.00,0,'2026-09-28 06:24:11','2026-09-28 06:25:06'),(15,9,20,'2026-09-30','18:00:00',12,12000.00,'completed','Paid via GCash / e-Wallet QR · Ref: hshsshhs','6fb17f68f66a283874a8b6d7ef5b50dc',NULL,NULL,0.00,0,'2026-09-28 06:26:58','2026-09-28 06:27:14');
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `icon` varchar(40) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Things to Do','things-to-do','compass','Tours, attractions & activities across Cebu',1),(2,'Tour Guides','tour-guides','user-check','Hire trusted local Cebuano guides',2),(3,'Hotels & Stays','hotels','bed','Resorts, hotels and stays',3),(4,'Restaurants','restaurants','utensils','Where to eat in Cebu',4);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_settings`
--

DROP TABLE IF EXISTS `conversation_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `partner_id` int(10) unsigned NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_settings_unique` (`user_id`,`partner_id`),
  KEY `conversation_settings_partner_fk` (`partner_id`),
  CONSTRAINT `conversation_settings_partner_fk` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_settings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_settings`
--

LOCK TABLES `conversation_settings` WRITE;
/*!40000 ALTER TABLE `conversation_settings` DISABLE KEYS */;
INSERT INTO `conversation_settings` VALUES (1,2,3,0,0,'2026-06-30 06:34:54');
/*!40000 ALTER TABLE `conversation_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dispute_files`
--

DROP TABLE IF EXISTS `dispute_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dispute_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dispute_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dispute_files_dispute_fk` (`dispute_id`),
  CONSTRAINT `dispute_files_dispute_fk` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispute_files`
--

LOCK TABLES `dispute_files` WRITE;
/*!40000 ALTER TABLE `dispute_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `dispute_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disputes`
--

DROP TABLE IF EXISTS `disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disputes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `guide_id` int(10) unsigned NOT NULL,
  `problem_type` enum('extra_payment','no_show','service_mismatch','unsafe','other') NOT NULL,
  `amount_requested` decimal(10,2) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('open','reviewing','resolved_refund','resolved_warning','rejected') NOT NULL DEFAULT 'open',
  `admin_note` varchar(500) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `disputes_booking_unique` (`booking_id`),
  KEY `disputes_user_fk` (`user_id`),
  KEY `disputes_guide_fk` (`guide_id`),
  CONSTRAINT `disputes_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disputes_guide_fk` FOREIGN KEY (`guide_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disputes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disputes`
--

LOCK TABLES `disputes` WRITE;
/*!40000 ALTER TABLE `disputes` DISABLE KEYS */;
INSERT INTO `disputes` VALUES (1,5,2,3,'extra_payment',500.00,'teketkyekyekhrmyeeymyemehmfmhdm','resolved_refund','Refund approved.','2026-09-16 18:01:10','2026-09-16 17:18:29','2026-09-16 18:01:10'),(2,13,10,18,'extra_payment',1000.00,'Ngayo og extra payment sig sakto ra tanan ako ge hatag','resolved_warning','Warning issued after dispute #2.','2026-09-28 06:05:54','2026-09-28 06:05:03','2026-09-28 06:05:54'),(3,11,10,3,'extra_payment',500.00,'Sample refund of the payment','resolved_refund','Refund approved.','2026-09-28 06:08:30','2026-09-28 06:08:16','2026-09-28 06:08:30'),(4,1,2,3,'extra_payment',500.00,'The guide says he needs an extra payment','resolved_warning','Warning issued after dispute #4.','2026-09-29 05:28:14','2026-09-29 05:26:46','2026-09-29 05:28:14');
/*!40000 ALTER TABLE `disputes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `favorites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `favorites_unique` (`user_id`,`listing_id`),
  KEY `favorites_listing_fk` (`listing_id`),
  CONSTRAINT `favorites_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favorites_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorites`
--

LOCK TABLES `favorites` WRITE;
/*!40000 ALTER TABLE `favorites` DISABLE KEYS */;
INSERT INTO `favorites` VALUES (1,2,3,'2026-09-16 17:36:42'),(4,2,15,'2026-09-27 15:56:15'),(8,19,1,'2026-09-28 05:34:42'),(9,20,9,'2026-09-28 06:45:26'),(10,20,2,'2026-09-28 06:45:28'),(11,20,5,'2026-09-28 06:45:53');
/*!40000 ALTER TABLE `favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guide_availability`
--

DROP TABLE IF EXISTS `guide_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guide_availability` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `blocked_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `guide_availability_unique` (`listing_id`,`blocked_date`),
  CONSTRAINT `guide_availability_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guide_availability`
--

LOCK TABLES `guide_availability` WRITE;
/*!40000 ALTER TABLE `guide_availability` DISABLE KEYS */;
/*!40000 ALTER TABLE `guide_availability` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guide_documents`
--

DROP TABLE IF EXISTS `guide_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guide_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `doc_type` varchar(60) NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `guide_documents_user_fk` (`user_id`),
  CONSTRAINT `guide_documents_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guide_documents`
--

LOCK TABLES `guide_documents` WRITE;
/*!40000 ALTER TABLE `guide_documents` DISABLE KEYS */;
INSERT INTO `guide_documents` VALUES (1,12,'valid_id','suyae.jpg','uploads/guide-docs/12/503be55b8d5f5825.jpg','2026-09-16 18:24:44'),(2,12,'tourism_accreditation','13.jpg','uploads/guide-docs/12/15936d4381f7ffae.jpg','2026-09-16 18:24:44'),(3,12,'other','suyae.jpg','uploads/guide-docs/12/008376f881a71644.jpg','2026-09-16 18:24:44'),(4,3,'valid_id','Valid government ID','uploads/guide-docs/3/valid-id.svg','2026-09-21 01:34:41'),(5,3,'guide_license','Tour guide license','uploads/guide-docs/3/guide-license.svg','2026-09-21 01:34:41');
/*!40000 ALTER TABLE `guide_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `listing_images`
--

DROP TABLE IF EXISTS `listing_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `listing_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `listing_images_listing_fk` (`listing_id`),
  CONSTRAINT `listing_images_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `listing_images`
--

LOCK TABLES `listing_images` WRITE;
/*!40000 ALTER TABLE `listing_images` DISABLE KEYS */;
INSERT INTO `listing_images` VALUES (1,9,'uploads/listings/3/b47060b8b3a98164.jpg',1);
/*!40000 ALTER TABLE `listing_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `listing_schedules`
--

DROP TABLE IF EXISTS `listing_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `listing_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` varchar(5) DEFAULT NULL,
  `capacity` int(10) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `listing_schedules_listing_fk` (`listing_id`),
  CONSTRAINT `listing_schedules_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `listing_schedules`
--

LOCK TABLES `listing_schedules` WRITE;
/*!40000 ALTER TABLE `listing_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `listing_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `listings`
--

DROP TABLE IF EXISTS `listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `listings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `title` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `area` varchar(120) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_unit` varchar(40) NOT NULL DEFAULT 'per person',
  `duration` varchar(80) DEFAULT NULL,
  `included` text DEFAULT NULL,
  `not_included` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sale_ends_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `listings_slug_unique` (`slug`),
  KEY `listings_user_fk` (`user_id`),
  KEY `listings_category_fk` (`category_id`),
  KEY `listings_status_idx` (`status`),
  CONSTRAINT `listings_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `listings`
--

LOCK TABLES `listings` WRITE;
/*!40000 ALTER TABLE `listings` DISABLE KEYS */;
INSERT INTO `listings` VALUES (1,3,1,'Moalboal Snorkeling','moalboal-snorkeling','Snorkel the vibrant coral reefs and turtle spots of Moalboal.','Explore the crystal-clear waters of Moalboal, Cebu. Snorkel over thriving coral gardens, swim alongside sea turtles, and witness the famous marine life of Panagsama Beach. Equipment and a local guide are included.','Moalboal, Cebu','Panagsama Beach, Moalboal, Cebu',9.9480020,123.3719180,800.00,'per person','2-3 hours','Snorkeling gear, local guide, boat transfer','Food and drinks, hotel pickup','uploads/places/sardine.jpg','approved',1,NULL,NULL,'2026-06-15 12:27:23','2026-07-08 18:57:58'),(2,3,4,'Istoria','kyles-resto-bar','Beachfront resto bar with local dishes and drinks in Moalboal.','Relax at Istoria Resto Bar in Moalboal, Cebu. Enjoy fresh seafood, Filipino favorites, ice cold drinks, and a laid back beachfront atmosphere  perfect after a day of diving and snorkeling.','Moalboal, Cebu','Panagsama Beach, Moalboal, Cebu',9.9480020,123.3719180,1000.00,'per table','','Table reservation','Food and drinks (pay on site)','https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=70','approved',1,NULL,NULL,'2026-06-15 12:27:23','2026-07-06 17:31:00'),(3,3,1,'Temple of Leah','temple-of-leah','Cebu\'s grand Roman-inspired temple, the \'Taj Mahal of Cebu\'.','Perched in the hills of Busay, the Temple of Leah is a majestic Greco-Roman temple built as a symbol of undying love. Marvel at the towering columns, grand staircases, bronze statues, and a sweeping view of Cebu City. A favorite spot for photos and history lovers.','Busay, Cebu City','Roosevelt Street, Busay, Cebu City',10.2397010,123.8105610,120.00,'per person','1-2 hours',NULL,NULL,'uploads/places/templeofleah.jpg','approved',1,NULL,NULL,'2026-06-15 12:49:40','2026-09-02 09:58:22'),(4,3,1,'Sirao Flower Garden','sirao-flower-garden','The \'Little Amsterdam\' of Cebu — fields of colorful celosia.','Sirao Flower Garden is famous for its vibrant rows of celosia (cockscomb) flowers set against the cool highlands of Cebu. Explore themed photo spots, windmills, and viewing decks. A must-visit for nature lovers and photographers.','Busay, Cebu City','Sirao, Busay, Cebu City',10.4140410,123.8721300,100.00,'per person','1-2 hours',NULL,NULL,'uploads/places/sirao.jpg','approved',1,NULL,NULL,'2026-06-15 12:49:40','2026-09-02 10:01:54'),(5,3,1,'Osmeña Peak','osmena-peak','The highest point in Cebu — jagged hills and sunrise views.','Rising 1,013 meters above sea level, Osmeña Peak is the highest point in Cebu. Hike through dramatic, jagged hills that resemble Bohol\'s Chocolate Hills and catch breathtaking sunrises with views of the sea on both sides of the island. Easy trek, unforgettable scenery.','Dalaguete, Cebu','Mantalongon, Dalaguete, Cebu',9.8076650,123.4648000,100.00,'per person','Half day',NULL,NULL,'uploads/places/osmena.jpg','approved',1,NULL,NULL,'2026-06-15 12:49:40','2026-07-06 17:36:06'),(6,3,1,'Casay Beach','casay-beach-dalaguete','Long stretch of fine sand on Cebu’s scenic southeast coast.','Casay Beach in Dalaguete offers a long shoreline of fine sand, calm waters, and a relaxed atmosphere away from the crowds. Perfect for swimming, picnics, and family weekends, with mountain backdrops and beautiful sunrises.','Dalaguete, Cebu','Casay, Dalaguete, Cebu',9.8198540,123.5496300,50.00,'per person','Day trip',NULL,NULL,'uploads/places/casay.jpg','approved',1,NULL,NULL,'2026-06-15 12:49:40','2026-07-08 18:58:37'),(7,3,1,'Kawasan Falls Canyoneering','kawasan-falls-canyoneering-badian','Turquoise waterfalls and cliff-jumping adventure in Badian.','Experience Cebu\'s most famous adventure: canyoneering through the stunning turquoise waters of Kawasan Falls in Badian. Jump off cliffs, swim through gorges, and slide down natural rock formations before reaching the majestic multi-tiered falls. Guides, gear, and safety equipment included.','Badian, Cebu','Matutinao, Badian, Cebu',NULL,NULL,1500.00,'per person','3-4 hours',NULL,NULL,'uploads/places/kawasan.jpg','approved',1,NULL,NULL,'2026-06-15 12:49:40','2026-06-15 14:46:09'),(8,3,1,'Oslob Whale Shark Watching','oslob-whale-shark-watching','Swim alongside gentle whale sharks in Tan-awan, Oslob.','Get up close with the gentle giants of the sea in Oslob, Cebu. Snorkel or dive beside butanding (whale sharks) in their natural feeding grounds — a once-in-a-lifetime, bucket-list experience. Includes briefing, boat, and snorkeling gear.','Oslob, Cebu','Tan-awan, Oslob, Cebu',NULL,NULL,1000.00,'per person','2-3 hours',NULL,NULL,'uploads/places/whaleshark.jpg','approved',1,NULL,NULL,'2026-06-15 12:49:40','2026-06-15 14:46:09'),(9,3,1,'Bojo River','bojo-river','Visitors frequently praise the peaceful atmosphere, scenic mangrove forests, and the relaxing pace of the river cruise.','Bojo River is a community-managed ecotourism destination in Aloguinsan, Cebu, Philippines, centered on a tranquil mangrove-lined river and its popular river cruise. It is widely recognized for sustainable, community-based tourism that highlights coastal conservation, local culture, and the river-to-reef ecosystem','Southern Cebu','6G4W+92W, Aloguinsan, Cebu',10.2026670,123.5453800,1000.00,'per person','4-6 hours','Guide fee','Lunch, Breakfast, Dinner','uploads/listings/3/b47060b8b3a98164.jpg','approved',1,NULL,NULL,'2026-09-21 00:42:28','2026-09-27 14:36:59'),(10,18,3,'La Joya Farm Resort & Spa','la-joya-farm-resort-spa','Hilltop farm resort & spa minutes from the Bojo River, Aloguinsan.','A tranquil hilltop retreat overlooking Aloguinsan and the Bojo River. Enjoy farm-to-table dining, an infinity pool, and full-service spa — the closest premium stay to the Bojo River eco-tour.','Aloguinsan, Cebu','Bojo, Aloguinsan, Cebu',10.2246000,123.5585000,3500.00,'per night','','Room accommodation','Tours and meals unless stated','https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80','approved',0,NULL,NULL,'2026-09-27 14:46:37','2026-09-28 05:55:13'),(11,18,3,'Kasai Village Dive Resort','kasai-village-dive-resort','Beachfront dive resort on Panagsama Beach, Moalboal.','Steps from the famous Moalboal sardine run and Panagsama Beach. Cozy rooms, a freediving school, and a seafront restaurant — perfect for snorkeling and diving trips.','Moalboal, Cebu','Panagsama Beach, Moalboal, Cebu',9.9490000,123.3800000,2800.00,'per night','','Room accommodation','Tours and meals unless stated','https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80','approved',0,NULL,NULL,'2026-09-27 14:46:37','2026-09-27 14:46:37'),(12,18,3,'Badian Island Wellness Resort','badian-island-wellness-resort','Private island wellness resort near Kawasan Falls, Badian.','An exclusive island resort with white-sand beach, wellness spa, and easy access to Kawasan Falls canyoneering. Ideal for a restful stay after your adventure.','Badian, Cebu','Badian Island, Badian, Cebu',9.8000000,123.3700000,6500.00,'per night','','Room accommodation','Tours and meals unless stated','https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80','approved',0,NULL,NULL,'2026-09-27 14:46:37','2026-09-27 14:46:37'),(13,18,3,'MJ\'s Beach Resort Oslob','mjs-beach-resort-oslob','Simple beachfront rooms beside the Oslob whale shark point.','Affordable beachfront rooms in Tan-awan, Oslob — a short walk from the whale shark watching area and Sumilon Island boats.','Oslob, Cebu','Tan-awan, Oslob, Cebu',9.4590000,123.3880000,1800.00,'per night','','Room accommodation','Tours and meals unless stated','https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80','approved',0,NULL,NULL,'2026-09-27 14:46:37','2026-09-27 14:46:37'),(14,18,3,'Casa Nostra Mountain View','casa-nostra-mountain-view','Cool-climate mountain lodge near Osmeña Peak, Dalaguete.','A cozy highland lodge in Mantalongon, Dalaguete — the jump-off to Osmeña Peak sunrise hikes and Casay Beach day trips.','Dalaguete, Cebu','Mantalongon, Dalaguete, Cebu',9.7900000,123.5200000,2200.00,'per night','','Room accommodation','Tours and meals unless stated','https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80','approved',0,NULL,NULL,'2026-09-27 14:46:37','2026-09-27 14:46:37'),(15,18,3,'Cebu Hilltop Hotel Busay','cebu-hilltop-hotel-busay','Panoramic city-view hotel in Busay, near Temple of Leah.','Perched in Busay with sweeping views of Cebu City. Walking distance to Temple of Leah and Sirao Flower Garden — a great base for uptown sightseeing.','Busay, Cebu City','Busay, Cebu City',10.3613810,123.8854900,3200.00,'per night','','Room accommodation','Tours and meals unless stated','https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80','approved',0,NULL,NULL,'2026-09-27 14:46:37','2026-09-28 05:27:15');
/*!40000 ALTER TABLE `listings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message_translations`
--

DROP TABLE IF EXISTS `message_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_translations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(10) unsigned NOT NULL,
  `target_lang` varchar(8) NOT NULL,
  `body` text NOT NULL,
  `source_lang` varchar(8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_translations_unique` (`message_id`,`target_lang`),
  CONSTRAINT `message_translations_message_fk` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message_translations`
--

LOCK TABLES `message_translations` WRITE;
/*!40000 ALTER TABLE `message_translations` DISABLE KEYS */;
INSERT INTO `message_translations` VALUES (1,11,'en','Are you OK','zh-CN','2026-09-28 05:29:59'),(2,3,'en','hi after','pl','2026-09-29 04:22:16'),(3,5,'en','you are pretty','tl','2026-09-29 04:22:19');
/*!40000 ALTER TABLE `message_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` int(10) unsigned NOT NULL,
  `receiver_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `source_lang` varchar(8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_sender_fk` (`sender_id`),
  KEY `messages_receiver_fk` (`receiver_id`),
  KEY `messages_listing_fk` (`listing_id`),
  CONSTRAINT `messages_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_receiver_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,2,3,NULL,'hello',1,'2026-06-29 22:08:39',NULL),(2,3,2,NULL,'hi',1,'2026-06-29 22:09:41','en'),(3,3,2,NULL,'hii po',1,'2026-06-30 06:21:51','pl'),(4,2,3,NULL,'boang',1,'2026-06-30 06:22:04',NULL),(5,3,2,NULL,'pretty kay ka',1,'2026-06-30 06:22:07','tl'),(6,2,3,NULL,'bayot ampota',1,'2026-06-30 06:34:32',NULL),(7,2,3,NULL,'hi',1,'2026-07-08 19:14:58',NULL),(8,2,3,NULL,'lami na',1,'2026-07-08 19:17:40',NULL),(9,2,3,NULL,'where are u',1,'2026-07-08 19:17:44',NULL),(10,10,3,NULL,'Hello, Are you the guide of Moalboal?',1,'2026-09-28 05:29:20',NULL),(11,3,10,NULL,'你好吗',1,'2026-09-28 05:29:55','zh-CN'),(12,1,18,NULL,'Administrator Warning\n\nYou have received an official warning from the GuideMate Administrator.\n\nReason: Warning issued after dispute #2.\n\nYour booking actions (Confirm / Complete / Cancel) are restricted until an administrator clears this warning. Please review this message and wait for admin follow-up.',1,'2026-09-28 06:05:54',NULL),(13,20,3,NULL,'hxhxhd',1,'2026-09-28 06:27:57',NULL),(14,2,18,NULL,'hello is this available?',1,'2026-09-29 05:07:45',NULL),(15,1,3,NULL,'Administrator Warning\n\nYou have received an official warning from the GuideMate Administrator.\n\nReason: Warning issued after dispute #4.\n\nYour booking actions (Confirm / Complete / Cancel) are restricted until an administrator clears this warning. Please review this message and wait for admin follow-up.',1,'2026-09-29 05:28:14',NULL),(16,3,1,NULL,'Okay, noted for this.',1,'2026-09-29 05:28:54',NULL),(17,1,3,NULL,'Administrator Notice\n\nYour account has been reviewed and unrestricted by the Administrator. You may Confirm, Complete, and Cancel bookings again.',0,'2026-09-29 07:04:32',NULL),(18,1,18,NULL,'Administrator Notice\n\nYour account has been reviewed and unrestricted by the Administrator. You may Confirm, Complete, and Cancel bookings again.',0,'2026-09-29 07:04:35',NULL),(19,1,12,NULL,'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.',0,'2026-09-29 11:36:42',NULL);
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(190) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES ('jalelmauc@gmail.com','c410f337583f6c3d7142c44a4ff2cdbeef7efaa3679e63cae128a332f1efb169','2026-07-06 18:25:37');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(40) NOT NULL DEFAULT 'card',
  `status` enum('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
  `reference` varchar(64) NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_reference_unique` (`reference`),
  KEY `payments_booking_fk` (`booking_id`),
  CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,2000.00,'gcash','paid','GM-71FC467EC8','2026-06-29 19:52:53','2026-06-29 19:52:53'),(2,2,1000.00,'gcash','refunded','GM-144497A11F','2026-07-06 17:33:26','2026-07-06 17:33:26'),(3,3,1000.00,'gcash','paid','GM-3373297568','2026-07-06 17:35:53','2026-07-06 17:35:53'),(4,4,9600.00,'gcash','paid','GM-F00DD30EAB','2026-07-08 18:56:43','2026-07-08 18:56:43'),(5,5,50.00,'gcash','refunded','GM-16C42003C9','2026-07-08 18:58:28','2026-07-08 18:58:28'),(6,6,6000.00,'gcash','paid','GM-9B30299D4E','2026-09-02 09:57:48','2026-09-02 09:57:48'),(7,7,500.00,'gcash','paid','GM-7663B190B9','2026-09-02 10:01:37','2026-09-02 10:01:37'),(8,8,1000.00,'gcash','refunded','GM-853C408ADD','2026-09-16 21:16:37','2026-09-16 21:16:37'),(9,9,8000.00,'gcash','paid','GM-93FC0E82D4','2026-09-16 21:17:45','2026-09-16 21:17:45'),(10,10,3200.00,'gcash','paid','GM-6724A7DACE','2026-09-28 05:24:32','2026-09-28 05:24:32'),(11,11,800.00,'instapay','refunded','GM-DEDCAAF7B7','2026-09-28 05:32:37','2026-09-28 05:32:37'),(12,12,800.00,'gcash','paid','GM-7EE210C3CC','2026-09-28 05:37:24','2026-09-28 05:37:24'),(13,13,2800.00,'gcash','paid','GM-E1DCD0941A','2026-09-28 05:57:41','2026-09-28 05:57:41'),(14,14,2400.00,'gcash','refunded','GM-F251A87043','2026-09-28 06:24:11','2026-09-28 06:24:11'),(15,15,12000.00,'gcash','paid','GM-DAA6D6D3B7','2026-09-28 06:26:58','2026-09-28 06:26:58');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_codes`
--

DROP TABLE IF EXISTS `promo_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `max_uses` int(10) unsigned DEFAULT NULL,
  `uses_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `min_spend` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promo_codes_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_codes`
--

LOCK TABLES `promo_codes` WRITE;
/*!40000 ALTER TABLE `promo_codes` DISABLE KEYS */;
INSERT INTO `promo_codes` VALUES (1,'CEBU6',6.00,NULL,NULL,'2026-12-31',NULL,0,1,'2026-09-29 08:29:12',8000.00),(2,'ISLAND300',NULL,300.00,NULL,'2026-09-30',NULL,0,1,'2026-09-29 08:29:12',3000.00),(3,'STAY10',10.00,NULL,NULL,'2026-11-15',NULL,0,1,'2026-09-29 08:29:12',5000.00);
/*!40000 ALTER TABLE `promo_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rental_requests`
--

DROP TABLE IF EXISTS `rental_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rental_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `vehicle_id` varchar(50) NOT NULL,
  `vehicle_name` varchar(160) NOT NULL,
  `vehicle_type` varchar(80) NOT NULL,
  `shop_name` varchar(160) NOT NULL,
  `location` varchar(160) DEFAULT NULL,
  `pickup_date` date NOT NULL,
  `rental_days` int(11) NOT NULL DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `customer_name` varchar(120) NOT NULL,
  `customer_email` varchar(190) NOT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','contacted','cancelled','completed','refunded') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(500) DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_status` enum('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(40) DEFAULT NULL,
  `payment_reference` varchar(80) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `id_document` varchar(255) DEFAULT NULL,
  `id_type` varchar(80) DEFAULT NULL,
  `id_number` varchar(120) DEFAULT NULL,
  `report_type` varchar(40) DEFAULT NULL,
  `report_message` varchar(1000) DEFAULT NULL,
  `report_status` enum('none','open','refunded','rejected') NOT NULL DEFAULT 'none',
  `report_created_at` timestamp NULL DEFAULT NULL,
  `owner_report_note` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rental_requests_user_fk` (`user_id`),
  KEY `rental_requests_status_idx` (`status`),
  CONSTRAINT `rental_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rental_requests`
--

LOCK TABLES `rental_requests` WRITE;
/*!40000 ALTER TABLE `rental_requests` DISABLE KEYS */;
INSERT INTO `rental_requests` VALUES (1,2,'scooter','Honda Click 125i','Scooter','Cebu Moto Rentals','Cebu City · Fuente Osmeña','2026-07-01',1,500.00,'Jalel Mauc','jalelmauc@gmail.com','09537330643','9:00AM NO DRIVER','approved',NULL,'2026-07-07 06:45:38','2026-06-30 05:34:59','2026-07-07 06:45:38','unpaid',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL),(2,6,'motorcycle','KTM 390 Adventure','Motorcycle','ADV Riders Cebu','Mandaue City · A.S. Fortuna','2026-07-01',1,1200.00,'Felix Baquerosa','felixbruce920@gmail.com','0912345697',NULL,'cancelled',NULL,NULL,'2026-06-30 06:27:18','2026-07-07 06:45:33','unpaid',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL),(3,2,'scooter','Honda Click 125i','Scooter','Cebu Moto Rentals','Cebu City · Fuente Osmeña','2026-09-30',2,1000.00,'Jalel Mauc','jalelmauc@gmail.com','09537330643','Pickup time: 11:00 · No need for drivers','approved',NULL,NULL,'2026-09-29 07:26:19','2026-09-29 07:26:19','paid','gcash','029392020302','2026-09-29 07:26:19','uploads/rentals/ids/2/090c4babcc1c188f.jpeg','Driver\'s License','929392920203',NULL,NULL,'none',NULL,NULL);
/*!40000 ALTER TABLE `rental_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_images`
--

DROP TABLE IF EXISTS `review_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `review_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `review_images_review_fk` (`review_id`),
  CONSTRAINT `review_images_review_fk` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_images`
--

LOCK TABLES `review_images` WRITE;
/*!40000 ALTER TABLE `review_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `title` varchar(160) DEFAULT NULL,
  `comment` text NOT NULL,
  `visited_on` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reviews_listing_fk` (`listing_id`),
  KEY `reviews_user_fk` (`user_id`),
  CONSTRAINT `reviews_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
INSERT INTO `reviews` VALUES (1,1,10,5,'Moalboal Snorkeling','The guide is very friendly and easy to talk with. I\'ll be back next year. Recommend guide to book!!','2026-09-28','2026-09-28 05:35:31'),(2,1,19,5,'Moalboal','Beautiful place','2026-09-28','2026-09-28 05:37:52'),(3,9,20,2,'hhh','hhg','2026-09-28','2026-09-28 06:44:45');
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guide','tourist','rental_admin','hotel_admin') NOT NULL DEFAULT 'tourist',
  `phone` varchar(40) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `guide_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `guide_review_note` varchar(500) DEFAULT NULL,
  `guide_reviewed_at` timestamp NULL DEFAULT NULL,
  `guide_warned` tinyint(1) NOT NULL DEFAULT 0,
  `guide_warning_note` varchar(500) DEFAULT NULL,
  `admin_totp_secret` varchar(64) DEFAULT NULL,
  `admin_totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `oauth_provider` varchar(20) DEFAULT NULL,
  `oauth_id` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_oauth_unique` (`oauth_provider`,`oauth_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrator','fbaquerosa@gmail.com','$2y$10$fWMGmvoZB7Ll9C.emzfPjeiUxcePTSYQiSqOxViUCWEIPJtqGEZJa','admin',NULL,NULL,'Platform administrator.','Cebu City',1,'none',NULL,NULL,0,NULL,'N4BXKWOIT2MEMUYD',0,'2026-06-15 11:51:31','2026-09-28 06:30:46',NULL,NULL,NULL),(2,'Jalel Mauc','jalelmauc@gmail.com','$2y$10$VWE9XjTCU1cch9Q8daRguuNBGBn3prrwsJXNfpMk83GYF9Im1ZV0G','tourist',NULL,'uploads/avatars/2/f5800b8ea0de67da.jpeg',NULL,NULL,1,'none',NULL,NULL,0,NULL,NULL,0,'2026-06-15 12:15:47','2026-09-29 09:15:30','2026-09-29 09:15:30',NULL,NULL),(3,'Felix Baquerosa','guide.moalboal@guidemate.local','$2y$10$8kopCDTNm6m3GZ5u/ahiR.GcEvuYEWhdAJgN0J5z00xveI5FyQxQ6','guide','','uploads/avatars/3/7d067d6e222603c5.jpg','Local guide for snorkeling tours and dining around Moalboal.','Moalboal, Cebu',1,'approved','[Warning 2026-09-17] Dispute #1: refund issued to tourist.\n[Warning 2026-09-28] Dispute #3: refund issued to tourist.\n',NULL,0,NULL,NULL,0,'2026-06-15 12:27:23','2026-09-29 07:14:54',NULL,NULL,NULL),(4,'Dranreb Misa','misadranreb4@gmail.com','$2y$10$Lab.KTqDLyDt/kKhF0Uvy.6ZF9m/CkRSR2OeMPj0U7GObooQkOJp.','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-06-16 05:51:17','2026-06-16 05:56:06',NULL,NULL,NULL),(5,'Christian Yongzon','chrisyongzon112@gmail.com','$2y$10$Ozzv9CMRew1eYpLa9l7ZqO1njckDOgc8f8cJjdB00VUaWUKOow6sG','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-06-16 07:19:09','2026-09-28 05:33:12','2026-06-30 06:24:35',NULL,NULL),(6,'Felix Baquerosa','felixbruce920@gmail.com','$2y$10$tU4jxJbTXUf.kEsjkWGwzeTt.egYSbBSwkhvFGVSDnaiJWOSVrlyW','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-06-29 21:21:42','2026-07-07 06:40:20','2026-06-30 06:28:52',NULL,NULL),(7,'Junril Ubas','marwadmm648@gmail.com','$2y$10$v7sI0JgbCaU7P9I9CpuOXepyK.D8YFBojEytnwanCrC4EVO4sr6EG','tourist',NULL,'uploads/avatars/7/e6c5646802a09272.jpeg',NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-06-30 06:15:50','2026-07-07 06:40:26','2026-06-30 06:39:09',NULL,NULL),(10,'Christian Yongzon','christianyongzon143@gmail.com','$2y$10$JIgB1Q0pJAA74kchQGQtLeFoU4vAntInM6BQKFawXgwkcUxkNAnJy','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-02 09:53:06','2026-09-29 09:14:29','2026-09-28 06:10:11',NULL,NULL),(11,'john ivan laluna','johnivan558@gmail.com','$2y$10$17d10r3BIiOvtMihQq4hxu9dJ4ipN6aMyzgRQ6eUI9bmJ2nhPEI4O','tourist',NULL,'uploads/avatars/11/e4f66376abcd685d.jpeg',NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-02 09:54:35','2026-09-29 09:14:15','2026-09-02 10:14:56',NULL,NULL),(12,'Jane De Leo','janethmauir@gmail.com','$2y$10$ZDBVp/Ta4c3J3REK/uaXXOI2xkPlaCU56A00E6exFKNRBwpqsry5u','rental_admin',NULL,NULL,NULL,NULL,1,'approved',NULL,'2026-09-29 11:36:42',0,NULL,NULL,0,'2026-09-16 18:24:44','2026-09-29 11:36:42',NULL,NULL,NULL),(13,'Google User','google_ad219a2e8778b055604e@social.guidemate.local','$2y$10$PvGJg82k.5cGDhyOMEuZV.bxbP5FC4o49HpYmtdgC7X765iBfcPBq','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-16 19:23:02','2026-09-29 09:14:53','2026-09-16 19:26:57','google','demo_google-bee4fc1e-6038-4d4c-b345-923313014eb0'),(14,'Facebook User','facebook_2b99f4d0c198ed50e627@social.guidemate.local','$2y$10$AZrgvytfyLnxFfytEcCmX.hMJgGWEv5g1pL/ZXIK4IflUVROnGTYK','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-16 19:23:15','2026-09-29 09:14:51','2026-09-27 13:37:50','facebook','demo_facebook-48a92cad-2f85-4635-8686-1bb37ad87ff1'),(15,'Jiji Plays','google_5d8acb71805db42e30ee@social.guidemate.local','$2y$10$X5fIuCwd77MPxE3NxjjzWOvdoF7y7YxinZl2BGtk8CCpqZ4T5gm/C','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-16 20:09:40','2026-09-29 09:14:49','2026-09-16 21:18:04','google','100827659670400175015'),(16,'Janeth Mauir','google_c50d84dbf58163b7f465@social.guidemate.local','$2y$10$U4J505ac9y8Vj5HGWIximuNeFd/9SDbIsGM0EeQKqRQ2w.KLPnffu','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-16 20:25:54','2026-09-29 09:14:43','2026-09-27 14:23:56','google','101669896341527826136'),(17,'Google User','google_a03ad608fa0ae6a9640b@social.guidemate.local','$2y$10$CGRyWQpKEGtAPhJU31BT5e3/8XJPg2RMvITCzP91jnpFQK9Z5Z0Oa','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-16 20:28:29','2026-09-29 09:14:47','2026-09-16 21:07:47','google','101982035606372835617'),(18,'Hotel Admin','stays@guidemate.local','$2y$10$M/j8Dl4te4jzaR1A13TA.OoYy6d1dl.fvWTPYRw1jGDocOhz58FVK','hotel_admin','',NULL,'Verified accommodation partner on GuideMate.','Cebu, Philippines',1,'approved',NULL,NULL,0,NULL,NULL,0,'2026-09-27 14:46:37','2026-09-29 09:15:02',NULL,NULL,NULL),(19,'Benjohn12','benjohn060810@gmail.com','$2y$10$qjEJx3qNhhuGf5/4MPw31.7467jH7oiCzVjnj0ZfO/pK.mINZG3E6','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-28 05:33:47','2026-09-29 09:14:21','2026-09-28 05:39:26',NULL,NULL),(20,'johnivan','johnivan58@gmail.com','$2y$10$4zfkIgrXXDjpPkMd.hkySOfofEqke/URdyuz36aE9WfODhlZWN7dC','tourist',NULL,NULL,NULL,NULL,0,'none',NULL,NULL,0,NULL,NULL,0,'2026-09-28 06:22:18','2026-09-29 09:14:18','2026-09-28 06:47:58',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voucher_redemptions`
--

DROP TABLE IF EXISTS `voucher_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voucher_redemptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_redemptions_user_code` (`user_id`,`code`),
  KEY `voucher_redemptions_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voucher_redemptions`
--

LOCK TABLES `voucher_redemptions` WRITE;
/*!40000 ALTER TABLE `voucher_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `voucher_redemptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'guidemate'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-29 20:06:42
