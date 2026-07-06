<?php
/**
 * Manage Exam Centres - Admin Panel
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

// Add new centre
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $centre_name = escape_input($conn, $_POST['centre_name'] ?? '');
    $address = escape_input($conn, $_POST['address'] ?? '');
    $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;
    $invigilator_name = escape_input($conn, $_POST['invigilator_name'] ?? '');
    $invigilator_mobile = escape_input($conn, $_POST['invigilator_mobile'] ?? '');

    if ($centre_name && $address && $capacity > 0 && $invigilator_name && $invigilator_mobile) {
        $stmt = prepare_query($conn, 'INSERT INTO exam_centres (centre_name, address, capacity, invigilator_name, invigilator_mobile) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('ssiss', $centre_name, $address, $capacity, $invigilator_name, $invigilator_mobile);

        if ($stmt->execute()) {
            $message = 'Exam centre added successfully!';
        } else {
            $error = 'Error adding centre: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = 'All fields are required! Capacity must be a number greater than 0.';
    }
}

// Get all centres with student count
$centres_result = $conn->query('
    SELECT 
        ec.id,
        ec.centre_name,
        ec.address,
        ec.capacity,
        ec.current_count,
        ec.invigilator_name,
        ec.invigilator_mobile,
        ec.created_at,
        COUNT(s.id) as actual_count
    FROM exam_centres ec
    LEFT JOIN students s ON ec.id = s.centre_id
    GROUP BY ec.id
    ORDER BY ec.created_at DESC
');

$centres = [];
while ($row = $centres_result->fetch_assoc()) {
    $centres[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Centres - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header h1 { color: #333; margin-bottom: 10px; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #667eea; text-decoration: none; font-weight: 600; }
        .back-link:hover { color: #764ba2; }
        .form-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-section h2 { color: #667eea; margin-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 14px; }
        input, textarea { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 14px;
            font-family: inherit;
        }
        textarea { resize: vertical; min-height: 80px; }
        input:focus, textarea:focus { outline: none; border-color: #667eea; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert-success { background: #efe; color: #3c3; border: 1px solid #cfc; }
        .centres-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .centre-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        .centre-card h3 { color: #333; margin-bottom: 10px; font-size: 18px; }
        .centre-info { margin-bottom: 15px; }
        .centre-info-row { display: flex; justify-content: space-between; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
        .centre-info-label { font-weight: 600; color: #666; font-size: 13px; }
        .centre-info-value { color: #333; font-size: 13px; }
        .capacity-bar { width: 100%; height: 8px; background: #eee; border-radius: 4px; overflow: hidden; margin-top: 5px; }
        .capacity-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); }
        .centre-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .action-btn { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 12px; text-decoration: none; text-align: center; display: inline-block; transition: all 0.2s; }
        .edit-btn { background: #3498db; color: white; }
        .edit-btn:hover { background: #2980b9; }
        .delete-btn { background: #e74c3c; color: white; }
        .delete-btn:hover { background: #c0392b; }
        .capacity-status {
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            margin-top: 10px;
        }
        .capacity-available { background: #d4edda; color: #155724; }
        .capacity-full { background: #f8d7da; color: #721c24; }
        .no-centres { text-align: center; padding: 40px 20px; color: #999; }
        .stats-container {
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
            text-align: center;
            border-left: 4px solid #667eea;
        }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #667eea; }
        .stat-card .label { font-size: 12px; color: #999; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="header">
            <h1>🏢 Manage Exam Centres</h1>
            <p>Create, edit and manage exam centres</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="number"><?php echo count($centres); ?></div>
                <div class="label">Total Centres</div>
            </div>
            <div class="stat-card">
                <div class="number">
                    <?php 
                    $total_capacity = array_sum(array_column($centres, 'capacity'));
                    echo $total_capacity;
                    ?>
                </div>
                <div class="label">Total Capacity</div>
            </div>
            <div class="stat-card">
                <div class="number">
                    <?php 
                    $total_students = array_sum(array_column($centres, 'actual_count'));
                    echo $total_students;
                    ?>
                </div>
                <div class="label">Students Allocated</div>
            </div>
        </div>

        <!-- Add Centre Form -->
        <div class="form-section">
            <h2>➕ Add New Exam Centre</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Centre Name *</label>
                        <input type="text" name="centre_name" placeholder="e.g., North Centre" required>
                    </div>
                    <div class="form-group">
                        <label>Capacity *</label>
                        <input type="number" name="capacity" placeholder="e.g., 100" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Address *</label>
                    <textarea name="address" placeholder="Enter complete address" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Invigilator Name *</label>
                        <input type="text" name="invigilator_name" placeholder="e.g., Dr. Rajesh Kumar" required>
                    </div>
                    <div class="form-group">
                        <label>Invigilator Mobile *</label>
                        <input type="tel" name="invigilator_mobile" placeholder="e.g., 9876543210" required>
                    </div>
                </div>

                <button type="submit">🏢 Add Centre</button>
            </form>
        </div>

        <!-- Centres Grid -->
        <div>
            <h2 style="margin-bottom: 20px; color: #333;">📋 All Exam Centres</h2>
            
            <?php if (count($centres) > 0): ?>
                <div class="centres-grid">
                    <?php foreach ($centres as $centre): ?>
                        <?php 
                        $percentage = ($centre['actual_count'] / $centre['capacity']) * 100;
                        $is_full = $centre['actual_count'] >= $centre['capacity'];
                        ?>
                        <div class="centre-card">
                            <h3><?php echo htmlspecialchars($centre['centre_name']); ?></h3>
                            
                            <div class="centre-info">
                                <div class="centre-info-row">
                                    <span class="centre-info-label">📍 Address:</span>
                                    <span class="centre-info-value"><?php echo htmlspecialchars($centre['address']); ?></span>
                                </div>
                                
                                <div class="centre-info-row">
                                    <span class="centre-info-label">👤 Invigilator:</span>
                                    <span class="centre-info-value"><?php echo htmlspecialchars($centre['invigilator_name']); ?></span>
                                </div>
                                
                                <div class="centre-info-row">
                                    <span class="centre-info-label">📞 Mobile:</span>
                                    <span class="centre-info-value"><?php echo htmlspecialchars($centre['invigilator_mobile']); ?></span>
                                </div>
                                
                                <div class="centre-info-row">
                                    <span class="centre-info-label">📊 Capacity:</span>
                                    <span class="centre-info-value">
                                        <?php echo $centre['actual_count']; ?> / <?php echo $centre['capacity']; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Capacity Bar -->
                            <div class="capacity-bar">
                                <div class="capacity-fill" style="width: <?php echo min($percentage, 100); ?>%;"></div>
                            </div>

                            <!-- Status -->
                            <div class="capacity-status <?php echo $is_full ? 'capacity-full' : 'capacity-available'; ?>">
                                <?php 
                                if ($is_full) {
                                    echo '🔴 FULL - No more seats';
                                } else {
                                    echo '🟢 Available - ' . ($centre['capacity'] - $centre['actual_count']) . ' seats left';
                                }
                                ?>
                            </div>

                            <!-- Actions -->
                            <div class="centre-actions">
                                <a href="edit_centre.php?id=<?php echo $centre['id']; ?>" class="action-btn edit-btn">✏️ Edit</a>
                                <a href="delete_centre.php?id=<?php echo $centre['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Delete this centre?');"> 🗑️ Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-centres">
                    <p>No exam centres created yet. Add one above to get started!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
