<?php
// المحرك الأساسي لتسجيل كل الحركات اللي بتحصل في السيستم (Audit Logging)
// دا اللي بيسجل مين عمل إيه وإمتى بالظبط

if (!function_exists('auditLogAuto')) {

 /**
 * Insert one record into audit_logs.
 *
 * @param PDO $pdo Active PDO connection
 * @param string $action Action type: ADD | UPDATE | DELETE | ASSIGN | SYSTEM_CHANGE | LOGIN | LOGOUT | etc.
 * @param string $targetType Entity type: USER | COURSE | EXAM | ATTENDANCE | SYSTEM | GRADE | ENROLLMENT | ...
 * @param int|null $targetId Primary key of the affected record (null for system-wide actions)
 * @param array|null $oldData Snapshot BEFORE the change
 * @param array|null $newData Snapshot AFTER the change
 * @param string $description Human-readable summary (auto-generated if empty)
 * @return int|false New audit_logs.id or false on failure
 */
 function auditLogAuto(
 PDO $pdo,
 string $action,
 string $targetType,
 ?int $targetId = null,
 ?array $oldData = null,
 ?array $newData = null,
 string $description = ''
 ): int|false {

 // بنشوف مين اللي بيعمل الحركة دي دلوقتي
 if (session_status() === PHP_SESSION_NONE) {
 session_start();
 }

 $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
 $actorRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'system';
 $actorName = isset($_SESSION['full_name']) ? $_SESSION['full_name']
 : (isset($_SESSION['username']) ? $_SESSION['username'] : 'System');
 $collegeId = isset($_SESSION['college_id']) ? (int)$_SESSION['college_id'] : null;

 // بننضف البيانات قبل ما نسجلها (عشان مفيش باسوردات تتسجل في اللوج)
 $sensitiveKeys = ['password', 'password_hash', 'token', 'secret', 'pin'];

 $cleanOld = null;
 if (!empty($oldData)) {
 $oldData = array_diff_key($oldData, array_flip($sensitiveKeys));
 $cleanOld = json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
 }

 $cleanNew = null;
 if (!empty($newData)) {
 $newData = array_diff_key($newData, array_flip($sensitiveKeys));
 $cleanNew = json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
 }

 // بنعمل وصف للحركة لو مبعتلناش واحد
 if ($description === '') {
 $description = _auditBuildDescription($action, $targetType, $targetId, $actorName, $actorRole);
 }

 // بنجيب الـ IP ونوع المتصفح
 $ip = _auditGetIp();
 $ua = isset($_SERVER['HTTP_USER_AGENT'])
 ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 512)
 : 'CLI';

 $sessId = session_id() ?: null;

 // بنسيف السجل في الداتا بيز
 try {
 $stmt = $pdo->prepare("
 INSERT INTO audit_logs
 (actor_id, actor_role, actor_name, action_type, target_type,
 target_id, old_values, new_values, description,
 ip_address, user_agent, session_id, college_id, created_at)
 VALUES
 (:actor_id, :actor_role, :actor_name, :action_type, :target_type,
 :target_id, :old_values, :new_values, :description,
 :ip_address, :user_agent, :session_id, :college_id, NOW())
 RETURNING id
 ");

 $stmt->execute([
 ':actor_id' => $actorId,
 ':actor_role' => $actorRole,
 ':actor_name' => $actorName,
 ':action_type' => strtoupper(trim($action)),
 ':target_type' => strtoupper(trim($targetType)),
 ':target_id' => $targetId,
 ':old_values' => $cleanOld,
 ':new_values' => $cleanNew,
 ':description' => $description,
 ':ip_address' => $ip,
 ':user_agent' => $ua,
 ':session_id' => $sessId,
 ':college_id' => $collegeId,
 ]);

 $row = $stmt->fetch();
 return $row ? (int)$row['id'] : false;

 } catch (PDOException $e) {
 // Never let audit failures crash the main application
 error_log('[EDU Nexus Audit] Failed to write log: ' . $e->getMessage());
 return false;
 }
 }

 // دوال مساعدة داخلية

 // بنطلع جملة مفهومة للحركة اللي حصلت
 function _auditBuildDescription(
 string $action,
 string $targetType,
 ?int $targetId,
 string $actorName,
 string $actorRole
 ): string {
 $idPart = $targetId ? " #$targetId" : '';
 $map = [
 'ADD' => "created a new $targetType{$idPart}",
 'UPDATE' => "updated $targetType{$idPart}",
 'DELETE' => "deleted $targetType{$idPart}",
 'ASSIGN' => "assigned $targetType{$idPart}",
 'SYSTEM_CHANGE' => "changed a SYSTEM setting",
 'LOGIN' => "logged in to the system",
 'LOGOUT' => "logged out of the system",
 'EXPORT' => "exported $targetType data",
 'IMPORT' => "imported $targetType data",
 'BLOCK' => "blocked $targetType{$idPart}",
 'UNBLOCK' => "unblocked $targetType{$idPart}",
 'RESET' => "reset $targetType{$idPart}",
 'APPROVE' => "approved $targetType{$idPart}",
 'REJECT' => "rejected $targetType{$idPart}",
 ];

 $verb = $map[strtoupper($action)] ?? strtolower($action) . " on $targetType{$idPart}";
 return "$actorName ($actorRole) $verb.";
 }

 // بنجيب الـ IP الحقيقي بتاع المستخدم
 function _auditGetIp(): string
 {
 foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
 if (!empty($_SERVER[$key])) {
 // X-Forwarded-For may be a comma-separated list
 $ip = trim(explode(',', $_SERVER[$key])[0]);
 if (filter_var($ip, FILTER_VALIDATE_IP)) {
 return $ip;
 }
 }
 }
 return '0.0.0.0';
 }

 // بنجيب بيانات السجل الحالية من الداتا بيز عشان نستخدمها كـ Snapshot
 function auditFetchSnapshot(PDO $pdo, string $table, int $id): ?array
 {
 // Whitelist allowed tables to prevent SQL injection
 $allowed = [
 'users','courses','enrollments','exam_sessions','attendance_sessions',
 'attendance_records','grades','fees','settings','colleges','departments',
 'resources','messages','announcements','class_schedule','student_groups',
 ];

 // Simple alphanumeric + underscore check as fallback
 if (!in_array($table, $allowed, true) && !preg_match('/^[a-z][a-z0-9_]{1,63}$/', $table)) {
 return null;
 }

 try {
 $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id LIMIT 1");
 $stmt->execute([':id' => $id]);
 $row = $stmt->fetch();
 return $row ?: null;
 } catch (PDOException $e) {
 return null;
 }
 }

 // تسجيل عملية الدخول
 function auditLogLogin(PDO $pdo): void
 {
 auditLogAuto($pdo, 'LOGIN', 'SESSION', null, null, null, '');
 }

    // بنسجل إن اليوزر خرج من السيستم
 function auditLogLogout(PDO $pdo): void
 {
 auditLogAuto($pdo, 'LOGOUT', 'SESSION', null, null, null, '');
 }

} // end if (!function_exists)
