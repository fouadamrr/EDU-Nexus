<?php
require_once __DIR__ . '/../models/College.php';
require_once __DIR__ . '/../models/User.php';

class CollegeController {
 
 public function handleRequest(array $postData, ?string $action): ?string {
 $collegeModel = new College();
 $userModel = new User();
 
 if ($action === 'add') {
 $college_name = trim($postData['name'] ?? '');
 $dean_username = trim($postData['dean_username'] ?? '');
 $dean_password = $postData['dean_password'] ?? '';
 $dean_name = trim($postData['dean_name'] ?? '');

 if (empty($college_name) || empty($dean_username) || empty($dean_password) || empty($dean_name)) {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">يرجى ملء جميع الحقول المطلوبة</span></div>';
 }
 
 $exists = $collegeModel->findOneBy('name', $college_name);
 $dean_user_exists = $userModel->findOneBy('username', $dean_username);

 if ($exists) {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">اسم الكلية موجود بالفعل</span></div>';
 } elseif ($dean_user_exists) {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">معرف حساب العميد مستخدم بالفعل لكلية أخرى</span></div>';
 } else {
 $college_id = $collegeModel->insert([
 'name' => $college_name,
 'dean_username' => $dean_username,
 'latitude' => !empty($postData['latitude']) ? (float)$postData['latitude'] : null,
 'longitude' => !empty($postData['longitude']) ? (float)$postData['longitude'] : null,
 ]);

 $userModel->insert([
 'username' => $dean_username,
 'password' => password_hash($dean_password, PASSWORD_DEFAULT),
 'full_name' => $dean_name,
 'role' => 'dean',
 'status' => 'active',
 'college_id' => $college_id,
 ]);

 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-check-circle text-lg"></i><span class="font-medium">تم إضافة الكلية وحساب العميد بنجاح</span></div>';
 }
 }

 if ($action === 'edit') {
 $id = (int)($postData['id'] ?? 0);
 $name = trim($postData['name'] ?? '');
 $dean_name = trim($postData['dean_name'] ?? '');
 $dean_password = $postData['dean_password'] ?? '';

 $college = $collegeModel->findById($id);
 if ($college && $name) {
 $collegeUpdate = ['name' => $name];
 if (isset($postData['latitude']) && $postData['latitude'] !== '') {
 $collegeUpdate['latitude'] = (float)$postData['latitude'];
 }
 if (isset($postData['longitude']) && $postData['longitude'] !== '') {
 $collegeUpdate['longitude'] = (float)$postData['longitude'];
 }
 $collegeModel->update($id, $collegeUpdate);

 $dean_user = $userModel->findOneBy('username', $college['dean_username'] ?? '');
 if ($dean_user) {
 $deanUpdate = ['full_name' => $dean_name];
 if (!empty($dean_password)) {
 $deanUpdate['password'] = password_hash($dean_password, PASSWORD_DEFAULT);
 }
 $userModel->update($dean_user['id'], $deanUpdate);
 }
 return '<div class="bg-bg text-accent border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-info-circle text-lg"></i><span class="font-medium">تم تعديل بيانات الكلية بنجاح</span></div>';
 } else {
 return '<div class="bg-bg text-primary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-lg"></i><span class="font-medium">لم يتم العثور على الكلية أو البيانات ناقصة</span></div>';
 }
 }
 
 if ($action === 'delete' && isset($postData['id'])) {
 $id = (int)$postData['id'];
 $college = $collegeModel->findById($id);
 if ($college) {
 $collegeModel->delete($id);
 return '<div class="bg-bg text-secondary border border-primary p-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-archive text-lg"></i><span class="font-medium">تم حذف الكلية بنجاح (تظل بيانات المستخدمين في PostgreSQL)</span></div>';
 }
 }

 return null;
 }

 public function getCollegesList(string $role, ?int $college_id): array {
 $collegeModel = new College();
 if ($role === 'dean') {
 $colleges = [$collegeModel->findById($college_id)];
 $colleges = array_filter($colleges);
 } else {
 $colleges = $collegeModel->getStudentCounts();
 }
 return $colleges;
 }
}
