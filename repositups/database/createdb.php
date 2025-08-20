<?php
$servername = "localhost:3306";
$username = "root";
$password = ""; // Changed to your MySQL root password

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS repositups";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db("repositups");

// SQL statements for table creation
$tableQueries = [

    // 1. User Table
    "CREATE TABLE IF NOT EXISTS User (
        userID INT AUTO_INCREMENT PRIMARY KEY,
        studentID VARCHAR(50) DEFAULT NULL,
        firstName VARCHAR(255) NOT NULL,
        middleName VARCHAR(255),
        lastName VARCHAR(255) NOT NULL,
        contactNumber VARCHAR(15),
        email VARCHAR(255) NOT NULL,
        role ENUM(
            'Administrator',
            'MCIIS Staff',
            'Faculty',
            'Student'
        ) NOT NULL,
        password VARCHAR(255) NOT NULL,
        createdTimestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (email),
        UNIQUE (studentID)
    )",

    // 2. Faculty Table
    "CREATE TABLE IF NOT EXISTS Faculty (
        facultyID VARCHAR(50) PRIMARY KEY,
        firstName VARCHAR(255) NOT NULL,
        middleName VARCHAR(255),
        lastName VARCHAR(255) NOT NULL,
        position VARCHAR(100),
        designation VARCHAR(100),
        email VARCHAR(255),
        ORCID VARCHAR(50),
        contactNumber VARCHAR(50),
        educationalAttainment VARCHAR(255),
        fieldOfSpecialization VARCHAR(255),
        researchInterest VARCHAR(255),
        isPartOfCIC BOOLEAN DEFAULT TRUE,
        UNIQUE (email)
    )",

    // 3. Research Table
    "CREATE TABLE IF NOT EXISTS Research (
        researchID INT AUTO_INCREMENT PRIMARY KEY,
        uploadedBy INT,
        researchTitle VARCHAR(255) NOT NULL,
        researchAdviser VARCHAR(50),
        program ENUM(
            'Bachelor of Science in Information Technology',
            'Bachelor of Science in Computer Science',
            'Bachelor of Library and Information Science',
            'Master of Library and Information Science',
            'Master in Information Technology'
        ) DEFAULT NULL,
        publishedMonth TINYINT,
        publishedYear YEAR,
        researchAbstract TEXT,
        researchApprovalSheet LONGBLOB,
        researchManuscript LONGBLOB,
        FOREIGN KEY (uploadedBy) REFERENCES User(userID) ON DELETE SET NULL,
        FOREIGN KEY (researchAdviser) REFERENCES Faculty(facultyID) ON DELETE SET NULL,
        UNIQUE (researchTitle)
    )",

    // 4. Researcher Table
    "CREATE TABLE IF NOT EXISTS Researcher (
        researcherID INT AUTO_INCREMENT PRIMARY KEY,
        researchID INT,
        firstName VARCHAR(255) NOT NULL,
        middleName VARCHAR(255),
        lastName VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        FOREIGN KEY (researchID) REFERENCES Research(researchID) ON DELETE CASCADE,
        UNIQUE (email)
    )",

    // 5. Keyword Table
    "CREATE TABLE IF NOT EXISTS Keyword (
        keywordID INT AUTO_INCREMENT PRIMARY KEY,
        keywordName VARCHAR(255) NOT NULL UNIQUE
    )",

    // 6. Panel Table
    "CREATE TABLE IF NOT EXISTS Panel (
        panelID INT AUTO_INCREMENT PRIMARY KEY,
        facultyID VARCHAR(50),
        researchID INT,
        FOREIGN KEY (facultyID) REFERENCES Faculty(facultyID) ON DELETE CASCADE,
        FOREIGN KEY (researchID) REFERENCES Research(researchID) ON DELETE CASCADE
    )",

    // 7. UserFacultyAuditLog Table
    "CREATE TABLE IF NOT EXISTS UserFacultyAuditLog (
        auditLogID INT AUTO_INCREMENT PRIMARY KEY,
        modifiedBy INT,
        targetUserID INT DEFAULT NULL,
        targetFacultyID VARCHAR(50) DEFAULT NULL,
        actionType ENUM(
            'update user',
            'update faculty'
        ) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (modifiedBy) REFERENCES User(userID),
        FOREIGN KEY (targetUserID) REFERENCES User(userID) ON DELETE SET NULL,
        FOREIGN KEY (targetFacultyID) REFERENCES Faculty(facultyID) ON DELETE SET NULL
    )",

    // 8. ResearchAccessLog Table
    "CREATE TABLE IF NOT EXISTS ResearchAccessLog (
        accessLogID INT AUTO_INCREMENT PRIMARY KEY,
        researchID INT,
        userID INT,
        accessTimestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (researchID) REFERENCES Research(researchID) ON DELETE CASCADE,
        FOREIGN KEY (userID) REFERENCES User(userID) ON DELETE CASCADE
    )",

    // 9. KeywordSearchLog Table
    "CREATE TABLE IF NOT EXISTS KeywordSearchLog (
        searchLogID INT AUTO_INCREMENT PRIMARY KEY,
        keywordID INT,
        userID INT,
        searchTimestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (keywordID) REFERENCES Keyword(keywordID) ON DELETE CASCADE,
        FOREIGN KEY (userID) REFERENCES User(userID) ON DELETE CASCADE
    )",

    // 10. ResearchEntryLog Table
    "CREATE TABLE IF NOT EXISTS ResearchEntryLog (
        entryLogID INT AUTO_INCREMENT PRIMARY KEY,
        performedBy INT,
        actionType VARCHAR(50) NOT NULL,
        researchID INT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (performedBy) REFERENCES User(userID),
        FOREIGN KEY (researchID) REFERENCES Research(researchID) ON DELETE CASCADE
    )",

    // 11. ResearchKeyword (Join Table)
    "CREATE TABLE IF NOT EXISTS ResearchKeyword (
        researchID INT,
        keywordID INT,
        PRIMARY KEY (researchID, keywordID),
        FOREIGN KEY (researchID) REFERENCES Research(researchID) ON DELETE CASCADE,
        FOREIGN KEY (keywordID) REFERENCES Keyword(keywordID) ON DELETE CASCADE
    )",

    // 12. Contact Table
    "CREATE TABLE IF NOT EXISTS contact (
        contactID INT AUTO_INCREMENT PRIMARY KEY,
        userID INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message VARCHAR(1000) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_userID (userID),
        FOREIGN KEY (userID) REFERENCES User(userID) ON DELETE CASCADE
    )"
];

// Execute each table creation query
foreach ($tableQueries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Table created successfully.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

$conn->close();
?>