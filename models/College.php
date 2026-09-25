<?php
require_once __DIR__ . '/Model.php';

class College extends Model {
 protected string $table = 'colleges';

 public function getStudentCounts(): array {
 $sql = "SELECT c.id, c.name, COUNT(u.id) AS student_count, 
 (SELECT full_name FROM users WHERE username = c.dean_username LIMIT 1) as dean_fullname,
 c.dean_username, c.latitude, c.longitude
 FROM colleges c
 LEFT JOIN users u ON u.college_id = c.id AND u.role = 'student'
 GROUP BY c.id, c.name, c.dean_username, c.latitude, c.longitude
 ORDER BY c.id";
 return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
 }
}
