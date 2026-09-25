<?php
// manage_departments.php
require_once 'includes/header.php';
require_once __DIR__ . '/controllers/DepartmentController.php';

// Role Check
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('programs'); // Using programs permission as departments relate to study programs

$controller = new DepartmentController();
$college_id = $_SESSION['college_id'] ?? 0;
$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete'])) {
 $action = $_POST['action'] ?? (isset($_GET['delete']) ? 'delete' : null);
 $data = $_POST;
 if (isset($_GET['delete'])) $data['id'] = $_GET['delete'];
 
 $message = $controller->handleRequest($data, $action, (int)$college_id, $role);
}

// Fetch Data
$departments = $controller->getDepartments((int)$college_id, $role);
$colleges = (in_array($role, ['super_admin', 'admin'])) ? $controller->getCollegeList() : [];
?>

<div class="max-w-6xl mx-auto space-y-6">

 <?php echo $message; ?>

 <!-- Page Header -->
 <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 bg-bg rounded-xl flex items-center justify-center text-primary">
 <i class="fas fa-sitemap text-xl"></i>
 </div>
 <div>
 <h2 class="text-2xl font-bold text-slate-800">إدارة الأقسام العلمية</h2>
 <p class="text-sm text-slate-500 mt-0.5">
 إجمالي الأقسام: <strong><?php echo count($departments); ?></strong>
 </p>
 </div>
 </div>
 <button onclick="openAddModal()"
 class="bg-primary text-white px-5 py-2.5 rounded-xl hover:bg-primary transition-all shadow-md shadow-sm flex items-center gap-2 font-bold">
 <i class="fas fa-plus"></i> إضافة قسم جديد
 </button>
 </div>

 <!-- Departments Table -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-slate-100 text-slate-500 font-bold text-xs uppercase tracking-wider">
 <tr>
 <th class="px-6 py-4">#</th>
 <th class="px-6 py-4">اسم القسم</th>
 <th class="px-6 py-4">الكلية التابع لها</th>
 <th class="px-6 py-4 text-center">الإجراءات</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50">
 <?php if (empty($departments)): ?>
 <tr>
 <td colspan="4" class="text-center py-20 text-slate-400">
 <i class="fas fa-layer-group text-4xl mb-3 block opacity-20"></i>
 لا توجد أقسام مسجلة حالياً
 </td>
 </tr>
 <?php else: ?>
 <?php foreach ($departments as $index => $dept): 
 // If admin, show college name
 $c_name = 'كليتي';
 if (in_array($role, ['super_admin', 'admin'])) {
 foreach($colleges as $c) {
 if ($c['id'] == $dept['college_id']) { $c_name = $c['name']; break; }
 }
 }
 ?>
 <tr class="hover:bg-bg/50 transition-colors">
 <td class="px-6 py-4 text-slate-400 font-mono text-xs"><?php echo $index + 1; ?></td>
 <td class="px-6 py-4">
 <div class="font-bold text-slate-800"><?php echo htmlspecialchars($dept['name']); ?></div>
 </td>
 <td class="px-6 py-4">
 <span class="text-xs font-bold px-3 py-1 bg-bg text-slate-600 rounded-full">
 <?php echo htmlspecialchars($c_name); ?>
 </span>
 </td>
 <td class="px-6 py-4 text-center">
 <div class="flex items-center justify-center gap-2">
 <button onclick='openEditModal(<?php echo json_encode($dept, JSON_UNESCAPED_UNICODE); ?>)'
 class="w-9 h-9 rounded-lg bg-bg text-primary hover:bg-primary hover:text-white transition-all flex items-center justify-center"
 title="تعديل">
 <i class="fas fa-edit text-sm"></i>
 </button>
 <a href="?delete=<?php echo $dept['id']; ?>"
 class="w-9 h-9 rounded-lg bg-bg text-primary hover:bg-primary hover:text-white transition-all flex items-center justify-center"
 onclick="return confirm('هل أنت متأكد من حذف هذا القسم؟');"
 title="حذف">
 <i class="fas fa-trash-alt text-sm"></i>
 </a>
 </div>
 </td>
 </tr>
 <?php endforeach; ?>
 <?php endif; ?>
 </tbody>
 </table>
 </div>
 </div>
</div>

<!-- Modal -->
<div id="deptModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
 <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-slide-up">
 <div class="p-6 border-b border-slate-100 bg-bg flex justify-between items-center">
 <h3 class="font-bold text-slate-800 flex items-center gap-2">
 <i class="fas fa-sitemap text-primary"></i>
 <span id="modalTitle">إضافة قسم جديد</span>
 </h3>
 <button onclick="closeModal()" class="text-slate-400 hover:text-primary transition-colors">
 <i class="fas fa-times text-xl"></i>
 </button>
 </div>
 <form method="POST" class="p-6 space-y-5">
 <input type="hidden" name="action" id="formAction" value="add">
 <input type="hidden" name="id" id="deptId">

 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">اسم القسم</label>
 <input type="text" name="name" id="deptName" required placeholder="مثال: قسم لغة عربية"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-300 outline-none transition-all bg-bg">
 </div>

 <?php if (in_array($role, ['super_admin', 'admin'])): ?>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">الكلية</label>
 <select name="college_id" id="deptCollegeId" class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-300 outline-none transition-all bg-bg" required>
 <option value="" disabled selected>-- اختر الكلية --</option>
 <?php foreach ($colleges as $c): ?>
 <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
 <?php endforeach; ?>
 </select>
 </div>
 <?php endif; ?>

 <div class="flex gap-3 pt-4 border-t border-slate-50">
 <button type="button" onclick="closeModal()" class="flex-1 bg-bg text-slate-600 font-bold py-3 rounded-xl hover:bg-slate-200 transition-all">إلغاء</button>
 <button type="submit" class="flex-1 bg-primary text-white font-bold py-3 rounded-xl hover:bg-primary shadow-lg shadow-sm transition-all">حفظ البيانات</button>
 </div>
 </form>
 </div>
</div>

<script>
const modal = document.getElementById('deptModal');
const modalTitle = document.getElementById('modalTitle');
const formAction = document.getElementById('formAction');
const deptId = document.getElementById('deptId');
const deptName = document.getElementById('deptName');
const deptCollegeId = document.getElementById('deptCollegeId');

function openAddModal() {
 modal.classList.remove('hidden');
 modalTitle.innerText = 'إضافة قسم جديد';
 formAction.value = 'add';
 deptId.value = '';
 deptName.value = '';
 if (deptCollegeId) deptCollegeId.value = '';
}

function openEditModal(dept) {
 modal.classList.remove('hidden');
 modalTitle.innerText = 'تعديل بيانات القسم';
 formAction.value = 'edit';
 deptId.value = dept.id;
 deptName.value = dept.name;
 if (deptCollegeId) deptCollegeId.value = dept.college_id;
}

function closeModal() {
 modal.classList.add('hidden');
}

window.onclick = function(event) {
 if (event.target == modal) closeModal();
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
</style>

<?php require_once 'includes/footer.php'; ?>
