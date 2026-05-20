-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 20, 2026 at 09:38 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `track_clock_hr`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `login_photo` varchar(255) DEFAULT NULL,
  `login_latitude` varchar(50) DEFAULT NULL,
  `login_longitude` varchar(50) DEFAULT NULL,
  `login_address` text DEFAULT NULL,
  `login_accuracy` varchar(20) DEFAULT NULL,
  `login_time` time DEFAULT NULL,
  `logout_photo` varchar(255) DEFAULT NULL,
  `logout_latitude` varchar(50) DEFAULT NULL,
  `logout_longitude` varchar(50) DEFAULT NULL,
  `logout_address` text DEFAULT NULL,
  `logout_accuracy` varchar(20) DEFAULT NULL,
  `logout_time` time DEFAULT NULL,
  `date` date DEFAULT NULL,
  `working_hours` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `login_photo`, `login_latitude`, `login_longitude`, `login_address`, `login_accuracy`, `login_time`, `logout_photo`, `logout_latitude`, `logout_longitude`, `logout_address`, `logout_accuracy`, `logout_time`, `date`, `working_hours`) VALUES
(1, 1, 'uploads/login_1772520399.png', '12.7501', '75.2052', NULL, NULL, '07:46:39', 'uploads/logout_1772538552.png', '12.3428', '76.6241', NULL, NULL, '12:49:12', '2026-03-03', ''),
(2, 1, 'uploads/login_1772520996.png', '12.7501', '75.2052', NULL, NULL, '07:56:36', 'uploads/logout_1772538552.png', '12.3428', '76.6241', NULL, NULL, '12:49:12', '2026-03-03', ''),
(3, 1, 'uploads/login_1772521071.png', '12.7501', '75.2052', NULL, NULL, '07:57:51', 'uploads/logout_1772538552.png', '12.3428', '76.6241', NULL, NULL, '12:49:12', '2026-03-03', ''),
(4, 1, 'uploads/login_1772533446.png', '12.3428', '76.6241', NULL, NULL, '11:24:06', 'uploads/logout_1772538552.png', '12.3428', '76.6241', NULL, NULL, '12:49:12', '2026-03-03', ''),
(5, 1, 'uploads/login_1772538534.png', '12.3428', '76.6241', NULL, NULL, '12:48:54', 'uploads/logout_1772538552.png', '12.3428', '76.6241', NULL, NULL, '12:49:12', '2026-03-03', ''),
(6, 1, 'uploads/login_1772539330.png', '12.3428', '76.6241', NULL, NULL, '13:02:10', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-03', ''),
(7, 1, 'uploads/login_1772539332.png', '12.3428', '76.6241', NULL, NULL, '13:02:12', 'uploads/logout_1772539344.png', '12.3428', '76.6241', NULL, NULL, '13:02:24', '2026-03-03', '0 Hours 0 Minutes'),
(8, 1, 'uploads/login_1772555759.png', '12.9807', '74.8023', NULL, NULL, '17:35:59', 'uploads/logout_1772556349.png', '12.9807', '74.8023', NULL, NULL, '17:45:49', '2026-03-03', '0 Hours 9 Minutes'),
(9, 1, 'uploads/login_1772608218.png', '12.9786', '77.364', NULL, NULL, '08:10:18', 'uploads/logout_1772644268.png', '12.9807', '74.8023', NULL, NULL, '18:11:08', '2026-03-04', '10 hours 0 minutes'),
(10, 1, 'uploads/login_1772608221.png', '12.9786', '77.364', NULL, NULL, '08:10:21', 'uploads/logout_1772644268.png', '12.9807', '74.8023', NULL, NULL, '18:11:08', '2026-03-04', '10 hours 0 minutes'),
(11, 1, 'uploads/login_1772608222.png', '12.9786', '77.364', NULL, NULL, '08:10:22', 'uploads/logout_1772644268.png', '12.9807', '74.8023', NULL, NULL, '18:11:08', '2026-03-04', '10 hours 0 minutes'),
(12, 1, 'uploads/login_1772618370.png', '12.9786', '77.364', NULL, NULL, '10:59:30', 'uploads/logout_1772644268.png', '12.9807', '74.8023', NULL, NULL, '18:11:08', '2026-03-04', '10 hours 0 minutes'),
(13, 1, 'uploads/login_1772618372.png', '12.9786', '77.364', NULL, NULL, '10:59:32', 'uploads/logout_1772644268.png', '12.9807', '74.8023', NULL, NULL, '18:11:08', '2026-03-04', '10 hours 0 minutes'),
(14, 1, 'uploads/login_1772618430.png', '12.9786', '77.364', NULL, NULL, '11:00:30', 'uploads/logout_1772644268.png', '12.9807', '74.8023', NULL, NULL, '18:11:08', '2026-03-04', '10 hours 0 minutes'),
(15, 1, 'uploads/login_1772618436.png', '12.9786', '77.364', NULL, NULL, '11:00:36', 'uploads/logout_1772644268.png', '12.9807', '74.8023', NULL, NULL, '18:11:08', '2026-03-04', '10 hours 0 minutes'),
(16, 1, 'uploads/login_1772618999.png', '12.9786', '77.364', NULL, NULL, '11:09:59', 'uploads/logout_1772644268.png', '12.9807', '74.8023', NULL, NULL, '18:11:08', '2026-03-04', '10 hours 0 minutes'),
(17, 1, 'uploads/login_1772644641.png', '12.9807', '74.8023', NULL, NULL, '22:47:21', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04', ''),
(18, 1, 'uploads/login_1772644643.png', '12.9807', '74.8023', NULL, NULL, '22:47:23', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04', ''),
(19, 1, 'uploads/login_1772645828.png', '12.9807', '74.8023', '', '', '18:37:08', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04', ''),
(20, 1, 'uploads/login_1772645830.png', '12.9807', '74.8023', '', '', '18:37:10', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04', ''),
(21, 1, 'uploads/login_1772645831.png', '12.9807', '74.8023', '', '', '18:37:11', 'uploads/logout_1772645853.png', '12.9807', '74.8023', 'NH66, Baikampady, Mangaluru taluk, Karnataka, 575019', '339032', '18:37:33', '2026-03-04', '0 Hours 0 Minutes'),
(22, 1, 'uploads/login_1772647281.png', '12.9807', '74.8023', '', '', '19:01:21', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04', ''),
(23, 1, 'uploads/login_1772647283.png', '12.9807', '74.8023', '', '', '19:01:23', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04', ''),
(24, 1, 'uploads/login_1772647285.png', '12.9807', '74.8023', '', '', '19:01:25', 'uploads/logout_1772647308.png', '12.9807', '74.8023', 'NH66, Baikampady, Mangaluru taluk, Karnataka, 575019, India', '339032', '19:01:48', '2026-03-04', '0 Hours 0 Minutes'),
(25, 1, 'uploads/login_1772730257.png', '13.3437', '74.7466', 'Kavi Muddana Marg, Brahmagiri, Udupi, Karnataka, 576101', '382092', '18:04:17', 'uploads/logout_1772730301.png', '13.3437', '74.7466', 'Kavi Muddana Marg, Brahmagiri, Udupi, Udupi taluku, Karnataka, 576101, India', '382092', '18:05:01', '2026-03-05', '0 Hours 0 Minutes'),
(26, 1, 'uploads/login_1772777942.png', '12.333393537764398', '76.69391022417179', 'Mysuru, Karnataka, India', '103', '07:19:02', 'uploads/logout_1772777991.png', '12.333393537764398', '76.69391022417179', 'Mysuru, Karnataka, India', '103', '07:19:51', '2026-03-06', '0 Hours 0 Minutes'),
(27, 1, 'uploads/login_1772783665.png', '12.9786', '77.364', 'Bengaluru, Karnataka, India', '187271', '08:54:25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-06', ''),
(28, 1, 'uploads/login_1772783762.png', '12.9786', '77.364', 'Bengaluru, Karnataka, India', '187271', '08:56:02', 'uploads/logout_1772783783.png', '12.333445707121118', '76.6939690811981', 'Mysuru, Karnataka, India', '71', '08:56:23', '2026-03-06', '0 Hours 0 Minutes'),
(29, 1, 'uploads/login_1773036467.png', '12.33352565338425', '76.69391712634807', 'Mysuru, Karnataka, India', '78', '07:07:47', 'uploads/logout_1773036589.png', '12.33352565338425', '76.69391712634807', 'Mysuru, Karnataka, India', '78', '07:09:49', '2026-03-09', '0 Hours 2 Minutes'),
(30, 1, 'uploads/login_1773038378.png', '12.33352565338425', '76.69391712634807', 'Ring Road, Udayagiri, Mysuru taluk, Karnataka, 570011', '78', '07:39:38', 'uploads/logout_1773038415.png', '12.333713180509374', '76.6939544673369', 'Mysuru, Karnataka, India', 'manual', '07:40:15', '2026-03-09', '0 Hours 0 Minutes'),
(31, 1, 'uploads/login_1773043838.png', '12.33353025', '76.69391250000001', 'Ring Road, Udayagiri, Mysuru taluk, Karnataka, 570011', '76', '09:10:38', 'uploads/logout_1773043890.png', '12.33353025', '76.69391250000001', 'Ring Road, Udayagiri, Mysuru taluk, Karnataka, 570011', '76', '09:11:30', '2026-03-09', '0 Hours 0 Minutes'),
(32, 1, 'uploads/login_1773049197.png', '12.333434154041832', '76.69385987394008', 'Udayagiri, Mysuru, Karnataka, 570001', '95', '10:39:57', 'uploads/logout_1773049212.png', '12.333434154041832', '76.69385987394008', 'Udayagiri, Mysuru, Karnataka, 570001', '95', '10:40:12', '2026-03-09', '0 Hours 0 Minutes'),
(33, 1, 'uploads/login_1773075500.png', '12.347052', '76.614571', '16, Vijayanagara Main Road, M2M Foods, Mysuru, Karnataka, 570016', '241', '17:58:20', 'uploads/logout_1773075528.png', '12.347052', '76.614571', '16, Vijayanagara Main Road, M2M Foods, Mysuru, Karnataka, 570016', '241', '17:58:48', '2026-03-09', '0 Hours 0 Minutes'),
(34, 1, 'uploads/login_1773078259.png', '12.347052', '76.614571', 'Manche Gowdana Koppalu, Vijayanagara Main Road, M2M Foods, Mysuru, Mysuru taluk, Karnataka, 570016, India', '241', '18:44:19', 'uploads/logout_1773078279.png', '12.347052', '76.614571', 'Manche Gowdana Koppalu, Vijayanagara Main Road, M2M Foods, Mysuru, Mysuru taluk, Karnataka, 570016, India', '241', '18:44:39', '2026-03-09', '0 Hours 0 Minutes'),
(35, 1, 'uploads/login_1773121763.png', '12.333483129965847', '76.69391730593104', 'Satagalli, Ring Road, Mysuru taluk, Karnataka, 570011, India', '83', '06:49:23', 'uploads/logout_1773121845.png', '12.333533', '76.693947', 'Satagalli, Ring Road, Mysuru taluk, Karnataka, 570011, India', '178', '06:50:45', '2026-03-10', '0 Hours 1 Minutes'),
(36, 1, 'uploads/login_1773133571.png', '12.333387926636595', '76.69388502296694', 'Udayagiri, Mysuru, Mysuru taluk, Karnataka, 570001, India', '78', '10:06:11', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-10', ''),
(37, 1, 'uploads/login_1773133708.png', '12.33354813153844', '76.69385134124205', 'Satagalli, Ring Road, Mysuru taluk, Karnataka, 570011, India', '92', '10:08:28', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-10', ''),
(38, 1, 'uploads/login_1773133919.png', '12.333508140677313', '76.69389791717859', 'Satagalli, Ring Road, Mysuru taluk, Karnataka, 570011, India', '85', '10:11:59', 'uploads/logout_1773133980.png', '12.333508140677313', '76.69389791717859', 'Satagalli, Ring Road, Mysuru taluk, Karnataka, 570011, India', '85', '10:13:00', '2026-03-10', '0 Hours 1 Minutes'),
(39, 1, 'uploads/login_1773135349.png', '12.333512979785517', '76.693832152257', 'Udayagiri, Mysuru, Mysuru taluk, Karnataka, 570001, India', '76', '10:35:49', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-10', ''),
(40, 1, 'uploads/login_1773206769.png', '12.333526893931662', '76.6939221708043', 'Satagalli, Ring Road, Mysuru taluk, Karnataka, 570011, India', '74', '06:26:09', 'uploads/logout_1773206789.png', '12.33348590065795', '76.69392850909462', 'Satagalli, Ring Road, Mysuru taluk, Karnataka, 570011, India', '78', '06:26:29', '2026-03-11', '0 Hours 0 Minutes'),
(41, 1, 'uploads/login_1773213071.png', '12.33350016642175', '76.69390887068333', 'Satagalli, Mysuru District, Karnataka, India', '90', '08:11:11', 'uploads/logout_1773213124.png', '12.333511093153314', '76.69384621498205', 'Satagalli, Mysuru District, Karnataka, India', '76', '08:12:04', '2026-03-11', '0 Hours 0 Minutes'),
(42, 1, 'uploads/login_1773639444.png', '12.33352565338425', '76.69391712634807', 'Satagalli, Mysuru District, Karnataka, India', '78', '06:37:24', 'uploads/logout_1773639607.png', '12.333530723729544', '76.69391844315246', 'Satagalli, Mysuru District, Karnataka, India', '72', '06:40:07', '2026-03-16', '0 Hours 2 Minutes'),
(43, 1, 'uploads/login_1773640608.png', '12.333543255228943', '76.69382064273601', 'Satagalli, Mysuru District, Karnataka, India', '95', '06:56:48', 'uploads/logout_1773640822.png', '12.3334865', '76.693893', 'Satagalli, Mysuru District, Karnataka, India', '114', '07:00:22', '2026-03-16', '0 Hours 3 Minutes'),
(44, 1, 'uploads/login_1773730156.png', '12.333556480405717', '76.69383010972798', 'Satagalli, Mysuru District, Karnataka, India', '88', '07:49:16', 'uploads/logout_1773730237.png', '12.333556480405717', '76.69383010972798', 'Satagalli, Mysuru District, Karnataka, India', '88', '07:50:37', '2026-03-17', '0 Hours 1 Minutes'),
(45, 1, 'uploads/login_1773730581.png', '12.333387584670318', '76.6938938023729', 'Satagalli, Mysuru District, Karnataka, India', '74', '07:56:21', 'uploads/logout_1773730634.png', '12.333387584670318', '76.6938938023729', 'Satagalli, Mysuru District, Karnataka, India', '74', '07:57:14', '2026-03-17', '0 Hours 0 Minutes'),
(46, 1, 'uploads/login_1774257048.png', '12.33352565338425', '76.69391712634807', 'Satagalli, Mysuru District, Karnataka, India', '78', '10:10:48', 'uploads/logout_1774257085.png', '12.33352565338425', '76.69391712634807', 'Satagalli, Mysuru District, Karnataka, India', '78', '10:11:25', '2026-03-23', '0 Hours 0 Minutes'),
(47, 1, 'uploads/login_1774334391.png', '12.33352565338425', '76.69391712634807', 'Satagalli, Mysuru District, Karnataka, India', '78', '07:39:51', 'uploads/logout_1774334426.png', '12.33351683469804', '76.69393608263861', 'Satagalli, Mysuru District, Karnataka, India', '76', '07:40:26', '2026-03-24', '0 Hours 0 Minutes'),
(48, 1, 'uploads/login_1774334962.png', '12.33353025', '76.69391250000001', 'Satagalli, Mysuru District, Karnataka, India', '76', '07:49:22', 'uploads/logout_1774334999.png', '12.33352565338425', '76.69391712634807', 'Satagalli, Mysuru District, Karnataka, India', '78', '07:49:59', '2026-03-24', '0 Hours 0 Minutes'),
(49, 1, 'uploads/login_1774423954.png', '12.33338143023197', '76.69384826872975', 'Kyathamaranahalli, Mysuru District, Karnataka, India', '89', '08:32:34', 'uploads/logout_1774423990.png', '12.33330200672957', '76.69377697897544', 'Kyathamaranahalli, Mysuru District, Karnataka, India', '97', '08:33:10', '2026-03-25', '0 Hours 0 Minutes'),
(50, 1, 'uploads/login_1774426403.png', '12.33348765674934', '76.69387535467112', 'Satagalli, Mysuru District, Karnataka, India', '105', '09:13:23', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25', ''),
(51, 1, 'uploads/login_1774504921.png', '12.346928748464398', '76.6146793595688', 'Manche Gowdana Koppalu, Mysuru District, Karnataka, India', '72', '07:02:01', 'uploads/logout_1774504966.png', '12.34693122252414', '76.61467837153465', 'Manche Gowdana Koppalu, Mysuru District, Karnataka, India', '72', '07:02:46', '2026-03-26', '0 Hours 0 Minutes'),
(52, 1, 'uploads/login_1774848878.png', '12.33348765674934', '76.69387535467112', 'Satagalli, Mysuru District, Karnataka, India', '105', '07:34:38', 'uploads/logout_1774848948.png', '12.33348765674934', '76.69387535467112', 'Satagalli, Mysuru District, Karnataka, India', '105', '07:35:48', '2026-03-30', '0 Hours 1 Minutes'),
(53, 1, 'uploads/login_1779262364.png', '12.3468465', '76.614727', 'Manche Gowdana Koppalu, Mysuru District, Karnataka, India', '212', '09:32:44', 'uploads/logout_1779262493.png', '12.3468465', '76.614727', 'Manche Gowdana Koppalu, Mysuru District, Karnataka, India', '212', '09:34:53', '2026-05-20', '0 Hours 2 Minutes');

-- --------------------------------------------------------

--
-- Table structure for table `managers`
--

CREATE TABLE `managers` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `managers`
--

INSERT INTO `managers` (`id`, `username`, `password`) VALUES
(1, 'admin', '1234');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `managers`
--
ALTER TABLE `managers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `managers`
--
ALTER TABLE `managers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
