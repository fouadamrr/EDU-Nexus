<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Exam.php';
require_once __DIR__ . '/../models/Book.php';
require_once __DIR__ . '/../models/Student.php';

class InstructorDashboardController {
 
 public function __construct() {
 try {
 $db = Database::getConnection();
 $db->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS doctor_id INT");
 $db->exec("ALTER TABLE exams ADD COLUMN IF NOT EXISTS doctor_id INT");
 $db->exec("ALTER TABLE resources ADD COLUMN IF NOT EXISTS doctor_id INT");
 $db->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS doctor_id INT");
 } catch (Exception $e) {
 // Ignore if column already exists or other error occurs
 }
 }
 
 public function getDashboardData(int $instructor_id, ?array $session_user): array {
 $userModel = new User();
 $studentModel = new Student();
 
 $fresh_user = $userModel->findById($instructor_id);
 if (!$fresh_user && $session_user) {
 $fresh_user = $session_user;
 }

 $display_name = $fresh_user['full_name'] ?? 'Doctor';
 $display_dept = $fresh_user['department'] ?? 'General';
 $display_subject = $fresh_user['subject'] ?? 'N/A';

 $trial_student = $userModel->findOneBy('username', 'trial_student');
 $trial_id = $trial_student['id'] ?? -1;

 $db = Database::getConnection();
 $stmt = $db->query("SELECT * FROM students");
 $all_students_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $my_students = [];
 if (!empty($all_students_details)) {
 foreach ($all_students_details as $detail) {
 $detail_doc_id = $detail['doctor_id'] ?? null;
 $detail_user_id = $detail['user_id'] ?? null;

 $is_my_student = ($detail_doc_id == $instructor_id);
 $is_trial = ($detail_user_id == $trial_id);

 if ($is_my_student || $is_trial) {
 $user_info = $userModel->findById($detail_user_id);
 if ($user_info) {
 $my_students[] = array_merge($detail, $user_info);
 }
 }
 }
 }

 usort($my_students, function ($a, $b) {
 return strcmp($a['username'] ?? '', $b['username'] ?? '');
 });

 $courseModel = new Course();
 $my_course_id = 1; 
 $all_courses = $courseModel->findAll();
 foreach($all_courses as $c) {
 $has_instructor = isset($c['instructor_id']) && $c['instructor_id'] == $instructor_id;
 $has_doctor = isset($c['doctor_id']) && $c['doctor_id'] == $instructor_id;
 
 if($has_instructor || $has_doctor) {
 $my_course_id = $c['id'];
 break;
 }
 }

 return [
 'display_name' => $display_name,
 'display_dept' => $display_dept,
 'display_subject' => $display_subject,
 'my_students' => $my_students,
 'my_course_id' => $my_course_id,
 ];
 }

 public function handleBookUpload(array $files, array $postData, int $instructor_id): string {
 $title = $postData['book_title'] ?? 'Untitled';
 $fileName = $files['book_file']['name'] ?? '';
 $tempPath = $files['book_file']['tmp_name'] ?? '';

 if ($fileName && $tempPath) {
 $uploadDir = __DIR__ . '/../uploads/books/';
 if (!file_exists($uploadDir)) {
 mkdir($uploadDir, 0777, true);
 }

 $targetPath = $uploadDir . $fileName;

 if (move_uploaded_file($tempPath, $targetPath)) {
 $bookModel = new Book();
 $bookModel->insert([
 'doctor_id' => $instructor_id,
 'book_title' => $title,
 'file_name' => $fileName,
 'upload_date' => date('Y-m-d')
 ]);
 return "<div class='bg-bg border border-primary text-primary p-4 rounded-xl shadow-sm flex items-center gap-3 mt-4'><i class='fas fa-check-circle text-lg'></i><span class='font-bold'>تم الرفع بنجاح. تمت إضافة الكتاب إلى قائمتك.</span></div>";
 } else {
 return "<div class='bg-bg border border-primary text-primary p-4 rounded-xl shadow-sm flex items-center gap-3 mt-4'><i class='fas fa-times-circle text-lg'></i><span class='font-bold'>فشل الرفع. حدث خطأ أثناء نقل الملف.</span></div>";
 }
 }
 return '';
 }

 public function handleExamCreation(array $postData, int $instructor_id): string {
 $examName = $postData['exam_name'] ?? 'Exam';
 $totalMarks = $postData['total_marks'] ?? 100;

 $examModel = new Exam();
 $examModel->insert([
 'doctor_id' => $instructor_id,
 'exam_name' => $examName,
 'total_marks' => $totalMarks,
 'date' => date('Y-m-d')
 ]);
 return "<div class='bg-bg text-primary border border-primary p-3 rounded-xl mb-5 text-sm font-bold flex items-center gap-2'><i class='fas fa-check'></i> تم إنشاء الاختبار بنجاح</div>";
 }

 public function getInstructorExams(int $instructor_id): array {
 $examModel = new Exam();
 return $examModel->findAll(['doctor_id' => $instructor_id]);
 }

 public function getInstructorBooks(int $instructor_id): array {
 $bookModel = new Book();
 $all_books = $bookModel->findAll();
 return array_filter($all_books, function ($b) use ($instructor_id) {
 return isset($b['doctor_id']) && (int)$b['doctor_id'] === (int)$instructor_id;
 });
 }

 public function getStudentAbsences(int $student_id, int $instructor_id): int {
 $attendanceModel = new Attendance();
 $absences = $attendanceModel->findAll([
 'student_id' => $student_id,
 'doctor_id' => $instructor_id,
 'status' => 'Absent'
 ]);
 return count($absences);
 }
}
