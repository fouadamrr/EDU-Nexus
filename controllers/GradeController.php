<?php
require_once __DIR__ . '/../models/Grade.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Course.php';
if (!function_exists('auditLogAuto')) {
	require_once __DIR__ . '/../includes/audit.php';
}

class GradeController {
 public function getCourseDetails(int $course_id): ?array {
 $course = (new Course())->findById($course_id);
 
 $role = $_SESSION['role'] ?? '';
 $college_id = $_SESSION['college_id'] ?? 0;
 
 if ($course && in_array($role, ['dean', 'affairs']) && $college_id > 0) {
 // تأمين: لازم المادة تكون تابعة لكلية العميد اللي داخل، ممنوع يشوف مواد كليات تانية
 if ($course['college_id'] != $college_id) {
 return null;
 }
 }
 
 return $course;
 }

 public function getEnrolledStudents(int $course_id): array {
 $enrollmentModel = new Enrollment();
 $userModel = new User();
 
 $role = $_SESSION['role'] ?? '';
 $college_id = $_SESSION['college_id'] ?? 0;
 
 $enrollments = $enrollmentModel->findAll(['course_id' => $course_id]);
 $students = [];
 foreach ($enrollments as $e) {
 $s = $userModel->findById($e['user_id']);
 if ($s) {
 // شئون الطلاب والعميد بيشوفوا طلبة كليتهم بس
 if (in_array($role, ['dean', 'affairs']) && $college_id > 0) {
 if ($s['college_id'] != $college_id) continue;
 }
 $students[] = $s;
 }
 }
 return $students;
 }

 public function getExistingGrades(int $course_id): array {
 $grades = (new Grade())->findAll(['course_id' => $course_id]);
 $grades_map = [];
 foreach ($grades as $g) {
 $grades_map[$g['user_id']] = $g;
 }
 return $grades_map;
 }

 public function handleRequest(array $postData, int $course_id, string $role): ?string {
 $gradeModel = new Grade();
 $message = null;

 // منطق الحفظ المشترك بين الحفظ العادي والاعتماد النهائي للكل
 $process_save = isset($postData['save_grades']) || isset($postData['batch_approve']);
 
 if ($process_save) {
 $submitted_grades = $postData['grades'] ?? [];
 $count = 0;
 $grades_map = $this->getExistingGrades($course_id);
 
 // بنحسب الدرجة النهائية للمادة من توزيع الدرجات بتاعها
 $course = (new Course())->findById($course_id);
 $total_m = (int)($course['theory_marks'] ?? 60) + (int)($course['coursework_marks'] ?? 20) + (int)($course['practical_marks'] ?? 20);

 foreach ($submitted_grades as $user_id => $data) {
 $cw = isset($data['coursework']) && $data['coursework'] !== '' ? (float)$data['coursework'] : null;
 $pr = isset($data['practical']) && $data['practical'] !== '' ? (float)$data['practical'] : null;
 $th = isset($data['theory']) && $data['theory'] !== '' ? (float)$data['theory'] : null;
 
 // لو الطالب مبعتش ليه أي درجات، بنعديه عشان ميمسحش الدرجات اللي اتسجلت قبل كدا
 if ($cw === null && $pr === null && $th === null && !isset($data['status'])) {
 continue; 
 }

 $existing = $grades_map[$user_id] ?? null;

 // بندمج الدرجات الجديدة مع القديمة لو بنسجل جزء بجزء
 if ($existing) {
 $cw = ($cw === null) ? $existing['coursework_score'] : $cw;
 $pr = ($pr === null) ? $existing['practical_score'] : $pr;
 $th = ($th === null) ? $existing['theory_score'] : $th;
 }

 $total_student = (float)$cw + (float)$pr + (float)$th;
 
 $grade_letter = '';
 if ($total_m > 0) {
 $percent = ($total_student / $total_m) * 100;
 $grade_letter = 'F';
 if ($percent >= 90) $grade_letter = 'A';
 elseif ($percent >= 85) $grade_letter = 'A-';
 elseif ($percent >= 80) $grade_letter = 'B+';
 elseif ($percent >= 75) $grade_letter = 'B';
 elseif ($percent >= 70) $grade_letter = 'B-';
 elseif ($percent >= 65) $grade_letter = 'C+';
 elseif ($percent >= 60) $grade_letter = 'C';
 elseif ($percent >= 56) $grade_letter = 'C-';
 elseif ($percent >= 53) $grade_letter = 'D+';
 elseif ($percent >= 50) $grade_letter = 'D';
 }

 $default_status = ($role === 'instructor') ? 'pending' : 'approved';
 $status = $data['status'] ?? $default_status;
 
 // لو داس على زرار الاعتماد، بنخلي الحالة معتمد أوتوماتيك
 if (isset($postData['batch_approve'])) {
 $status = 'approved';
 }

 $points_map = [
 'A' => 4.0, 'A-' => 3.7, 'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
 'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7, 'D+' => 1.3, 'D' => 1.0, 'F' => 0.0
 ];
 $points = $points_map[$grade_letter] ?? 0.0;

 $new_grade_data = [
 'coursework_score' => $cw,
 'practical_score'  => $pr,
 'theory_score'     => $th,
 'grade'            => $grade_letter,
 'points'           => $points,
 'status'           => $status
 ];

 if ($existing) {
 // ── Audit: تسجيل تعديل درجة طالب (أخطر عملية في السيستم) ──
 global $pdo;
 $old_grade_data = array_intersect_key($existing, array_flip(['coursework_score','practical_score','theory_score','grade','points','status']));
 auditLogAuto($pdo, 'UPDATE', 'GRADE', (int)$existing['id'],
 $old_grade_data,
 $new_grade_data,
 "تعديل درجة الطالب ID:{$user_id} في المادة ID:{$course_id} | التقدير: {$grade_letter}"
 );
 $gradeModel->update($existing['id'], $new_grade_data);
 } else {
 $insert_data = array_merge(['user_id' => $user_id, 'course_id' => $course_id, 'semester' => 'Spring 2026'], $new_grade_data);
 $new_grade_id = $gradeModel->insert($insert_data);
 // ── Audit: تسجيل إضافة درجة جديدة لطالب ──
 global $pdo;
 auditLogAuto($pdo, 'ADD', 'GRADE', is_numeric($new_grade_id) ? (int)$new_grade_id : null,
 null,
 array_merge(['user_id' => $user_id, 'course_id' => $course_id], $new_grade_data),
 "إضافة درجة جديدة للطالب ID:{$user_id} في المادة ID:{$course_id} | التقدير: {$grade_letter}"
 );
 }
 $count++;
 }

 if (isset($postData['batch_approve'])) {
 $message = '<div class="bg-bg text-primary p-4 rounded-xl mb-6 shadow-sm border border-primary flex items-center gap-3"><i class="fas fa-check-double"></i> تم حفظ واعتماد جميع الدرجات بنجاح لعدد (' . $count . ') طالباً</div>';
 } else {
 $message = '<div class="bg-bg text-primary p-4 rounded-xl mb-6 shadow-sm border border-primary flex items-center gap-3"><i class="fas fa-check-circle"></i> تم حفظ الدرجات بنجاح لنقر (' . $count . ') طالباً</div>';
 }
 }

 return $message;
 }
}
