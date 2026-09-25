<?php
require_once __DIR__ . '/../models/Announcement.php';

class AnnouncementController {
 public function handlePost(array $postData): ?string {
 $title = $postData['title'] ?? '';
 $content = $postData['content'] ?? '';
 $college_id = isset($postData['college_id']) ? (int)$postData['college_id'] : null;
 
 // Target Roles logic
 $target_roles = '';
 if (isset($postData['roles']) && is_array($postData['roles'])) {
 $target_roles = implode(',', $postData['roles']);
 }

 // Logic based on current user role
 $current_role = $_SESSION['role'] ?? '';
 $current_user_college = $_SESSION['college_id'] ?? null;

 if ($current_role === 'dean') {
 // Deans can only post to their own college and target students/instructors/staff
 $college_id = $current_user_college;
 $target_roles = 'student,instructor,affairs';
 }

 if ($title && $content) {
 $annModel = new Announcement();
 $annModel->insert([
 'title' => $title,
 'content' => $content,
 'college_id' => $college_id,
 'target_roles' => $target_roles,
 'created_by' => $_SESSION['user_id'] ?? null,
 'date' => date('Y-m-d')
 ]);
 return '<div class="bg-primary text-white p-4 rounded mb-6 border-r-4 border-primary">تم نشر الإعلان بنجاح</div>';
 }
 return null;
 }

 public function handleDelete(int $id): ?string {
 $annModel = new Announcement();
 if ($annModel->delete($id)) {
 return '<div class="bg-secondary text-white p-4 rounded mb-6 border-r-4 border-primary">تم حذف الإعلان بنجاح</div>';
 }
 return null;
 }

 public function getAllAnnouncements(): array {
 $annModel = new Announcement();
 // For the management list, we might want to see all or filter by college
 $current_role = $_SESSION['role'] ?? '';
 $current_user_college = $_SESSION['college_id'] ?? null;

 if ($current_role === 'dean') {
 $all = $annModel->findAll(['college_id' => $current_user_college]);
 } else {
 $all = $annModel->findAll();
 }

 usort($all, function($a, $b) {
 return strtotime($b['date'] ?? '') - strtotime($a['date'] ?? '');
 });
 return $all;
 }
}
