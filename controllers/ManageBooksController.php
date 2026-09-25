<?php
require_once __DIR__ . '/../models/Book.php';

class ManageBooksController {
 public function handleAddRequest(array $postData, array $files = []): string {
 $title = $postData['title'] ?? '';
 $author = $postData['author'] ?? '';
 $total = $postData['total'] ?? 0;
 $category = $postData['category'] ?? '';
 $file_path = null;

 // File Upload Handling
 if (isset($files['book_file']) && $files['book_file']['error'] === UPLOAD_ERR_OK) {
 $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
 $maxSize = 70 * 1024 * 1024; // 70MB

 $fileName = $files['book_file']['name'];
 $fileSize = $files['book_file']['size'];
 $fileTmpName = $files['book_file']['tmp_name'];
 $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

 if (!in_array($fileExtension, $allowedExtensions)) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">خطأ: نوع الملف غير مسموح به (فقط PDF, Word, PowerPoint)</div>';
 }

 if ($fileSize > $maxSize) {
 return '<div class="bg-primary text-white p-3 rounded mb-4">خطأ: حجم الملف يتجاوز الحد المسموح به (70 ميجابايت)</div>';
 }

 $uploadDir = __DIR__ . '/../assets/uploads/books/';
 if (!is_dir($uploadDir)) {
 mkdir($uploadDir, 0777, true);
 }

 $newFileName = uniqid('book_') . '.' . $fileExtension;
 $targetPath = $uploadDir . $newFileName;

 if (move_uploaded_file($fileTmpName, $targetPath)) {
 $file_path = 'assets/uploads/books/' . $newFileName;
 } else {
 return '<div class="bg-primary text-white p-3 rounded mb-4">خطأ في رفع الملف للسيرفر</div>';
 }
 }

 $bookModel = new Book();
 $bookModel->insert([
 'title' => $title,
 'author' => $author,
 'category' => $category,
 'total_copies' => $total,
 'available_copies' => $total,
 'file_path' => $file_path,
 'image' => 'book_default.jpg'
 ]);
 
 return '<div class="bg-primary text-white p-3 rounded mb-4">تم إضافة الكتاب بنجاح</div>';
 }

 public function handleDeleteRequest(int $id): string {
 $bookModel = new Book();
 $book = $bookModel->findById($id);
 
 if ($book && !empty($book['file_path'])) {
 $fullPath = __DIR__ . '/../' . $book['file_path'];
 if (file_exists($fullPath)) {
 unlink($fullPath);
 }
 }

 if ($bookModel->delete($id)) {
 return '<div class="bg-secondary text-white p-3 rounded mb-4">تم حذف الكتاب والملفات المرتبطة به</div>';
 }
 return '';
 }

 public function getAllBooks(): array {
 $bookModel = new Book();
 $books = $bookModel->findAll();
 usort($books, function ($a, $b) {
 return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
 });
 return $books;
 }
}
