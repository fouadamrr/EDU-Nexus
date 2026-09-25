<?php
require_once __DIR__ . '/models/Book.php';
session_start();

if (isset($_GET['id'])) {
 $id = (int)$_GET['id'];
 
 $bookModel = new Book();
 $book = $bookModel->findById($id);
 
 if ($book) {
 $filePath = __DIR__ . '/uploads/books/' . $book['file_name'];
 if (file_exists($filePath)) {
 unlink($filePath);
 }
 $bookModel->delete($id);
 }
}

header("Location: instructor_dashboard.php");
exit;
?>
