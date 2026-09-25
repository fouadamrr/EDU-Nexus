<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/College.php';
if (!function_exists('auditLogAuto')) {
	require_once __DIR__ . '/../includes/audit.php';
}

class UserController {
 
 public function handleRequest(array $postData, ?string $action, string $role, ?int $college_scope): ?string {
 $userModel = new User();
 
 if ($action === 'add') {
 $new_username = trim($postData['username'] ?? '');
 $new_password = password_hash($postData['password'] ?? '', PASSWORD_DEFAULT);
 $new_name = trim($postData['full_name'] ?? '');
 $new_email = trim($postData['email'] ?? '');
 $new_role = $postData['role'] ?? 'student';
 $new_college = !empty($postData['college_id']) ? (int)$postData['college_id'] : null;

 if ($role === 'affairs' && $new_role !== 'student') {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">غير مصرح لك بإنشاء حسابات غير حسابات الطلاب</span></div>';
 } elseif ($role === 'dean' && in_array($new_role, ['admin', 'dean'])) {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">غير مصرح لك بإنشاء حسابات إدارة عليا</span></div>';
 } else {
 if ($userModel->findOneBy('username', $new_username)) {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">اسم المستخدم موجود بالفعل</span></div>';
 } else {
 if ($role === 'dean' && $new_college === null && $college_scope) {
 $new_college = $college_scope;
 }
 $new_id = $userModel->insert([
 'username' => $new_username,
 'email' => $new_email,
 'password' => $new_password,
 'full_name' => $new_name,
 'role' => $new_role,
 'college_id' => $new_college,
 'status' => 'active'
 ]);
 // ── Audit: تسجيل إضافة مستخدم جديد ──
 global $pdo;
 auditLogAuto($pdo, 'ADD', 'USER', is_numeric($new_id) ? (int)$new_id : null,
 null,
 ['username' => $new_username, 'full_name' => $new_name, 'role' => $new_role, 'college_id' => $new_college],
 "إضافة مستخدم جديد: {$new_name} ({$new_role})"
 );
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-check-circle text-lg"></i><span class="font-medium">تم إضافة المستخدم بنجاح</span></div>';
 }
 }
 }

 if ($action === 'edit') {
 $edit_id = (int)($postData['id'] ?? 0);
 $edit_uname = trim($postData['username'] ?? '');
 $edit_name = trim($postData['full_name'] ?? '');
 $edit_email = trim($postData['email'] ?? '');
 $edit_role_v= $postData['role'] ?? 'student';
 $edit_pwd = $postData['password'] ?? '';
 $edit_coll = !empty($postData['college_id']) ? (int)$postData['college_id'] : null;

 $target_user = $userModel->findById($edit_id);
 if (!$target_user) return null;

 if ($target_user['role'] === 'super_admin' && $role !== 'super_admin') {
 return '<div class="bg-bg text-accent border border-primary p-4 rounded-xl mb-6">غير مصرح لك بتعديل بيانات رئيس الجامعة</div>';
 }
 if ($target_user['role'] === 'super_admin' && $edit_role_v !== 'super_admin') {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6">لا يمكن تغيير صلاحية رئيس الجامعة</div>';
 }

 if ($role === 'affairs' && ($target_user['role'] !== 'student' || $edit_role_v !== 'student')) {
 return '<div class="bg-bg text-accent border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">غير مصرح لك بتعديل هذا الحساب</span></div>';
 } elseif ($role === 'dean' && in_array($target_user['role'] ?? '', ['admin', 'dean'])) {
 return '<div class="bg-bg text-accent border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">غير مصرح لك بتعديل حسابات الإدارة العليا</span></div>';
 } else {
 $old_snapshot = array_intersect_key($target_user, array_flip(['username','full_name','role','email','college_id']));
 $updateData = [
 'username' => $edit_uname,
 'email' => $edit_email,
 'full_name' => $edit_name,
 'role' => $edit_role_v,
 'college_id' => $edit_coll,
 ];
 if (!empty($edit_pwd)) {
 $updateData['password'] = password_hash($edit_pwd, PASSWORD_DEFAULT);
 }
 $userModel->update($edit_id, $updateData);
 // ── Audit: تسجيل تعديل بيانات مستخدم ──
 global $pdo;
 auditLogAuto($pdo, 'UPDATE', 'USER', $edit_id,
 $old_snapshot,
 ['username' => $edit_uname, 'full_name' => $edit_name, 'role' => $edit_role_v, 'college_id' => $edit_coll],
 "تعديل بيانات المستخدم: {$edit_name} (ID:{$edit_id})"
 );
 return '<div class="bg-bg text-accent border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-info-circle text-lg"></i><span class="font-medium">تم تعديل بيانات المستخدم بنجاح</span></div>';
 }
 }
 
 if ($action === 'delete' && isset($postData['delete'])) {
 $del_id = (int)$postData['delete'];
 $target_user = $userModel->findById($del_id);
 if ($target_user) {
 if ($target_user['role'] === 'super_admin') {
 return '<div class="bg-bg text-secondary border border-primary p-4 rounded-xl mb-6">لا يمكن حذف حساب رئيس الجامعة</div>';
 }
 if ($role === 'affairs' && ($target_user['role'] ?? '') !== 'student') {
 return '<div class="bg-bg text-secondary border border-primary p-4 rounded-xl mb-6">غير مصرح لك بحذف هذا الحساب</div>';
 } elseif ($role === 'dean' && in_array($target_user['role'] ?? '', ['admin','dean'])) {
 return '<div class="bg-bg text-secondary border border-primary p-4 rounded-xl mb-6">غير مصرح لك بحذف حسابات الإدارة العليا</div>';
 } else {
 // ── Audit: تسجيل حذف مستخدم (قبل الحذف عشان البيانات لا تضيع) ──
 global $pdo;
 $old_data = array_intersect_key($target_user, array_flip(['username','full_name','role','email','college_id']));
 auditLogAuto($pdo, 'DELETE', 'USER', $del_id,
 $old_data, null,
 "حذف المستخدم: {$target_user['full_name']} ({$target_user['role']}) - ID:{$del_id}"
 );
 $userModel->delete($del_id);
 return '<div class="bg-bg text-secondary border border-primary p-4 rounded-xl mb-6"><i class="fas fa-trash"></i> تم حذف المستخدم بنجاح</div>';
 }
 }
 }

 if ($action === 'toggle' && isset($postData['toggle'])) {
 $tog_id = (int)$postData['toggle'];
 $target_user = $userModel->findById($tog_id);
 if ($target_user) {
 if ($target_user['role'] === 'super_admin') {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6">لا يمكن إيقاف حساب رئيس الجامعة</div>';
 }
 if ($role === 'affairs' && ($target_user['role'] ?? '') !== 'student') {
 return '<div class="bg-bg text-primary p-4 rounded-xl mb-6">غير مصرح لك بتغيير حالة هذا الحساب</div>';
 } elseif ($role === 'dean' && in_array($target_user['role'] ?? '', ['admin','dean'])) {
 return '<div class="bg-bg text-primary p-4 rounded-xl mb-6">غير مصرح لك بتغيير حالة حسابات الإدارة العليا</div>';
 } else {
 $new_status = ($target_user['status'] ?? 'active') === 'active' ? 'suspended' : 'active';
 $audit_action = $new_status === 'suspended' ? 'BLOCK' : 'UNBLOCK';
 // ── Audit: تسجيل تغيير حالة الحساب ──
 global $pdo;
 auditLogAuto($pdo, $audit_action, 'USER', $tog_id,
 ['status' => $target_user['status'] ?? 'active'],
 ['status' => $new_status],
 ($new_status === 'suspended' ? 'إيقاف' : 'تفعيل') . " حساب: {$target_user['full_name']} (ID:{$tog_id})"
 );
 $userModel->update($tog_id, ['status' => $new_status]);
 $lbl = $new_status === 'suspended' ? 'إيقاف' : 'إعادة تفعيل';
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6">تم '.$lbl.' الحساب بنجاح</div>';
 }
 }
 }

 return null;
 }

 public function getUsersList(string $role, ?int $college_scope, ?string $filter_role): array {
 $db = Database::getConnection();
 $userSql = 'SELECT u.*, c.name AS college_name, s.nomination_card, s.phone AS student_phone, s.national_id AS student_national_id FROM users u LEFT JOIN colleges c ON u.college_id = c.id LEFT JOIN students s ON u.id = s.user_id';
 $userParams = [];
 $whereParts = [];

 if ($role === 'affairs') {
 $whereParts[] = "u.role = 'student'";
 if ($college_scope) {
 $whereParts[] = 'u.college_id = :cid';
 $userParams[':cid'] = $college_scope;
 }
 } elseif ($role === 'dean') {
 if ($college_scope) {
 $whereParts[] = 'u.college_id = :cid';
 $userParams[':cid'] = $college_scope;
 } else {
 $whereParts[] = '1 = 0';
 }
 }

 if ($filter_role) {
 $whereParts[] = 'u.role = :frole';
 $userParams[':frole'] = $filter_role;
 }

 if ($whereParts) {
 $userSql .= ' WHERE ' . implode(' AND ', $whereParts);
 }
 $userSql .= ' ORDER BY u.id DESC';

 $stmt = $db->prepare($userSql);
 $stmt->execute($userParams);
 $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
 foreach ($users as &$u) { $u['source_db'] = 'postgresql'; }
 
 return $users;
 }
}
