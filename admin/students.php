<?php
/**
 * Manage Students - Admin Panel
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

// Add new student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = escape_input($conn, $_POST['name'] ?? '');
    $dob = escape_input($conn, $_POST['dob'] ?? '');
    $mobile = escape_input($conn, $_POST['mobile'] ?? '');
    $class = escape_input($conn, $_POST['class'] ?? '');
    $school = escape_input($conn, $_POST['school'] ?? '');
    
    if ($name && $dob && $mobile && $class && $school) {
        // Generate registration number
        $reg_no = 'BDO26' . str_pad($conn->query('SELECT COUNT(*) as c FROM students')->fetch_assoc()['c'] + 1, 5, '0', STR_PAD_LEFT);
        
        $stmt = prepare_query($conn, 'INSERT INTO students (registration_no, name, dob, mobile, class, school_name) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $reg_no, $name, $dob, $mobile, $class, $school);
        
        if ($stmt->execute()) {
            $message = 'Student added successfully! Registration No: ' . $reg_no;
        } else {
            $error = 'Error adding student: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = 'All fields are required!';
    }
}

// Get all students
$students_result = $conn->query('SELECT * FROM students ORDER BY created_at DESC LIMIT 100');
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo SYSTEM_NAME; ?></title>
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
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert-success { background: #efe; color: #3c3; border: 1px solid #cfc; }
        .table-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f0f0f0; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .badge { padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #ffeaa7; color: #d63031; }
        .badge-paid { background: #a9e4d4; color: #00b894; }
        .action-links { display: flex; gap: 10px; }
        .action-links a { padding: 6px 12px; border-radius: 3px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .edit-link { background: #3498db; color: white; }
        .edit-link:hover { background: #2980b9; }
        .delete-link { background: #e74c3c; color: white; }
        .delete-link:hover { background: #c0392b; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="header">
            <h1>👥 Manage Students</h1>
            <p>Add and manage student registrations</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="form-section">
            <h2>Add New Student</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <input type="date" name="dob" required>
                    </div>
                    <div class="form-group">
                        <label>Mobile Number *</label>
                        <input type="tel" name="mobile" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Class *</label>
                        <select name="class" required>
                            <option value="">Select Class</option>
                            <option value="5">Class 5</option>
                            <option value="6">Class 6</option>
                            <option value="7">Class 7</option>
                            <option value="8">Class 8</option>
                            <option value="9">Class 9</option>
                            <option value="10">Class 10</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Name *</label>
                        <input type="text" name="school" required>
                    </div>
                </div>
                <button type="submit">➕ Add Student</button>
            </form>
        </div>
        
        <div class="table-section">
            <h2>Registered Students (Last 100)</h2>
            <?php if (count($students) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Registration No</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Class</th>
                            <th>School</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['registration_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($student['class']); ?></td>
                                <td><?php echo htmlspecialchars($student['school_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $student['payment_status']; ?>">
                                        <?php echo strtoupper($student['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-links">
                                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="edit-link">✏️ Edit</a>
                                        <a href="delete_student.php?id=<?php echo $student['id']; ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this student?');">🗑️ Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No students registered yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
