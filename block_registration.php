<?php
require_once __DIR__ . '/includes/header.php';

// Check role
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('registration');


// 1. Ensure the column exists in the database
try {
 $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS registration_blocked BOOLEAN DEFAULT FALSE");
} catch (PDOException $e) {
 // Column might already exist, ignore errors.
}

$message = '';

// 2. Handle Block/Unblock Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['student_id'])) {
 $student_id = (int)$_POST['student_id'];
 $is_blocked = $_POST['action'] === 'block' ? 'true' : 'false';
 $reason = $_POST['reason'] ?? '';

 // Enforce college isolation for 'dean' and 'affairs'
 $is_authorized = true;
 if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_id'])) {
 $stmt_check = $pdo->prepare("SELECT college_id FROM students WHERE id = ?");
 $stmt_check->execute([$student_id]);
 $student_college = $stmt_check->fetchColumn();
 if ($student_college != $_SESSION['college_id']) {
 $is_authorized = false;
 }
 }

 if ($is_authorized) {
 try {
 $stmt = $pdo->prepare("UPDATE students SET registration_blocked = :blocked WHERE id = :id");
 $stmt->execute([':blocked' => $is_blocked, ':id' => $student_id]);
 
 $action_text = $is_blocked === 'true' ? 'تم حجب التسجيل' : 'تم رفع الحجب';
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 flex items-center gap-2 font-bold'>
 <i class='fas fa-check-circle'></i> $action_text بنجاح للطالب.
 </div>";
 } catch (PDOException $e) {
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 flex items-center gap-2 font-bold'>
 <i class='fas fa-exclamation-triangle'></i> خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage()) . "
 </div>";
 }
 } else {
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 flex items-center gap-2 font-bold'>
 <i class='fas fa-exclamation-triangle'></i> غير مصرح لك بتعديل بيانات هذا الطالب.
 </div>";
 }
}

// 3. Search and Filter
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all';

$query = "SELECT s.id as student_id, u.full_name, u.username as academic_number, c.name as college_name, s.level, s.registration_blocked
 FROM students s
 JOIN users u ON s.user_id = u.id
 LEFT JOIN colleges c ON s.college_id = c.id
 WHERE 1=1";

$params = [];

// Enforce college isolation for 'dean' and 'affairs'
if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_id'])) {
 $query .= " AND s.college_id = :user_college_id";
 $params[':user_college_id'] = $_SESSION['college_id'];
}

if ($search !== '') {
 $query .= " AND (u.full_name ILIKE :search OR u.username ILIKE :search)";
 $params[':search'] = "%$search%";
}

if ($filter_status === 'blocked') {
 $query .= " AND s.registration_blocked = TRUE";
} elseif ($filter_status === 'active') {
 $query .= " AND (s.registration_blocked = FALSE OR s.registration_blocked IS NULL)";
}

$query .= " ORDER BY s.registration_blocked DESC, u.full_name ASC LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="space-y-6 animate-fade-in-up">

 <!-- Header -->
 <div class="flex flex-col md:flex-row items-center justify-between gap-4">
 <div>
 <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
 <i class="fas fa-user-slash text-primary"></i>
 حجب تسجيل طالب
 </h2>
 <p class="text-gray-500 text-sm mt-1">إيقاف أو تفعيل قدرة الطالب على تسجيل المقررات الأكاديمية.</p>
 </div>
 </div>

 <?= $message ?>

 <!-- Search / Filter Card -->
 <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
 <form method="GET" action="block_registration.php" class="flex flex-col md:flex-row gap-4 items-end">
 <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>">
 
 <div class="flex-1 w-full">
 <label class="block text-sm font-bold text-gray-700 mb-2">البحث برقم القيد أو الاسم</label>
 <div class="relative">
 <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="أدخل رقم القيد أو اسم الطالب..." 
 class="w-full border border-gray-300 rounded-lg p-3 pr-10 focus:ring-2 focus:ring-primary focus:border-primary">
 <i class="fas fa-search absolute right-3 top-3.5 text-gray-400"></i>
 </div>
 </div>

 <div class="w-full md:w-64">
 <label class="block text-sm font-bold text-gray-700 mb-2">حالة التسجيل</label>
 <select name="status" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-primary focus:border-primary font-bold text-gray-700">
 <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>جميع الطلاب</option>
 <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>محجوب عن التسجيل فقط</option>
 <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>مسموح بالتسجيل</option>
 </select>
 </div>

 <button type="submit" class="bg-primary hover:bg-primary-light text-white font-bold py-3 px-6 rounded-lg transition-colors w-full md:w-auto shadow-md">
 <i class="fas fa-filter ml-2"></i> بحث وتصفية
 </button>
 </form>
 </div>

 <!-- Results Table -->
 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-gray-200">
 <tr>
 <th class="p-4 text-sm font-bold text-gray-600">رقم القيد</th>
 <th class="p-4 text-sm font-bold text-gray-600">اسم الطالب</th>
 <th class="p-4 text-sm font-bold text-gray-600">الكلية / الفرقة</th>
 <th class="p-4 text-sm font-bold text-gray-600 text-center">حالة التسجيل</th>
 <th class="p-4 text-sm font-bold text-gray-600 text-center">إجراءات</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 <?php if (empty($students_list)): ?>
 <tr>
 <td colspan="5" class="p-8 text-center text-gray-500 font-bold">لا توجد نتائج للبحث</td>
 </tr>
 <?php else: ?>
 <?php foreach ($students_list as $s): 
 $is_blocked = ($s['registration_blocked'] === true || $s['registration_blocked'] === 't' || $s['registration_blocked'] === 1);
 ?>
 <tr class="hover:bg-bg transition-colors">
 <td class="p-4 font-mono font-bold text-primary">
 <?= htmlspecialchars($s['academic_number']) ?>
 </td>
 <td class="p-4 font-bold text-gray-800">
 <?= htmlspecialchars($s['full_name']) ?>
 </td>
 <td class="p-4 text-sm text-gray-600">
 <?= htmlspecialchars($s['college_name'] ?? 'بدون كلية') ?> - الفرقة <?= $s['level'] ?>
 </td>
 <td class="p-4 text-center">
 <?php if ($is_blocked): ?>
 <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-primary text-white border border-primary">
 <i class="fas fa-lock"></i> التسجيل محجوب
 </span>
 <?php else: ?>
 <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-primary text-white border border-primary">
 <i class="fas fa-unlock"></i> مسموح بالتسجيل
 </span>
 <?php endif; ?>
 </td>
 <td class="p-4 text-center">
 <form method="POST" action="" class="inline-block" onsubmit="return confirm('هل أنت متأكد من هذا الإجراء؟');">
 <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
 <?php if ($is_blocked): ?>
 <input type="hidden" name="action" value="unblock">
 <button type="submit" class="bg-white border border-primary text-primary hover:bg-bg hover:text-primary px-4 py-1.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
 <i class="fas fa-unlock"></i> رفع الحجب
 </button>
 <?php else: ?>
 <input type="hidden" name="action" value="block">
 <button type="submit" class="bg-white border border-primary text-primary hover:bg-bg hover:text-primary px-4 py-1.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
 <i class="fas fa-ban"></i> حجب الطالب
 </button>
 <?php endif; ?>
 </form>
 </td>
 </tr>
 <?php endforeach; ?>
 <?php endif; ?>
 </tbody>
 </table>
 </div>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
