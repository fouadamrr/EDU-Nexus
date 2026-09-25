<?php
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'student';
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$student_id = $_POST['student_id'] ?? null;
if (!$student_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Student ID']);
    exit;
}

$college_date = $_POST['college_date'] ?? '';
$univ_date = $_POST['univ_date'] ?? '';
$recipient = $_POST['recipient'] ?? '';
$clearance_receipt = $_POST['clearance_receipt'] ?? '';
$clearance_date = $_POST['clearance_date'] ?? '';
$cert_receipt = $_POST['cert_receipt'] ?? '';
$cert_date = $_POST['cert_date'] ?? '';

// Handle Photo Upload
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/uploads/student_photos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = 'student_' . $student_id . '_' . time() . '.' . $ext;
    $target_file = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $photo_path = 'uploads/student_photos/' . $filename;
    }
}

try {
    // Check if request exists
    $stmt = $pdo->prepare("SELECT id FROM graduation_certificate_requests WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $sql = "UPDATE graduation_certificate_requests SET 
                college_council_date = ?, 
                university_council_date = ?, 
                recipient = ?, 
                clearance_receipt = ?, 
                clearance_receipt_date = ?, 
                certificate_receipt = ?, 
                certificate_receipt_date = ?,
                updated_at = CURRENT_TIMESTAMP";
        $params = [$college_date, $univ_date, $recipient, $clearance_receipt, $clearance_date, $cert_receipt, $cert_date];
        
        if ($photo_path) {
            $sql .= ", photo_path = ?";
            $params[] = $photo_path;
        }
        
        $sql .= " WHERE user_id = ?";
        $params[] = $student_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // Create new request (dummy/manual)
        $sql = "INSERT INTO graduation_certificate_requests 
                (user_id, college_council_date, university_council_date, recipient, clearance_receipt, clearance_receipt_date, certificate_receipt, certificate_receipt_date, status, affairs_review, dean_review, photo_path) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', 'approved', 'approved', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $college_date, $univ_date, $recipient, $clearance_receipt, $clearance_date, $cert_receipt, $cert_date, $photo_path]);
    }

    echo json_encode(['status' => 'success', 'photo_path' => $photo_path]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
