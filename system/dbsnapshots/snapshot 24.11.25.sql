-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql100.infinityfree.com
-- Generation Time: Nov 23, 2025 at 07:47 PM
-- Server version: 10.6.22-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40307645_devknowledgebase`
--

-- --------------------------------------------------------

--
-- Table structure for table `bug_reports`
--

CREATE TABLE `bug_reports` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'Brief title of the bug',
  `description` text NOT NULL COMMENT 'Detailed description of the bug',
  `page_url` varchar(500) NOT NULL COMMENT 'URL where the bug was found',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium' COMMENT 'Bug priority level',
  `steps_to_reproduce` text DEFAULT NULL COMMENT 'Detailed steps to reproduce the bug',
  `expected_behavior` text DEFAULT NULL COMMENT 'What should have happened',
  `actual_behavior` text DEFAULT NULL COMMENT 'What actually happened',
  `user_id` int(11) NOT NULL COMMENT 'ID of user who reported the bug',
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open' COMMENT 'Current status of the bug',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'When the bug was reported',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'When the bug was last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bug reports submitted through the system';

--
-- Dumping data for table `bug_reports`
--

INSERT INTO `bug_reports` (`id`, `title`, `description`, `page_url`, `priority`, `steps_to_reproduce`, `expected_behavior`, `actual_behavior`, `user_id`, `status`, `created_at`, `updated_at`) VALUES
(5, 'Quiz_results.php', 'Deprecated: strtotime(): Passing null to parameter #1 ($datetime) of type string is deprecated in /home/vol1000_2/infinityfree.com/if0_40307645/devknowledgebase.xo.je/htdocs/quiz_results.php on line 694\r\n\r\nfor an attempt that is not finished when going from admin analytics dashboard', ' ', 'medium', ' ', ' ', ' ', 1, 'resolved', '2025-11-14 11:53:40', '2025-11-19 11:48:32'),
(6, 'No. of items showing categories and subcategories', 'The page is showing the number of posts, categories and subcategories when it should only count posts.', 'https://devknowledgebase.xo.je/edit_course.php?id=2', 'medium', ' ', ' ', ' ', 1, 'resolved', '2025-11-14 13:26:42', '2025-11-19 11:34:50'),
(7, 'Search broken', 'When searching it sends it to the given link and says 404', 'https://devknowledgebase.xo.je/HTML_ACTION_PATH?q=test', 'medium', ' ', ' ', ' ', 1, 'resolved', '2025-11-14 15:47:55', '2025-11-19 11:16:12'),
(8, 'Not showing courses', 'Induction course was set to General Warehouse, yet on the page, it said there was 0 content set for it.', 'https://devknowledgebase.xo.je/admin/manage_departments.php', 'medium', ' ', ' ', ' ', 1, 'resolved', '2025-11-20 14:14:11', '2025-11-20 17:05:50');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `visibility` enum('public','hidden','restricted','it_only') NOT NULL DEFAULT 'public' COMMENT 'Category visibility: public (everyone), hidden (only admin), restricted (specific users), it_only (Super Admins only)',
  `allowed_users` text DEFAULT NULL COMMENT 'JSON array of user IDs who can see restricted categories',
  `visibility_note` varchar(255) DEFAULT NULL COMMENT 'Admin note about visibility restrictions',
  `allowed_departments` text DEFAULT NULL COMMENT 'JSON array of department IDs allowed to view this category'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `user_id`, `name`, `icon`, `created_at`, `updated_at`, `visibility`, `allowed_users`, `visibility_note`, `allowed_departments`) VALUES
(8, 1, 'New user setup', '', '2025-11-03 10:41:58', '2025-11-05 23:13:45', 'it_only', NULL, '', NULL),
(10, 1, 'Nightshift', '', '2025-11-03 15:47:47', '2025-11-05 23:15:58', 'public', NULL, '', NULL),
(11, 1, 'Toll', '', '2025-11-03 15:51:59', '2025-11-03 15:51:59', 'public', NULL, NULL, NULL),
(12, 1, 'Phoenix', '', '2025-11-03 16:31:16', '2025-11-03 16:31:16', 'public', NULL, NULL, NULL),
(13, 1, 'Big Chill', '', '2025-11-03 16:50:54', '2025-11-04 11:42:24', 'public', NULL, '', NULL),
(14, 1, 'Datto', '', '2025-11-05 08:47:40', '2025-11-05 09:04:27', 'it_only', NULL, '', NULL),
(15, 1, 'IT Glue', '', '2025-11-05 08:48:08', '2025-11-05 09:06:41', 'it_only', NULL, '', NULL),
(17, 1, 'Existing Users', '', '2025-11-06 09:16:57', '2025-11-06 09:16:57', 'it_only', NULL, '', NULL),
(18, 1, 'Leaving User', '', '2025-11-06 09:18:01', '2025-11-06 09:18:01', 'it_only', NULL, '', NULL),
(19, 1, 'Printers', '', '2025-11-06 09:20:55', '2025-11-06 09:20:55', 'it_only', NULL, '', NULL),
(20, 1, 'Induction', '', '2025-11-14 13:52:31', '2025-11-14 13:52:31', 'public', NULL, '', NULL),
(21, 1, 'Thunderbird Admin Misc', '', '2025-11-21 09:52:26', '2025-11-21 09:52:26', 'it_only', NULL, '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course_departments`
--

CREATE TABLE `course_departments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Maps training courses to departments (many-to-many)';

--
-- Dumping data for table `course_departments`
--

INSERT INTO `course_departments` (`id`, `course_id`, `department_id`, `assigned_date`, `assigned_by`) VALUES
(1, 1, 1, '2025-11-20 01:25:55', 1);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Stores department definitions';

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'General Warehouse', 'General Warehouse Department', 1, '2025-11-19 23:06:04', '2025-11-19 23:06:04');

-- --------------------------------------------------------

--
-- Table structure for table `edit_requests`
--

CREATE TABLE `edit_requests` (
  `id` int(11) NOT NULL,
  `item_type` enum('category','subcategory') NOT NULL,
  `item_id` int(11) NOT NULL,
  `current_name` varchar(255) NOT NULL,
  `requested_name` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reason` text NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `edit_requests`
--

INSERT INTO `edit_requests` (`id`, `item_type`, `item_id`, `current_name`, `requested_name`, `user_id`, `status`, `admin_note`, `created_at`, `updated_at`, `reviewed_by`, `reviewed_at`, `reason`) VALUES
(1, 'category', 13, 'Big Chill', 'Big Chill TEST', 2, 'approved', '', '2025-11-03 20:49:23', '2025-11-03 22:42:00', 1, '2025-11-03 22:42:00', 'TEST'),
(2, 'category', 13, 'Big Chill', 'Big Chill TEST', 3, 'declined', 'TEST', '2025-11-03 22:56:52', '2025-11-03 23:02:12', 2, '2025-11-03 23:02:12', 'TEST');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `reply_id` int(11) DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_type_category` enum('download','preview') NOT NULL DEFAULT 'download',
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `post_id`, `reply_id`, `original_filename`, `stored_filename`, `file_path`, `file_size`, `file_type`, `file_type_category`, `uploaded_at`) VALUES
(21, 34, NULL, '1762141492_How_to_use_Sales_Order_Dashboard.pdf', '1762336934_preview_1762141492_How_to_use_Sales_Order_Dashboard.pdf', 'uploads/files/preview/1762336934_preview_1762141492_How_to_use_Sales_Order_Dashboard.pdf', 966628, 'application/pdf', 'preview', '2025-11-05 23:02:14'),
(22, 35, NULL, '1762141404_How_to_use_Allocation_Dashboards.pdf', '1762337001_preview_1762141404_How_to_use_Allocation_Dashboards.pdf', 'uploads/files/preview/1762337001_preview_1762141404_How_to_use_Allocation_Dashboards.pdf', 1472687, 'application/pdf', 'preview', '2025-11-05 23:03:21'),
(23, 36, NULL, '1762141573_Reprint_Dg_Forms.pdf', '1762337056_preview_1762141573_Reprint_Dg_Forms.pdf', 'uploads/files/preview/1762337056_preview_1762141573_Reprint_Dg_Forms.pdf', 13788, 'application/pdf', 'preview', '2025-11-05 23:04:16'),
(24, 37, NULL, '1762141458_How_to_use_Inventory_Maintenance___Expiry_Dashboard.pdf', '1762337093_preview_1762141458_How_to_use_Inventory_Maintenance___Expiry_Dashboard.pdf', 'uploads/files/preview/1762337093_preview_1762141458_How_to_use_Inventory_Maintenance___Expiry_Dashboard.pdf', 1132911, 'application/pdf', 'preview', '2025-11-05 23:04:53'),
(25, 38, NULL, '1762142075_HAMI_warehouse_manual_v1.docx', '1762337128_preview_1762142075_HAMI_warehouse_manual_v1.docx', 'uploads/files/preview/1762337128_preview_1762142075_HAMI_warehouse_manual_v1.docx', 5655644, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'preview', '2025-11-05 23:05:28'),
(26, 39, NULL, 'Create New User Phoenix.pdf', '1762337222_preview_Create_New_User_Phoenix.pdf', 'uploads/files/preview/1762337222_preview_Create_New_User_Phoenix.pdf', 279170, 'application/pdf', 'preview', '2025-11-05 23:07:02'),
(27, 40, NULL, 'Chagning printers.pdf', '1762337275_preview_Chagning_printers.pdf', 'uploads/files/preview/1762337275_preview_Chagning_printers.pdf', 4141147, 'application/pdf', 'preview', '2025-11-05 23:07:55'),
(28, 41, NULL, '1762141741_Damaged_Product_Adjustment.pdf', '1762337335_preview_1762141741_Damaged_Product_Adjustment.pdf', 'uploads/files/preview/1762337335_preview_1762141741_Damaged_Product_Adjustment.pdf', 27603, 'application/pdf', 'preview', '2025-11-05 23:08:55'),
(29, 42, NULL, '1762141690_Scan_Items_No_Scanner.pdf', '1762337363_preview_1762141690_Scan_Items_No_Scanner.pdf', 'uploads/files/preview/1762337363_preview_1762141690_Scan_Items_No_Scanner.pdf', 21169, 'application/pdf', 'preview', '2025-11-05 23:09:23'),
(30, 43, NULL, '1762138453_Submit_Toll_Orders_Manually.pdf', '1762337397_preview_1762138453_Submit_Toll_Orders_Manually.pdf', 'uploads/files/preview/1762337397_preview_1762138453_Submit_Toll_Orders_Manually.pdf', 471371, 'application/pdf', 'preview', '2025-11-05 23:09:57'),
(32, 45, NULL, '1762138286_Lockup_Procedure_Nightshift.pdf', '1762337465_preview_1762138286_Lockup_Procedure_Nightshift.pdf', 'uploads/files/preview/1762337465_preview_1762138286_Lockup_Procedure_Nightshift.pdf', 68460, 'application/pdf', 'preview', '2025-11-05 23:11:05'),
(33, 46, NULL, 'Pallet booking Big Chill.pdf', '1762337548_preview_Pallet_booking_Big_Chill.pdf', 'uploads/files/preview/1762337548_preview_Pallet_booking_Big_Chill.pdf', 4656181, 'application/pdf', 'preview', '2025-11-05 23:12:28'),
(34, 47, NULL, 'Reset Phoenix Password.pdf', '1762373756_preview_Reset_Phoenix_Password.pdf', 'uploads/files/preview/1762373756_preview_Reset_Phoenix_Password.pdf', 4231951, 'application/pdf', 'preview', '2025-11-06 09:15:56'),
(35, 48, NULL, 'Remove User All Staff Distribution.pdf', '1762373919_preview_Remove_User_All_Staff_Distribution.pdf', 'uploads/files/preview/1762373919_preview_Remove_User_All_Staff_Distribution.pdf', 272133, 'application/pdf', 'preview', '2025-11-06 09:18:39'),
(36, 49, NULL, 'HP Probook Setup.pdf', '1762373952_preview_HP_Probook_Setup.pdf', 'uploads/files/preview/1762373952_preview_HP_Probook_Setup.pdf', 4300607, 'application/pdf', 'preview', '2025-11-06 09:19:12'),
(37, 50, NULL, 'Forwarding Shared Mailbox Emails to a User via Distribution List (DL) – Microsoft 365 Admin Center.pdf', '1762374005_preview_Forwarding_Shared_Mailbox_Emails_to_a_User_via_Distribution_List__DL______Microsoft_365_Admin_Center.pdf', 'uploads/files/preview/1762374005_preview_Forwarding_Shared_Mailbox_Emails_to_a_User_via_Distribution_List__DL______Microsoft_365_Admin_Center.pdf', 4070319, 'application/pdf', 'preview', '2025-11-06 09:20:05'),
(38, 51, NULL, 'Fixing Printer Install Errors (Elevation).pdf', '1762374159_preview_Fixing_Printer_Install_Errors__Elevation_.pdf', 'uploads/files/preview/1762374159_preview_Fixing_Printer_Install_Errors__Elevation_.pdf', 4023302, 'application/pdf', 'preview', '2025-11-06 09:22:39'),
(39, 52, NULL, 'Fix Datto Local Login.pdf', '1762374260_preview_Fix_Datto_Local_Login.pdf', 'uploads/files/preview/1762374260_preview_Fix_Datto_Local_Login.pdf', 4241332, 'application/pdf', 'preview', '2025-11-06 09:24:20'),
(40, 53, NULL, 'Expiry Dashboard Fix - EQPT 1.pdf', '1762374328_preview_Expiry_Dashboard_Fix_-_EQPT_1.pdf', 'uploads/files/preview/1762374328_preview_Expiry_Dashboard_Fix_-_EQPT_1.pdf', 4274538, 'application/pdf', 'preview', '2025-11-06 09:25:28'),
(41, 54, NULL, 'Ensuring Staff Receive a Crossware Email Signature.pdf', '1762374434_preview_Ensuring_Staff_Receive_a_Crossware_Email_Signature.pdf', 'uploads/files/preview/1762374434_preview_Ensuring_Staff_Receive_a_Crossware_Email_Signature.pdf', 4117300, 'application/pdf', 'preview', '2025-11-06 09:27:14'),
(42, 55, NULL, 'Adding J Drive to a user.pdf', '1762374460_preview_Adding_J_Drive_to_a_user.pdf', 'uploads/files/preview/1762374460_preview_Adding_J_Drive_to_a_user.pdf', 744310, 'application/pdf', 'preview', '2025-11-06 09:27:40'),
(43, 56, NULL, 'SVS & PPD Health and Safety Induction (July 2025).pdf', '1763081792_preview_SVS___PPD_Health_and_Safety_Induction__July_2025_.pdf', 'uploads/files/preview/1763081792_preview_SVS___PPD_Health_and_Safety_Induction__July_2025_.pdf', 597012, 'application/pdf', 'preview', '2025-11-14 13:56:32');

-- --------------------------------------------------------

--
-- Table structure for table `ip_lockouts`
--

CREATE TABLE `ip_lockouts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `first_attempt` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `locked_until` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migration_log`
--

CREATE TABLE `migration_log` (
  `id` int(11) NOT NULL,
  `migration_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `notes` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `migration_log`
--

INSERT INTO `migration_log` (`id`, `migration_name`, `description`, `executed_at`, `status`, `notes`) VALUES
(1, 'add_is_in_training_flag', 'Added is_in_training boolean flag to users table. Migrated existing training role users to use flag instead.', '2025-11-19 22:14:27', 'success', NULL),
(2, 'add_departments', 'Added departments system: departments table, user_departments junction, course_departments junction, and quiz_retest_tracking table', '2025-11-19 23:04:44', 'success', NULL),
(3, 'add_department_visibility_columns', 'Add department visibility columns to categories, subcategories, and posts', '2025-11-23 21:45:38', 'success', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `subcategory_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `title` varchar(500) NOT NULL,
  `content` text NOT NULL,
  `privacy` enum('public','private','shared','it_only') NOT NULL DEFAULT 'public' COMMENT 'Post privacy: public (everyone), private (author only), shared (specific users), it_only (Super Admins only)',
  `shared_with` text DEFAULT NULL COMMENT 'JSON array of user IDs',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `edited` tinyint(1) NOT NULL DEFAULT 0,
  `shared_departments` text DEFAULT NULL COMMENT 'JSON array of department IDs this post is shared with'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `subcategory_id`, `user_id`, `title`, `content`, `privacy`, `shared_with`, `created_at`, `updated_at`, `edited`, `shared_departments`) VALUES
(12, 17, 1, 'Adding a new user', '<p>When a new user starts at SVS, they need to be added to the Active Directory.</p>\r\n<p>&nbsp;</p>\r\n<p>This can be done through these steps:</p>\r\n<p>&nbsp;</p>\r\n<ol>\r\n<li>Log in to SVS-Server on Datto with SVSAdmin</li>\r\n<li>Press windows key and search &gt; Active Directory</li>\r\n<li>Expand the SVS Users folder and depending on where the user is based, right click the given location folder and click &gt; new &gt; user</li>\r\n<li>Fill in the information below. The naming convention for user logon names are {firstname}{lastname initial} all lowercase e.g. John Doe = johnd</li>\r\n<li>Then enter a temporary password the user will use to initially sign in to their account. Make sure \'User must change password at next logon\' is ticked.</li>\r\n<li><img src=\"uploads/images/img_1762137627_336d38821e1bbb42.png\"><img src=\"uploads/images/img_1762374750_d2672e076140b0f6.png\"></li>\r\n<li><img src=\"uploads/images/img_1762374762_d2832904c837449c.png\"></li>\r\n<li>Then click finish to create the user</li>\r\n<li>\r\n<p>Then find your newely created user and right click and select properties.</p>\r\n<p>Add this info:</p>\r\n<ul>\r\n<li>Description</li>\r\n<li>Telephone Number</li>\r\n<li>DDI (under telephone tab &gt; Home)</li>\r\n<li>Under organisation\r\n<ul>\r\n<li>Job title</li>\r\n<li>Department</li>\r\n<li>Company</li>\r\n<li>Manager</li>\r\n</ul>\r\n</li>\r\n<li>Under Member of\r\n<ul>\r\n<li>add user to\r\n<ul>\r\n<li>Domain Users</li>\r\n<li>Internet_Access</li>\r\n<li>SVS Vets</li>\r\n<li>SVS VPN</li>\r\n</ul>\r\n</li>\r\n</ul>\r\n</li>\r\n</ul>\r\n</li>\r\n</ol>', 'private', NULL, '2025-11-03 10:53:13', '2025-11-06 09:35:34', 1, NULL),
(34, 28, 1, 'How to use the Sales Order Dashboard', '<p>Attached</p>', 'public', NULL, '2025-11-05 23:02:14', '2025-11-05 23:02:14', 0, NULL),
(35, 31, 1, 'How to use Allocation Dashboards', '', 'public', NULL, '2025-11-05 23:03:21', '2025-11-05 23:03:21', 0, NULL),
(36, 30, 1, 'How to reprint DG forms', '', 'public', NULL, '2025-11-05 23:04:16', '2025-11-05 23:04:16', 0, NULL),
(37, 29, 1, 'How to use the inventory maintenance page and expiry dashboard.', '', 'public', NULL, '2025-11-05 23:04:53', '2025-11-05 23:04:53', 0, NULL),
(38, 37, 1, 'HAMI Warehouse Manual v1', '', 'public', NULL, '2025-11-05 23:05:28', '2025-11-05 23:05:28', 0, NULL),
(39, 34, 1, 'How to create new user', '', 'public', NULL, '2025-11-05 23:07:02', '2025-11-05 23:07:02', 0, NULL),
(40, 35, 1, 'Chagning printers', '', 'public', NULL, '2025-11-05 23:07:55', '2025-11-05 23:07:55', 0, NULL),
(41, 32, 1, 'Damaged product adjustment', '', 'public', NULL, '2025-11-05 23:08:55', '2025-11-05 23:08:55', 0, NULL),
(42, 32, 1, 'Scanning items with scanner on computer', '', 'public', NULL, '2025-11-05 23:09:23', '2025-11-05 23:09:23', 0, NULL),
(43, 26, 1, 'How to submit Toll orders manually', '', 'public', NULL, '2025-11-05 23:09:57', '2025-11-05 23:09:57', 0, NULL),
(45, 24, 1, 'How to lock-up', '', 'public', NULL, '2025-11-05 23:11:05', '2025-11-05 23:11:05', 0, NULL),
(46, 36, 1, 'Pallet booking Big Chill', '.', 'public', NULL, '2025-11-05 23:12:28', '2025-11-17 16:43:45', 1, NULL),
(47, 42, 1, 'Reset Phoenix Password', '', 'it_only', NULL, '2025-11-06 09:15:56', '2025-11-06 09:15:56', 0, NULL),
(48, 44, 1, 'How to remove user from all staff distribution', '', 'it_only', NULL, '2025-11-06 09:18:39', '2025-11-06 09:18:39', 0, NULL),
(49, 19, 1, 'HP Probook setup', '', 'public', NULL, '2025-11-06 09:19:12', '2025-11-06 09:19:12', 0, NULL),
(50, 43, 1, 'Forwarding shared mailbox emails to a user via DL', '', 'it_only', NULL, '2025-11-06 09:20:05', '2025-11-06 09:20:05', 0, NULL),
(51, 45, 1, 'Fixing printer install errors (Elevation)', '', 'it_only', NULL, '2025-11-06 09:22:39', '2025-11-06 09:22:39', 0, NULL),
(52, 46, 1, 'SVSAdmin password fix', '', 'it_only', NULL, '2025-11-06 09:24:20', '2025-11-06 09:24:20', 0, NULL),
(53, 29, 1, 'Expiry Dashboard Fix 1', '', 'it_only', NULL, '2025-11-06 09:25:28', '2025-11-06 09:25:28', 0, NULL),
(54, 47, 1, 'Ensuring staff receive a Crossware email signature', '', 'public', NULL, '2025-11-06 09:27:14', '2025-11-06 09:27:14', 0, NULL),
(55, 41, 1, 'Adding J drive to user', '', 'public', NULL, '2025-11-06 09:27:40', '2025-11-06 09:27:40', 0, NULL),
(56, 49, 1, 'Induction', '', 'public', NULL, '2025-11-14 13:56:32', '2025-11-14 13:56:32', 0, NULL),
(57, 50, 1, 'Resetting a users quiz attempts', '<p>-- Migration: Reset User Training Progress Completely<br>-- This script resets all training-related data for a specific user<br>-- Use this to reset a user for testing purposes</p>\r\n<p>-- Set the user ID you want to reset (change this value)<br>SET @user_id_to_reset = 8;</p>\r\n<p>-- Show what we\'re about to reset (for confirmation)<br>SELECT \'=== BEFORE RESET - Current State ===\' as status;<br>SELECT<br>&nbsp; &nbsp; u.id,<br>&nbsp; &nbsp; u.name,<br>&nbsp; &nbsp; u.role,<br>&nbsp; &nbsp; u.is_in_training,<br>&nbsp; &nbsp; COUNT(DISTINCT uta.id) as training_assignments,<br>&nbsp; &nbsp; COUNT(DISTINCT uqa.id) as quiz_attempts,<br>&nbsp; &nbsp; COUNT(DISTINCT tp.id) as training_progress_entries<br>FROM users u<br>LEFT JOIN user_training_assignments uta ON u.id = uta.user_id<br>LEFT JOIN user_quiz_attempts uqa ON u.id = uqa.user_id<br>LEFT JOIN training_progress tp ON u.id = tp.user_id<br>WHERE u.id = @user_id_to_reset<br>GROUP BY u.id, u.name, u.role, u.is_in_training;</p>\r\n<p>-- Reset user\'s training status (make them a training user again)<br>UPDATE users<br>SET is_in_training = 1,<br>&nbsp; &nbsp; original_training_completion = NULL<br>WHERE id = @user_id_to_reset;</p>\r\n<p>-- Delete all quiz attempts for this user<br>DELETE FROM user_quiz_answers<br>WHERE attempt_id IN (<br>&nbsp; &nbsp; SELECT id FROM user_quiz_attempts<br>&nbsp; &nbsp; WHERE user_id = @user_id_to_reset<br>);</p>\r\n<p>DELETE FROM user_quiz_attempts<br>WHERE user_id = @user_id_to_reset;</p>\r\n<p>-- Delete all training progress entries for this user<br>DELETE FROM training_progress<br>WHERE user_id = @user_id_to_reset;</p>\r\n<p>-- Delete all training assignments for this user<br>DELETE FROM user_training_assignments<br>WHERE user_id = @user_id_to_reset;</p>\r\n<p>-- Reset retest tracking for this user (delete their retest records)<br>DELETE FROM quiz_retest_tracking<br>WHERE user_id = @user_id_to_reset;</p>\r\n<p>-- Re-assign user to all their department courses (if they have department assignments)<br>-- This ensures they get the same training content again<br>INSERT IGNORE INTO user_training_assignments (user_id, course_id, assigned_by, assigned_date)<br>SELECT<br>&nbsp; &nbsp; ud.user_id,<br>&nbsp; &nbsp; cd.course_id,<br>&nbsp; &nbsp; 1 as assigned_by, -- Assuming admin user ID 1<br>&nbsp; &nbsp; CURRENT_TIMESTAMP as assigned_date<br>FROM user_departments ud<br>JOIN course_departments cd ON ud.department_id = cd.department_id<br>WHERE ud.user_id = @user_id_to_reset;</p>\r\n<p>-- Show the final state after reset<br>SELECT \'=== AFTER RESET - Final State ===\' as status;<br>SELECT<br>&nbsp; &nbsp; u.id,<br>&nbsp; &nbsp; u.name,<br>&nbsp; &nbsp; u.role,<br>&nbsp; &nbsp; u.is_in_training,<br>&nbsp; &nbsp; COUNT(DISTINCT uta.id) as training_assignments,<br>&nbsp; &nbsp; COUNT(DISTINCT uqa.id) as quiz_attempts,<br>&nbsp; &nbsp; COUNT(DISTINCT tp.id) as training_progress_entries<br>FROM users u<br>LEFT JOIN user_training_assignments uta ON u.id = uta.user_id<br>LEFT JOIN user_quiz_attempts uqa ON u.id = uqa.user_id<br>LEFT JOIN training_progress tp ON u.id = tp.user_id<br>WHERE u.id = @user_id_to_reset<br>GROUP BY u.id, u.name, u.role, u.is_in_training;</p>\r\n<p>-- Show what courses the user now has assigned<br>SELECT \'=== Re-assigned Courses ===\' as status;<br>SELECT<br>&nbsp; &nbsp; tc.id as course_id,<br>&nbsp; &nbsp; tc.name as course_name,<br>&nbsp; &nbsp; tc.department as course_department,<br>&nbsp; &nbsp; d.name as department_name<br>FROM user_training_assignments uta<br>JOIN training_courses tc ON uta.course_id = tc.id<br>LEFT JOIN course_departments cd ON tc.id = cd.course_id<br>LEFT JOIN departments d ON cd.department_id = d.id<br>WHERE uta.user_id = @user_id_to_reset<br>ORDER BY tc.name;</p>\r\n<p>SELECT \'=== RESET COMPLETE ===\' as status;<br>SELECT \'User has been completely reset and re-assigned to their department courses.\' as message;</p>', 'it_only', NULL, '2025-11-21 09:54:32', '2025-11-21 10:41:21', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answer_choices`
--

CREATE TABLE `quiz_answer_choices` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `choice_order` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `quiz_answer_choices`
--

INSERT INTO `quiz_answer_choices` (`id`, `question_id`, `choice_text`, `is_correct`, `choice_order`, `created_at`) VALUES
(7, 1, 'No', 1, 1, '2025-11-14 14:00:08'),
(6, 1, 'Yes', 0, 0, '2025-11-14 14:00:08'),
(3, 2, 'You will be dismissed?', 0, 0, '2025-11-14 14:00:02'),
(4, 2, 'You will be sent to the Medical Centre?', 0, 1, '2025-11-14 14:00:02'),
(5, 2, 'You will not be allowed to start work and will attend a formal disciplinary meeting to discuss your actions?', 1, 2, '2025-11-14 14:00:02'),
(8, 3, 'Be given a warning from your Manager?', 0, 0, '2025-11-14 14:00:58'),
(9, 3, 'Be informed of corrective action required within an agreed timeframe and receive a written warning?', 0, 1, '2025-11-14 14:00:58'),
(10, 3, 'If a serious offence, possibly be dismissed with or without notice?', 0, 2, '2025-11-14 14:00:58'),
(11, 3, 'Any of the above?', 1, 3, '2025-11-14 14:00:58'),
(12, 4, 'The Manager?', 0, 0, '2025-11-14 14:01:24'),
(13, 4, 'The Health and Safety Representatives?', 0, 1, '2025-11-14 14:01:24'),
(14, 4, 'Everyone?', 1, 2, '2025-11-14 14:01:24'),
(15, 5, 'I don’t need to attend any H&S meetings.', 0, 0, '2025-11-14 14:02:07'),
(16, 5, 'I need to attend twelve H&S meetings per year.', 0, 1, '2025-11-14 14:02:07'),
(17, 5, 'One every two months.', 0, 2, '2025-11-14 14:02:07'),
(18, 5, 'At least two H&S meetings per year.', 1, 3, '2025-11-14 14:02:07'),
(19, 6, 'Outside in the carpark.', 0, 0, '2025-11-14 14:03:01'),
(20, 6, 'On the team kitchen noticeboard.', 1, 1, '2025-11-14 14:03:01'),
(21, 6, 'By the toilets.', 0, 2, '2025-11-14 14:03:01'),
(22, 6, 'At the main reception area.', 0, 3, '2025-11-14 14:03:01'),
(23, 7, 'They don’t understand?', 0, 0, '2025-11-14 14:03:31'),
(24, 7, 'Lack of supervision?', 0, 1, '2025-11-14 14:03:31'),
(25, 7, 'Poor work methods?', 0, 2, '2025-11-14 14:03:31'),
(26, 7, 'All of the above?', 1, 3, '2025-11-14 14:03:31'),
(27, 8, 'Poor housekeeping?', 0, 0, '2025-11-14 14:04:06'),
(28, 8, 'Lack of inspections?', 0, 1, '2025-11-14 14:04:06'),
(29, 8, 'Unsuitable plant or equipment?', 0, 2, '2025-11-14 14:04:06'),
(30, 8, 'All of the above?', 1, 3, '2025-11-14 14:04:06'),
(31, 9, 'Yes.', 1, 0, '2025-11-14 14:04:28'),
(32, 9, 'No.', 0, 1, '2025-11-14 14:04:28'),
(33, 10, 'Keep working but tell your Manager later?', 0, 0, '2025-11-14 14:04:51'),
(34, 10, 'Turn it off and notify your Manager immediately?', 1, 1, '2025-11-14 14:04:51'),
(35, 11, 'Outside the office', 0, 0, '2025-11-14 14:06:01'),
(36, 11, 'Raglan Roast', 0, 1, '2025-11-14 14:06:01'),
(37, 11, 'Skip bin outside', 0, 2, '2025-11-14 14:06:01'),
(38, 11, 'Far side of carpark by chain fence', 1, 3, '2025-11-14 14:06:01'),
(39, 12, 'When emergency appears overs?', 0, 0, '2025-11-14 14:06:31'),
(40, 12, 'When directed by your Supervisor, or authorised person?', 1, 1, '2025-11-14 14:06:31'),
(41, 13, 'Yes.', 0, 0, '2025-11-14 14:06:50'),
(42, 13, 'No.', 1, 1, '2025-11-14 14:06:50'),
(43, 14, 'In emergencies or at Break time', 1, 0, '2025-11-14 14:07:39'),
(44, 14, 'When you feel like it', 0, 1, '2025-11-14 14:07:39'),
(45, 15, 'Bad posture', 0, 0, '2025-11-14 14:09:20'),
(46, 15, 'Good posture', 1, 1, '2025-11-14 14:09:20'),
(47, 16, 'Wet Floor and Faulty Equipment', 1, 0, '2025-11-14 14:12:30'),
(48, 16, 'Tape and boxes', 0, 1, '2025-11-14 14:12:30'),
(49, 16, 'Dust and dirt', 0, 2, '2025-11-14 14:12:30');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_image` varchar(255) DEFAULT NULL,
  `question_type` enum('multiple_choice') DEFAULT 'multiple_choice',
  `question_order` int(11) DEFAULT 0,
  `points` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quiz_id`, `question_text`, `question_image`, `question_type`, `question_order`, `points`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Are you permitted to smoke in any SVS offices or buildings?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 13:59:18', '2025-11-14 14:00:08'),
(2, 1, 'If you arrive at work under the influence of drugs or alcohol, what will happen?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:00:02', '2025-11-14 14:00:02'),
(3, 1, 'What may happen if you don’t follow safety rules?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:00:58', '2025-11-14 14:00:58'),
(4, 1, 'Workplace safety is the responsibility of:', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:01:24', '2025-11-14 14:01:24'),
(5, 1, 'What is the minimum number of H&S meetings that I need to attend each year?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:02:07', '2025-11-14 14:02:07'),
(6, 1, 'Where do I find the list of current risks/hazards in my workplace?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:03:01', '2025-11-14 14:03:01'),
(7, 1, 'People commit unsafe acts because:', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:03:31', '2025-11-14 14:03:31'),
(8, 1, 'Unsafe conditions exist because of:', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:04:06', '2025-11-14 14:04:06'),
(9, 1, 'If you are injured at work do you need to report it to your Manager?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:04:28', '2025-11-14 14:04:28'),
(10, 1, 'If machinery/plant is unsafe to operate, do you:', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:04:51', '2025-11-14 14:04:51'),
(11, 1, 'Where is your emergency evacuation assembly area?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:06:01', '2025-11-14 14:06:01'),
(12, 1, 'When can you leave the Assembly Area?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:06:31', '2025-11-14 14:06:31'),
(13, 1, 'Are you permitted to wear headphones in the Warehouse?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:06:50', '2025-11-14 14:06:50'),
(14, 1, 'When can I use my phone at work?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:07:39', '2025-11-14 14:07:39'),
(15, 1, 'If applicable to your role as a computer user, what is one ergonomic tip.', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:09:20', '2025-11-14 14:09:20'),
(16, 1, 'Can you name two risks on the risk register?', NULL, 'multiple_choice', 0, 1, 1, '2025-11-14 14:12:30', '2025-11-14 14:12:30');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_retest_log`
--

CREATE TABLE `quiz_retest_log` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `previous_attempt_id` int(11) NOT NULL,
  `retest_reason` enum('period_expired','admin_forced','content_updated') NOT NULL,
  `retest_date` datetime NOT NULL,
  `old_status` enum('passed','failed','in_progress') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_retest_tracking`
--

CREATE TABLE `quiz_retest_tracking` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `last_passed_date` date DEFAULT NULL,
  `retest_eligible_date` date DEFAULT NULL,
  `retest_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Tracks retest eligibility for quizzes';

-- --------------------------------------------------------

--
-- Table structure for table `quiz_statistics`
--

CREATE TABLE `quiz_statistics` (
  `quiz_id` int(11) NOT NULL,
  `quiz_title` varchar(255) NOT NULL,
  `total_attempts` int(11) DEFAULT 0,
  `total_users` int(11) DEFAULT 0,
  `average_score` decimal(5,2) DEFAULT 0.00,
  `highest_score` int(11) DEFAULT 0,
  `lowest_score` int(11) DEFAULT 0,
  `pass_rate` decimal(5,2) DEFAULT 0.00,
  `total_questions` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_statistics`
--

INSERT INTO `quiz_statistics` (`quiz_id`, `quiz_title`, `total_attempts`, `total_users`, `average_score`, `highest_score`, `lowest_score`, `pass_rate`, `total_questions`, `created_at`) VALUES
(1, 'TEST', 3, 2, '98.00', 100, 94, '66.67', 16, '2025-11-06 11:59:09'),
(7, '', 1, 1, '100.00', 100, 100, '100.00', 1, '2025-11-12 13:17:00'),
(8, '', 1, 1, '100.00', 100, 100, '100.00', 1, '2025-11-12 14:46:09'),
(9, '', 6, 1, '44.50', 67, 0, '0.00', 3, '2025-11-12 19:31:32');

-- --------------------------------------------------------

--
-- Table structure for table `replies`
--

CREATE TABLE `replies` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `edited` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `replies`
--

INSERT INTO `replies` (`id`, `post_id`, `user_id`, `content`, `created_at`, `updated_at`, `edited`) VALUES
(10, 53, 1, '<p>For example, a issue came through where the numbers were like this:</p>\r\n<p><img src=\"uploads/images/img_1763515826_c7d2313f51fe2d23.png\" width=\"1006\" height=\"324\"></p>\r\n<p>To fix this you first search for the offending piece of data using e.g.:</p>\r\n<p><span data-teams=\"true\"><a id=\"menur3kb\" class=\"fui-Link ___1q1shib f2hkw1w f3rmtva f1ewtqcl fyind8e f1k6fduh f1w7gpdv fk6fouc fjoy568 figsok6 f1s184ao f1mk8lai fnbmjn9 f1o700av f13mvf36 f1cmlufx f9n3di6 f1ids18y f1tx3yz7 f1deo86v f1eh06m1 f1iescvh fhgqx19 f1olyrje f1p93eir f1nev41a f1h8hb77 f1lqvz6u f10aw75t fsle3fq f17ae5zn\" title=\"http://phoenixtest.svs.co.nz:9180/dbadmin/url.php?url=https://dev.mysql.com/doc/refman/5.5/en/select.html\" href=\"http://phoenixtest.svs.co.nz:9180/dbadmin/url.php?url=https://dev.mysql.com/doc/refman/5.5/en/select.html\" target=\"_blank\" rel=\"noreferrer noopener\" aria-label=\"Link SELECT\">SELECT</a> * FROM `IC_ExpiryBatch` WHERE `warehouse_code` = \'HAMI\' <a id=\"menur3kd\" class=\"fui-Link ___1q1shib f2hkw1w f3rmtva f1ewtqcl fyind8e f1k6fduh f1w7gpdv fk6fouc fjoy568 figsok6 f1s184ao f1mk8lai fnbmjn9 f1o700av f13mvf36 f1cmlufx f9n3di6 f1ids18y f1tx3yz7 f1deo86v f1eh06m1 f1iescvh fhgqx19 f1olyrje f1p93eir f1nev41a f1h8hb77 f1lqvz6u f10aw75t fsle3fq f17ae5zn\" title=\"http://phoenixtest.svs.co.nz:9180/dbadmin/url.php?url=https://dev.mysql.com/doc/refman/5.5/en/logical-operators.html#operator_and\" href=\"http://phoenixtest.svs.co.nz:9180/dbadmin/url.php?url=https://dev.mysql.com/doc/refman/5.5/en/logical-operators.html#operator_and\" target=\"_blank\" rel=\"noreferrer noopener\" aria-label=\"Link and\">and</a> item_code = \'15126\'&nbsp;<a id=\"menur3kf\" class=\"fui-Link ___1q1shib f2hkw1w f3rmtva f1ewtqcl fyind8e f1k6fduh f1w7gpdv fk6fouc fjoy568 figsok6 f1s184ao f1mk8lai fnbmjn9 f1o700av f13mvf36 f1cmlufx f9n3di6 f1ids18y f1tx3yz7 f1deo86v f1eh06m1 f1iescvh fhgqx19 f1olyrje f1p93eir f1nev41a f1h8hb77 f1lqvz6u f10aw75t fsle3fq f17ae5zn\" title=\"http://phoenixtest.svs.co.nz:9180/dbadmin/url.php?url=https://dev.mysql.com/doc/refman/5.5/en/logical-operators.html#operator_and\" href=\"http://phoenixtest.svs.co.nz:9180/dbadmin/url.php?url=https://dev.mysql.com/doc/refman/5.5/en/logical-operators.html#operator_and\" target=\"_blank\" rel=\"noreferrer noopener\" aria-label=\"Link AND\">AND</a> `expiry_date` = \'0000-00-00\' <a id=\"menur3kh\" class=\"fui-Link ___1q1shib f2hkw1w f3rmtva f1ewtqcl fyind8e f1k6fduh f1w7gpdv fk6fouc fjoy568 figsok6 f1s184ao f1mk8lai fnbmjn9 f1o700av f13mvf36 f1cmlufx f9n3di6 f1ids18y f1tx3yz7 f1deo86v f1eh06m1 f1iescvh fhgqx19 f1olyrje f1p93eir f1nev41a f1h8hb77 f1lqvz6u f10aw75t fsle3fq f17ae5zn\" title=\"http://phoenixtest.svs.co.nz:9180/dbadmin/url.php?url=https://dev.mysql.com/doc/refman/5.5/en/logical-operators.html#operator_and\" href=\"http://phoenixtest.svs.co.nz:9180/dbadmin/url.php?url=https://dev.mysql.com/doc/refman/5.5/en/logical-operators.html#operator_and\" target=\"_blank\" rel=\"noreferrer noopener\" aria-label=\"Link and\">and</a> qty = 21</span></p>\r\n<p><span data-teams=\"true\">Which brought back 1 record.</span></p>\r\n<p><span data-teams=\"true\">Then you update that record to set the date to NULL istead of 0000-00-00 by using e.g.:</span></p>\r\n<p><span data-teams=\"true\">update `IC_ExpiryBatch` set expiry_date = null&nbsp; WHERE `warehouse_code` = \'HAMI\' and item_code = \'15126\' AND `expiry_date` = \'0000-00-00\' and qty = 21</span></p>\r\n<p>&nbsp;</p>\r\n<p><span data-teams=\"true\">Note: Use a transaction rollback to make sure only 1 record is affected :)&nbsp;</span></p>', '2025-11-19 14:33:06', '2025-11-19 14:33:06', 0);

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `visibility` enum('public','hidden','restricted','it_only') NOT NULL DEFAULT 'public' COMMENT 'Subcategory visibility: public (everyone), hidden (only admin), restricted (specific users), it_only (Super Admins only)',
  `allowed_users` text DEFAULT NULL COMMENT 'JSON array of user IDs who can see restricted subcategories',
  `visibility_note` varchar(255) DEFAULT NULL COMMENT 'Admin note about visibility restrictions',
  `allowed_departments` text DEFAULT NULL COMMENT 'JSON array of department IDs allowed to view this subcategory'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`id`, `category_id`, `user_id`, `name`, `created_at`, `updated_at`, `visibility`, `allowed_users`, `visibility_note`, `allowed_departments`) VALUES
(17, 8, 1, 'Active Directory', '2025-11-03 10:43:03', '2025-11-03 10:43:03', 'public', NULL, NULL, NULL),
(18, 8, 1, 'Microsoft 365 Admin Center', '2025-11-03 10:43:17', '2025-11-03 10:43:17', 'public', NULL, NULL, NULL),
(19, 8, 1, 'Laptop', '2025-11-03 10:43:35', '2025-11-03 10:43:35', 'public', NULL, NULL, NULL),
(20, 8, 1, 'Desktop', '2025-11-03 10:43:40', '2025-11-03 10:43:40', 'public', NULL, NULL, NULL),
(24, 10, 1, 'Lock-up procedure', '2025-11-03 15:48:09', '2025-11-03 15:48:09', 'public', NULL, NULL, NULL),
(25, 11, 1, 'Tracking', '2025-11-03 15:52:09', '2025-11-03 15:52:09', 'public', NULL, NULL, NULL),
(26, 11, 1, 'Ordering', '2025-11-03 15:53:50', '2025-11-03 15:53:50', 'public', NULL, NULL, NULL),
(28, 12, 1, 'Sales Order Dashboard', '2025-11-03 16:31:34', '2025-11-03 16:31:34', 'public', NULL, NULL, NULL),
(29, 12, 1, 'Inventory Maintenance & Expiry Dashboard', '2025-11-03 16:31:43', '2025-11-03 16:31:43', 'public', NULL, NULL, NULL),
(30, 12, 1, 'Dangerous Goods', '2025-11-03 16:31:52', '2025-11-03 16:31:52', 'public', NULL, NULL, NULL),
(31, 12, 1, 'Allocation Dashboards', '2025-11-03 16:32:06', '2025-11-03 16:32:06', 'public', NULL, NULL, NULL),
(32, 12, 1, 'Misc', '2025-11-03 16:32:13', '2025-11-03 16:32:13', 'public', NULL, NULL, NULL),
(34, 12, 1, 'New User', '2025-11-03 16:49:18', '2025-11-03 16:49:18', 'public', NULL, NULL, NULL),
(35, 12, 1, 'Printers', '2025-11-03 16:50:01', '2025-11-03 16:50:01', 'public', NULL, NULL, NULL),
(36, 13, 1, 'How to book a Big Chill order', '2025-11-03 16:52:33', '2025-11-05 15:50:28', 'public', NULL, '', NULL),
(37, 12, 1, 'Manual', '2025-11-03 16:54:02', '2025-11-03 16:54:02', 'public', NULL, NULL, NULL),
(38, 12, 1, 'Admin', '2025-11-04 09:00:37', '2025-11-06 09:13:08', 'it_only', NULL, '', NULL),
(39, 14, 1, 'Devices', '2025-11-05 09:09:03', '2025-11-05 09:09:03', 'it_only', NULL, '', NULL),
(41, 8, 1, 'Network Drives', '2025-11-06 09:14:05', '2025-11-06 09:14:16', 'public', NULL, '', NULL),
(42, 12, 1, 'Passwords', '2025-11-06 09:15:30', '2025-11-06 09:15:30', 'it_only', NULL, '', NULL),
(43, 17, 1, 'Emails', '2025-11-06 09:17:07', '2025-11-06 09:17:07', 'it_only', NULL, '', NULL),
(44, 18, 1, 'Emails', '2025-11-06 09:18:10', '2025-11-06 09:18:20', 'it_only', NULL, '', NULL),
(45, 19, 1, 'IT', '2025-11-06 09:22:13', '2025-11-06 09:22:13', 'it_only', NULL, '', NULL),
(46, 17, 1, 'Login', '2025-11-06 09:23:55', '2025-11-06 09:23:55', 'it_only', NULL, '', NULL),
(47, 8, 1, 'Emails', '2025-11-06 09:26:30', '2025-11-06 09:26:40', 'public', NULL, '', NULL),
(48, 12, 1, 'Handheld', '2025-11-06 16:30:02', '2025-11-06 16:30:02', 'public', NULL, '', NULL),
(49, 20, 1, 'Health and Safety', '2025-11-14 13:55:16', '2025-11-14 13:55:16', 'public', NULL, '', NULL),
(50, 21, 1, 'SQL', '2025-11-21 09:52:43', '2025-11-21 09:52:43', 'it_only', NULL, '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `training_courses`
--

CREATE TABLE `training_courses` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `estimated_hours` decimal(4,1) DEFAULT 0.0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `can_assign_to_departments` tinyint(1) DEFAULT 0 COMMENT 'Whether this course can be assigned to departments'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `training_courses`
--

INSERT INTO `training_courses` (`id`, `name`, `description`, `department`, `estimated_hours`, `is_active`, `created_by`, `created_at`, `updated_at`, `can_assign_to_departments`) VALUES
(1, 'Induction', 'Induction for new staff HAMI - DC', 'General Warehouse', '1.5', 1, 1, '2025-11-14 13:51:57', '2025-11-20 13:36:23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `training_course_content`
--

CREATE TABLE `training_course_content` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `content_type` enum('category','subcategory','post') NOT NULL,
  `content_id` int(11) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `training_order` int(11) DEFAULT 0,
  `time_required_minutes` int(11) DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `training_course_content`
--

INSERT INTO `training_course_content` (`id`, `course_id`, `content_type`, `content_id`, `is_required`, `training_order`, `time_required_minutes`, `admin_notes`, `created_at`) VALUES
(1, 1, 'subcategory', 49, 1, 0, 0, NULL, '2025-11-14 13:57:58'),
(2, 1, 'post', 56, 1, 0, 0, NULL, '2025-11-14 13:57:58'),
(3, 1, 'category', 20, 1, 0, 0, NULL, '2025-11-14 13:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `training_history`
--

CREATE TABLE `training_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `content_type` enum('category','subcategory','post') NOT NULL,
  `content_id` int(11) NOT NULL,
  `completion_date` datetime NOT NULL,
  `time_spent_minutes` int(11) NOT NULL,
  `course_completed_date` datetime DEFAULT NULL,
  `original_assignment_date` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_progress`
--

CREATE TABLE `training_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `content_type` enum('category','subcategory','post') NOT NULL,
  `content_id` int(11) NOT NULL,
  `status` enum('required','in_progress','completed','skipped') DEFAULT 'required',
  `quiz_completed` tinyint(1) DEFAULT 0 COMMENT 'Completed via quiz',
  `quiz_score` int(11) DEFAULT NULL COMMENT 'Last quiz score percentage',
  `quiz_completed_at` datetime DEFAULT NULL COMMENT 'When quiz was completed',
  `last_quiz_attempt_id` int(11) DEFAULT NULL COMMENT 'Reference to last attempt',
  `completion_date` datetime DEFAULT NULL,
  `time_spent_minutes` int(11) DEFAULT 0,
  `time_started` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `training_progress`
--

INSERT INTO `training_progress` (`id`, `user_id`, `course_id`, `content_type`, `content_id`, `status`, `quiz_completed`, `quiz_score`, `quiz_completed_at`, `last_quiz_attempt_id`, `completion_date`, `time_spent_minutes`, `time_started`, `created_at`, `updated_at`) VALUES
(1, 3, 0, 'post', 56, 'completed', 1, 100, '2025-11-14 14:15:44', 2, '2025-11-14 14:15:44', 0, NULL, '2025-11-14 14:14:07', '2025-11-14 14:15:44'),
(15, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:18:09', '2025-11-21 09:50:35'),
(14, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:18:05', '2025-11-21 09:50:35'),
(13, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:17:50', '2025-11-21 09:50:35'),
(12, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:15:11', '2025-11-21 09:50:35'),
(11, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:15:03', '2025-11-21 09:50:35'),
(16, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:18:11', '2025-11-21 09:50:35'),
(17, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:18:18', '2025-11-21 09:50:35'),
(18, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:21:16', '2025-11-21 09:50:35'),
(19, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:21:34', '2025-11-21 09:50:35'),
(20, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:27:03', '2025-11-21 09:50:35'),
(21, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:27:06', '2025-11-21 09:50:35'),
(22, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-20 17:27:12', '2025-11-21 09:50:35'),
(23, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-21 09:40:41', '2025-11-21 09:50:35'),
(24, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-21 09:40:52', '2025-11-21 09:50:35'),
(25, 8, 0, 'post', 56, 'completed', 1, 100, '2025-11-21 09:50:35', 5, '2025-11-21 09:50:35', 0, NULL, '2025-11-21 09:48:36', '2025-11-21 09:50:35');

-- --------------------------------------------------------

--
-- Table structure for table `training_quizzes`
--

CREATE TABLE `training_quizzes` (
  `id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `content_type` enum('category','subcategory','post') NOT NULL,
  `quiz_title` varchar(255) NOT NULL,
  `quiz_description` text DEFAULT NULL,
  `passing_score` int(11) DEFAULT 100 COMMENT 'Required score to pass (percentage)',
  `time_limit_minutes` int(11) DEFAULT NULL COMMENT 'Time limit for quiz, null for no limit',
  `is_active` tinyint(1) DEFAULT 1,
  `is_assigned` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `retest_period_months` int(11) DEFAULT NULL COMMENT 'Retest period in months, NULL means no retest required'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `training_quizzes`
--

INSERT INTO `training_quizzes` (`id`, `content_id`, `content_type`, `quiz_title`, `quiz_description`, `passing_score`, `time_limit_minutes`, `is_active`, `is_assigned`, `created_by`, `created_at`, `updated_at`, `retest_period_months`) VALUES
(1, 56, 'post', 'Health and Safety and MPI Induction Questionaire', '', 100, NULL, 1, 1, 1, '2025-11-14 13:58:39', '2025-11-20 10:42:41', 1);

-- --------------------------------------------------------

--
-- Table structure for table `training_sessions`
--

CREATE TABLE `training_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content_type` enum('category','subcategory','post') NOT NULL,
  `content_id` int(11) NOT NULL,
  `session_start` datetime NOT NULL DEFAULT current_timestamp(),
  `session_end` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `is_completed` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#4A90E2',
  `role` enum('super admin','admin','user','training') NOT NULL DEFAULT 'user',
  `previous_role` enum('super admin','admin','user','training') DEFAULT NULL,
  `training_revert_reason` varchar(255) DEFAULT NULL,
  `original_training_completion` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `is_in_training` tinyint(1) DEFAULT 0 COMMENT 'Boolean flag: whether user is currently in training (state, not role)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `pin`, `color`, `role`, `previous_role`, `training_revert_reason`, `original_training_completion`, `is_active`, `created_at`, `updated_at`, `created_by`, `last_login`, `failed_attempts`, `locked_until`, `is_in_training`) VALUES
(1, 'Kyle Walker', '$2y$10$uOS5Fi2OAUTyr52pPpmsOe0iHYI10uYOKEkU7bdiiHdWgeikrc3Bu', '#ff0000', 'super admin', NULL, NULL, NULL, 1, '2025-11-03 20:14:20', '2025-11-24 00:42:28', NULL, '2025-11-24 00:34:29', 0, NULL, 0),
(2, 'Cody Kirsten', '$2y$10$V3GHbftNw6hT5Gv08reQH.Ap3sFHtPLCxE49U1elcVOmRls6zs.Ci', '#00eeff', 'admin', NULL, NULL, NULL, 1, '2025-11-03 20:14:20', '2025-11-23 21:48:41', NULL, '2025-11-23 21:08:32', 0, NULL, 0),
(3, 'Deegan Begovich', '$2y$10$adVdwDaoguIbSsrV4wMSHe8mhImbte7nbsEiBArl/Oj1ZaYQ2xoTC', '#e74c3c', 'user', 'training', NULL, '2025-11-14 14:15:44', 1, '2025-11-03 20:14:20', '2025-11-20 01:07:53', NULL, '2025-11-20 01:07:53', 0, NULL, 0),
(8, 'Test 1', '$2y$10$6I2F34wS4aOINxWr20Hm4eHx5Oz81kHy3/CVgLdxctpqHbZOsq6z2', '#44ff00', 'user', NULL, NULL, '2025-11-21 09:50:35', 1, '2025-11-13 20:53:52', '2025-11-20 20:50:35', 1, '2025-11-20 20:40:36', 0, NULL, 0),
(9, 'Test 2', '$2y$10$x3z03AOVGq6OZCUcN26MLeE2Q72db58wtbYikqkW9VCaLr0MA3W3q', '#fff700', 'user', NULL, NULL, NULL, 1, '2025-11-13 20:54:17', '2025-11-20 22:39:41', 1, '2025-11-20 22:39:40', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users_backup_before_role_update`
--

CREATE TABLE `users_backup_before_role_update` (
  `id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#4A90E2',
  `role` enum('admin','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users_backup_before_role_update`
--

INSERT INTO `users_backup_before_role_update` (`id`, `name`, `pin`, `color`, `role`, `is_active`, `created_at`, `updated_at`, `created_by`, `last_login`, `failed_attempts`, `locked_until`) VALUES
(1, 'Kyle Walker', '$2y$10$uOS5Fi2OAUTyr52pPpmsOe0iHYI10uYOKEkU7bdiiHdWgeikrc3Bu', '#ff0000', 'admin', 1, '2025-11-03 20:14:20', '2025-11-05 04:27:00', NULL, '2025-11-05 04:27:00', 0, NULL),
(2, 'Cody Kirsten', '$2y$10$V3GHbftNw6hT5Gv08reQH.Ap3sFHtPLCxE49U1elcVOmRls6zs.Ci', '#00eeff', 'admin', 1, '2025-11-03 20:14:20', '2025-11-05 04:36:10', NULL, '2025-11-05 04:36:10', 0, NULL),
(3, 'Deegan Begovich', '$2y$10$EN0VUubG5cLjJHHMCJ3Eouf/AFtRIcJmAjIcZMeWahImgu55Y3wxe', '#e74c3c', 'admin', 1, '2025-11-03 20:14:20', '2025-11-05 03:33:13', NULL, '2025-11-05 03:33:13', 0, NULL),
(4, 'Test Account', '$2y$10$cQSQPCBCk4qdZzmtl9iSlev45l4OKo2qgks3I/0UqfYPKdJoSMluS', '#ffffff', 'user', 1, '2025-11-05 02:44:35', '2025-11-05 03:32:27', 1, '2025-11-05 03:32:27', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_departments`
--

CREATE TABLE `user_departments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Maps users to departments (many-to-many)';

--
-- Dumping data for table `user_departments`
--

INSERT INTO `user_departments` (`id`, `user_id`, `department_id`, `assigned_date`, `assigned_by`) VALUES
(1, 3, 1, '2025-11-19 23:06:17', 1),
(6, 8, 1, '2025-11-20 01:36:55', 1),
(14, 2, 1, '2025-11-21 03:45:50', 2);

-- --------------------------------------------------------

--
-- Table structure for table `user_pinned_categories`
--

CREATE TABLE `user_pinned_categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_quiz_answers`
--

CREATE TABLE `user_quiz_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_choice_id` int(11) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `answered_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_quiz_answers`
--

INSERT INTO `user_quiz_answers` (`id`, `attempt_id`, `question_id`, `selected_choice_id`, `is_correct`, `points_earned`, `answered_at`) VALUES
(1, 1, 1, 7, 1, 1, '2025-11-14 14:15:04'),
(2, 1, 2, 5, 1, 1, '2025-11-14 14:15:04'),
(3, 1, 3, 11, 1, 1, '2025-11-14 14:15:04'),
(4, 1, 4, 14, 1, 1, '2025-11-14 14:15:04'),
(5, 1, 5, 18, 1, 1, '2025-11-14 14:15:04'),
(6, 1, 6, 20, 1, 1, '2025-11-14 14:15:04'),
(7, 1, 7, 26, 1, 1, '2025-11-14 14:15:04'),
(8, 1, 8, 30, 1, 1, '2025-11-14 14:15:04'),
(9, 1, 9, 31, 1, 1, '2025-11-14 14:15:04'),
(10, 1, 10, 34, 1, 1, '2025-11-14 14:15:04'),
(11, 1, 11, 38, 1, 1, '2025-11-14 14:15:04'),
(12, 1, 12, 40, 1, 1, '2025-11-14 14:15:04'),
(13, 1, 13, 42, 1, 1, '2025-11-14 14:15:04'),
(14, 1, 14, 44, 0, 0, '2025-11-14 14:15:04'),
(15, 1, 15, 46, 1, 1, '2025-11-14 14:15:04'),
(16, 1, 16, 47, 1, 1, '2025-11-14 14:15:04'),
(17, 2, 1, 7, 1, 1, '2025-11-14 14:15:44'),
(18, 2, 2, 5, 1, 1, '2025-11-14 14:15:44'),
(19, 2, 3, 11, 1, 1, '2025-11-14 14:15:44'),
(20, 2, 4, 14, 1, 1, '2025-11-14 14:15:44'),
(21, 2, 5, 18, 1, 1, '2025-11-14 14:15:44'),
(22, 2, 6, 20, 1, 1, '2025-11-14 14:15:44'),
(23, 2, 7, 26, 1, 1, '2025-11-14 14:15:44'),
(24, 2, 8, 30, 1, 1, '2025-11-14 14:15:44'),
(25, 2, 9, 31, 1, 1, '2025-11-14 14:15:44'),
(26, 2, 10, 34, 1, 1, '2025-11-14 14:15:44'),
(27, 2, 11, 38, 1, 1, '2025-11-14 14:15:44'),
(28, 2, 12, 40, 1, 1, '2025-11-14 14:15:44'),
(29, 2, 13, 42, 1, 1, '2025-11-14 14:15:44'),
(30, 2, 14, 43, 1, 1, '2025-11-14 14:15:44'),
(31, 2, 15, 46, 1, 1, '2025-11-14 14:15:44'),
(32, 2, 16, 47, 1, 1, '2025-11-14 14:15:44'),
(65, 5, 1, 7, 1, 1, '2025-11-21 09:50:35'),
(66, 5, 2, 5, 1, 1, '2025-11-21 09:50:35'),
(67, 5, 3, 11, 1, 1, '2025-11-21 09:50:35'),
(68, 5, 4, 14, 1, 1, '2025-11-21 09:50:35'),
(69, 5, 5, 18, 1, 1, '2025-11-21 09:50:35'),
(70, 5, 6, 20, 1, 1, '2025-11-21 09:50:35'),
(71, 5, 7, 26, 1, 1, '2025-11-21 09:50:35'),
(72, 5, 8, 30, 1, 1, '2025-11-21 09:50:35'),
(73, 5, 9, 31, 1, 1, '2025-11-21 09:50:35'),
(74, 5, 10, 34, 1, 1, '2025-11-21 09:50:35'),
(75, 5, 11, 38, 1, 1, '2025-11-21 09:50:35'),
(76, 5, 12, 40, 1, 1, '2025-11-21 09:50:35'),
(77, 5, 13, 42, 1, 1, '2025-11-21 09:50:35'),
(78, 5, 14, 43, 1, 1, '2025-11-21 09:50:35'),
(79, 5, 15, 46, 1, 1, '2025-11-21 09:50:35'),
(80, 5, 16, 47, 1, 1, '2025-11-21 09:50:35');

-- --------------------------------------------------------

--
-- Table structure for table `user_quiz_attempts`
--

CREATE TABLE `user_quiz_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `score` int(11) DEFAULT NULL COMMENT 'Percentage score (0-100)',
  `total_points` int(11) DEFAULT 0,
  `earned_points` int(11) DEFAULT 0,
  `status` enum('in_progress','completed','failed','passed') DEFAULT 'in_progress',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `time_taken_minutes` int(11) DEFAULT NULL,
  `last_completed_date` date DEFAULT NULL COMMENT 'Date when user last completed this quiz for retest calculation',
  `retest_required` tinyint(1) DEFAULT 0 COMMENT 'Whether user needs to retake this quiz'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_quiz_attempts`
--

INSERT INTO `user_quiz_attempts` (`id`, `user_id`, `quiz_id`, `attempt_number`, `score`, `total_points`, `earned_points`, `status`, `started_at`, `completed_at`, `time_taken_minutes`, `last_completed_date`, `retest_required`) VALUES
(1, 3, 1, 1, 94, 16, 15, 'failed', '2025-10-14 14:14:11', '2025-10-14 14:15:04', 0, NULL, 0),
(2, 3, 1, 2, 100, 16, 16, 'passed', '2025-10-14 14:15:07', '2025-10-14 14:15:44', 0, NULL, 0),
(5, 8, 1, 1, 100, 16, 16, 'passed', '2025-11-21 09:48:38', '2025-11-21 09:50:35', 1, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_training_assignments`
--

CREATE TABLE `user_training_assignments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_date` datetime NOT NULL DEFAULT current_timestamp(),
  `assignment_source` enum('direct','department') NOT NULL DEFAULT 'direct',
  `department_id` int(11) DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','expired') DEFAULT 'not_started',
  `completion_date` datetime DEFAULT NULL,
  `retest_exempt` tinyint(1) DEFAULT 0 COMMENT 'Whether user is exempt from retest requirements'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user_training_assignments`
--

INSERT INTO `user_training_assignments` (`id`, `user_id`, `course_id`, `assigned_by`, `assigned_date`, `assignment_source`, `department_id`, `due_date`, `status`, `completion_date`, `retest_exempt`) VALUES
(1, 3, 1, 1, '2025-11-14 14:13:56', 'direct', NULL, NULL, 'completed', '2025-11-14 14:15:44', 0),
(4, 8, 1, 1, '2025-11-19 20:14:52', 'direct', NULL, NULL, 'completed', '2025-11-21 09:50:35', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bug_reports`
--
ALTER TABLE `bug_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_visibility` (`visibility`),
  ADD KEY `idx_visibility_it_only` (`visibility`) USING BTREE;

--
-- Indexes for table `course_departments`
--
ALTER TABLE `course_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_course_dept` (`course_id`,`department_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_department` (`department_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `edit_requests`
--
ALTER TABLE `edit_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item` (`item_type`,`item_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_edit_requests_status_date` (`status`,`created_at`),
  ADD KEY `idx_edit_requests_type_status` (`item_type`,`status`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_post_id` (`post_id`),
  ADD KEY `idx_reply_id` (`reply_id`),
  ADD KEY `idx_file_type_category` (`file_type_category`);

--
-- Indexes for table `ip_lockouts`
--
ALTER TABLE `ip_lockouts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`),
  ADD KEY `idx_ip_lockouts` (`ip_address`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip` (`ip_address`),
  ADD KEY `idx_ip_attempts` (`ip_address`),
  ADD KEY `idx_locked_ips` (`locked_until`);

--
-- Indexes for table `migration_log`
--
ALTER TABLE `migration_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `migration_name` (`migration_name`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subcategory_id` (`subcategory_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_privacy` (`privacy`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_privacy_it_only` (`privacy`) USING BTREE;
ALTER TABLE `posts` ADD FULLTEXT KEY `ft_title_content` (`title`,`content`);

--
-- Indexes for table `quiz_answer_choices`
--
ALTER TABLE `quiz_answer_choices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_order` (`choice_order`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_id` (`quiz_id`),
  ADD KEY `idx_order` (`question_order`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `quiz_retest_log`
--
ALTER TABLE `quiz_retest_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `previous_attempt_id` (`previous_attempt_id`);

--
-- Indexes for table `quiz_retest_tracking`
--
ALTER TABLE `quiz_retest_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_quiz` (`user_id`,`quiz_id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `idx_retest_eligible` (`retest_eligible_date`),
  ADD KEY `idx_retest_enabled` (`retest_enabled`);

--
-- Indexes for table `quiz_statistics`
--
ALTER TABLE `quiz_statistics`
  ADD PRIMARY KEY (`quiz_id`),
  ADD KEY `idx_attempts` (`total_attempts`),
  ADD KEY `idx_average_score` (`average_score`);

--
-- Indexes for table `replies`
--
ALTER TABLE `replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_post_id` (`post_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_visibility` (`visibility`),
  ADD KEY `idx_visibility_it_only` (`visibility`) USING BTREE;

--
-- Indexes for table `training_courses`
--
ALTER TABLE `training_courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_training_courses_active_dept` (`is_active`,`department`);

--
-- Indexes for table `training_course_content`
--
ALTER TABLE `training_course_content`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_course_content` (`course_id`,`content_type`,`content_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_content_type` (`content_type`),
  ADD KEY `idx_training_order` (`training_order`);

--
-- Indexes for table `training_history`
--
ALTER TABLE `training_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_user` (`user_id`),
  ADD KEY `idx_history_course` (`course_id`),
  ADD KEY `idx_history_completion` (`completion_date`),
  ADD KEY `idx_history_content` (`content_type`,`content_id`),
  ADD KEY `idx_history_user_completion` (`user_id`,`completion_date`);

--
-- Indexes for table `training_progress`
--
ALTER TABLE `training_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_progress` (`user_id`),
  ADD KEY `idx_course_progress` (`course_id`),
  ADD KEY `idx_content_progress` (`content_type`,`content_id`),
  ADD KEY `idx_status_progress` (`status`),
  ADD KEY `idx_user_content` (`user_id`,`content_type`,`content_id`),
  ADD KEY `idx_progress_user_course` (`user_id`,`course_id`),
  ADD KEY `idx_quiz_completed` (`quiz_completed`),
  ADD KEY `idx_last_quiz_attempt` (`last_quiz_attempt_id`);

--
-- Indexes for table `training_quizzes`
--
ALTER TABLE `training_quizzes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_content_quiz` (`content_id`,`content_type`),
  ADD KEY `idx_content` (`content_id`,`content_type`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `fk_training_quizzes_content` (`content_type`,`content_id`),
  ADD KEY `idx_training_quizzes_is_assigned` (`is_assigned`);

--
-- Indexes for table `training_sessions`
--
ALTER TABLE `training_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user` (`user_id`),
  ADD KEY `idx_sessions_content` (`content_type`,`content_id`),
  ADD KEY `idx_sessions_start` (`session_start`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_users_name` (`name`),
  ADD KEY `idx_users_last_login` (`last_login`),
  ADD KEY `idx_users_role_training` (`role`),
  ADD KEY `idx_users_previous_role` (`previous_role`),
  ADD KEY `idx_is_in_training` (`is_in_training`);

--
-- Indexes for table `user_departments`
--
ALTER TABLE `user_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_dept` (`user_id`,`department_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_department` (`department_id`);

--
-- Indexes for table `user_pinned_categories`
--
ALTER TABLE `user_pinned_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_category` (`user_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `user_quiz_answers`
--
ALTER TABLE `user_quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attempt_question_answer` (`attempt_id`,`question_id`),
  ADD KEY `idx_attempt_id` (`attempt_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_selected_choice` (`selected_choice_id`);

--
-- Indexes for table `user_quiz_attempts`
--
ALTER TABLE `user_quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_quiz_attempt` (`user_id`,`quiz_id`,`attempt_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_quiz_id` (`quiz_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_started_at` (`started_at`);

--
-- Indexes for table `user_training_assignments`
--
ALTER TABLE `user_training_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_course` (`user_id`,`course_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_assigned_by` (`assigned_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assignments_status_user` (`status`,`user_id`),
  ADD KEY `idx_uta_user_course_source` (`user_id`,`course_id`,`assignment_source`),
  ADD KEY `idx_uta_department_id` (`department_id`),
  ADD KEY `idx_uta_assignment_source` (`assignment_source`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bug_reports`
--
ALTER TABLE `bug_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `course_departments`
--
ALTER TABLE `course_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `edit_requests`
--
ALTER TABLE `edit_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `ip_lockouts`
--
ALTER TABLE `ip_lockouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migration_log`
--
ALTER TABLE `migration_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `quiz_answer_choices`
--
ALTER TABLE `quiz_answer_choices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `quiz_retest_log`
--
ALTER TABLE `quiz_retest_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_retest_tracking`
--
ALTER TABLE `quiz_retest_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `replies`
--
ALTER TABLE `replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `training_courses`
--
ALTER TABLE `training_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `training_course_content`
--
ALTER TABLE `training_course_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `training_history`
--
ALTER TABLE `training_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_progress`
--
ALTER TABLE `training_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `training_quizzes`
--
ALTER TABLE `training_quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `training_sessions`
--
ALTER TABLE `training_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_departments`
--
ALTER TABLE `user_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_pinned_categories`
--
ALTER TABLE `user_pinned_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_quiz_answers`
--
ALTER TABLE `user_quiz_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `user_quiz_attempts`
--
ALTER TABLE `user_quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_training_assignments`
--
ALTER TABLE `user_training_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `edit_requests`
--
ALTER TABLE `edit_requests`
  ADD CONSTRAINT `edit_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `fk_files_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_files_reply` FOREIGN KEY (`reply_id`) REFERENCES `replies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `replies`
--
ALTER TABLE `replies`
  ADD CONSTRAINT `fk_replies_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD CONSTRAINT `fk_subcategories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_pinned_categories`
--
ALTER TABLE `user_pinned_categories`
  ADD CONSTRAINT `user_pinned_categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_pinned_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
