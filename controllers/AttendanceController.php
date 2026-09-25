<?php
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/Attendance.php';

class AttendanceController {
 public function getStudentAttendance(int $user_id): array {
 $enrollmentModel = new Enrollment();
 $courseModel = new Course();
 $attendanceModel = new Attendance();

 $enrollments = $enrollmentModel->findAll(['user_id' => $user_id]);
 $courses = [];
 
 foreach ($enrollments as $e) {
 $c = $courseModel->findById($e['course_id']);
 if ($c) {
 $attendance_records = $attendanceModel->findAll(['user_id' => $user_id, 'course_id' => $c['id']]);
 $total_sessions = count($attendance_records);
 $present_count = 0;
 foreach ($attendance_records as $r) {
 if ($r['status'] === 'present') $present_count++;
 }
 $percentage = $total_sessions > 0 ? round(($present_count / $total_sessions) * 100) : 100;

 $c['attendance_pct'] = $percentage;
 $c['total_sessions'] = $total_sessions;
 $c['absent_count'] = $total_sessions - $present_count;
 $courses[] = $c;
 }
 }
 return $courses;
 }
}
