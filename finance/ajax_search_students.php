<?php
session_start();
require_once __DIR__ . '/db.php';
/** @var PDO $pdo */
/** @var string $role */
/** @var UniversityDB $db */

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'student';
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

$params = [':q1' => "%$q%", ':q2' => "%$q%"];
$college_filter = "";

if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_id'])) {
    $college_filter = " AND college_id = :cid";
    $params[':cid'] = $_SESSION['college_id'];
}

try {
    // استخدمنا q1 و q2 لأن بعض قواعد البيانات بيفضلوا إن المعاملات متتكررش بنفس الاسم في نفس الـ statement
    $sql = "SELECT id, full_name, username FROM users WHERE role = 'student' AND (full_name LIKE :q1 OR username LIKE :q2) $college_filter LIMIT 15";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $students]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
