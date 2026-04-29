-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2026 at 02:14 PM
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
-- Database: `rayhanrp_database_ta`
--

-- --------------------------------------------------------

--
-- Table structure for table `akun_rayhanrp`
--

CREATE TABLE `akun_rayhanrp` (
  `akun_id` int(11) NOT NULL,
  `nis_nip` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guru','siswa','kepala_sekolah','kurikulum') NOT NULL DEFAULT 'siswa',
  `nama_lengkap` varchar(150) NOT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diubah_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `akun_rayhanrp`
--

INSERT INTO `akun_rayhanrp` (`akun_id`, `nis_nip`, `password`, `role`, `nama_lengkap`, `jenis_kelamin`, `aktif`, `dibuat_pada`, `diubah_pada`) VALUES
(2, 'admin', '$2y$10$xCnaNHDPzENQ18aW6DWJueJ.6xuwZlusNQyYijmRXyOdB1ZzUR0g2', 'admin', 'REY', 'L', 1, '2026-04-24 19:30:20', '2026-04-24 19:30:20'),
(3, '12345678', '$2y$10$UxHZ73SWl8K7NaRrKKLYzO5RjG2s0Ini2AsVae8aTpEPqP.phtNPi', 'guru', 'RAY', 'L', 1, '2026-04-25 19:36:43', '2026-04-25 19:36:43'),
(4, '87654321', '$2y$10$zzDfl0nBaM3WKvmDEk4gdupb9T4SZl81slKL83tv/XIB.yj.SOL2W', 'kurikulum', 'RAI', 'L', 1, '2026-04-25 19:42:30', '2026-04-25 19:42:30'),
(5, 'kepalasekolah', '$2y$10$.cvYXAAuje4ij28sioFZGeBgmHVWJWPE5fB6vrEx8xnq6lN.bO4cS', 'kepala_sekolah', 'REI', 'L', 1, '2026-04-25 19:43:05', '2026-04-25 19:43:05'),
(6, '10243285', '$2y$10$yAGhop/O3t8oNrPT1P9i.eQtUlCjFONm8kZ.S6DSRfLeUW1DysXH.', 'siswa', 'AHKAM LISANUL MIZAN', 'L', 1, '2026-04-25 20:18:51', '2026-04-25 20:18:51'),
(7, '10243286', '$2y$10$kYs6dyKKXQM/imNr/YXtWuegLmYraHD5vsKVEgv1IYR4UV1ygGhA6', 'siswa', 'ALFIN BATHOSAN', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(8, '10243287', '$2y$10$x02Rzrvlldma6zdBAcR9Pu3rWnmjlWt7M.820bbY2sKv.nMoPasry', 'siswa', 'ANISA JAYA LESTARI', 'P', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(9, '10243288', '$2y$10$d7gS0AZs0pCstNPVeSjEYuC2YXzhzAyZ4SdQpMrnmCBhHRvmhZY2m', 'siswa', 'DEAN PETRA BETTI RUNESI', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(10, '10243289', '$2y$10$V05AaRt6LZAJPy7ovt7q/.KQ669QUpxcgooenkjp0BwIti/1PSgoO', 'siswa', 'ELVA CELIA FEBRIANA', 'P', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(11, '10243290', '$2y$10$CoUw8h2se6CiuQILkImp8edWLa41SXIwYELgw2gdLQvqy4y.U9sci', 'siswa', 'EXCHEL RINDA DWIKY DARMAWAN', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(12, '10243291', '$2y$10$bs0OqNKY5jogkbV4uGyQV.GqcbZNSbQ83ANpH8DC2NIlpkdolkX1C', 'siswa', 'FADLAN', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(13, '10243292', '$2y$10$HsqQb8.pAP3seRI/c.JbSu2Nxs/p9FiQ6JnwrdzkPEnJxjYMrG.rm', 'siswa', 'FAHRI HADI PRATAMA', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(14, '10243293', '$2y$10$z7uEQXi4M4YUYxT0gWpLZ.pv9DQZsYOtmX6pwOAanddeEu.JofjvK', 'siswa', 'FAUZAN ABY PRATAMA', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(15, '10243294', '$2y$10$bQz5qqHhDYSQfuANlh1UQ.DLGBmza94mw5smUx/2G/Pomd7EmdPIq', 'siswa', 'FAUZI IRWANA', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(16, '10243295', '$2y$10$grmcsnvfaUBNkYmCmB0dH.WikGv6e482ZSeXYde7ejoWs3A/BJQaa', 'siswa', 'HAIL SUKMA', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(17, '10243296', '$2y$10$vvzHomaLKRnMwYXX5tH6ROmsL1exfuob2N6/lJa6M8HJykiCbOfoC', 'siswa', 'ISTIFA FALASIFAH KHATAMI', 'P', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(18, '10243297', '$2y$10$jnV9HjC8QXIzk5A.seC4YO6S3ttNx9c0kDvw02iAJUOXrYzbsZgrG', 'siswa', 'MUHAMAD DIRGAHAYU DWI SAPUTRA', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(19, '10243298', '$2y$10$4fXF0f5AAMiQcCqBDcs4Z.rvohQ8psgPrQ1Rx97mQcEyScTXoqpmy', 'siswa', 'MUHAMAD RAMDAN', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(20, '10243299', '$2y$10$MjDvRT0PU/WP2r6IEUEAlu7pnPatxICP4DpWz.Y5k1ce6r6G1XNzC', 'siswa', 'MUHAMAD RIZKI ARDIANSAH', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(21, '10243300', '$2y$10$E1wLnPtC0gVGnaIJIxhDYOq5xdz3KXOBmsBBA.QuYmsfjOxPus3F6', 'siswa', 'MUHAMMAD AKBAR', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(22, '10243301', '$2y$10$dXSHGHTd.Fma6q3ba4rcUOgDurXJdtTV6lsgs.B0ieJuEWWHE8NJy', 'siswa', 'MUHAMMAD ALIF FIRDAUS', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(23, '10243302', '$2y$10$UYmthcLJagOUd8vB1d.OaeHBa4pMdzxDRqY.H1UGDl5V7Jf70aCim', 'siswa', 'MUHAMMAD AZAM IZZATULHAQ', 'L', 1, '2026-04-25 20:18:52', '2026-04-25 20:18:52'),
(24, '10243303', '$2y$10$out9QtrmMR5.boUb/gAlouH4.Wkm/cMD0tm.gHJYAH1Boa6cYgqL.', 'siswa', 'MUHAMMAD AZKA SA\'ADI NABHAN', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(25, '10243304', '$2y$10$KZ1WMX9/pUvHxZYbg./7AOuNJPR8vnkhUigPvYPIEJDHdx7yFlCT2', 'siswa', 'MUHAMMAD CHANDRA PRATAMA', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(26, '10243305', '$2y$10$mQBS2puoBS9IPz.XjjMxFeazXSTPhCWSJv.OIchy8W8rSmsHRuUG.', 'siswa', 'MUHAMMAD EZRA FEBRITAUFANI', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(27, '10243306', '$2y$10$yYGIG8.7l1qkfO7grhtZIeiAi16HzmwtoFfjLqyByCM45eGnfZ2L6', 'siswa', 'MULKI IKRAM MAULANA LUBIS', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(28, '10243307', '$2y$10$Iy7xTIWzKzlDlmroVwgbjOhy00GRPjvRL/moHnLFTLPtKC0qyTFWe', 'siswa', 'NOVI ROPIAH', 'P', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(29, '10243308', '$2y$10$oTs5IqUFO7Y98DRZUb9AT.kUhss.5QVP3uh7RLEECKlIYGo6OQhpW', 'siswa', 'NOVRI KRISNA PRATAMA', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(30, '10243309', '$2y$10$ATX7ONs7W8ceC750qCqAde62/rC7c5Yt7wwcLzhNvef569KoSdHz.', 'siswa', 'RADITYA RAYGA MULANA', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(31, '10243310', '$2y$10$ymSKCx9W8GsFQtK.a9QIiuNkig.gwT1BRFBWzTco/shmJMbuI2arW', 'siswa', 'RAFASYA ATHAULLAH RUIZKHA', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(32, '10243311', '$2y$10$OivQiuGTIfLYoIrNot86Wum8Lna0pwOj38KHAxYuvnliFlTdMp582', 'siswa', 'RAHMA KHOYRUL HAWA', 'P', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(33, '10243312', '$2y$10$bNFy0PDIvwKsRQfim/UuAOAQ/pwU/JwjksnUPtmzVDfzYIZeAHKNS', 'siswa', 'RAIHAN FADLANSYAH', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(34, '10243313', '$2y$10$wHWN.0EOr1QbQucWsH5OK.2uahE/fnl4SMQ5uUpsX.FIKQyALagI6', 'siswa', 'RAYHAN RIZKY PRATAMA', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(35, '10243314', '$2y$10$6EbxkgKdSSfrZyg4B6IdJeIYDDfUO8XamRRsjQeYUH131C3cj5RP.', 'siswa', 'REVITA GADIS AMIJAYA', 'P', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(36, '10243315', '$2y$10$SSyYQ4wjco2.INuC/FGf5u.swlFIOKVKke5g38vHoqNg7qOrnxOA6', 'siswa', 'RIZKI AHMAD MAULANA', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(37, '10243316', '$2y$10$BF.yPqEJ1YBFkDtXtAOOeevq/pEy7IiIUoU6OHgA8CCWSQeY2UQFO', 'siswa', 'RIZQY RAMADHAN INDRAWAN PUTRA', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(38, '10243317', '$2y$10$T2qbT.erQqI/67j56MDua.4rR6Jp9GVfENnloW7/QakJYfrcyyM0K', 'siswa', 'SALMA ASHANADIYA', 'P', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(39, '10243318', '$2y$10$/O9iWcdFQIDHiXfI9L.ozObcrx7.k6NnCODlSg14FYnRnLLxxidxe', 'siswa', 'SAZKIYA LUTHFIAH ADZANI', 'P', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(40, '10243319', '$2y$10$IPPUxjV9iU0gzgVyl5QKTe3oWV2mUsKfPEkzkkBMyMrvw7ylPrWfG', 'siswa', 'YOGA PRATAMA SETIAWAN', 'L', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(41, '10243320', '$2y$10$bFHgb73pgcXL08HGt5E0qeW8IcTfPcCK8nlPASQ7n99KpFNeg5aQy', 'siswa', 'YUNIFA RIZKY', 'P', 1, '2026-04-25 20:18:53', '2026-04-25 20:18:53'),
(42, 'pairfan', '$2y$10$0CGwoeKycbMPJACSLH/97e2W9MMYjzBd7mrdhYOX0MnUltWiHSqPS', 'guru', 'Pa Irfan', 'L', 1, '2026-04-25 20:40:29', '2026-04-25 20:40:29'),
(43, 'buismi', '$2y$10$AxaaGCgdQVEs1oRfxFAqYetKsRiRFMMm8Uhb3jNFMZPGD4Dq8dJGa', 'guru', 'Bu Ismi', 'L', 1, '2026-04-25 20:40:50', '2026-04-25 20:40:50');

-- --------------------------------------------------------

--
-- Table structure for table `akun_telegram_rayhanrp`
--

CREATE TABLE `akun_telegram_rayhanrp` (
  `id` int(11) NOT NULL,
  `akun_id` int(11) NOT NULL,
  `telegram_chat_id` bigint(20) NOT NULL,
  `username_telegram` varchar(100) DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `akun_telegram_rayhanrp`
--

INSERT INTO `akun_telegram_rayhanrp` (`id`, `akun_id`, `telegram_chat_id`, `username_telegram`, `dibuat_pada`) VALUES
(1, 34, 8445211581, NULL, '2026-04-25 20:48:25');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log_rayhanrp`
--

CREATE TABLE `audit_log_rayhanrp` (
  `id` bigint(20) NOT NULL,
  `akun_id` int(11) DEFAULT NULL,
  `aksi` varchar(80) NOT NULL,
  `objek_tipe` varchar(40) DEFAULT NULL,
  `objek_id` int(11) DEFAULT NULL,
  `detail` longtext DEFAULT NULL,
  `status` enum('sukses','gagal','ditolak') NOT NULL DEFAULT 'sukses',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `waktu` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log_rayhanrp`
--

INSERT INTO `audit_log_rayhanrp` (`id`, `akun_id`, `aksi`, `objek_tipe`, `objek_id`, `detail`, `status`, `ip_address`, `user_agent`, `waktu`) VALUES
(1, NULL, 'login', 'akun', 1, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-24 19:30:01'),
(2, NULL, 'create_user', 'akun', 2, '{\"nis_nip\":\"admin\",\"role\":\"admin\",\"grup_id\":null}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-24 19:30:20'),
(3, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-24 20:16:03'),
(4, 2, 'delete_user', 'akun', 1, NULL, 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-24 20:16:11'),
(5, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 19:35:59'),
(6, 2, 'create_user', 'akun', 3, '{\"nis_nip\":\"12345678\",\"role\":\"guru\",\"grup_id\":null}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 19:36:43'),
(7, 2, 'create_user', 'akun', 4, '{\"nis_nip\":\"87654321\",\"role\":\"kurikulum\",\"grup_id\":null}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 19:42:30'),
(8, 2, 'create_user', 'akun', 5, '{\"nis_nip\":\"kepalasekolah\",\"role\":\"kepala_sekolah\",\"grup_id\":null}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 19:43:05'),
(9, 2, 'create_matpel', 'mata_pelajaran', 3, '{\"kode\":\"MTK\",\"nama\":\"Matematika\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:00:50'),
(10, 2, 'toggle_matpel', 'mata_pelajaran', 3, '{\"aktif\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:01:55'),
(11, 2, 'toggle_matpel', 'mata_pelajaran', 3, '{\"aktif\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:01:57'),
(12, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:03:09'),
(13, 2, 'create_matpel', 'mata_pelajaran', 4, '{\"kode\":\"MTK\",\"nama\":\"Matematika\",\"kategori\":\"umum\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:13:17'),
(14, 2, 'toggle_matpel', 'mata_pelajaran', 4, '{\"aktif\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:13:22'),
(15, 2, 'toggle_matpel', 'mata_pelajaran', 4, '{\"aktif\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:13:23'),
(16, 2, 'create_matpel', 'mata_pelajaran', 5, '{\"kode\":\"PJOK\",\"nama\":\"Pendidikan Jasmani, Olahraga, dan Kesehatan\",\"kategori\":\"umum\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:14:14'),
(17, 2, 'create_matpel', 'mata_pelajaran', 6, '{\"kode\":\"PPLG\",\"nama\":\"Pengembangan Perangkat Lunak dan Gim\",\"kategori\":\"kejuruan\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:14:44'),
(18, 2, 'create_matpel', 'mata_pelajaran', 7, '{\"kode\":\"EBASKET\",\"nama\":\"Basket\",\"kategori\":\"pilihan\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:15:08'),
(19, 2, 'update_matpel', 'mata_pelajaran', 7, '{\"kode\":\"EBASKET\",\"nama\":\"Basket\",\"kategori\":\"kejuruan\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:15:14'),
(20, 2, 'update_matpel', 'mata_pelajaran', 7, '{\"kode\":\"EBASKET\",\"nama\":\"Basket\",\"kategori\":\"pilihan\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:15:17'),
(21, 2, 'update_matpel', 'mata_pelajaran', 7, '{\"kode\":\"EBASKETT\",\"nama\":\"Basket\",\"kategori\":\"pilihan\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:15:20'),
(22, 2, 'update_matpel', 'mata_pelajaran', 7, '{\"kode\":\"EBASKET\",\"nama\":\"Baskett\",\"kategori\":\"pilihan\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:15:24'),
(23, 2, 'update_matpel', 'mata_pelajaran', 7, '{\"kode\":\"EBASKET\",\"nama\":\"Basket\",\"kategori\":\"pilihan\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:15:26'),
(24, 2, 'import_user_excel', 'akun', NULL, '{\"imported\":36,\"failed\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:18:53'),
(25, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:19:32'),
(26, 5, 'login', 'akun', 5, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:19:44'),
(27, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:20:51'),
(28, 4, 'create_grup', 'grup', 1, '{\"tipe_grup\":\"kelas\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:22:27'),
(29, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":3,\"grup_id\":1,\"matpel_id\":6}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:33:01'),
(30, 4, 'toggle_guru_mengajar', 'guru_mengajar', 1, '{\"aktif\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:33:49'),
(31, 4, 'toggle_guru_mengajar', 'guru_mengajar', 1, '{\"aktif\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:33:51'),
(32, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:37:14'),
(33, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:39:11'),
(34, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:39:49'),
(35, 2, 'create_user', 'akun', 42, '{\"nis_nip\":\"pairfan\",\"role\":\"guru\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:40:29'),
(36, 2, 'create_user', 'akun', 43, '{\"nis_nip\":\"buismi\",\"role\":\"guru\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:40:50'),
(37, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:40:59'),
(38, 4, 'create_grup', 'grup', 2, '{\"tipe_grup\":\"kelas\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:41:25'),
(39, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":3,\"grup_id\":2,\"matpel_id\":4}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:42:05'),
(40, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":42,\"grup_id\":1,\"matpel_id\":6}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:42:20'),
(41, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:44:10'),
(42, 3, 'create_tugas', 'tugas', 1, '{\"grup_id\":1,\"matpel_id\":6,\"tipe_tugas\":\"grup\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:45:55'),
(43, 3, 'toggle_status_tugas', 'tugas', 1, '{\"status\":\"closed\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:46:01'),
(44, 3, 'create_tugas', 'tugas', 2, '{\"grup_id\":1,\"matpel_id\":6,\"tipe_tugas\":\"grup\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:46:05'),
(45, 3, 'toggle_status_tugas', 'tugas', 1, '{\"status\":\"active\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:46:31'),
(46, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:47:12'),
(47, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:47:32'),
(48, 3, 'create_pengumuman', 'pengumuman', 1, '{\"grup_id\":1,\"target_role\":\"all\",\"jumlah_terkirim\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:52:01'),
(49, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:52:23'),
(50, 4, 'toggle_guru_mengajar', 'guru_mengajar', 3, '{\"aktif\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:54:05'),
(51, 4, 'toggle_guru_mengajar', 'guru_mengajar', 3, '{\"aktif\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:54:07'),
(52, 4, 'toggle_guru_mengajar', 'guru_mengajar', 3, '{\"aktif\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:54:10'),
(53, 4, 'toggle_guru_mengajar', 'guru_mengajar', 3, '{\"aktif\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 20:54:15'),
(54, 4, 'update_guru_mengajar', 'guru_mengajar', 3, '{\"hari\":\"Rabu\",\"jam_mulai\":\"07:00\",\"jam_selesai\":\"15:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:10:49'),
(55, 4, 'update_guru_mengajar', 'guru_mengajar', 1, '{\"hari\":\"Kamis\",\"jam_mulai\":\"07:00\",\"jam_selesai\":\"15:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:11:03'),
(56, 4, 'update_guru_mengajar', 'guru_mengajar', 2, '{\"hari\":\"Jumat\",\"jam_mulai\":\"09:00\",\"jam_selesai\":\"11:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:11:34'),
(57, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:11:47'),
(58, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:16:35'),
(59, 4, 'create_grup', 'grup', 3, '{\"tipe_grup\":\"kelas\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:19:47'),
(60, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-25 21:30:26'),
(61, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-25 21:31:44'),
(62, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:38:36'),
(63, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:38:43'),
(64, 4, 'create_grup', 'grup', 4, '{\"tipe_grup\":\"kelas\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:39:26'),
(65, 4, 'create_grup', 'grup', 5, '{\"tipe_grup\":\"kelas\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:39:33'),
(66, 4, 'create_grup', 'grup', 6, '{\"tipe_grup\":\"eskul\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:39:43'),
(67, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":42,\"grup_id\":3,\"matpel_id\":6,\"hari\":\"Kamis\",\"jam_mulai\":\"07:00\",\"jam_selesai\":\"15:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:41:23'),
(68, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":42,\"grup_id\":5,\"matpel_id\":6,\"hari\":\"Kamis\",\"jam_mulai\":\"07:00\",\"jam_selesai\":\"15:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:41:54'),
(69, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":42,\"grup_id\":3,\"matpel_id\":4,\"hari\":\"Rabu\",\"jam_mulai\":\"07:00\",\"jam_selesai\":\"09:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:42:26'),
(70, 4, 'update_guru_mengajar', 'guru_mengajar', 5, '{\"hari\":\"Selasa\",\"jam_mulai\":\"07:00\",\"jam_selesai\":\"15:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:43:48'),
(71, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":42,\"grup_id\":5,\"matpel_id\":4,\"hari\":\"Senin\",\"jam_mulai\":\"07:00\",\"jam_selesai\":\"09:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:45:01'),
(72, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":43,\"grup_id\":6,\"matpel_id\":7,\"hari\":\"Senin\",\"jam_mulai\":\"23:23\",\"jam_selesai\":\"23:45\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:45:10'),
(73, 4, 'delete_guru_mengajar', 'guru_mengajar', 9, NULL, 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 21:45:13'),
(74, NULL, 'login_gagal', 'akun', NULL, '{\"nis_nip\":\"pairfan\"}', 'gagal', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:12:38'),
(75, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:12:42'),
(76, 42, 'create_tugas', 'tugas', 3, '{\"grup_id\":3,\"matpel_id\":6,\"tipe_tugas\":\"grup\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:23:56'),
(77, 42, 'toggle_revision_tugas', 'tugas', 3, '{\"izin_revisi\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:24:03'),
(78, 42, 'create_tugas', 'tugas', 4, '{\"grup_id\":3,\"matpel_id\":6,\"tipe_tugas\":\"grup\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:24:06'),
(79, 42, 'create_pengumuman', 'pengumuman', 2, '{\"grup_id\":5,\"target_role\":\"all\",\"jumlah_terkirim\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:27:51'),
(80, 42, 'delete_pengumuman', 'pengumuman', 2, NULL, 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:28:04'),
(81, 42, 'create_pengumuman', 'pengumuman', 3, '{\"grup_id\":3,\"target_role\":\"all\",\"jumlah_terkirim\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:28:16'),
(82, 5, 'login', 'akun', 5, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-25 22:28:48'),
(83, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 18:14:34'),
(84, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 18:24:45'),
(85, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 18:27:25'),
(86, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 18:27:38'),
(87, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 18:28:48'),
(88, 42, 'create_tugas', 'tugas', 5, '{\"grup_id\":null,\"matpel_id\":6,\"tipe_tugas\":\"perorang\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 18:30:28'),
(89, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 18:32:57'),
(90, NULL, 'login_gagal', 'akun', NULL, '{\"nis_nip\":\"87654321\"}', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 18:33:18'),
(91, NULL, 'login_gagal', 'akun', NULL, '{\"nis_nip\":\"12345678\"}', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 18:33:31'),
(92, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 18:33:44'),
(93, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"closed\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:14'),
(94, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"active\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:15'),
(95, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"closed\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:16'),
(96, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"active\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:16'),
(97, 42, 'toggle_status_tugas', 'tugas', 4, '{\"status\":\"closed\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:17'),
(98, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"closed\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:19'),
(99, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"active\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:19'),
(100, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"closed\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:20'),
(101, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"active\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:21'),
(102, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"closed\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:22'),
(103, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"active\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:22'),
(104, 42, 'toggle_status_tugas', 'tugas', 4, '{\"status\":\"active\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:25'),
(105, 42, 'toggle_revision_tugas', 'tugas', 5, '{\"izin_revisi\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:25'),
(106, 42, 'toggle_revision_tugas', 'tugas', 5, '{\"izin_revisi\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:26'),
(107, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"closed\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:32'),
(108, 42, 'toggle_status_tugas', 'tugas', 5, '{\"status\":\"active\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:04:33'),
(109, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:11:49'),
(110, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:12:56'),
(111, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:13:11'),
(112, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:14:17'),
(113, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:33:52'),
(114, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:36:26'),
(115, 5, 'login', 'akun', 5, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:36:38'),
(116, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:55:28'),
(117, 5, 'login', 'akun', 5, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 19:56:51'),
(118, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:03:54'),
(119, 4, 'create_grup', 'grup', 7, '{\"tipe_grup\":\"lainnya\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:04:05'),
(120, 4, 'update_grup', 'grup', 7, '{\"tipe_grup\":\"kelas\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:04:08'),
(121, 4, 'update_grup', 'grup', 7, '{\"tipe_grup\":\"lainnya\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:04:19'),
(122, 4, 'create_grup', 'grup', 8, '{\"tipe_grup\":\"lainnya\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:04:23'),
(123, 4, 'delete_grup', 'grup', 7, NULL, 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:04:30'),
(124, 4, 'create_grup', 'grup', 9, '{\"tipe_grup\":\"lainnya\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:04:36'),
(125, 4, 'delete_grup', 'grup', 9, NULL, 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:04:39'),
(126, 4, 'update_grup', 'grup', 8, '{\"tipe_grup\":\"kelas\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:05:29'),
(127, 4, 'update_grup', 'grup', 8, '{\"tipe_grup\":\"kelas\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:05:33'),
(128, 4, 'delete_grup', 'grup', 8, NULL, 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:07:14'),
(129, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":3,\"grup_id\":5,\"matpel_id\":4,\"hari\":\"Senin\",\"jam_mulai\":\"07:00\",\"jam_selesai\":\"12:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:07:45'),
(130, 4, 'delete_pengumuman', 'pengumuman', 3, NULL, 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:08:16'),
(131, 5, 'login', 'akun', 5, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:08:40'),
(132, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:10:52'),
(133, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:12:56'),
(134, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-26 20:17:20'),
(135, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 20:23:29'),
(136, NULL, 'login_gagal', 'akun', NULL, '{\"nis_nip\":\"admin\"}', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 20:25:08'),
(137, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.117.0 Chrome/142.0.7444.265 Electron/39.8.7 Safari/537.36', '2026-04-26 20:25:28'),
(138, 5, 'login', 'akun', 5, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-27 21:43:25'),
(139, 2, 'login', 'akun', 2, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-27 21:45:16'),
(140, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-27 21:46:41'),
(141, 4, 'create_guru_mengajar', 'guru_mengajar', NULL, '{\"akun_id\":43,\"grup_id\":3,\"matpel_id\":6,\"hari\":\"Rabu\",\"jam_mulai\":\"11:00\",\"jam_selesai\":\"15:00\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-27 21:48:33'),
(142, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-27 21:50:27'),
(143, 5, 'login', 'akun', 5, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 17:17:33'),
(144, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 17:24:47'),
(145, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 17:28:52'),
(146, 4, 'toggle_grup', 'grup', 5, '{\"aktif\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 17:56:46'),
(147, 4, 'toggle_grup', 'grup', 5, '{\"aktif\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 17:57:07'),
(148, 4, 'update_grup', 'grup', 5, '{\"tingkat\":2,\"jurusan\":\"Pengembangan Perangkat Lunak dan Gim\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 18:13:58'),
(149, 4, 'create_grup', 'grup', 10, '{\"tingkat\":12,\"jurusan\":\"Teknik Mekatronika\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 18:42:25'),
(150, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 18:52:04'),
(151, 3, 'login', 'akun', 3, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 18:53:13'),
(152, 4, 'login', 'akun', 4, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 18:53:28'),
(153, 42, 'login', 'akun', 42, '{\"via\":\"web\"}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 18:54:48'),
(154, 42, 'create_pengumuman', 'pengumuman', 4, '{\"grup_id\":5,\"target_role\":\"siswa\",\"jumlah_terkirim\":0}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 19:03:09'),
(155, 42, 'delete_pengumuman', 'pengumuman', 4, NULL, 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 19:03:19'),
(156, 42, 'create_pengumuman', 'pengumuman', 5, '{\"grup_id\":3,\"target_role\":\"siswa\",\"jumlah_terkirim\":1}', 'sukses', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-04-28 19:03:28');

-- --------------------------------------------------------

--
-- Table structure for table `grup_anggota_rayhanrp`
--

CREATE TABLE `grup_anggota_rayhanrp` (
  `keanggotaan_id` int(11) NOT NULL,
  `grup_id` int(11) NOT NULL,
  `akun_id` int(11) NOT NULL,
  `tipe_keanggotaan` enum('utama','tambahan') NOT NULL DEFAULT 'tambahan',
  `bergabung_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grup_anggota_rayhanrp`
--

INSERT INTO `grup_anggota_rayhanrp` (`keanggotaan_id`, `grup_id`, `akun_id`, `tipe_keanggotaan`, `bergabung_pada`, `aktif`) VALUES
(2, 3, 34, 'utama', '2026-04-25 21:36:45', 1),
(3, 3, 26, 'utama', '2026-04-25 21:39:57', 1),
(4, 3, 32, 'utama', '2026-04-25 21:40:03', 1),
(5, 5, 6, 'utama', '2026-04-25 21:40:20', 1),
(7, 4, 7, 'utama', '2026-04-25 21:40:41', 1);

--
-- Triggers `grup_anggota_rayhanrp`
--
DELIMITER $$
CREATE TRIGGER `trg_grup_anggota_rayhanrp_no_duplikat` BEFORE INSERT ON `grup_anggota_rayhanrp` FOR EACH ROW BEGIN
  IF NEW.aktif = 1 AND EXISTS (
    SELECT 1 FROM grup_anggota_rayhanRP
    WHERE grup_id = NEW.grup_id
      AND akun_id = NEW.akun_id
      AND aktif   = 1
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Anggota sudah aktif di grup ini.';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `grup_rayhanrp`
--

CREATE TABLE `grup_rayhanrp` (
  `grup_id` int(11) NOT NULL,
  `nama_grup` varchar(150) NOT NULL,
  `tingkat` tinyint(4) NOT NULL,
  `jurusan` enum('Teknik Pemesinan','Teknik Mekatronika','Teknik Kimia Industri','Pengembangan Perangkat Lunak dan Gim','Desain Komunikasi Visual','Animasi') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `pembuat_id` int(11) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diubah_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grup_rayhanrp`
--

INSERT INTO `grup_rayhanrp` (`grup_id`, `nama_grup`, `tingkat`, `jurusan`, `deskripsi`, `pembuat_id`, `aktif`, `dibuat_pada`, `diubah_pada`) VALUES
(3, 'XI PPLG B', 11, 'Pengembangan Perangkat Lunak dan Gim', NULL, 4, 1, '2026-04-25 21:19:47', '2026-04-28 18:41:12'),
(4, 'XII PPLG B', 12, 'Pengembangan Perangkat Lunak dan Gim', NULL, 4, 1, '2026-04-25 21:39:26', '2026-04-28 18:41:12'),
(5, 'XI PPLG A', 11, 'Pengembangan Perangkat Lunak dan Gim', NULL, 4, 1, '2026-04-25 21:39:33', '2026-04-28 18:41:12'),
(10, 'XII MEKA A', 12, 'Teknik Mekatronika', NULL, 4, 1, '2026-04-28 18:42:25', '2026-04-28 18:42:25');

-- --------------------------------------------------------

--
-- Table structure for table `guru_mengajar_rayhanrp`
--

CREATE TABLE `guru_mengajar_rayhanrp` (
  `id` int(11) NOT NULL,
  `akun_id` int(11) NOT NULL,
  `grup_id` int(11) NOT NULL,
  `matpel_id` int(11) NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guru_mengajar_rayhanrp`
--

INSERT INTO `guru_mengajar_rayhanrp` (`id`, `akun_id`, `grup_id`, `matpel_id`, `hari`, `jam_mulai`, `jam_selesai`, `aktif`, `dibuat_pada`) VALUES
(4, 42, 3, 6, 'Kamis', '07:00:00', '15:00:00', 1, '2026-04-25 21:41:23'),
(5, 42, 5, 6, 'Selasa', '07:00:00', '15:00:00', 1, '2026-04-25 21:41:54'),
(6, 42, 3, 4, 'Rabu', '07:00:00', '09:00:00', 1, '2026-04-25 21:42:26'),
(8, 42, 5, 4, 'Senin', '07:00:00', '09:00:00', 1, '2026-04-25 21:45:01'),
(10, 3, 5, 4, 'Senin', '07:00:00', '12:00:00', 1, '2026-04-26 20:07:45'),
(11, 43, 3, 6, 'Rabu', '11:00:00', '15:00:00', 1, '2026-04-27 21:48:33');

-- --------------------------------------------------------

--
-- Table structure for table `mata_pelajaran_rayhanrp`
--

CREATE TABLE `mata_pelajaran_rayhanrp` (
  `matpel_id` int(11) NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `kategori` enum('umum','kejuruan','pilihan') NOT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mata_pelajaran_rayhanrp`
--

INSERT INTO `mata_pelajaran_rayhanrp` (`matpel_id`, `kode`, `nama`, `aktif`, `kategori`, `dibuat_pada`) VALUES
(4, 'MTK', 'Matematika', 1, 'umum', '2026-04-25 20:13:17'),
(5, 'PJOK', 'Pendidikan Jasmani, Olahraga, dan Kesehatan', 1, 'umum', '2026-04-25 20:14:14'),
(6, 'PPLG', 'Pengembangan Perangkat Lunak dan Gim', 1, 'kejuruan', '2026-04-25 20:14:44'),
(7, 'EBASKET', 'Basket', 1, 'pilihan', '2026-04-25 20:15:08');

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi_rayhanrp`
--

CREATE TABLE `notifikasi_rayhanrp` (
  `notifikasi_id` int(11) NOT NULL,
  `tipe` enum('tugas','pengumuman','jadwal','nilai','sistem','telegram') NOT NULL,
  `grup_id` int(11) DEFAULT NULL,
  `sumber_tipe` enum('pengumuman','tugas','sistem') DEFAULT NULL,
  `sumber_id` int(11) DEFAULT NULL,
  `pesan` text NOT NULL,
  `jumlah_terkirim` int(11) NOT NULL DEFAULT 0,
  `waktu_kirim` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifikasi_rayhanrp`
--

INSERT INTO `notifikasi_rayhanrp` (`notifikasi_id`, `tipe`, `grup_id`, `sumber_tipe`, `sumber_id`, `pesan`, `jumlah_terkirim`, `waktu_kirim`) VALUES
(1, 'pengumuman', NULL, 'pengumuman', 1, '[Test Notif] Test', 1, '2026-04-25 20:52:01'),
(2, 'tugas', 3, NULL, NULL, 'Tugas baru: Tugas Akhir', 1, '2026-04-25 22:23:56'),
(3, 'tugas', 3, NULL, NULL, 'Tugas baru: Tugas Akhir', 1, '2026-04-25 22:24:06'),
(4, 'pengumuman', 5, 'pengumuman', 2, '[Test] Testing', 0, '2026-04-25 22:27:51'),
(5, 'pengumuman', 3, 'pengumuman', 3, '[Test] Testing', 1, '2026-04-25 22:28:16'),
(6, '', NULL, NULL, NULL, 'Tugas perorang baru: Remedial TA', 1, '2026-04-26 18:30:28'),
(7, 'pengumuman', 5, 'pengumuman', 4, '[Test] Test', 0, '2026-04-28 19:03:09'),
(8, 'pengumuman', 3, 'pengumuman', 5, '[Test] Test', 1, '2026-04-28 19:03:28');

-- --------------------------------------------------------

--
-- Table structure for table `pengumpulan_rayhanrp`
--

CREATE TABLE `pengumpulan_rayhanrp` (
  `pengumpulan_id` int(11) NOT NULL,
  `tugas_id` int(11) NOT NULL,
  `akun_id` int(11) NOT NULL,
  `teks_jawaban` longtext DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_nama_asli` varchar(255) DEFAULT NULL,
  `link_jawaban` varchar(500) DEFAULT NULL,
  `status` enum('dikumpulkan','terlambat','graded') NOT NULL DEFAULT 'dikumpulkan',
  `via` enum('web','telegram') NOT NULL DEFAULT 'web',
  `waktu_kumpul` datetime NOT NULL DEFAULT current_timestamp(),
  `diubah_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengumpulan_rayhanrp`
--

INSERT INTO `pengumpulan_rayhanrp` (`pengumpulan_id`, `tugas_id`, `akun_id`, `teks_jawaban`, `file_path`, `file_nama_asli`, `link_jawaban`, `status`, `via`, `waktu_kumpul`, `diubah_pada`) VALUES
(2, 4, 34, NULL, 'uploads/submissions/2026/04/34_4_1777130736_foto_34_4_1777130735.jpg', 'foto_34_4_1777130735.jpg', NULL, 'graded', 'telegram', '2026-04-25 22:25:37', '2026-04-26 19:13:05');

-- --------------------------------------------------------

--
-- Table structure for table `pengumpulan_revisi_rayhanrp`
--

CREATE TABLE `pengumpulan_revisi_rayhanrp` (
  `id` int(11) NOT NULL,
  `pengumpulan_id` int(11) NOT NULL,
  `nomor_revisi` int(11) NOT NULL DEFAULT 1,
  `teks_jawaban_lama` longtext DEFAULT NULL,
  `file_path_lama` varchar(500) DEFAULT NULL,
  `file_nama_asli_lama` varchar(255) DEFAULT NULL,
  `link_jawaban_lama` varchar(500) DEFAULT NULL,
  `teks_jawaban_baru` longtext DEFAULT NULL,
  `file_path_baru` varchar(500) DEFAULT NULL,
  `file_nama_asli_baru` varchar(255) DEFAULT NULL,
  `link_jawaban_baru` varchar(500) DEFAULT NULL,
  `alasan_revisi` text DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengumuman_rayhanrp`
--

CREATE TABLE `pengumuman_rayhanrp` (
  `pengumuman_id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `isi` text NOT NULL,
  `grup_id` int(11) DEFAULT NULL,
  `prioritas` enum('biasa','penting','darurat') NOT NULL DEFAULT 'biasa',
  `target_role` enum('all','guru','siswa') NOT NULL DEFAULT 'all',
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'published',
  `tanggal_tayang` date NOT NULL,
  `pembuat_id` int(11) DEFAULT NULL,
  `via_telegram` tinyint(1) NOT NULL DEFAULT 0,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengumuman_rayhanrp`
--

INSERT INTO `pengumuman_rayhanrp` (`pengumuman_id`, `judul`, `isi`, `grup_id`, `prioritas`, `target_role`, `status`, `tanggal_tayang`, `pembuat_id`, `via_telegram`, `dibuat_pada`) VALUES
(1, 'Test Notif', 'Test', NULL, 'penting', 'all', 'published', '2026-04-25', 3, 1, '2026-04-25 20:52:01'),
(5, 'Test', 'Test', 3, 'penting', 'siswa', 'published', '2026-04-28', 42, 1, '2026-04-28 19:03:28');

-- --------------------------------------------------------

--
-- Table structure for table `penilaian_rayhanrp`
--

CREATE TABLE `penilaian_rayhanrp` (
  `penilaian_id` int(11) NOT NULL,
  `pengumpulan_id` int(11) NOT NULL,
  `dinilai_oleh` int(11) DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `status_lulus` enum('lulus','tidak_lulus','revisi') DEFAULT NULL,
  `catatan_guru` text DEFAULT NULL,
  `dinilai_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diubah_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `penilaian_rayhanrp`
--

INSERT INTO `penilaian_rayhanrp` (`penilaian_id`, `pengumpulan_id`, `dinilai_oleh`, `nilai`, `status_lulus`, `catatan_guru`, `dinilai_pada`, `diubah_pada`) VALUES
(2, 2, 42, 95.13, 'lulus', '', '2026-04-28 19:01:28', '2026-04-28 19:01:28');

--
-- Triggers `penilaian_rayhanrp`
--
DELIMITER $$
CREATE TRIGGER `tr_penilaian_after_delete` AFTER DELETE ON `penilaian_rayhanrp` FOR EACH ROW BEGIN
  DECLARE v_tenggat DATETIME;
  DECLARE v_waktu_kumpul DATETIME;
  DECLARE v_new_status VARCHAR(20);
  
  -- Ambil data pengumpulan
  SELECT p.`waktu_kumpul`, t.`tenggat`
  INTO v_waktu_kumpul, v_tenggat
  FROM `pengumpulan_rayhanrp` p
  INNER JOIN `tugas_rayhanRP` t ON p.`tugas_id` = t.`tugas_id`
  WHERE p.`pengumpulan_id` = OLD.`pengumpulan_id`
  LIMIT 1;
  
  -- Tentukan status: dikumpulkan (tepat waktu) atau terlambat
  IF v_waktu_kumpul IS NOT NULL AND v_tenggat IS NOT NULL THEN
    IF v_waktu_kumpul <= v_tenggat THEN
      SET v_new_status = 'dikumpulkan';
    ELSE
      SET v_new_status = 'terlambat';
    END IF;
    
    -- Update status pengumpulan
    UPDATE `pengumpulan_rayhanrp`
    SET `status` = v_new_status
    WHERE `pengumpulan_id` = OLD.`pengumpulan_id`;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_penilaian_after_insert` AFTER INSERT ON `penilaian_rayhanrp` FOR EACH ROW BEGIN
  UPDATE `pengumpulan_rayhanrp`
  SET `status` = 'graded'
  WHERE `pengumpulan_id` = NEW.`pengumpulan_id`;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_penilaian_after_update` AFTER UPDATE ON `penilaian_rayhanrp` FOR EACH ROW BEGIN
  DECLARE v_tenggat DATETIME;
  DECLARE v_waktu_kumpul DATETIME;
  DECLARE v_new_status VARCHAR(20);
  
  -- Jika pengumpulan_id berubah
  IF OLD.`pengumpulan_id` != NEW.`pengumpulan_id` THEN
    
    -- ========== Update pengumpulan LAMA ==========
    -- Cek apakah masih ada penilaian lain untuk pengumpulan lama
    IF EXISTS (SELECT 1 FROM `penilaian_rayhanrp` WHERE `pengumpulan_id` = OLD.`pengumpulan_id` AND `penilaian_id` != OLD.`penilaian_id`) THEN
      -- Masih ada penilaian lain, status tetap 'graded'
      UPDATE `pengumpulan_rayhanrp`
      SET `status` = 'graded'
      WHERE `pengumpulan_id` = OLD.`pengumpulan_id`;
    ELSE
      -- Tidak ada penilaian lain, kembali ke status awal
      SELECT p.`waktu_kumpul`, t.`tenggat`
      INTO v_waktu_kumpul, v_tenggat
      FROM `pengumpulan_rayhanrp` p
      INNER JOIN `tugas_rayhanRP` t ON p.`tugas_id` = t.`tugas_id`
      WHERE p.`pengumpulan_id` = OLD.`pengumpulan_id`
      LIMIT 1;
      
      IF v_waktu_kumpul IS NOT NULL AND v_tenggat IS NOT NULL THEN
        IF v_waktu_kumpul <= v_tenggat THEN
          SET v_new_status = 'dikumpulkan';
        ELSE
          SET v_new_status = 'terlambat';
        END IF;
        
        UPDATE `pengumpulan_rayhanrp`
        SET `status` = v_new_status
        WHERE `pengumpulan_id` = OLD.`pengumpulan_id`;
      END IF;
    END IF;
    
    -- ========== Update pengumpulan BARU ==========
    -- Status baru otomatis menjadi 'graded'
    UPDATE `pengumpulan_rayhanrp`
    SET `status` = 'graded'
    WHERE `pengumpulan_id` = NEW.`pengumpulan_id`;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `reset_password_log_rayhanrp`
--

CREATE TABLE `reset_password_log_rayhanrp` (
  `log_id` int(11) NOT NULL,
  `target_akun_id` int(11) DEFAULT NULL,
  `direset_oleh` int(11) DEFAULT NULL,
  `alasan` text DEFAULT NULL,
  `status` enum('berhasil','gagal','ditolak') NOT NULL DEFAULT 'berhasil',
  `sumber` enum('web','telegram','api','sistem') NOT NULL DEFAULT 'web',
  `ip_address` varchar(45) DEFAULT NULL,
  `waktu` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_nilai_rayhanrp`
--

CREATE TABLE `riwayat_nilai_rayhanrp` (
  `id` int(11) NOT NULL,
  `penilaian_id` int(11) NOT NULL,
  `nilai_lama` decimal(5,2) DEFAULT NULL,
  `nilai_baru` decimal(5,2) NOT NULL,
  `catatan_lama` text DEFAULT NULL,
  `catatan_baru` text DEFAULT NULL,
  `diubah_oleh` int(11) DEFAULT NULL,
  `diubah_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `riwayat_nilai_rayhanrp`
--

INSERT INTO `riwayat_nilai_rayhanrp` (`id`, `penilaian_id`, `nilai_lama`, `nilai_baru`, `catatan_lama`, `catatan_baru`, `diubah_oleh`, `diubah_pada`) VALUES
(2, 2, 99.00, 100.00, '', '', 42, '2026-04-26 19:19:33'),
(3, 2, 100.00, 99.00, '', '', 4, '2026-04-26 20:18:34'),
(4, 2, 99.00, 94.00, '', '', 42, '2026-04-28 18:52:57'),
(5, 2, 94.00, 95.00, '', '', 42, '2026-04-28 18:54:55'),
(6, 2, 95.00, 95.13, '', '', 42, '2026-04-28 19:01:28');

-- --------------------------------------------------------

--
-- Table structure for table `tugas_perorang_rayhanrp`
--

CREATE TABLE `tugas_perorang_rayhanrp` (
  `id` int(11) NOT NULL,
  `tugas_id` int(11) NOT NULL,
  `akun_id` int(11) NOT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tugas_perorang_rayhanrp`
--

INSERT INTO `tugas_perorang_rayhanrp` (`id`, `tugas_id`, `akun_id`, `dibuat_pada`) VALUES
(1, 5, 34, '2026-04-26 18:30:28');

-- --------------------------------------------------------

--
-- Table structure for table `tugas_rayhanrp`
--

CREATE TABLE `tugas_rayhanrp` (
  `tugas_id` int(11) NOT NULL,
  `grup_id` int(11) DEFAULT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `matpel_id` int(11) NOT NULL,
  `tenggat` datetime NOT NULL,
  `poin_maksimal` int(11) NOT NULL DEFAULT 100,
  `lampiran_url` varchar(500) DEFAULT NULL,
  `status` enum('draft','active','closed') NOT NULL DEFAULT 'draft',
  `tipe_tugas` enum('grup','perorang') NOT NULL DEFAULT 'grup',
  `pembuat_id` int(11) DEFAULT NULL,
  `izin_revisi` tinyint(1) NOT NULL DEFAULT 0,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diubah_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tugas_rayhanrp`
--

INSERT INTO `tugas_rayhanrp` (`tugas_id`, `grup_id`, `judul`, `deskripsi`, `matpel_id`, `tenggat`, `poin_maksimal`, `lampiran_url`, `status`, `tipe_tugas`, `pembuat_id`, `izin_revisi`, `dibuat_pada`, `diubah_pada`) VALUES
(4, 3, 'Tugas Akhir', NULL, 6, '2026-05-03 23:59:00', 100, NULL, 'active', 'grup', 42, 0, '2026-04-25 22:24:05', '2026-04-26 19:04:25'),
(5, NULL, 'Remedial TA', NULL, 6, '2026-05-13 23:59:00', 100, NULL, 'active', 'perorang', 42, 0, '2026-04-26 18:30:28', '2026-04-26 19:04:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `akun_rayhanrp`
--
ALTER TABLE `akun_rayhanrp`
  ADD PRIMARY KEY (`akun_id`),
  ADD UNIQUE KEY `uk_akun_nis_nip` (`nis_nip`),
  ADD KEY `idx_akun_role` (`role`),
  ADD KEY `idx_akun_aktif` (`aktif`);

--
-- Indexes for table `akun_telegram_rayhanrp`
--
ALTER TABLE `akun_telegram_rayhanrp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_telegram_akun` (`akun_id`),
  ADD UNIQUE KEY `uk_telegram_chat` (`telegram_chat_id`);

--
-- Indexes for table `audit_log_rayhanrp`
--
ALTER TABLE `audit_log_rayhanrp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_akun` (`akun_id`,`waktu`),
  ADD KEY `idx_audit_aksi` (`aksi`,`waktu`),
  ADD KEY `idx_audit_status` (`status`,`waktu`);

--
-- Indexes for table `grup_anggota_rayhanrp`
--
ALTER TABLE `grup_anggota_rayhanrp`
  ADD PRIMARY KEY (`keanggotaan_id`),
  ADD KEY `idx_anggota_akun` (`akun_id`,`aktif`),
  ADD KEY `idx_anggota_grup` (`grup_id`,`aktif`),
  ADD KEY `idx_anggota_tipe` (`akun_id`,`tipe_keanggotaan`,`aktif`);

--
-- Indexes for table `grup_rayhanrp`
--
ALTER TABLE `grup_rayhanrp`
  ADD PRIMARY KEY (`grup_id`),
  ADD UNIQUE KEY `uk_grup_nama` (`nama_grup`),
  ADD KEY `idx_grup_tipe_aktif` (`aktif`),
  ADD KEY `idx_grup_pembuat` (`pembuat_id`);

--
-- Indexes for table `guru_mengajar_rayhanrp`
--
ALTER TABLE `guru_mengajar_rayhanrp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_guru_scope` (`akun_id`,`grup_id`,`matpel_id`,`hari`) USING BTREE,
  ADD KEY `idx_guru_scope_matpel` (`matpel_id`,`aktif`),
  ADD KEY `idx_guru_scope_guru` (`akun_id`,`aktif`),
  ADD KEY `idx_guru_scope_grup` (`grup_id`,`aktif`);

--
-- Indexes for table `mata_pelajaran_rayhanrp`
--
ALTER TABLE `mata_pelajaran_rayhanrp`
  ADD PRIMARY KEY (`matpel_id`),
  ADD UNIQUE KEY `uk_matpel_kode` (`kode`),
  ADD UNIQUE KEY `uk_matpel_nama` (`nama`),
  ADD KEY `idx_matpel_aktif` (`aktif`);

--
-- Indexes for table `notifikasi_rayhanrp`
--
ALTER TABLE `notifikasi_rayhanrp`
  ADD PRIMARY KEY (`notifikasi_id`),
  ADD KEY `idx_notifikasi_tipe_waktu` (`tipe`,`waktu_kirim`),
  ADD KEY `idx_notifikasi_grup` (`grup_id`),
  ADD KEY `idx_notifikasi_sumber` (`sumber_tipe`,`sumber_id`);

--
-- Indexes for table `pengumpulan_rayhanrp`
--
ALTER TABLE `pengumpulan_rayhanrp`
  ADD PRIMARY KEY (`pengumpulan_id`),
  ADD UNIQUE KEY `uk_pengumpulan_tugas_akun` (`tugas_id`,`akun_id`),
  ADD KEY `idx_pengumpulan_akun` (`akun_id`,`waktu_kumpul`),
  ADD KEY `idx_pengumpulan_status` (`status`);

--
-- Indexes for table `pengumpulan_revisi_rayhanrp`
--
ALTER TABLE `pengumpulan_revisi_rayhanrp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_revisi_nomor` (`pengumpulan_id`,`nomor_revisi`);

--
-- Indexes for table `pengumuman_rayhanrp`
--
ALTER TABLE `pengumuman_rayhanrp`
  ADD PRIMARY KEY (`pengumuman_id`),
  ADD KEY `idx_pengumuman_status` (`status`,`tanggal_tayang`),
  ADD KEY `idx_pengumuman_grup` (`grup_id`),
  ADD KEY `idx_pengumuman_pembuat` (`pembuat_id`),
  ADD KEY `idx_pengumuman_role` (`target_role`,`status`,`tanggal_tayang`);

--
-- Indexes for table `penilaian_rayhanrp`
--
ALTER TABLE `penilaian_rayhanrp`
  ADD PRIMARY KEY (`penilaian_id`),
  ADD UNIQUE KEY `uk_penilaian_pengumpulan` (`pengumpulan_id`),
  ADD KEY `idx_penilaian_dinilai_oleh` (`dinilai_oleh`),
  ADD KEY `idx_penilaian_status` (`status_lulus`);

--
-- Indexes for table `reset_password_log_rayhanrp`
--
ALTER TABLE `reset_password_log_rayhanrp`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_reset_target` (`target_akun_id`,`waktu`),
  ADD KEY `idx_reset_actor` (`direset_oleh`,`waktu`);

--
-- Indexes for table `riwayat_nilai_rayhanrp`
--
ALTER TABLE `riwayat_nilai_rayhanrp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_riwayat_penilaian` (`penilaian_id`,`diubah_pada`),
  ADD KEY `fk_riwayat_diubah_oleh` (`diubah_oleh`);

--
-- Indexes for table `tugas_perorang_rayhanrp`
--
ALTER TABLE `tugas_perorang_rayhanrp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tugas_perorang` (`tugas_id`,`akun_id`),
  ADD KEY `idx_tugas_perorang_akun` (`akun_id`);

--
-- Indexes for table `tugas_rayhanrp`
--
ALTER TABLE `tugas_rayhanrp`
  ADD PRIMARY KEY (`tugas_id`),
  ADD KEY `idx_tugas_grup` (`grup_id`,`status`,`tenggat`),
  ADD KEY `idx_tugas_matpel` (`matpel_id`,`status`,`tenggat`),
  ADD KEY `idx_tugas_pembuat` (`pembuat_id`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `akun_rayhanrp`
--
ALTER TABLE `akun_rayhanrp`
  MODIFY `akun_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `akun_telegram_rayhanrp`
--
ALTER TABLE `akun_telegram_rayhanrp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_log_rayhanrp`
--
ALTER TABLE `audit_log_rayhanrp`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT for table `grup_anggota_rayhanrp`
--
ALTER TABLE `grup_anggota_rayhanrp`
  MODIFY `keanggotaan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `grup_rayhanrp`
--
ALTER TABLE `grup_rayhanrp`
  MODIFY `grup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `guru_mengajar_rayhanrp`
--
ALTER TABLE `guru_mengajar_rayhanrp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `mata_pelajaran_rayhanrp`
--
ALTER TABLE `mata_pelajaran_rayhanrp`
  MODIFY `matpel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifikasi_rayhanrp`
--
ALTER TABLE `notifikasi_rayhanrp`
  MODIFY `notifikasi_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pengumpulan_rayhanrp`
--
ALTER TABLE `pengumpulan_rayhanrp`
  MODIFY `pengumpulan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pengumpulan_revisi_rayhanrp`
--
ALTER TABLE `pengumpulan_revisi_rayhanrp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengumuman_rayhanrp`
--
ALTER TABLE `pengumuman_rayhanrp`
  MODIFY `pengumuman_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `penilaian_rayhanrp`
--
ALTER TABLE `penilaian_rayhanrp`
  MODIFY `penilaian_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reset_password_log_rayhanrp`
--
ALTER TABLE `reset_password_log_rayhanrp`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `riwayat_nilai_rayhanrp`
--
ALTER TABLE `riwayat_nilai_rayhanrp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tugas_perorang_rayhanrp`
--
ALTER TABLE `tugas_perorang_rayhanrp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tugas_rayhanrp`
--
ALTER TABLE `tugas_rayhanrp`
  MODIFY `tugas_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `akun_telegram_rayhanrp`
--
ALTER TABLE `akun_telegram_rayhanrp`
  ADD CONSTRAINT `fk_telegram_akun` FOREIGN KEY (`akun_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `audit_log_rayhanrp`
--
ALTER TABLE `audit_log_rayhanrp`
  ADD CONSTRAINT `fk_audit_akun` FOREIGN KEY (`akun_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `grup_anggota_rayhanrp`
--
ALTER TABLE `grup_anggota_rayhanrp`
  ADD CONSTRAINT `fk_grup_anggota_akun` FOREIGN KEY (`akun_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grup_anggota_grup` FOREIGN KEY (`grup_id`) REFERENCES `grup_rayhanrp` (`grup_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `grup_rayhanrp`
--
ALTER TABLE `grup_rayhanrp`
  ADD CONSTRAINT `fk_grup_pembuat` FOREIGN KEY (`pembuat_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `guru_mengajar_rayhanrp`
--
ALTER TABLE `guru_mengajar_rayhanrp`
  ADD CONSTRAINT `fk_guru_mengajar_akun` FOREIGN KEY (`akun_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_guru_mengajar_grup` FOREIGN KEY (`grup_id`) REFERENCES `grup_rayhanrp` (`grup_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_guru_mengajar_matpel` FOREIGN KEY (`matpel_id`) REFERENCES `mata_pelajaran_rayhanrp` (`matpel_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifikasi_rayhanrp`
--
ALTER TABLE `notifikasi_rayhanrp`
  ADD CONSTRAINT `fk_notifikasi_grup` FOREIGN KEY (`grup_id`) REFERENCES `grup_rayhanrp` (`grup_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `pengumpulan_rayhanrp`
--
ALTER TABLE `pengumpulan_rayhanrp`
  ADD CONSTRAINT `fk_pengumpulan_akun` FOREIGN KEY (`akun_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pengumpulan_tugas` FOREIGN KEY (`tugas_id`) REFERENCES `tugas_rayhanrp` (`tugas_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pengumpulan_revisi_rayhanrp`
--
ALTER TABLE `pengumpulan_revisi_rayhanrp`
  ADD CONSTRAINT `fk_revisi_pengumpulan` FOREIGN KEY (`pengumpulan_id`) REFERENCES `pengumpulan_rayhanrp` (`pengumpulan_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pengumuman_rayhanrp`
--
ALTER TABLE `pengumuman_rayhanrp`
  ADD CONSTRAINT `fk_pengumuman_grup` FOREIGN KEY (`grup_id`) REFERENCES `grup_rayhanrp` (`grup_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pengumuman_pembuat` FOREIGN KEY (`pembuat_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `penilaian_rayhanrp`
--
ALTER TABLE `penilaian_rayhanrp`
  ADD CONSTRAINT `fk_penilaian_dinilai_oleh` FOREIGN KEY (`dinilai_oleh`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penilaian_pengumpulan` FOREIGN KEY (`pengumpulan_id`) REFERENCES `pengumpulan_rayhanrp` (`pengumpulan_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reset_password_log_rayhanrp`
--
ALTER TABLE `reset_password_log_rayhanrp`
  ADD CONSTRAINT `fk_reset_actor` FOREIGN KEY (`direset_oleh`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reset_target` FOREIGN KEY (`target_akun_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `riwayat_nilai_rayhanrp`
--
ALTER TABLE `riwayat_nilai_rayhanrp`
  ADD CONSTRAINT `fk_riwayat_diubah_oleh` FOREIGN KEY (`diubah_oleh`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_riwayat_penilaian` FOREIGN KEY (`penilaian_id`) REFERENCES `penilaian_rayhanrp` (`penilaian_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tugas_perorang_rayhanrp`
--
ALTER TABLE `tugas_perorang_rayhanrp`
  ADD CONSTRAINT `fk_tugas_perorang_akun` FOREIGN KEY (`akun_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tugas_perorang_tugas` FOREIGN KEY (`tugas_id`) REFERENCES `tugas_rayhanrp` (`tugas_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tugas_rayhanrp`
--
ALTER TABLE `tugas_rayhanrp`
  ADD CONSTRAINT `fk_tugas_grup` FOREIGN KEY (`grup_id`) REFERENCES `grup_rayhanrp` (`grup_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tugas_matpel` FOREIGN KEY (`matpel_id`) REFERENCES `mata_pelajaran_rayhanrp` (`matpel_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tugas_pembuat` FOREIGN KEY (`pembuat_id`) REFERENCES `akun_rayhanrp` (`akun_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
