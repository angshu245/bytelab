<?php
/**
 * View Results - Public Portal
 * ByteLab Olympiad Management System
 */

require_once '../config/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$error = '';
$result_data = null;

// Fetch student and result information
$stmt = prepare_query($conn, '
    SELECT 
        s.id,
        s.registration_no,
        s.name,
        s.class,
        s.school_name,
        s.roll_no,
        s.mobile,
        s.dob,
        ec.centre_name,
        r.marks,
        r.rank,
        r.status,
        r.created_at as result_date
    FROM students s
    LEFT JOIN exam_centres ec ON s.centre_id = ec.id
    LEFT JOIN results r ON s.id = r.student_id
    WHERE s.id = ?
');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $result_data = $result->fetch_assoc();
} else {
    $error = 'Student record not found!';
}
$stmt->close();

// Calculate percentile if marks are published
$percentile = null;
if ($result_data && $result_data['status'] === 'published' && $result_data['marks'] !== null) {
    $total_students_result = $conn->query("SELECT COUNT(*) as count FROM results WHERE marks IS NOT NULL");
    $total_students = $total_students_result->fetch_assoc()['count'];
    
    if ($total_students > 0) {
        $better_count_result = $conn->query("SELECT COUNT(*) as count FROM results WHERE marks > " . $result_data['marks']);
        $better_count = $better_count_result->fetch_assoc()['count'];
        $percentile = round(((($total_students - $better_count) / $total_students) * 100), 2);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .back-link { display: inline-block; margin-bottom: 20px; color: white; text-decoration: none; font-weight: 600; }
        .back-link:hover { opacity: 0.8; }
        .card { background: white; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .card-header h1 { font-size: 28px; margin-bottom: 5px; }
        .card-header p { font-size: 14px; opacity: 0.9; }
        .card-body { padding: 30px; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-error { background: #fee; color: #c33; border-left: 4px solid #c33; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .alert-info { background: #e3f2fd; color: #1976d2; border-left: 4px solid #1976d2; }
        .student-info {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .info-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .info-row:last-child { margin-bottom: 0; }
        .info-item {
            background: white;
            padding: 12px;
            border-radius: 5px;
        }
        .info-label { font-size: 12px; color: #999; font-weight: 600; text-transform: uppercase; }
        .info-value { font-size: 16px; color: #333; font-weight: 600; margin-top: 5px; }
        .result-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            border-radius: 15px;
            text-align: center;
            margin: 20px 0;
        }
        .result-container.not-published {
            background: #f9f9f9;
            color: #999;
        }
        .marks-display {
            font-size: 72px;
            font-weight: bold;
            margin: 20px 0;
        }
        .result-container.not-published .marks-display {
            color: #ccc;
        }
        .rank-display {
            font-size: 48px;
            font-weight: bold;
            margin: 10px 0;
        }
        .percentile-display {
            font-size: 20px;
            margin-top: 15px;
            opacity: 0.9;
        }
        .result-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 15px;
        }
        .status-published { background: rgba(255,255,255,0.2); color: white; }
        .status-pending { background: #ffeaa7; color: #d63031; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #f0f0f0;
            text-align: center;
            transition: all 0.3s;
        }
        .stat-box:hover { border-color: #667eea; box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1); }
        .stat-box .value { font-size: 32px; font-weight: bold; color: #667eea; }
        .stat-box .label { font-size: 13px; color: #999; margin-top: 8px; }
        .centre-info {
            background: #e3f2fd;
            border-left: 4px solid #1976d2;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .centre-info strong { color: #1976d2; }
        .no-result {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .no-result p { margin-bottom: 15px; }
        .download-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .download-btn:hover { transform: translateY(-2px); }
        @media print {
            body { background: white; }
            .back-link { display: none; }
            .download-btn { display: none; }
        }
        .certificate-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .certificate-notice strong { color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="card">
            <div class="card-header">
                <h1>📊 My Results</h1>
                <p><?php echo SYSTEM_NAME; ?></p>
            </div>
            
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php elseif ($result_data): ?>
                    
                    <!-- Student Information -->
                    <div class="student-info">
                        <div class="info-row">
                            <div class="info-item">
                                <div class="info-label">Registration Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($result_data['registration_no']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Student Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($result_data['name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Class</div>
                                <div class="info-value"><?php echo htmlspecialchars($result_data['class']); ?></div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-item">
                                <div class="info-label">School</div>
                                <div class="info-value"><?php echo htmlspecialchars($result_data['school_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Roll Number</div>
                                <div class="info-value"><?php echo $result_data['roll_no'] ? htmlspecialchars($result_data['roll_no']) : 'Not assigned'; ?></div>
                            </div>
                            <?php if ($result_data['centre_name']): ?>
                            <div class="info-item">
                                <div class="info-label">Exam Centre</div>
                                <div class="info-value"><?php echo htmlspecialchars($result_data['centre_name']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Result Display -->
                    <?php if ($result_data['status'] === 'published' && $result_data['marks'] !== null): ?>
                        
                        <div class="result-container">
                            <div style="font-size: 18px; opacity: 0.9;">YOUR RESULT</div>
                            <div class="marks-display"><?php echo $result_data['marks']; %>/100</div>
                            
                            <?php if ($result_data['rank']): ?>
                            <div style="font-size: 16px; opacity: 0.9;">RANK</div>
                            <div class="rank-display">#<?php echo $result_data['rank']; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($percentile !== null): ?>
                            <div class="percentile-display">
                                📊 Percentile: <?php echo $percentile; ?>%
                            </div>
                            <?php endif; ?>
                            
                            <div class="result-status status-published">✅ PUBLISHED</div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="value"><?php echo $result_data['marks']; ?></div>
                                <div class="label">Marks Obtained</div>
                            </div>
                            <div class="stat-box">
                                <div class="value">100</div>
                                <div class="label">Total Marks</div>
                            </div>
                            <?php if ($result_data['rank']): ?>
                            <div class="stat-box">
                                <div class="value">#<?php echo $result_data['rank']; ?></div>
                                <div class="label">Rank</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($percentile !== null): ?>
                            <div class="stat-box">
                                <div class="value"><?php echo $percentile; ?>%</div>
                                <div class="label">Percentile</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Performance Message -->
                        <?php 
                        $percentage = ($result_data['marks'] / 100) * 100;
                        if ($percentage >= 90): ?>
                            <div class="alert alert-info">
                                🌟 <strong>Excellent Performance!</strong> You have scored exceptionally well in the olympiad. Keep up the great work!
                            </div>
                        <?php elseif ($percentage >= 75): ?>
                            <div class="alert alert-info">
                                ✨ <strong>Great Performance!</strong> You have demonstrated strong knowledge and skills. Well done!
                            </div>
                        <?php elseif ($percentage >= 60): ?>
                            <div class="alert alert-info">
                                ✅ <strong>Good Effort!</strong> You have performed well. Continue learning and practicing!
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                📚 <strong>Learning Opportunity!</strong> Don't be discouraged. Use this as a stepping stone for improvement. Keep practicing!
                            </div>
                        <?php endif; ?>

                        <!-- Certificate Notice -->
                        <div class="certificate-notice">
                            <strong>📜 Certificate:</strong> Your certificate has been generated and is available for download in the certificates section.
                        </div>

                        <button class="download-btn" onclick="window.print();">🖨️ Print / Download Result</button>

                    <?php elseif ($result_data['status'] === 'pending' || $result_data['marks'] === null): ?>
                        
                        <div class="result-container not-published">
                            <div style="font-size: 18px;">RESULT NOT YET PUBLISHED</div>
                            <div class="marks-display">--</div>
                            <div style="font-size: 14px; opacity: 0.7;">Results will be published soon. Please check back later.</div>
                            <div class="result-status status-pending">⏳ PENDING</div>
                        </div>

                        <div class="alert alert-warning">
                            ⏳ <strong>Results Pending:</strong> Your result is not yet available. The organizers are still processing the results. Please check back soon.
                        </div>

                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-result">
                        <p>No result data available.</p>
                        <p>Please contact the administration if you believe this is an error.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
