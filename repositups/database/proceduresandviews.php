<?php
// Use the shared database connection from config.php
require_once '../config.php';

// Prepare the stored procedure
$sql = "
CREATE PROCEDURE sp_AddUser(
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
END;
";

// Prepare the stored procedure for adding a faculty
$sql_faculty = "
CREATE PROCEDURE sp_AddFaculty (
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
END;
";

// Prepare the stored procedure for updating a user
$sql_update_user = "
CREATE PROCEDURE sp_UpdateUser (
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
END;
";

// Prepare the stored procedure for deleting a user
$sql_delete_user = "
CREATE PROCEDURE sp_DeleteUser (
    IN p_userID INT
)
BEGIN
    DELETE FROM User WHERE userID = p_userID;
END;
";

// Prepare the stored procedure for updating a faculty
$sql_update_faculty = "
CREATE PROCEDURE sp_UpdateFaculty (
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
END;
";

// Prepare the stored procedure for deleting a faculty
$sql_delete_faculty = "
CREATE PROCEDURE sp_DeleteFaculty (
    IN p_facultyID INT
)
BEGIN
    DELETE FROM Faculty WHERE facultyID = p_facultyID;
END;
";

// Prepare the stored procedure for searching research
$sql_search_research = "
CREATE PROCEDURE sp_SearchResearch (
    IN p_title VARCHAR(255),
    IN p_keyword VARCHAR(255)
)
BEGIN
    SELECT * FROM vw_ResearchFullInfo
    WHERE 
    (p_title IS NULL OR researchTitle LIKE CONCAT('%', p_title, '%'))
    OR
    (p_keyword IS NULL OR keywords LIKE CONCAT('%', p_keyword, '%'));
END;
";

// Prepare the stored procedure for filtering research
$sql_filter_research = "
CREATE PROCEDURE sp_FilterResearch (
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
END;
";

// Prepare the view for faculty profiles
$sql_view_faculty_profiles = "
CREATE OR REPLACE VIEW vw_FacultyProfiles AS
SELECT 
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
FROM Faculty;
";

// Prepare the view for user role distribution
$sql_view_user_role_distribution = "
CREATE OR REPLACE VIEW vw_UserRoleDistribution AS
WITH AllRoles AS (
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
    END;
";

// Prepare the view for top accessed researches
$sql_view_top_accessed_researches = "
CREATE OR REPLACE VIEW vw_TopAccessedResearches AS
SELECT 
    r.researchID,
    r.researchTitle,
    COUNT(al.accessLogID) AS accessCount
FROM Research r
JOIN ResearchAccessLog al ON r.researchID = al.researchID
GROUP BY r.researchID, r.researchTitle
ORDER BY accessCount DESC
LIMIT 5;
";

// Prepare the view for top searched keywords
$sql_view_top_searched_keywords = "
CREATE OR REPLACE VIEW vw_TopSearchedKeywords AS
SELECT 
    keywordID,
    COUNT(*) AS searchCount
FROM KeywordSearchLog
GROUP BY keywordID
ORDER BY searchCount DESC
LIMIT 5;
";

// Prepare the view for research count per program
$sql_view_research_count_per_program = "
CREATE OR REPLACE VIEW vw_ResearchCountPerProgram AS
SELECT 
    program,
    COUNT(*) AS researchCount
FROM Research
GROUP BY program;
";

// Prepare the view for research count per year
$sql_view_research_count_per_year = "
CREATE OR REPLACE VIEW vw_ResearchCountPerYear AS
SELECT 
    publishedYear,
    COUNT(*) AS researchCount
FROM Research
GROUP BY publishedYear
ORDER BY publishedYear DESC;
";

// Prepare the stored procedure for adding a research entry
$sql_add_research_entry = "
CREATE PROCEDURE AddResearchEntry (
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
END;
";

// Prepare the stored procedure for adding a researcher
$sql_add_researcher = "
CREATE PROCEDURE AddResearcher (
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
END;
";

// Prepare the stored procedure for adding a keyword
$sql_add_keyword = "
CREATE PROCEDURE AddKeyword (
    IN keywordName VARCHAR(255)
)
BEGIN
    INSERT INTO Keyword (keywordName) VALUES (keywordName);
END;
";

// Prepare the stored procedure for adding a keyword to research
$sql_add_keyword_to_research = "
CREATE PROCEDURE AddKeywordToResearch (
    IN rID INT,
    IN keywordID INT
)
BEGIN
    INSERT IGNORE INTO ResearchKeyword (researchID, keywordID)
    VALUES (rID, keywordID);
END;
";

// Prepare the stored procedure for assigning a panelist
$sql_assign_panelist = "
CREATE PROCEDURE AssignPanelist (
    IN rID INT,
    IN fID INT
)
BEGIN
    INSERT INTO Panel (researchID, facultyID) VALUES (rID, fID);
END;
";

// Prepare the stored procedure for updating a research entry
$sql_update_research_entry = "
CREATE PROCEDURE UpdateResearchEntry (
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
END;
";

// Prepare the stored procedure for updating a researcher
$sql_update_researcher = "
CREATE PROCEDURE UpdateResearcher (
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
END;
";

// Prepare the stored procedure for deleting a researcher
$sql_delete_researcher = "
CREATE PROCEDURE DeleteResearcher (
    IN rID INT
)
BEGIN
    DELETE FROM Researcher WHERE researcherID = rID;
END;
";

// Prepare the stored procedure for removing a keyword from research
$sql_remove_keyword_from_research = "
CREATE PROCEDURE RemoveKeywordFromResearch (
    IN rID INT,
    IN keywordID INT
)
BEGIN
    DELETE FROM ResearchKeyword 
    WHERE researchID = rID AND keywordID = keywordID;
END;
";

// Prepare the stored procedure for deleting a keyword
$sql_delete_keyword = "
CREATE PROCEDURE DeleteKeyword (
    IN keywordID INT
)
BEGIN
    DELETE FROM Keyword WHERE keywordID = keywordID;
END;
";

// Prepare the stored procedure for removing a panelist
$sql_remove_panelist = "
CREATE PROCEDURE RemovePanelist (
    IN rID INT,
    IN fID INT
)
BEGIN
    DELETE FROM Panel WHERE researchID = rID AND facultyID = fID;
END;
";

// Prepare the stored procedure for getting all faculty productivity
$sql_get_all_faculty_productivity = "
CREATE PROCEDURE GetAllFacultyProductivity()
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
END;
";

// Prepare the view for top advisers
$sql_view_top_advisers = "
CREATE OR REPLACE VIEW vw_TopAdvisers AS
SELECT 
    r.researchAdviser,
    CONCAT(f.firstName, ' ', f.lastName) AS adviserName,
    COUNT(*) AS totalAdvised
FROM Research r
JOIN Faculty f ON r.researchAdviser = f.facultyID
GROUP BY r.researchAdviser, adviserName
ORDER BY totalAdvised DESC
LIMIT 10;
";

// Prepare the view for top panelists
$sql_view_top_panelists = "
CREATE OR REPLACE VIEW vw_TopPanelists AS
SELECT 
    p.facultyID,
    CONCAT(f.firstName, ' ', f.lastName) AS panelistName,
    COUNT(p.researchID) AS totalPaneled
FROM Panel p
JOIN Faculty f ON p.facultyID = f.facultyID
GROUP BY p.facultyID, panelistName
ORDER BY totalPaneled DESC
LIMIT 5;
";

// Prepare the view for research full info
$sql_view_research_full_info = "
CREATE OR REPLACE VIEW vw_ResearchFullInfo AS
SELECT 
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
GROUP BY r.researchID;
";

// Prepare the stored procedure for getting productivity report of a specific faculty
$sql_get_faculty_productivity_report = "
CREATE PROCEDURE GetFacultyProductivityReport(IN facultyID INT)
BEGIN
    SELECT f.facultyID, f.firstName, f.lastName,
        (SELECT COUNT(*) FROM Research r WHERE r.researchAdviser = f.facultyID) AS advisedCount,
        (SELECT COUNT(*) FROM Panel p WHERE p.facultyID = f.facultyID) AS paneledCount
    FROM Faculty f
    WHERE f.facultyID = facultyID;
END;
";

// Prepare the view for recent user registrations (last 30 days)
$sql_view_recent_user_registrations = "
CREATE OR REPLACE VIEW vw_RecentUserRegistrations AS
SELECT COUNT(*) AS recentRegistrations
FROM User
WHERE createdTimestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY);
";

if ($conn->query($sql) === TRUE) {
    echo "Stored procedure sp_AddUser created successfully.<br>";
} else {
    echo "Error creating sp_AddUser: " . $conn->error . "<br>";
}

if ($conn->query($sql_faculty) === TRUE) {
    echo "Stored procedure sp_AddFaculty created successfully.<br>";
} else {
    echo "Error creating sp_AddFaculty: " . $conn->error . "<br>";
}

if ($conn->query($sql_update_user) === TRUE) {
    echo "Stored procedure sp_UpdateUser created successfully.<br>";
} else {
    echo "Error creating sp_UpdateUser: " . $conn->error . "<br>";
}

if ($conn->query($sql_delete_user) === TRUE) {
    echo "Stored procedure sp_DeleteUser created successfully.<br>";
} else {
    echo "Error creating sp_DeleteUser: " . $conn->error . "<br>";
}

if ($conn->query($sql_update_faculty) === TRUE) {
    echo "Stored procedure sp_UpdateFaculty created successfully.<br>";
} else {
    echo "Error creating sp_UpdateFaculty: " . $conn->error . "<br>";
}

if ($conn->query($sql_delete_faculty) === TRUE) {
    echo "Stored procedure sp_DeleteFaculty created successfully.<br>";
} else {
    echo "Error creating sp_DeleteFaculty: " . $conn->error . "<br>";
}

if ($conn->query($sql_search_research) === TRUE) {
    echo "Stored procedure sp_SearchResearch created successfully.<br>";
} else {
    echo "Error creating sp_SearchResearch: " . $conn->error . "<br>";
}

if ($conn->query($sql_filter_research) === TRUE) {
    echo "Stored procedure sp_FilterResearch created successfully.<br>";
} else {
    echo "Error creating sp_FilterResearch: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_faculty_profiles) === TRUE) {
    echo "View vw_FacultyProfiles created successfully.<br>";
} else {
    echo "Error creating vw_FacultyProfiles: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_user_role_distribution) === TRUE) {
    echo "View vw_UserRoleDistribution created successfully.<br>";
} else {
    echo "Error creating vw_UserRoleDistribution: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_top_accessed_researches) === TRUE) {
    echo "View vw_TopAccessedResearches created successfully.<br>";
} else {
    echo "Error creating vw_TopAccessedResearches: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_top_searched_keywords) === TRUE) {
    echo "View vw_TopSearchedKeywords created successfully.<br>";
} else {
    echo "Error creating vw_TopSearchedKeywords: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_research_count_per_program) === TRUE) {
    echo "View vw_ResearchCountPerProgram created successfully.<br>";
} else {
    echo "Error creating vw_ResearchCountPerProgram: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_research_count_per_year) === TRUE) {
    echo "View vw_ResearchCountPerYear created successfully.<br>";
} else {
    echo "Error creating vw_ResearchCountPerYear: " . $conn->error . "<br>";
}

if ($conn->query($sql_add_research_entry) === TRUE) {
    echo "Stored procedure AddResearchEntry created successfully.<br>";
} else {
    echo "Error creating AddResearchEntry: " . $conn->error . "<br>";
}

if ($conn->query($sql_add_researcher) === TRUE) {
    echo "Stored procedure AddResearcher created successfully.<br>";
} else {
    echo "Error creating AddResearcher: " . $conn->error . "<br>";
}

if ($conn->query($sql_add_keyword) === TRUE) {
    echo "Stored procedure AddKeyword created successfully.<br>";
} else {
    echo "Error creating AddKeyword: " . $conn->error . "<br>";
}

if ($conn->query($sql_add_keyword_to_research) === TRUE) {
    echo "Stored procedure AddKeywordToResearch created successfully.<br>";
} else {
    echo "Error creating AddKeywordToResearch: " . $conn->error . "<br>";
}

if ($conn->query($sql_assign_panelist) === TRUE) {
    echo "Stored procedure AssignPanelist created successfully.<br>";
} else {
    echo "Error creating AssignPanelist: " . $conn->error . "<br>";
}

if ($conn->query($sql_update_research_entry) === TRUE) {
    echo "Stored procedure UpdateResearchEntry created successfully.<br>";
} else {
    echo "Error creating UpdateResearchEntry: " . $conn->error . "<br>";
}

if ($conn->query($sql_update_researcher) === TRUE) {
    echo "Stored procedure UpdateResearcher created successfully.<br>";
} else {
    echo "Error creating UpdateResearcher: " . $conn->error . "<br>";
}

if ($conn->query($sql_delete_researcher) === TRUE) {
    echo "Stored procedure DeleteResearcher created successfully.<br>";
} else {
    echo "Error creating DeleteResearcher: " . $conn->error . "<br>";
}

if ($conn->query($sql_remove_keyword_from_research) === TRUE) {
    echo "Stored procedure RemoveKeywordFromResearch created successfully.<br>";
} else {
    echo "Error creating RemoveKeywordFromResearch: " . $conn->error . "<br>";
}

if ($conn->query($sql_delete_keyword) === TRUE) {
    echo "Stored procedure DeleteKeyword created successfully.<br>";
} else {
    echo "Error creating DeleteKeyword: " . $conn->error . "<br>";
}

if ($conn->query($sql_remove_panelist) === TRUE) {
    echo "Stored procedure RemovePanelist created successfully.<br>";
} else {
    echo "Error creating RemovePanelist: " . $conn->error . "<br>";
}

if ($conn->query($sql_get_all_faculty_productivity) === TRUE) {
    echo "Stored procedure GetAllFacultyProductivity created successfully.<br>";
} else {
    echo "Error creating GetAllFacultyProductivity: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_top_advisers) === TRUE) {
    echo "View vw_TopAdvisers created successfully.<br>";
} else {
    echo "Error creating vw_TopAdvisers: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_top_panelists) === TRUE) {
    echo "View vw_TopPanelists created successfully.<br>";
} else {
    echo "Error creating vw_TopPanelists: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_research_full_info) === TRUE) {
    echo "View vw_ResearchFullInfo created successfully.<br>";
} else {
    echo "Error creating vw_ResearchFullInfo: " . $conn->error . "<br>";
}

if ($conn->query($sql_get_faculty_productivity_report) === TRUE) {
    echo "Stored procedure GetFacultyProductivityReport created successfully.<br>";
} else {
    echo "Error creating GetFacultyProductivityReport: " . $conn->error . "<br>";
}

if ($conn->query($sql_view_recent_user_registrations) === TRUE) {
    echo "View vw_RecentUserRegistrations created successfully.<br>";
} else {
    echo "Error creating vw_RecentUserRegistrations: " . $conn->error . "<br>";
}

// Close the connection
$conn->close();
?>