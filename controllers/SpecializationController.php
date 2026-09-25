<?php
// controllers/SpecializationController.php
require_once __DIR__ . '/../models/SpecializationRequest.php';
require_once __DIR__ . '/../models/StudentDetail.php';
require_once __DIR__ . '/../models/User.php';

class SpecializationController {
 private $requestModel;
 private $studentModel;

 public function __construct() {
 $this->requestModel = new SpecializationRequest();
 $this->studentModel = new StudentDetail();
 }

 /**
 * Get departments for a specific college
 */
 public function getCollegeDepartments(int $college_id): array {
 $db = Database::getConnection();
 $stmt = $db->prepare("SELECT name FROM departments WHERE college_id = ? ORDER BY name");
 $stmt->execute([$college_id]);
 return $stmt->fetchAll(PDO::FETCH_COLUMN);
 }

 /**
 * Handle student request submission
 */
 public function submitRequest(int $student_id, array $data): array {
 $requested_major = trim($data['requested_major'] ?? '');
 $reason = trim($data['reason'] ?? '');
 $type = $data['type'] ?? 'initial';

 if (empty($requested_major)) {
 return ['success' => false, 'message' => 'يرجى اختيار التخصص المطلوب'];
 }

 // Check for existing pending requests
 $pending = $this->requestModel->findAll(['student_id' => $student_id, 'status' => 'pending']);
 if (!empty($pending)) {
 return ['success' => false, 'message' => 'لديك طلب قيد الانتظار بالفعل. يرجى انتظار رد الإدارة.'];
 }

 // Get student info
 $student = $this->studentModel->findOneBy('user_id', $student_id);
 $user = (new User())->findById($student_id);

 try {
 $this->requestModel->insert([
 'student_id' => $student_id,
 'college_id' => $user['college_id'],
 'current_major' => $student['major'] ?? null,
 'requested_major' => $requested_major,
 'type' => $type,
 'reason' => $reason,
 'status' => 'pending'
 ]);
 return ['success' => true, 'message' => 'تم إرسال طلبك بنجاح وقيد المراجعة من قبل شؤون الطلاب.'];
 } catch (Exception $e) {
 return ['success' => false, 'message' => 'خطأ أثناء إرسال الطلب: ' . $e->getMessage()];
 }
 }

 /**
 * Handle admin approval/rejection
 */
 public function processRequest(int $request_id, string $status, int $admin_id, string $note = ''): array {
 if (!in_array($status, ['approved', 'rejected'])) {
 return ['success' => false, 'message' => 'حالة غير صالحة'];
 }

 $request = $this->requestModel->findById($request_id);
 if (!$request) {
 return ['success' => false, 'message' => 'الطلب غير موجود'];
 }

 if ($request['status'] !== 'pending') {
 return ['success' => false, 'message' => 'تمت معالجة هذا الطلب مسبقاً'];
 }

 $db = Database::getConnection();
 $db->beginTransaction();

 try {
 // Update request status
 $this->requestModel->update($request_id, [
 'status' => $status,
 'admin_id' => $admin_id,
 'admin_note' => $note,
 'updated_at' => date('Y-m-d H:i:s')
 ]);

 // If approved, update student major
 if ($status === 'approved') {
 $student = $this->studentModel->findOneBy('user_id', $request['student_id']);
 if ($student) {
 $this->studentModel->update($student['id'], [
 'major' => $request['requested_major']
 ]);
 } else {
 // Create student record if missing (unlikely but safe)
 $this->studentModel->insert([
 'user_id' => $request['student_id'],
 'college_id' => $request['college_id'],
 'major' => $request['requested_major']
 ]);
 }
 }

 $db->commit();
 $msg = ($status === 'approved') ? 'تمت الموافقة على الطلب وتحديث تخصص الطالب.' : 'تم رفض الطلب بنجاح.';
 return ['success' => true, 'message' => $msg];

 } catch (Exception $e) {
 $db->rollBack();
 return ['success' => false, 'message' => 'فشلت العملية: ' . $e->getMessage()];
 }
 }

 /**
 * Get student requests
 */
 public function getStudentRequests(int $student_id): array {
 return $this->requestModel->findByStudentId($student_id);
 }

 /**
 * Get pending requests for admin
 */
 public function getPendingRequests(int $college_id, string $role): array {
 if (in_array($role, ['super_admin', 'admin'])) {
 return $this->requestModel->findAll(['status' => 'pending'], 'created_at ASC');
 } else {
 return $this->requestModel->findPendingByCollege($college_id);
 }
 }
}
