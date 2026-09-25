<?php
// majors.php
require_once 'includes/header.php';
require_once __DIR__ . '/controllers/SpecializationController.php';

$controller = new SpecializationController();
$student_id = $_SESSION['user_id'];
$college_id = $_SESSION['college_id'] ?? 0;

$message = '';
$status_type = 'info';

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
 $result = $controller->submitRequest($student_id, $_POST);
 $message = $result['message'];
 $status_type = $result['success'] ? 'success' : 'error';
}

// Get Data
$student_info = (new StudentDetail())->findOneBy('user_id', $student_id);
$current_major = $student_info['major'] ?? 'غير متخصص بعد';
$departments = $controller->getCollegeDepartments($college_id);
$requests = $controller->getStudentRequests($student_id);

$has_pending = false;
foreach ($requests as $r) {
 if ($r['status'] === 'pending') {
 $has_pending = true;
 break;
 }
}
?>

<div class="max-w-5xl mx-auto space-y-8">

 <!-- Header Section -->
 <div class="bg-white p-8 rounded-2xl shadow-card border border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
 <div class="flex items-center gap-5">
 <div class="w-16 h-16 rounded-2xl bg-bg flex items-center justify-center text-white shadow-lg shadow-sm">
 <i class="fas fa-project-diagram text-2xl"></i>
 </div>
 <div>
 <h2 class="text-2xl font-bold text-slate-800">نظام التشعيب الأكاديمي</h2>
 <p class="text-slate-500 mt-1">إدارة التخصصات والتحويل بين الأقسام الأكاديمية</p>
 </div>
 </div>
 <div class="bg-bg px-6 py-3 rounded-xl border border-primary">
 <span class="text-primary font-bold text-sm block mb-1">تخصصك الحالي:</span>
 <span class="text-primary font-extrabold text-lg"><?php echo htmlspecialchars($current_major); ?></span>
 </div>
 </div>

 <?php if ($message): ?>
 <div class="p-4 rounded-xl border <?php echo $status_type === 'success' ? 'bg-bg border-primary text-primary' : 'bg-bg border-primary text-primary'; ?> flex items-center gap-3 animate-bounce-short">
 <i class="fas <?php echo $status_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-lg"></i>
 <span class="font-bold"><?php echo $message; ?></span>
 </div>
 <?php endif; ?>

 <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
 
 <!-- Request Panel -->
 <div class="lg:col-span-1">
 <div class="bg-white p-6 rounded-2xl shadow-card border border-slate-100 h-full">
 <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
 <i class="fas fa-paper-plane text-primary"></i>
 تقديم طلب جديد
 </h3>

 <?php if ($has_pending): ?>
 <div class="bg-bg border border-primary p-5 rounded-xl text-primary text-center">
 <i class="fas fa-clock text-3xl mb-3 opacity-50"></i>
 <p class="font-bold">لديك طلب قيد المراجعة</p>
 <p class="text-sm mt-2">يرجى انتظار قرار الإدارة قبل تقديم طلب جديد.</p>
 </div>
 <?php else: ?>
 <form method="POST" class="space-y-5">
 <input type="hidden" name="type" value="<?php echo empty($student_info['major']) ? 'initial' : 'transfer'; ?>">
 
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">التخصص المطلوب</label>
 <select name="requested_major" class="w-full p-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all bg-bg" required>
 <option value="" disabled selected>-- اختر القسم --</option>
 <?php foreach ($departments as $dept): ?>
 <?php if ($dept !== $current_major): ?>
 <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
 <?php endif; ?>
 <?php endforeach; ?>
 </select>
 <p class="text-[10px] text-slate-400 mt-1.5 px-1">* تظهر فقط الأقسام المتاحة في كليتك</p>
 </div>

 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">سبب الطلب (اختياري)</label>
 <textarea name="reason" rows="3" class="w-full p-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all bg-bg placeholder:text-slate-400" placeholder="اكتب سبباً مختصراً لرغبتك..."></textarea>
 </div>

 <button type="submit" name="submit_request" class="w-full bg-primary text-white py-4 rounded-xl font-bold shadow-lg shadow-sm hover:bg-accent hover:text-white hover:-translate-y-1 transition-all flex items-center justify-center gap-2">
 <span>إرسال الطلب</span>
 <i class="fas fa-chevron-left text-xs opacity-70"></i>
 </button>
 </form>
 <?php endif; ?>
 </div>
 </div>

 <!-- History Panel -->
 <div class="lg:col-span-2">
 <div class="bg-white p-6 rounded-2xl shadow-card border border-slate-100 h-full">
 <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
 <i class="fas fa-history text-accent"></i>
 تاريخ الطلبات
 </h3>

 <?php if (empty($requests)): ?>
 <div class="text-center py-12 text-slate-400 bg-bg rounded-2xl border border-dashed border-slate-200">
 <i class="fas fa-folder-open text-4xl mb-3 opacity-20"></i>
 <p>لم تقم بتقديم أي طلبات تشعيب مسبقاً</p>
 </div>
 <?php else: ?>
 <div class="overflow-x-auto">
 <table class="w-full text-right border-separate border-spacing-y-3">
 <thead>
 <tr class="text-slate-500 text-xs font-bold uppercase tracking-wider">
 <th class="px-4 py-2">التخصص المطلوب</th>
 <th class="px-4 py-2">التاريخ</th>
 <th class="px-4 py-2">النوع</th>
 <th class="px-4 py-2 text-center">الحالة</th>
 <th class="px-4 py-2">رد الإدارة</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($requests as $req): 
 $status_map = [
 'pending' => ['label' => 'قيد الانتظار', 'color' => 'bg-primary text-white', 'icon' => 'fa-clock'],
 'approved' => ['label' => 'تم القبول', 'color' => 'bg-primary text-white', 'icon' => 'fa-check-circle'],
 'rejected' => ['label' => 'مرفوض', 'color' => 'bg-secondary text-white', 'icon' => 'fa-times-circle'],
 ];
 $s = $status_map[$req['status']] ?? ['label' => $req['status'], 'color' => 'bg-bg', 'icon' => ''];
 ?>
 <tr class="bg-white shadow-sm border border-slate-50 hover:bg-bg transition-colors">
 <td class="px-4 py-4 rounded-r-xl font-bold text-slate-800">
 <?php echo htmlspecialchars($req['requested_major']); ?>
 </td>
 <td class="px-4 py-4 text-xs text-slate-500">
 <?php echo date('Y/m/d', strtotime($req['created_at'])); ?>
 </td>
 <td class="px-4 py-4 text-xs">
 <span class="px-2 py-1 rounded-md bg-bg text-slate-600">
 <?php echo $req['type'] === 'initial' ? 'تشعيب جديد' : 'طلب تحويل'; ?>
 </span>
 </td>
 <td class="px-4 py-4 text-center">
 <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold <?php echo $s['color']; ?>">
 <i class="fas <?php echo $s['icon']; ?>"></i>
 <?php echo $s['label']; ?>
 </span>
 </td>
 <td class="px-4 py-4 rounded-l-xl text-xs text-slate-500 italic max-w-[150px] truncate" title="<?php echo htmlspecialchars($req['admin_note'] ?? ''); ?>">
 <?php echo htmlspecialchars($req['admin_note'] ?: 'لا توجد ملاحظات'); ?>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endif; ?>
 </div>
 </div>
 </div>

 <!-- Info Section -->
 <div class="bg-bg p-6 rounded-2xl border border-primary flex items-start gap-4">
 <div class="bg-white p-3 rounded-xl text-primary shadow-sm">
 <i class="fas fa-info-circle text-xl"></i>
 </div>
 <div>
 <h4 class="font-bold text-primary mb-1">تعليمات هامة:</h4>
 <ul class="text-sm text-primary space-y-1 opacity-80 list-disc list-inside">
 <li>يتم التشعيب بناءً على القواعد الداخلية لكل كلية (المستوى الدراسي، المعدل التراكمي، إلخ).</li>
 <li>بمجرد إرسال الطلب، لا يمكنك حذفه أو تغييره حتى يتم الرد من الإدارة.</li>
 <li>في حال قبول طلبك، سيتم تحديث تخصصك في النظام فوراً وستظهر مقررات تخصصك الجديد في فترة التسجيل القادمة.</li>
 </ul>
 </div>
 </div>

</div>

<style>
@keyframes bounce-short {
 0%, 100% { transform: translateY(0); }
 50% { transform: translateY(-4px); }
}
.animate-bounce-short {
 animation: bounce-short 2s infinite;
}
</style>

<?php require_once 'includes/footer.php'; ?>
