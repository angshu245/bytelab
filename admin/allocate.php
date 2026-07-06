<?php
/**
 * Allocate Students to Centres - Admin Panel
 * ByteLab Olympiad Management System
 */

require_once '../config/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

// Handle allocation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'allocate') {
        $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $centre_id = isset($_POST['centre_id']) ? (int)$_POST['centre_id'] : 0;

        if ($student_id > 0 && $centre_id > 0) {
            // Check centre capacity
            $centre_result = $conn->query("SELECT capacity FROM exam_centres WHERE id = $centre_id");
            if ($centre_result->num_rows > 0) {
                $centre = $centre_result->fetch_assoc();
                
                // Count students already allocated to this centre
                $count_result = $conn->query("SELECT COUNT(*) as count FROM students WHERE centre_id = $centre_id");
                $count = $count_result->fetch_assoc()['count'];

                if ($count >= $centre['capacity']) {
                    $error = 'Centre capacity is full! Cannot allocate more students.';
                } else {
                    // Allocate student
                    $stmt = prepare_query($conn, 'UPDATE students SET centre_id = ? WHERE id = ?');
                    $stmt->bind_param('ii', $centre_id, $student_id);

                    if ($stmt->execute()) {
                        $message = 'Student allocated to centre successfully!';
                    } else {
                        $error = 'Error allocating student: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $error = 'Invalid centre selected!';
            }
        } else {
            $error = 'Please select both student and centre!';
        }
    } elseif ($_POST['action'] == 'deallocate') {
        $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

        if ($student_id > 0) {
            $stmt = prepare_query($conn, 'UPDATE students SET centre_id = NULL WHERE id = ?');
            $stmt->bind_param('i', $student_id);

            if ($stmt->execute()) {
                $message = 'Student deallocated successfully!';
            } else {
                $error = 'Error deallocating student: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'Invalid student selected!';
        }
    }
}

// Get all centres
$centres_result = $conn->query('SELECT id, centre_name, capacity FROM exam_centres ORDER BY centre_name');
$centres = [];
while ($row = $centres_result->fetch_assoc()) {
    $centres[] = $row;
}

// Get unallocated students with payment verified
$unallocated_result = $conn->query('
    SELECT id, registration_no, name, class, school_name, mobile 
    FROM students 
    WHERE centre_id IS NULL AND payment_status = "paid"
    ORDER BY created_at DESC
');
$unallocated_students = [];
while ($row = $unallocated_result->fetch_assoc()) {
    $unallocated_students[] = $row;
}

// Get allocated students with centre info
$allocated_result = $conn->query('
    SELECT 
        s.id,
        s.registration_no,
        s.name,
        s.class,
        s.school_name,
        s.mobile,
        ec.centre_name
    FROM students s
    LEFT JOIN exam_centres ec ON s.centre_id = ec.id
    WHERE s.centre_id IS NOT NULL AND s.payment_status = "paid"
    ORDER BY ec.centre_name, s.created_at DESC
');
$allocated_students = [];
while ($row = $allocated_result->fetch_assoc()) {
    $allocated_students[] = $row;
}

// Get centre statistics
$centres_stats = [];
foreach ($centres as $centre) {
    $count_result = $conn->query("SELECT COUNT(*) as count FROM students WHERE centre_id = " . $centre['id']);
    $count = $count_result->fetch_assoc()['count'];
    $centres_stats[$centre['id']] = [
        'name' => $centre['centre_name'],
        'capacity' => $centre['capacity'],
        'allocated' => $count,
        'available' => $centre['capacity'] - $count
    ];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Allocate Students - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header h1 { color: #333; margin-bottom: 5px; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #667eea; text-decoration: none; font-weight: 600; }
        .back-link:hover { color: #764ba2; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid; }
        .alert-error { background: #fee; color: #c33; border-color: #c33; }
        .alert-success { background: #efe; color: #3c3; border-color: #3c3; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
            text-align: center;
        }
        .stat-number { font-size: 28px; font-weight: bold; color: #667eea; }
        .stat-label { font-size: 12px; color: #999; margin-top: 5px; }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 1024px) {
            .content-grid { grid-template-columns: 1fr; }
        }
        .section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section h2 { color: #667eea; margin-bottom: 15px; font-size: 18px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 14px; }
        select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        select:focus { outline: none; border-color: #667eea; }
        button { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            padding: 10px 15px; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s;
        }
        button:hover { transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #f0f0f0; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #ddd; font-size: 13px; }
        tr:hover { background: #f9f9f9; }
        .action-btn {
            padding: 6px 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .action-btn:hover { background: #c0392b; }
        .centre-capacity {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .centre-capacity-item {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #667eea;
            font-size: 12px;
        }
        .centre-capacity-item strong { color: #667eea; }
        .capacity-bar {
            width: 100%;
            height: 6px;
            background: #eee;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        .capacity-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); }
        .no-data { text-align: center; padding: 30px; color: #999; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            background: #a9e4d4;
            color: #00b894;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="header">
            <h1>📍 Allocate Students to Centres</h1>
            <p>Manage student-centre allocations and capacity</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($unallocated_students); ?></div>
                <div class="stat-label">Unallocated Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($allocated_students); ?></div>
                <div class="stat-label">Allocated Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($centres); ?></div>
                <div class="stat-label">Total Centres</div>
            </div>
        </div>

        <!-- Centre Capacity Overview -->
        <div class="section" style="margin-bottom: 20px;">
            <h2>🏢 Centre Capacity Overview</h2>
            <div class="centre-capacity">
                <?php foreach ($centres_stats as $centre_id => $stats): ?>
                    <?php $percentage = ($stats['allocated'] / $stats['capacity']) * 100; ?>
                    <div class="centre-capacity-item">
                        <strong><?php echo htmlspecialchars($stats['name']); ?></strong><br>
                        <?php echo $stats['allocated']; ?>/<?php echo $stats['capacity']; ?>
                        <div class="capacity-bar">
                            <div class="capacity-fill" style="width: <?php echo min($percentage, 100); ?>%;"></div>
                        </div>
                        <div style="margin-top: 3px; font-size: 11px; color: <?php echo $stats['available'] <= 0 ? '#e74c3c' : '#27ae60'; ?>;">
                            <?php echo $stats['available'] > 0 ? '🟢 ' . $stats['available'] . ' available' : '🔴 FULL'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="content-grid">
            <!-- Allocate Student -->
            <div class="section">
                <h2>✏️ Allocate Student</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="allocate">
                    
                    <div class="form-group">
                        <label for="student_id">Select Student *</label>
                        <select id="student_id" name="student_id" required>
                            <option value="">-- Select Unallocated Student --</option>
                            <?php foreach ($unallocated_students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['registration_no'] . ' - ' . $student['name'] . ' (Class ' . $student['class'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="centre_id">Select Centre *</label>
                        <select id="centre_id" name="centre_id" required>
                            <option value="">-- Select Exam Centre --</option>
                            <?php foreach ($centres as $centre): 
                                $stat = $centres_stats[$centre['id']];
                                $is_full = $stat['available'] <= 0;
                            ?>
                                <option value="<?php echo $centre['id']; ?>" <?php echo $is_full ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($centre['centre_name'] . ' (' . $stat['allocated'] . '/' . $stat['capacity'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit">📍 Allocate</button>
                </form>

                <div style="margin-top: 20px; padding: 15px; background: #f0f7ff; border-radius: 5px; border-left: 3px solid #3498db;">
                    <strong style="color: #2c5aa0;">ℹ️ Info:</strong><br>
                    <small style="color: #2c5aa0;">Only students with verified payment are shown. Full centres are disabled in the dropdown.</small>
                </div>
            </div>

            <!-- Deallocate Student -->
            <div class="section">
                <h2>↩️ Deallocate Student</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="deallocate">
                    
                    <div class="form-group">
                        <label for="deallocate_student_id">Select Student to Deallocate *</label>
                        <select id="deallocate_student_id" name="student_id" required>
                            <option value="">-- Select Allocated Student --</option>
                            <?php foreach ($allocated_students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['registration_no'] . ' - ' . $student['name'] . ' (' . $student['centre_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" style="background: #e67e22;">↩️ Deallocate</button>
                </form>

                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; border-left: 3px solid #ffc107;">
                    <strong style="color: #856404;">⚠️ Warning:</strong><br>
                    <small style="color: #856404;">Deallocating a student removes their centre assignment. They must be reallocated before the exam.</small>
                </div>
            </div>
        </div>

        <!-- Allocated Students Table -->
        <div class="section">
            <h2>📋 Allocated Students (<?php echo count($allocated_students); ?>)</h2>
            <?php if (count($allocated_students) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Registration No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>School</th>
                                <th>Allocated Centre</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allocated_students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['registration_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class']); ?></td>
                                    <td><?php echo htmlspecialchars($student['school_name']); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($student['centre_name']); ?></span></td>
                                    <td>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="deallocate">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" class="action-btn" onclick="return confirm('Deallocate this student?');">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>No allocated students yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Unallocated Students Table -->
        <div class="section" style="margin-top: 20px;">
            <h2>📌 Unallocated Students (<?php echo count($unallocated_students); ?>)</h2>
            <?php if (count($unallocated_students) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Registration No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>School</th>
                                <th>Mobile</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unallocated_students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['registration_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class']); ?></td>
                                    <td><?php echo htmlspecialchars($student['school_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['mobile']); ?></td>
                                    <td><span class="badge" style="background: #ffeaa7; color: #d63031;">Pending</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>✅ All students with verified payments have been allocated!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
