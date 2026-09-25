<?php
require_once __DIR__ . '/../models/Fee.php';
require_once __DIR__ . '/../models/User.php';

class ManageFeesController {

 public function handleAddRequest(array $postData): string {
 $student_id = $postData['student_id'] ?? 0;
 $amount = $postData['amount'] ?? 0;
 $description = $postData['description'] ?? '';
 $due_date = $postData['due_date'] ?? '';

 (new Fee())->insert([
 'user_id' => $student_id,
 'amount' => $amount,
 'description' => $description,
 'due_date' => $due_date,
 'status' => 'pending'
 ]);
 return '<div class="bg-primary text-white p-3 rounded-xl mb-4 font-bold flex items-center gap-2"><i class="fas fa-check-circle"></i> تم إضافة الرسوم بنجاح</div>';
 }

 public function handleDeleteRequest(int $id): string {
 if ((new Fee())->delete($id)) {
 return '<div class="bg-secondary text-white p-3 rounded-xl mb-4 font-bold flex items-center gap-2"><i class="fas fa-trash"></i> تم حذف الرسوم</div>';
 }
 return '';
 }

 public function handlePayRequest(int $id): string {
 $fee = (new Fee())->findById($id);
 if ($fee) {
 (new Fee())->update($id, ['status' => 'paid']);
 return '<div class="bg-accent text-white p-3 rounded-xl mb-4 font-bold flex items-center gap-2"><i class="fas fa-check-double"></i> تم تحديث الحالة إلى مدفوع</div>';
 }
 return '';
 }

 /**
 * Returns all colleges for the filter dropdown (admin only).
 */
 public function getAllColleges(): array {
 global $pdo;
 try {
 return $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {
 return [];
 }
 }

 /**
 * Returns students list with total fees, filtered by college & level.
 * Scoped to session college_id for dean/affairs automatically.
 */
 public function getStudentsFeeList(int $college_id_filter = 0, int $level_filter = 0): array {
 global $pdo;
 $role = $_SESSION['role'] ?? '';
 $session_cid = (int)($_SESSION['college_id'] ?? 0);

 // Determine effective college filter
 $effective_college = 0;
 if (in_array($role, ['dean', 'affairs'])) {
 $effective_college = $session_cid; // locked to their college
 } elseif ($college_id_filter > 0) {
 $effective_college = $college_id_filter;
 }

 $params = ['student'];
 $where = "WHERE u.role = ?";

 if ($effective_college > 0) {
 $where .= " AND u.college_id = ?";
 $params[] = $effective_college;
 }

 if ($level_filter > 0) {
 $where .= " AND s.level = ?";
 $params[] = $level_filter;
 }

 $sql = "
 SELECT
 u.id,
 u.full_name,
 u.username,
 u.college_id,
 c.name AS college_name,
 s.level,
 COALESCE(SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END), 0) AS total_paid,
 COALESCE(SUM(CASE WHEN f.status != 'paid' THEN f.amount ELSE 0 END), 0) AS total_unpaid,
 COUNT(f.id) AS fee_count
 FROM users u
 LEFT JOIN students s ON u.id = s.user_id
 LEFT JOIN colleges c ON u.college_id = c.id
 LEFT JOIN fees f ON u.id = f.user_id
 {$where}
 GROUP BY u.id, u.full_name, u.username, u.college_id, c.name, s.level
 ORDER BY u.full_name
 ";

 try {
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {
 return [];
 }
 }

 /**
 * Returns detailed fees for one student.
 */
 public function getStudentFees(int $student_id): array {
 global $pdo;
 try {
 $stmt = $pdo->prepare("SELECT * FROM fees WHERE user_id = ? ORDER BY due_date DESC");
 $stmt->execute([$student_id]);
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {
 return [];
 }
 }

 /**
 * For modal: scoped student list for current user.
 */
 public function getStudents(): array {
 $filters = ['role' => 'student'];
 $role = $_SESSION['role'] ?? '';
 $college_id = $_SESSION['college_id'] ?? 0;
 if (in_array($role, ['dean', 'affairs']) && $college_id > 0) {
 $filters['college_id'] = $college_id;
 }
 return (new User())->findAll($filters);
 }

 /**
 * Bulk-assign fees to all students matching the given college & level filter.
 * If $skip_existing is true, students who already have a fee with the same
 * description will be skipped (avoids duplicates).
 */
 public function handleBulkAddRequest(array $post): string {
 global $pdo;

 $description = trim($post['bulk_description'] ?? '');
 $amount = (float)($post['bulk_amount'] ?? 0);
 $due_date = $post['bulk_due_date'] ?? '';
 $college_id = (int)($post['bulk_college_id'] ?? 0);
 $level = (int)($post['bulk_level'] ?? 0);
 $skip = isset($post['skip_existing']) && $post['skip_existing'] == '1';

 if (!$description || $amount <= 0 || !$due_date) {
 return '<div class="bg-primary text-white p-3 rounded-xl mb-4 font-bold">يرجى ملء جميع الحقول المطلوبة.</div>';
 }

 // Resolve effective college (security: dean/affairs locked to their college)
 $role = $_SESSION['role'] ?? '';
 $session_cid = (int)($_SESSION['college_id'] ?? 0);
 if (in_array($role, ['dean', 'affairs'])) {
 $college_id = $session_cid;
 }

 // Get matching student IDs
 $params = ['student'];
 $where = "WHERE u.role = ?";
 if ($college_id > 0) { $where .= " AND u.college_id = ?"; $params[] = $college_id; }
 if ($level > 0) { $where .= " AND s.level = ?"; $params[] = $level; }

 try {
 $stmt = $pdo->prepare("SELECT u.id FROM users u LEFT JOIN students s ON u.id = s.user_id {$where}");
 $stmt->execute($params);
 $student_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
 } catch (Exception $e) {
 return '<div class="bg-primary text-white p-3 rounded-xl mb-4 font-bold">خطأ في جلب بيانات الطلاب.</div>';
 }

 if (empty($student_ids)) {
 return '<div class="bg-primary text-white p-3 rounded-xl mb-4 font-bold">لا يوجد طلاب مطابقون للفلتر المحدد.</div>';
 }

 $feeModel = new Fee();
 $inserted = 0;
 $skipped = 0;

 foreach ($student_ids as $sid) {
 if ($skip) {
 // Check if student already has a fee with same description
 $chk = $pdo->prepare("SELECT COUNT(*) FROM fees WHERE user_id = ? AND description = ?");
 $chk->execute([$sid, $description]);
 if ($chk->fetchColumn() > 0) { $skipped++; continue; }
 }
 $feeModel->insert([
 'user_id' => $sid,
 'amount' => $amount,
 'description' => $description,
 'due_date' => $due_date,
 'status' => 'pending'
 ]);
 $inserted++;
 }

 $msg = "تم إضافة الرسوم لـ <strong>{$inserted}</strong> طالب بنجاح.";
 if ($skipped > 0) $msg .= " (تم تخطي <strong>{$skipped}</strong> طالب لديهم رسوم مماثلة)";
 return '<div class="bg-primary text-white p-3 rounded-xl mb-4 font-bold flex items-center gap-2"><i class="fas fa-check-circle text-primary"></i> '.$msg.'</div>';
 }

 // Legacy – kept for backwards compat
 public function getEnrichedFees(): array { return []; }
}
