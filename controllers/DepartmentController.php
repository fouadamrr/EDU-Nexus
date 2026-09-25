<?php
// controllers/DepartmentController.php
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/College.php';

class DepartmentController {
 private $deptModel;

 public function __construct() {
 $this->deptModel = new Department();
 }

 public function handleRequest(array $postData, ?string $action, int $current_college_id, string $role): ?string {
 if ($action === 'add') {
 $name = trim($postData['name'] ?? '');
 $college_id = ($role === 'super_admin' || $role === 'admin') ? (int)($postData['college_id'] ?? 0) : $current_college_id;

 if (empty($name) || $college_id <= 0) {
 return $this->formatMessage('يرجى إدخال اسم القسم واختيار الكلية', 'red');
 }

 try {
 $this->deptModel->insert([
 'name' => $name,
 'college_id' => $college_id
 ]);
 return $this->formatMessage('تم إضافة القسم بنجاح', 'emerald');
 } catch (Exception $e) {
 return $this->formatMessage('خطأ أثناء إضافة القسم: ' . $e->getMessage(), 'red');
 }
 }

 if ($action === 'edit') {
 $id = (int)($postData['id'] ?? 0);
 $name = trim($postData['name'] ?? '');
 
 if ($id <= 0 || empty($name)) {
 return $this->formatMessage('بيانات غير صحيحة', 'red');
 }

 try {
 $this->deptModel->update($id, ['name' => $name]);
 return $this->formatMessage('تم تعديل القسم بنجاح', 'blue');
 } catch (Exception $e) {
 return $this->formatMessage('خطأ أثناء تعديل القسم: ' . $e->getMessage(), 'red');
 }
 }

 if ($action === 'delete' && isset($postData['id'])) {
 $id = (int)$postData['id'];
 try {
 $this->deptModel->delete($id);
 return $this->formatMessage('تم حذف القسم بنجاح', 'amber');
 } catch (Exception $e) {
 return $this->formatMessage('خطأ أثناء الحذف: ' . $e->getMessage(), 'red');
 }
 }

 return null;
 }

 public function getDepartments(int $college_id, string $role): array {
 if (in_array($role, ['super_admin', 'admin'])) {
 return $this->deptModel->findAll([], 'college_id ASC, name ASC');
 } else {
 return $this->deptModel->findByCollege($college_id);
 }
 }

 public function getCollegeList(): array {
 return (new College())->findAll([], 'name ASC');
 }

 private function formatMessage(string $text, string $color): string {
 $icons = ['red' => 'fa-exclamation-circle', 'emerald' => 'fa-check-circle', 'blue' => 'fa-info-circle', 'amber' => 'fa-archive'];
 $icon = $icons[$color] ?? 'fa-info';
 return "<div class='bg-{$color}-50 text-{$color}-600 border border-{$color}-200 p-4 rounded-xl mb-6 flex items-center gap-3'><i class='fas {$icon} text-lg'></i><span class='font-medium'>{$text}</span></div>";
 }
}
