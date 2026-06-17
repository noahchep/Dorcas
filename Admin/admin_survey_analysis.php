<?php
/* ==========================
    DATABASE CONNECTION FIX
   ========================== */
if (!isset($conn)) {
    $db_path = $_SERVER['DOCUMENT_ROOT'] . '/Portal-Assistant-AI/db_connect.php';
    if (file_exists($db_path)) {
        include_once($db_path);
    } else {
        $conn = mysqli_connect("localhost", "root", "", "Portal-Asisstant-AI");
    }
}

if (!$conn) {
    die("<div style='color:red; padding:20px;'>Database connection failed.</div>");
}

/* ==========================
    HANDLE DOWNLOAD REQUESTS
   ========================== */
if (isset($_GET['download']) && isset($_GET['format'])) {
    $format = $_GET['format'];
    
    // Fetch all data for download
    $total_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM survey_responses");
    $total_count = mysqli_fetch_assoc($total_res)['total'] ?? 0;
    
    $challenges_q = mysqli_query($conn, "SELECT challenge_type, COUNT(*) as count FROM survey_responses GROUP BY challenge_type");
    $challenges = [];
    while($row = mysqli_fetch_assoc($challenges_q)) {
        $challenges[] = $row;
    }
    
    $accuracy_q = mysqli_query($conn, "SELECT chatbot_help, COUNT(*) as count FROM survey_responses GROUP BY chatbot_help");
    $accuracy_data = [];
    while($row = mysqli_fetch_assoc($accuracy_q)) {
        $accuracy_data[] = $row;
    }
    
    $avg_q = mysqli_query($conn, "SELECT AVG(ease_rating) as average FROM survey_responses");
    $avg_score = ($total_count > 0) ? round(mysqli_fetch_assoc($avg_q)['average'], 2) : 0;
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="analytics_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Responses', $total_count]);
        fputcsv($output, ['Average Satisfaction', $avg_score . '/5']);
        fputcsv($output, ['']);
        fputcsv($output, ['Challenge Type', 'Count', 'Percentage']);
        foreach ($challenges as $ch) {
            fputcsv($output, [$ch['challenge_type'], $ch['count'], round(($ch['count'] / $total_count) * 100, 1) . '%']);
        }
        fputcsv($output, ['']);
        fputcsv($output, ['AI Accuracy Rating', 'Count', 'Percentage']);
        foreach ($accuracy_data as $ad) {
            fputcsv($output, [$ad['chatbot_help'], $ad['count'], round(($ad['count'] / $total_count) * 100, 1) . '%']);
        }
        fclose($output);
        exit;
    }
}

/* ==========================
    DATA ANALYSIS LOGIC
   ========================== */

// 1. Fetch Total Responses
$total_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM survey_responses");
$total_count = mysqli_fetch_assoc($total_res)['total'] ?? 0;

// 2. Challenge Analysis
$challenges_q = mysqli_query($conn, "SELECT challenge_type, COUNT(*) as count FROM survey_responses GROUP BY challenge_type");
$challenge_labels = [];
$challenge_counts = [];
while($row = mysqli_fetch_assoc($challenges_q)) {
    $challenge_labels[] = $row['challenge_type'];
    $challenge_counts[] = $row['count'];
}

// 3. AI Performance Data (WEIGHTED ACCURACY)
$accuracy_q = mysqli_query($conn, "SELECT chatbot_help, COUNT(*) as count FROM survey_responses GROUP BY chatbot_help");
$accuracy_labels = [];
$accuracy_counts = [];
$total_weighted_score = 0;
while($row = mysqli_fetch_assoc($accuracy_q)) {
    $label = $row['chatbot_help'];
    $count = $row['count'];
    $accuracy_labels[] = $label;
    $accuracy_counts[] = $count;
    
    if ($label == 'Highly Accurate') { $weight = 1.0; }
    elseif ($label == 'Helpful') { $weight = 0.75; }
    elseif ($label == 'Average') { $weight = 0.50; }
    elseif ($label == 'Inaccurate') { $weight = 0.25; }
    else { $weight = 0.0; }
    $total_weighted_score += ($weight * $count);
}

$final_accuracy = ($total_count > 0) ? round(($total_weighted_score / $total_count) * 100) : 0;

// 4. Mean Satisfaction Calculation
$avg_q = mysqli_query($conn, "SELECT AVG(ease_rating) as average FROM survey_responses");
$avg_score = ($total_count > 0) ? round(mysqli_fetch_assoc($avg_q)['average'], 2) : 0;

// 5. Satisfaction Distribution
$satisfaction_q = mysqli_query($conn, "SELECT ease_rating, COUNT(*) as count FROM survey_responses GROUP BY ease_rating ORDER BY ease_rating");
$satisfaction_ratings = [];
$satisfaction_counts = [];
while($row = mysqli_fetch_assoc($satisfaction_q)) {
    $satisfaction_ratings[] = $row['ease_rating'] . ' Stars';
    $satisfaction_counts[] = $row['count'];
}

/* ==========================
    RESEARCH CONCLUSION GENERATOR
   ========================== */
$conclusion = "No data collected yet.";
if ($total_count > 0) {
    if ($avg_score >= 4) {
        $conclusion = "The <strong>Portal-Assistant-AI</strong> demonstrates high effectiveness. With an average rating of $avg_score/5, the system successfully provides autonomous academic guidance to students.";
    } elseif ($avg_score >= 2.5) {
        $conclusion = "The <strong>Portal-Assistant-AI</strong> shows moderate success. While students find the UI functional, the AI model requires further training on specific academic parameters to reach peak accuracy.";
    } else {
        $conclusion = "Current feedback for <strong>Portal-Assistant-AI</strong> indicates significant technical gaps. Future iterations should focus on improving NLP response accuracy and system integration.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal-Assistant-AI | Analytics Dashboard</title>
    
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- html2canvas for PNG download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <!-- SheetJS for Excel export -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            padding: 20px;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header Section */
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-title h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .header-title p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .download-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-csv {
            background: #10b981;
            color: white;
        }
        
        .btn-csv:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-png {
            background: #ef4444;
            color: white;
        }
        
        .btn-png:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .btn-excel {
            background: #3b82f6;
            color: white;
        }
        
        .btn-excel:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h4 {
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #4f46e5;
            margin: 10px 0;
        }
        
        .stat-card .trend {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .chart-card h3 {
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            font-size: 1.1rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 15px;
        }
        
        canvas {
            max-height: 300px;
            width: 100%;
        }
        
        /* Table Styles */
        .data-table {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        .data-table h3 {
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        
        /* Conclusion Box */
        .conclusion-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-left: 5px solid #0ea5e9;
            padding: 20px 25px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .conclusion-box h4 {
            color: #0369a1;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .conclusion-box p {
            color: #0c4a6e;
            line-height: 1.6;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                text-align: center;
            }
            
            .stat-card .value {
                font-size: 1.8rem;
            }
        }
        
        /* Print styles for PDF */
        @media print {
            .download-buttons {
                display: none;
            }
            body {
                background: white;
                padding: 0;
            }
            .stat-card {
                break-inside: avoid;
            }
            .chart-card {
                break-inside: avoid;
            }
        }
        
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
            font-size: 0.8rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container" id="dashboard-content">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-title">
                <h1>📊 Portal-Assistant-AI Analytics Dashboard</h1>
                <p>Evaluation of Chatbot Effectiveness | Student Feedback Analysis</p>
            </div>
            <div class="download-buttons">
                <button class="btn btn-csv" onclick="downloadCSV()">
                    📥 Download CSV Report
                </button>
                <button class="btn btn-excel" onclick="downloadExcel()">
                    📊 Download Excel Report
                </button>
                <button class="btn btn-png" onclick="downloadPNG()">
                    📸 Download as PNG
                </button>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>📋 Total Responses</h4>
                <div class="value"><?php echo $total_count; ?></div>
                <div class="trend">Sample Size (N)</div>
            </div>
            <div class="stat-card">
                <h4>⭐ Mean Satisfaction</h4>
                <div class="value"><?php echo $avg_score; ?> / 5</div>
                <div class="trend">Average ease of use rating</div>
            </div>
            <div class="stat-card">
                <h4>🎯 AI Accuracy Rate</h4>
                <div class="value"><?php echo $final_accuracy; ?>%</div>
                <div class="trend">Weighted accuracy score</div>
            </div>
        </div>
        
        <!-- Charts Grid -->
        <div class="charts-grid">
            <!-- Pie Chart: Challenges -->
            <div class="chart-card">
                <h3>📈 System Challenges Distribution</h3>
                <div class="chart-container">
                    <canvas id="challengesPieChart"></canvas>
                </div>
                <div class="chart-legend" id="challenges-legend"></div>
            </div>
            
            <!-- Bar Chart: AI Performance -->
            <div class="chart-card">
                <h3>🤖 AI Performance Accuracy</h3>
                <div class="chart-container">
                    <canvas id="accuracyBarChart"></canvas>
                </div>
            </div>
            
            <!-- Bar Chart: Satisfaction Ratings -->
            <div class="chart-card">
                <h3>⭐ Student Satisfaction Distribution</h3>
                <div class="chart-container">
                    <canvas id="satisfactionBarChart"></canvas>
                </div>
            </div>
            
            <!-- Donut Chart: AI Performance Distribution -->
            <div class="chart-card">
                <h3>🎯 AI Performance Breakdown</h3>
                <div class="chart-container">
                    <canvas id="accuracyDonutChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Data Tables -->
        <div class="data-table">
            <h3>📋 Detailed Survey Data</h3>
            <table>
                <thead>
                    <tr>
                        <th>Challenge Category</th>
                        <th>Frequency (f)</th>
                        <th>Percentage (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($total_count > 0 && !empty($challenge_labels)) {
                        for($i = 0; $i < count($challenge_labels); $i++): 
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($challenge_labels[$i]); ?></td>
                        <td><?php echo $challenge_counts[$i]; ?></td>
                        <td><?php echo round(($challenge_counts[$i] / $total_count) * 100, 1); ?>%</td>
                    </tr>
                    <?php 
                        endfor;
                    } else {
                        echo "<tr><td colspan='3' style='text-align:center;'>No survey data available yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="data-table">
            <h3>🤖 AI Accuracy Ratings</h3>
            <table>
                <thead>
                    <tr>
                        <th>Rating Category</th>
                        <th>Frequency (f)</th>
                        <th>Percentage (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($total_count > 0 && !empty($accuracy_labels)) {
                        for($i = 0; $i < count($accuracy_labels); $i++): 
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($accuracy_labels[$i]); ?></td>
                        <td><?php echo $accuracy_counts[$i]; ?></td>
                        <td><?php echo round(($accuracy_counts[$i] / $total_count) * 100, 1); ?>%</td>
                    </tr>
                    <?php 
                        endfor;
                    } else {
                        echo "<tr><td colspan='3' style='text-align:center;'>No accuracy data available yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Conclusion -->
        <div class="conclusion-box">
            <h4>🔬 Automated Research Conclusion</h4>
            <p><?php echo $conclusion; ?></p>
        </div>
        
    </div>
    
    <script>
        // Colors for charts
        const colors = [
            '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', 
            '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1'
        ];
        
        // Data from PHP
        const challengeLabels = <?php echo json_encode($challenge_labels); ?>;
        const challengeCounts = <?php echo json_encode($challenge_counts); ?>;
        const accuracyLabels = <?php echo json_encode($accuracy_labels); ?>;
        const accuracyCounts = <?php echo json_encode($accuracy_counts); ?>;
        const satisfactionRatings = <?php echo json_encode($satisfaction_ratings); ?>;
        const satisfactionCounts = <?php echo json_encode($satisfaction_counts); ?>;
        
        // 1. Challenges Pie Chart
        if (challengeLabels.length > 0) {
            const ctx1 = document.getElementById('challengesPieChart').getContext('2d');
            new Chart(ctx1, {
                type: 'pie',
                data: {
                    labels: challengeLabels,
                    datasets: [{
                        data: challengeCounts,
                        backgroundColor: colors.slice(0, challengeLabels.length),
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = challengeCounts.reduce((a,b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.raw} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // 2. Accuracy Bar Chart
        if (accuracyLabels.length > 0) {
            const ctx2 = document.getElementById('accuracyBarChart').getContext('2d');
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: accuracyLabels,
                    datasets: [{
                        label: 'Number of Responses',
                        data: accuracyCounts,
                        backgroundColor: '#4f46e5',
                        borderRadius: 8,
                        barPercentage: 0.7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = accuracyCounts.reduce((a,b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${context.raw} responses (${percentage}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Students' },
                            grid: { color: '#e2e8f0' }
                        },
                        x: {
                            title: { display: true, text: 'Accuracy Rating' }
                        }
                    }
                }
            });
        }
        
        // 3. Satisfaction Bar Chart
        if (satisfactionRatings.length > 0) {
            const ctx3 = document.getElementById('satisfactionBarChart').getContext('2d');
            new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: satisfactionRatings,
                    datasets: [{
                        label: 'Number of Students',
                        data: satisfactionCounts,
                        backgroundColor: '#10b981',
                        borderRadius: 8,
                        barPercentage: 0.7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Students' },
                            grid: { color: '#e2e8f0' }
                        },
                        x: {
                            title: { display: true, text: 'Ease of Use Rating' }
                        }
                    }
                }
            });
        }
        
        // 4. Accuracy Donut Chart
        if (accuracyLabels.length > 0) {
            const ctx4 = document.getElementById('accuracyDonutChart').getContext('2d');
            new Chart(ctx4, {
                type: 'doughnut',
                data: {
                    labels: accuracyLabels,
                    datasets: [{
                        data: accuracyCounts,
                        backgroundColor: ['#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4'],
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = accuracyCounts.reduce((a,b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.raw} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Download Functions
        function downloadCSV() {
            window.location.href = '?download=1&format=csv';
        }
        
        function downloadExcel() {
            // Create workbook from table data
            const tables = document.querySelectorAll('.data-table table');
            const workbook = XLSX.utils.book_new();
            
            tables.forEach((table, index) => {
                const worksheet = XLSX.utils.table_to_sheet(table);
                const sheetName = index === 0 ? 'Challenges' : 'AI Accuracy';
                XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
            });
            
            // Add stats sheet
            const statsData = [
                ['Metric', 'Value'],
                ['Total Responses', '<?php echo $total_count; ?>'],
                ['Mean Satisfaction (out of 5)', '<?php echo $avg_score; ?>'],
                ['AI Accuracy Rate', '<?php echo $final_accuracy; ?>%'],
                [''],
                ['Report Generated', new Date().toLocaleString()]
            ];
            const statsSheet = XLSX.utils.aoa_to_sheet(statsData);
            XLSX.utils.book_append_sheet(workbook, statsSheet, 'Summary');
            
            XLSX.writeFile(workbook, `analytics_report_${new Date().toISOString().slice(0,19)}.xlsx`);
        }
        
        async function downloadPNG() {
            const element = document.getElementById('dashboard-content');
            const originalOverflow = element.style.overflow;
            const originalHeight = element.style.height;
            
            element.style.overflow = 'visible';
            element.style.height = 'auto';
            
            try {
                const canvas = await html2canvas(element, {
                    scale: 2,
                    backgroundColor: '#f1f5f9',
                    logging: false,
                    useCORS: true
                });
                
                const link = document.createElement('a');
                link.download = `dashboard_${new Date().toISOString().slice(0,19)}.png`;
                link.href = canvas.toDataURL();
                link.click();
            } catch (error) {
                console.error('Error generating PNG:', error);
                alert('Error generating screenshot. Please try again.');
            } finally {
                element.style.overflow = originalOverflow;
                element.style.height = originalHeight;
            }
        }
        
        // Print functionality (for PDF via browser)
        function printDashboard() {
            window.print();
        }
    </script>
    
    <!-- Optional: Add a print button to the header -->
    <div style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="background: #475569; color: white; border: none; padding: 12px 20px; border-radius: 50px; cursor: pointer; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            🖨️ Print / Save as PDF
        </button>
    </div>
    
</body>
</html>