<?php
require_once __DIR__ . '/../models/Fee.php';

class FeesController {
 
 public function getStudentFees(int $user_id): array {
 $feeModel = new Fee();
 $fees = $feeModel->findAll(['user_id' => $user_id]);

 if (empty($fees)) {
 // Only add real, relevant default fees if student has none
 $feeModel->insert([
 'user_id' => $user_id,
 'title' => 'المصروفات الدراسية (القسط الأول)',
 'amount' => 1500.00,
 'due_date' => date('Y-m-d', strtotime('+1 month')),
 'status' => 'pending'
 ]);
 $feeModel->insert([
 'user_id' => $user_id,
 'title' => 'رسوم الأنشطة الطلابية',
 'amount' => 100.00,
 'due_date' => date('Y-m-d', strtotime('-1 month')),
 'status' => 'paid'
 ]);
 $feeModel->insert([
 'user_id' => $user_id,
 'title' => 'رسوم الكتب والمذكرات الدراسية',
 'amount' => 300.00,
 'due_date' => date('Y-m-d', strtotime('+2 months')),
 'status' => 'pending'
 ]);
 $fees = $feeModel->findAll(['user_id' => $user_id]);
 }
 return $fees;
 }

 public function processPayment(array $postData, int $user_id): ?string {
 if (!isset($postData['pay_fee_id'])) return null;

 $fee_id = (int)$postData['pay_fee_id'];
 $feeModel = new Fee();

 // Use the model's built-in PDO connection (not legacy Database::getConnection)
 $fee = $feeModel->findById($fee_id);

 if ($fee && (int)($fee['user_id'] ?? 0) === $user_id) {
 $feeModel->update($fee_id, ['status' => 'paid']);
 return "<div class='bg-primary p-4 mb-6 rounded-lg border border-primary flex items-center gap-3 text-primary'>
 <i class='fas fa-check-circle text-xl'></i>
 <div>
 <p class='font-bold'>تمت عملية الدفع بنجاح!</p>
 <p class='text-sm'>تم تحديث حالة المصروفات. رقم العملية: #PAY-" . rand(10000, 99999) . "</p>
 </div>
 </div>";
 }
 return null;
 }

 public function calculateTotals(array $fees): array {
 $total_required = 0;
 $total_paid = 0;

 foreach ($fees as $f) {
 $amt = floatval($f['amount']);
 $total_required += $amt;
 if (strtolower($f['status']) === 'paid') {
 $total_paid += $amt;
 }
 }
 return [
 'total_required' => $total_required,
 'total_paid' => $total_paid,
 'remaining' => $total_required - $total_paid
 ];
 }
}
