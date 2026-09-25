<?php
require_once __DIR__ . '/../models/Course.php';

class ExamsController {
 public function getStudentMockExams(int $user_id): array {
 $db = Database::getConnection();
 $stmt = $db->prepare("SELECT c.code, c.name FROM grades g 
 JOIN courses c ON g.course_id = c.id 
 WHERE g.user_id = ?");
 $stmt->execute([$user_id]);
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 }
}
