<?php
// manage_specializations.php
require_once 'includes/header.php';
require_once __DIR__ . '/controllers/SpecializationController.php';

// Access Control
require_permission('students');

$controller = new SpecializationController();
$admin_id = $_SESSION['user_id'];
$admin_role = $_SESSION['role'];
$college_id = $_SESSION['college_id'] ?? 0;

$message = '';
$status_type = 'info';

// Handle Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_request'])) {
 $req_id = (int)$_POST['request_id'];
 $decision = $_POST['decision']; // 'approved' or 'rejected'
 $note = trim($_POST['admin_note'] ?? '');
 
 $result = $controller->processRequest($req_id, $decision, $admin_id, $note);
 $message = $result['message'];
 $status_type = $result['success'] ? 'success' : 'error';
}

// Get Pending Requests
$pending_requests = $controller->getPendingRequests($college_id, $admin_role);
?>

<div class="max-w-6xl mx-auto space-y-8">

 <!-- Admin Header -->
 <div class="bg-white p-8 rounded-2xl shadow-card border border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
 <div class="flex items-center gap-5">
 <div class="w-16 h-16 rounded-2xl bg-bg flex items-center justify-center text-white shadow-lg shadow-sm">
 <i class="fas fa-tasks text-2xl"></i>
 </div>
 <div>
 <h2 class="text-2xl font-bold text-slate-800">إدارة طلبات التشعيب والتحويل</h2>
 <p class="text-slate-500 mt-1">مراجعة واعتماد طلبات التخصصات الأكاديمية للطلاب</p>
 </div>
 </div>
 <div class="flex gap-3">
 <span class="bg-bg text-primary px-4 py-2 rounded-xl border border-primary font-bold text-sm flex items-center gap-2">
 <i class="fas fa-hourglass-half"></i>
 <?php echo count($pending_requests); ?> طلبات معلقة
 </span>
 </div>
 </div>

 <?php if ($message): ?>
 <div class="p-4 rounded-xl border <?php echo $status_type === 'success' ? 'bg-bg border-primary text-primary' : 'bg-bg border-primary text-primary'; ?> flex items-center gap-3">
 <i class="fas <?php echo $status_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-lg"></i>
 <span class="font-bold"><?php echo $message; ?></span>
 </div>
 <?php endif; ?>

 <!-- Pending Requests Table -->
 <div class="bg-white rounded-2xl shadow-card border border-slate-100 overflow-hidden">
 <div class="p-6 border-b border-slate-100 flex justify-between items-center">
 <h3 class="font-bold text-slate-800 flex items-center gap-2">
 <i class="fas fa-list-ul text-primary"></i>
 قائمة الطلبات الجديدة
 </h3>
 </div>

 <?php if (empty($pending_requests)): ?>
 <div class="text-center py-20 text-slate-400">
 <div class="w-20 h-20 bg-bg rounded-full flex items-center justify-center mx-auto mb-4">
 <i class="fas fa-check-double text-3xl opacity-20"></i>
 </div>
 <p class="text-lg font-medium">لا توجد طلبات معلقة حالياً</p>
 <p class="text-sm">لقد تمت معالجة جميع الطلبات السابقة بنجاح.</p>
 </div>
 <?php else: ?>
 <div class="overflow-x-auto">
 <table class="w-full text-right">
 <thead class="bg-bg">
 <tr class="text-slate-500 text-xs font-bold uppercase tracking-wider">
 <th class="px-6 py-4">بيانات الطالب</th>
 <th class="px-6 py-4">الكلية</th>
 <th class="px-6 py-4">القسم المطلـوب</th>
 <th class="px-6 py-4">التخصص الحالي</th>
 <th class="px-6 py-4">النوع</th>
 <th class="px-6 py-4 text-center">الإجراء</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-100">
 <?php foreach ($pending_requests as $req): 
 // Get student name via User model
 $student = (new User())->findById($req['student_id']);
 $stu_detail = (new StudentDetail())->findOneBy('user_id', $req['student_id']);
 
 // Get college name
 $stmt = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
 $stmt->execute([$req['college_id']]);
 $c_name = $stmt->fetchColumn();
 ?>
 <tr class="hover:bg-bg/50 transition-colors">
 <td class="px-6 py-4">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-full bg-bg flex items-center justify-center text-slate-400 font-bold">
 <?php echo mb_substr($student['full_name'], 0, 1, 'UTF-8'); ?>
 </div>
 <div>
 <div class="font-bold text-slate-800"><?php echo htmlspecialchars($student['full_name']); ?></div>
 <div class="text-xs text-slate-500 font-mono"><?php echo htmlspecialchars($student['username']); ?></div>
 <div class="text-[10px] text-primary font-bold">المستوى: <?php echo $stu_detail['level'] ?? '—'; ?></div>
 </div>
 </div>
 </td>
 <td class="px-6 py-4 text-sm text-slate-600">
 <span class="inline-flex items-center gap-1">
 <i class="fas fa-university opacity-50 text-[10px]"></i>
 <?php echo htmlspecialchars($c_name); ?>
 </span>
 </td>
 <td class="px-6 py-4">
 <span class="text-sm font-bold text-primary bg-bg px-2 py-1 rounded-md border border-primary">
 <?php echo htmlspecialchars($req['requested_major']); ?>
 </span>
 </td>
 <td class="px-6 py-4 text-sm text-slate-500">
 <?php echo htmlspecialchars($req['current_major'] ?: 'غير متخصص'); ?>
 </td>
 <td class="px-6 py-4">
 <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-bg text-slate-500 border border-slate-200">
 <?php echo $req['type'] === 'initial' ? 'تشعيب' : 'تحويل'; ?>
 </span>
 </td>
 <td class="px-6 py-4 text-center">
 <button onclick="openProcessModal(<?php echo $req['id']; ?>, '<?php echo addslashes($student['full_name']); ?>', '<?php echo addslashes($req['requested_major']); ?>')" class="bg-primary text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-accent hover:text-white transition-all">
 معالجة الطلب
 </button>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endif; ?>
 </div>

</div>

<!-- Process Modal -->
<div id="processModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
 <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden animate-slide-up">
 <div class="p-6 border-b border-slate-100 bg-bg flex justify-between items-center">
 <h3 class="font-bold text-slate-800">اتخاذ قرار بشأن الطلب</h3>
 <button onclick="closeProcessModal()" class="text-slate-400 hover:text-primary transition-colors">
 <i class="fas fa-times text-xl"></i>
 </button>
 </div>
 <form method="POST" class="p-6 space-y-6">
 <input type="hidden" name="request_id" id="modal_request_id">
 
 <div class="bg-bg p-4 rounded-xl border border-primary">
 <p class="text-sm text-primary leading-relaxed">
 أنت بصدد معالجة طلب الطالب <strong id="modal_student_name"></strong> 
 للانتقال إلى قسم <strong id="modal_requested_major"></strong>.
 </p>
 </div>

 <div>
 <label class="block text-sm font-bold text-slate-700 mb-3">القرار النهائي</label>
 <div class="grid grid-cols-2 gap-4">
 <label class="relative cursor-pointer group">
 <input type="radio" name="decision" value="approved" class="peer sr-only" required checked>
 <div class="p-4 border-2 border-slate-100 rounded-xl peer-checked:border-primary peer-checked:bg-bg transition-all flex flex-col items-center gap-2">
 <i class="fas fa-check-circle text-2xl text-slate-300 peer-checked:text-primary"></i>
 <span class="font-bold text-slate-600 peer-checked:text-primary">اعتماد وقبول</span>
 </div>
 </label>
 <label class="relative cursor-pointer group">
 <input type="radio" name="decision" value="rejected" class="peer sr-only">
 <div class="p-4 border-2 border-slate-100 rounded-xl peer-checked:border-primary peer-checked:bg-bg transition-all flex flex-col items-center gap-2">
 <i class="fas fa-times-circle text-2xl text-slate-300 peer-checked:text-primary"></i>
 <span class="font-bold text-slate-600 peer-checked:text-secondary">رفض الطلب</span>
 </div>
 </label>
 </div>
 </div>

 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">ملاحظات الإدارة (تظهر للطالب)</label>
 <textarea name="admin_note" rows="3" class="w-full p-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary outline-none transition-all placeholder:text-slate-400" placeholder="مثال: تم قبول طلبك بناءً على استيفاء الشروط..."></textarea>
 </div>

 <div class="flex gap-3">
 <button type="button" onclick="closeProcessModal()" class="flex-1 bg-bg text-slate-600 font-bold py-3 rounded-xl hover:bg-slate-200 transition-all">إلغاء</button>
 <button type="submit" name="process_request" class="flex-2 bg-primary text-white font-bold py-3 px-8 rounded-xl hover:bg-accent hover:text-white shadow-lg shadow-sm transition-all">
 تأكيد وحفظ القرار
 </button>
 </div>
 </form>
 </div>
</div>

<script>
function openProcessModal(id, sName, rMajor) {
 document.getElementById('modal_request_id').value = id;
 document.getElementById('modal_student_name').innerText = sName;
 document.getElementById('modal_requested_major').innerText = rMajor;
 document.getElementById('processModal').classList.remove('hidden');
}

function closeProcessModal() {
 document.getElementById('processModal').classList.add('hidden');
}
</script>

<style>
@keyframes slide-up {
 from { opacity: 0; transform: translateY(20px); }
 to { opacity: 1; transform: translateY(0); }
}
.animate-slide-up {
 animation: slide-up 0.3s ease-out;
}
.flex-2 { flex: 2; }
</style>

<?php require_once 'includes/footer.php'; ?>
