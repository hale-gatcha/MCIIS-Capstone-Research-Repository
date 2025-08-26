-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for repositups
CREATE DATABASE IF NOT EXISTS `repositups` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `repositups`;

-- Dumping structure for procedure repositups.AddKeyword
DELIMITER //
CREATE PROCEDURE `AddKeyword`(
    IN keywordName VARCHAR(255)
)
BEGIN
    INSERT INTO Keyword (keywordName) VALUES (keywordName);
END//
DELIMITER ;

-- Dumping structure for procedure repositups.AddKeywordToResearch
DELIMITER //
CREATE PROCEDURE `AddKeywordToResearch`(
    IN rID INT,
    IN keywordID INT
)
BEGIN
    INSERT IGNORE INTO ResearchKeyword (researchID, keywordID)
    VALUES (rID, keywordID);
END//
DELIMITER ;

-- Dumping structure for procedure repositups.AddResearchEntry
DELIMITER //
CREATE PROCEDURE `AddResearchEntry`(
    IN uploaderID INT,
    IN title VARCHAR(255),
    IN adviserID VARCHAR(50),
    IN program ENUM(
        'Bachelor of Science in Information Technology',
        'Bachelor of Science in Computer Science',
        'Bachelor of Library and Information Science',
        'Master of Library and Information Science',
        'Master in Information Technology'
    ),
    IN month TINYINT,
    IN year YEAR,
    IN abstract TEXT,
    IN approvalSheet LONGBLOB,
    IN manuscript LONGBLOB
)
BEGIN
    INSERT INTO Research (
        uploadedBy, researchTitle, researchAdviser, program,
        publishedMonth, publishedYear, researchAbstract,
        researchApprovalSheet, researchManuscript
    ) VALUES (
        uploaderID, title, adviserID, program, month, year,
        abstract, approvalSheet, manuscript
    );
    
    SET @last_id = LAST_INSERT_ID();
    
    INSERT INTO ResearchEntryLog (
        performedBy, actionType, researchID, timestamp
    ) VALUES (
        uploaderID, 'create', @last_id, NOW()
    );
    
    -- Return the inserted ID
    SELECT @last_id AS researchID;
END//
DELIMITER ;

-- Dumping structure for procedure repositups.AddResearcher
DELIMITER //
CREATE PROCEDURE `AddResearcher`(
    IN rID INT,
    IN fname VARCHAR(255),
    IN mname VARCHAR(255),
    IN lname VARCHAR(255),
    IN email VARCHAR(255)
)
BEGIN
    INSERT INTO Researcher (
        researchID, firstName, middleName, lastName, email
    ) VALUES (
        rID, fname, mname, lname, email
    );
END//
DELIMITER ;

-- Dumping structure for procedure repositups.AssignPanelist
DELIMITER //
CREATE PROCEDURE `AssignPanelist`(
    IN rID INT,
    IN fID INT
)
BEGIN
    INSERT INTO Panel (researchID, facultyID) VALUES (rID, fID);
END//
DELIMITER ;

-- Dumping structure for table repositups.contact
CREATE TABLE IF NOT EXISTS `contact` (
  `contactID` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`contactID`),
  KEY `idx_userID` (`userID`),
  CONSTRAINT `contact_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for procedure repositups.DeleteKeyword
DELIMITER //
CREATE PROCEDURE `DeleteKeyword`(
    IN keywordID INT
)
BEGIN
    DELETE FROM Keyword WHERE keywordID = keywordID;
END//
DELIMITER ;

-- Dumping structure for procedure repositups.DeleteResearcher
DELIMITER //
CREATE PROCEDURE `DeleteResearcher`(
    IN rID INT
)
BEGIN
    DELETE FROM Researcher WHERE researcherID = rID;
END//
DELIMITER ;

-- Dumping structure for table repositups.faculty
CREATE TABLE IF NOT EXISTS `faculty` (
  `facultyID` varchar(50) NOT NULL,
  `firstName` varchar(255) NOT NULL,
  `middleName` varchar(255) DEFAULT NULL,
  `lastName` varchar(255) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ORCID` varchar(50) DEFAULT NULL,
  `contactNumber` varchar(50) DEFAULT NULL,
  `educationalAttainment` varchar(255) DEFAULT NULL,
  `fieldOfSpecialization` varchar(255) DEFAULT NULL,
  `researchInterest` varchar(255) DEFAULT NULL,
  `isPartOfCIC` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`facultyID`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for procedure repositups.GetAllFacultyProductivity
DELIMITER //
CREATE PROCEDURE `GetAllFacultyProductivity`()
BEGIN
    SELECT 
        f.facultyID, 
        f.firstName, 
        f.lastName,
        COUNT(DISTINCT r.researchID) AS advisedCount,
        COUNT(DISTINCT p.researchID) AS paneledCount
    FROM Faculty f
    LEFT JOIN Research r ON r.researchAdviser = f.facultyID
    LEFT JOIN Panel p ON p.facultyID = f.facultyID
    GROUP BY f.facultyID;
END//
DELIMITER ;

-- Dumping structure for procedure repositups.GetFacultyProductivityReport
DELIMITER //
CREATE PROCEDURE `GetFacultyProductivityReport`(IN facultyID INT)
BEGIN
    SELECT f.facultyID, f.firstName, f.lastName,
        (SELECT COUNT(*) FROM Research r WHERE r.researchAdviser = f.facultyID) AS advisedCount,
        (SELECT COUNT(*) FROM Panel p WHERE p.facultyID = f.facultyID) AS paneledCount
    FROM Faculty f
    WHERE f.facultyID = facultyID;
END//
DELIMITER ;

-- Dumping structure for table repositups.keyword
CREATE TABLE IF NOT EXISTS `keyword` (
  `keywordID` int(11) NOT NULL AUTO_INCREMENT,
  `keywordName` varchar(255) NOT NULL,
  PRIMARY KEY (`keywordID`),
  UNIQUE KEY `keywordName` (`keywordName`)
) ENGINE=InnoDB AUTO_INCREMENT=603 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table repositups.keywordsearchlog
CREATE TABLE IF NOT EXISTS `keywordsearchlog` (
  `searchLogID` int(11) NOT NULL AUTO_INCREMENT,
  `keywordID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `searchTimestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`searchLogID`),
  KEY `keywordID` (`keywordID`),
  KEY `userID` (`userID`),
  CONSTRAINT `keywordsearchlog_ibfk_1` FOREIGN KEY (`keywordID`) REFERENCES `keyword` (`keywordID`) ON DELETE CASCADE,
  CONSTRAINT `keywordsearchlog_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table repositups.panel
CREATE TABLE IF NOT EXISTS `panel` (
  `panelID` int(11) NOT NULL AUTO_INCREMENT,
  `facultyID` varchar(50) DEFAULT NULL,
  `researchID` int(11) DEFAULT NULL,
  PRIMARY KEY (`panelID`),
  KEY `facultyID` (`facultyID`),
  KEY `researchID` (`researchID`),
  CONSTRAINT `panel_ibfk_1` FOREIGN KEY (`facultyID`) REFERENCES `faculty` (`facultyID`) ON DELETE CASCADE,
  CONSTRAINT `panel_ibfk_2` FOREIGN KEY (`researchID`) REFERENCES `research` (`researchID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for procedure repositups.RemoveKeywordFromResearch
DELIMITER //
CREATE PROCEDURE `RemoveKeywordFromResearch`(
    IN rID INT,
    IN keywordID INT
)
BEGIN
    DELETE FROM ResearchKeyword 
    WHERE researchID = rID AND keywordID = keywordID;
END//
DELIMITER ;

-- Dumping structure for procedure repositups.RemovePanelist
DELIMITER //
CREATE PROCEDURE `RemovePanelist`(
    IN rID INT,
    IN fID INT
)
BEGIN
    DELETE FROM Panel WHERE researchID = rID AND facultyID = fID;
END//
DELIMITER ;

-- Dumping structure for table repositups.research
CREATE TABLE IF NOT EXISTS `research` (
  `researchID` int(11) NOT NULL AUTO_INCREMENT,
  `uploadedBy` int(11) DEFAULT NULL,
  `researchTitle` varchar(255) NOT NULL,
  `researchAdviser` varchar(50) DEFAULT NULL,
  `program` enum('Bachelor of Science in Information Technology','Bachelor of Science in Computer Science','Bachelor of Library and Information Science','Master of Library and Information Science','Master in Information Technology') DEFAULT NULL,
  `publishedMonth` tinyint(4) DEFAULT NULL,
  `publishedYear` year(4) DEFAULT NULL,
  `researchAbstract` text DEFAULT NULL,
  `researchApprovalSheet` longblob DEFAULT NULL,
  `researchManuscript` longblob DEFAULT NULL,
  PRIMARY KEY (`researchID`),
  UNIQUE KEY `researchTitle` (`researchTitle`),
  KEY `uploadedBy` (`uploadedBy`),
  KEY `researchAdviser` (`researchAdviser`),
  CONSTRAINT `research_ibfk_1` FOREIGN KEY (`uploadedBy`) REFERENCES `user` (`userID`) ON DELETE SET NULL,
  CONSTRAINT `research_ibfk_2` FOREIGN KEY (`researchAdviser`) REFERENCES `faculty` (`facultyID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table repositups.researchaccesslog
CREATE TABLE IF NOT EXISTS `researchaccesslog` (
  `accessLogID` int(11) NOT NULL AUTO_INCREMENT,
  `researchID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `accessTimestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`accessLogID`),
  KEY `researchID` (`researchID`),
  KEY `userID` (`userID`),
  CONSTRAINT `researchaccesslog_ibfk_1` FOREIGN KEY (`researchID`) REFERENCES `research` (`researchID`) ON DELETE CASCADE,
  CONSTRAINT `researchaccesslog_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `user` (`userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table repositups.researchentrylog
CREATE TABLE IF NOT EXISTS `researchentrylog` (
  `entryLogID` int(11) NOT NULL AUTO_INCREMENT,
  `performedBy` int(11) DEFAULT NULL,
  `actionType` varchar(50) NOT NULL,
  `researchID` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`entryLogID`),
  KEY `performedBy` (`performedBy`),
  KEY `researchID` (`researchID`),
  CONSTRAINT `researchentrylog_ibfk_1` FOREIGN KEY (`performedBy`) REFERENCES `user` (`userID`),
  CONSTRAINT `researchentrylog_ibfk_2` FOREIGN KEY (`researchID`) REFERENCES `research` (`researchID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table repositups.researcher
CREATE TABLE IF NOT EXISTS `researcher` (
  `researcherID` int(11) NOT NULL AUTO_INCREMENT,
  `researchID` int(11) DEFAULT NULL,
  `firstName` varchar(255) NOT NULL,
  `middleName` varchar(255) DEFAULT NULL,
  `lastName` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`researcherID`),
  UNIQUE KEY `email` (`email`),
  KEY `researchID` (`researchID`),
  CONSTRAINT `researcher_ibfk_1` FOREIGN KEY (`researchID`) REFERENCES `research` (`researchID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table repositups.researchkeyword
CREATE TABLE IF NOT EXISTS `researchkeyword` (
  `researchID` int(11) NOT NULL,
  `keywordID` int(11) NOT NULL,
  PRIMARY KEY (`researchID`,`keywordID`),
  KEY `keywordID` (`keywordID`),
  CONSTRAINT `researchkeyword_ibfk_1` FOREIGN KEY (`researchID`) REFERENCES `research` (`researchID`) ON DELETE CASCADE,
  CONSTRAINT `researchkeyword_ibfk_2` FOREIGN KEY (`keywordID`) REFERENCES `keyword` (`keywordID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for procedure repositups.sp_AddFaculty
DELIMITER //
CREATE PROCEDURE `sp_AddFaculty`(
    IN p_facultyID VARCHAR(50),
    IN p_firstName VARCHAR(255),
    IN p_middleName VARCHAR(255),
    IN p_lastName VARCHAR(255),
    IN p_position VARCHAR(100),
    IN p_designation VARCHAR(100),
    IN p_email VARCHAR(255),
    IN p_ORCID VARCHAR(50),
    IN p_contactNumber VARCHAR(50),
    IN p_educAttainment VARCHAR(255),
    IN p_specialization VARCHAR(255),
    IN p_researchInterest VARCHAR(255),
    IN p_isPartOfCIC BOOLEAN
)
BEGIN
    INSERT INTO Faculty (
        facultyID, firstName, middleName, lastName,
        position, designation, email, ORCID,
        contactNumber, educationalAttainment,
        fieldOfSpecialization, researchInterest,
        isPartOfCIC
    )
    VALUES (
        p_facultyID, p_firstName, p_middleName, p_lastName,
        p_position, p_designation, p_email, p_ORCID,
        p_contactNumber, p_educAttainment,
        p_specialization, p_researchInterest,
        p_isPartOfCIC
    );
END//
DELIMITER ;

-- Dumping structure for procedure repositups.sp_AddUser
DELIMITER //
CREATE PROCEDURE `sp_AddUser`(
    IN p_studentID VARCHAR(50),
    IN p_firstName VARCHAR(255),
    IN p_middleName VARCHAR(255),
    IN p_lastName VARCHAR(255),
    IN p_contactNumber VARCHAR(15),
    IN p_email VARCHAR(255),
    IN p_role ENUM('Administrator','MCIIS Staff','Faculty','Student'),
    IN p_password VARCHAR(255)
)
BEGIN
    INSERT INTO User (
        studentID, firstName, middleName, lastName, contactNumber, email, role, password
    ) VALUES (
        p_studentID, p_firstName, p_middleName, p_lastName, p_contactNumber, p_email, p_role, p_password
    );
END//
DELIMITER ;

-- Dumping structure for procedure repositups.sp_DeleteFaculty
DELIMITER //
CREATE PROCEDURE `sp_DeleteFaculty`(
    IN p_facultyID INT
)
BEGIN
    DELETE FROM Faculty WHERE facultyID = p_facultyID;
END//
DELIMITER ;

-- Dumping structure for procedure repositups.sp_DeleteUser
DELIMITER //
CREATE PROCEDURE `sp_DeleteUser`(
    IN p_userID INT
)
BEGIN
    DELETE FROM User WHERE userID = p_userID;
END//
DELIMITER ;

-- Dumping structure for procedure repositups.sp_FilterResearch
DELIMITER //
CREATE PROCEDURE `sp_FilterResearch`(
    IN p_adviserID VARCHAR(50),
    IN p_program VARCHAR(100),
    IN p_year YEAR
)
BEGIN
    SELECT * FROM vw_ResearchFullInfo
    WHERE 
        (p_adviserID IS NULL OR researchAdviser = p_adviserID)
        AND (p_program IS NULL OR program = p_program)
        AND (p_year IS NULL OR publishedYear = p_year);
END//
DELIMITER ;

-- Dumping structure for procedure repositups.sp_SearchResearch
DELIMITER //
CREATE PROCEDURE `sp_SearchResearch`(
    IN p_title VARCHAR(255),
    IN p_keyword VARCHAR(255)
)
BEGIN
    SELECT * FROM vw_ResearchFullInfo
    WHERE 
    (p_title IS NULL OR researchTitle LIKE CONCAT('%', p_title, '%'))
    OR
    (p_keyword IS NULL OR keywords LIKE CONCAT('%', p_keyword, '%'));
END//
DELIMITER ;

-- Dumping structure for procedure repositups.sp_UpdateFaculty
DELIMITER //
CREATE PROCEDURE `sp_UpdateFaculty`(
    IN p_facultyID VARCHAR(50),
    IN p_firstName VARCHAR(255),
    IN p_middleName VARCHAR(255),
    IN p_lastName VARCHAR(255),
    IN p_position VARCHAR(100),
    IN p_designation VARCHAR(100),
    IN p_email VARCHAR(255),
    IN p_ORCID VARCHAR(50),
    IN p_contactNumber VARCHAR(50),
    IN p_educAttainment VARCHAR(255),
    IN p_specialization VARCHAR(255),
    IN p_researchInterest VARCHAR(255),
    IN p_isPartOfCIC BOOLEAN,
    IN p_modifiedByUserID INT 
)
BEGIN
    DECLARE v_old_firstName VARCHAR(255);
    DECLARE v_old_middleName VARCHAR(255);
    DECLARE v_old_lastName VARCHAR(255);
    DECLARE v_old_position VARCHAR(100);
    DECLARE v_old_designation VARCHAR(100);
    DECLARE v_old_email VARCHAR(255);
    DECLARE v_old_ORCID VARCHAR(50);
    DECLARE v_old_contactNumber VARCHAR(50);
    DECLARE v_old_educAttainment VARCHAR(255);
    DECLARE v_old_specialization VARCHAR(255);
    DECLARE v_old_researchInterest VARCHAR(255);
    DECLARE v_old_isPartOfCIC BOOLEAN;

    -- Fetch current values before update
    SELECT firstName, middleName, lastName, position, designation,
           email, ORCID, contactNumber, educationalAttainment,
           fieldOfSpecialization, researchInterest, isPartOfCIC
    INTO v_old_firstName, v_old_middleName, v_old_lastName, v_old_position,
         v_old_designation, v_old_email, v_old_ORCID, v_old_contactNumber,
         v_old_educAttainment, v_old_specialization, v_old_researchInterest, v_old_isPartOfCIC
    FROM Faculty
    WHERE facultyID = p_facultyID;

    -- Perform the update
    UPDATE Faculty
    SET firstName = p_firstName,
        middleName = p_middleName,
        lastName = p_lastName,
        position = p_position,
        designation = p_designation,
        email = p_email,
        ORCID = p_ORCID,
        contactNumber = p_contactNumber,
        educationalAttainment = p_educAttainment,
        fieldOfSpecialization = p_specialization,
        researchInterest = p_researchInterest,
        isPartOfCIC = p_isPartOfCIC
    WHERE facultyID = p_facultyID;

    -- Log the update if anything changed
    IF v_old_firstName != p_firstName OR
       v_old_middleName != p_middleName OR
       v_old_lastName != p_lastName OR
       v_old_position != p_position OR
       v_old_designation != p_designation OR
       v_old_email != p_email OR
       v_old_ORCID != p_ORCID OR
       v_old_contactNumber != p_contactNumber OR
       v_old_educAttainment != p_educAttainment OR
       v_old_specialization != p_specialization OR
       v_old_researchInterest != p_researchInterest OR
       v_old_isPartOfCIC != p_isPartOfCIC THEN

        INSERT INTO UserFacultyAuditLog (
            modifiedBy,
            targetUserID,
            actionType
        )
        VALUES (
            p_modifiedByUserID,
            p_facultyID,
            'update faculty'
        );
    END IF;
END//
DELIMITER ;

-- Dumping structure for procedure repositups.sp_UpdateUser
DELIMITER //
CREATE PROCEDURE `sp_UpdateUser`(
    IN p_userID INT,
    IN p_studentID VARCHAR(50),
    IN p_firstName VARCHAR(255),
    IN p_middleName VARCHAR(255),
    IN p_lastName VARCHAR(255),
    IN p_contactNumber VARCHAR(15),
    IN p_email VARCHAR(255),
    IN p_role ENUM('Administrator','MCIIS Staff','Faculty','Student'),
    IN p_password VARCHAR(255),
    IN p_modifiedByUserID INT
)
BEGIN
    -- Update user
    UPDATE User
    SET 
        studentID = CASE WHEN p_role = 'Student' THEN p_studentID ELSE NULL END,
        firstName = p_firstName,
        middleName = p_middleName,
        lastName = p_lastName,
        contactNumber = p_contactNumber,
        email = p_email,
        role = p_role,
        password = p_password
    WHERE userID = p_userID;

    -- Always log the update
    INSERT INTO UserFacultyAuditLog (
        modifiedBy, targetUserID, actionType
    )
    VALUES (
        p_modifiedByUserID, p_userID, 'update user'
    );
END//
DELIMITER ;

-- Dumping structure for procedure repositups.UpdateResearchEntry
DELIMITER //
CREATE PROCEDURE `UpdateResearchEntry`(
    IN rID INT,
    IN updaterID INT,
    IN title VARCHAR(255),
    IN adviserID VARCHAR(50),
    IN program ENUM(
        'Bachelor of Science in Information Technology',
        'Bachelor of Science in Computer Science',
        'Bachelor of Library and Information Science',
        'Master of Library and Information Science',
        'Master in Information Technology'
    ),
    IN month TINYINT,
    IN year YEAR,
    IN abstract TEXT,
    IN approvalSheet LONGBLOB,
    IN manuscript LONGBLOB
)
BEGIN
    UPDATE Research
    SET researchTitle = title,
        researchAdviser = adviserID,
        program = program,
        publishedMonth = month,
        publishedYear = year,
        researchAbstract = abstract,
        researchApprovalSheet = approvalSheet,
        researchManuscript = manuscript
    WHERE researchID = rID;
    
    INSERT INTO ResearchEntryLog (
        performedBy, actionType, researchID, timestamp
    ) VALUES (
        updaterID, 'modify', rID, NOW()
    );
END//
DELIMITER ;

-- Dumping structure for procedure repositups.UpdateResearcher
DELIMITER //
CREATE PROCEDURE `UpdateResearcher`(
    IN researcherID INT,
    IN fname VARCHAR(255),
    IN mname VARCHAR(255),
    IN lname VARCHAR(255),
    IN email VARCHAR(255),
    IN newResearchID INT
)
BEGIN
    UPDATE Researcher
    SET firstName = fname,
        middleName = mname,
        lastName = lname,
        email = email,
        researchID = newResearchID
    WHERE researcherID = researcherID;
END//
DELIMITER ;

-- Dumping structure for table repositups.user
CREATE TABLE IF NOT EXISTS `user` (
  `userID` int(11) NOT NULL AUTO_INCREMENT,
  `studentID` varchar(50) DEFAULT NULL,
  `firstName` varchar(255) NOT NULL,
  `middleName` varchar(255) DEFAULT NULL,
  `lastName` varchar(255) NOT NULL,
  `contactNumber` varchar(15) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('Administrator','MCIIS Staff','Faculty','Student') NOT NULL,
  `password` varchar(255) NOT NULL,
  `createdTimestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`userID`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `studentID` (`studentID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table repositups.userfacultyauditlog
CREATE TABLE IF NOT EXISTS `userfacultyauditlog` (
  `auditLogID` int(11) NOT NULL AUTO_INCREMENT,
  `modifiedBy` int(11) DEFAULT NULL,
  `targetUserID` int(11) DEFAULT NULL,
  `targetFacultyID` varchar(50) DEFAULT NULL,
  `actionType` enum('update user','update faculty') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`auditLogID`),
  KEY `modifiedBy` (`modifiedBy`),
  KEY `targetUserID` (`targetUserID`),
  KEY `targetFacultyID` (`targetFacultyID`),
  CONSTRAINT `userfacultyauditlog_ibfk_1` FOREIGN KEY (`modifiedBy`) REFERENCES `user` (`userID`),
  CONSTRAINT `userfacultyauditlog_ibfk_2` FOREIGN KEY (`targetUserID`) REFERENCES `user` (`userID`) ON DELETE SET NULL,
  CONSTRAINT `userfacultyauditlog_ibfk_3` FOREIGN KEY (`targetFacultyID`) REFERENCES `faculty` (`facultyID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for view repositups.vw_facultyprofiles
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_facultyprofiles` (
	`facultyID` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_general_ci',
	`fullName` TEXT NULL COLLATE 'utf8mb4_general_ci',
	`position` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`designation` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`email` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`ORCID` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`contactNumber` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`educationalAttainment` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`fieldOfSpecialization` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`researchInterest` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`isPartOfCIC` TINYINT(1) NULL
);

-- Dumping structure for view repositups.vw_recentuserregistrations
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_recentuserregistrations` (
	`recentRegistrations` BIGINT(21) NOT NULL
);

-- Dumping structure for view repositups.vw_researchcountperprogram
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_researchcountperprogram` (
	`program` ENUM('Bachelor of Science in Information Technology','Bachelor of Science in Computer Science','Bachelor of Library and Information Science','Master of Library and Information Science','Master in Information Technology') NULL COLLATE 'utf8mb4_general_ci',
	`researchCount` BIGINT(21) NOT NULL
);

-- Dumping structure for view repositups.vw_researchcountperyear
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_researchcountperyear` (
	`publishedYear` YEAR NULL,
	`researchCount` BIGINT(21) NOT NULL
);

-- Dumping structure for view repositups.vw_researchfullinfo
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_researchfullinfo` (
	`researchID` INT(11) NOT NULL,
	`researchTitle` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_general_ci',
	`program` ENUM('Bachelor of Science in Information Technology','Bachelor of Science in Computer Science','Bachelor of Library and Information Science','Master of Library and Information Science','Master in Information Technology') NULL COLLATE 'utf8mb4_general_ci',
	`publishedMonth` TINYINT(4) NULL,
	`publishedYear` YEAR NULL,
	`researchAbstract` TEXT NULL COLLATE 'utf8mb4_general_ci',
	`researchApprovalSheet` LONGBLOB NULL,
	`researchManuscript` LONGBLOB NULL,
	`researchAdviser` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`adviserName` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`researchers` MEDIUMTEXT NULL COLLATE 'utf8mb4_general_ci',
	`panelists` MEDIUMTEXT NULL COLLATE 'utf8mb4_general_ci',
	`keywords` MEDIUMTEXT NULL COLLATE 'utf8mb4_general_ci'
);

-- Dumping structure for view repositups.vw_topaccessedresearches
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_topaccessedresearches` (
	`researchID` INT(11) NOT NULL,
	`researchTitle` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_general_ci',
	`accessCount` BIGINT(21) NOT NULL
);

-- Dumping structure for view repositups.vw_topadvisers
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_topadvisers` (
	`researchAdviser` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`adviserName` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_general_ci',
	`totalAdvised` BIGINT(21) NOT NULL
);

-- Dumping structure for view repositups.vw_toppanelists
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_toppanelists` (
	`facultyID` VARCHAR(1) NULL COLLATE 'utf8mb4_general_ci',
	`panelistName` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_general_ci',
	`totalPaneled` BIGINT(21) NOT NULL
);

-- Dumping structure for view repositups.vw_topsearchedkeywords
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_topsearchedkeywords` (
	`keywordID` INT(11) NULL,
	`searchCount` BIGINT(21) NOT NULL
);

-- Dumping structure for view repositups.vw_userroledistribution
-- Creating temporary table to overcome VIEW dependency errors
CREATE TABLE `vw_userroledistribution` (
	`role` VARCHAR(1) NOT NULL COLLATE 'utf8mb4_general_ci',
	`totalUsers` BIGINT(21) NOT NULL
);

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_facultyprofiles`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_facultyprofiles` AS SELECT 
    facultyID,
    CONCAT(firstName, ' ', middleName, ' ', lastName) AS fullName,
    position,
    designation,
    email,
    ORCID,
    contactNumber,
    educationalAttainment,
    fieldOfSpecialization,
    researchInterest,
    isPartOfCIC
FROM Faculty 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_recentuserregistrations`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_recentuserregistrations` AS SELECT COUNT(*) AS recentRegistrations
FROM User
WHERE createdTimestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_researchcountperprogram`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_researchcountperprogram` AS SELECT 
    program,
    COUNT(*) AS researchCount
FROM Research
GROUP BY program 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_researchcountperyear`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_researchcountperyear` AS SELECT 
    publishedYear,
    COUNT(*) AS researchCount
FROM Research
GROUP BY publishedYear
ORDER BY publishedYear DESC 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_researchfullinfo`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_researchfullinfo` AS SELECT 
    r.researchID,
    r.researchTitle,
    r.program,
    r.publishedMonth,
    r.publishedYear,
    r.researchAbstract,
    r.researchApprovalSheet,
    r.researchManuscript,
    r.researchAdviser,
    CONCAT(f.firstName, ' ', f.lastName) AS adviserName,
    GROUP_CONCAT(DISTINCT CONCAT(re.firstName, ' ', re.lastName) SEPARATOR ', ') AS researchers,
    GROUP_CONCAT(DISTINCT CONCAT(pf.firstName, ' ', pf.lastName) SEPARATOR ', ') AS panelists,
    GROUP_CONCAT(DISTINCT k.keywordName SEPARATOR ', ') AS keywords
FROM Research r
LEFT JOIN Faculty f ON r.researchAdviser = f.facultyID
LEFT JOIN Researcher re ON r.researchID = re.researchID
LEFT JOIN Panel p ON r.researchID = p.researchID
LEFT JOIN Faculty pf ON p.facultyID = pf.facultyID
LEFT JOIN ResearchKeyword rk ON r.researchID = rk.researchID
LEFT JOIN Keyword k ON rk.keywordID = k.keywordID
GROUP BY r.researchID 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_topaccessedresearches`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_topaccessedresearches` AS SELECT 
    r.researchID,
    r.researchTitle,
    COUNT(al.accessLogID) AS accessCount
FROM Research r
JOIN ResearchAccessLog al ON r.researchID = al.researchID
GROUP BY r.researchID, r.researchTitle
ORDER BY accessCount DESC
LIMIT 5 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_topadvisers`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_topadvisers` AS SELECT 
    r.researchAdviser,
    CONCAT(f.firstName, ' ', f.lastName) AS adviserName,
    COUNT(*) AS totalAdvised
FROM Research r
JOIN Faculty f ON r.researchAdviser = f.facultyID
GROUP BY r.researchAdviser, adviserName
ORDER BY totalAdvised DESC
LIMIT 10 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_toppanelists`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_toppanelists` AS SELECT 
    p.facultyID,
    CONCAT(f.firstName, ' ', f.lastName) AS panelistName,
    COUNT(p.researchID) AS totalPaneled
FROM Panel p
JOIN Faculty f ON p.facultyID = f.facultyID
GROUP BY p.facultyID, panelistName
ORDER BY totalPaneled DESC
LIMIT 5 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_topsearchedkeywords`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_topsearchedkeywords` AS SELECT 
    keywordID,
    COUNT(*) AS searchCount
FROM KeywordSearchLog
GROUP BY keywordID
ORDER BY searchCount DESC
LIMIT 5 
;

-- Removing temporary table and create final VIEW structure
DROP TABLE IF EXISTS `vw_userroledistribution`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_userroledistribution` AS WITH AllRoles AS (
    SELECT 'Administrator' AS role
    UNION SELECT 'MCIIS Staff'
    UNION SELECT 'Faculty'
    UNION SELECT 'Student'
)
SELECT 
    ar.role,
    COUNT(u.userID) AS totalUsers
FROM AllRoles ar
LEFT JOIN User u ON ar.role = u.role
GROUP BY ar.role
ORDER BY 
    CASE ar.role
        WHEN 'Administrator' THEN 1
        WHEN 'MCIIS Staff' THEN 2
        WHEN 'Faculty' THEN 3
        WHEN 'Student' THEN 4
    END 
;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
