-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 29, 2026 at 03:46 AM
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
-- Database: `hr_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `candidate_id` bigint(20) UNSIGNED NOT NULL,
  `job_posting_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('submitted','screening','shortlisted','interview','assessed','ranked','ranking_sent','offer','hired','rejected') DEFAULT NULL,
  `applied_at` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ranking_notified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `candidate_id`, `job_posting_id`, `status`, `applied_at`, `notes`, `ranking_notified_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'ranking_sent', '2026-05-10', 'dsadas', NULL, '2026-06-23 18:16:32', '2026-06-25 19:47:47'),
(2, 2, 2, '', '2026-05-15', NULL, NULL, '2026-06-23 18:16:32', '2026-06-23 22:53:06'),
(3, 3, 3, 'hired', '2026-05-20', 'Strong background, passed initial screening.', NULL, '2026-06-23 18:16:32', '2026-06-23 22:53:06'),
(4, 4, 4, 'hired', '2026-05-25', 'Interview confirmed with the candidate.', NULL, '2026-06-23 18:16:32', '2026-06-23 22:53:06'),
(5, 5, 5, 'ranking_sent', '2026-06-01', 'Completed written assessment, awaiting ranking.', NULL, '2026-06-23 18:16:32', '2026-06-25 21:16:23'),
(6, 6, 6, '', '2026-06-05', 'Ranked among top candidates for the position.', NULL, '2026-06-23 18:16:32', '2026-06-23 22:06:25'),
(7, 7, 7, '', '2026-06-08', 'Offer sent, awaiting response.', NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32'),
(8, 8, 8, 'ranking_sent', '2026-06-10', 'Candidate accepted the offer.', NULL, '2026-06-23 18:16:32', '2026-06-25 21:17:51'),
(9, 9, 9, 'ranking_sent', '2026-06-12', 'Candidate declined the offer.', NULL, '2026-06-23 18:16:32', '2026-06-25 21:29:16'),
(10, 10, 10, 'hired', '2026-06-14', 'Excellent interview performance. Hired.', NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32'),
(11, 11, 11, 'ranking_sent', '2026-06-18', 'Did not meet minimum qualifications.', NULL, '2026-06-23 18:16:32', '2026-06-25 21:28:09'),
(12, 12, 12, 'ranking_sent', '2026-06-20', NULL, NULL, '2026-06-23 18:16:32', '2026-06-25 21:22:00');

-- --------------------------------------------------------

--
-- Table structure for table `application_documents`
--

CREATE TABLE `application_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `position_title` varchar(255) DEFAULT NULL,
  `item_number` varchar(255) DEFAULT NULL,
  `appointment_status` enum('permanent','temporary','provisional','casual','job_order','ojt') NOT NULL DEFAULT 'job_order',
  `appointment_date` date DEFAULT NULL,
  `onboarding_date` date DEFAULT NULL,
  `appointment_paper_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `application_id`, `position_title`, `item_number`, `appointment_status`, `appointment_date`, `onboarding_date`, `appointment_paper_path`, `created_at`, `updated_at`) VALUES
(1, 3, 'HR Assistant', 'OSEC-DECSB-HRA-001-2026', 'provisional', '2026-06-17', '2026-06-21', NULL, '2026-06-23 22:53:06', '2026-06-23 22:53:06'),
(2, 4, 'Records Officer', 'OSEC-DECSB-AO2-002-2026', 'permanent', '2026-06-15', NULL, NULL, '2026-06-23 22:53:06', '2026-06-23 22:53:06'),
(3, 1, 'Administrative Officer II', NULL, 'provisional', '2026-06-24', NULL, NULL, '2026-06-23 22:53:17', '2026-06-23 22:53:17');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_criteria`
--

CREATE TABLE `assessment_criteria` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `job_posting_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `weight_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_criteria`
--

INSERT INTO `assessment_criteria` (`id`, `job_posting_id`, `name`, `weight_percentage`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(2, 1, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(4, 2, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(5, 2, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(6, 2, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(7, 3, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(8, 3, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(9, 3, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(10, 4, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(11, 4, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(12, 4, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(13, 5, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(14, 5, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(15, 5, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(16, 6, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(17, 6, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(18, 6, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(19, 7, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(20, 7, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(21, 7, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(22, 8, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(23, 8, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(24, 8, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(25, 9, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(26, 9, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(27, 9, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(28, 10, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(29, 10, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(30, 10, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(31, 11, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(32, 11, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(33, 11, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(34, 12, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(35, 12, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(36, 12, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(37, 14, 'Technical skills', 40.00, 'Job-specific knowledge and competence', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(38, 14, 'Communication', 30.00, 'Clarity, listening, and interpersonal skills', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(39, 14, 'Problem solving', 30.00, 'Analytical thinking and adaptability', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(41, 1, 'Problem Solving', 20.00, NULL, '2026-06-23 21:58:50', '2026-06-23 21:58:50'),
(42, 1, 'test', 10.00, NULL, '2026-06-23 21:58:57', '2026-06-23 21:58:57');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `phone`, `address`, `resume_path`, `photo_path`, `created_at`, `updated_at`) VALUES
(1, 'Maria', 'Lopez', 'Santos', 'therable13@gmail.com', '0917-123-4567', 'Imus, Cavite', NULL, NULL, '2026-06-23 18:16:31', '2026-06-23 18:16:31'),
(2, 'Juan', NULL, 'Dela Cruz', 'juan.delacruz@email.com', '0918-234-5678', 'Bacoor, Cavite', NULL, NULL, '2026-06-23 18:16:31', '2026-06-23 18:16:31'),
(3, 'Ana', 'Marie', 'Reyes', 'ana.reyes@email.com', '0919-345-6789', 'Dasmariñas, Cavite', NULL, NULL, '2026-06-23 18:16:31', '2026-06-23 18:16:31'),
(4, 'Pedro', NULL, 'Garcia', 'pedro.garcia@email.com', '0920-456-7890', 'Trece Martires, Cavite', NULL, NULL, '2026-06-23 18:16:31', '2026-06-23 18:16:31'),
(5, 'Liza', 'Anne', 'Mendoza', 'liza.mendoza@email.com', '0921-567-8901', 'General Trias, Cavite', NULL, NULL, '2026-06-23 18:16:31', '2026-06-23 18:16:31'),
(6, 'Carlo', NULL, 'Villanueva', 'carlo.villanueva@email.com', '0922-678-9012', 'Tanza, Cavite', NULL, NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32'),
(7, 'Jasmine', 'Rose', 'Ramos', 'jasmine.ramos@email.com', '0923-789-0123', 'Naic, Cavite', NULL, NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32'),
(8, 'Mark', NULL, 'Torres', 'mark.torres@email.com', '0924-890-1234', 'Rosario, Cavite', NULL, NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32'),
(9, 'Kristine', 'Joy', 'Aquino', 'kristine.aquino@email.com', '0925-901-2345', 'Carmona, Cavite', NULL, NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32'),
(10, 'Ramon', NULL, 'Bautista', 'ramon.bautista@email.com', '0926-012-3456', 'Silang, Cavite', NULL, NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32'),
(11, 'Cherry', 'Mae', 'Fernandez', 'cherry.fernandez@email.com', '0927-123-4567', 'Indang, Cavite', NULL, NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32'),
(12, 'Noel', NULL, 'Castillo', 'noel.castillo@email.com', '0928-234-5678', 'Amadeo, Cavite', NULL, NULL, '2026-06-23 18:16:32', '2026-06-23 18:16:32');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_assessments`
--

CREATE TABLE `candidate_assessments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `assessment_criteria_id` bigint(20) UNSIGNED NOT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `evaluator_remarks` text DEFAULT NULL,
  `evaluated_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidate_assessments`
--

INSERT INTO `candidate_assessments` (`id`, `application_id`, `assessment_criteria_id`, `score`, `evaluator_remarks`, `evaluated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 32.80, NULL, NULL, '2026-06-23 21:50:33', '2026-06-23 21:59:22'),
(2, 1, 2, 30.00, NULL, NULL, '2026-06-23 21:50:33', '2026-06-23 21:59:22'),
(4, 2, 4, 28.40, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(5, 2, 5, 27.30, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(6, 2, 6, 28.20, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(7, 3, 7, 36.40, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(8, 3, 8, 23.40, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(9, 3, 9, 25.20, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(10, 4, 10, 34.80, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(11, 4, 11, 27.30, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(12, 4, 12, 26.40, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(13, 5, 13, 34.40, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(14, 5, 14, 23.40, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(15, 5, 15, 25.20, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(16, 6, 16, 32.40, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(17, 6, 17, 27.00, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(18, 6, 18, 25.20, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(19, 7, 19, 38.00, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(20, 7, 20, 21.00, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(21, 7, 21, 21.90, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(22, 8, 22, 35.60, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(23, 8, 23, 27.30, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(24, 8, 24, 26.10, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(25, 9, 25, 10.00, NULL, NULL, '2026-06-23 21:50:33', '2026-06-25 21:29:10'),
(26, 9, 26, 10.00, NULL, NULL, '2026-06-23 21:50:33', '2026-06-25 21:29:10'),
(27, 9, 27, 10.00, NULL, NULL, '2026-06-23 21:50:33', '2026-06-25 21:29:10'),
(28, 10, 28, 33.20, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(29, 10, 29, 24.00, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(30, 10, 30, 21.60, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(31, 11, 31, 38.00, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(32, 11, 32, 21.00, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(33, 11, 33, 24.00, 'Seeded sample evaluation.', 'HR Panel', '2026-06-23 21:50:33', '2026-06-23 21:50:33'),
(34, 12, 34, 32.80, NULL, NULL, '2026-06-23 21:50:33', '2026-06-25 21:21:55'),
(35, 12, 35, 30.00, NULL, NULL, '2026-06-23 21:50:33', '2026-06-25 21:21:55'),
(36, 12, 36, 23.70, NULL, NULL, '2026-06-23 21:50:33', '2026-06-25 21:21:55'),
(37, 1, 41, 20.00, NULL, NULL, '2026-06-23 21:59:22', '2026-06-23 21:59:22'),
(38, 1, 42, 10.00, NULL, NULL, '2026-06-23 21:59:22', '2026-06-23 21:59:22');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interview_schedules`
--

CREATE TABLE `interview_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('open_ranking','interview','exam') NOT NULL DEFAULT 'interview',
  `scheduled_at` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `interviewer_name` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `interview_schedules`
--

INSERT INTO `interview_schedules` (`id`, `application_id`, `type`, `scheduled_at`, `location`, `interviewer_name`, `status`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 'interview', '2026-06-14 09:00:00', 'HR Conference Room', 'Mr. Alvarez', 'scheduled', NULL, '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(2, 2, 'exam', '2026-06-16 10:00:00', 'Testing Room B', 'Ms. Cruz', 'scheduled', NULL, '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(3, 3, 'open_ranking', '2026-06-19 11:00:00', 'Main Hall', 'HR Panel', 'completed', 'Session completed successfully.', '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(4, 4, 'interview', '2026-06-21 12:00:00', 'HR Office', 'Mr. Santos', 'completed', 'Session completed successfully.', '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(5, 5, 'exam', '2026-06-23 13:00:00', 'Conference Room A', 'Ms. Reyes', 'cancelled', 'Cancelled due to scheduling conflict.', '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(6, 6, 'open_ranking', '2026-06-25 14:00:00', 'HR Conference Room', 'Mr. Alvarez', 'no_show', 'Candidate did not show up.', '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(7, 7, 'interview', '2026-06-27 09:00:00', 'Testing Room B', 'Ms. Cruz', 'scheduled', NULL, '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(8, 8, 'exam', '2026-06-29 10:00:00', 'Main Hall', 'HR Panel', 'scheduled', NULL, '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(9, 9, 'open_ranking', '2026-07-01 11:00:00', 'HR Office', 'Mr. Santos', 'completed', 'Session completed successfully.', '2026-06-23 18:40:21', '2026-06-23 18:40:21'),
(10, 10, 'interview', '2026-07-04 12:00:00', 'Conference Room A', 'Ms. Reyes', 'completed', 'Session completed successfully.', '2026-06-23 18:40:21', '2026-06-23 18:40:21');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_offers`
--

CREATE TABLE `job_offers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `compensation` decimal(12,2) DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `offer_sent_at` date DEFAULT NULL,
  `response_deadline` date DEFAULT NULL,
  `status` enum('draft','sent','accepted','declined','expired') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_offers`
--

INSERT INTO `job_offers` (`id`, `application_id`, `compensation`, `benefits`, `terms`, `offer_sent_at`, `response_deadline`, `status`, `created_at`, `updated_at`) VALUES
(2, 5, 14634.00, NULL, NULL, '2026-06-24', '2026-06-25', 'sent', '2026-06-23 22:14:30', '2026-06-23 23:06:10');

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `duties_responsibilities` text DEFAULT NULL,
  `qualification_standards` text DEFAULT NULL,
  `qualification_education` text DEFAULT NULL,
  `qualification_training` text DEFAULT NULL,
  `qualification_experience` text DEFAULT NULL,
  `qualification_eligibility` text DEFAULT NULL,
  `mandatory_requirements` text DEFAULT NULL,
  `additional_requirements` text DEFAULT NULL,
  `place_of_assignment` varchar(255) DEFAULT NULL,
  `employment_type` varchar(255) DEFAULT NULL,
  `salary_grade` varchar(255) DEFAULT NULL,
  `vacancies` int(11) NOT NULL DEFAULT 1,
  `posted_at` date DEFAULT NULL,
  `closes_at` date DEFAULT NULL,
  `status` enum('draft','open','filled','closed') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_postings`
--

INSERT INTO `job_postings` (`id`, `title`, `description`, `duties_responsibilities`, `qualification_standards`, `qualification_education`, `qualification_training`, `qualification_experience`, `qualification_eligibility`, `mandatory_requirements`, `additional_requirements`, `place_of_assignment`, `employment_type`, `salary_grade`, `vacancies`, `posted_at`, `closes_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Administrative Officer II', 'Responsible for administrative support and records management for the office.', 'Maintain accurate records and filing systems.\nPrepare correspondence and reports.\nAssist in scheduling meetings and appointments.\nCoordinate with other departments as needed.', 'Bachelor\'s degree relevant to the job.\nAt least 1 year of relevant work experience.\nCivil Service eligibility (Professional) preferred.', NULL, NULL, NULL, NULL, NULL, NULL, 'Main Office', 'Regular', NULL, 2, '2026-06-01', '2026-07-01', 'open', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(2, 'IT Support Specialist', 'Provides technical support to staff and maintains office equipment and systems.', 'Troubleshoot hardware and software issues.\nManage helpdesk tickets and resolve within SLA.\nPerform routine maintenance on workstations and network equipment.\nAssist in onboarding new employees with IT setup.', 'Bachelor\'s degree in Information Technology, Computer Science, or related field.\nFamiliarity with Windows and basic networking.\nGood communication and problem-solving skills.', NULL, NULL, NULL, NULL, NULL, NULL, 'IT Department', 'Job Order', NULL, 1, '2026-06-05', '2026-06-30', 'open', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(3, 'HR Assistant', 'Supports HR operations including recruitment, onboarding, and employee records.', 'Screen applications and shortlist candidates.\nSchedule interviews and coordinate with panel members.\nPrepare employment documents and contracts.\nMaintain 201 files and HR database.', 'Bachelor\'s degree in Human Resource Management, Psychology, or related field.\nStrong organizational and interpersonal skills.\nProficient in MS Office applications.', NULL, NULL, NULL, NULL, NULL, NULL, 'HR Department', 'Provisional', NULL, 1, '2026-05-15', '2026-06-15', 'filled', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(4, 'Records Officer', 'Manages document filing, archiving, and retrieval systems for the office.', 'File and organize incoming and outgoing documents.\nRespond to records requests from staff and external parties.\nMaintain confidentiality of sensitive files.\nAssist in digitization of paper records.', 'At least high school graduate with relevant clerical training.\nAttention to detail and good organizational skills.\nBasic computer literacy.', NULL, NULL, NULL, NULL, NULL, NULL, 'Records Section', 'Casual', NULL, 1, '2026-04-20', '2026-05-20', 'closed', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(5, 'Budget Analyst', 'Prepares and monitors budget proposals and financial reports for the office.', 'Prepare annual budget proposals and work plans.\nMonitor fund utilization against approved budget.\nPrepare financial and variance reports.\nCoordinate with accounting and finance units.', 'Bachelor\'s degree in Accountancy, Finance, Economics, or related field.\nAt least 1 year of experience in budgeting or financial analysis.\nProficient in spreadsheet applications.', NULL, NULL, NULL, NULL, NULL, NULL, 'Budget Office', 'Regular', NULL, 1, '2026-06-10', '2026-07-10', 'open', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(6, 'Procurement Aide', 'Assists in the procurement of goods, services, and supplies for the office.', 'Prepare purchase requests and canvass forms.\nAssist in bidding and supplier documentation.\nMonitor delivery schedules and inventory of supplies.\nCoordinate with the Bids and Awards Committee.', 'Bachelor\'s degree preferred, or relevant work experience.\nFamiliarity with procurement processes is an advantage.\nDetail-oriented and organized.', NULL, NULL, NULL, NULL, NULL, NULL, 'Procurement Office', 'Job Order', NULL, 2, '2026-06-12', '2026-07-12', 'open', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(7, 'Network Administrator', 'Manages and maintains the office network infrastructure and server systems.', 'Monitor network performance and uptime.\nConfigure and maintain routers, switches, and firewalls.\nImplement security policies and backup procedures.\nProvide Tier 2 technical support for network issues.', 'Bachelor\'s degree in IT, Computer Engineering, or related field.\nAt least 2 years of experience in network administration.\nCertifications such as CCNA are an advantage.', NULL, NULL, NULL, NULL, NULL, NULL, 'IT Department', 'Regular', NULL, 1, '2026-05-01', '2026-06-01', 'filled', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(8, 'Customer Service Representative', 'Handles front-desk inquiries and assists clients with their concerns.', 'Attend to walk-in and phone inquiries.\nProcess client requests and route concerns to proper units.\nMaintain a log of inquiries and resolutions.\nProvide excellent and courteous service at all times.', 'At least 2 years of college education.\nGood communication skills, both written and verbal.\nPleasant disposition and customer-service orientation.', NULL, NULL, NULL, NULL, NULL, NULL, 'Front Desk', 'Casual', NULL, 3, '2026-06-18', '2026-07-18', 'open', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(9, 'Graphic Design Intern', 'Assists the communications team with visual materials for office campaigns.', 'Design posters, social media graphics, and presentation materials.\nAssist in photo and video documentation of office events.\nSupport the communications team in branding consistency.', 'Currently enrolled in a relevant college course (e.g. Multimedia Arts, Fine Arts, IT).\nProficient in design tools such as Canva, Photoshop, or Illustrator.\nCreative and able to work under minimal supervision.', NULL, NULL, NULL, NULL, NULL, NULL, 'Communications Unit', 'On-the-Job Trainee', NULL, 2, '2026-06-20', '2026-07-20', 'open', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(10, 'Legal Researcher', 'Conducts legal research and drafts documents in support of the legal office.', 'Conduct legal research on relevant laws, rules, and jurisprudence.\nDraft legal opinions, memos, and contracts under supervision.\nAssist in case documentation and record-keeping.\nAttend hearings or meetings as needed.', 'Bachelor\'s degree in Political Science, Legal Management, or related field.\nLaw graduate or law student preferred but not required.\nStrong research and writing skills.', NULL, NULL, NULL, NULL, NULL, NULL, 'Legal Office', 'Provisional', NULL, 1, '2026-06-08', '2026-07-08', 'open', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(11, 'Maintenance Worker', 'Performs general maintenance and upkeep of office facilities and grounds.', 'Perform minor repairs on office facilities and equipment.\nMaintain cleanliness and orderliness of premises and grounds.\nMonitor and report facility issues to the administrative unit.\nAssist in setup for office events.', 'At least elementary or high school graduate.\nBasic skills in carpentry, plumbing, or electrical work an advantage.\nPhysically fit and reliable.', NULL, NULL, NULL, NULL, NULL, NULL, 'General Services Unit', 'Casual', NULL, 2, '2026-03-01', '2026-03-31', 'closed', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(12, 'Data Encoder', 'Encodes and validates data for various office records and systems.', 'Encode data accurately into office databases and systems.\nValidate and cross-check entries against source documents.\nGenerate basic reports from encoded data.\nMaintain data confidentiality and integrity.', 'At least 2 years of college education.\nFast and accurate typing skills.\nFamiliarity with spreadsheet and database applications.', NULL, NULL, NULL, NULL, NULL, NULL, 'Management Information Systems Office', 'Job Order', NULL, 2, '2026-06-22', '2026-07-22', 'draft', '2026-06-23 18:00:17', '2026-06-23 18:00:17'),
(14, 'sdsad', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'dsadsa', 'Regular', NULL, 1, '2026-06-10', '2026-06-27', 'draft', '2026-06-23 18:07:41', '2026-06-23 18:07:41');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_06_24_000001_create_job_postings_table', 2),
(5, '2026_06_24_000002_create_candidates_table', 2),
(6, '2026_06_24_000003_create_applications_table', 2),
(7, '2026_06_24_000004_create_application_documents_table', 2),
(8, '2026_06_24_000005_create_interview_schedules_table', 2),
(9, '2026_06_24_000006_create_assessment_criteria_table', 2),
(10, '2026_06_24_000007_create_candidate_assessments_table', 2),
(11, '2026_06_24_000008_create_job_offers_table', 2),
(12, '2026_06_24_000009_create_talent_pools_table', 2),
(13, '2026_06_24_000010_create_appointments_table', 2),
(14, '2025_06_26_000001_add_notified_at_to_applications_table', 3),
(15, '2026_06_25_000001_add_salary_grade_to_job_postings_table', 3),
(16, '2026_06_25_140000_add_qualification_breakdown_to_job_postings_table', 3),
(17, '2026_06_25_150000_create_requirement_items_table', 3),
(18, '2026_06_25_150100_create_job_posting_requirement_item_table', 3),
(19, '2026_06_25_200000_drop_job_posting_requirement_item_table', 3),
(20, '2026_06_25_200001_drop_requirement_items_table', 3),
(21, '2026_06_25_200002_add_requirements_text_to_job_postings_table', 3);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('EZ8fiuzKgAdanSEB9D4QRllrmOJ4IMS7vne0s8qW', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaVRBQndSVVdCdFNCSlRWcTdzVlI0VDdtSWs0VzFaeFZlZXVoeGRvTyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hcHBsaWNhdGlvbnMiO3M6NToicm91dGUiO3M6MTg6ImFwcGxpY2F0aW9ucy5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1782451789),
('JVlhzFlKijxChVpfoliioVeBPgforHYyPSVaPGpH', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQldzVTlEeFJXVzJ5S25ndnREREU1RHFFVEhHamFqMFc2Nk1kRm5wTSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hcHBsaWNhdGlvbnMiO3M6NToicm91dGUiO3M6MTg6ImFwcGxpY2F0aW9ucy5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1782693944),
('MKdLIw1YNXITapt9RbB3z7K1IkTaYL4NudtlwMiM', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ0JnakI2a2RUbnByMTZENHZvNTBmSE1nbFQ3VE1nRk1yNXNpZG9FciI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hc3Nlc3NtZW50cyI7czo1OiJyb3V0ZSI7czoxNzoiYXNzZXNzbWVudHMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1782446841),
('ofdC7GsKfuY6zSQNqh5lQKEefN55NwZkwt1HkZwa', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieEVtQWxzOExSejhnYlljRkd2UkR5emhKNU1UcTk1ZG02ZHRtS0ZMQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDg6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hc3Nlc3NtZW50cz9qb2JfcG9zdGluZz0xMiI7czo1OiJyb3V0ZSI7czoxNzoiYXNzZXNzbWVudHMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1782451321),
('Xf866A2eh9AsVZu5tQHDcjeQrBI6UIBbaFeXymCg', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVTMzb0x1VzliM1JnZnVJSExGQ3dLMXFtbEtGZnRKaDFJQ25NR2J3bSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1782285589),
('xSc9btiov79qYOUsLjqn1h6XO5SJrR9JIyeOgyPT', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieEdSVG9lYk5MZFJ2Vm84QVV5VEQ0M0pKUnhNeU9hbTNvdzI0UE03MSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hcHBsaWNhdGlvbnMiO3M6NToicm91dGUiO3M6MTg6ImFwcGxpY2F0aW9ucy5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1782693945),
('ZZZfHEbCqCU1X1pDsq2Bl6n3Ba6zWo4WI1Cvx4B6', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWXd5TEJUR2ptQnV6RVA2OGhQS1ZRQ2k1S3JQUmFWbDRMY0EzSnpkbiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hcHBsaWNhdGlvbnMiO3M6NToicm91dGUiO3M6MTg6ImFwcGxpY2F0aW9ucy5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1782697071);

-- --------------------------------------------------------

--
-- Table structure for table `talent_pools`
--

CREATE TABLE `talent_pools` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `candidate_id` bigint(20) UNSIGNED NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `added_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applications_candidate_id_foreign` (`candidate_id`),
  ADD KEY `applications_job_posting_id_foreign` (`job_posting_id`);

--
-- Indexes for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_documents_application_id_foreign` (`application_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointments_application_id_foreign` (`application_id`);

--
-- Indexes for table `assessment_criteria`
--
ALTER TABLE `assessment_criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_criteria_job_posting_id_foreign` (`job_posting_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `candidates_email_unique` (`email`);

--
-- Indexes for table `candidate_assessments`
--
ALTER TABLE `candidate_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_assessments_application_id_foreign` (`application_id`),
  ADD KEY `candidate_assessments_assessment_criteria_id_foreign` (`assessment_criteria_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `interview_schedules_application_id_foreign` (`application_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `job_offers`
--
ALTER TABLE `job_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_offers_application_id_foreign` (`application_id`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `talent_pools`
--
ALTER TABLE `talent_pools`
  ADD PRIMARY KEY (`id`),
  ADD KEY `talent_pools_candidate_id_foreign` (`candidate_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `application_documents`
--
ALTER TABLE `application_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `assessment_criteria`
--
ALTER TABLE `assessment_criteria`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `candidate_assessments`
--
ALTER TABLE `candidate_assessments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_offers`
--
ALTER TABLE `job_offers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `talent_pools`
--
ALTER TABLE `talent_pools`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_job_posting_id_foreign` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD CONSTRAINT `application_documents_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_criteria`
--
ALTER TABLE `assessment_criteria`
  ADD CONSTRAINT `assessment_criteria_job_posting_id_foreign` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_assessments`
--
ALTER TABLE `candidate_assessments`
  ADD CONSTRAINT `candidate_assessments_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_assessments_assessment_criteria_id_foreign` FOREIGN KEY (`assessment_criteria_id`) REFERENCES `assessment_criteria` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  ADD CONSTRAINT `interview_schedules_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_offers`
--
ALTER TABLE `job_offers`
  ADD CONSTRAINT `job_offers_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `talent_pools`
--
ALTER TABLE `talent_pools`
  ADD CONSTRAINT `talent_pools_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
