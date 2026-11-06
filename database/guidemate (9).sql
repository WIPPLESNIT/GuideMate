-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2026 at 03:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `guidemate`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_appeals`
--

CREATE TABLE `account_appeals` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `suspension_reason` varchar(255) NOT NULL DEFAULT '',
  `appeal_message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_appeals`
--

INSERT INTO `account_appeals` (`id`, `user_id`, `name`, `email`, `suspension_reason`, `appeal_message`, `status`, `admin_notes`, `reviewed_at`, `reviewed_by`, `created_at`) VALUES
(4, NULL, 'John Doe', 'johndoe@sample.com', 'have a violation in our privacy', 'wala man koy ge labag sa policy basin na mali ramo', 'approved', 'Approved by admin on profile update', '2026-10-09 01:32:33', 1, '2026-10-09 01:31:06'),
(5, NULL, 'Sample Sample', 'samplesample@sample.com', 'waewae', 'asdasd', 'approved', 'Approved by admin on profile update', '2026-10-09 01:37:17', 1, '2026-10-09 01:36:57'),
(6, NULL, 'Junril Ubas', 'marwadmm648@gmail.com', 'Violation of Community Guidelines', 'Tantado', 'approved', 'Approved by admin on profile update', '2026-10-14 14:26:45', 1, '2026-10-14 13:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 2, '8f2dae86bab6437055c6c97a03e140cfbf966227e8b56b010768a2919d4ece96', '2026-07-15 20:15:47', '2026-06-15 12:15:47'),
(2, 2, '096b1d06632ffb5e91642fa02f0239870cce64e24dfb68e67a20fcc4e03398de', '2026-07-15 20:16:00', '2026-06-15 12:16:00'),
(3, 2, '706a768748a7ef3cc95b3b281db8fbae4a3ec448578e5ab4e90171336be7bcc1', '2026-07-15 20:45:15', '2026-06-15 12:45:15'),
(4, 2, 'e3dfd8c2cf4ac3ec824959191e69893513566f4d73134d02576d65f76dd50131', '2026-07-15 20:48:27', '2026-06-15 12:48:27'),
(5, 2, '51c01028697917fd704e2dc3f31e61759687e42d08cf0d8db849425a8e14bbc0', '2026-07-15 21:15:56', '2026-06-15 13:15:56'),
(6, 2, 'd7c5a4dc4092980357f7ccf49ad1e4d824a7e2058ab8d926390099c8f49b3f3f', '2026-07-15 21:50:34', '2026-06-15 13:50:34'),
(7, 2, 'd3a647dcb3e6031d1e1ce7b4eb3124a631d8e53964514c17067749d6ef340a2c', '2026-07-15 22:32:11', '2026-06-15 14:32:11'),
(8, 2, 'cb73e1cd494d2ad8ca7f2fa45e1eeaf2f5ac8d5c68f4b92ec3db705fc1506191', '2026-07-15 22:35:34', '2026-06-15 14:35:34'),
(9, 2, '23a7e24a011199b37cf132efc2227f1440d046f72f234c5f92bee44896cec544', '2026-07-15 22:42:54', '2026-06-15 14:42:54'),
(10, 2, '2a71210d7b0e6659e8927b3a1f486b5643543d112e41ab09d6eef7ee0bed1eda', '2026-07-15 22:44:50', '2026-06-15 14:44:50'),
(11, 2, 'f3c93e4958bd85bc6bbc1e7aa3e0bb47bf4cb9f521b6231175a9deccc20f037f', '2026-07-15 22:48:11', '2026-06-15 14:48:11'),
(12, 2, '5540e7b6870160958cd62bc139cf2059e4220cc3c8e81a2e3955be69b43edcea', '2026-07-15 22:48:48', '2026-06-15 14:48:48'),
(15, 2, '1d484ef97d62b7067d5654b82376c7956692ce812263855e5c329ca401e5ccc5', '2026-07-16 14:57:52', '2026-06-16 06:57:52'),
(16, 2, '8fc3c94944d9282b4bb0e1f36a7a1402f2da56e6a6cfca64b33e65bb9f3fcff6', '2026-07-16 15:04:43', '2026-06-16 07:04:43'),
(17, 2, 'cf7496279f5b5dc483b45f55f29fb39ffb718fdec9f07428602627864b378768', '2026-07-16 15:12:37', '2026-06-16 07:12:37'),
(18, 2, '1bce5b70539430dd0b7facb1fe300f589545e2a299b58c21ed6c0b3b189df12d', '2026-07-16 15:15:16', '2026-06-16 07:15:16'),
(19, 2, 'bb4e37b635ed984109b8d7848f4a467908f62fafdd5f5acabe5dfdae8910bffa', '2026-07-16 15:18:16', '2026-06-16 07:18:16'),
(22, 2, '069559a09e19e610326af85636350cf359dab1b845295e29162f22a2c119ee80', '2026-07-21 23:07:11', '2026-06-21 15:07:11'),
(23, 2, 'b2c09ad065b67d3259263f3b9d13c49df11f4014b5d33a22932be5069fb7bad3', '2026-07-21 23:09:48', '2026-06-21 15:09:48'),
(24, 2, 'd964012f0b1b582fcf1d780bed69518ecbc3407acf4d259855ed8774e3a6d3ef', '2026-07-21 23:10:06', '2026-06-21 15:10:06'),
(25, 2, '87c4649c5eef8a640da61a9b288a49ecd070720bf677a9a2d5848a99ab9449c1', '2026-07-21 23:15:34', '2026-06-21 15:15:34'),
(26, 2, '5ac517357655fdb1ef5b174fc756cef89400c9c73df9dd9764e0325da8433931', '2026-07-30 03:24:23', '2026-06-29 19:24:23'),
(27, 2, '2f6ef26e5875fceccdf5c9e90cdd969081de0c5a191f99711ccfa148cede4f63', '2026-07-30 03:26:20', '2026-06-29 19:26:20'),
(28, 2, '394441acdf96ed4dd206231d6f3b561fd18be9ea6c5581d9359e4c0ce1d43bb2', '2026-07-30 03:29:23', '2026-06-29 19:29:23'),
(29, 2, '1d4ba90c4c3e7881f31ba3ccf61e1c9b08a93413d1f79431cd83a190d3c0200e', '2026-07-30 03:30:04', '2026-06-29 19:30:04'),
(30, 2, '0e800289a055d97731b3208e810ac7ea03543e7b6dcdcf10d09b665a0e1a08d7', '2026-07-30 03:31:41', '2026-06-29 19:31:41'),
(31, 2, '8a92444589ea5c807845fffa09a671bb60e8c89142ccaba6bc6b9778481bd84e', '2026-07-30 03:34:35', '2026-06-29 19:34:35'),
(32, 2, 'd11d305f9a9783cc11e543dfa6f72dea64bbe8d82b7bf383f5d865e0a4250f48', '2026-07-30 03:37:23', '2026-06-29 19:37:23'),
(33, 2, 'ef385fcb462e9776f84ab6985860435b1ca63e54ef015be36044c92a789174a9', '2026-07-30 03:38:26', '2026-06-29 19:38:26'),
(34, 2, '4d9f69152bd0eb0ad5f886acdc31cb1285bbbad330b33f74c04a76829c0167de', '2026-07-30 03:38:41', '2026-06-29 19:38:41'),
(35, 2, '813a2564d46c1f307044931b2ca0fc42d509ec9d08bcb97ae3a5611af2a12e8f', '2026-07-30 03:39:50', '2026-06-29 19:39:50'),
(36, 2, 'db52705476518ef296185d921817c2eca3f4164d9ca92919917f7e4219d7e9ca', '2026-07-30 03:42:32', '2026-06-29 19:42:32'),
(37, 2, '0c0449c989413b3465a8c40fe2f43a4e4d9309c99fe11a6ea1ddce04695e928e', '2026-07-30 03:45:14', '2026-06-29 19:45:14'),
(38, 2, 'bb0f4fb6feec41bc258d49a8a63792cd711e21689b3b9bcdfbdcae5147b32433', '2026-07-30 03:45:44', '2026-06-29 19:45:44'),
(39, 2, '2f2b9f3e761be6099f9eacd956b1b9ba9ac8e84acc17216f7581eafd42918516', '2026-07-30 03:46:28', '2026-06-29 19:46:28'),
(40, 2, 'f681f73964e2b6fc9936d75dde9c08eb73c25ca2f80d14c2ea913623c9ad4c7d', '2026-07-30 03:47:22', '2026-06-29 19:47:22'),
(41, 2, '99316d2aa4fad31e14a38779b416c8175e1100684af476b80d0e3a5da74e4a28', '2026-07-30 03:48:56', '2026-06-29 19:48:56'),
(42, 2, '18ab15dc63b7ea94543c1295c763e4b66cefbbec889fb7089ed6df165a72277a', '2026-07-30 03:49:49', '2026-06-29 19:49:49'),
(43, 2, 'c01fcebfbb364131bfe7ecf62ea823a18c253d523e450cea84d99348302b67c5', '2026-07-30 03:52:27', '2026-06-29 19:52:27'),
(44, 2, 'a1a116057ac42e1637a2aae24c735a81b80b97c8322b8713af4ddc710d3b23a4', '2026-07-30 04:04:03', '2026-06-29 20:04:03'),
(46, 2, 'bf5235b1bf253b7aa78f52be12e5588df0a6a65a7925657bd48ca6e4f98408cb', '2026-07-30 04:10:04', '2026-06-29 20:10:04'),
(47, 2, 'e74bc71297fdaf18354f118d87ff1d87701892d3f33df3d628c1096f0e00e1e0', '2026-07-30 04:11:32', '2026-06-29 20:11:32'),
(49, 2, '8ca053481b03bec3e5e26560c557509c495e3a738fefb89dfa3c3af6f4ec5dff', '2026-07-30 04:16:56', '2026-06-29 20:16:56'),
(50, 2, '90bfbcee19bcbfd15412e100906c49f07733094bd4f8eb7d31e034463bfaf038', '2026-07-30 04:21:25', '2026-06-29 20:21:25'),
(52, 2, 'a8c0baf8c14a6c990a360cc5c7ee59aaaa0476257811db86e1780fab8b4784ad', '2026-07-30 04:28:57', '2026-06-29 20:28:57'),
(53, 2, 'df164628e7d998c3b44af2af2b8654cbeb6e8f014092f609c3bcbe5f08e0038c', '2026-07-30 04:29:38', '2026-06-29 20:29:38'),
(56, 2, '2a7223e2b78919d4fb35025c06e743f71ac888962d9f16805e22c1f4d4fa8e34', '2026-07-30 04:33:11', '2026-06-29 20:33:11'),
(57, 2, '157ab46c83e6fe8c1cd930eb0d3d9870c7df671a55c38154f2a5b289f416c542', '2026-07-30 04:34:46', '2026-06-29 20:34:46'),
(58, 2, '6b57ae0608e4e431acf72298a5f5cf651bb4a2edc020f897e7e3211f19f768a1', '2026-07-30 04:38:18', '2026-06-29 20:38:18'),
(59, 2, '983f92f651cfc15e3128de345c6aff25e8eca6cdcc35d8e1e8525ff68ed0db71', '2026-07-30 04:49:25', '2026-06-29 20:49:25'),
(61, 2, '182ec4787bd4c9d638a046d01f9f7b004f3c6aa1160f9ead98f5b991aafe8518', '2026-07-30 04:50:33', '2026-06-29 20:50:33'),
(63, 2, '214061e2edc8add34edcab364e6554490afe6dd2edb1c3de830972010bfefcfa', '2026-07-30 04:55:42', '2026-06-29 20:55:42'),
(65, 2, 'a4057b38ff261ddd59a33be8820fd43da2fcd1f9f991ae62d091033b93dbf3ae', '2026-07-30 05:00:43', '2026-06-29 21:00:43'),
(66, 2, 'c716ae032e3c3cda13733b09869be5aad218ff9e0e06a8b06960936910bcc5a3', '2026-07-30 05:01:56', '2026-06-29 21:01:56'),
(67, 2, '5ea24fbc6e98dc0e5955fa7d35b61e07dbb1e65fbf24b50756e352ef0b4d6d75', '2026-07-30 05:05:18', '2026-06-29 21:05:18'),
(68, 2, '5c059b31123ad751dc10304eb879faab8176022e6fbdc06629b4e71d85ef2bb5', '2026-07-30 05:06:19', '2026-06-29 21:06:19'),
(69, 2, 'a03c6206c7d70d98072fbc9d693ea211798879fa838baa2ecab333347051a433', '2026-07-30 05:06:55', '2026-06-29 21:06:55'),
(70, 6, 'b71a95778adf312ddb25cc4b9bb413b8ba1d6d62fe284d37aae373e0ff4988a8', '2026-07-30 05:21:42', '2026-06-29 21:21:42'),
(71, 6, 'f87eef1be337a7914b68ea7831831119d200f8e5871118ee934e725dd597d8af', '2026-07-30 05:22:01', '2026-06-29 21:22:01'),
(72, 2, '8adf7b28cf39aece449b4374b8ab7a36235392e6ddb28146c661e32938527c1a', '2026-07-30 05:27:38', '2026-06-29 21:27:38'),
(74, 2, 'f66f83f0366defb6c7705839a837dc25e74082b9171134122ffc2aa684001c8a', '2026-07-30 05:32:15', '2026-06-29 21:32:15'),
(75, 2, '080a55838f05bc1ea948e53d4b4136d1b7e5cdff33f6692038743b5aa6346707', '2026-07-30 05:34:16', '2026-06-29 21:34:16'),
(76, 2, '8082b12a8676eda6135f1777889c5de11c20d6c08c882dfef2ab24d9294841bd', '2026-07-30 05:42:06', '2026-06-29 21:42:06'),
(77, 2, '4954a9b9cbc488ddf9e4575202c87bd735cfe236aaa417327ef3699447b708a4', '2026-07-30 05:45:00', '2026-06-29 21:45:00'),
(78, 2, '09f9033ef9b9a70c5ebe93052090dc89b1e5f72b34048d79c7c8e4fd137d8aa8', '2026-07-30 06:05:31', '2026-06-29 22:05:31'),
(79, 2, '3613d9cd0e019e250f7b707badfb347360fbc45416956b3f8a9cbae0dc092fba', '2026-07-30 06:13:43', '2026-06-29 22:13:43'),
(80, 2, 'a92b5977828bc23e6a4c846043b69f73ea034d636d853b1b447f9fa8724a7543', '2026-07-30 06:25:27', '2026-06-29 22:25:27'),
(81, 2, '67e6809723617416f3197e06fd7f437f595e7baeb8eb9462bac1274b0dd80c8c', '2026-07-30 06:27:27', '2026-06-29 22:27:27'),
(82, 2, '40dcbe91626660b67a32e959a5fb7860acbf6950ced7692bac79e47957d3b5ee', '2026-07-30 06:44:48', '2026-06-29 22:44:48'),
(83, 2, 'ab3127dc7c7585b54d2fa03f3ef1630eca8e9fead7be4003fa213be57fc6ac5e', '2026-07-30 06:54:50', '2026-06-29 22:54:50'),
(84, 2, '04aa142febb4dba502bdab108b5cfcdb4be62bd15eebd6b84aa8eedf5dd9da45', '2026-07-30 07:15:44', '2026-06-29 23:15:44'),
(85, 2, 'e40df003f97e3c6041b691d52862eb1f9c40fe78d3468c39ba278274e12cfc66', '2026-07-30 12:11:26', '2026-06-30 04:11:26'),
(86, 2, '4c06cfd5aa3c73db586ec5c7f06ea1116781b16a2fa7e41e7d53afafaecf8ac0', '2026-07-30 12:13:21', '2026-06-30 04:13:21'),
(87, 2, '47ac7ec51113f44734a12e3db8a2081702accad83a2f336e25927931e0407809', '2026-07-30 12:14:02', '2026-06-30 04:14:02'),
(88, 2, '6b06abc3b2f54aae3a926a10514b1898aac94b8ac58151826a9fea2c5a898c28', '2026-07-30 12:15:43', '2026-06-30 04:15:43'),
(89, 2, 'a1080bd2eb12c466f6e3459f3ac11e919c9312d364635a4c18dbbc70e5fda3ef', '2026-07-30 12:19:17', '2026-06-30 04:19:17'),
(90, 2, '6a05393916c6fae216560b38c3a5aee1a4035a542e1522a5c0b7c74164799e96', '2026-07-30 12:20:34', '2026-06-30 04:20:34'),
(91, 2, 'e53e61af2a3cdf069495f4b4945c220df2420f6e5ab6fd6bc127e138c67a5f22', '2026-07-30 12:26:59', '2026-06-30 04:26:59'),
(92, 2, 'd8450f8c475bf01c2f5c894ec8e113cb476e303b456157c6df0aea011f6e5394', '2026-07-30 12:28:33', '2026-06-30 04:28:33'),
(93, 2, 'dca722820179ab3194630c7e3bd2502b5cc53d0134d7ea96c580e647b0007da4', '2026-07-30 12:29:16', '2026-06-30 04:29:16'),
(94, 2, 'a9283edb0dd93a6a0f0b10598a5ffcdfbe2fdc4a2a86a335f63be7084810e41d', '2026-07-30 12:32:49', '2026-06-30 04:32:49'),
(95, 2, 'fb063dcf70b96694ac0ff81fab8e3c28a5718a82230a1effe01b0a5e7cf631a0', '2026-07-30 12:35:01', '2026-06-30 04:35:01'),
(96, 2, '6d7b5134dcba11134e2cbce17547ebb3adbd5dbc3c9b40026575425d437f44ae', '2026-07-30 12:48:14', '2026-06-30 04:48:14'),
(97, 2, '25a79a2e2bcf6a3fca943ab32fff2cbdd1c98d70b7fcce731117145f15e15b71', '2026-07-30 12:56:19', '2026-06-30 04:56:19'),
(98, 2, '0d2e41c5805b18b9463ec15d73ddd9243f499c4d4ec3946e27d7eaddf93211ae', '2026-07-30 12:59:25', '2026-06-30 04:59:25'),
(99, 2, '7b96e8d550e6057d5e827986409337f4a7d3f1cb35de5124e58f370767ec24b9', '2026-07-30 13:11:21', '2026-06-30 05:11:21'),
(100, 2, '8dc576947e4bb492f1b37b4e1718ea14527d04e87039732f0ce2f80becf65baf', '2026-07-30 13:13:39', '2026-06-30 05:13:39'),
(101, 2, '40ad084e111a29be9affdf9704ae62194f07208ed5a1d7b6908930d2b3e1dd16', '2026-07-30 13:14:57', '2026-06-30 05:14:57'),
(102, 2, 'bbe58d4109944998b777b391a0b43f5989ded6926082976cf50c65e5f5001c8d', '2026-07-30 13:17:04', '2026-06-30 05:17:04'),
(103, 2, '33e8d3d6d6c435085bcd2b781e4f124766253e3f5f9ded9679ea209ddb593e36', '2026-07-30 13:34:20', '2026-06-30 05:34:20'),
(104, 2, 'd5c5227fac93304944c5d749b1ae2ff3a77fbf75bfdcbf5330e30e8367d91f68', '2026-07-30 14:12:01', '2026-06-30 06:12:01'),
(105, 6, '4f3566c52162642489e1fd449ff4899dc9b8dbe425a9f9a3db4f9366c66476a6', '2026-07-30 14:15:21', '2026-06-30 06:15:21'),
(106, 2, 'a223e8f143b9a2a710da7127ba475c41a6ec718a8ab1a0b3678bd01e7e5f108f', '2026-07-30 14:15:31', '2026-06-30 06:15:31'),
(110, 6, '1889d6dbf212b79816fa8ccded21d8002c4078714c793e8683568ecb7f570521', '2026-07-30 14:25:51', '2026-06-30 06:25:51'),
(111, 6, 'd6755ebc39470d342de886564e3df60ecc3bb7b1b6b50a696fd6c3dd56890dbd', '2026-07-30 14:26:56', '2026-06-30 06:26:56'),
(113, 2, 'f12f4e290082fd7fe66066b882a7c400f89169b1fc77ae4ba84f03873055f3b4', '2026-07-31 12:54:46', '2026-07-01 04:54:46'),
(114, 2, '26171cb4214296790f3ca3bb926a49550e781403bba56358911a895044a997e2', '2026-07-31 13:19:10', '2026-07-01 05:19:10'),
(115, 2, 'e67b8a8d66546de0ae1d3d1346f85c5eb44629ab18c72e423ea80caa68074965', '2026-08-05 20:22:09', '2026-07-06 12:22:09'),
(116, 2, '2fb9bc6a2d87148c1695f4b2690fec340081fb8c65b092bf8d2efdb1c2f53795', '2026-08-05 20:31:27', '2026-07-06 12:31:27'),
(117, 2, '894ed69464d73013818574450b391deb1c10303e1d5f39ef48b5bee07738c2db', '2026-08-05 20:36:49', '2026-07-06 12:36:49'),
(118, 6, '855b718c800a9fc3236ce4137a8627941ac2f1f72aad0bc2310f3dbfe40acc1a', '2026-08-05 20:43:57', '2026-07-06 12:43:57'),
(119, 6, 'f9440b59b427aba34595ec34aae9ab843230e3aee4794cd556b5d9202dad7f9c', '2026-08-05 20:44:18', '2026-07-06 12:44:18'),
(120, 6, 'b70d34af66a5b2cfa03f862307eb06b027997abb14d8105852e52de634ca7a5c', '2026-08-05 22:44:11', '2026-07-06 14:44:11'),
(121, 6, '67676d3c8ac90b0b0f9b7cd86a693c82d86824d3a7b577465a6a59b573d9189f', '2026-08-05 22:44:38', '2026-07-06 14:44:38'),
(122, 2, '20dc0f41e51cade64e2c1df6403fb39519d618ffc3a18b468834eaf590b17539', '2026-08-05 22:45:07', '2026-07-06 14:45:07'),
(123, 2, 'e3c8da0b292d58497ece97f628f4f351588a1da207ee5b5ebad82deeb95bfb94', '2026-08-05 22:48:35', '2026-07-06 14:48:35'),
(124, 2, 'f9b1a99699d23044e0454dc5aea96a9beb5bd6857cb516a435487adbcf04bf1b', '2026-08-05 22:50:36', '2026-07-06 14:50:36'),
(127, 2, '5f37bf1039e23313a768e67f2f4d7ba7f689873d10c3d9fae9762d6c204136b5', '2026-08-05 22:56:44', '2026-07-06 14:56:44'),
(128, 2, 'c93bbc367ced66110867b0d18e0e43cf5c2dfc71000d2371f38d210cffc73f3e', '2026-08-05 23:03:58', '2026-07-06 15:03:58'),
(130, 2, '4351adb93a641078054589a2b0f087ab6a9c51e1dcf4be629b7e1481c77560b3', '2026-08-05 23:19:13', '2026-07-06 15:19:13'),
(131, 2, '0406570b5d264575901b33923262d7ca2c551ce87478b949b92376c94cd6ba20', '2026-08-05 23:22:58', '2026-07-06 15:22:58'),
(133, 2, 'fd34db84602ac64326974f9a85726fd4880325d50ef2ffe5e49ba84e30194aae', '2026-08-05 23:43:24', '2026-07-06 15:43:24'),
(134, 2, 'ca9bba277679f2aa31fb59b8fcd05f9fafa242c54baa00850c81d6496d1fc519', '2026-08-05 23:46:00', '2026-07-06 15:46:00'),
(135, 2, '2163a495231316228f2b8512812f2a340a690fff2b4c094325eb8ad7e1910b6d', '2026-08-06 00:06:09', '2026-07-06 16:06:09'),
(136, 2, '1820bad129efea26ca2c4b4b09e84bf4aa6cec1dcf36d27be8d951ef7c918bea', '2026-08-06 00:29:07', '2026-07-06 16:29:07'),
(137, 2, '8afa5bd6d622651d9762c59cf48c83842ac5f1db187cf205823dff06116468c5', '2026-08-06 00:31:19', '2026-07-06 16:31:19'),
(138, 2, '2c650449c6850262c731bb3c3965117f25a6acbfc3851176c7b222e906ea8d70', '2026-08-06 00:41:26', '2026-07-06 16:41:26'),
(139, 2, '7b7a98f2423e00745d9ed9edbc5aa180ee848ba706ca6cc69250fc8a2e4b5aa7', '2026-08-06 00:44:29', '2026-07-06 16:44:29'),
(142, 2, 'ed473d831b3c48bf37a31dfc3bbb0bbae8eeea0522eb2bdeff52efbbf5e0dff6', '2026-08-06 01:26:39', '2026-07-06 17:26:39'),
(143, 2, 'e77a46f88dfa04fce53eb728fcfc8a78f1a62e21e52e720e285fc4b3978391f0', '2026-08-06 01:35:28', '2026-07-06 17:35:28'),
(144, 2, '689d5728dc75849e28e0425b14e049bc71307b5e25dd3f5b0799eb317121e705', '2026-08-06 14:26:00', '2026-07-07 06:26:00'),
(145, 2, '39d4985cf0651750c3ba43904ff46e9188cf35f1599196ef4789ffbffce41ae3', '2026-08-06 15:21:05', '2026-07-07 07:21:05'),
(146, 2, 'a6efda3d840bbd2790a0f42cb8ab8d2ba7153214a720ad3aee2a15fbee898feb', '2026-08-08 02:40:42', '2026-07-08 18:40:42'),
(147, 2, '0f1b08bdc547c4c1cffd14f00e83299171cb24fbade17e9e454c0ab303a97c3f', '2026-08-08 03:14:31', '2026-07-08 19:14:31'),
(148, 2, '8361d916b0c091b6c3d03c30afe77facd9c722f173429259ee8bfeda7e0f9958', '2026-08-08 03:17:27', '2026-07-08 19:17:27'),
(149, 2, '6eb13bd8eeab8325ff3d54d9e1cf052289d1f627d8599d65cc4a943e50f776d3', '2026-10-02 16:14:47', '2026-09-02 08:14:47'),
(150, 2, '4504b93d32a3f302331c0c5163317d8f57e78f622e25a6e1abc53e113f8cc636', '2026-10-02 17:29:09', '2026-09-02 09:29:09'),
(155, 2, '94fadb732f96ba7d3d00b9a9915691b2c4128830a099e5f0f0475c458190fd63', '2026-10-17 00:56:13', '2026-09-16 16:56:13'),
(156, 2, 'd365dd5e5e250ea8d973c988fe90a44b13862f3ae78c2fcb9e0246461c743d78', '2026-10-17 01:10:07', '2026-09-16 17:10:07'),
(157, 2, 'f03ba8806b582d605758488e14a840df3d5747d93c8690f100ed65d1537467da', '2026-10-17 02:02:45', '2026-09-16 18:02:45'),
(158, 2, 'b1b4ba0e5c83b15fd8b45a24c08b7b9a60c4451f66acfd36a98af0d868ae9520', '2026-10-17 02:11:29', '2026-09-16 18:11:29'),
(159, 2, '6acbcbd8915caee69c6348b0c105f60a77758429b61d9479d0b0ec6354e8b201', '2026-10-17 02:49:37', '2026-09-16 18:49:37'),
(160, 2, '6c755c4a92ec4e4415ff9b809f9f05deed6734e229d6790a0bd78ec2f290a03e', '2026-10-17 02:57:54', '2026-09-16 18:57:54'),
(177, 16, '3640b55f57ce1015fe8e6d40a1b2eb9683979b797bc638df3c427703de8c564a', '2026-10-17 04:25:54', '2026-09-16 20:25:54'),
(178, 17, '2b4a53445b773ba1a4f83cf2e3898be8f121f0cd90b433299f25bf734ee5114c', '2026-10-17 04:28:29', '2026-09-16 20:28:29'),
(181, 17, '8db7a33cadb1302fcde11b6e2273f60c99f5a588d92310b200f702842e864734', '2026-10-17 04:34:33', '2026-09-16 20:34:33'),
(182, 17, 'bf9073d43d2f1c9f65b6c876511db6e6d9d48e97f3c3a7bb69e55b6444b590ae', '2026-10-17 04:37:17', '2026-09-16 20:37:17'),
(187, 17, 'a8b46bbc27163e6aadcc68e6b7342957cf3f20a717476be17ae814f14d8984fa', '2026-10-17 04:56:59', '2026-09-16 20:56:59'),
(188, 15, '7ebcc46b989da407f52c4ce3d383eda60110bdfab7456836348d6a92ab7fb23c', '2026-10-17 05:09:44', '2026-09-16 21:09:44'),
(189, 2, '79191a6d088de1cfc9171502277a9785d60945832a37d5d8710d64c093963af9', '2026-10-18 13:52:06', '2026-09-18 05:52:06'),
(190, 2, '94c396b06e3c051be3d09a822f298509452b552fbba424ea5f66a2e92ec11f0f', '2026-10-21 08:23:52', '2026-09-21 00:23:52'),
(191, 2, '7cdc31acdf70464773bb3bf61d8096e3732bff90c602fa0e9291da1689b5c611', '2026-10-21 08:32:22', '2026-09-21 00:32:22'),
(194, 2, '48ee6c140b739e0dab27456dce33b46567c53241ddf34adba1a417a8e2d83eac', '2026-10-27 22:26:58', '2026-09-27 14:26:58'),
(195, 2, 'f89067c15d86c12e8cc107b94377f07c1122048601f6397c7319c5efb02aec0e', '2026-10-27 23:05:55', '2026-09-27 15:05:55'),
(196, 2, '715bb7a168b597c0c58b5dc8b87084c0a87244bd0644fcbc0f452e516d4bf9af', '2026-10-27 23:29:53', '2026-09-27 15:29:53'),
(197, 2, '734ce230b4102cba9569fccd91b557ca45eefd8cad7271e1a27cfbfb2a63ad1c', '2026-10-27 23:37:30', '2026-09-27 15:37:30'),
(198, 2, '4be7be850b9c4a8f7520433104cc5ef808de247bdd00fc1217df5a3a388afeab', '2026-10-27 23:54:13', '2026-09-27 15:54:13'),
(199, 2, '265e1ef5e752d9ee62614259565ae2003e34e6ecd853cdc6a9005251de97c804', '2026-10-28 13:23:41', '2026-09-28 05:23:41'),
(208, 2, 'f7c2b94364552e67808a285ccd1ed5084df3acee10bde7f1bba429d59a468cdf', '2026-10-29 12:21:02', '2026-09-29 04:21:02'),
(209, 2, '5aebef02e57c64ef65b1eadd0783a15203c53efbfa3c9e81b74cf95f7fa87acc', '2026-10-29 12:38:19', '2026-09-29 04:38:19'),
(210, 2, 'e5e2d0f00e9b65afcfc6fcef927778023007db6c6324f4677427c8aac1adea91', '2026-10-29 12:52:19', '2026-09-29 04:52:19'),
(211, 2, 'fc2b52016d71d32769290bc14a792be9eaf5ebdfdad006cf69d41dc4af79bc76', '2026-10-29 13:06:52', '2026-09-29 05:06:52'),
(212, 2, '9f2cc12fd409afee9c3b082c4df9e2b88eb9b45195eed275ee910cc5aebae5c9', '2026-10-29 15:09:56', '2026-09-29 07:09:56'),
(213, 2, '3dfad6f98af7a11e1a40d95713a901704bd89062840581fc773c269e35a7badb', '2026-10-29 15:36:42', '2026-09-29 07:36:42'),
(214, 2, '2698745954a0727050ff2e3325d2ee30178a7e995d027871dea58f4085e5ac73', '2026-10-29 15:40:53', '2026-09-29 07:40:53'),
(215, 2, '910f788cab8b4bf1bfe322ed2be7acde4939939834e96e91fa91854f2171a381', '2026-10-29 15:43:10', '2026-09-29 07:43:10'),
(216, 2, 'c6844d6663345fb8c273d3f984723557a28fc3d25146806f6b86bf61a7a33147', '2026-10-29 15:53:04', '2026-09-29 07:53:04'),
(217, 2, '0b2b2cea0a599341ed29be39bd967762a1ac0f1beddb1ed187ea47a4948ae521', '2026-10-29 16:01:46', '2026-09-29 08:01:46'),
(218, 2, 'b7660547817d664a3c8a12042c554d01827ed829cf7518dbb77785200c4a989d', '2026-10-29 16:09:04', '2026-09-29 08:09:04'),
(219, 2, '47ee2884cef2082b47648ae103821bf2f952bcca5720e625973b474952c4afbc', '2026-10-29 16:19:00', '2026-09-29 08:19:00'),
(220, 2, '96c654e0011315b26a825a5b93a39d692e248ec36e0b089e5d9a63a89f3247de', '2026-10-29 16:34:15', '2026-09-29 08:34:15'),
(222, 2, 'f11f8088571d6f76f66c7db7da3a4715dfd3d991578ba2d2943a1b36ba22becf', '2026-10-29 16:55:07', '2026-09-29 08:55:07'),
(241, 1, 'd9e2f821931adea0b06649a38e4f900a84c2c8d2f3f132259377e267f4b9242d', '2026-11-30 21:25:29', '2026-10-31 13:25:29'),
(243, 1, '1df1107a9852ecbe8ddab55d160e0ca93f6cec2aa6e10fe92aa6755fe05cc841', '2026-11-30 21:48:27', '2026-10-31 13:48:27'),
(245, 1, '7dca992365ed9f69a965c23cb1aca30e22511dab721119110685013db208d4ae', '2026-11-30 22:06:20', '2026-10-31 14:06:20'),
(247, 1, '454854e196d623f5137c60c3f93bf309cf8c0da37a2da5dd6f2afe7917a63167', '2026-12-01 10:53:31', '2026-11-01 02:53:31'),
(252, 1, '00c7b46af66199db89b0d73c96f5da258722f4bf8471f8690dab744ab02b8b2f', '2026-12-01 12:15:59', '2026-11-01 04:15:59'),
(254, 1, '2228525d5774b87b49d2647e154fd61456b4d68dbbeb1c45122253af538cf1dd', '2026-12-01 12:16:22', '2026-11-01 04:16:22'),
(259, 1, 'be3ffafca214e0992f4d06a6f0878393543018e110094bf42e49de3f6f5dc463', '2026-12-01 14:59:56', '2026-11-01 06:59:56'),
(261, 1, '14f751b2355521a2f5227e39f0dea5f298129518cbd13fb13beabb5560088938', '2026-12-01 15:22:11', '2026-11-01 07:22:11'),
(263, 2, '513d757afe255bc02c2d16a9e1c09e95470c2247487f10f136c39eb3f8394794', '2026-12-01 16:30:34', '2026-11-01 08:30:34'),
(264, 2, 'a05d08a911945dfb668b094dcfd6fdbb25360db9c955de8d06cc9b2244cbc36b', '2026-12-01 16:31:12', '2026-11-01 08:31:12'),
(265, 2, 'f9bb3eb59a61c201a1413f68c3cbe6f389d74db1064aed2db2f9f9ff09a901b6', '2026-12-01 16:32:27', '2026-11-01 08:32:27'),
(277, 2467, '810998f0b165eae186543f0440e70be497a1ca3c1cb4a8141a2f84b64b67d955', '2026-12-06 20:51:30', '2026-11-06 12:51:30'),
(278, 2467, 'bcca45b43870fa62a0df0d0bd41cce0431a780c1dfd087b307226ef02c706f2b', '2026-12-06 20:51:51', '2026-11-06 12:51:51');

-- --------------------------------------------------------

--
-- Table structure for table `app_feedback`
--

CREATE TABLE `app_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `rating` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `category` varchar(40) NOT NULL DEFAULT 'general',
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_feedback`
--

INSERT INTO `app_feedback` (`id`, `user_id`, `name`, `email`, `rating`, `category`, `message`, `status`, `created_at`) VALUES
(2, 2, 'Jalel Mauc', 'jalelmauc@gmail.com', 5, 'feature', 'It have been some bugs for some features', 'reviewed', '2026-07-06 15:25:18'),
(4, 2, 'Jalel Mauc', 'jalelmauc@gmail.com', 0, 'praise', 'xksksxiisisisi', 'archived', '2026-07-07 06:27:29');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `admin_id`, `action`, `entity_type`, `entity_id`, `meta`, `created_at`) VALUES
(1, 1, 'user.toggle_active', 'user', 4, '{\"active\":false}', '2026-06-16 05:56:06'),
(2, 1, 'user.toggle_active', 'user', 2, '{\"active\":false}', '2026-06-16 07:14:55'),
(3, 1, 'user.toggle_active', 'user', 2, '{\"active\":true}', '2026-06-16 07:15:13'),
(4, 1, 'user.toggle_active', 'user', 2, '{\"active\":false}', '2026-06-16 07:15:39'),
(5, 1, 'user.toggle_active', 'user', 2, '{\"active\":true}', '2026-06-16 07:18:12'),
(6, 1, 'user.toggle_active', 'user', 5, '{\"active\":false}', '2026-06-16 07:19:13'),
(7, 1, 'user.toggle_active', 'user', 5, '{\"active\":true}', '2026-06-16 07:19:57'),
(8, 1, 'user.toggle_active', 'user', 5, '{\"active\":false}', '2026-06-16 07:21:22'),
(9, 1, 'user.toggle_active', 'user', 5, '{\"active\":true}', '2026-06-16 07:21:25'),
(10, 1, 'user.toggle_active', 'user', 7, '{\"active\":false}', '2026-06-30 06:17:49'),
(11, 1, 'user.toggle_active', 'user', 7, '{\"active\":true}', '2026-06-30 06:18:09'),
(12, 1, 'feedback.status', 'app_feedback', 2, '{\"status\":\"reviewed\"}', '2026-07-06 15:26:15'),
(13, 1, 'feedback.status', 'app_feedback', 2, '{\"status\":\"reviewed\"}', '2026-07-06 15:26:23'),
(14, 1, 'feedback.status', 'app_feedback', 4, '{\"status\":\"archived\"}', '2026-07-07 06:32:14'),
(15, 1, 'feedback.status', 'app_feedback', 4, '{\"status\":\"archived\"}', '2026-07-07 06:32:20'),
(16, 1, 'feedback.status', 'app_feedback', 4, '{\"status\":\"reviewed\"}', '2026-07-07 06:32:21'),
(17, 1, 'feedback.status', 'app_feedback', 4, '{\"status\":\"archived\"}', '2026-07-07 06:32:34'),
(18, 1, 'feedback.status', 'app_feedback', 4, '{\"status\":\"reviewed\"}', '2026-07-07 06:32:46'),
(19, 1, 'feedback.status', 'app_feedback', 4, '{\"status\":\"archived\"}', '2026-07-07 06:32:55'),
(20, 1, 'user.toggle_active', 'user', 6, '{\"active\":false}', '2026-07-07 06:40:20'),
(21, 1, 'user.toggle_active', 'user', 5, '{\"active\":false}', '2026-07-07 06:40:24'),
(22, 1, 'user.toggle_active', 'user', 7, '{\"active\":false}', '2026-07-07 06:40:26'),
(23, 1, 'rental.status', 'rental_request', 2, '{\"status\":\"cancelled\"}', '2026-07-07 06:45:33'),
(24, 1, 'rental.status', 'rental_request', 1, '{\"status\":\"approved\"}', '2026-07-07 06:45:38'),
(25, 1, 'dispute.refund', 'dispute', 1, NULL, '2026-09-16 18:01:10'),
(26, 1, 'listing.status', 'listing', 9, '{\"status\":\"approved\"}', '2026-09-21 00:42:40'),
(27, 1, 'user.toggle_active', 'user', 5, '{\"active\":true}', '2026-09-28 05:27:30'),
(28, 1, 'user.toggle_active', 'user', 10, '{\"active\":false}', '2026-09-28 05:32:40'),
(29, 1, 'user.toggle_active', 'user', 10, '{\"active\":true}', '2026-09-28 05:33:07'),
(30, 1, 'user.toggle_active', 'user', 5, '{\"active\":false}', '2026-09-28 05:33:12'),
(31, 1, 'dispute.warning', 'dispute', 2, NULL, '2026-09-28 06:05:54'),
(32, 1, 'dispute.refund', 'dispute', 3, NULL, '2026-09-28 06:08:30'),
(33, 1, 'dispute.warning', 'dispute', 4, NULL, '2026-09-29 05:28:14'),
(34, 1, 'guide.clear_warning', 'user', 3, NULL, '2026-09-29 07:04:32'),
(35, 1, 'guide.clear_warning', 'user', 18, NULL, '2026-09-29 07:04:35'),
(36, 1, 'user.toggle_active', 'user', 11, '{\"active\":false}', '2026-09-29 09:14:15'),
(37, 1, 'user.toggle_active', 'user', 20, '{\"active\":false}', '2026-09-29 09:14:18'),
(38, 1, 'user.toggle_active', 'user', 19, '{\"active\":false}', '2026-09-29 09:14:21'),
(39, 1, 'user.toggle_active', 'user', 10, '{\"active\":false}', '2026-09-29 09:14:29'),
(40, 1, 'user.toggle_active', 'user', 16, '{\"active\":false}', '2026-09-29 09:14:43'),
(41, 1, 'user.toggle_active', 'user', 18, '{\"active\":false}', '2026-09-29 09:14:45'),
(42, 1, 'user.toggle_active', 'user', 17, '{\"active\":false}', '2026-09-29 09:14:47'),
(43, 1, 'user.toggle_active', 'user', 15, '{\"active\":false}', '2026-09-29 09:14:49'),
(44, 1, 'user.toggle_active', 'user', 14, '{\"active\":false}', '2026-09-29 09:14:51'),
(45, 1, 'user.toggle_active', 'user', 13, '{\"active\":false}', '2026-09-29 09:14:53'),
(46, 1, 'user.toggle_active', 'user', 12, '{\"active\":false}', '2026-09-29 09:14:54'),
(47, 1, 'user.toggle_active', 'user', 12, '{\"active\":true}', '2026-09-29 09:14:59'),
(48, 1, 'user.toggle_active', 'user', 18, '{\"active\":true}', '2026-09-29 09:15:02'),
(49, 1, 'guide.approve', 'user', 12, NULL, '2026-09-29 11:36:42'),
(50, 1, 'guide.approve', 'user', 23, NULL, '2026-10-09 01:02:10'),
(51, 1, 'user.suspend', 'user', 23, '{\"name\":\"John Doe\",\"email\":\"johndoe@sample.com\",\"reason\":\"have a violation in our privacy\"}', '2026-10-09 01:04:30'),
(52, 1, 'user.update', 'user', 23, '{\"name\":\"John Doe\",\"email\":\"johndoe@sample.com\",\"role\":\"guide\",\"is_active\":1,\"guide_status\":\"approved\"}', '2026-10-09 01:32:33'),
(53, 1, 'guide.approve', 'user', 25, NULL, '2026-10-09 01:35:54'),
(54, 1, 'user.suspend', 'user', 25, '{\"name\":\"Sample Sample\",\"email\":\"samplesample@sample.com\",\"reason\":\"waewae\"}', '2026-10-09 01:36:21'),
(55, 1, 'user.update', 'user', 25, '{\"name\":\"Sample Sample\",\"email\":\"samplesample@sample.com\",\"role\":\"guide\",\"is_active\":1,\"guide_status\":\"approved\"}', '2026-10-09 01:37:17'),
(56, 1, 'user.update', 'user', 10, '{\"name\":\"Christian Yongzon\",\"email\":\"christianyongzon143@gmail.com\",\"role\":\"tourist\",\"is_active\":1,\"guide_status\":\"none\"}', '2026-10-09 03:12:49'),
(57, 1, 'user.update', 'user', 3, '{\"name\":\"Marc Repuesto\",\"email\":\"guide.moalboal@guidemate.local\",\"role\":\"guide\",\"is_active\":1,\"guide_status\":\"approved\"}', '2026-10-14 13:33:05'),
(58, 1, 'user.update', 'user', 7, '{\"name\":\"Junril Ubas\",\"email\":\"marwadmm648@gmail.com\",\"role\":\"tourist\",\"is_active\":1,\"guide_status\":\"none\"}', '2026-10-14 14:26:45'),
(59, 1, 'user.update', 'user', 2, '{\"name\":\"Jalel Mauc\",\"email\":\"jalelmauc@gmail.com\",\"role\":\"tourist\",\"is_active\":0,\"guide_status\":\"none\"}', '2026-10-14 14:42:01'),
(60, 1, 'user.update', 'user', 7, '{\"name\":\"Junril Ubas\",\"email\":\"marwadmm648@gmail.com\",\"role\":\"tourist\",\"is_active\":0,\"guide_status\":\"none\"}', '2026-10-14 14:42:31'),
(61, 1, 'user.delete', 'user', 7, '{\"name\":\"Junril Ubas\",\"email\":\"marwadmm648@gmail.com\",\"role\":\"tourist\"}', '2026-10-14 14:43:18'),
(62, 1, 'user.update', 'user', 18, '{\"name\":\"Hotel Admin\",\"email\":\"marwadmm648@gmail.com\",\"role\":\"hotel_admin\",\"is_active\":1,\"guide_status\":\"approved\"}', '2026-10-14 14:43:29'),
(63, 1, 'user.delete', 'user', 19, '{\"name\":\"Benjohn12\",\"email\":\"benjohn060810@gmail.com\",\"role\":\"tourist\"}', '2026-10-14 14:45:18'),
(64, 1, 'user.delete', 'user', 26, '{\"name\":\"Paran111\",\"email\":\"benjohn060810+qwe@gmail.com\",\"role\":\"tourist\"}', '2026-10-14 14:45:20'),
(65, 1, 'user.delete', 'user', 25, '{\"name\":\"Sample Sample\",\"email\":\"samplesample@sample.com\",\"role\":\"guide\"}', '2026-10-14 14:45:24'),
(66, 1, 'user.delete', 'user', 10, '{\"name\":\"Christian Yongzon\",\"email\":\"christianyongzon143@gmail.com\",\"role\":\"tourist\"}', '2026-10-14 14:45:44'),
(67, 1, 'user.delete', 'user', 5, '{\"name\":\"Christian Yongzon\",\"email\":\"chrisyongzon112@gmail.com\",\"role\":\"tourist\"}', '2026-10-14 14:45:58'),
(68, 1, 'user.delete', 'user', 4, '{\"name\":\"Dranreb Misa\",\"email\":\"misadranreb4@gmail.com\",\"role\":\"tourist\"}', '2026-10-14 14:46:11'),
(69, 1, 'user.update', 'user', 18, '{\"name\":\"Hotel Admin\",\"email\":\"chrisyongzon112@gmail.com\",\"role\":\"hotel_admin\",\"is_active\":1,\"guide_status\":\"approved\"}', '2026-10-14 14:46:25'),
(70, 1, 'user.update', 'user', 18, '{\"name\":\"Hotel Admin\",\"email\":\"christianyongzon143@gmail.com\",\"role\":\"hotel_admin\",\"is_active\":1,\"guide_status\":\"approved\"}', '2026-10-14 15:20:20'),
(71, 1, 'user.update', 'user', 18, '{\"name\":\"Hotel Admin\",\"email\":\"chrisyongzon112@gmail.com\",\"role\":\"hotel_admin\",\"is_active\":1,\"guide_status\":\"approved\"}', '2026-10-14 16:36:08'),
(72, 1, 'guide.approve', 'user', 58, NULL, '2026-10-18 18:28:06'),
(73, 1, 'guide.approve', 'user', 58, NULL, '2026-10-18 18:28:11'),
(74, 1, 'vehicle.reject', 'vehicle', 13, '{\"vehicle_name\":\"test\",\"provider_id\":58,\"reason\":\"test test test test test test test testtest test test test\"}', '2026-10-18 18:32:49'),
(75, 1, 'user.delete', 'user', 58, '{\"name\":\"John Does\",\"email\":\"samplejohn@sample.com\",\"role\":\"rental_admin\"}', '2026-10-19 17:58:17'),
(76, 1, 'guide.approve', 'user', 167, NULL, '2026-10-20 04:42:52'),
(77, 1, 'vehicle.approve', 'vehicle', 72, '{\"vehicle_name\":\"2022 Honda Click 125i V2 Automatic\",\"provider_id\":167}', '2026-10-20 04:49:58'),
(78, 1, 'user.delete', 'user', 505, '{\"name\":\"Test Tourist 5f5ef259\",\"email\":\"tourist_5f5ef259@example.com\",\"role\":\"tourist\"}', '2026-10-22 17:42:14'),
(79, 1, 'user.delete', 'user', 506, '{\"name\":\"Test Guide NoDocs 5f5ef259\",\"email\":\"guide_nodocs_5f5ef259@example.com\",\"role\":\"guide\"}', '2026-10-22 17:42:20'),
(80, 1, 'user.delete', 'user', 373, '{\"name\":\"Pending Guide\",\"email\":\"guide_pending_bd5ca1e5@example.com\",\"role\":\"guide\"}', '2026-10-22 17:42:29'),
(81, 1, 'user.delete', 'user', 372, '{\"name\":\"Pending Guide\",\"email\":\"guide_pending_f305b9c1@example.com\",\"role\":\"guide\"}', '2026-10-22 17:42:33'),
(82, 1, 'user.delete', 'user', 370, '{\"name\":\"Sherwin-edited\",\"email\":\"christianyongzon143+sherwin@gmail.com\",\"role\":\"guide\"}', '2026-10-22 17:42:50'),
(83, 1, 'user.delete', 'user', 49, '{\"name\":\"John Each\",\"email\":\"johneach@sample.com\",\"role\":\"rental_provider\"}', '2026-10-22 17:43:46'),
(84, 1, 'user.delete', 'user', 74, '{\"name\":\"John Does\",\"email\":\"sampledoes@sample.com\",\"role\":\"rental_provider\"}', '2026-10-22 17:43:51'),
(85, 1, 'guide.approve', 'user', 371, NULL, '2026-10-23 00:18:06'),
(86, 1, 'user.delete', 'user', 543, '{\"name\":\"Test Guide One\",\"email\":\"guide_doc_test_b3f0da1b@example.com\",\"role\":\"guide\"}', '2026-10-23 00:20:42'),
(87, 1, 'user.delete', 'user', 544, '{\"name\":\"Test Guide Two\",\"email\":\"guide_doc_two_b3f0da1b@example.com\",\"role\":\"guide\"}', '2026-10-23 00:20:45'),
(88, 1, 'user.delete', 'user', 619, '{\"name\":\"Test Guide One\",\"email\":\"guide_doc_test_3cc48ea1@example.com\",\"role\":\"guide\"}', '2026-10-23 01:17:01'),
(89, 1, 'user.delete', 'user', 620, '{\"name\":\"Test Guide Two\",\"email\":\"guide_doc_two_3cc48ea1@example.com\",\"role\":\"guide\"}', '2026-10-23 01:17:04'),
(90, 1, 'user.delete', 'user', 601, '{\"name\":\"Test Guide One\",\"email\":\"guide_doc_test_bc0bfe7b@example.com\",\"role\":\"guide\"}', '2026-10-23 01:17:08'),
(91, 1, 'user.delete', 'user', 602, '{\"name\":\"Test Guide Two\",\"email\":\"guide_doc_two_bc0bfe7b@example.com\",\"role\":\"guide\"}', '2026-10-23 01:17:11'),
(92, 1, 'vehicle.approve', 'vehicle', 72, '{\"vehicle_name\":\"2022 Honda Click 125i V2 Automatic\",\"provider_id\":167}', '2026-10-23 02:16:54'),
(93, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 05:42:33'),
(94, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 05:42:44'),
(95, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 05:49:52'),
(96, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 05:51:54'),
(97, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 05:52:07'),
(98, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 05:55:49'),
(99, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 05:59:50'),
(100, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 05:59:54'),
(101, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 06:21:42'),
(102, 1, 'guide.reject', 'user', 1034, '{\"note\":\"\"}', '2026-10-25 17:10:48'),
(103, 1, 'user.delete', 'user', 1366, '{\"name\":\"Guide Tester\",\"email\":\"guide_bk_7339dabd@example.com\",\"role\":\"guide\"}', '2026-10-25 20:19:28'),
(104, 1, 'user.delete', 'user', 1367, '{\"name\":\"Tourist One\",\"email\":\"tourist_bk_58b1ebec@example.com\",\"role\":\"tourist\"}', '2026-10-25 20:19:33'),
(105, 1, 'user.delete', 'user', 1364, '{\"name\":\"Guide Tester\",\"email\":\"guide_bk_1a71f56d@example.com\",\"role\":\"guide\"}', '2026-10-25 20:19:37'),
(106, 1, 'user.delete', 'user', 1365, '{\"name\":\"Tourist One\",\"email\":\"tourist_bk_334ae09a@example.com\",\"role\":\"tourist\"}', '2026-10-25 20:19:41'),
(107, 1, 'user.delete', 'user', 1034, '{\"name\":\"Flash Test Partner\",\"email\":\"flash_956c533c@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:19:44'),
(108, 1, 'user.delete', 'user', 783, '{\"name\":\"Json Hotel Partner\",\"email\":\"json_hotel_f49ec473@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:19:47'),
(109, 1, 'user.delete', 'user', 784, '{\"name\":\"Partner A\",\"email\":\"h_a_5d8ad8bf@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:19:50'),
(110, 1, 'user.delete', 'user', 785, '{\"name\":\"Partner B\",\"email\":\"h_b_05bb2d3e@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:19:54'),
(111, 1, 'user.delete', 'user', 780, '{\"name\":\"Separate Hotel Partner\",\"email\":\"sep_hotel_397d2cc5@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:19:57'),
(112, 1, 'user.delete', 'user', 781, '{\"name\":\"Separate Guide Partner\",\"email\":\"sep_guide_985df1d5@example.com\",\"role\":\"guide\"}', '2026-10-25 20:20:01'),
(113, 1, 'user.delete', 'user', 782, '{\"name\":\"Edit Hotel Partner\",\"email\":\"edit_hotel_a2c5e3e6@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:20:03'),
(114, 1, 'user.delete', 'user', 778, '{\"name\":\"Redirect Hotel Partner\",\"email\":\"red_hotel_18206a63@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:20:06'),
(115, 1, 'user.delete', 'user', 779, '{\"name\":\"Owner Hotel Partner\",\"email\":\"owner_hotel_2ea1fa98@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:20:09'),
(116, 1, 'user.delete', 'user', 777, '{\"name\":\"Redirect Hotel Partner\",\"email\":\"red_hotel_6bb3123c@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:20:12'),
(117, 1, 'user.delete', 'user', 766, '{\"name\":\"Verified Hotel Partner\",\"email\":\"hotel_approved_04b22bfb@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:20:17'),
(118, 1, 'user.delete', 'user', 758, '{\"name\":\"Pending Guide\",\"email\":\"guide_pending_bc0c0905@example.com\",\"role\":\"guide\"}', '2026-10-25 20:20:21'),
(119, 1, 'user.delete', 'user', 759, '{\"name\":\"Unverified Store Guide\",\"email\":\"guide_store_a0f678bf@example.com\",\"role\":\"guide\"}', '2026-10-25 20:20:24'),
(120, 1, 'user.delete', 'user', 760, '{\"name\":\"Pending Provider\",\"email\":\"p_pending_c7de618e@example.com\",\"role\":\"rental_provider\"}', '2026-10-25 20:20:27'),
(121, 1, 'user.delete', 'user', 761, '{\"name\":\"Pending Store Provider\",\"email\":\"p_store_407e9893@example.com\",\"role\":\"rental_provider\"}', '2026-10-25 20:20:31'),
(122, 1, 'user.delete', 'user', 762, '{\"name\":\"Pending Hotel Partner\",\"email\":\"hotel_pending_c90b23df@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:20:36'),
(123, 1, 'user.delete', 'user', 763, '{\"name\":\"Status Test Guide\",\"email\":\"guide_stat_02550e0b@example.com\",\"role\":\"guide\"}', '2026-10-25 20:20:41'),
(124, 1, 'user.delete', 'user', 764, '{\"name\":\"Approved Guide\",\"email\":\"guide_appr_c5fd7869@example.com\",\"role\":\"guide\"}', '2026-10-25 20:20:44'),
(125, 1, 'user.delete', 'user', 765, '{\"name\":\"Approved Provider\",\"email\":\"p_appr_8f837558@example.com\",\"role\":\"rental_provider\"}', '2026-10-25 20:20:48'),
(126, 1, 'user.delete', 'user', 757, '{\"name\":\"Verified Hotel Partner\",\"email\":\"hotel_approved_2a767ae1@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:20:50'),
(127, 1, 'user.delete', 'user', 749, '{\"name\":\"Pending Guide\",\"email\":\"guide_pending_0094d75d@example.com\",\"role\":\"guide\"}', '2026-10-25 20:20:53'),
(128, 1, 'user.delete', 'user', 750, '{\"name\":\"Unverified Store Guide\",\"email\":\"guide_store_5821241d@example.com\",\"role\":\"guide\"}', '2026-10-25 20:20:55'),
(129, 1, 'user.delete', 'user', 751, '{\"name\":\"Pending Provider\",\"email\":\"p_pending_600d4208@example.com\",\"role\":\"rental_provider\"}', '2026-10-25 20:20:58'),
(130, 1, 'user.delete', 'user', 752, '{\"name\":\"Pending Store Provider\",\"email\":\"p_store_1994c1dd@example.com\",\"role\":\"rental_provider\"}', '2026-10-25 20:21:00'),
(131, 1, 'user.delete', 'user', 753, '{\"name\":\"Pending Hotel Partner\",\"email\":\"hotel_pending_eb41a374@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:21:03'),
(132, 1, 'user.delete', 'user', 754, '{\"name\":\"Status Test Guide\",\"email\":\"guide_stat_72758878@example.com\",\"role\":\"guide\"}', '2026-10-25 20:21:06'),
(133, 1, 'user.delete', 'user', 755, '{\"name\":\"Approved Guide\",\"email\":\"guide_appr_5ba6938c@example.com\",\"role\":\"guide\"}', '2026-10-25 20:21:09'),
(134, 1, 'user.delete', 'user', 756, '{\"name\":\"Approved Provider\",\"email\":\"p_appr_d9b46b2a@example.com\",\"role\":\"rental_provider\"}', '2026-10-25 20:21:12'),
(135, 1, 'user.delete', 'user', 740, '{\"name\":\"Pending Guide\",\"email\":\"guide_pending_2582b1c6@example.com\",\"role\":\"guide\"}', '2026-10-25 20:21:14'),
(136, 1, 'user.delete', 'user', 741, '{\"name\":\"Unverified Store Guide\",\"email\":\"guide_store_4ac09d95@example.com\",\"role\":\"guide\"}', '2026-10-25 20:21:17'),
(137, 1, 'user.delete', 'user', 742, '{\"name\":\"Pending Provider\",\"email\":\"p_pending_e977a141@example.com\",\"role\":\"rental_provider\"}', '2026-10-25 20:21:21'),
(138, 1, 'user.delete', 'user', 743, '{\"name\":\"Pending Store Provider\",\"email\":\"p_store_96e8fc14@example.com\",\"role\":\"rental_provider\"}', '2026-10-25 20:21:24'),
(139, 1, 'user.delete', 'user', 744, '{\"name\":\"Pending Hotel Partner\",\"email\":\"hotel_pending_fff674b8@example.com\",\"role\":\"hotel_admin\"}', '2026-10-25 20:21:26'),
(140, 1, 'vehicle.approve', 'vehicle', 72, '{\"vehicle_name\":\"2022 Honda Click 125i V2 Automatic\",\"provider_id\":167}', '2026-10-31 00:25:35'),
(141, 1, 'hotel.reject', 'listing', 581, '{\"hotel_title\":\"Resubmit Property Resort\",\"provider_id\":1811,\"reason\":\"test tes test test test\"}', '2026-10-31 00:37:35'),
(142, 1, 'hotel.approve', 'listing', 582, '{\"hotel_title\":\"hotel test\",\"provider_id\":18}', '2026-10-31 00:43:21'),
(143, 1, 'hotel.reject', 'listing', 590, '{\"hotel_title\":\"Resubmit Property Resort\",\"provider_id\":1821,\"reason\":\"\"}', '2026-10-31 00:59:28'),
(144, 1, 'hotel.delete', 'listing', 589, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1819,\"deleted_by\":1}', '2026-10-31 00:59:43'),
(145, 1, 'hotel.delete', 'listing', 590, '{\"hotel_title\":\"Resubmit Property Resort\",\"provider_id\":1821,\"deleted_by\":1}', '2026-10-31 00:59:56'),
(146, 1, 'hotel.delete', 'listing', 586, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1815,\"deleted_by\":1}', '2026-10-31 00:59:58'),
(147, 1, 'hotel.delete', 'listing', 580, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1809,\"deleted_by\":1}', '2026-10-31 01:00:00'),
(148, 1, 'hotel.delete', 'listing', 581, '{\"hotel_title\":\"Resubmit Property Resort\",\"provider_id\":1811,\"deleted_by\":1}', '2026-10-31 01:00:03'),
(149, 1, 'hotel.delete', 'listing', 577, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1805,\"deleted_by\":1}', '2026-10-31 01:00:05'),
(150, 1, 'hotel.delete', 'listing', 563, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1784,\"deleted_by\":1}', '2026-10-31 01:00:06'),
(151, 1, 'hotel.delete', 'listing', 560, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1780,\"deleted_by\":1}', '2026-10-31 01:00:09'),
(152, 1, 'hotel.delete', 'listing', 546, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1759,\"deleted_by\":1}', '2026-10-31 01:00:10'),
(153, 1, 'hotel.delete', 'listing', 543, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1755,\"deleted_by\":1}', '2026-10-31 01:00:12'),
(154, 1, 'hotel.delete', 'listing', 540, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1749,\"deleted_by\":1}', '2026-10-31 01:00:15'),
(155, 1, 'hotel.delete', 'listing', 537, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1745,\"deleted_by\":1}', '2026-10-31 01:00:16'),
(156, 1, 'hotel.delete', 'listing', 534, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1739,\"deleted_by\":1}', '2026-10-31 01:00:19'),
(157, 1, 'hotel.delete', 'listing', 531, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1735,\"deleted_by\":1}', '2026-10-31 01:00:21'),
(158, 1, 'hotel.delete', 'listing', 528, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1729,\"deleted_by\":1}', '2026-10-31 01:00:23'),
(159, 1, 'hotel.delete', 'listing', 525, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1725,\"deleted_by\":1}', '2026-10-31 01:00:24'),
(160, 1, 'hotel.delete', 'listing', 521, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1687,\"deleted_by\":1}', '2026-10-31 01:00:26'),
(161, 1, 'hotel.delete', 'listing', 518, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1683,\"deleted_by\":1}', '2026-10-31 01:00:27'),
(162, 1, 'hotel.delete', 'listing', 499, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1651,\"deleted_by\":1}', '2026-10-31 01:00:29'),
(163, 1, 'hotel.delete', 'listing', 496, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1647,\"deleted_by\":1}', '2026-10-31 01:00:31'),
(164, 1, 'hotel.delete', 'listing', 493, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1641,\"deleted_by\":1}', '2026-10-31 01:00:32'),
(165, 1, 'hotel.delete', 'listing', 490, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1637,\"deleted_by\":1}', '2026-10-31 01:00:34'),
(166, 1, 'hotel.delete', 'listing', 475, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1605,\"deleted_by\":1}', '2026-10-31 01:00:36'),
(167, 1, 'hotel.delete', 'listing', 472, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1601,\"deleted_by\":1}', '2026-10-31 01:00:37'),
(168, 1, 'hotel.delete', 'listing', 470, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1595,\"deleted_by\":1}', '2026-10-31 01:00:39'),
(169, 1, 'hotel.delete', 'listing', 467, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1591,\"deleted_by\":1}', '2026-10-31 01:00:41'),
(170, 1, 'user.delete', 'user', 1819, '{\"name\":\"Rejectee Partner\",\"email\":\"rej_part_8a32db5a@example.com\",\"role\":\"hotel_provider\"}', '2026-10-31 01:01:01'),
(171, 1, 'user.delete', 'user', 1821, '{\"name\":\"Resubmit Partner\",\"email\":\"resub_part_b2b52412@example.com\",\"role\":\"hotel_provider\"}', '2026-10-31 01:01:18'),
(172, 1, 'hotel.approve', 'listing', 595, '{\"hotel_title\":\"Hotel-Test\",\"provider_id\":18}', '2026-10-31 10:16:50'),
(173, 1, 'listing.status', 'listing', 596, '{\"status\":\"rejected\"}', '2026-10-31 11:03:17'),
(174, 1, 'dispute.warning', 'dispute', 8, NULL, '2026-10-31 11:46:45'),
(175, 1, 'hotel.reject', 'listing', 646, '{\"hotel_title\":\"Resubmit Property Resort\",\"provider_id\":1925,\"reason\":\"\"}', '2026-11-01 02:00:01'),
(176, 1, 'hotel.delete', 'listing', 645, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1923,\"deleted_by\":1}', '2026-11-01 02:00:16'),
(177, 1, 'hotel.delete', 'listing', 646, '{\"hotel_title\":\"Resubmit Property Resort\",\"provider_id\":1925,\"deleted_by\":1}', '2026-11-01 02:00:18'),
(178, 1, 'hotel.delete', 'listing', 642, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1919,\"deleted_by\":1}', '2026-11-01 02:00:20'),
(179, 1, 'hotel.delete', 'listing', 633, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1906,\"deleted_by\":1}', '2026-11-01 02:00:24'),
(180, 1, 'hotel.delete', 'listing', 636, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1910,\"deleted_by\":1}', '2026-11-01 02:00:26'),
(181, 1, 'hotel.delete', 'listing', 630, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1894,\"deleted_by\":1}', '2026-11-01 02:00:27'),
(182, 1, 'hotel.delete', 'listing', 622, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1878,\"deleted_by\":1}', '2026-11-01 02:00:31'),
(183, 1, 'hotel.delete', 'listing', 627, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1890,\"deleted_by\":1}', '2026-11-01 02:00:34'),
(184, 1, 'hotel.delete', 'listing', 619, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1874,\"deleted_by\":1}', '2026-11-01 02:00:36'),
(185, 1, 'hotel.delete', 'listing', 614, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1861,\"deleted_by\":1}', '2026-11-01 02:00:39'),
(186, 1, 'hotel.delete', 'listing', 596, '{\"hotel_title\":\"Seaside Villa & Suites\",\"provider_id\":1824,\"deleted_by\":1}', '2026-11-01 02:00:49'),
(187, 1, 'hotel.delete', 'listing', 600, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1829,\"deleted_by\":1}', '2026-11-01 02:00:52'),
(188, 1, 'hotel.delete', 'listing', 608, '{\"hotel_title\":\"Needs Improvement Lodge\",\"provider_id\":1851,\"deleted_by\":1}', '2026-11-01 02:00:53'),
(189, 1, 'hotel.delete', 'listing', 611, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1857,\"deleted_by\":1}', '2026-11-01 02:00:55'),
(190, 1, 'hotel.delete', 'listing', 605, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1847,\"deleted_by\":1}', '2026-11-01 02:00:57'),
(191, 1, 'hotel.delete', 'listing', 597, '{\"hotel_title\":\"Gated Test Inn\",\"provider_id\":1825,\"deleted_by\":1}', '2026-11-01 02:00:59'),
(192, 1, 'guide.clear_warning', 'user', 18, NULL, '2026-11-01 02:13:28'),
(193, 1, 'dispute.warning', 'dispute', 18, NULL, '2026-11-01 02:22:26'),
(194, 1, 'guide.clear_warning', 'user', 18, NULL, '2026-11-01 03:53:57'),
(195, 1, 'hotel.approve', 'listing', 781, '{\"hotel_title\":\"test hotel name\",\"provider_id\":18}', '2026-11-01 10:51:18'),
(196, 1, 'hotel.approve', 'listing', 895, '{\"hotel_title\":\"hotel test\",\"provider_id\":18}', '2026-11-01 11:39:20'),
(197, 1, 'hotel.approve', 'listing', 968, '{\"hotel_title\":\"Bluewater Maribago Beach Resort\",\"provider_id\":18}', '2026-11-02 09:22:52'),
(198, 1, 'guide.reject', 'user', 2270, '{\"note\":\"\"}', '2026-11-02 14:24:22'),
(199, 1, 'guide.reject', 'user', 1923, '{\"note\":\"\"}', '2026-11-02 14:24:24'),
(200, 1, 'guide.reject', 'user', 1920, '{\"note\":\"\"}', '2026-11-02 14:24:28'),
(201, 1, 'guide.reject', 'user', 1916, '{\"note\":\"\"}', '2026-11-02 14:24:28'),
(202, 1, 'guide.reject', 'user', 1917, '{\"note\":\"\"}', '2026-11-02 14:24:29'),
(203, 1, 'guide.reject', 'user', 1910, '{\"note\":\"\"}', '2026-11-02 14:24:30'),
(204, 1, 'guide.reject', 'user', 1907, '{\"note\":\"\"}', '2026-11-02 14:24:31'),
(205, 1, 'listing.status', 'listing', 1090, '{\"status\":\"approved\"}', '2026-11-02 14:25:26'),
(206, 1, 'guide.reject', 'user', 2404, '{\"note\":\"\"}', '2026-11-03 06:46:41'),
(207, 1, 'guide_listing.approve', 'listing', 1161, '{\"title\":\"test title\",\"guide_id\":3}', '2026-11-03 07:23:04'),
(208, 1, 'guide_listing.approve', 'listing', 1190, '{\"title\":\"test title\",\"guide_id\":3}', '2026-11-04 03:35:13'),
(209, 1, 'guide.revoke', 'user', 1880, NULL, '2026-11-04 08:31:22'),
(210, 1, 'user.update', 'user', 1880, '{\"name\":\"Resubmit Partner\",\"email\":\"resub_part_7f82c1ff@example.com\",\"role\":\"hotel_provider\",\"is_active\":0,\"guide_status\":\"rejected\"}', '2026-11-04 08:31:29'),
(211, 1, 'user.suspend', 'user', 1875, '{\"name\":\"Review Test Partner\",\"email\":\"rev_test_62933811@example.com\",\"reason\":\"kayw ay kalro\"}', '2026-11-04 08:32:14'),
(212, 1, 'guide_listing.approve', 'listing', 1191, '{\"title\":\"test package\",\"guide_id\":3}', '2026-11-04 17:16:38'),
(213, 1, 'guide.warning', 'user', 2402, NULL, '2026-11-05 15:56:06'),
(214, 1, 'guide.clear_warning', 'user', 2402, NULL, '2026-11-05 15:56:12'),
(215, 1, 'user.delete', 'user', 2406, '{\"name\":\"Resubmit Partner\",\"email\":\"resub_part_146f3cec@example.com\",\"role\":\"hotel_provider\"}', '2026-11-05 15:56:25'),
(216, 1, 'guide_listing.approve', 'listing', 1192, '{\"title\":\"Test Package Title\",\"guide_id\":3}', '2026-11-06 04:49:26'),
(217, 1, 'guide.reject', 'user', 2401, '{\"note\":\"\"}', '2026-11-06 10:46:09'),
(218, 1, 'guide.reject', 'user', 2397, '{\"note\":\"\"}', '2026-11-06 10:46:11'),
(219, 1, 'guide.reject', 'user', 2398, '{\"note\":\"\"}', '2026-11-06 10:46:12'),
(220, 1, 'guide.reject', 'user', 2390, '{\"note\":\"\"}', '2026-11-06 10:46:13'),
(221, 1, 'guide.reject', 'user', 2387, '{\"note\":\"\"}', '2026-11-06 10:46:14'),
(222, 1, 'guide.reject', 'user', 2383, '{\"note\":\"\"}', '2026-11-06 10:46:14'),
(223, 1, 'guide.reject', 'user', 2384, '{\"note\":\"\"}', '2026-11-06 10:46:15'),
(224, 1, 'guide.reject', 'user', 1903, '{\"note\":\"\"}', '2026-11-06 10:46:15'),
(225, 1, 'guide.reject', 'user', 1904, '{\"note\":\"\"}', '2026-11-06 10:46:16'),
(226, 1, 'guide.reject', 'user', 1894, '{\"note\":\"\"}', '2026-11-06 10:46:16'),
(227, 1, 'guide.reject', 'user', 1891, '{\"note\":\"\"}', '2026-11-06 10:46:17'),
(228, 1, 'guide.reject', 'user', 1887, '{\"note\":\"\"}', '2026-11-06 10:46:17'),
(229, 1, 'guide.reject', 'user', 1888, '{\"note\":\"\"}', '2026-11-06 10:46:17'),
(230, 1, 'guide.reject', 'user', 1875, '{\"note\":\"\"}', '2026-11-06 10:46:18'),
(231, 1, 'guide.reject', 'user', 1878, '{\"note\":\"\"}', '2026-11-06 10:46:18'),
(232, 1, 'guide.reject', 'user', 1871, '{\"note\":\"\"}', '2026-11-06 10:46:19'),
(233, 1, 'guide.reject', 'user', 1872, '{\"note\":\"\"}', '2026-11-06 10:46:19'),
(234, 1, 'guide.reject', 'user', 1861, '{\"note\":\"\"}', '2026-11-06 10:46:20'),
(235, 1, 'guide.reject', 'user', 1858, '{\"note\":\"\"}', '2026-11-06 10:46:20'),
(236, 1, 'guide.reject', 'user', 1854, '{\"note\":\"\"}', '2026-11-06 10:46:21'),
(237, 1, 'guide.reject', 'user', 1855, '{\"note\":\"\"}', '2026-11-06 10:46:21'),
(238, 1, 'guide.reject', 'user', 1851, '{\"note\":\"\"}', '2026-11-06 10:46:22'),
(239, 1, 'guide.reject', 'user', 1845, '{\"note\":\"\"}', '2026-11-06 10:46:22'),
(240, 1, 'guide.reject', 'user', 1848, '{\"note\":\"\"}', '2026-11-06 10:46:22'),
(241, 1, 'guide.reject', 'user', 1844, '{\"note\":\"\"}', '2026-11-06 10:46:23'),
(242, 1, 'guide.reject', 'user', 1829, '{\"note\":\"\"}', '2026-11-06 10:46:23'),
(243, 1, 'guide.reject', 'user', 1826, '{\"note\":\"\"}', '2026-11-06 10:46:23'),
(244, 1, 'guide.reject', 'user', 1822, '{\"note\":\"\"}', '2026-11-06 10:46:24'),
(245, 1, 'guide.reject', 'user', 1823, '{\"note\":\"\"}', '2026-11-06 10:46:24'),
(246, 1, 'user.delete', 'user', 11, '{\"name\":\"john ivan laluna\",\"email\":\"johnivan558@gmail.com\",\"role\":\"tourist\"}', '2026-11-06 10:46:49'),
(247, 1, 'user.delete', 'user', 699, '{\"name\":\"Eliza Yang\",\"email\":\"elyang@sample.com\",\"role\":\"hotel_provider\"}', '2026-11-06 10:50:00'),
(248, 1, 'user.delete', 'user', 371, '{\"name\":\"Christian Yongzon\",\"email\":\"ben@sample.com\",\"role\":\"guide\"}', '2026-11-06 10:50:32'),
(249, 1, 'user.delete', 'user', 369, '{\"name\":\"johnivangwapo\",\"email\":\"johnivan5558@gmail.com\",\"role\":\"tourist\"}', '2026-11-06 10:50:35'),
(250, 1, 'user.delete', 'user', 51, '{\"name\":\"Christian Yongzon\",\"email\":\"christianyongzon143@gmail.com\",\"role\":\"tourist\"}', '2026-11-06 10:50:42'),
(251, 1, 'user.delete', 'user', 46, '{\"name\":\"Junril Ubas\",\"email\":\"samplesample@sample.com\",\"role\":\"hotel_provider\"}', '2026-11-06 10:50:46'),
(252, 1, 'user.delete', 'user', 23, '{\"name\":\"John Doe\",\"email\":\"johndoe@sample.com\",\"role\":\"guide\"}', '2026-11-06 10:50:50'),
(253, 1, 'user.delete', 'user', 20, '{\"name\":\"johnivan\",\"email\":\"johnivan58@gmail.com\",\"role\":\"tourist\"}', '2026-11-06 10:50:54'),
(254, 1, 'guide.approve', 'user', 2466, NULL, '2026-11-06 11:04:40'),
(255, 1, 'guide.approve', 'user', 2465, NULL, '2026-11-06 11:04:42'),
(256, 1, 'guide_listing.approve', 'listing', 1193, '{\"title\":\"Kawasan Falls Canyoneering Adventure\",\"guide_id\":2466}', '2026-11-06 11:17:19'),
(257, 1, 'guide_listing.approve', 'listing', 1198, '{\"title\":\"Temple of Leah Roman Shrine & Viewpoint\",\"guide_id\":2466}', '2026-11-06 13:10:10'),
(258, 1, 'guide_listing.approve', 'listing', 1197, '{\"title\":\"Fort San Pedro Historical Citadel\",\"guide_id\":2466}', '2026-11-06 13:10:11'),
(259, 1, 'guide_listing.approve', 'listing', 1196, '{\"title\":\"Magellan\\u2019s Cross & Santo Ni\\u00f1o Pilgrim Site\",\"guide_id\":2466}', '2026-11-06 13:10:12'),
(260, 1, 'guide_listing.approve', 'listing', 1195, '{\"title\":\"Guided Oslob Whale Shark Snorkeling Encounter\",\"guide_id\":2466}', '2026-11-06 13:10:14'),
(261, 1, 'guide_listing.approve', 'listing', 1194, '{\"title\":\"Moalboal Sardine Run and Marine Sanctuary Snorkeling Tour\",\"guide_id\":2466}', '2026-11-06 13:10:15'),
(262, 1, 'guide_listing.approve', 'listing', 1204, '{\"title\":\"Sumilon Island Sandbar & Marine Reserve\",\"guide_id\":2465}', '2026-11-06 13:39:07'),
(263, 1, 'guide_listing.approve', 'listing', 1203, '{\"title\":\"Simala Shrine Marian Castle Monastery\",\"guide_id\":2465}', '2026-11-06 13:39:09'),
(264, 1, 'guide_listing.approve', 'listing', 1202, '{\"title\":\"Sirao Pictorial Garden & Flower Farm\",\"guide_id\":2465}', '2026-11-06 13:39:10'),
(265, 1, 'guide_listing.approve', 'listing', 1201, '{\"title\":\"Osme\\u00f1a Peak Early Sunrise Trek & Highland View\",\"guide_id\":2465}', '2026-11-06 13:39:11'),
(266, 1, 'guide_listing.approve', 'listing', 1200, '{\"title\":\"Mactan Marine Sanctuary Island Hopping Cruise\",\"guide_id\":2465}', '2026-11-06 13:39:13'),
(267, 1, 'guide_listing.approve', 'listing', 1199, '{\"title\":\"Early Dawn Malapascua Thresher Shark Deep Dive\",\"guide_id\":2465}', '2026-11-06 13:39:14'),
(268, 1, 'guide_listing.approve', 'listing', 1210, '{\"title\":\"Ogtong Cave & Natural Spring Pool\",\"guide_id\":3}', '2026-11-06 14:09:37'),
(269, 1, 'guide_listing.approve', 'listing', 1209, '{\"title\":\"Cebu Taoist Temple & Beverly Hills Pagodas\",\"guide_id\":3}', '2026-11-06 14:09:39'),
(270, 1, 'guide_listing.approve', 'listing', 1208, '{\"title\":\"10,000 Roses Caf\\u00e9 & Boardwalk\",\"guide_id\":3}', '2026-11-06 14:09:40'),
(271, 1, 'guide_listing.approve', 'listing', 1207, '{\"title\":\"Historic Cebu Downtown Walking & Street Food Tour\",\"guide_id\":3}', '2026-11-06 14:09:41'),
(272, 1, 'guide_listing.approve', 'listing', 1206, '{\"title\":\"Bojo River Eco-Cultural Cruise and Paddle Experience\",\"guide_id\":3}', '2026-11-06 14:09:42'),
(273, 1, 'guide_listing.approve', 'listing', 1205, '{\"title\":\"Bantayan Eco-Bike Coastal Beach & Cave Trail\",\"guide_id\":3}', '2026-11-06 14:09:44'),
(274, 1, 'guide.approve', 'user', 2469, NULL, '2026-11-06 14:18:43'),
(275, 1, 'guide.approve', 'user', 2468, NULL, '2026-11-06 14:18:45'),
(276, 1, 'hotel.approve', 'listing', 1211, '{\"hotel_title\":\"Bluewater Maribago Beach Resort\",\"provider_id\":2469}', '2026-11-06 14:26:34'),
(277, 1, 'guide_listing.approve', 'listing', 1212, '{\"title\":\"3D2N South Cebu Adventure: Canyoneering, Sardine Run, Simala Shrine, Sumilon Island & Honda Click V2 Scooter\",\"guide_id\":3}', '2026-11-06 14:51:24');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `booking_date` date NOT NULL,
  `check_in_date` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `nights` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `booking_time` time DEFAULT NULL,
  `booking_end_time` time DEFAULT NULL,
  `booking_dates` text DEFAULT NULL,
  `guests` int(11) NOT NULL DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_stage` enum('full','deposit_paid','balance_due','paid','settled') NOT NULL DEFAULT 'full',
  `status` enum('pending','confirmed','completed','cancelled','disputed','refunded') NOT NULL DEFAULT 'pending',
  `hotel_status` enum('pending','deposit_paid','confirmed','checked_in','completed','cancelled','no_show') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `verify_token` varchar(64) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `unit_completed_at` timestamp NULL DEFAULT NULL,
  `provider_completed_at` timestamp NULL DEFAULT NULL,
  `promo_code_id` int(10) UNSIGNED DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `refund_status` varchar(50) DEFAULT NULL,
  `cancellation_policy` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `icon` varchar(40) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `description`, `sort_order`) VALUES
(1, 'Things to Do', 'things-to-do', 'compass', 'Tours, attractions & activities across Cebu', 1),
(2, 'Attractions', 'attractions', 'ticket', 'Tours, heritage sites, and premier attractions across Cebu', 2),
(3, 'Hotels & Stays', 'hotels', 'bed', 'Resorts, hotels and stays', 3),
(4, 'Restaurants', 'restaurants', 'utensils', 'Where to eat in Cebu', 4),
(5, 'Travel Packages', 'packages', 'cube', 'All-inclusive multi-service trips curated and hosted by local guides', 5);

-- --------------------------------------------------------

--
-- Table structure for table `conversation_settings`
--

CREATE TABLE `conversation_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `partner_id` int(10) UNSIGNED NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversation_settings`
--

INSERT INTO `conversation_settings` (`id`, `user_id`, `partner_id`, `is_pinned`, `is_archived`, `updated_at`) VALUES
(1, 2, 3, 0, 0, '2026-06-30 06:34:54'),
(2, 3, 2, 0, 1, '2026-10-21 08:50:44');

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `guide_id` int(10) UNSIGNED NOT NULL,
  `problem_type` enum('extra_payment','no_show','service_mismatch','unsafe','other') NOT NULL,
  `amount_requested` decimal(10,2) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('open','reviewing','resolved_refund','resolved_warning','rejected') NOT NULL DEFAULT 'open',
  `admin_note` varchar(500) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispute_files`
--

CREATE TABLE `dispute_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `dispute_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guide_availability`
--

CREATE TABLE `guide_availability` (
  `id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `blocked_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guide_documents`
--

CREATE TABLE `guide_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `doc_type` varchar(60) NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guide_documents`
--

INSERT INTO `guide_documents` (`id`, `user_id`, `doc_type`, `label`, `file_path`, `created_at`) VALUES
(1, 12, 'valid_id', 'suyae.jpg', 'uploads/guide-docs/12/503be55b8d5f5825.jpg', '2026-09-16 18:24:44'),
(2, 12, 'tourism_accreditation', '13.jpg', 'uploads/guide-docs/12/15936d4381f7ffae.jpg', '2026-09-16 18:24:44'),
(3, 12, 'other', 'suyae.jpg', 'uploads/guide-docs/12/008376f881a71644.jpg', '2026-09-16 18:24:44'),
(4, 3, 'valid_id', 'Valid government ID', 'uploads/guide-docs/3/valid-id.svg', '2026-09-21 01:34:41'),
(5, 3, 'guide_license', 'Tour guide license', 'uploads/guide-docs/3/guide-license.svg', '2026-09-21 01:34:41'),
(18, 167, 'valid_id', 'guide2.png', 'uploads/guide-docs/167/d3b196b2709aecf1.png', '2026-10-20 04:42:11'),
(19, 167, 'employment', 'guide2.png', 'uploads/guide-docs/167/d7eaa5d4bf5efa29.png', '2026-10-20 04:42:11'),
(89, 2465, 'valid_id', 'sample id.jpg', 'uploads/guide-docs/2465/a41fa9334611e24a.jpg', '2026-11-06 11:02:47'),
(90, 2465, 'guide_license', 'sample doc.jpg', 'uploads/guide-docs/2465/8ec915da0c2bb072.jpg', '2026-11-06 11:02:47'),
(91, 2466, 'valid_id', 'sample id.jpg', 'uploads/guide-docs/2466/f9847ea7da74a6c4.jpg', '2026-11-06 11:04:25'),
(92, 2466, 'guide_license', 'sample doc.jpg', 'uploads/guide-docs/2466/09cd384dadf112d8.jpg', '2026-11-06 11:04:25'),
(93, 2468, 'valid_id', 'sample id.jpg', 'uploads/guide-docs/2468/79d392e73120eaf5.jpg', '2026-11-06 14:15:48'),
(94, 2468, 'employment', 'sample doc.jpg', 'uploads/guide-docs/2468/ce7f9b5de33b2352.jpg', '2026-11-06 14:15:48'),
(95, 2469, 'valid_id', 'sample id.jpg', 'uploads/guide-docs/2469/fbed9aa4bfde83ad.jpg', '2026-11-06 14:18:15'),
(96, 2469, 'employment', 'sample doc.jpg', 'uploads/guide-docs/2469/cc5c4cb29769e3c0.jpg', '2026-11-06 14:18:15');

-- --------------------------------------------------------

--
-- Table structure for table `hotel_rooms`
--

CREATE TABLE `hotel_rooms` (
  `id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `room_size` varchar(60) DEFAULT '32 m²',
  `bed_type` varchar(80) DEFAULT '1 King Bed',
  `max_guests` int(10) UNSIGNED DEFAULT 2,
  `price_per_night` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_percentage` int(10) UNSIGNED NOT NULL DEFAULT 20,
  `total_units` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `available_units` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `status` enum('available','unavailable','blocked','fully_booked') NOT NULL DEFAULT 'available',
  `amenities` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_rooms`
--

INSERT INTO `hotel_rooms` (`id`, `listing_id`, `name`, `description`, `room_size`, `bed_type`, `max_guests`, `price_per_night`, `deposit_percentage`, `total_units`, `available_units`, `status`, `amenities`, `image`, `images`, `created_at`, `updated_at`) VALUES
(1, 10, 'Premier Hilltop Villa', 'Overlooking the lush rolling hills of Aloguinsan. Features a private sunset balcony, handcrafted timber furnishings, and organic bath amenities.', '42 m² (452 sq ft)', '1 King Bed', 2, 3500.00, 20, 1, 1, 'available', 'Air Conditioning, Free High-Speed Wi-Fi, Private Hilltop Balcony, Hot Rain Shower, Mini-Bar, Daily Organic Breakfast for 2', 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1571003123894-1f0594d2b5d9?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(2, 10, 'Farm View Family Cottage', 'Spacious standalone cottage surrounded by organic farm gardens. Ideal for families and small groups visiting the Bojo River eco-tour.', '58 m² (624 sq ft)', '2 Queen Beds', 4, 4800.00, 25, 1, 1, 'available', 'Air Conditioning, Free Wi-Fi, Garden Patio, Hot Shower, Kitchenette, Tea & Coffee Maker, Farm Tour Pass', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1582719478250-c89cae4dc85b?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(3, 10, 'Eco-Glamping Suite', 'A cozy safari-style glamping tent on a raised wooden platform. Perfect for nature lovers wanting peaceful night breezes.', '28 m² (301 sq ft)', '1 Queen Bed', 2, 2600.00, 20, 1, 1, 'available', 'Silent Eco-Fan & Inverter AC, Private Open-Air Rain Shower, Stargazing Deck, Free Wi-Fi at Lounge', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1520250497591-112f2f40a3f4?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-02 09:08:36'),
(4, 11, 'Deluxe Oceanfront Bungalow', 'Step directly out toward the Tañon Strait. Features breathtaking sunset views, high ceiling bamboo accents, and dive equipment drying racks.', '38 m² (409 sq ft)', '1 King Bed', 2, 2800.00, 20, 1, 1, 'available', 'Direct Beachfront, Starlink Wi-Fi (150 Mbps), Inverter AC, Hot Rain Shower, Mini-Fridge, Free Snorkel Gear', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1520250497591-112f2f40a3f4?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(5, 11, 'Sea View Diver Suite', 'Upper-level suite with panoramic views of Pescador Island. Includes dedicated gear storage, workstation, and daily breakfast.', '46 m² (495 sq ft)', '1 King Bed + 1 Daybed', 3, 3600.00, 20, 1, 1, 'available', 'Panoramic Sea View, Work Desk, Air Conditioning, Safe, Tea & Coffee, Dive Center Priority Booking', 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1571003123894-1f0594d2b5d9?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(6, 11, 'Standard Garden Room', 'Tranquil room nestled in the resort tropical garden. Quiet, cool, and comfortable after an active day of diving.', '30 m² (322 sq ft)', '2 Twin Beds', 2, 2200.00, 20, 1, 1, 'available', 'Garden View, Silent AC, En-suite Bathroom with Hot Water, Free Wi-Fi, Fresh Beach Towels', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1566073771259-6a8506099945?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(7, 12, 'Thalasso Island Pool Villa', 'Ultra-luxurious private island villa with ionized seawater pool and unobstructed vistas of the Badian bay and mountains.', '65 m² (700 sq ft)', '1 King Bed', 2, 6500.00, 30, 1, 1, 'available', 'Private Plunge Pool, Butler Service, Luxury Soaking Tub, High-speed Wi-Fi, Spa Treatments Discount, Free Island Boat Transfer', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1582719478250-c89cae4dc85b?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(8, 12, 'Deluxe Island Badian Suite', 'Spacious open-concept suite adorned with native marble and Philippine hardwood. Expansive balcony overlooking the white sand beach.', '52 m² (560 sq ft)', '1 King Bed + 1 Daybed', 3, 5200.00, 25, 1, 1, 'available', 'Sea View Balcony, Air Conditioning, Marble Bathroom, Free Breakfast for 2, Organic Toiletries', 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1571003123894-1f0594d2b5d9?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(9, 13, 'Beachfront Standard Queen', 'Clean, cozy beachfront room 5 minutes from the Tan-awan whale shark briefing center. Listen to the ocean waves as you sleep.', '24 m² (258 sq ft)', '1 Queen Bed', 2, 1800.00, 20, 1, 1, 'available', 'Beachfront Access, Air Conditioning, Hot Shower, Cable TV, Wi-Fi in Public Areas, Early Whale Shark Queue Assistance', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1566073771259-6a8506099945?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(10, 13, 'Family Seaside Suite', 'Two double beds with ample floor space for luggage and gear. Perfect jump-off point for Sumilon Island day trips.', '36 m² (387 sq ft)', '2 Double Beds', 4, 2800.00, 20, 1, 1, 'available', 'Air Conditioning, Refrigerator, Private Bathroom, Sea View Porch, Free Parking', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1445019980597-93fa8acb246c?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(11, 14, 'Highland Mountain Cabin', 'Rustic wooden cabin in cool Mantalongon. Enjoy the mountain breeze and stunning sunrises before hiking Osmeña Peak.', '26 m² (280 sq ft)', '1 Queen Bed', 2, 2200.00, 20, 1, 1, 'available', 'Mountain View Deck, Hot Shower, Electric Kettle & Tea Selection, Mountain Trekking Guide Referral', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1445019980597-93fa8acb246c?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(12, 14, 'Osmeña Peak View Family Room', 'Generously sized highland room with picture windows framing Dalaguete’s jagged limestone peaks.', '40 m² (430 sq ft)', '2 Queen Beds', 4, 3200.00, 20, 1, 1, 'available', 'Balcony with Peak View, Hot Water Shower, Dining Table, Wi-Fi, Complimentary Native Hot Chocolate', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1551882547-ff40c63fe5fa?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(13, 15, 'Skyline City View Deluxe', 'Perched on the Busay hillside with sparkling night views of Cebu City. Minutes from Temple of Leah and Tops Lookout.', '32 m² (344 sq ft)', '1 King Bed', 2, 3200.00, 20, 1, 1, 'available', 'Panoramic Skyline View, 43\" Smart TV with Netflix, High-Speed Wi-Fi, Air Conditioning, Rainfall Shower', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1551882547-ff40c63fe5fa?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(14, 15, 'Executive Busay Suite', 'Corner suite featuring wrap-around floor-to-ceiling glass windows, living area, and private jacuzzi tub.', '48 m² (516 sq ft)', '1 King Bed + 1 Sofa Bed', 3, 4200.00, 25, 1, 1, 'available', 'Private Jacuzzi, City View Terrace, Mini Bar, Bathrobes & Slippers, Breakfast for 2', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', '[\"https:\\/\\/images.unsplash.com\\/photo-1582719478250-c89cae4dc85b?w=800&q=80\"]', '2026-10-23 07:07:48', '2026-11-01 09:53:18'),
(15, 23, 'Deluxe Suite Test', '', '32 m²', '1 King Bed', 2, 3500.00, 20, 1, 1, 'available', '', 'https://example.com/hotel.jpg', '[\"https:\\/\\/example.com\\/hotel.jpg\"]', '2026-10-25 13:40:01', '2026-11-01 09:53:18'),
(16, 24, 'Deluxe Suite Test', '', '32 m²', '1 King Bed', 2, 3500.00, 20, 1, 1, 'available', '', 'https://example.com/hotel.jpg', '[\"https:\\/\\/example.com\\/hotel.jpg\"]', '2026-10-25 13:40:12', '2026-11-01 09:53:18'),
(17, 25, 'Deluxe Suite Test', '', '32 m²', '1 King Bed', 2, 3500.00, 20, 1, 1, 'available', '', 'https://example.com/hotel.jpg', '[\"https:\\/\\/example.com\\/hotel.jpg\"]', '2026-10-25 13:40:21', '2026-11-01 09:53:18'),
(18, 26, 'Deluxe Suite Test', '', '32 m²', '1 King Bed', 2, 3500.00, 20, 1, 1, 'available', '', 'https://example.com/hotel.jpg', '[\"https:\\/\\/example.com\\/hotel.jpg\"]', '2026-10-25 13:40:30', '2026-11-01 09:53:18'),
(163, 469, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-30 23:20:56', '2026-10-30 23:20:56'),
(164, 474, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-30 23:21:11', '2026-10-30 23:21:11'),
(171, 492, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-30 23:35:38', '2026-10-30 23:35:38'),
(172, 498, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-30 23:37:30', '2026-10-30 23:37:30'),
(178, 520, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-30 23:39:33', '2026-10-30 23:39:33'),
(180, 527, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-30 23:50:49', '2026-10-30 23:50:49'),
(181, 533, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-30 23:54:39', '2026-10-30 23:54:39'),
(182, 539, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-30 23:55:31', '2026-10-30 23:55:31'),
(183, 545, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 00:08:27', '2026-10-31 00:08:27'),
(189, 562, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 00:16:40', '2026-10-31 00:16:40'),
(195, 579, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 00:32:44', '2026-10-31 00:32:44'),
(197, 588, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 00:55:11', '2026-10-31 00:55:11'),
(200, 599, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 10:13:40', '2026-10-31 10:13:40'),
(203, 607, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 11:30:22', '2026-10-31 11:30:22'),
(204, 613, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 11:31:07', '2026-10-31 11:31:07'),
(206, 621, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 12:13:00', '2026-10-31 12:13:00'),
(208, 629, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 12:33:07', '2026-10-31 12:33:07'),
(209, 635, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 12:59:42', '2026-10-31 12:59:42'),
(211, 640, 'Deluxe Ocean View', NULL, '32 m²', '1 King Bed', 2, 4000.00, 20, 1, 1, 'available', NULL, NULL, NULL, '2026-10-31 13:23:43', '2026-10-31 13:23:43'),
(212, 644, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', '45 sqm', '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-10-31 13:26:02', '2026-10-31 13:26:02'),
(240, 2, 'Test Deluxe Suite', NULL, '32 m²', '1 King Bed', 2, 2000.00, 20, 1, 1, 'available', NULL, NULL, NULL, '2026-11-01 08:01:49', '2026-11-01 08:01:49'),
(348, 849, 'Test Deluxe Suite', NULL, '32 m²', '1 King Bed', 2, 2000.00, 20, 1, 1, 'available', NULL, NULL, NULL, '2026-11-01 11:35:53', '2026-11-01 11:35:53'),
(373, 918, 'Deluxe Suite Test', 'Beautiful sunset view from private balcony.', NULL, '1 King Bed', 3, 3200.00, 20, 1, 1, 'available', 'Balcony, Sea View', 'https://example.com/room1.jpg', '[\"https:\\/\\/example.com\\/room1.jpg\",\"https:\\/\\/example.com\\/room2.jpg\"]', '2026-11-02 08:29:23', '2026-11-02 08:29:23'),
(374, 919, 'Updated Deluxe Suite Deluxe', 'Updated luxury beachfront bungalow.', NULL, '2 Queen Beds', 4, 3800.00, 25, 1, 1, 'blocked', 'Balcony, Sea View, Fast Wifi, Bathtub', 'https://example.com/updated_photo.jpg', '[\"https:\\/\\/example.com\\/updated_photo.jpg\",\"https:\\/\\/example.com\\/room2.jpg\"]', '2026-11-02 08:29:50', '2026-11-02 08:29:50'),
(415, 968, 'Premier Deluxe Room', 'Spacious contemporary retreat designed with warm wood accents and Filipino-inspired finishes. Features a plush king-size bed, an expansive private balcony overlooking lush resort gardens, and a modern marble bathroom with a separate bathtub and rain shower.', NULL, '1 King Bed', 2, 6500.00, 20, 1, 1, 'available', 'Air Conditioning, Free Wi-Fi, Hot Shower, Private Balcony, Minibar, Cable TV, Coffee & Tea Maker, Safety Deposit Box', 'uploads/rooms/18/476121fea90dd504.jpg', '[\"uploads\\/rooms\\/18\\/476121fea90dd504.jpg\"]', '2026-11-02 09:25:29', '2026-11-02 11:37:26'),
(439, 1004, 'Test Deluxe Suite', NULL, '32 m²', '1 King Bed', 2, 2000.00, 20, 1, 1, 'available', NULL, NULL, NULL, '2026-11-02 10:05:10', '2026-11-02 10:05:10'),
(471, 1108, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', NULL, '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-11-02 20:44:14', '2026-11-02 20:44:14'),
(476, 1126, 'Ocean Villa Room', 'Spacious villa room overlooking the turquoise sea.', NULL, '2 King Beds', 2, 3200.00, 20, 1, 1, 'available', '', NULL, NULL, '2026-11-03 06:07:38', '2026-11-03 06:07:38'),
(486, 1211, 'Deluxe Garden Bungalow', 'Charming thatched-roof bungalow nestled amidst lush tropical gardens, featuring indigenous Filipino design accents, a marble bathroom with open-concept tub, and a private veranda.', NULL, '1 King Bed', 2, 7500.00, 20, 1, 1, 'available', 'Air Conditioning, Free Wi-Fi, Hot Shower, Private Balcony, Mini Bar, Cable TV, Coffee Maker, Safety Deposit Box', 'uploads/rooms/2469/6929b5f1e3cb41d1.jpg', '[\"uploads\\/rooms\\/2469\\/6929b5f1e3cb41d1.jpg\"]', '2026-11-06 14:30:11', '2026-11-06 14:30:11');

-- --------------------------------------------------------

--
-- Table structure for table `incident_reports`
--

CREATE TABLE `incident_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `report_number` varchar(32) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `category` enum('hotel','vehicle','tour_guide','tourist_spot') NOT NULL,
  `issue_category` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time NOT NULL,
  `reporter_name` varchar(120) NOT NULL,
  `reporter_email` varchar(191) NOT NULL,
  `reporter_phone` varchar(40) DEFAULT NULL,
  `status` enum('pending','under_review','investigating','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `photo_path` varchar(255) DEFAULT NULL,
  `photo_name` varchar(255) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `hotel_name` varchar(191) DEFAULT NULL,
  `hotel_location` varchar(191) DEFAULT NULL,
  `hotel_booking_ref` varchar(100) DEFAULT NULL,
  `hotel_room_or_facility` varchar(120) DEFAULT NULL,
  `vehicle_type` varchar(64) DEFAULT NULL,
  `vehicle_plate_or_id` varchar(64) DEFAULT NULL,
  `vehicle_assigned_driver` varchar(120) DEFAULT NULL,
  `vehicle_trip_or_booking_ref` varchar(100) DEFAULT NULL,
  `guide_name_or_id` varchar(120) DEFAULT NULL,
  `guide_tour_or_booking_ref` varchar(100) DEFAULT NULL,
  `spot_attraction_name` varchar(191) DEFAULT NULL,
  `spot_location` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

CREATE TABLE `listings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
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
  `capacity` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `included` text DEFAULT NULL,
  `not_included` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sale_ends_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `listings`
--

INSERT INTO `listings` (`id`, `user_id`, `category_id`, `title`, `slug`, `summary`, `description`, `area`, `address`, `latitude`, `longitude`, `price`, `price_unit`, `duration`, `capacity`, `included`, `not_included`, `cover_image`, `status`, `rejection_reason`, `submitted_at`, `reviewed_at`, `reviewed_by`, `is_featured`, `sale_price`, `sale_ends_at`, `created_at`, `updated_at`) VALUES
(1193, 2466, 1, 'Kawasan Falls Canyoneering Adventure', 'kawasan-falls-canyoneering-adventure', 'Trek, leap from cliffs up to 30 feet, and swim through the vibrant turquoise waters of the Kanlaob River canyon.', 'Experience Cebu\'s premier downstream river trekking adventure starting from Kanlaob River in Alegria and concluding at the iconic Kawasan Falls in Badian. Participants will hike through jungle trails, scramble across limestone boulders, jump off natural cliffs ranging from 10 to 30 feet (all optional with walking bypasses), and swim in natural spring water pools. A safety briefing is conducted at the base camp before gearing up. What to bring: dry bag, rashguard or swimwear, grip shoes/booties, and waterproof camera.', 'Badian, Cebu', 'Barangay Matutinao, Badian, Cebu', 9.8255057, 123.3736071, 2100.00, 'per person', '4–5 hours', 15, 'DOT-accredited canyon guide (1 guide per 4 guests)\r\nSafety helmet and life vest\r\nHabal-habal transport to jump-off point\r\nMunicipal tourism recovery and environmental fees\r\nPost-tour recovery meal at base camp', 'Waterproof shoe rental (₱100)\r\nAction camera rental with SD card (₱1,000)\r\nLocker rental (₱100)', 'uploads/listings/2466/e3116b9c4deb050f.png', 'approved', NULL, '2026-11-06 11:17:08', '2026-11-06 11:17:19', 1, 1, NULL, NULL, '2026-11-06 11:17:08', '2026-11-06 13:10:58'),
(1194, 2466, 1, 'Moalboal Sardine Run and Marine Sanctuary Snorkeling Tour', 'moalboal-sardine-run-and-marine-sanctuary-snorkeling-tour', 'Snorkel just meters off the shoreline among millions of swirling sardines and resident green sea turtles.', 'Panagsama Beach is world-famous for its massive schooling sardine biomass that resides just 20 to 30 meters from the coast along a vertical reef wall. This guided snorkeling trip leads you directly into the bait ball formation where pelagic jacks occasionally hunt, followed by a shallow reef glide along Talisay Point to spot foraging hawksbill and green sea turtles. What to bring: reef-safe sunscreen, underwater camera, and swim gear.', 'Moalboal, Cebu', 'Panagsama Beach, Barangay Basdiot, Moalboal, Cebu', 9.9372185, 123.3921896, 950.00, 'per person', '2–3 hours', 10, 'Certified local snorkeling guide\r\nSnorkel mask, snorkel pipe, and flotation vest\r\nMarine park conservation fee\r\nFreshwater rinse station access', 'Neoprene fin rental (₱150)\r\nPescador Island motorized boat add-on (₱500 per person)\r\nUnderwater photography assistance (₱300)', 'uploads/listings/2466/f0ed87fdbf914b70.webp', 'approved', NULL, '2026-11-06 11:32:52', '2026-11-06 13:10:15', 1, 1, NULL, NULL, '2026-11-06 11:32:52', '2026-11-06 13:41:07'),
(1195, 2466, 1, 'Guided Oslob Whale Shark Snorkeling Encounter', 'guided-oslob-whale-shark-snorkeling-encounter', 'Swim alongside gentle whale sharks in their natural coastal habitat under strict eco-tourism guidelines.', 'Take an early morning outrigger paddleboat ride into the designated marine conservation strip off Tan-awan, Oslob. Guests spend 30 supervised minutes in the water observing juvenile whale sharks feeding on plankton. Strict briefing guidelines apply: minimum 4-meter distance from the animals, no touching, and zero chemical sunscreens permitted. What to bring: dark swimwear, dry clothes, towel, and waterproof phone pouch.', 'Oslob, Cebu', 'Natalio Bacalso National Highway, Barangay Tan-awan, Oslob, Cebu', 9.4615696, 123.3778971, 1500.00, 'per person', '1.5–2 hours', 12, 'Barangay Tan-awan briefing and registration\r\nLocal boat paddler and spotter\r\nSnorkel mask and standard life jacket\r\nLocal municipal ecological fee', 'Go-Pro rental with a personal boatman photographer (₱550)\r\nHotel pick-up and drop-off\r\nBreakfast buffet (₱250)', 'uploads/listings/2466/d02e08a665798129.webp', 'approved', NULL, '2026-11-06 12:58:22', '2026-11-06 13:10:14', 1, 0, NULL, NULL, '2026-11-06 12:58:22', '2026-11-06 13:10:14'),
(1196, 2466, 2, 'Magellan’s Cross & Santo Niño Pilgrim Site', 'magellan-s-cross-santo-ni-o-pilgrim-site', 'Visit the cradle of Christianity in Asia housing the 1521 cross and the revered image of the Santo Niño.', 'Discover the spiritual heart of Cebu. The octagonal stone kiosk houses the original wooden cross planted by Ferdinand Magellan on April 21, 1521. Immediately adjacent stands the Basilica Minore del Santo Niño, founded in 1565. Visitors can observe traditional Sinulog candle-dancers offering prayers outside the basilica and explore the museum housing antique vestments and ecclesiastical artifacts. Dress code: shoulders and knees must be covered.', 'Cebu City, Cebu', 'Santo Niño Pilgrim Center, Osmeña Boulevard, Cebu City', 10.3285605, 123.9008849, 450.00, 'per person', '', 20, 'Guided historical narration\r\nBasilica ecclesiastical museum admission ticket\r\nTraditional candle prayer offering voucher', 'Religious souvenir items\r\nGratuities for prayer dancers', 'uploads/listings/2466/d0009dd2bbe959ee.jpg', 'approved', NULL, '2026-11-06 13:02:16', '2026-11-06 13:10:12', 1, 1, NULL, NULL, '2026-11-06 13:02:16', '2026-11-06 13:39:57'),
(1197, 2466, 2, 'Fort San Pedro Historical Citadel', 'fort-san-pedro-historical-citadel', 'Step inside the oldest triangular stone bastion fortress built by Spanish conquistadors in 1738.', 'Built under the direction of Miguel López de Legazpi, Fort San Pedro served as a military defense fortification against pirates and raiders. Tour the elevated ramparts mounted with bronze Spanish cannons, walk through the three fortified bastions (La Concepción, San Ignacio de Loyola, and San Miguel), and view historical Spanish-era documents, nautical maps, and artifacts preserved inside the museum rooms.', 'Cebu City, Cebu', 'A. Pigafetta Street, Plaza Independencia, Cebu City', 10.3285605, 123.9008849, 300.00, 'per person', '1 hour', 20, 'Fort San Pedro official entrance admission\r\nCurated museum gallery access\r\nOn-site historical site escort', 'Professional DSLR photo shooting permit\r\nSouvenirs from the heritage shop', 'uploads/listings/2466/05555746ae808201.jpg', 'approved', NULL, '2026-11-06 13:06:03', '2026-11-06 13:10:11', 1, 1, NULL, NULL, '2026-11-06 13:06:03', '2026-11-06 13:11:07'),
(1198, 2466, 2, 'Temple of Leah Roman Shrine & Viewpoint', 'temple-of-leah-roman-shrine-viewpoint', 'Marvel at a grand Greco-Roman temple built as a symbol of undying love overlooking metropolitan Cebu.', 'Situated in the highlands of Busay, this neoclassical monument features 24 expansive chambers, towering Doric columns, life-sized statues of Roman gladiators and lions, and a grand brass statue of Leah Albino-Adarna. The temple\'s expansive granite courtyard provides uninterrupted panoramic views of Metro Cebu, Mandaue, and Mactan Island out to the Bohol Strait.', 'Cebu City, Cebu', 'Cebu Transcentral Highway, Barangay Busay, Cebu City', 10.3546222, 123.8867634, 350.00, 'per person', '1.5–2 hours', 25, 'Temple of Leah general admission ticket\r\nAccess to the upper courtyard and open galleries\r\nHighland scenic viewpoint access', 'Private mountain vehicle parking fee (₱50)\r\nCafé snacks and cold refreshments on terrace', 'uploads/listings/2466/e010f1728aaf6d6e.jpg', 'approved', NULL, '2026-11-06 13:08:54', '2026-11-06 13:10:10', 1, 0, NULL, NULL, '2026-11-06 13:08:54', '2026-11-06 14:11:59'),
(1199, 2465, 1, 'Early Dawn Malapascua Thresher Shark Deep Dive', 'early-dawn-malapascua-thresher-shark-deep-dive', 'Witness pelagic thresher sharks emerge from the deep at sunrise cleaning stations.', 'Depart before sunrise aboard a traditional dive banca bound for Monad or Kimud Shoal. This pelagic dive targets cleaning stations 20 to 30 meters deep, where thresher sharks (Alopias pelagicus) arrive at dawn to have parasites removed by cleaner wrasses. Open Water Advanced or Nitrox certification is required due to depth profiles. What to bring: C-card, dive logbook, dive computer, and warm surface jacket.', 'Daanbantayan, Cebu', 'Bounty Beach, Malapascua Island, Daanbantayan, Cebu', 11.2503512, 123.9991280, 2800.00, 'per person', '3–4 hours', 8, 'Professional PADI/SDI Divemaster guide\r\n1 boat dive departure to Monad/Kimud Shoal\r\nDive tank, weights, and weight belt\r\nHot coffee and a morning light snack on board', 'Full scuba gear rental (BCD, regulator, wetsuit, fins) (₱800)\r\nNitrox tank fill (₱350)\r\nMalapascua environmental and dive pass (₱300)', 'uploads/listings/2465/e1459b9fce77039a.webp', 'approved', NULL, '2026-11-06 13:18:57', '2026-11-06 13:39:14', 1, 1, NULL, NULL, '2026-11-06 13:18:57', '2026-11-06 13:40:58'),
(1200, 2465, 1, 'Mactan Marine Sanctuary Island Hopping Cruise', 'mactan-marine-sanctuary-island-hopping-cruise', 'Cruise across the Hilutungan Channel to snorkel vibrant marine sanctuaries and dine on a private sandbar.', 'Board a motorized outrigger banca from Marigondon or Punta Engaño wharf and sail to the protected Hilutungan and Nalusuan Marine Sanctuaries. Snorkel in deep coral gardens home to giant clams, parrotfish, and trevallies, followed by a leisurely stopover at Caohagan Island for fresh seafood cooked on the beach. What to bring: swimwear, dry bag, sunglasses, and beach towel.', 'Lapu-Lapu City, Cebu', 'Marigondon Beach Road, Lapu-Lapu City, Cebu', 10.2918049, 123.9043976, 2400.00, 'per person', '6–7 hours', 14, 'Private/chartered motorized outrigger banca\r\nBoat crew and licensed tourist guide\r\nSnorkel gear and safety life jackets\r\nGrilled seafood and local fruit picnic lunch on board', 'Nalusuan Sanctuary landing fee (₱400)\r\nHilutungan marine fee (₱300)\r\nAlcoholic drinks and soda', 'uploads/listings/2465/23622742fe0bed76.jpg', 'approved', NULL, '2026-11-06 13:22:42', '2026-11-06 13:39:13', 1, 0, NULL, NULL, '2026-11-06 13:22:42', '2026-11-06 13:39:13'),
(1201, 2465, 1, 'Osmeña Peak Early Sunrise Trek & Highland View', 'osme-a-peak-early-sunrise-trek-highland-view', 'Summit Cebu’s highest elevation at sunrise to view jagged limestone peaks rolling into the sea.', 'Climb up to 1,013 meters above sea level to stand on Cebu\'s tallest crest. The jagged, cone-shaped limestone hills resemble a green-carpeted labyrinth reminiscent of Bohol\'s Chocolate Hills set against the open ocean. The trek from the registration outpost is moderately paced along established stone trails, making it accessible to beginners. What to bring: trail running shoes or hiking sandals, light windbreaker, water bottle, and flashlight/headlamp.', 'Dalaguete, Cebu', 'Mantalongon, Dalaguete, Cebu', 9.8131561, 123.4622906, 850.00, 'per person', '2–3 hours', 12, 'Accredited local Dalaguete mountain guide\r\nBarangay Mantalongon environmental and trailhead fee\r\nHabal-habal transfer from Mantalongon vegetable market to base', 'Trekking pole rental (₱50)\r\nVan transfer from Cebu City/South terminal\r\nHot breakfast at Mantalongon market (₱150)', 'uploads/listings/2465/589db446b913f2b1.jpg', 'approved', NULL, '2026-11-06 13:25:39', '2026-11-06 13:39:11', 1, 1, NULL, NULL, '2026-11-06 13:25:39', '2026-11-06 13:40:45'),
(1202, 2465, 2, 'Sirao Pictorial Garden & Flower Farm', 'sirao-pictorial-garden-flower-farm', 'Stroll through vibrant terraced flower gardens and famous panoramic photo installations in the Busay hills.', 'Spanning a lush hillside in Busay, Sirao Garden features fiery red, orange, and gold celosia blooms alongside sunflowers, chrysanthemums, and bougainvillea. Designed as an open-air photography haven, it boasts whimsical installations, including a giant golden hand overlooking the valley, wooden windmills, rustic swing bridges, and thatched viewing nests.', 'Cebu City, Cebu', 'Canada Drive, Barangay Sirao, Cebu City', 10.3285605, 123.9008849, 300.00, 'per person', '2 hours', 25, 'Sirao Garden entrance pass\r\nFull access to both upper and lower garden installations\r\nHand photo-nest installation queue pass', 'Mountain highway, habal-habal/van transport\r\nFresh flower bouquets and native snacks', 'uploads/listings/2465/9559f7373c5885d2.jpg', 'approved', NULL, '2026-11-06 13:33:04', '2026-11-06 13:39:10', 1, 1, NULL, NULL, '2026-11-06 13:33:04', '2026-11-06 14:10:11'),
(1203, 2465, 2, 'Simala Shrine Marian Castle Monastery', 'simala-shrine-marian-castle-monastery', 'Tour Cebu’s most famous hillside castle monastery and Marian pilgrimage site in Sibonga.', 'Built by the Marian Monks of Eucharistic Adoration in 1998, Simala Shrine is an architectural masterpiece resembling a European Gothic castle perched on the hills of Lindogon. Pilgrims and tourists visit to witness the Miraculous Virgin Mary statue, view the testimonial hall of answered prayers, and admire its grand staircases, landscaped courtyards, and cathedral ceilings. Modest attire is strictly enforced (sleeved tops and full-length bottoms required).', 'Sibonga, Cebu', 'Marian Hills, Barangay Lindogon, Sibonga, Cebu', 9.9807320, 123.6095954, 600.00, 'per person', '3 hours', 20, 'Monastery grounds admission\r\nPilgrim orientation and designated prayer hall access\r\nTour coordinator assistance', 'Monastery prayer candles (assorted intentions) (₱50 each)\r\nPrivate round-trip south terminal transit', 'uploads/listings/2465/6915316973c1ebc6.jpg', 'approved', NULL, '2026-11-06 13:35:43', '2026-11-06 13:39:09', 1, 1, NULL, NULL, '2026-11-06 13:35:43', '2026-11-06 13:40:14'),
(1204, 2465, 2, 'Sumilon Island Sandbar & Marine Reserve', 'sumilon-island-sandbar-marine-reserve', 'Relax on an iconic, tide-shifting white sandbar surrounded by crystal-clear azure waters and coral reefs.', 'Located just a 15-minute boat ride off Oslob, Sumilon is a 24-hectare coral island surrounded by the Philippines\' first designated marine reserve (established in 1974). The island is famous for its shifting white sandbar that changes shape with the seasonal monsoons. The turquoise waters around the sandbar offer shallow, beginner-friendly snorkeling with damselfish, clownfish, and thriving hard coral beds.', 'Oslob, Cebu', 'Bancogon Port / Sumilon Island, Oslob, Cebu', 9.5204466, 123.4335205, 1200.00, 'per person', '4–5 hours', 16, 'Round-trip motorized boat transfers from Bancogon Mainland Wharf\r\nIsland sandbar access and municipal environmental fee\r\nMarine sanctuary day pass', 'Bluewater Sumilon Resort amenities pass (pool and lagoon kayaking) (₱1,200)\r\nSnorkel gear rental (₱150)\r\nLunch buffet', 'uploads/listings/2465/ac42c6aef2ee1b2f.jpg', 'approved', NULL, '2026-11-06 13:38:54', '2026-11-06 13:39:07', 1, 0, NULL, NULL, '2026-11-06 13:38:54', '2026-11-06 13:39:07'),
(1205, 3, 1, 'Bantayan Eco-Bike Coastal Beach & Cave Trail', 'bantayan-eco-bike-coastal-beach-cave-trail', 'Pedal along white sand shores, coconut groves, and hidden cliff jump spots on Bantayan\'s quiet roads.', 'Explore the southern tip of Bantayan Island on fat-tire or gravel bikes. The route winds through Santa Fe\'s coastal villages, stopping at Ogtong Beach, the ruins of an unfinished beach resort with open cliff platforms into the sea, and secluded stretches of Sugar Beach and Kota Beach sandbar. The terrain is flat, relaxing, and beginner-friendly. What to bring: cycling shorts, sunglasses, hydration pack, and camera.', 'Santa Fe, Bantayan Island', 'Talisay, Santa Fe, Bantayan Island, Cebu', 11.2185609, 123.7285283, 1100.00, 'per person', '4–5 hours', 10, 'Geared bicycle and safety helmet\r\nLocal trail captain / cycling guide\r\nHydration refills and fresh young coconut (buko) break\r\nFirst-aid emergency support', 'E-bike / scooter upgrade (₱350)\r\nOgtong Cave entrance ticket (₱200)\r\nLunch at Santa Fe food park', 'uploads/listings/3/3153d8a1c481dfff.jpg', 'approved', NULL, '2026-11-06 13:53:54', '2026-11-06 14:09:44', 1, 1, NULL, NULL, '2026-11-06 13:53:54', '2026-11-06 14:10:25'),
(1206, 3, 1, 'Bojo River Eco-Cultural Cruise and Paddle Experience', 'bojo-river-eco-cultural-cruise-and-paddle-experience', 'Glide down a serene tidal river flanked by protected mangroves while learning local ecology from fisherfolk guides.', 'Managed directly by the Bojo Aloguinsan Ecotourism Association (BAETA), this tour offers an educational outrigger boat cruise down the emerald-green Bojo River outlet leading to the open Tañon Strait. Guests learn about 22 mangrove species, native birdlife, and coastal conservation, followed by an opportunity to swim at the rivermouth or take out traditional outrigger paddleboats. What to bring: sun hat, slip-on shoes, swimwear, and insect repellent.', 'Aloguinsan, Cebu', 'Barangay Bojo, Aloguinsan, Cebu', 10.2029828, 123.5481389, 900.00, 'per person', '2.5–3 hours', 15, 'Guided river cruise with BAETA community guide\r\nTraditional welcome lei and native snack (soli-soli pastry)\r\nRiver eco-tourism entrance and maintenance fee\r\nTraditional handicraft demonstration', 'Buffet organic farm lunch at The Farmhouse (₱350)\r\nStand-up paddleboard rental (₱250)', 'uploads/listings/3/99eb4b8df32a377b.jpg', 'approved', NULL, '2026-11-06 13:56:09', '2026-11-06 14:09:42', 1, 1, NULL, NULL, '2026-11-06 13:56:09', '2026-11-06 14:10:20'),
(1207, 3, 1, 'Historic Cebu Downtown Walking & Street Food Tour', 'historic-cebu-downtown-walking-street-food-tour', 'Walk through centuries of history along Colon Street, concluding with authentic street delicacies and lechon.', 'Trace the roots of the oldest city in the Philippines on foot. Starting at Plaza Independencia, this walking expedition navigates through the Spanish colonial footprint, stopping at Fort San Pedro, Magellan’s Cross, and the Basilica Minore del Santo Niño. The walk winds down Colon Street—the country\'s oldest street—and through Carbon Market to sample hanging rice (puso), ngohiong, siomai sa Tisa, and Cebu lechon. What to bring: comfortable walking shoes, umbrella, and cash for market snacks.', 'Cebu City, Cebu', 'Plaza Independencia, M.J. Cuenco Avenue, Cebu City', 10.2940335, 123.9049287, 1200.00, 'per person', '3.5–4 hours', 10, 'Licensed heritage and cultural walking guide\r\nFort San Pedro and museum admission tickets\r\nCurated 5-course street food tasting sampler\r\nBottled mineral water', 'Souvenir shopping budget\r\nJeepney fare outside the itinerary\r\nFull lechon takeaway box', 'uploads/listings/3/f2ebb8bb06095cbc.jpg', 'approved', NULL, '2026-11-06 13:58:50', '2026-11-06 14:09:41', 1, 0, NULL, NULL, '2026-11-06 13:58:50', '2026-11-06 14:09:41'),
(1208, 3, 2, '10,000 Roses Café & Boardwalk', '10-000-roses-caf-boardwalk', 'Witness a sea of illuminated LED white roses set against the Mactan channel and the CCLEX bridge skyline.', 'Positioned on the coastal promenade of Day-as, Cordova, this installation features over 10,000 artificial white roses equipped with LED lighting. During dusk, the field illuminates in warm light against the backdrop of the Cebu-Cordova Link Expressway (CCLEX) and metropolitan Cebu’s glowing waterfront. Visitors can walk the boardwalk pathways, take photos among the blooms, and enjoy seaside dining.', 'Cordova, Mactan, Cebu', 'San Miguel Street, Barangay Day-as, Cordova, Cebu', 10.2556230, 123.9348882, 200.00, 'per person', '1–1.5 hours', 10, 'Attraction entrance ticket\r\nViewing boardwalk and open viewing deck access', 'Food and beverage orders from the café\r\nCCLEX tollway fees (for private vehicles)', 'uploads/listings/3/a0ceda8aecd85b67.webp', 'approved', NULL, '2026-11-06 14:02:13', '2026-11-06 14:09:40', 1, 0, NULL, NULL, '2026-11-06 14:02:13', '2026-11-06 14:09:40'),
(1209, 3, 2, 'Cebu Taoist Temple & Beverly Hills Pagodas', 'cebu-taoist-temple-beverly-hills-pagodas', 'Ascend 81 steps modeled after the Great Wall to explore traditional Chinese pagodas and mountain shrines.', 'Built in 1972 by Cebu’s prominent Chinese-Filipino community, the temple sits 300 meters above sea level in Beverly Hills. Visitors climb 81 steps representing the 81 chapters of the Tao Te Ching to reach multi-tiered, dragon-crested pagodas, tranquil koi ponds, and a replica of the Great Wall of China. The temple offers a peaceful retreat and a sweeping vantage point across the cityscape. Strict silence is observed in the prayer chapels.', 'Cebu City, Cebu', 'Beverly Hills Subdivision, Barangay Lahug, Cebu City', 10.3242559, 123.8985336, 350.00, 'per person', '1.5 hours', 15, 'Guided walking tour of the temple grounds\r\nCultural overview of Taoist rituals and architectural symbolism\r\nTemple admission and maintenance contribution', 'Incense stick offerings\r\nBottled refreshments', 'uploads/listings/3/6950bd6354b95c93.jpeg', 'approved', NULL, '2026-11-06 14:06:20', '2026-11-06 14:09:39', 1, 1, NULL, NULL, '2026-11-06 14:06:20', '2026-11-06 14:09:56'),
(1210, 3, 2, 'Ogtong Cave & Natural Spring Pool', 'ogtong-cave-natural-spring-pool', 'Swim in a crystal-clear subterranean freshwater spring hidden beneath a coastal limestone cavern.', 'Located within the manicured botanical grounds of Ogtong Cave Resort, this natural limestone cave features an underground freshwater pool illuminated by ambient light fixtures. The water remains calm, cool, and crystalline year-round. After soaking in the underground cavern, guests can walk directly to the resort\'s beachfront gardens overlooking the turquoise shallows of the Tañon Strait.', 'Santa Fe, Bantayan Island, Cebu', 'Barangay Pooc, Santa Fe, Bantayan Island, Cebu', 11.2185609, 123.7285283, 400.00, 'per person', '2 hours', 12, 'Ogtong Cave cavern and pool entrance fee\r\nResort botanical garden access\r\nFreshwater shower and changing room usage', 'Resort swimming pool access (₱150)\r\nBeach cabana rental (₱300)\r\nRestaurant meals and drinks', 'uploads/listings/3/c5c573f0b5d716cc.jpg', 'approved', NULL, '2026-11-06 14:09:28', '2026-11-06 14:09:37', 1, 0, NULL, NULL, '2026-11-06 14:09:28', '2026-11-06 14:09:37'),
(1211, 2469, 3, 'Bluewater Maribago Beach Resort', 'bluewater-maribago-beach-resort', 'Luxury seaside escape with a private beach cove, thatched-roof villas, and authentic Filipino hospitality.', 'Nestled along the eastern coast of Mactan Island, Bluewater Maribago offers 7 hectares of tropical paradise featuring a private beach and its own offshore islet. The property showcases traditional Filipino architecture with modern comforts, three lagoon-shaped swimming pools with waterfalls, and several open-air dining venues including Allegro Restaurant and The Cove live seafood pavilion. It is located just 20 minutes from Mactan-Cebu International Airport, making it ideal for island-hopping departures and relaxing beach getaways.', 'Lapu-Lapu City, Cebu', 'Buyong, Maribago, Lapu-Lapu City', 10.2934208, 123.9022613, 7500.00, 'per night', '3:00 PM /  11:00 AM', 10, 'Private White Sand Beach & Private Island\r\n3 Outdoor Lagoon Swimming Pools\r\nAmuma Spa & Wellness Center\r\nComplimentary High-Speed Wi-Fi\r\nOn-site Water Sports & Dive Center\r\nMultiple Dining Options & Cove Seafood Restaurant', 'Roundtrip Airport Van Transfer (₱1,200)\r\nExtra Bed with Breakfast (₱1,800/night)\r\nNo Smoking Indoors\r\nCheck-in requires government ID and security deposit (₱2,000)', 'uploads/listings/2469/96c174c4d98d6d28.jpg', 'approved', NULL, '2026-11-06 14:26:00', '2026-11-06 14:26:34', 1, 0, NULL, NULL, '2026-11-06 14:26:08', '2026-11-06 14:30:11'),
(1212, 3, 5, '3D2N South Cebu Adventure: Canyoneering, Sardine Run, Simala Shrine, Sumilon Island & Honda Click V2 Scooter', '3d2n-south-cebu-adventure-canyoneering-sardine-run-simala-shrine-sumilon-island-honda-click-v2-scooter', 'Complete 3-day South Cebu loop with Kawasan canyoneering, coastal snorkeling, island sandbar, castle shrine, boutique resort stay, and private Honda Click scooter freedom.', '<!-- PACKAGE_OVERVIEW: {\"category_label\":\"\",\"badge\":\"New Expedition\",\"assurance\":\"\"} -->\nExperience the definitive South Cebu circuit combining extreme outdoor thrills, rich cultural architecture, and self-paced coastal road trips. Your home base is Pescadores Suites Moalboal, situated just steps from the Panagsama reef drop-off. Over three action-packed days, leap through turquoise river canyons on the Kawasan Canyoneering adventure, snorkel along millions of schooling sardines and sea turtles, marvel at the Gothic spires of the hillside Simala Shrine, and cruise by motorized banca to the shifting white sands of Sumilon Island. A dedicated Honda Click 125 V2 automatic scooter with two helmets gives you complete mobility to explore coastal lookouts and local food spots at your own pace.\n\nExperience Tags: Adventure & Outdoor, Marine & Snorkeling, Cultural Heritage, Resort Stay, Self-Drive Scooter Tour, South Cebu Loop\n\n--- Day-by-Day Itinerary ---\n[{\"day\":1,\"title\":\"Day 1: Coastal Arrival, Scooter Handover & Moalboal Sardine Run\",\"desc\":\"Arrive in Moalboal, receive your Honda Click 125 V2 scooter, check in to Pescadores Suites, and snorkel alongside millions of sardines and sea turtles at Panagsama Beach.\",\"schedule\":[{\"time\":\"01:00 PM\",\"activity\":\"Resort check-in at Pescadores Suites & Honda Click V2 handover with safety briefing\"},{\"time\":\"03:00 PM\",\"activity\":\"Guided marine sanctuary snorkeling session with the Moalboal sardine run and sea turtles\"},{\"time\":\"05:30 PM\",\"activity\":\"Golden hour sunset viewing along the Panagsama coastal reef strip\"},{\"time\":\"07:00 PM\",\"activity\":\"Leisure dinner at local seaside restaurants\"}],\"highlights\":[\"Pick up your Honda Click 125 V2 automatic scooter with two safety helmets\",\"Check in to Pescadores Suites Moalboal and settle into your room\",\"Guided snorkeling along Panagsama reef wall among schooling sardines and sea turtles\",\"Sunset stroll and casual dining along the vibrant Panagsama coastal strip\"]},{\"day\":2,\"title\":\"Day 2: Full Kawasan Falls Canyoneering Adventure\",\"desc\":\"Ride your scooter to Badian for an adrenaline-filled canyoning trek through Kanlaob River, ending with a hot recovery meal at Kawasan Falls.\",\"schedule\":[{\"time\":\"07:30 AM\",\"activity\":\"Complimentary resort breakfast at Pescadores Suites\"},{\"time\":\"08:30 AM\",\"activity\":\"Scenic coastal scooter ride to the Badian canyoneering base camp\"},{\"time\":\"09:30 AM\",\"activity\":\"Gear fitting, safety orientation, and jump-off river trek through Kanlaob River\"},{\"time\":\"01:30 PM\",\"activity\":\"Arrival at Kawasan Falls main cascade and post-adventure hot recovery lunch\"},{\"time\":\"03:30 PM\",\"activity\":\"Return ride to Moalboal for poolside relaxation and evening leisure\"}],\"highlights\":[\"Hearty morning breakfast at Pescadores Suites\",\"Scenic morning scooter ride through southern coastal highways to Badian\",\"Downstream cliff jumping (10–30 ft), natural water slides, and river trekking\",\"Swim under the turquoise waters of Kawasan Falls\",\"Hearty Filipino recovery lunch included at the base camp\"]},{\"day\":3,\"title\":\"Day 3: Coastal Cruise & Departure\",\"desc\":\"Journey to Oslob for an outrigger boat trip to Sumilon Island’s white sandbar, followed by a visit to the castle-like Simala Shrine in Sibonga.\",\"schedule\":[{\"time\":\"06:00 AM\",\"activity\":\"Early checkout from Pescadores Suites and coastal ride to Oslob Bancogon port\"},{\"time\":\"08:00 AM\",\"activity\":\"Motorized banca crossing and swimming at Sumilon Island shifting sandbar\"},{\"time\":\"11:30 AM\",\"activity\":\"Return to mainland and scenic coastal ride north toward Sibonga\"},{\"time\":\"01:30 PM\",\"activity\":\"Visit and prayer walk through the castle architecture of Simala Shrine\"},{\"time\":\"04:30 PM\",\"activity\":\"Scooter return and trip conclusion\"}],\"highlights\":[\"Scenic cross-island morning ride along the southern Cebu coastline\",\"Outrigger boat excursion to the crystal-clear waters of Sumilon Island sandbar\",\"Sightseeing and photo opportunities at the Gothic castle monastery of Simala Shrine\",\"Smooth scooter inspection and handover at the final jump-off drop point\"]}]\n\n--- Policies & Requirements ---\nValid Government ID / Passport for all guests.\r\nValid Driver’s License for the scooter rider.\r\nModerate physical fitness for canyoneering (ages 7+).\r\nModest attire for Simala Shrine (covered shoulders and knees).\r\nMarine rules: use reef-safe sunscreen and do not touch corals or turtles.', 'Moalboal, Badian, Oslob & Sibonga, South Cebu', 'Panagsama Beach, Moalboal or South Bus Terminal, Cebu City', 10.1533665, 123.7004416, 11610.00, 'package', '3 Days / 2 Nights', 7, 'Activities: Kawasan Falls Canyoneering Adventure, Moalboal Sardine Run and Marine Sanctuary Snorkeling Tour, Simala Shrine Marian Castle Monastery, Sumilon Island Sandbar & Marine Reserve\nAccommodation: Moalboal Beach Haven Resort (Deluxe Poolside Queen Room) (2 Nights)\nTransportation: 2022 Honda Click 125i V2 Automatic (3 Days)\n2 Nights Accommodation at Pescadores Suites Moalboal with daily breakfast\r\nFull Kawasan Falls Canyoneering fees, safety gear (helmet, life vest), and recovery hot lunch\r\nDOT-Accredited Canyoneering Guide & Snorkeling Guide\r\nMoalboal Panagsama Marine Sanctuary entrance fee and snorkel gear set\r\nRoundtrip motorized outrigger boat transfer to Sumilon Island Sandbar & marine park fee\r\nSimala Shrine admission and pilgrim registration\r\n3-Day Honda Click 125 V2 Scooter Rental with 2 standard safety helmets\r\nAll municipal tourism, environmental, and barangay ecological permits\n<!-- PACKAGE_SERVICES: {\"activity_ids\":[1193,1194,1203,1204],\"hotel_room_id\":101,\"vehicle_id\":72} -->', 'Airfare to and from Mactan-Cebu International Airport (CEB)\r\nFuel/gasoline refills for the Honda Click 125 V2 scooter\r\nOptional Kawasan Falls zipline upgrade to the first jump point (₱600)\r\nBluewater Sumilon Resort amenities pass (pool, lagoon kayaking & buffet)\r\nPersonal underwater action camera / Go-Pro rentals\r\nMeals, alcoholic beverages, and snacks outside the stated inclusions\r\nSimala Shrine souvenir candles and personal donations\r\nTips and gratuities for local boatmen and guides', 'uploads/listings/3/0ecee6c744ff5fc8.jpg', 'approved', NULL, '2026-11-06 14:50:41', '2026-11-06 14:51:24', 1, 0, NULL, NULL, '2026-11-06 14:50:41', '2026-11-06 14:51:24');

-- --------------------------------------------------------

--
-- Table structure for table `listing_images`
--

CREATE TABLE `listing_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `listing_images`
--

INSERT INTO `listing_images` (`id`, `listing_id`, `image_path`, `sort_order`) VALUES
(189, 1193, 'uploads/listings/2466/e3116b9c4deb050f.png', 1),
(190, 1193, 'uploads/listings/2466/d819acf50b77185f.png', 2),
(191, 1193, 'uploads/listings/2466/a04f50acd31c181c.jpg', 3),
(192, 1194, 'uploads/listings/2466/f0ed87fdbf914b70.webp', 1),
(193, 1194, 'uploads/listings/2466/a2c6364bdbb64a79.jpg', 2),
(194, 1194, 'uploads/listings/2466/9675370b0b3ca3b3.webp', 3),
(195, 1195, 'uploads/listings/2466/d02e08a665798129.webp', 1),
(196, 1195, 'uploads/listings/2466/f1173960480cc88d.webp', 2),
(197, 1196, 'uploads/listings/2466/d0009dd2bbe959ee.jpg', 1),
(198, 1196, 'uploads/listings/2466/ee8f7141d750e31f.jpg', 2),
(199, 1197, 'uploads/listings/2466/05555746ae808201.jpg', 1),
(200, 1197, 'uploads/listings/2466/299d63c0d46a0a59.jpg', 2),
(201, 1197, 'uploads/listings/2466/7e1c5177cfee5511.jpg', 3),
(203, 1199, 'uploads/listings/2465/e1459b9fce77039a.webp', 1),
(204, 1199, 'uploads/listings/2465/79291928441ed959.jpg', 2),
(205, 1199, 'uploads/listings/2465/232efca34e52bb76.webp', 3),
(206, 1200, 'uploads/listings/2465/23622742fe0bed76.jpg', 1),
(207, 1200, 'uploads/listings/2465/523035741350b256.jpg', 2),
(208, 1200, 'uploads/listings/2465/ce6a928fe525aed4.jpg', 3),
(209, 1201, 'uploads/listings/2465/589db446b913f2b1.jpg', 1),
(210, 1201, 'uploads/listings/2465/203a49644ab3a12e.jpg', 2),
(211, 1201, 'uploads/listings/2465/25cfaad70e597e38.webp', 3),
(212, 1202, 'uploads/listings/2465/9559f7373c5885d2.jpg', 1),
(213, 1202, 'uploads/listings/2465/da45d11b3d39264e.jpg', 2),
(214, 1202, 'uploads/listings/2465/3e1e148edf4735bb.jpg', 3),
(215, 1203, 'uploads/listings/2465/6915316973c1ebc6.jpg', 1),
(216, 1203, 'uploads/listings/2465/7b0b0bb47349c28d.webp', 2),
(217, 1203, 'uploads/listings/2465/5a399f9ae440dd32.jpg', 3),
(218, 1204, 'uploads/listings/2465/ac42c6aef2ee1b2f.jpg', 1),
(219, 1204, 'uploads/listings/2465/ef0cadc2a785c89a.jpg', 2),
(220, 1198, 'uploads/listings/2466/e010f1728aaf6d6e.jpg', 1),
(221, 1198, 'uploads/listings/2466/eb14929bd61ff1f0.jpg', 2),
(222, 1198, 'uploads/listings/2466/7ab3da244be02a6f.jpg', 3),
(223, 1205, 'uploads/listings/3/3153d8a1c481dfff.jpg', 1),
(224, 1205, 'uploads/listings/3/2282db01ba517acf.jpg', 2),
(225, 1205, 'uploads/listings/3/5024c1ce7d93671c.webp', 3),
(226, 1206, 'uploads/listings/3/99eb4b8df32a377b.jpg', 1),
(227, 1206, 'uploads/listings/3/d2a2df4c7323aeb3.jpg', 2),
(228, 1206, 'uploads/listings/3/e0b93ccb37a03b6c.jpg', 3),
(229, 1207, 'uploads/listings/3/f2ebb8bb06095cbc.jpg', 1),
(230, 1207, 'uploads/listings/3/f7cb680548ae2284.jpg', 2),
(231, 1207, 'uploads/listings/3/da777da9bd199c6a.jpg', 3),
(232, 1208, 'uploads/listings/3/a0ceda8aecd85b67.webp', 1),
(233, 1208, 'uploads/listings/3/9bd852d252726016.jpg', 2),
(234, 1208, 'uploads/listings/3/deb1fd503c0b9d58.jpg', 3),
(235, 1209, 'uploads/listings/3/6950bd6354b95c93.jpeg', 1),
(236, 1209, 'uploads/listings/3/d9f6ec541e967829.webp', 2),
(237, 1209, 'uploads/listings/3/870ab3876ea48e18.jpg', 3),
(238, 1210, 'uploads/listings/3/c5c573f0b5d716cc.jpg', 1),
(239, 1210, 'uploads/listings/3/ac01148cd4e52cad.jpg', 2),
(240, 1210, 'uploads/listings/3/4ff9693a44150460.jpg', 3),
(241, 1211, 'uploads/listings/2469/96c174c4d98d6d28.jpg', 1),
(242, 1211, 'uploads/listings/2469/d943b0e70cf522bf.jpg', 2),
(243, 1211, 'uploads/listings/2469/56ddd1915059cf6c.jpg', 3),
(244, 1212, 'uploads/listings/3/0ecee6c744ff5fc8.jpg', 1),
(245, 1212, 'uploads/listings/3/9cb3a91507ebf16c.jpg', 2),
(246, 1212, 'uploads/listings/3/8dfa90b0fbfb5698.webp', 3);

-- --------------------------------------------------------

--
-- Table structure for table `listing_schedules`
--

CREATE TABLE `listing_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` varchar(5) DEFAULT NULL,
  `end_time` varchar(5) DEFAULT NULL,
  `capacity` int(10) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `receiver_id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `source_lang` varchar(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `listing_id`, `body`, `is_read`, `created_at`, `source_lang`) VALUES
(1, 2, 3, NULL, 'hello', 1, '2026-06-29 22:08:39', NULL),
(2, 3, 2, NULL, 'hi', 1, '2026-06-29 22:09:41', 'en'),
(3, 3, 2, NULL, 'hii po', 1, '2026-06-30 06:21:51', 'pl'),
(4, 2, 3, NULL, 'boang', 1, '2026-06-30 06:22:04', 'ceb'),
(5, 3, 2, NULL, 'pretty kay ka', 1, '2026-06-30 06:22:07', 'tl'),
(6, 2, 3, NULL, 'bayot ampota', 1, '2026-06-30 06:34:32', 'tl'),
(7, 2, 3, NULL, 'hi', 1, '2026-07-08 19:14:58', NULL),
(8, 2, 3, NULL, 'lami na', 1, '2026-07-08 19:17:40', 'sw'),
(9, 2, 3, NULL, 'where are u', 1, '2026-07-08 19:17:44', NULL),
(12, 1, 18, NULL, 'Administrator Warning\n\nYou have received an official warning from the GuideMate Administrator.\n\nReason: Warning issued after dispute #2.\n\nYour booking actions (Confirm / Complete / Cancel) are restricted until an administrator clears this warning. Please review this message and wait for admin follow-up.', 1, '2026-09-28 06:05:54', NULL),
(14, 2, 18, NULL, 'hello is this available?', 1, '2026-09-29 05:07:45', NULL),
(15, 1, 3, NULL, 'Administrator Warning\n\nYou have received an official warning from the GuideMate Administrator.\n\nReason: Warning issued after dispute #4.\n\nYour booking actions (Confirm / Complete / Cancel) are restricted until an administrator clears this warning. Please review this message and wait for admin follow-up.', 1, '2026-09-29 05:28:14', NULL),
(16, 3, 1, NULL, 'Okay, noted for this.', 1, '2026-09-29 05:28:54', NULL),
(17, 1, 3, NULL, 'Administrator Notice\n\nYour account has been reviewed and unrestricted by the Administrator. You may Confirm, Complete, and Cancel bookings again.', 1, '2026-09-29 07:04:32', NULL),
(18, 1, 18, NULL, 'Administrator Notice\n\nYour account has been reviewed and unrestricted by the Administrator. You may Confirm, Complete, and Cancel bookings again.', 1, '2026-09-29 07:04:35', NULL),
(19, 1, 12, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 1, '2026-09-29 11:36:42', NULL),
(59, 1, 167, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 1, '2026-10-20 04:42:57', NULL),
(130, 18, 1, NULL, 'fdfdfdf', 1, '2026-10-23 05:43:02', NULL),
(137, 18, 1, NULL, 'fsdd', 1, '2026-10-23 06:00:20', NULL),
(138, 18, 1, NULL, 'fhdhdh', 1, '2026-10-23 06:00:24', NULL),
(139, 1, 3, NULL, 'Short greeting: Hello partner!', 1, '2026-10-23 06:20:23', NULL),
(140, 1, 3, NULL, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 1, '2026-10-23 06:20:23', 'la'),
(141, 1, 3, NULL, 'Line 1: Tour guide policy reminder\nLine 2: Terms and conditions\nLine 3: Thank you.', 1, '2026-10-23 06:20:23', NULL),
(143, 1, 3, NULL, 'Short greeting: Hello partner!', 1, '2026-10-23 07:06:05', NULL),
(144, 1, 3, NULL, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 1, '2026-10-23 07:06:05', 'la'),
(145, 1, 3, NULL, 'Line 1: Tour guide policy reminder\nLine 2: Terms and conditions\nLine 3: Thank you.', 1, '2026-10-23 07:06:05', NULL),
(147, 1, 3, NULL, 'Short greeting: Hello partner!', 1, '2026-10-25 19:32:06', NULL),
(148, 1, 3, NULL, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 1, '2026-10-25 19:32:06', 'la'),
(149, 1, 3, NULL, 'Line 1: Tour guide policy reminder\nLine 2: Terms and conditions\nLine 3: Thank you.', 1, '2026-10-25 19:32:06', NULL),
(158, 1, 3, NULL, 'Short greeting: Hello partner!', 1, '2026-10-30 23:39:22', NULL),
(159, 1, 3, NULL, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 1, '2026-10-30 23:39:22', 'la'),
(160, 1, 3, NULL, 'Line 1: Tour guide policy reminder\nLine 2: Terms and conditions\nLine 3: Thank you.', 1, '2026-10-30 23:39:22', NULL),
(165, 1, 18, NULL, 'Administrator Warning\n\nYou have received an official warning from the GuideMate Administrator.\n\nReason: Warning issued after dispute #8.\n\nYour booking actions (Confirm / Complete / Cancel) are restricted until an administrator clears this warning. Please review this message and wait for admin follow-up.', 1, '2026-10-31 11:46:45', NULL),
(166, 1, 18, NULL, 'Administrator Notice\n\nYour account has been reviewed and unrestricted by the Administrator. You may Confirm, Complete, and Cancel bookings again.', 1, '2026-11-01 02:13:28', NULL),
(167, 1, 18, NULL, 'Administrator Warning\n\nYou have received an official warning from the GuideMate Administrator.\n\nReason: test test test\n\nYour booking actions (Confirm / Complete / Cancel) are restricted until an administrator clears this warning. Please review this message and wait for admin follow-up.', 1, '2026-11-01 02:22:26', NULL),
(168, 1, 18, NULL, 'Administrator Notice\n\nYour account has been reviewed and unrestricted by the Administrator. You may Confirm, Complete, and Cancel bookings again.', 1, '2026-11-01 03:53:57', NULL),
(208, 1, 2466, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 1, '2026-11-06 11:04:40', NULL),
(209, 1, 2465, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 0, '2026-11-06 11:04:42', NULL),
(210, 1, 2469, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 0, '2026-11-06 14:18:43', NULL),
(211, 1, 2468, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 0, '2026-11-06 14:18:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `message_translations`
--

CREATE TABLE `message_translations` (
  `id` int(10) UNSIGNED NOT NULL,
  `message_id` int(10) UNSIGNED NOT NULL,
  `target_lang` varchar(8) NOT NULL,
  `body` text NOT NULL,
  `source_lang` varchar(8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `message_translations`
--

INSERT INTO `message_translations` (`id`, `message_id`, `target_lang`, `body`, `source_lang`, `created_at`) VALUES
(2, 3, 'en', 'hi after', 'pl', '2026-09-29 04:22:16'),
(3, 5, 'en', 'you are pretty', 'tl', '2026-09-29 04:22:19'),
(55, 4, 'en', 'crazy', 'ceb', '2026-10-21 08:50:39'),
(56, 6, 'en', 'gay ampota', 'tl', '2026-10-21 08:50:39'),
(57, 8, 'en', 'pitch and', 'sw', '2026-10-21 08:50:40'),
(77, 140, 'en', 'The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 'la', '2026-10-24 08:56:53'),
(78, 144, 'en', 'The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 'la', '2026-10-24 08:56:54'),
(82, 148, 'en', 'The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 'la', '2026-10-25 19:35:56'),
(88, 159, 'en', 'The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. The customer is very important, the customer will be followed by the customer. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 'la', '2026-10-31 01:16:11');

-- --------------------------------------------------------

--
-- Table structure for table `package_booking_items`
--

CREATE TABLE `package_booking_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_booking_id` int(10) UNSIGNED NOT NULL,
  `package_id` int(10) UNSIGNED NOT NULL,
  `provider_id` int(10) UNSIGNED NOT NULL,
  `service_type` enum('hotel','vehicle') NOT NULL,
  `hotel_booking_id` int(10) UNSIGNED DEFAULT NULL,
  `rental_request_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `package_itineraries`
--

CREATE TABLE `package_itineraries` (
  `id` int(10) UNSIGNED NOT NULL,
  `package_id` int(10) UNSIGNED NOT NULL,
  `day_number` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `highlights` text DEFAULT NULL,
  `schedule` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `package_services`
--

CREATE TABLE `package_services` (
  `id` int(10) UNSIGNED NOT NULL,
  `package_id` int(10) UNSIGNED NOT NULL,
  `provider_id` int(10) UNSIGNED NOT NULL,
  `service_type` enum('hotel','vehicle','activity','attraction') NOT NULL,
  `hotel_room_id` int(10) UNSIGNED DEFAULT NULL,
  `vehicle_id` int(10) UNSIGNED DEFAULT NULL,
  `listing_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `agreed_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(190) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`email`, `token`, `expires_at`, `created_at`, `used_at`) VALUES
('christianyongzon143@gmail.com', '77f8fb53ecbcd2f501ff31ebadf6eecfe5041a46c44b3195068a223749e7edc3', '2026-10-14 17:40:39', '2026-10-14 17:10:39', NULL),
('chrisyongzon112@gmail.com', 'fea7c66fa4490f7bc7f6c7ad5ef81a57afdfc958d0399fb28c6564821e00bc02', '2026-10-14 17:32:42', '2026-10-14 17:02:42', NULL),
('guide.moalboal@guidemate.local', 'f791b873f28dbc5eefd1cbf7d044ceda06204c76d627edb46eb56032717e7511', '2026-10-14 16:41:20', '2026-10-14 16:11:20', NULL),
('jalelmauc@gmail.com', 'c410f337583f6c3d7142c44a4ff2cdbeef7efaa3679e63cae128a332f1efb169', '2026-07-06 18:25:37', '2026-10-14 15:00:35', NULL),
('johndoe@sample.com', '4fa614d7200d4d6c9ed3f55dae4aacfd0792cf770a3781a55d8fb1d94629615b', '2026-10-14 18:11:27', '2026-10-14 17:41:27', NULL),
('marwadmm648@gmail.com', 'c6a09ee10728f73326b8966bf3da10ec14721a24d46e09e33c8d45be6b147af9', '2026-10-14 15:43:36', '2026-10-14 15:00:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(40) NOT NULL DEFAULT 'card',
  `status` enum('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
  `reference` varchar(64) NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promo_codes`
--

CREATE TABLE `promo_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `max_uses` int(10) UNSIGNED DEFAULT NULL,
  `uses_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `min_spend` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promo_codes`
--

INSERT INTO `promo_codes` (`id`, `code`, `discount_percent`, `discount_amount`, `valid_from`, `valid_until`, `max_uses`, `uses_count`, `is_active`, `created_at`, `min_spend`) VALUES
(1, 'CEBU6', 6.00, NULL, NULL, '2026-12-31', NULL, 0, 1, '2026-09-29 08:29:12', 8000.00),
(2, 'ISLAND300', NULL, 300.00, NULL, '2026-09-30', NULL, 0, 1, '2026-09-29 08:29:12', 3000.00),
(3, 'STAY10', 10.00, NULL, NULL, '2026-11-15', NULL, 0, 1, '2026-09-29 08:29:12', 5000.00);

-- --------------------------------------------------------

--
-- Table structure for table `rental_report_files`
--

CREATE TABLE `rental_report_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `rental_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rental_requests`
--

CREATE TABLE `rental_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
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
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `refund_status` varchar(50) DEFAULT NULL,
  `cancellation_policy` varchar(20) DEFAULT '24h',
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
  `owner_report_note` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rental_requests`
--

INSERT INTO `rental_requests` (`id`, `user_id`, `vehicle_id`, `vehicle_name`, `vehicle_type`, `shop_name`, `location`, `pickup_date`, `rental_days`, `total_amount`, `customer_name`, `customer_email`, `customer_phone`, `notes`, `status`, `admin_note`, `contacted_at`, `created_at`, `updated_at`, `cancelled_at`, `cancellation_reason`, `refund_amount`, `refund_status`, `cancellation_policy`, `payment_status`, `payment_method`, `payment_reference`, `paid_at`, `id_document`, `id_type`, `id_number`, `report_type`, `report_message`, `report_status`, `report_created_at`, `owner_report_note`) VALUES
(1, 2, 'scooter', 'Honda Click 125i', 'Scooter', 'Cebu Moto Rentals', 'Cebu City · Fuente Osmeña', '2026-07-01', 1, 500.00, 'Jalel Mauc', 'jalelmauc@gmail.com', '09537330643', '9:00AM NO DRIVER', 'approved', NULL, '2026-07-07 06:45:38', '2026-06-30 05:34:59', '2026-07-07 06:45:38', NULL, NULL, 0.00, NULL, '24h', 'unpaid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'none', NULL, NULL),
(2, 6, 'motorcycle', 'KTM 390 Adventure', 'Motorcycle', 'ADV Riders Cebu', 'Mandaue City · A.S. Fortuna', '2026-07-01', 1, 1200.00, 'Felix Baquerosa', 'felixbruce920@gmail.com', '0912345697', NULL, 'cancelled', NULL, NULL, '2026-06-30 06:27:18', '2026-07-07 06:45:33', NULL, NULL, 0.00, NULL, '24h', 'unpaid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'none', NULL, NULL),
(3, 2, 'scooter', 'Honda Click 125i', 'Scooter', 'Cebu Moto Rentals', 'Cebu City · Fuente Osmeña', '2026-09-30', 2, 1000.00, 'Jalel Mauc', 'jalelmauc@gmail.com', '09537330643', 'Pickup time: 11:00 · No need for drivers', 'approved', NULL, NULL, '2026-09-29 07:26:19', '2026-09-29 07:26:19', NULL, NULL, 0.00, NULL, '24h', 'paid', 'gcash', '029392020302', '2026-09-29 07:26:19', 'uploads/rentals/ids/2/090c4babcc1c188f.jpeg', 'Driver\'s License', '929392920203', NULL, NULL, 'none', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rental_reviews`
--

CREATE TABLE `rental_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `rental_id` int(10) UNSIGNED NOT NULL,
  `reviewer_id` int(10) UNSIGNED NOT NULL,
  `provider_id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` varchar(50) DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `feedback` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `provider_id` int(10) UNSIGNED DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `unit_rating` tinyint(3) UNSIGNED DEFAULT NULL,
  `title` varchar(160) DEFAULT NULL,
  `comment` text NOT NULL,
  `unit_comment` text DEFAULT NULL,
  `provider_rating` tinyint(3) UNSIGNED DEFAULT NULL,
  `provider_comment` text DEFAULT NULL,
  `visited_on` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review_images`
--

CREATE TABLE `review_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `review_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guide','tourist','rental_provider','hotel_provider') NOT NULL DEFAULT 'tourist',
  `phone` varchar(40) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `suspension_reason` varchar(500) DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `guide_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_requests` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `guide_review_note` varchar(500) DEFAULT NULL,
  `guide_reviewed_at` timestamp NULL DEFAULT NULL,
  `guide_warned` tinyint(1) NOT NULL DEFAULT 0,
  `guide_warning_note` varchar(500) DEFAULT NULL,
  `admin_totp_secret` varchar(64) DEFAULT NULL,
  `admin_totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `oauth_provider` varchar(20) DEFAULT NULL,
  `oauth_id` varchar(191) DEFAULT NULL,
  `login_count` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `avatar`, `bio`, `location`, `is_active`, `suspension_reason`, `suspended_at`, `guide_status`, `revenue`, `total_requests`, `guide_review_note`, `guide_reviewed_at`, `guide_warned`, `guide_warning_note`, `admin_totp_secret`, `admin_totp_enabled`, `created_at`, `updated_at`, `last_seen_at`, `last_login_at`, `oauth_provider`, `oauth_id`, `login_count`) VALUES
(1, 'Administrator', 'fbaquerosa@gmail.com', '$2y$10$fWMGmvoZB7Ll9C.emzfPjeiUxcePTSYQiSqOxViUCWEIPJtqGEZJa', 'admin', NULL, NULL, 'Platform administrator.', 'Cebu City', 1, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, 'N4BXKWOIT2MEMUYD', 0, '2026-06-15 11:51:31', '2026-11-01 07:22:11', '2026-11-01 07:22:11', NULL, NULL, NULL, 0),
(2, 'Jalel Mauc', 'jalelmauc@gmail.com', '$2y$10$VWE9XjTCU1cch9Q8daRguuNBGBn3prrwsJXNfpMk83GYF9Im1ZV0G', 'tourist', NULL, 'uploads/avatars/2/f5800b8ea0de67da.jpeg', NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-06-15 12:15:47', '2026-10-14 14:42:01', '2026-09-29 09:15:30', NULL, NULL, NULL, 0),
(3, 'Marc Repuesto', 'guide.moalboal@guidemate.local', '$2y$10$8kopCDTNm6m3GZ5u/ahiR.GcEvuYEWhdAJgN0J5z00xveI5FyQxQ6', 'guide', NULL, 'uploads/avatars/3/1665bce1d7903665.jpg', 'Local guide for snorkeling tours and dining around Moalboal.', 'Moalboal, Cebu', 1, NULL, NULL, 'approved', 0.00, 0, '[Warning 2026-09-17] Dispute #1: refund issued to tourist.\n[Warning 2026-09-28] Dispute #3: refund issued to tourist.\n', NULL, 0, NULL, NULL, 0, '2026-06-15 12:27:23', '2026-11-06 14:30:54', '2026-11-06 14:30:54', '2026-11-06 14:30:54', NULL, NULL, 50),
(6, 'Felix Baquerosa', 'felixbruce920@gmail.com', '$2y$10$tU4jxJbTXUf.kEsjkWGwzeTt.egYSbBSwkhvFGVSDnaiJWOSVrlyW', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-06-29 21:21:42', '2026-07-07 06:40:20', '2026-06-30 06:28:52', NULL, NULL, NULL, 0),
(12, 'Jane De Leo', 'janethmauir@gmail.com', '$2y$10$ZDBVp/Ta4c3J3REK/uaXXOI2xkPlaCU56A00E6exFKNRBwpqsry5u', 'rental_provider', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 0.00, 0, NULL, '2026-09-29 11:36:42', 0, NULL, NULL, 0, '2026-09-16 18:24:44', '2026-10-19 18:18:03', '2026-10-18 17:09:51', '2026-10-18 17:09:51', NULL, NULL, 2),
(13, 'Google User', 'google_ad219a2e8778b055604e@social.guidemate.local', '$2y$10$PvGJg82k.5cGDhyOMEuZV.bxbP5FC4o49HpYmtdgC7X765iBfcPBq', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 19:23:02', '2026-09-29 09:14:53', '2026-09-16 19:26:57', NULL, 'google', 'demo_google-bee4fc1e-6038-4d4c-b345-923313014eb0', 0),
(14, 'Facebook User', 'facebook_2b99f4d0c198ed50e627@social.guidemate.local', '$2y$10$AZrgvytfyLnxFfytEcCmX.hMJgGWEv5g1pL/ZXIK4IflUVROnGTYK', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 19:23:15', '2026-09-29 09:14:51', '2026-09-27 13:37:50', NULL, 'facebook', 'demo_facebook-48a92cad-2f85-4635-8686-1bb37ad87ff1', 0),
(15, 'Jiji Plays', 'google_5d8acb71805db42e30ee@social.guidemate.local', '$2y$10$X5fIuCwd77MPxE3NxjjzWOvdoF7y7YxinZl2BGtk8CCpqZ4T5gm/C', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 20:09:40', '2026-09-29 09:14:49', '2026-09-16 21:18:04', NULL, 'google', '100827659670400175015', 0),
(16, 'Janeth Mauir', 'google_c50d84dbf58163b7f465@social.guidemate.local', '$2y$10$U4J505ac9y8Vj5HGWIximuNeFd/9SDbIsGM0EeQKqRQ2w.KLPnffu', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 20:25:54', '2026-09-29 09:14:43', '2026-09-27 14:23:56', NULL, 'google', '101669896341527826136', 0),
(17, 'Google User', 'google_a03ad608fa0ae6a9640b@social.guidemate.local', '$2y$10$CGRyWQpKEGtAPhJU31BT5e3/8XJPg2RMvITCzP91jnpFQK9Z5Z0Oa', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 20:28:29', '2026-09-29 09:14:47', '2026-09-16 21:07:47', NULL, 'google', '101982035606372835617', 0),
(18, 'Dray Misa', 'chrisyongzon112@gmail.com', '$2y$10$9Ccj2ig8vtbp84APeffXN.6UseHrq2.lVXOLr6whZgp4wmCihKrBG', 'hotel_provider', '', NULL, 'Verified accommodation partner on GuideMate.', 'Cebu, Philippines', 1, NULL, NULL, 'approved', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-27 14:46:37', '2026-11-06 14:14:29', NULL, '2026-11-06 14:14:21', NULL, NULL, 39),
(167, 'Miguel Yongzon', 'migyong@sample.com', '$2y$10$uabG9oJ9LICqWNaD8xoQAuzqD4ya4dNcRwR3mnutHLYtu78NynWAO', 'rental_provider', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 4010.00, 5, NULL, '2026-10-20 04:42:52', 0, NULL, NULL, 0, '2026-10-20 04:42:11', '2026-11-06 12:52:27', NULL, '2026-11-06 12:45:09', NULL, NULL, 18),
(2465, 'John Doe', 'jdoe@gmail.com', '$2y$10$hWpz770ETILp1attRRzN6..l2xcLBCVliXLUD8cL7qfl89rIK3j/6', 'guide', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 0.00, 0, NULL, '2026-11-06 11:04:42', 0, NULL, NULL, 0, '2026-11-06 11:02:47', '2026-11-06 13:42:38', NULL, '2026-11-06 13:12:26', NULL, NULL, 2),
(2466, 'John Lloyd Noya', 'johnlloyd@gmail.com', '$2y$10$oG9VLl9fZukm4hvXOSgKA.VbkGEzbYzxyhcyJa4oY96pvbndiqn06', 'guide', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 0.00, 0, NULL, '2026-11-06 11:04:40', 0, NULL, NULL, 0, '2026-11-06 11:04:25', '2026-11-06 13:46:34', NULL, '2026-11-06 13:42:55', NULL, NULL, 3),
(2467, 'Christian Yongzon', 'christianyongzon143@gmail.com', '$2y$10$28byXmIxD8v6L8xXygu4auttRfF45G0vCnhDN3HVfnAkhCUHwsBqO', 'tourist', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-11-06 12:51:30', '2026-11-06 14:51:34', '2026-11-06 14:51:34', NULL, NULL, NULL, 0),
(2468, 'Kalix Zang', 'kzang@gmail.com', '$2y$10$jP0JijJgwTJGl6wf9nFkGe1b.yNmRy1PcWqz1mUZIDvbIf8zc5CDW', 'hotel_provider', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 0.00, 0, NULL, '2026-11-06 14:18:45', 0, NULL, NULL, 0, '2026-11-06 14:15:48', '2026-11-06 14:18:45', NULL, '2026-11-06 14:15:48', NULL, NULL, 1),
(2469, 'Stephanie Jane', 'stepjane@gmail.com', '$2y$10$IBF.I8R1bchsJfXhhIU7VO6YrtwZUnBeuBCRYQ8FW/51zvsvcjPbS', 'hotel_provider', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 0.00, 0, NULL, '2026-11-06 14:18:43', 0, NULL, NULL, 0, '2026-11-06 14:18:15', '2026-11-06 14:30:35', NULL, '2026-11-06 14:18:15', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `make` varchar(80) NOT NULL,
  `model` varchar(80) NOT NULL,
  `year` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `name` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `specs` text DEFAULT NULL,
  `location` varchar(160) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `price_per_day` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','unavailable') NOT NULL DEFAULT 'pending',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `user_id`, `make`, `model`, `year`, `type`, `name`, `slug`, `description`, `specs`, `location`, `address`, `price_per_day`, `image`, `status`, `is_available`, `rejection_reason`, `submitted_at`, `reviewed_at`, `reviewed_by`, `created_at`, `updated_at`) VALUES
(72, 167, 'Honda', 'Click 125i V2', 2022, 'Scooter', '2022 Honda Click 125i V2 Automatic', '2022-honda-click-125i-v2-automatic', 'The vehicle is in excellent overall condition, both inside and out. The exterior body is free of any dents, scratches, or visible damage. Everything is fully functional and ready to go.', '[\"Automatic Transmission\"]', 'Minglanilla, Cebu', 'Purok Guyabano, Kilid Hopian', 1000.00, 'uploads/vehicles/167/6d69ffd5b4ee70ab.jpg', 'approved', 1, NULL, '2026-10-30 15:50:42', '2026-10-31 00:25:35', 1, '2026-10-20 04:49:14', '2026-10-31 01:14:41');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_images`
--

CREATE TABLE `vehicle_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_images`
--

INSERT INTO `vehicle_images` (`id`, `vehicle_id`, `image_path`, `sort_order`, `created_at`) VALUES
(2, 72, 'uploads/vehicles/167/6d69ffd5b4ee70ab.jpg', 1, '2026-10-20 14:17:28'),
(3, 72, 'uploads/vehicles/167/scooter_profile.jpg', 2, '2026-10-20 14:17:28');

-- --------------------------------------------------------

--
-- Table structure for table `voucher_redemptions`
--

CREATE TABLE `voucher_redemptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_appeals`
--
ALTER TABLE `account_appeals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appeals_user` (`user_id`),
  ADD KEY `idx_appeals_status` (`status`),
  ADD KEY `idx_appeals_created` (`created_at`);

--
-- Indexes for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_api_tokens_token` (`token`),
  ADD KEY `idx_api_tokens_user` (`user_id`);

--
-- Indexes for table `app_feedback`
--
ALTER TABLE `app_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_app_feedback_created` (`created_at`),
  ADD KEY `idx_app_feedback_status` (`status`),
  ADD KEY `fk_app_feedback_user` (`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_logs_admin_fk` (`admin_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bookings_listing_fk` (`listing_id`),
  ADD KEY `bookings_user_fk` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`);

--
-- Indexes for table `conversation_settings`
--
ALTER TABLE `conversation_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conversation_settings_unique` (`user_id`,`partner_id`),
  ADD KEY `conversation_settings_partner_fk` (`partner_id`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `disputes_booking_unique` (`booking_id`),
  ADD KEY `disputes_user_fk` (`user_id`),
  ADD KEY `disputes_guide_fk` (`guide_id`);

--
-- Indexes for table `dispute_files`
--
ALTER TABLE `dispute_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dispute_files_dispute_fk` (`dispute_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `favorites_unique` (`user_id`,`listing_id`),
  ADD KEY `favorites_listing_fk` (`listing_id`);

--
-- Indexes for table `guide_availability`
--
ALTER TABLE `guide_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `guide_availability_unique` (`listing_id`,`blocked_date`);

--
-- Indexes for table `guide_documents`
--
ALTER TABLE `guide_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guide_documents_user_fk` (`user_id`);

--
-- Indexes for table `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hotel_rooms_listing` (`listing_id`),
  ADD KEY `idx_hotel_rooms_status` (`status`);

--
-- Indexes for table `incident_reports`
--
ALTER TABLE `incident_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_number` (`report_number`),
  ADD KEY `idx_incident_category` (`category`),
  ADD KEY `idx_incident_status` (`status`),
  ADD KEY `idx_incident_user` (`user_id`),
  ADD KEY `idx_incident_created` (`created_at`);

--
-- Indexes for table `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `listings_slug_unique` (`slug`),
  ADD KEY `listings_user_fk` (`user_id`),
  ADD KEY `listings_category_fk` (`category_id`),
  ADD KEY `listings_status_idx` (`status`),
  ADD KEY `idx_listings_reviewed_by` (`reviewed_by`);

--
-- Indexes for table `listing_images`
--
ALTER TABLE `listing_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `listing_images_listing_fk` (`listing_id`);

--
-- Indexes for table `listing_schedules`
--
ALTER TABLE `listing_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `listing_schedules_listing_fk` (`listing_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `messages_sender_fk` (`sender_id`),
  ADD KEY `messages_receiver_fk` (`receiver_id`),
  ADD KEY `messages_listing_fk` (`listing_id`);

--
-- Indexes for table `message_translations`
--
ALTER TABLE `message_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `message_translations_unique` (`message_id`,`target_lang`);

--
-- Indexes for table `package_booking_items`
--
ALTER TABLE `package_booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pkg_bk_parent` (`parent_booking_id`),
  ADD KEY `idx_pkg_bk_provider` (`provider_id`);

--
-- Indexes for table `package_itineraries`
--
ALTER TABLE `package_itineraries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pkg_itinerary_day` (`package_id`,`day_number`);

--
-- Indexes for table `package_services`
--
ALTER TABLE `package_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pkg_services_pkg` (`package_id`),
  ADD KEY `idx_pkg_services_provider` (`provider_id`,`status`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`email`),
  ADD KEY `idx_password_resets_token` (`token`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_reference_unique` (`reference`),
  ADD KEY `payments_booking_fk` (`booking_id`);

--
-- Indexes for table `promo_codes`
--
ALTER TABLE `promo_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promo_codes_code_unique` (`code`);

--
-- Indexes for table `rental_report_files`
--
ALTER TABLE `rental_report_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rental_id` (`rental_id`);

--
-- Indexes for table `rental_requests`
--
ALTER TABLE `rental_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rental_requests_user_fk` (`user_id`),
  ADD KEY `rental_requests_status_idx` (`status`);

--
-- Indexes for table `rental_reviews`
--
ALTER TABLE `rental_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rental_review` (`rental_id`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_reviewer_id` (`reviewer_id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking_review` (`booking_id`),
  ADD KEY `reviews_listing_fk` (`listing_id`),
  ADD KEY `reviews_user_fk` (`user_id`),
  ADD KEY `idx_reviews_provider_id` (`provider_id`);

--
-- Indexes for table `review_images`
--
ALTER TABLE `review_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `review_images_review_fk` (`review_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_oauth_unique` (`oauth_provider`,`oauth_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicles_slug_unique` (`slug`),
  ADD KEY `idx_vehicles_user_id` (`user_id`),
  ADD KEY `idx_vehicles_status` (`status`),
  ADD KEY `idx_vehicles_is_available` (`is_available`),
  ADD KEY `idx_vehicles_type` (`type`),
  ADD KEY `fk_vehicles_reviewer` (`reviewed_by`);

--
-- Indexes for table `vehicle_images`
--
ALTER TABLE `vehicle_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vehicle_images_vehicle_id` (`vehicle_id`);

--
-- Indexes for table `voucher_redemptions`
--
ALTER TABLE `voucher_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_redemptions_user_code` (`user_id`,`code`),
  ADD KEY `voucher_redemptions_booking` (`booking_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_appeals`
--
ALTER TABLE `account_appeals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=279;

--
-- AUTO_INCREMENT for table `app_feedback`
--
ALTER TABLE `app_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=278;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=299;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `conversation_settings`
--
ALTER TABLE `conversation_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `dispute_files`
--
ALTER TABLE `dispute_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `guide_availability`
--
ALTER TABLE `guide_availability`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guide_documents`
--
ALTER TABLE `guide_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=487;

--
-- AUTO_INCREMENT for table `incident_reports`
--
ALTER TABLE `incident_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `listings`
--
ALTER TABLE `listings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1213;

--
-- AUTO_INCREMENT for table `listing_images`
--
ALTER TABLE `listing_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=247;

--
-- AUTO_INCREMENT for table `listing_schedules`
--
ALTER TABLE `listing_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;

--
-- AUTO_INCREMENT for table `message_translations`
--
ALTER TABLE `message_translations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `package_booking_items`
--
ALTER TABLE `package_booking_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package_itineraries`
--
ALTER TABLE `package_itineraries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package_services`
--
ALTER TABLE `package_services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=209;

--
-- AUTO_INCREMENT for table `promo_codes`
--
ALTER TABLE `promo_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=193;

--
-- AUTO_INCREMENT for table `rental_report_files`
--
ALTER TABLE `rental_report_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `rental_requests`
--
ALTER TABLE `rental_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `rental_reviews`
--
ALTER TABLE `rental_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `review_images`
--
ALTER TABLE `review_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2470;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=308;

--
-- AUTO_INCREMENT for table `vehicle_images`
--
ALTER TABLE `vehicle_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `voucher_redemptions`
--
ALTER TABLE `voucher_redemptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_appeals`
--
ALTER TABLE `account_appeals`
  ADD CONSTRAINT `fk_appeals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `app_feedback`
--
ALTER TABLE `app_feedback`
  ADD CONSTRAINT `fk_app_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conversation_settings`
--
ALTER TABLE `conversation_settings`
  ADD CONSTRAINT `conversation_settings_partner_fk` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversation_settings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `disputes_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disputes_guide_fk` FOREIGN KEY (`guide_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disputes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dispute_files`
--
ALTER TABLE `dispute_files`
  ADD CONSTRAINT `dispute_files_dispute_fk` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `guide_availability`
--
ALTER TABLE `guide_availability`
  ADD CONSTRAINT `guide_availability_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `guide_documents`
--
ALTER TABLE `guide_documents`
  ADD CONSTRAINT `guide_documents_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `listings`
--
ALTER TABLE `listings`
  ADD CONSTRAINT `listings_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `listings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `listing_images`
--
ALTER TABLE `listing_images`
  ADD CONSTRAINT `listing_images_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `listing_schedules`
--
ALTER TABLE `listing_schedules`
  ADD CONSTRAINT `listing_schedules_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `messages_receiver_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_translations`
--
ALTER TABLE `message_translations`
  ADD CONSTRAINT `message_translations_message_fk` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `package_itineraries`
--
ALTER TABLE `package_itineraries`
  ADD CONSTRAINT `fk_pkg_itin_package` FOREIGN KEY (`package_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `package_services`
--
ALTER TABLE `package_services`
  ADD CONSTRAINT `fk_pkg_serv_package` FOREIGN KEY (`package_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pkg_serv_provider` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_report_files`
--
ALTER TABLE `rental_report_files`
  ADD CONSTRAINT `fk_rental_report_files_rental` FOREIGN KEY (`rental_id`) REFERENCES `rental_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_requests`
--
ALTER TABLE `rental_requests`
  ADD CONSTRAINT `rental_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_reviews`
--
ALTER TABLE `rental_reviews`
  ADD CONSTRAINT `fk_rental_reviews_provider` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rental_reviews_rental` FOREIGN KEY (`rental_id`) REFERENCES `rental_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rental_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `review_images`
--
ALTER TABLE `review_images`
  ADD CONSTRAINT `review_images_review_fk` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vehicles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_images`
--
ALTER TABLE `vehicle_images`
  ADD CONSTRAINT `fk_vehicle_images_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
