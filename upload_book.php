<?php
require_once __DIR__ . '/models/Book.php';

if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'instructor') {
 header("Location: index.php");
 exit;
}

$instructor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['book_file'])) {
 $title = $_POST['book_title'] ?? 'Untitled';
 $fileName = $_FILES['book_file']['name'] ?? '';
 $tempPath = $_FILES['book_file']['tmp_name'] ?? '';
 
 if ($fileName && $tempPath) {
 $uploadDir = __DIR__ . '/uploads/books/';
 
 if (!file_exists($uploadDir)) {
 mkdir($uploadDir, 0777, true);
 }
 
 $targetPath = $uploadDir . $fileName;
 
 if (move_uploaded_file($tempPath, $targetPath)) {
 $date = date('Y-m-d');
 
 $bookModel = new Book();
 $bookModel->insert([
 'doctor_id' => $instructor_id,
 'book_title' => $title,
 'file_name' => $fileName,
 'upload_date' => $date,
 'category' => 'المقرر الدراسي'
 ]);
 
 header("Location: instructor_dashboard.php?upload=success");
 exit;
 } else {
 header("Location: instructor_dashboard.php?upload=error");
 exit;
 }
 }
}
header("Location: instructor_dashboard.php");
?>
