<?php
require_once __DIR__ . '/Model.php';

class Course extends Model {
 protected string $table = 'courses';

 public function getCoursesWithPrerequisites(int $collegeId): array {
 $sql = "SELECT c.*, 
 ARRAY_AGG(p.prerequisite_course_id) as prerequisites
 FROM {$this->table} c
 LEFT JOIN course_prerequisites p ON c.id = p.course_id
 WHERE c.college_id = :cid
 GROUP BY c.id
 ORDER BY c.level ASC, c.name ASC";
 $stmt = $this->db->prepare($sql);
 $stmt->execute([':cid' => $collegeId]);
 return $stmt->fetchAll();
 }

 public function getStudentEnrolledCourses(int $studentId, string $semester): array {
 $sql = "SELECT c.*, e.id as enrollment_id, g.grade, g.points 
 FROM enrollments e
 JOIN {$this->table} c ON e.course_id = c.id
 LEFT JOIN grades g ON (g.user_id = e.user_id AND g.course_id = c.id AND g.semester = e.semester)
 WHERE e.user_id = :uid AND e.semester = :sem";
 $stmt = $this->db->prepare($sql);
 $stmt->execute([':uid' => $studentId, ':sem' => $semester]);
 return $stmt->fetchAll();
 }
}
