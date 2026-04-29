-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2026 at 03:55 AM
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
-- Database: `smartenroll`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `user_role` varchar(50) NOT NULL DEFAULT '',
  `action` varchar(120) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` varchar(120) NOT NULL DEFAULT '',
  `details_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `user_role`, `action`, `entity_type`, `entity_id`, `details_json`, `created_at`) VALUES
(1, 1, 'finance', 'tuition_invoice_preview_emailed', 'tuition_invoice_preview', 'INV-7926', '{\"student_id\":\"20260015\",\"email\":\"work.ivansanchez@gmail.com\",\"amount\":5734,\"items\":[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":5734}]}', '2026-04-28 05:55:08'),
(2, 1, 'finance', 'tuition_payment_saved', 'tuition_payment', '30', '{\"student_id\":\"20260015\",\"school_year\":\"2025-2026\",\"amount_paid\":5734,\"balance_after\":57606,\"receipt_no\":\"INV-7926\",\"items\":[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":5734}]}', '2026-04-28 06:12:31'),
(3, 1, 'finance', 'tuition_payment_saved', 'tuition_payment', '31', '{\"student_id\":\"20260015\",\"school_year\":\"2025-2026\",\"amount_paid\":100,\"balance_after\":57506,\"receipt_no\":\"INV-7068\",\"items\":[{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":100}]}', '2026-04-28 06:20:47'),
(4, 1, 'finance', 'tuition_invoice_preview_emailed', 'tuition_invoice_preview', 'INV-7131', '{\"student_id\":\"20260015\",\"email\":\"work.ivansanchez@gmail.com\",\"payment_date\":\"2026-04-29\",\"amount\":5802,\"items\":[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":5802}]}', '2026-04-29 01:34:05');

-- --------------------------------------------------------

--
-- Table structure for table `batch_assignments`
--

CREATE TABLE `batch_assignments` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL DEFAULT 0,
  `student_id` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `grade_level` varchar(50) NOT NULL,
  `batch_name` varchar(50) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_assignments`
--

INSERT INTO `batch_assignments` (`id`, `enrollment_id`, `student_id`, `school_year`, `grade_level`, `batch_name`, `updated_at`) VALUES
(1, 25, '', '2026-2027', 'Toddler', 'Batch B', '2026-03-07 10:42:26'),
(6, 22, '', '2026-2027', 'Grade 4', 'Batch B', '2026-03-07 08:56:11'),
(8, 47, '2026009', '2025-2026', 'Kindergarten', 'Batch D', '2026-03-07 09:26:14'),
(18, 46, '2026008', '2025-2026', 'Kindergarten', 'Batch B', '2026-03-07 10:42:10'),
(21, 41, '2026003', '2025-2026', 'Toddler', 'Batch B', '2026-03-07 10:42:49'),
(22, 42, '2026004', '2025-2026', 'Toddler', 'Batch A', '2026-03-07 10:42:49'),
(23, 40, '2026002', '2025-2026', 'Casa', 'Batch C', '2026-03-07 10:43:12'),
(24, 44, '2026006', '2025-2026', 'Grade 6', 'Batch B', '2026-03-07 14:23:10'),
(25, 36, '', '2026-2027', 'Casa', 'Batch 2 (10:30AM to 12:30PM)', '2026-03-28 09:13:34'),
(26, 51, '20260013', '2025-2026', 'Casa', 'Batch 2 (10:30AM to 12:30PM)', '2026-03-29 06:33:59');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `grade_level` varchar(50) DEFAULT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `student_status` varchar(50) DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `learner_lname` varchar(100) DEFAULT NULL,
  `learner_fname` varchar(100) DEFAULT NULL,
  `learner_mname` varchar(100) DEFAULT NULL,
  `learner_ext` varchar(20) DEFAULT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `sex` varchar(20) DEFAULT NULL,
  `dob` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `mother_tongue` varchar(100) DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `municipality` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `street` varchar(150) DEFAULT NULL,
  `father_lname` varchar(100) DEFAULT NULL,
  `father_fname` varchar(100) DEFAULT NULL,
  `father_mname` varchar(100) DEFAULT NULL,
  `father_occ` varchar(100) DEFAULT NULL,
  `father_contact` varchar(50) DEFAULT NULL,
  `mother_lname` varchar(100) DEFAULT NULL,
  `mother_fname` varchar(100) DEFAULT NULL,
  `mother_mname` varchar(100) DEFAULT NULL,
  `mother_occ` varchar(100) DEFAULT NULL,
  `mother_contact` varchar(50) DEFAULT NULL,
  `mother_maiden` varchar(100) DEFAULT NULL,
  `guardian_type` varchar(50) DEFAULT NULL,
  `guardian_lname` varchar(100) DEFAULT NULL,
  `guardian_fname` varchar(100) DEFAULT NULL,
  `guardian_mname` varchar(100) DEFAULT NULL,
  `guardian_occ` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(50) DEFAULT NULL,
  `emergency1_name` varchar(150) DEFAULT NULL,
  `emergency1_relationship` varchar(100) DEFAULT NULL,
  `emergency1_contact` varchar(50) DEFAULT NULL,
  `emergency2_name` varchar(150) DEFAULT NULL,
  `emergency2_relationship` varchar(100) DEFAULT NULL,
  `emergency2_contact` varchar(50) DEFAULT NULL,
  `emergency3_name` varchar(150) DEFAULT NULL,
  `emergency3_relationship` varchar(100) DEFAULT NULL,
  `emergency3_contact` varchar(50) DEFAULT NULL,
  `special_needs` text DEFAULT NULL,
  `medication` varchar(10) DEFAULT NULL,
  `medication_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `grade_level`, `student_id`, `school_year`, `student_status`, `completion_date`, `learner_lname`, `learner_fname`, `learner_mname`, `learner_ext`, `nickname`, `sex`, `dob`, `age`, `mother_tongue`, `religion`, `email`, `province`, `municipality`, `barangay`, `street`, `father_lname`, `father_fname`, `father_mname`, `father_occ`, `father_contact`, `mother_lname`, `mother_fname`, `mother_mname`, `mother_occ`, `mother_contact`, `mother_maiden`, `guardian_type`, `guardian_lname`, `guardian_fname`, `guardian_mname`, `guardian_occ`, `guardian_contact`, `emergency1_name`, `emergency1_relationship`, `emergency1_contact`, `emergency2_name`, `emergency2_relationship`, `emergency2_contact`, `emergency3_name`, `emergency3_relationship`, `emergency3_contact`, `special_needs`, `medication`, `medication_details`, `created_at`) VALUES
(1, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'asd', 'asd', '', 'asfasf', 'Male', '12/12/2009', 16, 'asd', 'asd', 'w', '037700000', '037705000', '037705007', 'hasd', 'aszxc', 'asd', 'zxc', 'zxc', 'asd', 'zxc', 'zxc', 'asd', 'aszxc', 'asd', 'zxczx', 'father', '', '', '', '', '', 'asdas', 'asda', 'dvczx', 'zxcvxc', 'zxczx', 'asdasd', 'asdas', 'asdasd', 'zxczx', 'asdzxc', 'no', '', '2026-02-16 23:26:31'),
(2, 'Casa', NULL, NULL, NULL, NULL, 'Ambasa', 'Josie', 'Mae', '', 'asdasd', 'Male', '12/12/2009', 16, 'asd', 'asdad', 'ambasajosiemae@gmail.com', '042100000', '042111000', '042111016', 'asf', 'asfas', 'fasf', 'zxczx', 'asd', 'xczx', 'asdasd', 'zxc', 'zxc', 'asd', 'zxczxc', 'asdasd', 'father', '', '', '', '', '', 'asd', 'zxc', 'sad', 'asdasd', 'asda', 'zxczxc', 'zxc', 'zxczxc', 'asd', 'fdas', 'no', '', '2026-02-16 23:32:09'),
(3, 'Casa', NULL, NULL, NULL, NULL, 'asd', 'asd', 'asd', '', 'asda', 'Female', '12/12/2009', 16, 'asdxzc', 'asdasd', 'asasd', '035400000', '035411000', '035411015', 'asfasf', 'asdw', 'wqd', 'asd', 'qwe', 'sdf', 'as', 'asd', 'xcvxcv', 'asd', 'zxc', 'asd', 'father', '', '', '', '', '', 'xzv', 'xc', 'asdf', 'asd', 'asd', 'cvx', 'zxc', 'zxc', 'asf', 'asdxzc', 'no', '', '2026-02-16 23:39:24'),
(4, 'Grade 2', NULL, NULL, NULL, NULL, 'asd', 'asdw', 'qwd', '', 'asdw', 'Male', '12/12/2007', 18, 'ga', 'asd', 'qwe', '037700000', '037702000', '037702016', 'asd', 'gdss', 'asd', 'cb', 'asd', 'xcv', 'asd', 'cxv', 'asd', 'jhg', 'mn', 'bnm', 'mother', '', '', '', '', '', 'nb', 'j', 'mnblk', 'nbnm', 'n', 'lkj', ',m', 'kj', 'mnbmn', 'hkljlk', 'no', '', '2026-02-17 00:32:46'),
(5, 'Grade 2', NULL, NULL, NULL, NULL, 'Ambasa', 'Josie', 'Mae', '', 'qrr', 'Male', '12/12/2007', 18, 'fas', 'cxvxcv', 'ambasajosiemae@gmail.com', '035400000', '035415000', '035415013', 'gasd', 'asgsd', 'gasf', 'asd', 'vxcv', 'asd', 'zxc', 'gas', 'zxc', 'asd', 'vxcv', 'asf', 'mother', '', '', '', '', '', 'asfzxc', 'sad', 'vxc', 'bv', 'zxc', 'asd', 'asd', 'asd', 'xcv', 'asfcx', 'no', '', '2026-02-17 00:36:55'),
(6, 'Casa', NULL, NULL, NULL, NULL, 'asd', 'asd', 'asd', '', 'afs', 'Male', '11/11/2007', 18, 'asd', 'asd', 'ambasajosiemae@gmail.com', '035400000', '035412000', '035412003', 'asf', 'kjhkj', 'jhkh', 'kjhkjh', 'nbm', 'nb,mn', ',mnj', 'khkh', 'mnbnm', 'kjhkj', 'nmbmnl', 'lkjnb', 'mother', '', '', '', '', '', 'nbjk', 'bmnbjk', 'hkjh', 'hbmn', 'nmbnmb', 'jkhjk', 'kjhkjh', 'jkh', 'nmbmn', 'fbhsd', 'no', '', '2026-02-17 01:14:21'),
(7, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'zxc', 'as', '', 'asfasf', 'Male', '11/11/2006', 19, 'xcv', 'asd', 'zxc', '034900000', '034908000', '034908012', 'xcvsad', 'aszxc', 'asdxzc', 'asdzxc', 'asdzcx', 'asdzxc', 'asd', 'zxc', 'sdg', 'zxc', 'asf', 'cvb', 'mother', '', '', '', '', '', 'cvb', 'cv', 'sdf', 'xcv', 'cvb', 'cvcvb', 'cv', 'xcv', 'asd', 'sdg', 'no', '', '2026-02-17 01:15:43'),
(8, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'asxzc', 'asd', '', 'asdas', 'Male', '11/11/2006', 19, 'vcbsdf', 'xcva', 'zxca', '034900000', '034914000', 'San Juan', 'zva', 'zxca', 'cv', 'asd', 'nbc', 'asd', 'v', 'd', 'n', 'f', 'v', 'g', 'mother', '', '', '', '', '', 'b', 'x', 'c', 'v', 'k', 'j', 'l', 'g', 'n', 'n', 'no', '', '2026-02-17 01:30:14'),
(9, 'Grade 2', NULL, NULL, NULL, NULL, 'asd', 'asd', 'asd', '', 'zxc', 'Male', '11/11/2006', 19, 'asfv', 'xcv', 'asd', 'Tarlac', 'Moncada', 'Camposanto 1 - Sur', 'cvb', 'asd', 'k', 'n', 'k', 'n', 'k', 'n', 'j', 'g', 'i', 'h', 'mother', 'k', 'n', 'j', 'g', 'i', 'k', 'm', 'l', 'g', 'i', 'y', 'j', 'u', 'uj', 'sd', 'no', '', '2026-02-17 02:01:51'),
(10, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'zx', 'as', '', 'zxc', 'Male', '11/11/2006', 19, 'asd', 'zxca', 'asd', 'Nueva Ecija', 'Jaen', 'San Josef', 'as', 'asc', 'zxc', 'asd', 'zxc', 'asf', 'l', 'k', 'm', 'h', 'n', 'k', 'father', 'asc', 'zxc', 'asd', 'zxc', 'asf', 'asd', 'xzcv', 'z', 'asd', 'vc', 'z', 'zx', 'v', 's', 'bcvb', 'no', '', '2026-02-17 02:08:30'),
(11, 'Casa', NULL, NULL, NULL, NULL, 'a2wa', 'asd', 'ad', 'Jr', 'asd', 'Male', '08/10/2006', 19, 'wer', 'wrs', 'aer', 'Aurora', 'Dingalan', 'Dikapanikian', '231q2', 'edrtg', 'srf', 'srf', 'sref', 'sfr', 'sre', 'swr5', 'swre', 'wsr', 'serf', 'wsrf', 'mother', 'sre', 'swr5', 'swre', 'wsr', 'serf', 'wer', 'sdf', '754', 'wrt', 'srtgf', '45', 'stfg', 'gx', '546', 'n/a', 'no', '', '2026-02-17 02:11:53'),
(12, 'Toddler', NULL, NULL, NULL, NULL, 'Ambasa', 'Josie', 'Mae', '', 'ghjgh', 'Male', '10/09/2007', 18, 'zfs', 'dfg', 'ambasajosiemae@gmail.com', 'Bataan', 'Samal', 'Sapa', 'sdfgch', 'dydg', 'fgh', 'ser', 'kjh', 'yf', 'df', 'oy', 'dfg', 'yu', 'sr', 'uy', 'father', 'dydg', 'fgh', 'ser', 'kjh', 'yf', 'dfg', 'dfg', 'dfg', 'vbh', 'vb', 'fgh', 'fgh', 'fgh', 'bvh', 'dfg', 'no', '', '2026-02-24 07:12:04'),
(13, '', NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', 0, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'no', '', '2026-02-24 07:38:24'),
(14, '', NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', 0, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'no', '', '2026-02-24 07:38:45'),
(15, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'asd', 'zxc', '', 'asc', 'Male', '12/12/2009', 16, 'zxc', 'asd', 'ambasajosiemae@gmail.com', '', '', 'Amangbangan', 'cvbcvb', 'bv', 'kjhmn', 'mnbjk', 'nmb', 'jknmb', 'nmbk', 'jkhmn', 'jkh', 'nmb', 'jkh', '.,,mn', 'other', 'jm,m', 'nmb.', 'n,mnkl', 'bn,', 'm,bn', 'xcv', 'xcv', 'sdf', 'sdf', 'as', 'vbn', 'vbn', 'fd', 'sadf', 'asd', 'no', '', '2026-02-24 10:02:09'),
(16, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'asd', 'zxc', '', 'asc', 'Male', '12/12/2009', 16, 'zxc', 'asd', 'ambasajosiemae@gmail.com', '', '', 'Amangbangan', 'cvbcvb', 'bv', 'kjhmn', 'mnbjk', 'nmb', 'jknmb', 'nmbk', 'jkhmn', 'jkh', 'nmb', 'jkh', '.,,mn', 'other', 'jm,m', 'nmb.', 'n,mnkl', 'bn,', 'm,bn', 'xcv', 'xcv', 'sdf', 'sdf', 'as', 'vbn', 'vbn', 'fd', 'sadf', 'asd', 'no', '', '2026-02-24 10:02:59'),
(17, 'Grade 2', NULL, NULL, NULL, NULL, 'asd', 'cxv', 'asd', '', 'dfg', 'Female', '12/12/1998', 27, 'cvb', 'asd', 'cvb', '', '', 'Doclong 1', 'asf', 'xcb', 'asd', 'cvb', 'asd', 'cvb', 'asf', 'n', 'asd', 'vb', 'asd', 'cvb', 'mother', 'asf', 'n', 'asd', 'vb', 'asd', 'cvb', 'vb', 'as', 'cvb', 'df', 'g', 'cvb', 'as', 'asd', 'as', 'no', '', '2026-02-24 10:57:52'),
(18, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'zxc', 'asd', '', 'asd', 'Male', '12/12/2009', 16, 'asd', 'xcv', 'ambasajosiemae@gmail.com', '', '', 'Malinao', 'asd', 'qw', 'asd', 'zx', 'asd', 'xcv', 'asd', 'cvx', 'asd', 'zxc', 'asd', 'as', 'father', 'qw', 'asd', 'zx', 'asd', 'xcv', 'fa', 'zx', 'b', 'asd', 'as', 'f', 'bcx', 'vc', 'asd', 'h', 'no', '', '2026-02-24 11:49:52'),
(19, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'xzc', 'asd', '', 'asd', 'Male', '12/12/2007', 18, 'asd', 'zxc', 'ambasajosiemae@gmail.com', '', '', 'Doña Imelda', 'asd', 'wq', 'asd', 'zxc', 'asd', 'zxc', 'asd', 'xc', 'asd', 'asd', 'zxc', 'asd', 'mother', 'asd', 'xc', 'asd', 'asd', 'zxc', 'zxc', '123', 'bx', '123', 'sd', 'asd', 'ds', 'sad', '123', 'asd', 'no', '', '2026-02-24 12:16:32'),
(20, 'Toddler', NULL, NULL, NULL, NULL, 'asd', 'zxc', 'sdf', '', 'asf', 'Male', '12/12/2008', 17, 'asd', 'cvb', 'aasd', '', '', 'Palanginan', 'saf', 'cvb', 'as', 'cv', 'asd', 'czx', 'vcx', 'zxc', 'czx', 'fv', 'das', 'cv', 'father', 'cvb', 'as', 'cv', 'asd', 'czx', 'sda', 'ads', '132', 'v', 'dsf', 'r4', 'f', 'fdg', '65', 'fsa', 'yes', 'cxcv', '2026-02-24 13:26:26'),
(21, 'Casa', NULL, NULL, NULL, NULL, 'das', 'cxv', 'ads', '', 'asd', 'Male', '12/12/2008', 17, 'dfg', 'bv', 'ambasajosiemae@gmail.com', 'Isabela', 'Cordon', 'Osmena', 'asdasd', 'asdasd', 'sadf', 'vcgb', 'asd', 'fasdasd', 'asd', 'awasd', 'asd', 'xc', 'asd', 'asd', 'father', 'asdasd', 'sadf', 'vcgb', 'asd', 'fasdasd', 'asg', 'sfd', 'cvb', '123', 'sdf', 'asd', 'asd', 'as', 'a123', 'asd', 'yes', 'cvb', '2026-02-24 13:33:48'),
(22, 'Grade 4', NULL, '2026-2027', NULL, '2026-02-24', 'Ambasa', 'Josie', 'Mae', '', 'asd', 'Male', '12/29/2006', 19, 'asd', 'gdf', 'ambasajosiemae@gmail.com', 'Zambales', 'Palauig', 'San Juan', 'qwasd', 'qwd', 'asd', 'asd', 'xcv', 'asd', 'xcv', 'sdf', 'vb', 'asd', 'vb', 'asd', 'mother', 'xcv', 'sdf', 'vb', 'asd', 'vb', 'as', 'vb', '324', 'asd', 'vcx', '123', 'asd', 'vc', '123', 'asd', 'yes', 'cvb', '2026-02-24 13:50:33'),
(25, 'Toddler', NULL, '2026-2027', NULL, '2026-03-03', 'asd', 'asd', 'asd', '', 'zxc', 'Male', '12/12/2006', 19, 'asd', 'sdf', 'restonjc1@gmail.com', 'Pampanga', 'Porac', 'Manibaug Paralaya', '703, 6th Street', 'asd', 'zxc', 'asd', 'dfg', '09123456789', 'asd', 'asd', 'asd', 'xcv', '09123456789', 'qwd', 'mother', 'asd', 'asd', 'asd', 'xcv', '09123456789', 'asd', 'asd', '09123456789', 'awd', 'awd', '09123456789', 'asd', 'asd', '09123456789', 'asd', 'no', '', '2026-03-03 05:40:18'),
(26, 'Toddler', NULL, '2026-2027', NULL, '2026-03-03', 'asd', 'zxc', 'asd', '', 'asd', 'Male', '12/12/2008', 17, 'asdf', 'xcvbasd', 'restonjc1@gmail.com', 'Pampanga', 'City of San Fernando', 'Lourdes', '703, 6th Street', 'asd', 'asd', 'asd', 'awd', '09123456789', 'asd', 'asdf', 'afs', 'db', '09123456789', 'asd', 'father', 'asd', 'asd', 'asd', 'awd', '09123456789', 'asd', 'asd', '09123456789', 'asd', 'asd', '09123456789', 'vb', 'asd', '09123456789', 'asd', 'no', '', '2026-03-03 05:48:03'),
(27, 'Toddler', NULL, '2026-2027', NULL, '2026-03-03', 'asd', 'zxc', 'asd', '', 'asf', 'Male', '12/11/2000', 25, 'asd', 'sdg', 'restonjc1@gmail.com', 'Pampanga', 'Minalin', 'San Francisco 1st', '703, 6th Street', 'asd', 'szxc', 'dfhg', 'dfb', '09123456789', 'asd', 'xcvb', 'asd', 'dfgh', '09123456789', 'asf', 'father', 'asd', 'szxc', 'dfhg', 'dfb', '09123456789', 'asd', 'asfg', '09123456789', 'dfas', 'asd', '09123456789', 'sdg', 'asf', '09123456789', 'dfh', 'no', '', '2026-03-03 05:49:45'),
(28, 'Toddler', '2026007123', NULL, NULL, '2026-03-03', 'asd', 'sdf', 'asd', NULL, 'asd', 'Male', '10/10/2005', 20, 'asd', 'asf', 'restonjc1@gmail.com', 'Pampanga', 'Masantol', 'Puti', '703, 6th Street', 'asd', 'sdg', 'asd', 'asf', '09123456789', 'asfg', 'ga', 'asf', 'asf', '09123456789', 'ags', 'father', 'asd', 'sdg', 'asd', 'asf', '09123456789', 'asf', 'asf', '09123456789', 'asf', 'asd', '09123456789', 'asd', 'asd', '09123456789', 'asf', 'no', NULL, '2026-03-03 05:54:00'),
(29, 'Toddler', '2026008743', NULL, NULL, '2026-03-03', 'asf', 'asd', 'asf', NULL, 'asd', 'Male', '09/08/2005', 20, 'awf', 'as', 'restonjc1@gmail.com', 'Pampanga', 'Masantol', 'Nigui', '703, 6th Street', 'asd', 'awd', 'asf', 'awd', '09925212820', 'asd', 'xcvasdf', 'asd', 'asf', '09925212820', 'asd', 'father', 'asd', 'awd', 'asf', 'awd', '09925212820', 'asdf', 'asd', '09925212820', 'asf', 'fas', '09925212820', 'asd', 'fas', '09925212820', 'asf', 'no', NULL, '2026-03-03 05:56:31'),
(30, 'Toddler', '2026007494', NULL, NULL, '2026-03-03', 'awd', 'asd', 'wda', NULL, 'awd', 'Male', '05/02/2003', 22, 'qwd', 'asd', 'restonjc1@gmail.com', 'Pampanga', 'Macabebe', 'San Isidro', '703, 6th Street', 'qawd', 'asd', 'awd', 'asdw', '09925212820', 'awd', 'asd', 'qwd', 'aw', '09925212820', 'awd', 'father', 'qawd', 'asd', 'awd', 'asdw', '09925212820', 'awd', 'asd', '09925212820', 'awd', 'aw', '09925212820', 'ad', 'awd', '09925212820', 'awd', 'no', NULL, '2026-03-03 06:01:56'),
(31, 'Toddler', '2026006603', NULL, NULL, '2026-03-03', 'asd', 'asfcvb', 'asd', NULL, 'asf', 'Male', '01/01/2000', 26, 'asf', 'asf', 'restonjc1@gmail.com', 'Pampanga', 'Mexico', 'Gandus', '703, 6th Street', 'asf', 'ag', 'asd', 'asf', '09925212820', 'asf', 'awf', 'afs', 'awf', '09925212820', 'awf', 'father', 'asf', 'ag', 'asd', 'asf', '09925212820', 'aw', 'awd', '09925212820', 'asd', 'awd', '09925212820', 'awd', 'asd', '09925212820', 'awd', 'no', NULL, '2026-03-03 06:08:51'),
(32, 'Toddler', '2026009480', NULL, NULL, '2026-03-03', 'asd', 'awd', 'asd', NULL, 'qwd', 'Male', '02/01/2005', 21, 'awd', 'asd', 'restonjc1@gmail.com', 'Pampanga', 'Porac', 'Manibaug Pasig', '703, 6th Street', 'aw', 'asd', 'qawe', 'awd', '09925212820', 'awd', 'awd', 'awd', 'asd', '09925212820', 'asdaw', 'father', 'aw', 'asd', 'qawe', 'awd', '09925212820', 'asd', 'awd', '09925212820', 'aw', 'asd', '09925212820', 'aw', 'awd', '09925212820', 'asd', 'no', NULL, '2026-03-03 06:15:08'),
(33, 'Brave', '2026000033', NULL, NULL, '2026-03-03', 'awd', 'asd', 'awd', NULL, 'asfg', 'Male', '02/05/2003', 23, 'asd', 'asdf', 'restonjc1@gmail.com', 'Pampanga', 'Mexico', 'Lagundi', '703, 6th Street', 'asd', 'sdfbv', 'sd', 'asf', '09925212820', 'asfd', 'gds', 'asd', 'ag', '09925212820', 'awd', 'father', 'asd', 'sdfbv', 'sd', 'asf', '09925212820', 'asd', 'qwr', '09925212820', 'asd', 'g', '09925212820', 'wf', 'asf', '09925212820', 'awd', 'no', NULL, '2026-03-03 06:18:54'),
(34, 'Casa', '2026000034', NULL, NULL, '2026-03-03', 'fgh', 'asd', 'awef', NULL, 'adw', 'Female', '02/02/2005', 21, 'awd', 'asd', 'restonjc1@gmail.com', 'Pampanga', 'Porac', 'Mitla Proper', '703, 6th Street', 'srg', 'awf', 'sgr', 'awd', '09925212820', 'aw', 'awf', 'awf', 'sdg', '09925212820', 'awd', 'father', 'srg', 'awf', 'sgr', 'awd', '09925212820', 'awf', 'awd', '09925212820', 'asf', 'awd', '09925212820', 'awe', 'asd', '09925212820', 'qwd', 'no', NULL, '2026-03-03 06:21:55'),
(35, 'Toddler', '2026000035', NULL, NULL, '2026-03-03', 'asd', 'afaw', 'aw', NULL, 'asf', 'Female', '02/03/2005', 21, 'aseg', 'aw', 'restonjc1@gmail.com', 'Pampanga', 'Mexico', 'Gandus', '703, 6th Street', 'asd', 'awf', 'gas', 'awdf', '09925212820', 'awd', 'awd', 'awd', 'awd', '09925212820', 'aawd', 'father', 'asd', 'awf', 'gas', 'awdf', '09925212820', 'asd', 'awd', '09925212820', 'awf', 'gf', '09925212820', 'awf', 'awf', '09925212820', 'qw', 'no', NULL, '2026-03-03 06:25:20'),
(36, 'Casa', NULL, '2026-2027', NULL, '2026-03-03', 'asd', 'awf', 'awf', '', 'awf', 'Male', '03/02/2006', 20, 'awf', 'awd', 'restonjc1@gmail.com', 'Pampanga', 'Masantol', 'San Isidro Anac', '703, 6th Street', 'awf', 'awf', 'awf', 'awd', '09925212820', 'aw', 'adfg', 'awf', 'agw', '09925212820', 'awdf', 'father', 'awf', 'awf', 'awf', 'awd', '09925212820', 'awf', 'awd', '09925212820', 'aw', 'awfg', '09925212820', 'aw', 'aw', '09925212820', 'awd', 'no', '', '2026-03-03 06:26:50'),
(37, 'Toddler', '2026009481', NULL, NULL, '2026-03-03', 'asd', 'sdv', 'asd', NULL, 'qawd', 'Male', '01/04/2006', 20, 'sdg', 'aw', 'restonjc1@gmail.com', 'Pampanga', 'Masantol', 'Sagrada', '703, 6th Street', 'g', 'a', 'awd', 'g', '09925212820', 'g', 'awd', 'gs', 'awd', '09925212820', 'awd', 'father', 'g', 'a', 'awd', 'g', '09925212820', 'aw', 'awf', '09925212820', 'fawe', 'awf', '09925212820', 'sd', 'awf', '09925212820', 'gew', 'no', NULL, '2026-03-03 06:44:00'),
(38, 'Grade 6', '2026009482', NULL, NULL, '2026-03-03', 'asd', 'awfd', 'awd', NULL, 'awf', 'Male', '02/01/2003', 23, 'awf', 'eg', 'admin@gmail.com', 'Pangasinan', 'City of Alaminos', 'Bisocol', 'awf', 'asd', 'awd', 'awf', 'awf', '09925212820', 'awf', 'awf', 'asf', 'awd', '09925212820', 'awf', 'father', 'asd', 'awd', 'awf', 'awf', '09925212820', 'awf', 'awfasf', '09925212820', 'awd', 'awd', '09925212820', 'awf', 'awd', '09925212820', 'awf', 'no', NULL, '2026-03-03 06:45:14'),
(39, 'Toddler', '2026001', NULL, NULL, '2026-03-03', 'asd', 'awd', 'asd', '', 'qwfd', 'Male', '03/02/2006', 20, 'qwf', 'asdf', 'restonjc1@gmail.com', 'Pampanga', 'Masantol', 'San Isidro Matua (Pob.)', '703, 6th Street', 'awf', 'awf', 'asd', 'wfq', '09925212820', 'awd', 'awd', 'awf', 'asd', '09925212820', 'awf', 'father', 'awf', 'awf', 'asd', 'wfq', '09925212820', 'qwf', 'awd', '09925212820', 'awsds', 'asd', '09925212820', 'wfaf', 'awf', '09925212820', 'asf', 'no', NULL, '2026-03-03 06:50:34'),
(40, 'Casa', '2026002', '2025-2026', NULL, '2026-03-03', 'asd', 'awf', 'awf', '', 'asf', 'Male', '03/02/2005', 21, 'awf', 'asf', 'restonjc1@gmail.com', 'Pampanga', 'Masantol', 'San Isidro Matua (Pob.)', '703, 6th Street', 'asd', 'awf', 'asf', 'awf', '09925212820', 'awf', 'awf', 'asd', 'awf', '09925212820', 'awd', 'father', 'asd', 'awf', 'asf', 'awf', '09925212820', 'asf', 'aw', '09925212820', 'awf', 'aw', '09925212820', 'awf', 'awd', '09925212820', 'awf', 'no', NULL, '2026-03-03 06:53:45'),
(41, 'Toddler', '2026003', '2025-2026', NULL, '2026-03-03', 'asd', 'af', 'awf', '', 'afw', 'Male', '03/02/2005', 21, 'awg', 'awf', 'restonjc1@gmail.com', 'Pampanga', 'Magalang', 'San Isidro', '703, 6th Street', 'awfsgd', 'sdgaw', 'awf', 'awf', '09925212820', 'awf', 'sdf', 'awd', 'as', '09925212820', 'g', 'father', 'awfsgd', 'sdgaw', 'awf', 'awf', '09925212820', 'awg', 'a', '09925212820', 'awd', 'awf', '09925212820', 'awf', 'awf', '09925212820', 'awf', 'no', '', '2026-03-03 07:03:11'),
(42, 'Toddler', '2026004', '2025-2026', NULL, '2026-03-03', 'awd', 'awf', 'asf', '', 'awf', 'Male', '02/03/2006', 20, 'awf', 'awfg', 'restonjc1@gmail.com', 'Pampanga', 'Masantol', 'Puti', '703, 6th Street', 'aw', 'wf', 'awf', 'awg', '09925212820', 'awd', 'awfg', 'aw', 'awd', '09925212820', 'faw', 'other', 'agw', 'awas', 'aw', 'awf', '09925212820', 'awf', 'awg', '09925212820', 'awd', 'awf', '09925212820', 'awf', 'awfg', '09925212820', 'awf', 'no', '', '2026-03-03 07:11:07'),
(43, 'Toddler', '2026005', '2025-2026', NULL, '2026-03-03', 'awf', 'asf', 'aw', 'Sr', 'asfawf', 'Male', '03/02/2006', 20, 'awf', 'fa', 'restonjc1@gmail.com', 'Pampanga', 'Mabalacat City', 'Dapdap', '703, 6th Street', 'afw', 'as', 'awf', 'asd', '09925212820', 'adf', 'awg', 'awf', 'afw', '09925212820', 'awf', 'father', 'afw', 'as', 'awf', 'asd', '09925212820', 'sad', 'awg', '09925212820', 'adsfg', 'asd', '09925212820', 'asd', 'awf', '09925212820', 'awf', 'yes', 'awg', '2026-03-03 08:14:15'),
(44, 'Grade 6', '2026006', '2025-2026', NULL, '2026-03-07', 'Sanchez', 'Ivan', 'Umali', '', 'vani', 'Male', '07/02/2005', 20, 'Kapmpangan', 'Roman Catholic', 'work.ivansanchez@gmail.com', 'Pampanga', 'Mabalacat City', 'Dapdap', 'Brgy. Dapdap Mabalacat City Pampanga', 'avs', 'gd', 'asf', 'fas', '09123456789', 'acx', 'agas', 'c', 'ga', '09123456789', 'afs', 'father', 'avs', 'gd', 'asf', 'fas', '09123456789', 'vxz', 'asd', '09123456789', 'xc', 'afs', '09123456789', 'as', 'as', '09123456789', 'af', 'no', '', '2026-03-07 07:01:11'),
(45, 'Grade 2', '2026007', '2021-2022', NULL, '2022-03-03', 'faw', 'as', 'afw', '', 'as', 'Male', '12/12/2008', 17, 'awf', 'fawawf', 'ambasajosiemae@gmail.com', 'Cagayan', 'Aparri', 'Centro 8 (Pob.)', 'awf', 'ds', 'aw', 'faw', 'das', '09123456789', 'wav', 'awd', 'afs', 'wfa', '09123456789', 'wa', 'father', 'ds', 'aw', 'faw', 'das', '09123456789', 'wfaasd', 'waf', '09123456789', 'waf', 'wfa', '09123456789', 'waf', 'aw', '09123456789', 'awf', 'no', '', '2026-03-07 07:47:56'),
(46, 'Kindergarten', '2026008', '2025-2026', NULL, '2026-03-07', 'Ambasa', 'Josie', 'Mae', '', 'siemae', 'Female', '06/18/2006', 19, 'Taglish', 'RC', 'ambasajosiemae@gmail.com', 'Bataan', 'Limay', 'Poblacion', 'sdawdasd', 'sdg', 'awf', 'afw', 'afw', '09111111111', 'ad', 'fa', 'afw', 'wa', '09111111111', 'wfa', 'father', 'sdg', 'awf', 'afw', 'afw', '09111111111', 'a', 'fwa', '09111111111', 'g', 'as', '09111111111', 'afs', 'faw', '09111111111', 'wfa', 'no', '', '2026-03-07 09:01:22'),
(47, 'Kindergarten', '2026009', '2025-2026', NULL, '2026-03-07', 'Salunga', 'Josie Mae', 'Ambasa', '', 'as', 'Female', '06/18/2006', 19, 'faw', 'as', 'ambasajosiemae@gmail.com', 'Quirino', 'Saguday', 'Magsaysay (Pob.)', 'asf', 'awf', 'faw', 'sa', 'wfa', '09123456789', 'wfa', 'fas', 'wf', 'fas', '09123456789', 'fas', 'father', 'awf', 'faw', 'sa', 'wfa', '09123456789', 'sad', 'fwa', '09123456789', 'ag', 'asf', '09123456789', 'waf', 'awf', '09123456789', 'fwa', 'no', '', '2026-03-07 09:11:37'),
(48, 'Toddler', '20260010', '2025-2026', NULL, '2026-03-07', 'asd', 'wad', 'fw', '', 'aw', 'Female', '12/12/2006', 19, 'awf', 'as', 'ambasajosiemae@gmail.com', 'Pangasinan', 'Anda', 'Macandocandong', 'asf', 'aw', 'asd', 'wad', 'asd', '09123456789', 'asd', 'fwa', 'fwa', 'asd', '09123456789', 'awf', 'mother', 'asd', 'fwa', 'fwa', 'asd', '09123456789', 'as', 'aw', '09123456789', 'gw', 'wda', '09123456789', 'aw', 'fw', '09123456789', 'fwa', 'no', '', '2026-03-07 14:02:36'),
(49, 'Toddler', '20260011', '2025-2026', NULL, '2026-03-26', 'Sanchez', 'Ivan', 'Umali', '', 'vani', 'Male', '07/02/2005', 20, 'Kapampangan', 'Catholic', 'work.ivansanchez@gmail.com', 'Pampanga', 'Mabalacat City', 'Dapdap', 'asdw3312', 'sd', 'xs', 'g', 'asd', '09123456789', 'waf', 'fqrw', 'afs', 'qwr', '09123456789', 'sfa', 'father', 'sd', 'xs', 'g', 'asd', '09123456789', 'asgf', 'hs', '09123456789', 'asd', 'asfqr', '09123456789', 'awg', 'qr', '09123456789', 'gas', 'no', '', '2026-03-26 10:52:13'),
(50, 'Casa', '20260012', '2025-2026', NULL, '2026-03-26', 'David', 'j', 'a', '', 'qw', 'Female', '05/27/2009', 16, 'asd', 'aefw', 'work.ivansanchez@gmail.com', 'Biliran', 'Biliran', 'Busali', 'asd', 'awg', 'awd', 'wfa', 'wf', '09123456789', 'agbh', 'aw', 'ghaw', 'aw', '09123456789', 'af', 'mother', 'agbh', 'aw', 'ghaw', 'aw', '09123456789', 'gh', 'gaw', '09123456789', 'aw', 'asd', '09123456789', 'agw', 'wg', '09123456789', 'awg', 'no', '', '2026-03-26 12:11:58'),
(51, 'Casa', '20260013', '2025-2026', NULL, '2026-03-29', 'slng', 'mj', 'a', '', 'sie', 'Female', '06/18/2006', 19, 'taglish', 'catholic', 'salungajosiemae@gmail.com', 'Pampanga', 'Mabalacat City', 'Dapdap', '015P.BURGOS ST. MABALACAT', 'Salunga', 'josie', 'b', 'ofw', '09876543211', 'ambasa', 'amor', 'l', 'housewife', '09357236154', 'amor', 'mother', 'ambasa', 'amor', 'l', 'housewife', '09357236154', 'sdg', 'ghsd', '09357236154', 'seh', 'awf', '09357236154', 'seg', 'awg', '09357236154', 'sira ulo', 'no', '', '2026-03-29 06:33:25'),
(52, 'Kindergarten', '20260014', '2025-2026', NULL, '2026-04-13', 'De Guzman', 'Jomer', 'Rivera', '', 'jomer', 'Male', '06/24/2005', 20, 'Kapampangan', 'three', 'jomerrdeguzman@gmail.com', 'Pampanga', 'Mabalacat City', 'Dapdap', '14th Street Brgy. Dapdap', 'nsdg', 'ha', 'awg', 'sdg', '09123456789', 'asg', 'ahw', 'd', 'awg', '09123456789', 'haw', 'mother', 'asg', 'ahw', 'd', 'awg', '09123456789', 'ga', 'whg', '09123456789', 'aw', 'aw', '09123456789', 'hwa', 'awg', '09123456789', 'hdf', 'no', '', '2026-04-13 10:26:50'),
(53, 'Toddler', '20260015', '2025-2026', NULL, '2026-04-28', 'Sanchez', 'Ivannns', 'aaw', '', 'awg', 'Male', '07/02/2005', 20, 'gaw', 'aw', 'work.ivansanchez@gmail.com', 'Batangas', 'Cuenca', 'Labac', 'gwa', 'aagw', 'awgh', 'awg', 'awg', '09123456789', 'gwa', 'fb', 'awf', 'gaw', '09123456789', 'gaw', 'mother', 'gwa', 'fb', 'awf', 'gaw', '09123456789', 'awf', 'awg', '09123456789', NULL, NULL, NULL, NULL, NULL, NULL, 'awg', 'no', '', '2026-04-28 05:54:48'),
(54, 'Casa', '20260016', '2025-2026', 'Continuing', '2026-04-28', 'snchz', 'ivn', 'asf', '', 'aawg', 'Female', '2005-07-02', 20, 'awg', 'awg', 'work.ivansanchez@gmail.com', 'Bohol', 'Clarin', 'Danahao', 'agw', 'awd', 'gaw', 'aw', 'gwa', '09123456789', 'awg', 'sa', 'gw', 'awg', '09123456789', 'gaw', 'mother', 'awg', 'sa', 'gw', 'awg', '09123456789', 'aw', 'awg', '09123456789', '', '', '', '', '', '', 'awg', 'no', '', '2026-04-28 06:35:01');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_custom_fields`
--

CREATE TABLE `enrollment_custom_fields` (
  `id` int(11) NOT NULL,
  `field_key` varchar(150) NOT NULL,
  `field_label` varchar(150) NOT NULL,
  `section_name` varchar(150) NOT NULL,
  `input_type` varchar(50) NOT NULL DEFAULT 'text',
  `field_options` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_field_label_overrides`
--

CREATE TABLE `enrollment_field_label_overrides` (
  `id` int(11) NOT NULL,
  `field_key` varchar(150) NOT NULL,
  `field_label` varchar(150) NOT NULL,
  `section_name` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_grade_levels`
--

CREATE TABLE `enrollment_grade_levels` (
  `id` int(11) NOT NULL,
  `grade_key` varchar(150) NOT NULL,
  `grade_label` varchar(150) NOT NULL,
  `tuition_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment_grade_levels`
--

INSERT INTO `enrollment_grade_levels` (`id`, `grade_key`, `grade_label`, `tuition_fee`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Toddler', 'Toddler', 63340.00, 10, 1, '2026-04-28 05:54:00'),
(2, 'Casa', 'Casa', 75732.00, 20, 1, '2026-04-28 05:54:00'),
(3, 'Kindergarten', 'Kindergarten', 68112.00, 30, 1, '2026-04-28 05:54:00'),
(4, 'Brave', 'Brave SpEd', 81226.00, 40, 1, '2026-04-28 05:54:00'),
(5, 'Grade 1', 'Grade 1', 67740.00, 50, 1, '2026-04-28 05:54:00'),
(6, 'Grade 2', 'Grade 2', 66740.00, 60, 1, '2026-04-28 05:54:00'),
(7, 'Grade 3', 'Grade 3', 66740.00, 70, 1, '2026-04-28 05:54:00');

-- --------------------------------------------------------

--
-- Table structure for table `grade_breakdown_components`
--

CREATE TABLE `grade_breakdown_components` (
  `id` int(11) NOT NULL,
  `grade_key` varchar(255) NOT NULL,
  `components` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`components`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grade_breakdown_components`
--

INSERT INTO `grade_breakdown_components` (`id`, `grade_key`, `components`, `created_at`, `updated_at`) VALUES
(1, 'Toddler', '{\"Tuition Fee\":57340,\"Registration Fee & Miscellaneous\":5000,\"asd\":100,\"zxc\":100,\"zxccv\":120}', '2026-04-29 00:31:25', '2026-04-29 00:54:42'),
(2, 'Casa', '{\"Tuition Fee\":69732,\"Registration Fee & Miscellaneous\":6000}', '2026-04-29 00:31:25', '2026-04-29 00:54:42'),
(3, 'Kindergarten', '{\"Tuition Fee\":65612,\"Registration Fee & Miscellaneous\":2500}', '2026-04-29 00:31:25', '2026-04-29 00:54:42'),
(4, 'Brave', '{\"Tuition Fee\":79226,\"Registration Fee & Miscellaneous\":2000}', '2026-04-29 00:31:25', '2026-04-29 00:54:42'),
(5, 'Grade 1', '{\"Tuition Fee\":66740,\"Registration Fee & Miscellaneous\":1000}', '2026-04-29 00:31:25', '2026-04-29 00:54:42'),
(6, 'Grade 2', '{\"Tuition Fee\":66740}', '2026-04-29 00:31:25', '2026-04-29 00:54:42'),
(7, 'Grade 3', '{\"Tuition Fee\":66740}', '2026-04-29 00:31:25', '2026-04-29 00:54:42');

-- --------------------------------------------------------

--
-- Table structure for table `student_requirements`
--

CREATE TABLE `student_requirements` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `student_id` varchar(100) NOT NULL,
  `requirement_key` varchar(100) NOT NULL,
  `original_name` varchar(255) NOT NULL DEFAULT '',
  `stored_name` varchar(255) NOT NULL DEFAULT '',
  `file_path` varchar(255) NOT NULL DEFAULT '',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_requirements`
--

INSERT INTO `student_requirements` (`id`, `enrollment_id`, `student_id`, `requirement_key`, `original_name`, `stored_name`, `file_path`, `uploaded_at`) VALUES
(1, 41, '2026003', 'picture_2x2', 'CHAPTER 2.pdf', '2026003_picture_2x2_1774692558.pdf', 'uploads/requirements/2026003_picture_2x2_1774692558.pdf', '2026-03-28 10:09:18'),
(3, 51, '20260013', 'picture_2x2', 'level_1.png', '20260013_picture_2x2_1774766084.png', 'uploads/requirements/20260013_picture_2x2_1774766084.png', '2026-03-29 06:34:44');

-- --------------------------------------------------------

--
-- Table structure for table `tuition_payments`
--

CREATE TABLE `tuition_payments` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `student_id` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT '',
  `school_year` varchar(100) DEFAULT '',
  `grade_level` varchar(100) DEFAULT '',
  `payment_date` date NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tuition_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `receipt_no` varchar(100) DEFAULT '',
  `payment_note` varchar(255) DEFAULT '',
  `payment_items` longtext DEFAULT NULL,
  `payment_token` varchar(64) DEFAULT '',
  `paymongo_checkout_id` varchar(100) DEFAULT '',
  `paymongo_checkout_url` varchar(255) DEFAULT '',
  `payment_status` varchar(32) NOT NULL DEFAULT 'ready',
  `payment_paid_at` datetime DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT '',
  `proof_original_name` varchar(255) DEFAULT '',
  `proof_uploaded_at` datetime DEFAULT NULL,
  `proof_status` varchar(32) NOT NULL DEFAULT 'pending',
  `email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tuition_payments`
--

INSERT INTO `tuition_payments` (`id`, `enrollment_id`, `student_id`, `email`, `school_year`, `grade_level`, `payment_date`, `amount_paid`, `tuition_fee`, `balance_after`, `receipt_no`, `payment_note`, `payment_items`, `payment_token`, `paymongo_checkout_id`, `paymongo_checkout_url`, `payment_status`, `payment_paid_at`, `proof_file`, `proof_original_name`, `proof_uploaded_at`, `proof_status`, `email_sent`, `created_at`) VALUES
(1, 47, '2026009', 'ambasajosiemae@gmail.com', '2025-2026', 'Kindergarten', '2026-03-26', 100.00, 68112.00, 68012.00, '', 'asd', NULL, '2a441125bed6d4ee2b6983fc38ca83da', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-26 10:45:40'),
(2, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-26', 100.00, 63340.00, 63240.00, '', 'vaniii', NULL, '956d098ae9620566a3404beb1ac71bf6', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-26 10:52:43'),
(3, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-26', 100.00, 63340.00, 63140.00, '', '', NULL, '50768d23d9bc9ae5b112e4f52b31a405', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-03-26 11:12:07'),
(4, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-26', 2000.00, 63340.00, 61140.00, '', '', NULL, '3f4507465c6a0d0d04c9b0cf7331cc86', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-26 12:02:57'),
(5, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-26', 3000.00, 63340.00, 58140.00, '', '', NULL, '86d4953107d9834de2e4400d5c286f79', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-26 12:03:16'),
(6, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-26', 2000.00, 63340.00, 56140.00, '', '', NULL, '808a85da3b6d6109fd40cf3a2c8e2404', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-26 12:03:24'),
(7, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-26', 56140.00, 63340.00, 0.00, '', '', NULL, 'b2fdeca2329e9f5431bfd9703745ebe7', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-03-26 12:03:27'),
(8, 50, '20260012', 'work.ivansanchez@gmail.com', '2025-2026', 'Casa', '2026-03-26', 100.00, 75732.00, 75632.00, '', '', NULL, 'e56cb1401119a88e20bb7b9830916d65', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-03-26 12:12:47'),
(9, 50, '20260012', 'work.ivansanchez@gmail.com', '2025-2026', 'Casa', '2026-03-28', 1000.00, 75732.00, 74632.00, '', '', '[{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":1000}]', '8fb57ec55c03bee573aa2fa14dedd62f', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-03-28 08:10:56'),
(10, 50, '20260012', 'work.ivansanchez@gmail.com', '2025-2026', 'Casa', '2026-03-28', 74732.00, 75732.00, 0.00, '', '', '[{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":69732},{\"option\":\"Reservation Fee\",\"label\":\"Reservation Fee\",\"amount\":5000}]', 'ac517e2fbe90621d321cca6afaa2e36b', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 08:37:17'),
(11, 46, '2026008', 'ambasajosiemae@gmail.com', '2025-2026', 'Kindergarten', '2026-03-28', 6000.00, 68112.00, 62112.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', 'c297bfbaaa6e6dc102c926c428b719c2', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 08:51:12'),
(12, 46, '2026008', 'ambasajosiemae@gmail.com', '2025-2026', 'Kindergarten', '2026-03-28', 6000.00, 68112.00, 56112.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', 'afc50566aa5180b5fd6df83c1d73cdda', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 08:58:24'),
(13, 46, '2026008', 'ambasajosiemae@gmail.com', '2025-2026', 'Kindergarten', '2026-03-28', 6000.00, 68112.00, 50112.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', 'ae624a0e24a5b2c8bda85e083dc38a0d', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 08:58:40'),
(14, 39, '2026001', 'restonjc1@gmail.com', NULL, 'Toddler', '2026-03-28', 6000.00, 63340.00, 57340.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', '3660d76ab7c76e796a313315afaa3077', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 08:59:43'),
(15, 31, '2026006603', 'restonjc1@gmail.com', NULL, 'Toddler', '2026-03-28', 6000.00, 63340.00, 57340.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', 'e89ca0d9efde75e66d836eecefa827e5', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 09:18:26'),
(16, 31, '2026006603', 'restonjc1@gmail.com', NULL, 'Toddler', '2026-03-28', 100.00, 63340.00, 57240.00, '', '', '[{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":100}]', '0af4bf9632e6b05b41c56995741470b5', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 09:18:45'),
(17, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-28', 6000.00, 63340.00, 0.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', 'f9e845672dd4dd801b0660249c8f1e61', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 10:26:16'),
(18, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-28', 6100.00, 63340.00, 0.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000},{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":100}]', '7b5df298d793c345fa4b4d43cb36e1d0', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-28 10:27:00'),
(19, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-28', 57340.00, 63340.00, 0.00, '', '', '[{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":57340}]', 'cdf5bb0873216d2ccb2c90ac026cec0b', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-03-28 10:51:22'),
(20, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-28', 6000.00, 63340.00, 0.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', '54a7e371f6bc3515d6354175d87b9096', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-03-28 12:17:01'),
(21, 51, '20260013', 'salungajosiemae@gmail.com', '2025-2026', 'Casa', '2026-03-29', 100.00, 75732.00, 75632.00, '', '', '[{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":100}]', '2564d9b5226374d6fde88c5da4784a31', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-03-29 06:36:03'),
(22, 51, '20260013', 'salungajosiemae@gmail.com', '2025-2026', 'Casa', '2026-03-29', 1000.00, 75732.00, 74632.00, '', '', '[{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":1000}]', 'a2cdffdd620c0ff1db0e524b34607c71', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-29 06:39:28'),
(23, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-29', 12734.00, 63340.00, 0.00, '', '', '[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":5734},{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000},{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":1000}]', 'c7cd431a116882372ae3ab3cb0fc1653', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-29 07:26:04'),
(24, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-29', 11734.00, 63340.00, 0.00, '', '', '[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":5734},{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', '0ae268361f9b7f37f7acb00cdbf0f5bf', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-03-29 07:27:52'),
(25, 49, '20260011', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-03-29', 11834.00, 63340.00, 0.00, '', '', '[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":5734},{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000},{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":100}]', '6297a3f9eac7283c7e3bd8d819bcb862', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-03-29 07:28:11'),
(26, 40, '2026002', 'restonjc1@gmail.com', '2025-2026', 'Casa', '2026-04-13', 6973.20, 75732.00, 68758.80, '', '', '[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":6973.2}]', 'f39c3755823e2a34aa503284fa4cb47e', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-04-13 10:14:11'),
(27, 52, '20260014', 'jomerrdeguzman@gmail.com', '2025-2026', 'Kindergarten', '2026-04-13', 6561.20, 68112.00, 61550.80, '', '', '[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":6561.2}]', '0e5aa953ae5c8e25c1e96ace2f979007', '', '', 'ready', NULL, '', '', NULL, 'pending', 1, '2026-04-13 10:27:17'),
(28, 52, '20260014', 'jomerrdeguzman@gmail.com', '2025-2026', 'Kindergarten', '2026-04-13', 6000.00, 68112.00, 55550.80, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', '67dd64801f5719a3c03ae375a853a562', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-04-13 10:27:45'),
(29, 35, '2026000035', 'restonjc1@gmail.com', NULL, 'Toddler', '2026-04-13', 6000.00, 63340.00, 57340.00, '', '', '[{\"option\":\"Registration Fee & Miscellaneous\",\"label\":\"Registration Fee & Miscellaneous\",\"amount\":6000}]', '4c755f9cbfd58ad828d96504eb82ae65', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-04-13 10:29:15'),
(30, 53, '20260015', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-04-28', 5734.00, 63340.00, 57606.00, 'INV-7926', '', '[{\"option\":\"Monthly Payment\",\"label\":\"Monthly Payment\",\"amount\":5734}]', '2b577646b5c73ee91071fcf9be46ca47', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-04-28 06:12:31'),
(31, 53, '20260015', 'work.ivansanchez@gmail.com', '2025-2026', 'Toddler', '2026-04-28', 100.00, 63340.00, 57506.00, 'INV-7068', '', '[{\"option\":\"Tuition Fee\",\"label\":\"Tuition Fee\",\"amount\":100}]', 'b3ab20df1f82d4b76bc08d8c7892ceea', '', '', 'ready', NULL, '', '', NULL, 'pending', 0, '2026-04-28 06:20:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `employee_id` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `employee_id`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Adreo', '', 'adreomontessori@gmail.com', '$2y$10$wJjq27WlheVt61K9uFf9DuT04cmNF8NpRjFgDrlp/9aBoD.YnGLGm', 'admin', '2026-04-13 10:12:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action_created` (`action`,`created_at`);

--
-- Indexes for table `batch_assignments`
--
ALTER TABLE `batch_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_sy_grade` (`student_id`,`school_year`,`grade_level`),
  ADD UNIQUE KEY `uniq_enrollment` (`enrollment_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_id` (`student_id`);

--
-- Indexes for table `enrollment_custom_fields`
--
ALTER TABLE `enrollment_custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_field_key` (`field_key`);

--
-- Indexes for table `enrollment_field_label_overrides`
--
ALTER TABLE `enrollment_field_label_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_field_key` (`field_key`);

--
-- Indexes for table `enrollment_grade_levels`
--
ALTER TABLE `enrollment_grade_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_grade_key` (`grade_key`);

--
-- Indexes for table `grade_breakdown_components`
--
ALTER TABLE `grade_breakdown_components`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grade_key` (`grade_key`);

--
-- Indexes for table `student_requirements`
--
ALTER TABLE `student_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_requirement` (`enrollment_id`,`requirement_key`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `tuition_payments`
--
ALTER TABLE `tuition_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_enrollment_id` (`enrollment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_users_email` (`email`),
  ADD UNIQUE KEY `uniq_users_role` (`role`),
  ADD UNIQUE KEY `uniq_users_employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `batch_assignments`
--
ALTER TABLE `batch_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `enrollment_custom_fields`
--
ALTER TABLE `enrollment_custom_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment_field_label_overrides`
--
ALTER TABLE `enrollment_field_label_overrides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment_grade_levels`
--
ALTER TABLE `enrollment_grade_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=272;

--
-- AUTO_INCREMENT for table `grade_breakdown_components`
--
ALTER TABLE `grade_breakdown_components`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `student_requirements`
--
ALTER TABLE `student_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tuition_payments`
--
ALTER TABLE `tuition_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
