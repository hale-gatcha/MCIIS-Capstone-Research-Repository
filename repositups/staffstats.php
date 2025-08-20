<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is MCIIS Staff only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}
if ($_SESSION['user_role'] !== 'MCIIS Staff') {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: "Inter", sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .error-container {
                max-width: 500px;
                margin: 40px auto;
                text-align: center;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                border-top: 4px solid #220044;
            }
            .error-icon {
                font-size: 48px;
                color: #220044;
                margin-bottom: 20px;
            }
            h1 {
                color: #220044;
                margin: 0 0 15px 0;
                font-size: 24px;
            }
            p {
                color: #666;
                margin: 0 0 20px 0;
                font-size: 16px;
            }
            .back-link {
                display: inline-block;
                padding: 10px 20px;
                background-color: #FF6600;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s;
            }
            .back-link:hover {
                background-color: #e65c00;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Access Denied</h1>
            <p>MCIIS Staff only.</p>
            <a href="welcome.php" class="back-link">Return to Homepage</a>
        </div>
    </body>
    </html>';
    exit();
}

// Helper function to fetch all rows from a view
function fetchAllFromView($pdo, $viewName) {
    $stmt = $pdo->query("SELECT * FROM $viewName");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch statistics
$facultyStats = [];
$stmt = $pdo->query("SELECT f.facultyID, CONCAT(f.firstName, ' ', f.lastName) AS facultyName, 
    COUNT(DISTINCT r.researchID) AS advisedCount, 
    COUNT(DISTINCT p.researchID) AS paneledCount
FROM Faculty f
LEFT JOIN Research r ON r.researchAdviser = f.facultyID
LEFT JOIN Panel p ON p.facultyID = f.facultyID
GROUP BY f.facultyID, facultyName
ORDER BY facultyName");
$facultyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$facultyNames = array_column($facultyStats, 'facultyName');
$advisedCounts = array_map('intval', array_column($facultyStats, 'advisedCount'));
$paneledCounts = array_map('intval', array_column($facultyStats, 'paneledCount'));

$topAdvisers = fetchAllFromView($pdo, 'vw_TopAdvisers');
$topAdvisers = array_slice($topAdvisers, 0, 5); // Only top 5
$topAdviserNames = array_column($topAdvisers, 'adviserName');
$topAdvisedCounts = array_map('intval', array_column($topAdvisers, 'totalAdvised'));

$topPanelists = fetchAllFromView($pdo, 'vw_TopPanelists');
$topPanelistNames = array_column($topPanelists, 'panelistName');
$topPaneledCounts = array_map('intval', array_column($topPanelists, 'totalPaneled'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Statistics - Research Repository</title>
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

        .navbar-logo {
            height: clamp(32px, 6vw, 80px);
            width: auto;
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

        @media (min-width: 1100px) {
            .container { max-width: 1200px; }
            .dashboard-grid {
                grid-template-columns: 1fr 1fr;
                gap: 2.8em 2.2em;
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
            padding: 1.5em 1em;
            height: 350px;
            min-height: 300px;
            box-shadow: 0 0.125em 0.5em rgba(34,0,68,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fafaff;
            font-size: clamp(0.92rem, 1.5vw, 1.08rem);
            margin-bottom: 1.2em;
        }
        
        th, td {
            padding: clamp(0.6em, 2vw, 1em) clamp(0.75em, 2vw, 1.25em);
            border: 1.5px solid #e0e0e0;
            text-align: center;
            vertical-align: middle;
        }
        
        th {
            background: #2e003e;
            color: #fff;
            font-size: 1.08em;
            font-weight: 600;
        }

        tr:nth-child(even) { background: #f3f3f9; }
        tr:nth-child(odd) { background: #fafaff; }

        details summary {
            cursor: pointer;
            font-weight: 500;
            color: #2e003e;
            margin-bottom: 0.5em;
            font-size: clamp(1rem, 1.5vw, 1.1rem);
        }

        @media (max-width: 700px) {
            .container { 
                padding: 0.7em 2vw;
                max-width: 98vw;
            }
            .chart-container {
                height: 280px;
                min-height: 250px;
                padding: 1em 0.5em;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(35, 1, 68);">
            <div class="container-fluid position-relative justify-content-center">
                <a href="welcome.php" class="btn btn-outline-light me-2 show-hamburger border-0 home-btn" style="position:absolute;left:0;top:50%;transform:translateY(-50%);z-index:1100;padding-left:16px;background:transparent;">
                    <i class="fas fa-home"></i>
                </a>
                <a class="navbar-brand mx-auto d-flex align-items-center justify-content-center" href="#">
                    <img src="images/octopus-logo.png" alt="Logo" class="navbar-logo me-2">
                    <span>Repositups</span>
                </a>
            </div>
        </nav>
    </header>

    <div class="container">
        <h1><i class="fas fa-chalkboard-teacher"></i> Staff Statistics</h1>

        <div class="dashboard-grid">
            <!-- Existing chart sections but with updated styling -->
            <div class="section">
                <div class="chart-title">Research Advised Per Faculty</div>
                <div class="chart-container">
                    <canvas id="advisedChart"></canvas>
                </div>
            </div>
            <div class="section">
                <div class="chart-title">Research Paneled Per Faculty</div>
                <div class="chart-container">
                    <canvas id="paneledChart"></canvas>
                </div>
            </div>
            <div class="section">
                <div class="chart-title">Top 5 Advisers</div>
                <div class="chart-container">
                    <canvas id="topAdvisersChart"></canvas>
                </div>
            </div>
            <div class="section">
                <div class="chart-title">Top 5 Panelists</div>
                <div class="chart-container">
                    <canvas id="topPanelistsChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-section">
            <h2>Raw Data Tables</h2>
            <details>
                <summary style="cursor:pointer;font-weight:600;color:#2e003e;">Show/Hide Tables</summary>
                <div class="stat-section">
                    <h3>Faculty Research Advising and Paneling</h3>
                    <table>
                        <tr>
                            <th>Faculty Name</th>
                            <th>Research Advised</th>
                            <th>Research Paneled</th>
                        </tr>
                        <?php foreach ($facultyStats as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['facultyName']) ?></td>
                                <td><?= htmlspecialchars($row['advisedCount']) ?></td>
                                <td><?= htmlspecialchars($row['paneledCount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="stat-section">
                    <h3>Top 5 Advisers</h3>
                    <table>
                        <tr>
                            <th>Adviser Name</th>
                            <th>Total Advised</th>
                        </tr>
                        <?php foreach ($topAdvisers as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['adviserName']) ?></td>
                                <td><?= htmlspecialchars($row['totalAdvised']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="stat-section">
                    <h3>Top 5 Panelists</h3>
                    <table>
                        <tr>
                            <th>Panelist Name</th>
                            <th>Total Paneled</th>
                        </tr>
                        <?php foreach ($topPanelists as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['panelistName']) ?></td>
                                <td><?= htmlspecialchars($row['totalPaneled']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </details>
        </div>
    </div>

    <script>
        // Add this helper function at the start of your script section
        function isMobileView() {
            return window.innerWidth <= 768;
        }

        // Update Chart.js defaults
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 15;

        // Data from PHP
        const facultyNames = <?= json_encode($facultyNames) ?>;
        const advisedCounts = <?= json_encode($advisedCounts) ?>;
        const paneledCounts = <?= json_encode($paneledCounts) ?>;
        const topAdviserNames = <?= json_encode($topAdviserNames) ?>;
        const topAdvisedCounts = <?= json_encode($topAdvisedCounts) ?>;
        const topPanelistNames = <?= json_encode($topPanelistNames) ?>;
        const topPaneledCounts = <?= json_encode($topPaneledCounts) ?>;

        const chartColors = [
            '#FF6600', '#2e003e', '#FFB300', '#6A0572', '#009688', '#1976D2', '#43A047', '#E53935', '#8E24AA', '#FDD835',
            '#00B8D4', '#C51162', '#FFD600', '#00C853', '#D50000', '#AA00FF', '#FF6D00', '#2962FF', '#AEEA00', '#C6FF00'
        ];

        // Dynamically set chart height for mobile
        function getChartHeight() {
            if (window.innerWidth <= 600) return 120;
            if (window.innerWidth <= 900) return 180;
            return 220;
        }

        function setChartHeights() {
            document.querySelectorAll('canvas').forEach(canvas => {
                canvas.height = getChartHeight();
            });
        }
        window.addEventListener('resize', setChartHeights);
        window.addEventListener('DOMContentLoaded', setChartHeights);

        // Research Advised Per Faculty (Bar)
        new Chart(document.getElementById('advisedChart'), {
            type: 'bar',
            data: {
                labels: facultyNames.map(label => 
                    label.length > 30 ? label.substring(0, 30) + '...' : label
                ),
                datasets: [{
                    label: 'Research Advised',
                    data: advisedCounts,
                    backgroundColor: chartColors.slice(0, facultyNames.length),
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: chartColors.map(color => color + 'CC')
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
                                return facultyNames[context[0].dataIndex];
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
                            font: { size: 11 },
                            maxRotation: 45,
                            minRotation: 0,
                            display: !isMobileView() // Hide labels on mobile
                        }
                    }
                },
                layout: { padding: 10 }
            }
        });

        // Research Paneled Per Faculty (Bar)
        new Chart(document.getElementById('paneledChart'), {
            type: 'bar',
            data: {
                labels: facultyNames.map(label => 
                    label.length > 30 ? label.substring(0, 30) + '...' : label
                ),
                datasets: [{
                    label: 'Research Paneled',
                    data: paneledCounts,
                    backgroundColor: chartColors.slice(0, facultyNames.length),
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: chartColors.map(color => color + 'CC')
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
                                return facultyNames[context[0].dataIndex];
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
                            font: { size: 11 },
                            maxRotation: 45,
                            minRotation: 0,
                            display: !isMobileView() // Hide labels on mobile
                        }
                    }
                },
                layout: { padding: 10 }
            }
        });

        // Top 5 Advisers (Bar)
        new Chart(document.getElementById('topAdvisersChart'), {
            type: 'bar',
            data: {
                labels: topAdviserNames.map(label => 
                    label.length > 30 ? label.substring(0, 30) + '...' : label
                ),
                datasets: [{
                    label: 'Total Advised',
                    data: topAdvisedCounts,
                    backgroundColor: chartColors.slice(0, topAdviserNames.length),
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: chartColors.map(color => color + 'CC')
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
                                return topAdviserNames[context[0].dataIndex];
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
                            font: { size: 11 },
                            maxRotation: 45,
                            minRotation: 0,
                            display: !isMobileView() // Hide labels on mobile
                        }
                    }
                },
                layout: { padding: 10 }
            }
        });

        // Top 5 Panelists Chart (same style as above)
        new Chart(document.getElementById('topPanelistsChart'), {
            type: 'bar',
            data: {
                labels: topPanelistNames.map(label => 
                    label.length > 30 ? label.substring(0, 30) + '...' : label
                ),
                datasets: [{
                    label: 'Total Paneled',
                    data: topPaneledCounts,
                    backgroundColor: chartColors.slice(0, topPanelistNames.length),
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: chartColors.map(color => color + 'CC')
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
                                return topPanelistNames[context[0].dataIndex];
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
                            font: { size: 11 },
                            maxRotation: 45,
                            minRotation: 0,
                            display: !isMobileView() // Hide labels on mobile
                        }
                    }
                },
                layout: { padding: 10 }
            }
        });

        // Improve accessibility for details/summary on mobile
        const summary = document.querySelector('summary');
        if (summary) {
            summary.setAttribute('tabindex', '0');
            summary.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    summary.click();
                }
            });
        }

        // Add this at the end to handle window resizing
        window.addEventListener('resize', function() {
            Chart.instances.forEach(chart => chart.update());
        });
    </script>
</body>
</html>