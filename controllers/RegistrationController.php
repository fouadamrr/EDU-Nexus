<?php
require_once __DIR__ . '/../models/Setting.php';
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/Enrollment.php';

class RegistrationController {

 /**
 * Check if registration period is open system-wide.
 */
 public function isRegistrationOpen(int $student_id = 0): bool {
 $db = Database::getConnection();
 
 // 1. Check for global setting first (college_id is NULL or 0)
 $stmt = $db->query("SELECT value FROM settings WHERE key = 'registration_open' AND (college_id IS NULL OR college_id = 0) LIMIT 1");
 $global_open = $stmt->fetchColumn();
 if ($global_open === '1') return true;
 
 // 2. If global is closed, check if it's specifically open for the student's college
 if ($student_id > 0) {
 $stmt_s = $db->prepare("SELECT college_id FROM users WHERE id = ? LIMIT 1");
 $stmt_s->execute([$student_id]);
 $cid = (int)$stmt_s->fetchColumn();
 
 if ($cid > 0) {
 $stmt_c = $db->prepare("SELECT value FROM settings WHERE key = 'registration_open' AND college_id = ? LIMIT 1");
 $stmt_c->execute([$cid]);
 return $stmt_c->fetchColumn() === '1';
 }
 }
 
 return false;
 }

 /**
 * Get student's academic level from students table.
 */
 public function getStudentLevel(int $user_id): int {
 $db = Database::getConnection();
 $stmt = $db->prepare("SELECT level FROM students WHERE user_id = ? LIMIT 1");
 $stmt->execute([$user_id]);
 return (int)($stmt->fetchColumn() ?: 1);
 }

 /**
 * Get GPA, Advisor Name, and calculated Max Hours.
 */
 public function getStudentAcademicInfo(int $user_id): array {
 $db = Database::getConnection();
 
 // Fetch GPA and Advisor ID
 $stmt = $db->prepare("
 SELECT s.gpa, s.doctor_id, u.full_name as advisor_name 
 FROM students s 
 LEFT JOIN users u ON s.doctor_id = u.id 
 WHERE s.user_id = ? LIMIT 1
 ");
 $stmt->execute([$user_id]);
 $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

 $gpa = (float)($data['gpa'] ?? 0.0);
 $advisor = $data['advisor_name'] ?? 'غير محدد';

 // Dynamic Limit Logic
 // GPA >= 3.0 -> 21
 // GPA >= 2.0 -> 18
 // GPA < 2.0 -> 14
 if ($gpa >= 3.0) {
 $max_hours = 21;
 } elseif ($gpa >= 2.0) {
 $max_hours = 18;
 } else {
 $max_hours = 14;
 }

 return [
 'gpa' => $gpa,
 'advisor_name' => $advisor,
 'max_hours' => $max_hours
 ];
 }

 /**
 * Get all courses grouped by level, flagging which are
 * permitted via admin override for this student.
 * Returns: array of courses with 'is_permitted' flag.
 */
 public function getAvailableCourses(int $student_id): array {
 $db = Database::getConnection();

 // 1. Get the student's college_id first
 $stmt_s = $db->prepare("SELECT college_id FROM users WHERE id = ? LIMIT 1");
 $stmt_s->execute([$student_id]);
 $student_college_id = (int)($stmt_s->fetchColumn() ?: 0);

 // Auto-create table if it doesn't exist yet (idempotent)
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

 // 2. Get courses specifically for this student's college
 // We exclude courses where the student has already passed (Grade != 'F' and status = 'approved')
 $stmt = $db->prepare("
 SELECT c.*,
 COALESCE(u.full_name, 'غير محدد') AS doctor,
 CASE WHEN rp.id IS NOT NULL THEN 1 ELSE 0 END AS is_permitted
 FROM courses c
 LEFT JOIN users u ON c.instructor_id = u.id
 LEFT JOIN registration_permissions rp
 ON c.id = rp.course_id AND rp.student_id = :sid
 WHERE c.college_id = :cid
 AND c.id NOT IN (
 SELECT course_id FROM grades 
 WHERE user_id = :sid AND grade != 'F' AND status = 'approved'
 )
 ORDER BY c.level ASC, c.name ASC
 ");
 $stmt->execute([':sid' => $student_id, ':cid' => $student_college_id]);
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 }


 /**
 * Get course IDs a student is enrolled in for a given semester.
 */
 public function getCurrentEnrollments(int $user_id, string $semester): array {
 $enrollments = (new Enrollment())->findAll(['user_id' => $user_id, 'semester' => $semester]);
 return array_map(fn($e) => (int)($e['course_id'] ?? 0), $enrollments);
 }

 /**
 * Get list of admin-permitted (opened) course IDs for a student.
 */
 public function getPermittedCourseIds(int $student_id): array {
 $db = Database::getConnection();
 $stmt = $db->prepare("SELECT course_id FROM registration_permissions WHERE student_id = ?");
 $stmt->execute([$student_id]);
 return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
 }

 /**
 * Save registration for a student.
 * Admin roles can bypass the registration_open flag.
 * Validation: students can only register their own level's courses
 * OR courses explicitly opened for them by admin.
 */
 public function handleRegistration(
 array $postData,
 int $user_id,
 string $semester,
 bool $is_open,
 array $available_courses,
 string $role = 'student',
 int $student_level = 1
 ): array {

 $can_bypass = in_array($role, ['super_admin', 'admin', 'dean', 'affairs']);

 if (!$is_open && !$can_bypass) {
 return [
 'success' => false,
 'message' => '<div class="bg-bg border border-primary text-primary p-4 rounded-lg font-bold mb-6 text-right">⛔ عذراً، فترة التسجيل مغلقة حالياً. تواصل مع شؤون الطلاب.</div>'
 ];
 }

 $course_ids = $postData['course_ids'] ?? [];
 if (!is_array($course_ids)) $course_ids = [];
 $course_ids = array_values(array_unique(array_map('intval', $course_ids)));
 $course_ids = array_filter($course_ids, fn($id) => $id > 0);

 if (count($course_ids) === 0) {
 return [
 'success' => false,
 'message' => '<div class="bg-bg border border-primary text-primary p-4 rounded-lg font-bold mb-6 text-right">⚠️ الرجاء اختيار مقرر واحد على الأقل قبل الحفظ.</div>'
 ];
 }

 // Build lookup for available courses
 $all_courses_by_id = [];
 $permitted_ids = $this->getPermittedCourseIds($user_id);
 foreach ($available_courses as $c) {
 if (isset($c['id'])) $all_courses_by_id[(int)$c['id']] = $c;
 }

 // 3. Validate Credit Hours Limit
 $academic_info = $this->getStudentAcademicInfo($user_id);
 $max_hours = $academic_info['max_hours'];
 
 $total_requested_hours = 0;
 $valid_ids = [];
 $rejected = [];
 
 foreach ($course_ids as $cid) {
 if (isset($all_courses_by_id[$cid])) {
 $total_requested_hours += (int)($all_courses_by_id[$cid]['credit_hours'] ?? 0);
 $valid_ids[] = $cid;
 } else {
 $rejected[] = $cid;
 }
 }

 if ($total_requested_hours > $max_hours && !$can_bypass) {
 return [
 'success' => false,
 'message' => '<div class="bg-bg border border-primary text-primary p-4 rounded-lg font-bold mb-6 text-right">❌ تم تجاوز الحد الأقصى للساعات المسموح به (' . $max_hours . ' ساعة) بناءً على معدلك التراكمي.</div>'
 ];
 }

 if (count($valid_ids) === 0) {
 return [
 'success' => false,
 'message' => '<div class="bg-bg border border-primary text-primary p-4 rounded-lg font-bold mb-6 text-right">❌ المقررات المختارة غير صالحة أو غير مخصصة لك حالياً.</div>'
 ];
 }

 // Save enrollments (delete old, insert new)
 $db = Database::getConnection();
 $stmt = $db->prepare("DELETE FROM enrollments WHERE user_id = ? AND semester = ?");
 $stmt->execute([$user_id, $semester]);

 $enrollmentModel = new Enrollment();
 foreach ($valid_ids as $cid) {
 $enrollmentModel->insert([
 'user_id' => $user_id,
 'course_id' => $cid,
 'semester' => $semester
 ]);
 }

 $warning = '';
 if (!empty($rejected)) {
 $warning = '<div class="bg-bg border border-primary text-secondary p-3 rounded-lg text-sm mb-2 text-right">⚠️ تم تجاهل المقررات التالية لأنها لا تتوافق مع مستواك ولم يُفتح لك تسجيلها: <strong>' . implode('، ', $rejected) . '</strong></div>';
 }

 return [
 'success' => true,
 'message' => $warning . '<div class="bg-bg border border-primary text-primary p-4 rounded-lg font-bold mb-6 text-right">✅ تم حفظ المقررات بنجاح! يمكنك الآن طباعة الاستمارة.</div>',
 'course_ids' => $valid_ids
 ];
 }
}
