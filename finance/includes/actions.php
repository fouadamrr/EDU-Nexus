<?php
// الدوال اللي بتنفذ العمليات على الداتا بيز وبتسجلها في اللوج آلياً
// الملف دا فيه كل الأكشنز المهمة زي إضافة يوزر أو كورس أو درجات

// بنحمل محرك اللوج لو مكنش اتحمل
if (!function_exists('auditLogAuto')) {
 require_once __DIR__ . '/audit.php';
}

// دالة مساعدة عشان نسجل في اللوج من غير ما السيستم يضرب لو فيه مشكلة
function _safeAuditLog(PDO $pdo, string $action, string $targetType, ?int $targetId,
 ?array $oldData, ?array $newData, string $description = ''): void
{
 if (function_exists('auditLogAuto')) {
 try {
 auditLogAuto($pdo, $action, $targetType, $targetId, $oldData, $newData, $description);
 } catch (Throwable $e) {
 error_log('[EDU Nexus Audit] _safeAuditLog failed: ' . $e->getMessage());
 }
 }
}

// إدارة اليوزرات

// ضيف يوزر جديد
function createUser(PDO $pdo, array $data): int|false
{
 // شفر الباسورد قبل ما تشيله في الداتا بيز
 if (isset($data['password'])) {
 $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
 unset($data['password']);
 }

 $data['created_at'] = date('Y-m-d H:i:s');

 $cols = array_keys($data);
 $marks = array_map(fn($c) => ':' . $c, $cols);
 $sql = "INSERT INTO users (" . implode(',', $cols) . ") VALUES (" . implode(',', $marks) . ") RETURNING id";

 try {
 $stmt = $pdo->prepare($sql);
 foreach ($data as $k => $v) {
 $stmt->bindValue(':' . $k, $v);
 }
 $stmt->execute();
 $row = $stmt->fetch();
 $newId = $row ? (int)$row['id'] : false;

 if ($newId) {
 _safeAuditLog(
 $pdo, 'ADD', 'USER', $newId,
 null,
 array_diff_key($data, ['password_hash' => '']),
 "New user account created: " . ($data['username'] ?? $data['email'] ?? "ID $newId")
 );
 }
 return $newId;
 } catch (PDOException $e) {
 error_log('[actions] createUser: ' . $e->getMessage());
 return false;
 }
}

// عدل بيانات يوزر
function updateUser(PDO $pdo, int $userId, array $newData): bool
{
 // صور حالته القديمة قبل التعديل
 $oldData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'users', $userId) : null;

 if (isset($newData['password'])) {
 $newData['password_hash'] = password_hash($newData['password'], PASSWORD_BCRYPT);
 unset($newData['password']);
 }

 $sets = [];
 $params = [':_id' => $userId];
 foreach ($newData as $k => $v) {
 $sets[] = "$k = :$k";
 $params[":$k"] = $v;
 }

 $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = :_id";

 try {
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $affected = $stmt->rowCount() > 0;

 if ($affected) {
 $afterData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'users', $userId) : null;
 _safeAuditLog($pdo, 'UPDATE', 'USER', $userId, $oldData, $afterData, "User #$userId account updated.");
 }
 return $affected;
 } catch (PDOException $e) {
 error_log('[actions] updateUser: ' . $e->getMessage());
 return false;
 }
}

// امسح يوزر خالص من السيستم
function deleteUser(PDO $pdo, int $userId): bool
{
 $oldData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'users', $userId) : null;

 try {
 $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
 $stmt->execute([':id' => $userId]);
 $deleted = $stmt->rowCount() > 0;

 if ($deleted) {
 _safeAuditLog(
 $pdo, 'DELETE', 'USER', $userId,
 $oldData, null,
 "User #$userId (" . ($oldData['username'] ?? 'unknown') . ") deleted."
 );
 }
 return $deleted;
 } catch (PDOException $e) {
 error_log('[actions] deleteUser: ' . $e->getMessage());
 return false;
 }
}

// إدارة الكورسات والمواد

// ضيف مادة جديدة
function createCourse(PDO $pdo, array $data): int|false
{
 $cols = array_keys($data);
 $marks = array_map(fn($c) => ':' . $c, $cols);
 $sql = "INSERT INTO courses (" . implode(',', $cols) . ") VALUES (" . implode(',', $marks) . ") RETURNING id";

 try {
 $stmt = $pdo->prepare($sql);
 foreach ($data as $k => $v) $stmt->bindValue(':' . $k, $v);
 $stmt->execute();
 $row = $stmt->fetch();
 $newId = $row ? (int)$row['id'] : false;

 if ($newId) {
 _safeAuditLog(
 $pdo, 'ADD', 'COURSE', $newId,
 null, $data,
 "Course created: " . ($data['course_name'] ?? $data['course_code'] ?? "ID $newId")
 );
 }
 return $newId;
 } catch (PDOException $e) {
 error_log('[actions] createCourse: ' . $e->getMessage());
 return false;
 }
}

/**
 * Update course details.
 *
 * @param PDO $pdo
 * @param int $courseId
 * @param array $newData
 * @return bool
 */
function updateCourse(PDO $pdo, int $courseId, array $newData): bool
{
 $oldData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'courses', $courseId) : null;

 $sets = []; $params = [':_id' => $courseId];
 foreach ($newData as $k => $v) { $sets[] = "$k = :$k"; $params[":$k"] = $v; }

 try {
 $stmt = $pdo->prepare("UPDATE courses SET " . implode(', ', $sets) . " WHERE id = :_id");
 $stmt->execute($params);
 $affected = $stmt->rowCount() > 0;

 if ($affected) {
 $afterData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'courses', $courseId) : null;
 _safeAuditLog($pdo, 'UPDATE', 'COURSE', $courseId, $oldData, $afterData, "Course #$courseId updated.");
 }
 return $affected;
 } catch (PDOException $e) {
 error_log('[actions] updateCourse: ' . $e->getMessage());
 return false;
 }
}

/**
 * Delete a course.
 */
function deleteCourse(PDO $pdo, int $courseId): bool
{
 $oldData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'courses', $courseId) : null;
 try {
 $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
 $stmt->execute([':id' => $courseId]);
 $deleted = $stmt->rowCount() > 0;

 if ($deleted) {
 _safeAuditLog($pdo, 'DELETE', 'COURSE', $courseId, $oldData, null,
 "Course #$courseId (" . ($oldData['course_name'] ?? '') . ") deleted.");
 }
 return $deleted;
 } catch (PDOException $e) {
 error_log('[actions] deleteCourse: ' . $e->getMessage());
 return false;
 }
}

/**
 * Assign a course to an instructor.
 *
 * @param PDO $pdo
 * @param int $courseId
 * @param int $instructorId
 * @return bool
 */
function assignCourse(PDO $pdo, int $courseId, int $instructorId): bool
{
 $oldData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'courses', $courseId) : null;

 try {
 $stmt = $pdo->prepare("UPDATE courses SET instructor_id = :iid WHERE id = :cid");
 $stmt->execute([':iid' => $instructorId, ':cid' => $courseId]);
 $ok = $stmt->rowCount() > 0;

 if ($ok) {
 $afterData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'courses', $courseId) : null;
 _safeAuditLog(
 $pdo, 'ASSIGN', 'COURSE', $courseId,
 $oldData, $afterData,
 "Course #$courseId assigned to instructor #$instructorId."
 );
 }
 return $ok;
 } catch (PDOException $e) {
 error_log('[actions] assignCourse: ' . $e->getMessage());
 return false;
 }
}

// إدارة الامتحانات

/**
 * Create a new online exam session.
 *
 * @param PDO $pdo
 * @param array $data title, course_id, instructor_id, duration_minutes, total_marks, ...
 * @return int|false
 */
function createExam(PDO $pdo, array $data): int|false
{
 $data['created_at'] = date('Y-m-d H:i:s');

 $cols = array_keys($data);
 $marks = array_map(fn($c) => ':' . $c, $cols);
 $sql = "INSERT INTO exam_sessions (" . implode(',', $cols) . ") VALUES (" . implode(',', $marks) . ") RETURNING id";

 try {
 $stmt = $pdo->prepare($sql);
 foreach ($data as $k => $v) $stmt->bindValue(':' . $k, $v);
 $stmt->execute();
 $row = $stmt->fetch();
 $newId = $row ? (int)$row['id'] : false;

 if ($newId) {
 _safeAuditLog(
 $pdo, 'ADD', 'EXAM', $newId,
 null, $data,
 "Exam created: " . ($data['title'] ?? "Exam #$newId") . " for course #" . ($data['course_id'] ?? '?')
 );
 }
 return $newId;
 } catch (PDOException $e) {
 error_log('[actions] createExam: ' . $e->getMessage());
 return false;
 }
}

/**
 * Delete an exam session.
 */
function deleteExam(PDO $pdo, int $examId): bool
{
 $oldData = function_exists('auditFetchSnapshot') ? auditFetchSnapshot($pdo, 'exam_sessions', $examId) : null;
 try {
 $stmt = $pdo->prepare("DELETE FROM exam_sessions WHERE id = :id");
 $stmt->execute([':id' => $examId]);
 $deleted = $stmt->rowCount() > 0;

 if ($deleted) {
 _safeAuditLog($pdo, 'DELETE', 'EXAM', $examId, $oldData, null,
 "Exam session #$examId (" . ($oldData['title'] ?? '') . ") deleted.");
 }
 return $deleted;
 } catch (PDOException $e) {
 error_log('[actions] deleteExam: ' . $e->getMessage());
 return false;
 }
}

// ════════════════════════════════════════════════════════════════
// ENROLLMENT
// ════════════════════════════════════════════════════════════════

/**
 * Enroll a student in a course (or update enrollment status).
 *
 * @param PDO $pdo
 * @param int $studentId
 * @param int $courseId
 * @param string $status 'enrolled' | 'dropped' | 'pending'
 * @return int|false Enrollment ID
 */
function createEnrollment(PDO $pdo, int $studentId, int $courseId, string $status = 'enrolled'): int|false
{
 try {
 $stmt = $pdo->prepare("
 INSERT INTO enrollments (student_id, course_id, status, created_at)
 VALUES (:sid, :cid, :status, NOW())
 ON CONFLICT (student_id, course_id)
 DO UPDATE SET status = EXCLUDED.status
 RETURNING id
 ");
 $stmt->execute([':sid' => $studentId, ':cid' => $courseId, ':status' => $status]);
 $row = $stmt->fetch();
 $newId = $row ? (int)$row['id'] : false;

 if ($newId) {
 _safeAuditLog(
 $pdo, 'ASSIGN', 'ENROLLMENT', $newId,
 null,
 ['student_id' => $studentId, 'course_id' => $courseId, 'status' => $status],
 "Student #$studentId enrolled in course #$courseId (status: $status)."
 );
 }
 return $newId;
 } catch (PDOException $e) {
 error_log('[actions] createEnrollment: ' . $e->getMessage());
 return false;
 }
}

// ════════════════════════════════════════════════════════════════
// ATTENDANCE
// ════════════════════════════════════════════════════════════════

/**
 * Mark / update an attendance record for a student.
 *
 * @param PDO $pdo
 * @param int $sessionId
 * @param int $studentId
 * @param string $status 'present' | 'absent' | 'late'
 * @return bool
 */
function markAttendance(PDO $pdo, int $sessionId, int $studentId, string $status): bool
{
 // Capture existing record before change
 $oldRow = null;
 try {
 $chk = $pdo->prepare(
 "SELECT * FROM attendance_records WHERE session_id = :sid AND student_id = :stid LIMIT 1"
 );
 $chk->execute([':sid' => $sessionId, ':stid' => $studentId]);
 $oldRow = $chk->fetch() ?: null;
 } catch (PDOException $e) { /* table might not exist */ }

 $action = $oldRow ? 'UPDATE' : 'ADD';

 try {
 $stmt = $pdo->prepare("
 INSERT INTO attendance_records (session_id, student_id, status, marked_at)
 VALUES (:sid, :stid, :status, NOW())
 ON CONFLICT (session_id, student_id)
 DO UPDATE SET status = EXCLUDED.status, marked_at = NOW()
 RETURNING id
 ");
 $stmt->execute([':sid' => $sessionId, ':stid' => $studentId, ':status' => $status]);
 $row = $stmt->fetch();
 $ok = (bool)$row;

 if ($ok) {
 _safeAuditLog(
 $pdo, $action, 'ATTENDANCE', $sessionId,
 $oldRow ? ['status' => $oldRow['status']] : null,
 ['status' => $status, 'student_id' => $studentId],
 "Attendance for student #$studentId in session #$sessionId set to '$status'."
 );
 }
 return $ok;
 } catch (PDOException $e) {
 error_log('[actions] markAttendance: ' . $e->getMessage());
 return false;
 }
}

// ════════════════════════════════════════════════════════════════
// EXAM SUBMISSION
// ════════════════════════════════════════════════════════════════

/**
 * Record a student's exam submission.
 *
 * @param PDO $pdo
 * @param int $examId
 * @param int $studentId
 * @param array $answers [question_id => answer, ...]
 * @return int|false Submission ID
 */
function submitExam(PDO $pdo, int $examId, int $studentId, array $answers): int|false
{
 try {
 $stmt = $pdo->prepare("
 INSERT INTO exam_submissions (exam_id, student_id, answers, submitted_at)
 VALUES (:eid, :sid, :ans, NOW())
 RETURNING id
 ");
 $stmt->execute([
 ':eid' => $examId,
 ':sid' => $studentId,
 ':ans' => json_encode($answers, JSON_UNESCAPED_UNICODE),
 ]);
 $row = $stmt->fetch();
 $subId = $row ? (int)$row['id'] : false;

 if ($subId) {
 _safeAuditLog(
 $pdo, 'ADD', 'EXAM_SUBMISSION', $subId,
 null,
 ['exam_id' => $examId, 'student_id' => $studentId, 'answer_count' => count($answers)],
 "Student #$studentId submitted exam #$examId (submission #$subId)."
 );
 }
 return $subId;
 } catch (PDOException $e) {
 error_log('[actions] submitExam: ' . $e->getMessage());
 return false;
 }
}

// إدارة الدرجات

/**
 * Submit/update a student grade.
 *
 * @param PDO $pdo
 * @param int $studentId
 * @param int $courseId
 * @param array $gradeData [midterm, final, total, grade, ...]
 * @return bool
 */
function submitGrade(PDO $pdo, int $studentId, int $courseId, array $gradeData): bool
{
 // Fetch current grade record
 $oldData = null;
 try {
 $chk = $pdo->prepare("SELECT * FROM grades WHERE student_id = :sid AND course_id = :cid LIMIT 1");
 $chk->execute([':sid' => $studentId, ':cid' => $courseId]);
 $oldData = $chk->fetch() ?: null;
 } catch (PDOException $e) {}

 $action = $oldData ? 'UPDATE' : 'ADD';

 $gradeData['student_id'] = $studentId;
 $gradeData['course_id'] = $courseId;
 $gradeData['updated_at'] = date('Y-m-d H:i:s');

 $cols = array_keys($gradeData);
 $marks = array_map(fn($c) => ':' . $c, $cols);
 $updates = array_map(fn($c) => "$c = EXCLUDED.$c", array_diff($cols, ['student_id','course_id']));

 $sql = "INSERT INTO grades (" . implode(',', $cols) . ")
 VALUES (" . implode(',', $marks) . ")
 ON CONFLICT (student_id, course_id)
 DO UPDATE SET " . implode(', ', $updates) . "
 RETURNING id";

 try {
 $stmt = $pdo->prepare($sql);
 foreach ($gradeData as $k => $v) $stmt->bindValue(':' . $k, $v);
 $stmt->execute();
 $row = $stmt->fetch();
 $ok = (bool)$row;

 if ($ok) {
 _safeAuditLog(
 $pdo, $action, 'GRADE', (int)($row['id'] ?? 0),
 $oldData,
 array_intersect_key($gradeData, array_flip(['midterm','final','total','grade'])),
 "Grade for student #$studentId in course #$courseId " . ($action === 'ADD' ? 'submitted' : 'updated') . "."
 );
 }
 return $ok;
 } catch (PDOException $e) {
 error_log('[actions] submitGrade: ' . $e->getMessage());
 return false;
 }
}

// إعدادات السيستم

/**
 * Update a system setting and log it as SYSTEM_CHANGE.
 *
 * @param PDO $pdo
 * @param string $key
 * @param string $value
 * @param int|null $collegeId
 * @return bool
 */
function updateSystemSetting(PDO $pdo, string $key, string $value, ?int $collegeId = null): bool
{
 // Fetch old value
 $oldVal = null;
 try {
 $chk = $pdo->prepare(
 "SELECT value FROM settings WHERE key = :key AND " .
 ($collegeId ? "college_id = :cid" : "college_id IS NULL") .
 " LIMIT 1"
 );
 $params = [':key' => $key];
 if ($collegeId) $params[':cid'] = $collegeId;
 $chk->execute($params);
 $oldVal = $chk->fetchColumn();
 } catch (PDOException $e) {}

 try {
 $stmt = $pdo->prepare("
 INSERT INTO settings (key, value, college_id)
 VALUES (:key, :value, :cid)
 ON CONFLICT (college_id, key)
 DO UPDATE SET value = EXCLUDED.value
 ");
 $stmt->execute([':key' => $key, ':value' => $value, ':cid' => $collegeId]);

 _safeAuditLog(
 $pdo, 'SYSTEM_CHANGE', 'SYSTEM', null,
 $oldVal !== false ? ['key' => $key, 'value' => $oldVal] : null,
 ['key' => $key, 'value' => $value],
 "System setting '$key' changed to '$value'."
 );
 return true;
 } catch (PDOException $e) {
 error_log('[actions] updateSystemSetting: ' . $e->getMessage());
 return false;
 }
}
