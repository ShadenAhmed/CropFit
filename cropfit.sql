-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: 27 أبريل 2026 الساعة 21:07
-- إصدار الخادم: 5.7.24
-- PHP Version: 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cropfit`
--

-- --------------------------------------------------------

--
-- بنية الجدول `consultation`
--

CREATE TABLE `consultation` (
  `consultID` int(11) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `soilType` varchar(100) DEFAULT NULL,
  `season` varchar(50) DEFAULT NULL,
  `date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- بنية الجدول `crop`
--

CREATE TABLE `crop` (
  `cropID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `waterRequirement` enum('Low','Moderate','High') DEFAULT NULL,
  `growthDuration` int(11) DEFAULT NULL,
  `preferredSoil` varchar(100) DEFAULT NULL,
  `preferredSeason` varchar(50) DEFAULT NULL,
  `suitabilityScore` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- إرجاع أو استيراد بيانات الجدول `crop`
--

INSERT INTO `crop` (`cropID`, `name`, `waterRequirement`, `growthDuration`, `preferredSoil`, `preferredSeason`, `suitabilityScore`) VALUES
(1, 'Wheat', 'Moderate', 120, 'Clay', 'Winter', 8.5),
(2, 'Tomato', 'High', 90, 'Loamy', 'Spring', 9.2),
(3, 'Date Palm', 'Low', 365, 'Sandy', 'All Seasons', 9.8),
(4, 'Maize', 'Moderate', 100, 'Loamy', 'Summer', 7.5),
(5, 'Barley', 'Low', 110, 'Sandy', 'Winter', 8),
(6, 'Potato', 'Moderate', 110, 'Loamy', 'Autumn', 8.7),
(7, 'Cucumber', 'High', 60, 'Silt', 'Spring', 7.9),
(8, 'Carrot', 'Moderate', 75, 'Sandy', 'Autumn', 8.2),
(9, 'Lettuce', 'High', 45, 'Loamy', 'Winter', 9),
(10, 'Onion', 'Low', 150, 'Silt', 'Winter', 8.4),
(11, 'Watermelon', 'Moderate', 85, 'Sandy', 'Summer', 9.5),
(12, 'Bell Pepper', 'High', 80, 'Loamy', 'Spring', 8.1),
(13, 'Grapes', 'Low', 180, 'Rocky', 'Spring', 9.3),
(14, 'Olives', 'Low', 365, 'Rocky', 'All Seasons', 9.7),
(15, 'Strawberry', 'High', 120, 'Loamy', 'Winter', 8.8),
(16, 'Alfalfa', 'High', 30, 'Clay', 'Summer', 7.2),
(17, 'Garlic', 'Low', 240, 'Silt', 'Autumn', 8.9),
(18, 'Eggplant', 'Moderate', 90, 'Loamy', 'Summer', 8.3),
(19, 'Spinach', 'Moderate', 50, 'Clay', 'Winter', 7.8),
(20, 'Lemon', 'Moderate', 365, 'Sandy', 'All Seasons', 9.1);

-- --------------------------------------------------------

--
-- بنية الجدول `recommendation`
--

CREATE TABLE `recommendation` (
  `recommendationID` int(11) NOT NULL,
  `consultID` int(11) DEFAULT NULL,
  `generatedDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- بنية الجدول `recommendation_crops`
--

CREATE TABLE `recommendation_crops` (
  `recommendationID` int(11) NOT NULL,
  `cropID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- بنية الجدول `savedcrop`
--

CREATE TABLE `savedcrop` (
  `savedID` int(11) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `cropID` int(11) DEFAULT NULL,
  `progress` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- بنية الجدول `user`
--

CREATE TABLE `user` (
  `userID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- إرجاع أو استيراد بيانات الجدول `user`
--

INSERT INTO `user` (`userID`, `name`, `email`, `password`, `role`) VALUES
(1, 'Sara Ahmed', 'sara@example.com', 'pass123', 'user'),
(2, 'Michael Scott', 'michael@example.com', 'pass456', 'user'),
(3, 'Nora Khalid', 'noura@example.com', 'pass789', 'user'),
(4, 'System Admin', 'admin@system.com', 'admin123', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `consultation`
--
ALTER TABLE `consultation`
  ADD PRIMARY KEY (`consultID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `crop`
--
ALTER TABLE `crop`
  ADD PRIMARY KEY (`cropID`);

--
-- Indexes for table `recommendation`
--
ALTER TABLE `recommendation`
  ADD PRIMARY KEY (`recommendationID`),
  ADD UNIQUE KEY `consultID` (`consultID`);

--
-- Indexes for table `recommendation_crops`
--
ALTER TABLE `recommendation_crops`
  ADD PRIMARY KEY (`recommendationID`,`cropID`),
  ADD KEY `cropID` (`cropID`);

--
-- Indexes for table `savedcrop`
--
ALTER TABLE `savedcrop`
  ADD PRIMARY KEY (`savedID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `cropID` (`cropID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `consultation`
--
ALTER TABLE `consultation`
  MODIFY `consultID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crop`
--
ALTER TABLE `crop`
  MODIFY `cropID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `recommendation`
--
ALTER TABLE `recommendation`
  MODIFY `recommendationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `savedcrop`
--
ALTER TABLE `savedcrop`
  MODIFY `savedID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- قيود الجداول المحفوظة
--

--
-- القيود للجدول `consultation`
--
ALTER TABLE `consultation`
  ADD CONSTRAINT `consultation_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE;

--
-- القيود للجدول `recommendation`
--
ALTER TABLE `recommendation`
  ADD CONSTRAINT `recommendation_ibfk_1` FOREIGN KEY (`consultID`) REFERENCES `consultation` (`consultID`) ON DELETE CASCADE;

--
-- القيود للجدول `recommendation_crops`
--
ALTER TABLE `recommendation_crops`
  ADD CONSTRAINT `recommendation_crops_ibfk_1` FOREIGN KEY (`recommendationID`) REFERENCES `recommendation` (`recommendationID`),
  ADD CONSTRAINT `recommendation_crops_ibfk_2` FOREIGN KEY (`cropID`) REFERENCES `crop` (`cropID`);

--
-- القيود للجدول `savedcrop`
--
ALTER TABLE `savedcrop`
  ADD CONSTRAINT `savedcrop_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`),
  ADD CONSTRAINT `savedcrop_ibfk_2` FOREIGN KEY (`cropID`) REFERENCES `crop` (`cropID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
