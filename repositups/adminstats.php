<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}

// Check if user is Administrator
if ($_SESSION['user_role'] !== 'Administrator') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Research Repository</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background-color: #f8f9fc;
                margin: 0;
                padding: 0;
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .access-denied-container {
                background: white;
                padding: 3rem;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
                text-align: center;
                max-width: 400px;
                width: 90%;
            }

            .warning-icon {
                width: 64px;
                height: 64px;
                margin-bottom: 1.5rem;
            }

            .warning-icon path {
                fill: #ff9800;
            }

            h1 {
                color: #2D0053;
                font-size: 2rem;
                margin: 0 0 0.5rem;
                font-weight: 600;
            }

            p {
                color: #666;
                font-size: 1rem;
                margin: 0 0 2rem;
            }

            .return-btn {
                background: #FF6600;
                color: white;
                text-decoration: none;
                padding: 0.8rem 2rem;
                border-radius: 6px;
                font-weight: 500;
                display: inline-block;
                transition: background-color 0.2s;
            }

            .return-btn:hover {
                background: #e65c00;
            }
        </style>
    </head>
    <body>
        <div class="access-denied-container">
            <svg class="warning-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path fill="#ff9800" d="M12 2L1 21h22L12 2zm0 3.99L19.53 19H4.47L12 5.99zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
            </svg>
            <h1>Access Denied</h1>
            <p>Administrator only.</p>
            <a href="welcome.php" class="return-btn">Return to Homepage</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Helper function to fetch all rows from a view
function fetchAllFromView($pdo, $viewName) {
    $stmt = $pdo->query("SELECT * FROM $viewName");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch statistics
$userRoles = fetchAllFromView($pdo, 'vw_UserRoleDistribution');
$topResearch = fetchAllFromView($pdo, 'vw_TopAccessedResearches');
$topKeywords = fetchAllFromView($pdo, 'vw_TopSearchedKeywords');
$perYear = fetchAllFromView($pdo, 'vw_ResearchCountPerYear');
$perProgram = fetchAllFromView($pdo, 'vw_ResearchCountPerProgram');
$recentRegistrations = fetchAllFromView($pdo, 'vw_RecentUserRegistrations');

// Prepare data for JS
$userRolesLabels = array_column($userRoles, 'role');
$userRolesData = array_map('intval', array_column($userRoles, 'totalUsers'));
$topResearchLabels = array_column($topResearch, 'researchTitle');
$topResearchData = array_map('intval', array_column($topResearch, 'accessCount'));

$stmt = $pdo->prepare("SELECT keywordName FROM keyword WHERE keywordID = ?");
$topKeywordsLabels = array_map(function($row) use ($pdo, $stmt) {
    $stmt->execute([$row['keywordID']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['keywordName'] : 'Unknown Keyword';
}, $topKeywords);

$topKeywordsData = array_map('intval', array_column($topKeywords, 'searchCount'));
$perYearLabels = array_column($perYear, 'publishedYear');
$perYearData = array_map('intval', array_column($perYear, 'researchCount'));
$perProgramLabels = array_column($perProgram, 'program');
$perProgramData = array_map('intval', array_column($perProgram, 'researchCount'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Statistics - Research Repository</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: rgba(35, 1, 68, 0.03);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
            font-size: clamp(1.1rem, 2vw, 2rem);
        }

        .home-btn {
            font-size: clamp(1.5rem, 2.5vw, 2.5rem);
            padding: clamp(0.8rem, 1.5vw, 1.5rem);
            position: absolute;
            left: 8px;
        }

        .container-fluid.flex-grow-1 {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        /* Home button styles */
        .home-btn {
            font-size: clamp(1.5rem, 2.5vw, 2.5rem);
            padding: clamp(0.8rem, 1.5vw, 1.5rem);
            transition: background-color 0.3s ease, color 0.3s ease;
            background: transparent;
            color: inherit;
        }

        .home-btn i {
            display: block;
            line-height: 1;
            color: white;
            transition: color 0.3s ease;
        }

        .home-btn:hover {
            background-color: transparent !important;
        }

        .home-btn:hover i {
            color: #FF6600 !important;
        }

        /* LOGO styles */
        .navbar-logo {
            height: clamp(32px, 6vw, 80px);
            width: auto;
        }

        html {
            font-size: clamp(14px, 2vw, 18px); /* Responsive base font size */
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            color: #222;
            font-size: 1rem;
        }
        .container {
            max-width: 90vw;
            margin: 2.5vw auto;
            background: #fff;
            border-radius: 1.125em;
            box-shadow: 0 0.25em 1.5em rgba(34,0,68,0.10);
            padding: 2.5em 2vw 3em 2vw;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.2em;
        }
        @media (min-width: 700px) {
            .container { max-width: 95vw; }
        }
        @media (min-width: 1100px) {
            .container { max-width: 1200px; }
            .dashboard-grid {
                grid-template-columns: 1.2fr 1fr;
                gap: 2.8em 2.2em;
            }
            .dashboard-grid .section.user-role,
            .dashboard-grid .section.registrations {
                grid-row: 1;
            }
            .dashboard-grid .section.top-research,
            .dashboard-grid .section.top-keywords {
                grid-row: 2;
            }
        }
        @media (max-width: 1099px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        .dashboard-grid .section {
            background: #fff;
            border-radius: 0.875em;
            box-shadow: 0 0.125em 0.75em rgba(34,0,68,0.07);
            padding: 2em 1.4em 1.75em 1.4em;
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
        }
        h1 {
            color: #2e003e;
            font-size: clamp(1.5rem, 5vw, 2.8rem);
            text-align: center;
            margin-bottom: 2em;
            font-weight: 700;
        }
        h2 {
            color: #FF6600;
            font-size: clamp(1.05rem, 3vw, 1.4rem);
            margin-top: 2.5em;
            margin-bottom: 1em;
            font-weight: 600;
        }
        .chart-title {
            text-align: center;
            font-weight: 600;
            margin-bottom: 1.1em;
            color: #2e003e;
            font-size: clamp(1rem, 2vw, 1.4rem);
            letter-spacing: 0.01em;
        }
        .chart-container {
            background: #f6f6fb;
            border-radius: 0.625em;
            padding: 1.5em 1em 1.5em 1em;
            margin-bottom: 0;
            position: relative;
            height: 350px;
            min-height: 300px;
            box-shadow: 0 0.125em 0.5em rgba(34,0,68,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-container canvas {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
        }

        /* Specific chart container heights for different chart types */
        .section.user-role .chart-container,
        .section.registrations .chart-container {
            height: 300px;
        }

        .section.top-research .chart-container,
        .section.top-keywords .chart-container {
            height: 350px;
        }

        .section:has(#perYearChart) .chart-container,
        .section:has(#perProgramChart) .chart-container {
            height: 400px;
        }

        @media (max-width: 700px) {
            .chart-container {
                height: 280px;
                min-height: 250px;
                padding: 1em 0.5em;
            }
            
            .section.user-role .chart-container,
            .section.registrations .chart-container {
                height: 250px;
            }
            
            .section.top-research .chart-container,
            .section.top-keywords .chart-container {
                height: 300px;
            }
            
            .section:has(#perYearChart) .chart-container,
            .section:has(#perProgramChart) .chart-container {
                height: 320px;
            }
        }
        .stat-number {
            font-size: clamp(2rem, 8vw, 4.2rem);
            color: #43A047;
            text-align: center;
            font-weight: 700;
            margin: 0 0 0.7em 0;
            letter-spacing: 0.01em;
        }
        .stat-label {
            text-align: center;
            font-size: clamp(1rem, 2vw, 1.15rem);
            font-weight: 600;
            color: #2e003e;
            margin-bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fafaff;
            font-size: clamp(0.92rem, 1.5vw, 1.08rem);
            margin-bottom: 1.2em;
            table-layout: auto;
        }
        
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1.5em;
            border-radius: 0.5em;
            box-shadow: 0 0.125em 0.5em rgba(34,0,68,0.06);
        }
        
        th, td {
            padding: clamp(0.6em, 2vw, 1em) clamp(0.75em, 2vw, 1.25em);
            border: 1.5px solid #e0e0e0;
            text-align: center;
            vertical-align: middle;
            white-space: normal;
            word-wrap: break-word;
        }
        
        th {
            background: #2e003e;
            color: #fff;
            font-size: 1.08em;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:nth-child(even) {
            background: #f3f3f9;
        }
        tr:nth-child(odd) {
            background: #fafaff;
        }
        details summary {
            cursor: pointer;
            font-weight: 500;
            color: #2e003e;
            margin-bottom: 0.5em;
            font-size: clamp(1rem, 1.5vw, 1.1rem);
        }
        @media (max-width: 800px) {
            .container { max-width: 98vw; padding: 0.7em 2vw; }
            th, td { 
                padding: 0.6em 0.75em;
                font-size: clamp(0.85rem, 1.2vw, 1rem);
            }
        }
        @media (max-width: 700px) {
            .dashboard-grid { width: 100%; }
            .container { padding: 0.5em 1vw; }
            h1 { font-size: clamp(1.1rem, 5vw, 1.7rem); }
            h2 { font-size: clamp(0.95rem, 3vw, 1.1rem); }
            .chart-title { font-size: clamp(0.95rem, 2vw, 1.1rem); }
            .stat-label { font-size: clamp(0.9rem, 2vw, 1rem); }
            .stat-number { font-size: clamp(1.5rem, 8vw, 2.5rem); }
        }
        /* Responsive icon size for any icons in headings */
        h1 i, h2 i, .chart-title i {
            font-size: clamp(1.2em, 4vw, 2.5em);
            vertical-align: middle;
        }
        @media (max-width: 700px) {
            h1 i, h2 i, .chart-title i {
                font-size: clamp(1em, 6vw, 1.5em);
            }
        }    
    
    </style>
</head>
<body>    
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid position-relative justify-content-center">
                <!-- Home button (top left) -->
                <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:1100;padding-left:16px;background:transparent;">
                    <i class="fas fa-home"></i>
                </a>
                <!-- Centered brand -->
                <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                    <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                    <span>Repositups</span>
                </a>
            </div>
        </nav>
    </header>

    <div class="container mt-5">
        <h1>Admin Statistics</h1>

        <div class="dashboard-grid">
            <div class="section user-role">
                <div class="chart-title">User Role Distribution</div>
                <div class="chart-container">
                    <canvas id="userRoleChart"></canvas>
                </div>
            </div>
            <div class="section registrations" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <div class="stat-number">
                    <?= isset($recentRegistrations[0]['recentRegistrations']) ? intval($recentRegistrations[0]['recentRegistrations']) : 0 ?>
                </div>
                <div class="stat-label">Recent User Registrations<br>(Last 30 Days)</div>
            </div>
            <div class="section top-research">
                <div class="chart-title">Top 5 Most Accessed Research</div>
                <div class="chart-container">
                    <canvas id="topResearchChart"></canvas>
                </div>
            </div>
            <div class="section top-keywords">
                <div class="chart-title">Top 5 Most Searched Keywords</div>
                <div class="chart-container">
                    <canvas id="topKeywordsChart"></canvas>
                </div>
            </div>
            <div class="section">
                <div class="chart-title">Research Counts Per Year</div>
                <div class="chart-container">
                    <canvas id="perYearChart"></canvas>
                </div>
            </div>
            <div class="section">
                <div class="chart-title">Research Counts Per Program</div>
                <div class="chart-container">
                    <canvas id="perProgramChart"></canvas>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Raw Data Tables</h2>
            <details>
                <summary>Show/Hide Tables</summary>
                <div>
                    <h3>User Role Distribution</h3>
                    <div class="table-responsive">
                        <table>
                            <tr><th>Role</th><th>Total Users</th></tr>
                            <?php foreach ($userRoles as $row): ?>
                                <tr><td><?= htmlspecialchars($row['role']) ?></td><td><?= htmlspecialchars($row['totalUsers']) ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <h3>Top 5 Most Accessed Research</h3>
                    <div class="table-responsive">
                        <table>
                            <tr><th>Research Title</th><th>Access Count</th></tr>
                            <?php foreach ($topResearch as $row): ?>
                                <tr><td><?= htmlspecialchars($row['researchTitle']) ?></td><td><?= htmlspecialchars($row['accessCount']) ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <h3>Top 5 Most Searched Keywords</h3>
                    <div class="table-responsive">
                        <table>
                            <tr><th>Keyword</th><th>Search Count</th></tr>
                            <?php foreach ($topKeywords as $row): ?>
                                <?php
                                $stmt->execute([$row['keywordID']]);
                                $keyword = $stmt->fetch(PDO::FETCH_ASSOC);
                                $keywordName = $keyword ? $keyword['keywordName'] : 'Unknown Keyword';
                                ?>
                                <tr><td><?= htmlspecialchars($keywordName) ?></td><td><?= htmlspecialchars($row['searchCount']) ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <h3>Research Counts Per Year</h3>
                    <div class="table-responsive">
                        <table>
                            <tr><th>Year</th><th>Research Count</th></tr>
                            <?php foreach ($perYear as $row): ?>
                                <tr><td><?= htmlspecialchars($row['publishedYear']) ?></td><td><?= htmlspecialchars($row['researchCount']) ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <h3>Research Counts Per Program</h3>
                    <div class="table-responsive">
                        <table>
                            <tr><th>Program</th><th>Research Count</th></tr>
                            <?php foreach ($perProgram as $row): ?>
                                <tr><td><?= htmlspecialchars($row['program']) ?></td><td><?= htmlspecialchars($row['researchCount']) ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <h3>Recent User Registrations (Last 30 Days)</h3>
                    <div class="table-responsive">
                        <table>
                            <tr><th>Recent Registrations</th></tr>
                            <tr><td><?= isset($recentRegistrations[0]['recentRegistrations']) ? intval($recentRegistrations[0]['recentRegistrations']) : 0 ?></td></tr>
                        </table>
                    </div>
                </div>
            </details>
        </div>
    <script>
    const userRolesLabels = <?= json_encode($userRolesLabels) ?>;
    const userRolesData = <?= json_encode($userRolesData) ?>;
    const topResearchLabels = <?= json_encode($topResearchLabels) ?>;
    const topResearchData = <?= json_encode($topResearchData) ?>;
    const topKeywordsLabels = <?= json_encode($topKeywordsLabels) ?>;
    const topKeywordsData = <?= json_encode($topKeywordsData) ?>;
    const perYearLabels = <?= json_encode($perYearLabels) ?>;
    const perYearData = <?= json_encode($perYearData) ?>;
    const perProgramLabels = <?= json_encode($perProgramLabels) ?>;
    const perProgramData = <?= json_encode($perProgramData) ?>;

    const chartColors = ['#FF6600', '#2e003e', '#FFB300', '#6A0572', '#009688'];

    // Add this function before creating any charts
    function isMobileView() {
        return window.innerWidth <= 768;
    }

    // Enhanced Chart.js configuration with better resolution and positioning
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding = 15;

    // User Role Distribution Chart
    new Chart(document.getElementById('userRoleChart'), {
        type: 'doughnut',
        data: {
            labels: userRolesLabels,
            datasets: [{
                data: userRolesData,
                backgroundColor: chartColors.slice(0, userRolesLabels.length),
                borderWidth: 2,
                borderColor: '#fff',
                hoverBorderWidth: 3,
                hoverBorderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: 2,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        font: { size: 12, weight: '500' },
                        color: '#2e003e'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(46, 0, 62, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#FF6600',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true
                }
            },
            layout: {
                padding: 10
            }
        }
    });

    // Top Research Chart
    new Chart(document.getElementById('topResearchChart'), {
        type: 'bar',
        data: {
            labels: topResearchLabels.map(label => 
                label.length > 30 ? label.substring(0, 30) + '...' : label
            ),
            datasets: [{
                label: 'Access Count',
                data: topResearchData,
                backgroundColor: chartColors.slice(0, topResearchLabels.length),
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: chartColors.slice(0, topResearchLabels.length).map(color => color + 'CC')
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: 2,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(46, 0, 62, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#FF6600',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        title: function(context) {
                            return topResearchLabels[context[0].dataIndex];
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(46, 0, 62, 0.1)' },
                    ticks: { color: '#2e003e', font: { size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#2e003e',
                        font: { size: 10 },
                        maxRotation: 45,
                        minRotation: 0,
                        display: !isMobileView() // Hide labels on mobile
                    }
                }
            },
            layout: { padding: 10 }
        }
    });

    // Top Keywords Chart
    new Chart(document.getElementById('topKeywordsChart'), {
        type: 'bar',
        data: {
            labels: topKeywordsLabels,
            datasets: [{
                label: 'Search Count',
                data: topKeywordsData,
                backgroundColor: chartColors.slice(0, topKeywordsLabels.length),
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: chartColors.slice(0, topKeywordsLabels.length).map(color => color + 'CC')
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: 2,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(46, 0, 62, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#FF6600',
                    borderWidth: 1,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(46, 0, 62, 0.1)' },
                    ticks: { color: '#2e003e', font: { size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#2e003e',
                        font: { size: 11 },
                        display: !isMobileView() // Hide labels on mobile
                    }
                }
            },
            layout: { padding: 10 }
        }
    });

    // Research Per Year Chart
    new Chart(document.getElementById('perYearChart'), {
        type: 'line',
        data: {
            labels: perYearLabels,
            datasets: [{
                label: 'Research Count',
                data: perYearData,
                fill: true,
                backgroundColor: 'rgba(255,102,0,0.15)',
                borderColor: '#FF6600',
                borderWidth: 3,
                tension: 0.4,
                pointBackgroundColor: '#2e003e',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#FF6600',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: 2,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(46, 0, 62, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#FF6600',
                    borderWidth: 1,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(46, 0, 62, 0.1)' },
                    ticks: { color: '#2e003e', font: { size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#2e003e', font: { size: 11 } }
                }
            },
            layout: { padding: 10 }
        }
    });

    // Research Per Program Chart
    new Chart(document.getElementById('perProgramChart'), {
        type: 'bar',
        data: {
            labels: perProgramLabels.map(label => 
                label.length > 25 ? label.substring(0, 25) + '...' : label
            ),
            datasets: [{
                label: 'Research Count',
                data: perProgramData,
                backgroundColor: chartColors.slice(0, perProgramLabels.length),
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: chartColors.slice(0, perProgramLabels.length).map(color => color + 'CC')
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: 2,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(46, 0, 62, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#FF6600',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        title: function(context) {
                            return perProgramLabels[context[0].dataIndex];
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: 'rgba(46, 0, 62, 0.1)' },
                    ticks: { color: '#2e003e', font: { size: 11 } }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        color: '#2e003e',
                        font: { size: 10 },
                        display: !isMobileView() // Hide labels on mobile
                    }
                }
            },
            layout: { padding: 10 }
        }
    });
    // Add this at the end of your script
    window.addEventListener('resize', function() {
        // Force charts to update when window is resized
        Chart.instances.forEach(chart => chart.update());
    });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
