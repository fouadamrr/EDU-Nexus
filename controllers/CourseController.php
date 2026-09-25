<?php
require_once __DIR__ . '/../models/Course.php';

class CourseController {
 
 public function handleRequest(array $postData, ?string $action): ?string {
 $courseModel = new Course();
 
 if ($action === 'add') {
 $code = strtoupper(trim($postData['code'] ?? ''));
 $name = trim($postData['name'] ?? '');
 $credit = (int)($postData['credit'] ?? 3);
 
 // New Grade regulations
 $theory_marks = (int)($postData['theory_marks'] ?? 60);
 $coursework_marks = (int)($postData['coursework_marks'] ?? 20);
 $practical_marks = (int)($postData['practical_marks'] ?? 20);

 $exists = $courseModel->findOneBy('code', $code);
 if ($exists) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">كود المقرر موجود بالفعل</div>';
 } else {
 $target_college = null;
 if (in_array($_SESSION['role'] ?? '', ['dean', 'affairs'])) {
 $target_college = $_SESSION['college_id'] ?? null;
 } else {
 $target_college = ($postData['college_id_select'] ?? null) ?: null;
 }
 
 if (!$target_college) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">يجب تحديد الكلية التابع لها المقرر</div>';
 }
 
 $courseModel->insert([
 'code' => $code,
 'name' => $name,
 'credit_hours' => $credit,
 'level' => (int)($postData['level'] ?? 0),
 'instructor_id' => ($postData['instructor_id'] ?? null) ?: null,
 'college_id' => $target_college,
 'theory_marks' => $theory_marks,
 'coursework_marks' => $coursework_marks,
 'practical_marks' => $practical_marks,
 ]);
 return '<div class="bg-primary text-white p-3 rounded mb-4">تم إضافة المقرر بنجاح</div>';
 }
 }

 if ($action === 'edit') {
 $id = (int)($postData['id'] ?? 0);
 if ($id <= 0) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">خطأ: رقم المقرر غير صحيح</div>';
 }
 $code = strtoupper(trim($postData['code'] ?? ''));
 $name = trim($postData['name'] ?? '');
 $credit = (int)($postData['credit'] ?? 3);

 $theory_marks = (int)($postData['theory_marks'] ?? 60);
 $coursework_marks = (int)($postData['coursework_marks'] ?? 20);
 $practical_marks = (int)($postData['practical_marks'] ?? 20);
 $instructor_id = (!empty($postData['instructor_id'])) ? (int)$postData['instructor_id'] : null;
 
 try {
 global $pdo;
 $sets = [];
 $params = [
 ':code' => $code,
 ':name' => $name,
 ':credit_hours' => $credit,
 ':level' => (int)($postData['level'] ?? 0),
 ':theory_marks' => $theory_marks,
 ':coursework_marks' => $coursework_marks,
 ':practical_marks' => $practical_marks,
 ':id' => $id,
 ];

 if ($instructor_id !== null) {
 $params[':instructor_id'] = $instructor_id;
 $sets[] = 'instructor_id = :instructor_id';
 } else {
 $sets[] = 'instructor_id = NULL';
 }

 if (in_array($_SESSION['role'] ?? '', ['admin', 'super_admin']) && !empty($postData['college_id_select'])) {
 $params[':college_id'] = (int)$postData['college_id_select'];
 $sets[] = 'college_id = :college_id';
 }

 $updateSql = "UPDATE courses SET 
 code = :code, name = :name, credit_hours = :credit_hours,
 level = :level, theory_marks = :theory_marks,
 coursework_marks = :coursework_marks, practical_marks = :practical_marks,
 " . implode(', ', $sets) . "
 WHERE id = :id";

 $stmt = $pdo->prepare($updateSql);
 $stmt->execute($params);
 return '<div class="bg-accent text-white p-3 rounded mb-4">✅ تم تعديل المقرر بنجاح</div>';
 } catch (Exception $e) {
 error_log('CourseController edit error: ' . $e->getMessage());
 return '<div class="bg-accent text-white p-3 rounded mb-4">خطأ أثناء التعديل: ' . htmlspecialchars($e->getMessage()) . '</div>';
 }
 }

 if ($action === 'delete' && isset($postData['delete'])) {
 $id = (int)$postData['delete'];
 if ($courseModel->delete($id)) {
 return '<div class="bg-secondary text-white p-3 rounded mb-4">تم حذف المقرر بنجاح</div>';
 } else {
 return '<div class="bg-secondary text-white p-3 rounded mb-4">فشل الحذف</div>';
 }
 }

 return null;
 }

 public function getCoursesList(?int $selected_college_id): array {
 global $pdo;
 $courses = [];
 
 try {
 if ($selected_college_id > 0) {
 // Course belongs strictly to this college
 $stmt = $pdo->prepare("SELECT * FROM courses WHERE college_id = :cid ORDER BY id DESC");
 $stmt->execute([':cid' => $selected_college_id]);
 $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
 } else {
 // Admin viewing ALL courses
 $stmt = $pdo->query("SELECT * FROM courses ORDER BY id DESC");
 $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
 }
 } catch (Exception $e) {
 error_log("Error fetching courses: " . $e->getMessage());
 }
 
 return $courses;
 }
}
