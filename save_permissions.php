<?php
/**
 * save_permissions.php — EDU Nexus
 * AJAX POST endpoint: saves the Super Admin's permission selections for a user.
 * Returns JSON.
 */
session_start();
require_once 'db.php';
require_once 'includes/permissions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
 http_response_code(401);
 echo json_encode(['success' => false, 'message' => 'غير مصرح']);
 exit;
}

$role = $_SESSION['role'] ?? 'student';
$user_id = $_SESSION['user_id'];

// ── Security ──────────────────────────────────────────────────
if ($role !== 'super_admin') {
 http_response_code(403);
 echo json_encode(['success' => false, 'message' => 'غير مصرح لك بتعديل الصلاحيات']);
 exit;
}

$target_user_id = (int)($_POST['user_id'] ?? 0);
$selected_perms = $_POST['permissions'] ?? []; // array of permission keys
$selected_perms = is_array($selected_perms) ? $selected_perms : [];

if (!$target_user_id) {
 echo json_encode(['success' => false, 'message' => 'معرف المستخدم مفقود أو غير صحيح']);
 exit;
}

// ── Ensure the user_permissions and logs tables exist ────────
try {
 $pdo->exec("
 CREATE TABLE IF NOT EXISTS user_permissions (
 id SERIAL PRIMARY KEY,
 user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 permission VARCHAR(100) NOT NULL,
 granted_by INT REFERENCES users(id) ON DELETE SET NULL,
 granted_at TIMESTAMPTZ DEFAULT NOW(),
 UNIQUE (user_id, permission)
 );
 CREATE TABLE IF NOT EXISTS logs (
 id SERIAL PRIMARY KEY,
 user_id INT,
 college_id INT,
 action VARCHAR(255),
 details TEXT,
 ip_address VARCHAR(45),
 created_at TIMESTAMPTZ DEFAULT NOW()
 );
 ");
} catch (Exception $e) { /* tables already exist — fine */ }


// ── Fetch target user ─────────────────────────────────────────
$chkStmt = $pdo->prepare("SELECT role, full_name FROM users WHERE id = :id LIMIT 1");
$chkStmt->execute(['id' => $target_user_id]);
$targetUser = $chkStmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
 echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
 exit;
}

// Cannot modify super_admin (they bypass everything) or student (they use their own portal)
if (in_array($targetUser['role'], ['super_admin', 'student'])) {
 echo json_encode(['success' => false, 'message' => 'لا يمكن تعديل صلاحيات هذا النوع من الحسابات بشل صريح']);
 exit;
}

// ── Clean & Validate Permissions ──────────────────────────────
$valid_keys = array_keys(SYSTEM_PERMISSIONS);
$clean_perms = [];
foreach ($selected_perms as $p) {
 if (in_array($p, $valid_keys)) {
 $clean_perms[] = $p;
 }
}

try {
 $pdo->beginTransaction();

 // 1. Delete all existing specific permissions for this user
 $delStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid");
 $delStmt->execute(['uid' => $target_user_id]);

 // 2. Insert new selections
 if (!empty($clean_perms)) {
 $insertStmt = $pdo->prepare("
 INSERT INTO user_permissions (user_id, permission, granted_by)
 VALUES (:uid, :perm, :granted_by)
 ");
 foreach ($clean_perms as $perm) {
 $insertStmt->execute([
 'uid' => $target_user_id,
 'perm' => $perm,
 'granted_by' => $user_id
 ]);
 }
 }

 // ── Audit log ─────────────────────────────────────────────
 $permList = implode(', ', $clean_perms) ?: 'لا يوجد';
 $permCount = count($clean_perms);
 $targetName = $targetUser['full_name'];
 $logStmt = $pdo->prepare("
 INSERT INTO logs (user_id, action, details)
 VALUES (:uid, 'permission_update', :details)
 ");
 $logStmt->execute([
 'uid' => $user_id,
 'details' => "تم تعديل صلاحيات ({$targetName}). الإجمالي الممنوح: {$permCount} وحدة. [{$permList}]"
 ]);

 $pdo->commit();

 echo json_encode([
 'success' => true,
 'message' => 'تم حفظ الصلاحيات بنجاح'
 ]);

} catch (Exception $e) {
 $pdo->rollBack();
 error_log("Permission Save Error: " . $e->getMessage());
 echo json_encode([
 'success' => false,
 'message' => 'حدث خطأ أثناء حفظ الصلاحيات'
 ]);
}
