<?php
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Course.php';

class ManageEnrollmentsController {

 // ── Enrollments ──────────────────────────────────────────────────────────

 /**
 * Admin-forced enroll (bypasses registration_open flag).
 */
 public function handleEnrollRequest(array $postData): string {
 $student_id = (int)($postData['student_id'] ?? 0);
 $course_id = (int)($postData['course_id'] ?? 0);
 $semester = 'Spring 2026';

 if (!$student_id || !$course_id) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">خطأ: بيانات ناقصة</div>';
 }

 $enrollmentModel = new Enrollment();
 $existing = $enrollmentModel->findAll(['user_id' => $student_id, 'course_id' => $course_id]);
 if (!empty($existing)) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">الطالب مسجل بالفعل في هذا المقرر</div>';
 }

 $enrollmentModel->insert([
 'user_id' => $student_id,
 'course_id' => $course_id,
 'semester' => $semester,
 ]);
 return '<div class="bg-primary text-white p-3 rounded mb-4">✅ تم تسجيل الطالب بنجاح</div>';
 }

 public function handleDeleteRequest(int $id): string {
 if ((new Enrollment())->delete($id)) {
 return '<div class="bg-secondary text-white p-3 rounded mb-4">تم إلغاء تسجيل الطالب</div>';
 }
 return '';
 }

 // ── Registration Permissions ─────────────────────────────────────────────

 /**
 * Grant a student permission to register a specific course
 * (cross-level or during a closed registration period).
 */
 public function grantPermission(int $student_id, int $course_id, int $granted_by): string {
 if (!$student_id || !$course_id) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">خطأ: بيانات ناقصة</div>';
 }
 $db = Database::getConnection();
 try {
 $stmt = $db->prepare("
 INSERT INTO registration_permissions (student_id, course_id, granted_by)
 VALUES (?, ?, ?)
 ON CONFLICT (student_id, course_id) DO NOTHING
 ");
 $stmt->execute([$student_id, $course_id, $granted_by]);
 return '<div class="bg-primary text-white p-3 rounded mb-4">✅ تم فتح المادة للطالب بنجاح</div>';
 } catch (Exception $e) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
 }
 }

 /**
 * Revoke a previously granted permission.
 */
 public function revokePermission(int $student_id, int $course_id): string {
 $db = Database::getConnection();
 $stmt = $db->prepare("DELETE FROM registration_permissions WHERE student_id = ? AND course_id = ?");
 $stmt->execute([$student_id, $course_id]);
 return '<div class="bg-bg text-gray-700 p-3 rounded mb-4">🔒 تم إلغاء الإذن</div>';
 }

 /**
 * Get all active permissions enriched with student/course/granter names.
 */
 public function getAllPermissions(): array {
 $db = Database::getConnection();
 // Auto-create the table if it doesn't exist yet (safe to call multiple times)
 $db->exec("
 CREATE TABLE IF NOT EXISTS registration_permissions (
 id SERIAL PRIMARY KEY,
 student_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
 granted_by INT REFERENCES users(id) ON DELETE SET NULL,
 note TEXT,
 created_at TIMESTAMPTZ DEFAULT NOW(),
 UNIQUE (student_id, course_id)
 );
 CREATE INDEX IF NOT EXISTS idx_reg_perm_student ON registration_permissions(student_id);
 ");
 $sql = "
 SELECT rp.*,
 s.full_name AS student_name,
 c.name AS course_name,
 c.code AS course_code,
 g.full_name AS granted_by_name
 FROM registration_permissions rp
 JOIN users s ON rp.student_id = s.id
 JOIN courses c ON rp.course_id = c.id
 LEFT JOIN users g ON rp.granted_by = g.id
 ";
 
 $params = [];
 $role = $_SESSION['role'] ?? '';
 $college_id = $_SESSION['college_id'] ?? 0;
 if (in_array($role, ['dean', 'affairs']) && $college_id > 0) {
 $sql .= " WHERE s.college_id = :cid AND c.college_id = :cid";
 $params[':cid'] = $college_id;
 }
 
 $sql .= " ORDER BY rp.created_at DESC";
 $stmt = $db->prepare($sql);
 $stmt->execute($params);
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 }

 // ── Lists ─────────────────────────────────────────────────────────────────

 public function getStudents(string $search = ''): array {
 $db = Database::getConnection();
 $sql = "SELECT id, full_name, username, college_id
 FROM users
 WHERE role = 'student'";
 $params = [];
 
 $role = $_SESSION['role'] ?? '';
 $college_id = $_SESSION['college_id'] ?? 0;
 if (in_array($role, ['dean', 'affairs']) && $college_id > 0) {
 $sql .= " AND college_id = :cid";
 $params[':cid'] = $college_id;
 }

 if (!empty($search)) {
 $sql .= " AND (full_name ILIKE :s OR username ILIKE :s)";
 $params[':s'] = '%' . $search . '%';
 }
 $sql .= " ORDER BY full_name ASC LIMIT 200";
 $stmt = $db->prepare($sql);
 $stmt->execute($params);
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 }

 public function getCoursesWithLevel(): array {
 $db = Database::getConnection();
 $sql = "SELECT id, name, code, level FROM courses";
 $params = [];
 
 $role = $_SESSION['role'] ?? '';
 $college_id = $_SESSION['college_id'] ?? 0;
 if (in_array($role, ['dean', 'affairs']) && $college_id > 0) {
 $sql .= " WHERE college_id = :cid";
 $params[':cid'] = $college_id;
 }
 
 $sql .= " ORDER BY level, name";
 $stmt = $db->prepare($sql);
 $stmt->execute($params);
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 }

 public function getCourses(): array {
 return $this->getCoursesWithLevel();
 }

 public function getEnrichedEnrollments(): array {
 $enrollmentModel = new Enrollment();
 $userModel = new User();
 $courseModel = new Course();

 $enrollments = $enrollmentModel->findAll();
 $display = [];

 $role = $_SESSION['role'] ?? '';
 $college_id = $_SESSION['college_id'] ?? 0;

 foreach ($enrollments as $e) {
 $s = $userModel->findById($e['user_id']);
 $c = $courseModel->findById($e['course_id']);
 if ($s && $c) {
 if (in_array($role, ['dean', 'affairs']) && $college_id > 0) {
 if (($s['college_id'] ?? 0) != $college_id || ($c['college_id'] ?? 0) != $college_id) {
 continue;
 }
 }
 
 $e['student_name'] = $s['full_name'];
 $e['student_username'] = $s['username'];
 $e['course_name'] = $c['name'];
 $e['course_code'] = $c['code'];
 $display[] = $e;
 }
 }
 usort($display, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
 return $display;
 }
}
