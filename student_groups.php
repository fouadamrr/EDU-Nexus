<?php
require_once 'includes/header.php';

if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('students');

$title = 'المجموعات الدراسية والفرق';
$filter_college = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;

// Fetch Colleges for the filter (Admin only)
$all_colleges = [];
if (in_array($role, ['super_admin', 'admin'])) {
 try {
 $stmtCol = $pdo->query("SELECT id, name FROM colleges ORDER BY name");
 $all_colleges = $stmtCol->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {}
}

// Fetch students grouped by level
$levels = [];
try {
 $params = [];
 $where = "WHERE u.role = 'student'";
 
 $college_id = $_SESSION['college_id'] ?? 0;
 if (in_array($role, ['dean', 'affairs'])) {
 if ($college_id) {
 $where .= " AND u.college_id = ?";
 $params[] = $college_id;
 } else {
 $where .= " AND 1=0";
 }
 } elseif ($filter_college > 0) {
 $where .= " AND u.college_id = ?";
 $params[] = $filter_college;
 }

 $query = "SELECT s.level, COUNT(u.id) as student_count, string_agg(u.full_name, ', ' ORDER BY u.id) as names 
 FROM users u
 JOIN students s ON u.id = s.user_id 
 $where 
 GROUP BY s.level 
 ORDER BY s.level";
 
 $stmt = $pdo->prepare($query);
 $stmt->execute($params);
 $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
 $levels = [];
}
$level_names = [0 => 'الفرقة الإعدادية/عام', 1 => 'الفرقة الأولى', 2 => 'الفرقة الثانية', 3 => 'الفرقة الثالثة', 4 => 'الفرقة الرابعة'];
?>

<div class="space-y-6 animate-fade-in-up max-w-7xl mx-auto">
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
 <div class="flex justify-between items-center mb-6 border-b pb-4 border-slate-100">
 <div>
 <h1 class="text-2xl font-bold text-secondary flex items-center gap-2"><i class="fas fa-users text-primary"></i> <?php echo $title; ?></h1>
 <p class="text-sm text-slate-500 mt-1">عرض حالة تقسيم الطلاب حسب الفرق والمجموعات الدراسية.</p>
 </div>
 
 <div class="flex items-center gap-4">
 <?php if (in_array($role, ['super_admin', 'admin'])): ?>
 <form method="GET" class="flex items-center gap-2">
 <input type="hidden" name="tab" value="schedules">
 <select name="college_id" onchange="this.form.submit()" class="bg-bg border-slate-200 text-sm font-bold text-slate-700 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary outline-none">
 <option value="0">جميع الكليات</option>
 <?php foreach($all_colleges as $col): ?>
 <option value="<?php echo $col['id']; ?>" <?php echo $filter_college == $col['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($col['name']); ?></option>
 <?php endforeach; ?>
 </select>
 </form>
 <?php endif; ?>
 <a href="all_students.php" class="bg-bg text-primary px-5 py-2.5 rounded-xl font-bold hover:bg-primary transition"><i class="fas fa-user-graduate ml-2"></i> إدارة جميع الطلاب</a>
 </div>
 </div>
 
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
 <?php if (empty($levels)): ?>
 <div class="col-span-1 md:col-span-3 p-8 text-center bg-bg border border-slate-200 rounded-2xl">
 <p class="text-slate-500 font-bold">لا يوجد طلاب مسجلون في النظام حتى الآن.</p>
 </div>
 <?php else: ?>
 <?php foreach ($levels as $l): ?>
 <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm bg-white">
 <div class="bg-bg border-b border-slate-200 px-5 py-4 flex justify-between items-center">
 <h3 class="font-bold text-lg text-secondary"><?php echo $level_names[$l['level']] ?? 'فرقة '.$l['level']; ?></h3>
 <span class="bg-primary text-white text-xs font-bold px-3 py-1 rounded-full"><?php echo $l['student_count']; ?> طالب</span>
 </div>
 <div class="p-5">
 <p class="text-sm text-slate-600 font-bold mb-3 border-b border-slate-100 pb-2">عينة من أسماء الدفعة:</p>
 <?php 
 $namesList = explode(', ', $l['names']);
 $sample = array_slice($namesList, 0, 10);
 ?>
 <ul class="text-sm text-slate-500 space-y-2 mb-4">
 <?php foreach($sample as $name): ?>
 <li><i class="fas fa-user text-slate-300 ml-1"></i> <?php echo htmlspecialchars($name); ?></li>
 <?php endforeach; ?>
 </ul>
 <?php if (count($namesList) > 10): ?>
 <p class="text-xs text-center text-slate-400 font-bold bg-bg py-2 rounded-lg">و <?php echo (count($namesList) - 10); ?> طلاب آخرين...</p>
 <?php endif; ?>
 <div class="mt-4 pt-4 border-t border-slate-100">
 <a href="all_students.php?level=<?php echo $l['level']; ?>" class="block w-full text-center bg-bg text-white font-bold py-2 rounded-xl hover:bg-sky-500 transition text-sm">عرض كل طلاب الفرقة</a>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>
 </div>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
