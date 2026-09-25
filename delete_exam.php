<?php
require_once __DIR__ . '/models/Exam.php';
require_once __DIR__ . '/models/Grade.php';
session_start();

if (isset($_GET['id'])) {
 $id = (int)$_GET['id'];
 
 $examModel = new Exam();
 $gradeModel = new Grade();

 $exam = $examModel->findById($id);
 
 if ($exam) {
 $db = Database::getConnection();
 $pdo->prepare("DELETE FROM exam_grades WHERE exam_id = ?")->execute([$id]);
 $examModel->delete($id);
 }
}

header("Location: instructor_dashboard.php");
exit;
?>
