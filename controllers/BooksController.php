<?php
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Book.php';

class BooksController {
 public function getAvailableBooks(int $student_id, string $username): array {
 $studentModel = new Student();
 $bookModel = new Book();

 $student_details = $studentModel->findOneBy('user_id', $student_id);
 $assigned_doctor_id = null;
 if ($student_details && isset($student_details['doctor_id'])) {
 $assigned_doctor_id = $student_details['doctor_id'];
 }

 $is_trial = $username === 'trial_student';

 if ($is_trial) {
 return $bookModel->findAll();
 } elseif ($assigned_doctor_id) {
 $all_books = $bookModel->findAll();
 return array_filter($all_books, function($b) use ($assigned_doctor_id) {
 return isset($b['doctor_id']) && (int)$b['doctor_id'] === (int)$assigned_doctor_id;
 });
 }
 return [];
 }
}
