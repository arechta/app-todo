/*
SQLyog Ultimate v13.1.1 (64 bit)
MySQL - 10.4.32-MariaDB : Database - app_todo
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`app_todo` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `app_todo`;

/*Table structure for table `master_card` */

DROP TABLE IF EXISTS `master_card`;

CREATE TABLE `master_card` (
  `id_card` varchar(100) NOT NULL,
  `id_list` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'low',
  `status` enum('in_complete','complete') DEFAULT 'in_complete',
  `created_by` varchar(30) DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id_card`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `master_list` */

DROP TABLE IF EXISTS `master_list`;

CREATE TABLE `master_list` (
  `id_list` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_by` varchar(30) DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id_list`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `master_task` */

DROP TABLE IF EXISTS `master_task`;

CREATE TABLE `master_task` (
  `id_task` varchar(100) NOT NULL,
  `id_card` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` varchar(30) DEFAULT NULL,
  `created_date` datetime DEFAULT NULL,
  `updated_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id_task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `user_account` */

DROP TABLE IF EXISTS `user_account`;

CREATE TABLE `user_account` (
  `NIK` varchar(30) NOT NULL,
  `USERNAME` varchar(90) DEFAULT NULL,
  `PASSWORD` varchar(768) DEFAULT NULL,
  `NAMA` varchar(300) DEFAULT NULL,
  `NO_HP` varchar(60) DEFAULT NULL,
  `NO_WA` varchar(60) DEFAULT NULL,
  `EMAIL` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`NIK`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

/*Table structure for table `user_privileges` */

DROP TABLE IF EXISTS `user_privileges`;

CREATE TABLE `user_privileges` (
  `NIK` varchar(10) NOT NULL COMMENT 'Account ID',
  `PERMISSIONS` longtext DEFAULT NULL COMMENT 'JSON Format',
  `LEVEL` int(10) DEFAULT NULL,
  `LEVEL_CODE` varchar(20) DEFAULT NULL,
  `DATE_CREATED` datetime DEFAULT NULL,
  `DATE_UPDATED` datetime DEFAULT NULL,
  `CREATED_BY` varchar(10) DEFAULT NULL,
  `UPDATED_BY` varchar(10) DEFAULT NULL,
  `UPDATED_DESC` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`NIK`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
