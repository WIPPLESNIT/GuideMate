-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 23, 2026 at 09:41 AM
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
(4, 23, 'John Doe', 'johndoe@sample.com', 'have a violation in our privacy', 'wala man koy ge labag sa policy basin na mali ramo', 'approved', 'Approved by admin on profile update', '2026-10-09 01:32:33', 1, '2026-10-09 01:31:06'),
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
(153, 11, '60b2e407ba7301ed070b4dd7c902bc666b600616b9282d1187065b14f818f824', '2026-10-02 17:54:35', '2026-09-02 09:54:35'),
(154, 11, '4fbc3035f72db94f26656a5799cb301a2dc18866ec75e070038d18bb08230dc9', '2026-10-02 17:54:51', '2026-09-02 09:54:51'),
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
(206, 20, 'c1e90c3396e42e3af8796bd272bbf35a353a13735472f8871e6e7f2b4d752564', '2026-10-28 14:22:18', '2026-09-28 06:22:18'),
(207, 20, '1f8cbac13f63018af91ca0f3b31c8dbbf61d72dfba58f1976a8a14ad5a1f74b4', '2026-10-28 14:22:23', '2026-09-28 06:22:23'),
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
(231, 51, 'd0562babe6ab6c47431e205e8da0b5deee92518f59eedc180c94d2d5396a9752', '2026-11-18 01:39:05', '2026-10-18 17:39:05'),
(232, 51, '16f93c8804ff0baf6f7b4ca3222334beadb71f3365aa65d2bba44a609ac7b69c', '2026-11-18 01:39:24', '2026-10-18 17:39:24'),
(233, 51, 'fd51ce8855d99915a15713bb4c052bec7dc8cdac0881d3abc37e4a0a4cdb3d67', '2026-11-19 00:35:55', '2026-10-19 16:35:55'),
(236, 51, '7e7017f9f2417dd502d66a8649e3461b5c1d10534f42237eec6c06f9916f38dd', '2026-11-20 16:32:03', '2026-10-21 08:32:03'),
(237, 369, 'c06e65d3498907001f65b771897e919bf137c7a2e2526041b4c2de62bd87ed24', '2026-11-20 16:33:42', '2026-10-21 08:33:42'),
(238, 369, '078a7c747ad880451e8aca9b019ee415fe0b01c4f83d55fc41425f5e318c040d', '2026-11-20 16:34:08', '2026-10-21 08:34:08');

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
(101, 1, 'admin.message', 'user', 371, NULL, '2026-10-23 06:21:42');

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
  `payment_stage` enum('full','deposit_paid','balance_due','settled') NOT NULL DEFAULT 'full',
  `status` enum('pending','confirmed','completed','cancelled','disputed','refunded') NOT NULL DEFAULT 'pending',
  `hotel_status` enum('pending','deposit_paid','confirmed','checked_in','completed','cancelled','no_show') DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `verify_token` varchar(64) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `unit_completed_at` timestamp NULL DEFAULT NULL,
  `provider_completed_at` timestamp NULL DEFAULT NULL,
  `promo_code_id` int(10) UNSIGNED DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `listing_id`, `room_id`, `user_id`, `booking_date`, `check_in_date`, `check_out_date`, `nights`, `booking_time`, `booking_end_time`, `booking_dates`, `guests`, `total_amount`, `deposit_amount`, `balance_amount`, `payment_stage`, `status`, `hotel_status`, `notes`, `verify_token`, `verified_at`, `unit_completed_at`, `provider_completed_at`, `promo_code_id`, `discount_amount`, `reminder_sent`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, 2, '2026-07-01', NULL, NULL, 1, '19:00:00', NULL, NULL, 2, 2000.00, 0.00, 0.00, 'full', 'disputed', NULL, 'Paid via GCash / e-Wallet QR · Ref: 3849292929', '91d41b13d874dc2a0df22479e7d5d9ef', NULL, NULL, NULL, NULL, 0.00, 0, '2026-06-29 19:52:53', '2026-09-29 05:26:46'),
(2, 5, NULL, 2, '2026-07-15', NULL, NULL, 1, NULL, NULL, NULL, 10, 1000.00, 0.00, 0.00, 'full', 'refunded', NULL, 'Paid via GCash / e-Wallet QR · Ref: 93933922920', '5639baebc198c1ce68a6b6f821eb3393', NULL, NULL, NULL, NULL, 0.00, 0, '2026-07-06 17:33:26', '2026-07-06 17:34:14'),
(3, 5, NULL, 2, '2026-07-15', NULL, NULL, 1, NULL, NULL, NULL, 10, 1000.00, 0.00, 0.00, 'full', 'confirmed', NULL, 'Paid via GCash / e-Wallet QR · Ref: 9494020202020', 'fe4fcb8c6080ed60c90a0f9fc3cd5d5a', NULL, NULL, NULL, NULL, 0.00, 0, '2026-07-06 17:35:53', '2026-07-06 17:35:53'),
(4, 1, NULL, 2, '2026-07-16', NULL, NULL, 1, NULL, NULL, NULL, 12, 9600.00, 0.00, 0.00, 'full', 'confirmed', NULL, 'Paid via GCash / e-Wallet QR · Ref: 938329292992', '9f7bcf388ac32a1500a0d977dd2ded38', NULL, NULL, NULL, NULL, 0.00, 0, '2026-07-08 18:56:43', '2026-07-08 18:56:43'),
(5, 6, NULL, 2, '2026-07-16', NULL, NULL, 1, NULL, NULL, NULL, 1, 50.00, 0.00, 0.00, 'full', 'refunded', NULL, 'Paid via GCash / e-Wallet QR · Ref: 3838299229', '1f7d59ce1aa0f1ef66e4d4b78fdc3cbf', NULL, NULL, NULL, NULL, 0.00, 0, '2026-07-08 18:58:28', '2026-09-16 18:01:10'),
(6, 3, NULL, 11, '2026-09-03', NULL, NULL, 1, '07:00:00', NULL, NULL, 50, 6000.00, 0.00, 0.00, 'full', 'confirmed', NULL, 'Paid via GCash / e-Wallet QR · Ref: 20283728291', 'e4adfae83f392e45819702e297325ab8', NULL, NULL, NULL, NULL, 0.00, 0, '2026-09-02 09:57:48', '2026-09-02 09:57:48'),
(7, 4, NULL, 11, '2026-09-03', NULL, NULL, 1, '09:00:00', NULL, NULL, 5, 500.00, 0.00, 0.00, 'full', 'confirmed', NULL, 'Paid via GCash / e-Wallet QR · Ref: ushshdbe', '35d0328ea810c158ef247a30d361a735', NULL, NULL, NULL, NULL, 0.00, 0, '2026-09-02 10:01:37', '2026-09-02 10:01:37'),
(8, 5, NULL, 15, '2026-09-30', NULL, NULL, 1, '07:00:00', NULL, NULL, 10, 1000.00, 0.00, 0.00, 'full', 'refunded', NULL, 'Paid via GCash / e-Wallet QR · Ref: 293929292929', '957ab9dedfcfa917ff20beeb64ef3cc8', NULL, NULL, NULL, NULL, 0.00, 0, '2026-09-16 21:16:37', '2026-09-28 06:18:06'),
(9, 1, NULL, 15, '2026-09-17', NULL, NULL, 1, '06:00:00', NULL, NULL, 10, 8000.00, 0.00, 0.00, 'full', 'completed', NULL, 'Paid via GCash / e-Wallet QR · Ref: 994922929', '09b80e6eec11b10768919f9fd7a70dbe', NULL, '2026-09-28 06:17:38', '2026-09-28 06:17:38', NULL, 0.00, 0, '2026-09-16 21:17:45', '2026-10-20 15:10:28'),
(10, 15, NULL, 2, '2026-09-28', NULL, NULL, 1, '08:00:00', NULL, NULL, 1, 3200.00, 0.00, 0.00, 'full', 'confirmed', NULL, 'Paid via GCash / e-Wallet QR · Ref: 0964575358885', '2a9a708345ec36e0dc6284a7b5712504', NULL, NULL, NULL, NULL, 0.00, 0, '2026-09-28 05:24:32', '2026-09-28 05:24:32'),
(14, 1, NULL, 20, '2026-09-29', NULL, NULL, 1, '08:00:00', NULL, NULL, 3, 2400.00, 0.00, 0.00, 'full', 'refunded', NULL, 'Paid via GCash / e-Wallet QR · Ref: 28282', '511e3ac125da4f8a0fecbdec99f7f572', NULL, NULL, NULL, NULL, 0.00, 0, '2026-09-28 06:24:11', '2026-09-28 06:25:06'),
(15, 9, NULL, 20, '2026-09-30', NULL, NULL, 1, '18:00:00', NULL, NULL, 12, 12000.00, 0.00, 0.00, 'full', 'completed', NULL, 'Paid via GCash / e-Wallet QR · Ref: hshsshhs', '6fb17f68f66a283874a8b6d7ef5b50dc', NULL, '2026-09-28 06:27:14', '2026-09-28 06:27:14', NULL, 0.00, 0, '2026-09-28 06:26:58', '2026-10-20 15:10:28'),
(21, 9, NULL, 51, '2026-10-19', NULL, NULL, 1, '06:00:00', '09:00:00', '[\"2026-10-19\",\"2026-10-20\",\"2026-10-21\"]', 1, 3000.00, 0.00, 0.00, 'full', 'pending', NULL, 'Paid via InstaPay QR (3 days: 2026-10-19, 2026-10-20, 2026-10-21) · Ref: 1234566', '1a5534cb8e845d942b481fb9c0ebfb6b', NULL, NULL, NULL, NULL, 0.00, 0, '2026-10-18 17:40:14', '2026-10-18 17:40:14'),
(22, 1, NULL, 51, '2026-10-19', NULL, NULL, 1, '09:00:00', '15:00:00', '[\"2026-10-19\",\"2026-10-20\",\"2026-10-21\"]', 1, 2400.00, 0.00, 0.00, 'full', 'confirmed', NULL, 'Paid via InstaPay QR (3 days: 2026-10-19, 2026-10-20, 2026-10-21) · Ref: 123456799G', '24e778b37b6c9efcf4d0fb9df3cddef8', NULL, NULL, NULL, NULL, 0.00, 0, '2026-10-18 17:41:01', '2026-10-18 17:52:05'),
(30, 12, NULL, 369, '2026-10-22', NULL, NULL, 1, '07:00:00', '16:00:00', '[\"2026-10-22\"]', 2, 13000.00, 0.00, 0.00, 'full', 'pending', NULL, 'Paid via GCash / e-Wallet QR (2026-10-22) · Ref: ajansbd', '0c35bd34567165f51da158b07ca81ffa', NULL, NULL, NULL, NULL, 0.00, 0, '2026-10-21 08:36:09', '2026-10-21 08:36:09'),
(31, 9, NULL, 369, '2026-10-22', NULL, NULL, 1, '20:00:00', '22:00:00', '[\"2026-10-22\"]', 1, 1000.00, 0.00, 0.00, 'full', 'pending', NULL, 'Paid via GCash / e-Wallet QR (2026-10-22) · Ref: 28363792', '05aeda0d14524fe8d88db216b9029efb', NULL, NULL, NULL, NULL, 0.00, 0, '2026-10-21 08:38:50', '2026-10-21 08:38:50'),
(32, 9, NULL, 369, '2026-10-29', NULL, NULL, 1, '08:00:00', '11:00:00', '[\"2026-10-29\"]', 1, 1000.00, 0.00, 0.00, 'full', 'pending', NULL, 'Paid via GCash / e-Wallet QR (2026-10-29) · Ref: ejje', '0d3a6c26cdef583e292b6fc9abafe4ee', NULL, NULL, NULL, NULL, 0.00, 0, '2026-10-21 08:47:49', '2026-10-21 08:47:49'),
(34, 10, 3, 51, '2026-10-23', '2026-10-23', '2026-10-24', 1, NULL, NULL, NULL, 2, 2600.00, 520.00, 2080.00, 'deposit_paid', 'pending', 'deposit_paid', 'Hotel Room: Eco-Glamping Suite (1 nights: 2026-10-23 to 2026-10-24)\nDeposit paid via GCash / e-Wallet QR: ₱520.00\nRemaining balance due at front desk: ₱2,080.00 · Ref: 12334', '259171bb2b6b647fab8af7bd77de6554', NULL, NULL, NULL, NULL, 0.00, 0, '2026-10-23 07:37:37', '2026-10-23 07:37:37');

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
(2, 'Tour Guides', 'tour-guides', 'user-check', 'Hire trusted local Cebuano guides', 2),
(3, 'Hotels & Stays', 'hotels', 'bed', 'Resorts, hotels and stays', 3),
(4, 'Restaurants', 'restaurants', 'utensils', 'Where to eat in Cebu', 4);

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
(2, 3, 2, 0, 1, '2026-10-21 08:50:44'),
(3, 3, 20, 0, 1, '2026-10-21 08:50:51');

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

--
-- Dumping data for table `disputes`
--

INSERT INTO `disputes` (`id`, `booking_id`, `user_id`, `guide_id`, `problem_type`, `amount_requested`, `description`, `status`, `admin_note`, `resolved_at`, `created_at`, `updated_at`) VALUES
(1, 5, 2, 3, 'extra_payment', 500.00, 'teketkyekyekhrmyeeymyemehmfmhdm', 'resolved_refund', 'Refund approved.', '2026-09-16 18:01:10', '2026-09-16 17:18:29', '2026-09-16 18:01:10'),
(4, 1, 2, 3, 'extra_payment', 500.00, 'The guide says he needs an extra payment', 'resolved_warning', 'Warning issued after dispute #4.', '2026-09-29 05:28:14', '2026-09-29 05:26:46', '2026-09-29 05:28:14');

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

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `listing_id`, `created_at`) VALUES
(1, 2, 3, '2026-09-16 17:36:42'),
(4, 2, 15, '2026-09-27 15:56:15'),
(9, 20, 9, '2026-09-28 06:45:26'),
(10, 20, 2, '2026-09-28 06:45:28'),
(11, 20, 5, '2026-09-28 06:45:53'),
(14, 369, 6, '2026-10-21 08:34:27'),
(15, 369, 8, '2026-10-21 08:34:28'),
(16, 369, 12, '2026-10-21 08:35:48');

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
(6, 23, 'valid_id', 'Screenshot 2026-10-05 175113.png', 'uploads/guide-docs/23/35899544a0924543.png', '2026-10-09 01:00:54'),
(7, 23, 'guide_license', 'Screenshot 2026-10-05 175113.png', 'uploads/guide-docs/23/6fd562adb9e9c183.png', '2026-10-09 01:00:54'),
(10, 46, 'valid_id', 'Screenshot 2026-10-09 090525.png', 'uploads/guide-docs/46/c20dcefab6ae407a.png', '2026-10-14 18:36:29'),
(11, 46, 'association', 'Screenshot 2026-10-09 090525.png', 'uploads/guide-docs/46/95598e9f91b6efd7.png', '2026-10-14 18:36:29'),
(18, 167, 'valid_id', 'guide2.png', 'uploads/guide-docs/167/d3b196b2709aecf1.png', '2026-10-20 04:42:11'),
(19, 167, 'employment', 'guide2.png', 'uploads/guide-docs/167/d7eaa5d4bf5efa29.png', '2026-10-20 04:42:11'),
(63, 371, 'valid_id', 'ss.jpg', 'uploads/guide-docs/371/a1a78ce5f68c9474.jpg', '2026-10-23 01:12:32'),
(64, 371, 'guide_license', 'ss2.jpg', 'uploads/guide-docs/371/b3e7d5fede8f5253.jpg', '2026-10-23 01:12:32'),
(65, 371, 'other', 'ss3.jpg', 'uploads/guide-docs/371/43f0d3ace2fcb93e.jpg', '2026-10-23 01:12:32'),
(78, 699, 'valid_id', 'ss7.jpg', 'uploads/guide-docs/699/95ec1a1a755fcc55.jpg', '2026-10-23 05:02:09'),
(79, 699, 'employment', 'ss6.jpg', 'uploads/guide-docs/699/87450c076aa943f1.jpg', '2026-10-23 05:02:09'),
(80, 699, 'other', 'ss3.jpg', 'uploads/guide-docs/699/3b1a2cf8563e1e5b.jpg', '2026-10-23 05:02:09');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_rooms`
--

INSERT INTO `hotel_rooms` (`id`, `listing_id`, `name`, `description`, `room_size`, `bed_type`, `max_guests`, `price_per_night`, `deposit_percentage`, `total_units`, `available_units`, `status`, `amenities`, `image`, `created_at`, `updated_at`) VALUES
(1, 10, 'Premier Hilltop Villa', 'Overlooking the lush rolling hills of Aloguinsan. Features a private sunset balcony, handcrafted timber furnishings, and organic bath amenities.', '42 m² (452 sq ft)', '1 King Bed', 2, 3500.00, 20, 1, 1, 'available', 'Air Conditioning, Free High-Speed Wi-Fi, Private Hilltop Balcony, Hot Rain Shower, Mini-Bar, Daily Organic Breakfast for 2', 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(2, 10, 'Farm View Family Cottage', 'Spacious standalone cottage surrounded by organic farm gardens. Ideal for families and small groups visiting the Bojo River eco-tour.', '58 m² (624 sq ft)', '2 Queen Beds', 4, 4800.00, 25, 1, 1, 'available', 'Air Conditioning, Free Wi-Fi, Garden Patio, Hot Shower, Kitchenette, Tea & Coffee Maker, Farm Tour Pass', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(3, 10, 'Eco-Glamping Suite', 'A cozy safari-style glamping tent on a raised wooden platform. Perfect for nature lovers wanting peaceful night breezes.', '28 m² (301 sq ft)', '1 Queen Bed', 2, 2600.00, 20, 1, 1, 'available', 'Silent Eco-Fan & Inverter AC, Private Open-Air Rain Shower, Stargazing Deck, Free Wi-Fi at Lounge', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:28:38'),
(4, 11, 'Deluxe Oceanfront Bungalow', 'Step directly out toward the Tañon Strait. Features breathtaking sunset views, high ceiling bamboo accents, and dive equipment drying racks.', '38 m² (409 sq ft)', '1 King Bed', 2, 2800.00, 20, 1, 1, 'available', 'Direct Beachfront, Starlink Wi-Fi (150 Mbps), Inverter AC, Hot Rain Shower, Mini-Fridge, Free Snorkel Gear', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(5, 11, 'Sea View Diver Suite', 'Upper-level suite with panoramic views of Pescador Island. Includes dedicated gear storage, workstation, and daily breakfast.', '46 m² (495 sq ft)', '1 King Bed + 1 Daybed', 3, 3600.00, 20, 1, 1, 'available', 'Panoramic Sea View, Work Desk, Air Conditioning, Safe, Tea & Coffee, Dive Center Priority Booking', 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(6, 11, 'Standard Garden Room', 'Tranquil room nestled in the resort tropical garden. Quiet, cool, and comfortable after an active day of diving.', '30 m² (322 sq ft)', '2 Twin Beds', 2, 2200.00, 20, 1, 1, 'available', 'Garden View, Silent AC, En-suite Bathroom with Hot Water, Free Wi-Fi, Fresh Beach Towels', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(7, 12, 'Thalasso Island Pool Villa', 'Ultra-luxurious private island villa with ionized seawater pool and unobstructed vistas of the Badian bay and mountains.', '65 m² (700 sq ft)', '1 King Bed', 2, 6500.00, 30, 1, 1, 'available', 'Private Plunge Pool, Butler Service, Luxury Soaking Tub, High-speed Wi-Fi, Spa Treatments Discount, Free Island Boat Transfer', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(8, 12, 'Deluxe Island Badian Suite', 'Spacious open-concept suite adorned with native marble and Philippine hardwood. Expansive balcony overlooking the white sand beach.', '52 m² (560 sq ft)', '1 King Bed + 1 Daybed', 3, 5200.00, 25, 1, 1, 'available', 'Sea View Balcony, Air Conditioning, Marble Bathroom, Free Breakfast for 2, Organic Toiletries', 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(9, 13, 'Beachfront Standard Queen', 'Clean, cozy beachfront room 5 minutes from the Tan-awan whale shark briefing center. Listen to the ocean waves as you sleep.', '24 m² (258 sq ft)', '1 Queen Bed', 2, 1800.00, 20, 1, 1, 'available', 'Beachfront Access, Air Conditioning, Hot Shower, Cable TV, Wi-Fi in Public Areas, Early Whale Shark Queue Assistance', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(10, 13, 'Family Seaside Suite', 'Two double beds with ample floor space for luggage and gear. Perfect jump-off point for Sumilon Island day trips.', '36 m² (387 sq ft)', '2 Double Beds', 4, 2800.00, 20, 1, 1, 'available', 'Air Conditioning, Refrigerator, Private Bathroom, Sea View Porch, Free Parking', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(11, 14, 'Highland Mountain Cabin', 'Rustic wooden cabin in cool Mantalongon. Enjoy the mountain breeze and stunning sunrises before hiking Osmeña Peak.', '26 m² (280 sq ft)', '1 Queen Bed', 2, 2200.00, 20, 1, 1, 'available', 'Mountain View Deck, Hot Shower, Electric Kettle & Tea Selection, Mountain Trekking Guide Referral', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(12, 14, 'Osmeña Peak View Family Room', 'Generously sized highland room with picture windows framing Dalaguete’s jagged limestone peaks.', '40 m² (430 sq ft)', '2 Queen Beds', 4, 3200.00, 20, 1, 1, 'available', 'Balcony with Peak View, Hot Water Shower, Dining Table, Wi-Fi, Complimentary Native Hot Chocolate', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(13, 15, 'Skyline City View Deluxe', 'Perched on the Busay hillside with sparkling night views of Cebu City. Minutes from Temple of Leah and Tops Lookout.', '32 m² (344 sq ft)', '1 King Bed', 2, 3200.00, 20, 1, 1, 'available', 'Panoramic Skyline View, 43\" Smart TV with Netflix, High-Speed Wi-Fi, Air Conditioning, Rainfall Shower', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48'),
(14, 15, 'Executive Busay Suite', 'Corner suite featuring wrap-around floor-to-ceiling glass windows, living area, and private jacuzzi tub.', '48 m² (516 sq ft)', '1 King Bed + 1 Sofa Bed', 3, 4200.00, 25, 1, 1, 'available', 'Private Jacuzzi, City View Terrace, Mini Bar, Bathrobes & Slippers, Breakfast for 2', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', '2026-10-23 07:07:48', '2026-10-23 07:07:48');

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
  `included` text DEFAULT NULL,
  `not_included` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sale_ends_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `listings`
--

INSERT INTO `listings` (`id`, `user_id`, `category_id`, `title`, `slug`, `summary`, `description`, `area`, `address`, `latitude`, `longitude`, `price`, `price_unit`, `duration`, `included`, `not_included`, `cover_image`, `status`, `is_featured`, `sale_price`, `sale_ends_at`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 'Moalboal Snorkeling', 'moalboal-snorkeling', 'Snorkel the vibrant coral reefs and turtle spots of Moalboal.', 'Explore the crystal-clear waters of Moalboal, Cebu. Snorkel over thriving coral gardens, swim alongside sea turtles, and witness the famous marine life of Panagsama Beach. Equipment and a local guide are included.', 'Moalboal, Cebu', 'Panagsama Beach, Moalboal, Cebu', 9.9492466, 123.3658045, 800.00, 'per person', '2-3 hours', 'Snorkeling gear, local guide, boat transfer', 'Food and drinks, hotel pickup', 'uploads/places/sardine.jpg', 'approved', 1, NULL, NULL, '2026-06-15 12:27:23', '2026-10-18 17:52:46'),
(2, 3, 4, 'Istoria', 'kyles-resto-bar', 'Beachfront resto bar with local dishes and drinks in Moalboal.', 'Relax at Istoria Resto Bar in Moalboal, Cebu. Enjoy fresh seafood, Filipino favorites, ice cold drinks, and a laid back beachfront atmosphere  perfect after a day of diving and snorkeling.', 'Moalboal, Cebu', 'Panagsama Beach, Moalboal, Cebu', 9.9480020, 123.3719180, 1000.00, 'per table', '', 'Table reservation', 'Food and drinks (pay on site)', 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=70', 'approved', 1, NULL, NULL, '2026-06-15 12:27:23', '2026-07-06 17:31:00'),
(3, 3, 1, 'Temple of Leah', 'temple-of-leah', 'Cebu\'s grand Roman-inspired temple, the \'Taj Mahal of Cebu\'.', 'Perched in the hills of Busay, the Temple of Leah is a majestic Greco-Roman temple built as a symbol of undying love. Marvel at the towering columns, grand staircases, bronze statues, and a sweeping view of Cebu City. A favorite spot for photos and history lovers.', 'Busay, Cebu City', 'Roosevelt Street, Busay, Cebu City', 10.3608094, 123.8847401, 120.00, 'per person', '1-2 hours', NULL, NULL, 'uploads/places/templeofleah.jpg', 'approved', 1, NULL, NULL, '2026-06-15 12:49:40', '2026-10-09 04:14:01'),
(4, 3, 1, 'Sirao Flower Garden', 'sirao-flower-garden', 'The \'Little Amsterdam\' of Cebu — fields of colorful celosia.', 'Sirao Flower Garden is famous for its vibrant rows of celosia (cockscomb) flowers set against the cool highlands of Cebu. Explore themed photo spots, windmills, and viewing decks. A must-visit for nature lovers and photographers.', 'Busay, Cebu City', 'Sirao, Busay, Cebu City', 10.3608094, 123.8847401, 100.00, 'per person', '1-2 hours', NULL, NULL, 'uploads/places/sirao.jpg', 'approved', 1, NULL, NULL, '2026-06-15 12:49:40', '2026-10-09 04:14:04'),
(5, 3, 1, 'Osmeña Peak', 'osmena-peak', 'The highest point in Cebu — jagged hills and sunrise views.', 'Rising 1,013 meters above sea level, Osmeña Peak is the highest point in Cebu. Hike through dramatic, jagged hills that resemble Bohol\'s Chocolate Hills and catch breathtaking sunrises with views of the sea on both sides of the island. Easy trek, unforgettable scenery.', 'Dalaguete, Cebu', 'Mantalongon, Dalaguete, Cebu', 9.8076650, 123.4648000, 100.00, 'per person', 'Half day', NULL, NULL, 'uploads/places/osmena.jpg', 'approved', 1, NULL, NULL, '2026-06-15 12:49:40', '2026-07-06 17:36:06'),
(6, 3, 1, 'Casay Beach', 'casay-beach-dalaguete', 'Long stretch of fine sand on Cebu’s scenic southeast coast.', 'Casay Beach in Dalaguete offers a long shoreline of fine sand, calm waters, and a relaxed atmosphere away from the crowds. Perfect for swimming, picnics, and family weekends, with mountain backdrops and beautiful sunrises.', 'Dalaguete, Cebu', 'Casay, Dalaguete, Cebu', 9.8198540, 123.5496300, 50.00, 'per person', 'Day trip', NULL, NULL, 'uploads/places/casay.jpg', 'approved', 1, NULL, NULL, '2026-06-15 12:49:40', '2026-07-08 18:58:37'),
(7, 3, 1, 'Kawasan Falls Canyoneering', 'kawasan-falls-canyoneering-badian', 'Turquoise waterfalls and cliff-jumping adventure in Badian.', 'Experience Cebu\'s most famous adventure: canyoneering through the stunning turquoise waters of Kawasan Falls in Badian. Jump off cliffs, swim through gorges, and slide down natural rock formations before reaching the majestic multi-tiered falls. Guides, gear, and safety equipment included.', 'Badian, Cebu', 'Matutinao, Badian, Cebu', NULL, NULL, 1500.00, 'per person', '3-4 hours', NULL, NULL, 'uploads/places/kawasan.jpg', 'approved', 1, NULL, NULL, '2026-06-15 12:49:40', '2026-06-15 14:46:09'),
(8, 3, 1, 'Oslob Whale Shark Watching', 'oslob-whale-shark-watching', 'Swim alongside gentle whale sharks in Tan-awan, Oslob.', 'Get up close with the gentle giants of the sea in Oslob, Cebu. Snorkel or dive beside butanding (whale sharks) in their natural feeding grounds — a once-in-a-lifetime, bucket-list experience. Includes briefing, boat, and snorkeling gear.', 'Oslob, Cebu', 'Tan-awan, Oslob, Cebu', NULL, NULL, 1000.00, 'per person', '2-3 hours', NULL, NULL, 'uploads/places/whaleshark.jpg', 'approved', 1, NULL, NULL, '2026-06-15 12:49:40', '2026-06-15 14:46:09'),
(9, 3, 1, 'Bojo River', 'bojo-river', 'Visitors frequently praise the peaceful atmosphere, scenic mangrove forests, and the relaxing pace of the river cruise.', 'Bojo River is a community-managed ecotourism destination in Aloguinsan, Cebu, Philippines, centered on a tranquil mangrove-lined river and its popular river cruise. It is widely recognized for sustainable, community-based tourism that highlights coastal conservation, local culture, and the river-to-reef ecosystem', 'Southern Cebu', '6G4W+92W, Aloguinsan, Cebu', 10.2225279, 123.5485072, 1000.00, 'per person', '4-6 hours', 'Guide fee', 'Lunch, Breakfast, Dinner', 'uploads/listings/3/b47060b8b3a98164.jpg', 'approved', 1, NULL, NULL, '2026-09-21 00:42:28', '2026-10-18 17:41:29'),
(10, 18, 3, 'La Joya Farm Resort & Spa', 'la-joya-farm-resort-spa', 'Hilltop farm resort & spa minutes from the Bojo River, Aloguinsan.', 'A tranquil hilltop retreat overlooking Aloguinsan and the Bojo River. Enjoy farm-to-table dining, an infinity pool, and full-service spa — the closest premium stay to the Bojo River eco-tour.', 'Aloguinsan, Cebu', 'Bojo, Aloguinsan, Cebu', 10.2246000, 123.5585000, 3500.00, 'per night', '', 'Room accommodation', 'Tours and meals unless stated', 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80', 'approved', 0, NULL, NULL, '2026-09-27 14:46:37', '2026-09-28 05:55:13'),
(11, 18, 3, 'Kasai Village Dive Resort', 'kasai-village-dive-resort', 'Beachfront dive resort on Panagsama Beach, Moalboal.', 'Steps from the famous Moalboal sardine run and Panagsama Beach. Cozy rooms, a freediving school, and a seafront restaurant — perfect for snorkeling and diving trips.', 'Moalboal, Cebu', 'Panagsama Beach, Moalboal, Cebu', 9.9492466, 123.3658045, 2800.00, 'per night', '', 'Room accommodation', 'Tours and meals unless stated', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80', 'approved', 0, NULL, NULL, '2026-09-27 14:46:37', '2026-10-09 04:13:58'),
(12, 18, 3, 'Badian Island Wellness Resort', 'badian-island-wellness-resort', 'Private island wellness resort near Kawasan Falls, Badian.', 'An exclusive island resort with white-sand beach, wellness spa, and easy access to Kawasan Falls canyoneering. Ideal for a restful stay after your adventure.', 'Badian, Cebu', 'Badian Island, Badian, Cebu', 9.8000000, 123.3700000, 6500.00, 'per night', '', 'Room accommodation', 'Tours and meals unless stated', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', 'approved', 0, NULL, NULL, '2026-09-27 14:46:37', '2026-09-27 14:46:37'),
(13, 18, 3, 'MJ\'s Beach Resort Oslob', 'mjs-beach-resort-oslob', 'Simple beachfront rooms beside the Oslob whale shark point.', 'Affordable beachfront rooms in Tan-awan, Oslob — a short walk from the whale shark watching area and Sumilon Island boats.', 'Oslob, Cebu', 'Tan-awan, Oslob, Cebu', 9.4590000, 123.3880000, 1800.00, 'per night', '', 'Room accommodation', 'Tours and meals unless stated', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', 'approved', 0, NULL, NULL, '2026-09-27 14:46:37', '2026-09-27 14:46:37'),
(14, 18, 3, 'Casa Nostra Mountain View', 'casa-nostra-mountain-view', 'Cool-climate mountain lodge near Osmeña Peak, Dalaguete.', 'A cozy highland lodge in Mantalongon, Dalaguete — the jump-off to Osmeña Peak sunrise hikes and Casay Beach day trips.', 'Dalaguete, Cebu', 'Mantalongon, Dalaguete, Cebu', 9.7900000, 123.5200000, 2200.00, 'per night', '', 'Room accommodation', 'Tours and meals unless stated', 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80', 'approved', 0, NULL, NULL, '2026-09-27 14:46:37', '2026-09-27 14:46:37'),
(15, 18, 3, 'Cebu Hilltop Hotel Busay', 'cebu-hilltop-hotel-busay', 'Panoramic city-view hotel in Busay, near Temple of Leah.', 'Perched in Busay with sweeping views of Cebu City. Walking distance to Temple of Leah and Sirao Flower Garden — a great base for uptown sightseeing.', 'Busay, Cebu City', 'Busay, Cebu City', 10.3613810, 123.8854900, 3200.00, 'per night', '', 'Room accommodation', 'Tours and meals unless stated', 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80', 'approved', 0, NULL, NULL, '2026-09-27 14:46:37', '2026-09-28 05:27:15');

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
(1, 9, 'uploads/listings/3/b47060b8b3a98164.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `listing_schedules`
--

CREATE TABLE `listing_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` varchar(5) DEFAULT NULL,
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
(13, 20, 3, NULL, 'hxhxhd', 1, '2026-09-28 06:27:57', NULL),
(14, 2, 18, NULL, 'hello is this available?', 1, '2026-09-29 05:07:45', NULL),
(15, 1, 3, NULL, 'Administrator Warning\n\nYou have received an official warning from the GuideMate Administrator.\n\nReason: Warning issued after dispute #4.\n\nYour booking actions (Confirm / Complete / Cancel) are restricted until an administrator clears this warning. Please review this message and wait for admin follow-up.', 1, '2026-09-29 05:28:14', NULL),
(16, 3, 1, NULL, 'Okay, noted for this.', 1, '2026-09-29 05:28:54', NULL),
(17, 1, 3, NULL, 'Administrator Notice\n\nYour account has been reviewed and unrestricted by the Administrator. You may Confirm, Complete, and Cancel bookings again.', 1, '2026-09-29 07:04:32', NULL),
(18, 1, 18, NULL, 'Administrator Notice\n\nYour account has been reviewed and unrestricted by the Administrator. You may Confirm, Complete, and Cancel bookings again.', 1, '2026-09-29 07:04:35', NULL),
(19, 1, 12, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 1, '2026-09-29 11:36:42', NULL),
(20, 1, 23, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 1, '2026-10-09 01:02:10', NULL),
(22, 46, 3, 9, 'Hi! I am interested in \"Bojo River\". Could you share more details?', 1, '2026-10-14 18:38:24', NULL),
(25, 3, 46, NULL, '你好', 0, '2026-10-19 16:01:32', NULL),
(34, 51, 3, NULL, 'Hello Marc', 1, '2026-10-19 16:36:44', NULL),
(35, 3, 51, NULL, '你好', 1, '2026-10-19 16:37:26', 'zh-CN'),
(36, 51, 3, NULL, '我想預約你', 1, '2026-10-19 16:39:07', 'zh'),
(41, 51, 3, NULL, 'こんにちは、マーク。今、時間ある？', 1, '2026-10-19 17:13:21', 'ja'),
(42, 3, 51, NULL, 'いいえ', 1, '2026-10-19 17:14:06', 'ja'),
(59, 1, 167, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 1, '2026-10-20 04:42:57', NULL),
(88, 51, 167, NULL, 'Hello', 1, '2026-10-20 15:51:48', NULL),
(89, 51, 167, NULL, 'Hello', 1, '2026-10-20 15:52:23', NULL),
(90, 51, 167, NULL, 'D', 1, '2026-10-20 16:02:40', NULL),
(91, 51, 3, NULL, 'Marc', 1, '2026-10-21 08:32:23', NULL),
(92, 3, 51, NULL, 'ouh', 1, '2026-10-21 08:32:32', 'id'),
(93, 369, 167, NULL, 'prde pa?', 1, '2026-10-21 08:34:55', NULL),
(94, 369, 3, NULL, 'jj', 1, '2026-10-21 08:35:21', NULL),
(95, 369, 23, NULL, 'iiii', 0, '2026-10-21 08:35:25', NULL),
(96, 369, 3, NULL, 'single paka?', 1, '2026-10-21 08:48:17', 'tl'),
(97, 3, 369, NULL, 'dili napo', 1, '2026-10-21 08:48:41', 'ceb'),
(98, 369, 3, NULL, 'ni kaon naka?', 1, '2026-10-21 08:49:18', 'ceb'),
(99, 369, 3, NULL, 'mingaw nako nimo', 1, '2026-10-21 08:49:31', 'ceb'),
(100, 3, 369, NULL, 'biot', 1, '2026-10-21 08:49:42', 'en'),
(101, 3, 369, NULL, 'gjhg', 1, '2026-10-21 08:49:49', 'en'),
(102, 369, 3, NULL, 'ih report tika', 1, '2026-10-21 08:49:57', 'kha'),
(127, 1, 371, NULL, 'Application Approved\n\nYour partner application has been approved by the GuideMate Administrator. You can now use your partner account on GuideMate.', 0, '2026-10-23 00:18:10', NULL),
(128, 1, 371, NULL, 'hello', 0, '2026-10-23 05:42:33', NULL),
(129, 1, 371, NULL, 'heelo', 0, '2026-10-23 05:42:44', NULL),
(130, 18, 1, NULL, 'fdfdfdf', 1, '2026-10-23 05:43:02', NULL),
(131, 1, 371, NULL, 'hghg', 0, '2026-10-23 05:49:52', NULL),
(132, 1, 371, NULL, 'ggdgd', 0, '2026-10-23 05:51:54', NULL),
(133, 1, 371, NULL, 'hello', 0, '2026-10-23 05:52:07', NULL),
(134, 1, 371, NULL, 'fdfd', 0, '2026-10-23 05:55:49', NULL),
(135, 1, 371, NULL, 'fgfhh', 0, '2026-10-23 05:59:50', NULL),
(136, 1, 371, NULL, 'fhf', 0, '2026-10-23 05:59:54', NULL),
(137, 18, 1, NULL, 'fsdd', 1, '2026-10-23 06:00:20', NULL),
(138, 18, 1, NULL, 'fhdhdh', 1, '2026-10-23 06:00:24', NULL),
(139, 1, 3, NULL, 'Short greeting: Hello partner!', 0, '2026-10-23 06:20:23', NULL),
(140, 1, 3, NULL, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 0, '2026-10-23 06:20:23', NULL),
(141, 1, 3, NULL, 'Line 1: Tour guide policy reminder\nLine 2: Terms and conditions\nLine 3: Thank you.', 0, '2026-10-23 06:20:23', NULL),
(142, 1, 371, NULL, 'fesfsgse', 0, '2026-10-23 06:21:42', NULL),
(143, 1, 3, NULL, 'Short greeting: Hello partner!', 0, '2026-10-23 07:06:05', NULL),
(144, 1, 3, NULL, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Lorem ipsum dolor sit amet, consectetur adipiscing elit. https://guidemate.example.com/very/long/unbroken/path/that/could/overflow/without/word-break/styling/test1234567890', 0, '2026-10-23 07:06:05', NULL),
(145, 1, 3, NULL, 'Line 1: Tour guide policy reminder\nLine 2: Terms and conditions\nLine 3: Thank you.', 0, '2026-10-23 07:06:05', NULL);

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
(10, 35, 'en', 'Hello', 'zh-CN', '2026-10-19 16:37:27'),
(14, 36, 'en', 'I want to make an appointment with you', 'zh', '2026-10-19 17:11:44'),
(15, 41, 'en', 'Hello, Mark. Do you have time now?', 'ja', '2026-10-19 17:13:23'),
(16, 42, 'en', 'no', 'ja', '2026-10-19 17:14:10'),
(50, 92, 'en', 'oh', 'id', '2026-10-21 08:32:35'),
(51, 96, 'en', 'single yet?', 'tl', '2026-10-21 08:48:29'),
(52, 97, 'en', 'not napo', 'ceb', '2026-10-21 08:48:45'),
(53, 98, 'en', 'did you eat?', 'ceb', '2026-10-21 08:49:21'),
(54, 99, 'en', 'I miss you', 'ceb', '2026-10-21 08:49:44'),
(55, 4, 'en', 'crazy', 'ceb', '2026-10-21 08:50:39'),
(56, 6, 'en', 'gay ampota', 'tl', '2026-10-21 08:50:39'),
(57, 8, 'en', 'pitch and', 'sw', '2026-10-21 08:50:40'),
(61, 102, 'en', 'yes report only', 'kha', '2026-10-22 15:49:03');

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `amount`, `method`, `status`, `reference`, `paid_at`, `created_at`) VALUES
(1, 1, 2000.00, 'gcash', 'paid', 'GM-71FC467EC8', '2026-06-29 19:52:53', '2026-06-29 19:52:53'),
(2, 2, 1000.00, 'gcash', 'refunded', 'GM-144497A11F', '2026-07-06 17:33:26', '2026-07-06 17:33:26'),
(3, 3, 1000.00, 'gcash', 'paid', 'GM-3373297568', '2026-07-06 17:35:53', '2026-07-06 17:35:53'),
(4, 4, 9600.00, 'gcash', 'paid', 'GM-F00DD30EAB', '2026-07-08 18:56:43', '2026-07-08 18:56:43'),
(5, 5, 50.00, 'gcash', 'refunded', 'GM-16C42003C9', '2026-07-08 18:58:28', '2026-07-08 18:58:28'),
(6, 6, 6000.00, 'gcash', 'paid', 'GM-9B30299D4E', '2026-09-02 09:57:48', '2026-09-02 09:57:48'),
(7, 7, 500.00, 'gcash', 'paid', 'GM-7663B190B9', '2026-09-02 10:01:37', '2026-09-02 10:01:37'),
(8, 8, 1000.00, 'gcash', 'refunded', 'GM-853C408ADD', '2026-09-16 21:16:37', '2026-09-16 21:16:37'),
(9, 9, 8000.00, 'gcash', 'paid', 'GM-93FC0E82D4', '2026-09-16 21:17:45', '2026-09-16 21:17:45'),
(10, 10, 3200.00, 'gcash', 'paid', 'GM-6724A7DACE', '2026-09-28 05:24:32', '2026-09-28 05:24:32'),
(14, 14, 2400.00, 'gcash', 'refunded', 'GM-F251A87043', '2026-09-28 06:24:11', '2026-09-28 06:24:11'),
(15, 15, 12000.00, 'gcash', 'paid', 'GM-DAA6D6D3B7', '2026-09-28 06:26:58', '2026-09-28 06:26:58'),
(19, 21, 3000.00, 'instapay', 'paid', 'GM-CD558CCCCB', '2026-10-18 17:40:14', '2026-10-18 17:40:14'),
(20, 22, 2400.00, 'instapay', 'paid', 'GM-043B27B1C4', '2026-10-18 17:41:01', '2026-10-18 17:41:01'),
(21, 30, 13000.00, 'gcash', 'paid', 'GM-95A629B473', '2026-10-21 08:36:09', '2026-10-21 08:36:09'),
(22, 31, 1000.00, 'gcash', 'paid', 'GM-21D071C488', '2026-10-21 08:38:50', '2026-10-21 08:38:50'),
(23, 32, 1000.00, 'gcash', 'paid', 'GM-AA458F7047', '2026-10-21 08:47:49', '2026-10-21 08:47:49'),
(26, 34, 520.00, 'gcash', 'paid', 'GM-F88ADAA679', '2026-10-23 07:37:37', '2026-10-23 07:37:37');

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

INSERT INTO `rental_requests` (`id`, `user_id`, `vehicle_id`, `vehicle_name`, `vehicle_type`, `shop_name`, `location`, `pickup_date`, `rental_days`, `total_amount`, `customer_name`, `customer_email`, `customer_phone`, `notes`, `status`, `admin_note`, `contacted_at`, `created_at`, `updated_at`, `payment_status`, `payment_method`, `payment_reference`, `paid_at`, `id_document`, `id_type`, `id_number`, `report_type`, `report_message`, `report_status`, `report_created_at`, `owner_report_note`) VALUES
(1, 2, 'scooter', 'Honda Click 125i', 'Scooter', 'Cebu Moto Rentals', 'Cebu City · Fuente Osmeña', '2026-07-01', 1, 500.00, 'Jalel Mauc', 'jalelmauc@gmail.com', '09537330643', '9:00AM NO DRIVER', 'approved', NULL, '2026-07-07 06:45:38', '2026-06-30 05:34:59', '2026-07-07 06:45:38', 'unpaid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'none', NULL, NULL),
(2, 6, 'motorcycle', 'KTM 390 Adventure', 'Motorcycle', 'ADV Riders Cebu', 'Mandaue City · A.S. Fortuna', '2026-07-01', 1, 1200.00, 'Felix Baquerosa', 'felixbruce920@gmail.com', '0912345697', NULL, 'cancelled', NULL, NULL, '2026-06-30 06:27:18', '2026-07-07 06:45:33', 'unpaid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'none', NULL, NULL),
(3, 2, 'scooter', 'Honda Click 125i', 'Scooter', 'Cebu Moto Rentals', 'Cebu City · Fuente Osmeña', '2026-09-30', 2, 1000.00, 'Jalel Mauc', 'jalelmauc@gmail.com', '09537330643', 'Pickup time: 11:00 · No need for drivers', 'approved', NULL, NULL, '2026-09-29 07:26:19', '2026-09-29 07:26:19', 'paid', 'gcash', '029392020302', '2026-09-29 07:26:19', 'uploads/rentals/ids/2/090c4babcc1c188f.jpeg', 'Driver\'s License', '929392920203', NULL, NULL, 'none', NULL, NULL),
(14, 51, '72', '2022 Honda Click 125i V2 Automatic', 'Scooter', 'Miguel Yongzon', 'Minglanilla, Cebu', '2026-10-20', 2, 2000.00, 'Christian Yongzon', 'christianyongzon143@gmail.com', '09280056394', 'Pickup time: 08:00 · Dala 1 helmet', 'completed', NULL, '2026-10-20 04:58:07', '2026-10-20 04:54:47', '2026-10-20 04:58:07', 'paid', 'gcash', '12345688', '2026-10-20 04:54:47', 'uploads/rentals/ids/51/74175fa3b60999df.jpeg', 'Driver\'s License', '123456889', NULL, NULL, 'none', NULL, NULL),
(31, 51, '72', '2022 Honda Click 125i V2 Automatic', 'Scooter', 'Miguel Yongzon', 'Minglanilla, Cebu', '2026-10-29', 1, 1000.00, 'Christian Yongzon', 'christianyongzon143@gmail.com', '09280056394', 'Pickup time: 10:00 · Test', 'completed', NULL, '2026-10-20 15:51:02', '2026-10-20 15:49:47', '2026-10-20 15:51:02', 'paid', 'gcash', '12345', '2026-10-20 15:49:47', 'uploads/rentals/ids/51/63fb28c3c7cf45d1.jpeg', 'UMID', '1234567', NULL, NULL, 'none', NULL, NULL);

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

--
-- Dumping data for table `rental_reviews`
--

INSERT INTO `rental_reviews` (`id`, `rental_id`, `reviewer_id`, `provider_id`, `vehicle_id`, `rating`, `feedback`, `created_at`, `updated_at`) VALUES
(7, 31, 51, 167, '72', 4, 'Updated review: Overall great experience with the Honda Click.', '2026-10-23 01:43:33', '2026-10-23 01:43:33');

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

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `booking_id`, `listing_id`, `user_id`, `provider_id`, `rating`, `unit_rating`, `title`, `comment`, `unit_comment`, `provider_rating`, `provider_comment`, `visited_on`, `created_at`, `updated_at`) VALUES
(3, NULL, 9, 20, 3, 2, 2, 'hhh', 'hhg', 'hhg', 2, 'hhg', '2026-09-28', '2026-09-28 06:44:45', '2026-10-20 15:10:28');

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
  `role` enum('admin','guide','tourist','rental_provider','hotel_admin') NOT NULL DEFAULT 'tourist',
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
(1, 'Administrator', 'fbaquerosa@gmail.com', '$2y$10$fWMGmvoZB7Ll9C.emzfPjeiUxcePTSYQiSqOxViUCWEIPJtqGEZJa', 'admin', NULL, NULL, 'Platform administrator.', 'Cebu City', 1, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, 'N4BXKWOIT2MEMUYD', 0, '2026-06-15 11:51:31', '2026-09-28 06:30:46', NULL, NULL, NULL, NULL, 0),
(2, 'Jalel Mauc', 'jalelmauc@gmail.com', '$2y$10$VWE9XjTCU1cch9Q8daRguuNBGBn3prrwsJXNfpMk83GYF9Im1ZV0G', 'tourist', NULL, 'uploads/avatars/2/f5800b8ea0de67da.jpeg', NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-06-15 12:15:47', '2026-10-14 14:42:01', '2026-09-29 09:15:30', NULL, NULL, NULL, 0),
(3, 'Marc Repuesto', 'guide.moalboal@guidemate.local', '$2y$10$8kopCDTNm6m3GZ5u/ahiR.GcEvuYEWhdAJgN0J5z00xveI5FyQxQ6', 'guide', NULL, 'uploads/avatars/3/1665bce1d7903665.jpg', 'Local guide for snorkeling tours and dining around Moalboal.', 'Moalboal, Cebu', 1, NULL, NULL, 'approved', 0.00, 0, '[Warning 2026-09-17] Dispute #1: refund issued to tourist.\n[Warning 2026-09-28] Dispute #3: refund issued to tourist.\n', NULL, 0, NULL, NULL, 0, '2026-06-15 12:27:23', '2026-10-22 23:45:16', NULL, '2026-10-22 23:42:52', NULL, NULL, 18),
(6, 'Felix Baquerosa', 'felixbruce920@gmail.com', '$2y$10$tU4jxJbTXUf.kEsjkWGwzeTt.egYSbBSwkhvFGVSDnaiJWOSVrlyW', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-06-29 21:21:42', '2026-07-07 06:40:20', '2026-06-30 06:28:52', NULL, NULL, NULL, 0),
(11, 'john ivan laluna', 'johnivan558@gmail.com', '$2y$10$17d10r3BIiOvtMihQq4hxu9dJ4ipN6aMyzgRQ6eUI9bmJ2nhPEI4O', 'tourist', NULL, 'uploads/avatars/11/e4f66376abcd685d.jpeg', NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-02 09:54:35', '2026-09-29 09:14:15', '2026-09-02 10:14:56', NULL, NULL, NULL, 0),
(12, 'Jane De Leo', 'janethmauir@gmail.com', '$2y$10$ZDBVp/Ta4c3J3REK/uaXXOI2xkPlaCU56A00E6exFKNRBwpqsry5u', 'rental_provider', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 0.00, 0, NULL, '2026-09-29 11:36:42', 0, NULL, NULL, 0, '2026-09-16 18:24:44', '2026-10-19 18:18:03', '2026-10-18 17:09:51', '2026-10-18 17:09:51', NULL, NULL, 2),
(13, 'Google User', 'google_ad219a2e8778b055604e@social.guidemate.local', '$2y$10$PvGJg82k.5cGDhyOMEuZV.bxbP5FC4o49HpYmtdgC7X765iBfcPBq', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 19:23:02', '2026-09-29 09:14:53', '2026-09-16 19:26:57', NULL, 'google', 'demo_google-bee4fc1e-6038-4d4c-b345-923313014eb0', 0),
(14, 'Facebook User', 'facebook_2b99f4d0c198ed50e627@social.guidemate.local', '$2y$10$AZrgvytfyLnxFfytEcCmX.hMJgGWEv5g1pL/ZXIK4IflUVROnGTYK', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 19:23:15', '2026-09-29 09:14:51', '2026-09-27 13:37:50', NULL, 'facebook', 'demo_facebook-48a92cad-2f85-4635-8686-1bb37ad87ff1', 0),
(15, 'Jiji Plays', 'google_5d8acb71805db42e30ee@social.guidemate.local', '$2y$10$X5fIuCwd77MPxE3NxjjzWOvdoF7y7YxinZl2BGtk8CCpqZ4T5gm/C', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 20:09:40', '2026-09-29 09:14:49', '2026-09-16 21:18:04', NULL, 'google', '100827659670400175015', 0),
(16, 'Janeth Mauir', 'google_c50d84dbf58163b7f465@social.guidemate.local', '$2y$10$U4J505ac9y8Vj5HGWIximuNeFd/9SDbIsGM0EeQKqRQ2w.KLPnffu', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 20:25:54', '2026-09-29 09:14:43', '2026-09-27 14:23:56', NULL, 'google', '101669896341527826136', 0),
(17, 'Google User', 'google_a03ad608fa0ae6a9640b@social.guidemate.local', '$2y$10$CGRyWQpKEGtAPhJU31BT5e3/8XJPg2RMvITCzP91jnpFQK9Z5Z0Oa', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-16 20:28:29', '2026-09-29 09:14:47', '2026-09-16 21:07:47', NULL, 'google', '101982035606372835617', 0),
(18, 'Hotel Admin', 'chrisyongzon112@gmail.com', '$2y$10$9Ccj2ig8vtbp84APeffXN.6UseHrq2.lVXOLr6whZgp4wmCihKrBG', 'hotel_admin', NULL, NULL, 'Verified accommodation partner on GuideMate.', 'Cebu, Philippines', 1, NULL, NULL, 'approved', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-27 14:46:37', '2026-10-23 07:40:44', NULL, '2026-10-23 07:32:16', NULL, NULL, 5),
(20, 'johnivan', 'johnivan58@gmail.com', '$2y$10$4zfkIgrXXDjpPkMd.hkySOfofEqke/URdyuz36aE9WfODhlZWN7dC', 'tourist', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-09-28 06:22:18', '2026-09-29 09:14:18', '2026-09-28 06:47:58', NULL, NULL, NULL, 0),
(23, 'John Doe', 'johndoe@sample.com', '$2y$10$.E/WaVyUW9yC6rPHO6Ahn.8qzEBMc6MmQwC1EvyTc2o0HVFGo8efO', 'guide', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 0.00, 0, NULL, '2026-10-09 01:02:10', 0, NULL, NULL, 0, '2026-10-09 01:00:54', '2026-10-18 17:05:30', '2026-10-18 17:05:30', '2026-10-18 17:05:30', NULL, NULL, 3),
(46, 'Junril Ubas', 'samplesample@sample.com', '$2y$10$rH4bKRiYrByGdzhjcoWLeuNaC7JBi0N7F412ibNJJezU.ZmRq2uGS', 'hotel_admin', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'pending', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-10-14 18:36:29', '2026-10-18 17:18:56', '2026-10-18 17:18:56', '2026-10-18 17:18:56', NULL, NULL, 3),
(51, 'Christian Yongzon', 'christianyongzon143@gmail.com', '$2y$10$AyWQCkHq26Lu3/mf/ou4zeXvn9o80nAAtNGTLV5RHJEiCNVlaqIN.', 'tourist', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-10-18 17:39:05', '2026-10-23 07:39:35', '2026-10-23 07:39:35', NULL, NULL, NULL, 0),
(167, 'Miguel Yongzon', 'migyong@sample.com', '$2y$10$uabG9oJ9LICqWNaD8xoQAuzqD4ya4dNcRwR3mnutHLYtu78NynWAO', 'rental_provider', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'approved', 3000.00, 2, NULL, '2026-10-20 04:42:52', 0, NULL, NULL, 0, '2026-10-20 04:42:11', '2026-10-23 05:00:23', NULL, '2026-10-23 04:45:33', NULL, NULL, 6),
(369, 'johnivangwapo', 'johnivan5558@gmail.com', '$2y$10$KI1YnFTz93cTi1A9o7Up2ePlcWUeLd7aTJ7j.CEFZv2qA2DsEb3lm', 'tourist', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'none', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-10-21 08:33:42', '2026-10-21 08:50:14', '2026-10-21 08:50:14', NULL, NULL, NULL, 0),
(371, 'Christian Yongzon', 'ben@sample.com', '$2y$10$hX5X.FiV9ddTGOZseNFWcOWcbGSWQSeXV8GI6m1HpcFwxhcFKr2Yy', 'guide', '09280056394', NULL, 'ako diay si natoy na mahal na mahal si Yang', 'Minglanilla', 1, NULL, NULL, 'pending', 0.00, 0, NULL, '2026-10-23 01:12:32', 0, NULL, NULL, 0, '2026-10-22 13:52:18', '2026-10-23 01:13:12', NULL, '2026-10-23 00:18:26', NULL, NULL, 4),
(699, 'Eliza Yang', 'elyang@sample.com', '$2y$10$CiWZeAabpVfyvP/LbLwLGe5lMokrv6jDRWQWekUFBKjvdY0GAS89q', 'hotel_admin', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'pending', 0.00, 0, NULL, NULL, 0, NULL, NULL, 0, '2026-10-23 05:02:09', '2026-10-23 05:03:23', NULL, '2026-10-23 05:02:09', NULL, NULL, 1);

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
(72, 167, 'Honda', 'Click 125i V2', 2022, 'Scooter', '2022 Honda Click 125i V2 Automatic', '2022-honda-click-125i-v2-automatic', 'The vehicle is in excellent overall condition, both inside and out. The exterior body is free of any dents, scratches, or visible damage. Everything is fully functional and ready to go.', '[\"Automatic Transmission\"]', 'Minglanilla, Cebu', 'Purok Guyabano, Kilid Hopian', 1010.00, 'uploads/vehicles/167/6d69ffd5b4ee70ab.jpg', 'approved', 1, NULL, '2026-10-23 02:11:43', '2026-10-23 02:16:54', 1, '2026-10-20 04:49:14', '2026-10-23 02:16:54');

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
-- Indexes for table `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `listings_slug_unique` (`slug`),
  ADD KEY `listings_user_fk` (`user_id`),
  ADD KEY `listings_category_fk` (`category_id`),
  ADD KEY `listings_status_idx` (`status`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `app_feedback`
--
ALTER TABLE `app_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `conversation_settings`
--
ALTER TABLE `conversation_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dispute_files`
--
ALTER TABLE `dispute_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `guide_availability`
--
ALTER TABLE `guide_availability`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guide_documents`
--
ALTER TABLE `guide_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `listings`
--
ALTER TABLE `listings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `listing_images`
--
ALTER TABLE `listing_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `listing_schedules`
--
ALTER TABLE `listing_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `message_translations`
--
ALTER TABLE `message_translations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `promo_codes`
--
ALTER TABLE `promo_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `rental_requests`
--
ALTER TABLE `rental_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `rental_reviews`
--
ALTER TABLE `rental_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `review_images`
--
ALTER TABLE `review_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=703;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=247;

--
-- AUTO_INCREMENT for table `vehicle_images`
--
ALTER TABLE `vehicle_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

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
