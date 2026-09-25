<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../models/Grade.php';
require_once __DIR__ . '/../models/Course.php';

class ResultsController {
 
 public function getTargetStudent(string $role, int $user_id, string $full_name, string $username, ?int $requested_id = null): array {
 $target_user_id = $user_id;
 $target_full_name = $full_name;
 $target_username = $username;

 if (in_array($role, ['super_admin', 'admin', 'dean', 'affairs']) && $requested_id > 0) {
 $userModel = new User();
 $student = $userModel->findById($requested_id);
 if ($student && ($student['role'] ?? '') === 'student') {
 $target_user_id = $student['id'];
 $target_full_name = $student['full_name'] ?? $student['username'];
 $target_username = $student['username'] ?? '';
 }
 }

 return [
 'id' => $target_user_id,
 'full_name' => $target_full_name,
 'username' => $target_username
 ];
 }

 public function getStudentResults(int $target_user_id, string $role = 'student'): array {
 $enrollmentModel = new Enrollment();
 $gradeModel = new Grade();
 $courseModel = new Course();

 $enrollments = $enrollmentModel->findAll(['user_id' => $target_user_id]);
 $all_grades = $gradeModel->findAll(['user_id' => $target_user_id]);

 $grades_index = [];
 foreach ($all_grades as $g) {
 $key = ($g['course_id'] ?? '') . '|' . ($g['semester'] ?? '');
 $grades_index[$key] = $g;
 }

 $semesters = [];
 if (!empty($enrollments)) {
 foreach ($enrollments as $en) {
 $course_id = $en['course_id'] ?? null;
 if (!$course_id) continue;
 
 $course = $courseModel->findById($course_id);
 if (!$course) continue;

 $sem = $en['semester'] ?? 'غير محدد';
 $grade_key = $course_id . '|' . $sem;
 $g = $grades_index[$grade_key] ?? null;
 
 $display_grade = '';
 $display_points = '';
 
 // Admins, Deans and Affairs see everything. Students and Instructors (on results page) see only approved.
 $can_see_pending = in_array($role, ['super_admin', 'admin', 'dean', 'affairs']);
 
 if ($g && isset($g['status'])) {
 if ($g['status'] === 'approved' || $can_see_pending) {
 $display_grade = $g['grade'] ?? '';
 $display_points = $g['points'] ?? '';
 
 // Add a (Pending) label if it's not approved but visible to admin
 if ($g['status'] !== 'approved' && $can_see_pending) {
 $display_grade .= ' (قيد الاعتماد)';
 }
 }
 }

 $row = [
 'code' => $course['code'] ?? '',
 'name' => $course['name'] ?? '',
 'credit_hours' => $course['credit_hours'] ?? 0,
 'semester' => $sem,
 'grade' => $display_grade,
 'points' => $display_points
 ];

 $semesters[$sem][] = $row;
 }
 }
 return $semesters;
 }
}
