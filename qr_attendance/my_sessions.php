<?php
require_once '../includes/header.php';
require_permission('absence');

if (!in_array($role, ['instructor', 'admin', 'super_admin', 'dean', 'affairs'])) {
 header('Location: ../index.php'); exit;
}

// Filters
$filter_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// ── Fetch sessions with role-based scoping ────────────────────────────────────
$params = [];
$where = "WHERE 1=1";

if ($role === 'instructor') {
 $where .= " AND ls.instructor_id = ?";
 $params[] = $user_id;
} elseif (in_array($role, ['dean','affairs'])) {
 $where .= " AND c.college_id = ?";
 $params[] = (int)($_SESSION['college_id'] ?? 0);
}

if ($filter_course) { $where .= " AND ls.course_id = ?"; $params[] = $filter_course; }
if ($filter_status) { $where .= " AND ls.status = ?"; $params[] = $filter_status; }
if ($filter_date_from) { $where .= " AND ls.created_at::date >= ?"; $params[] = $filter_date_from; }
if ($filter_date_to) { $where .= " AND ls.created_at::date <= ?"; $params[] = $filter_date_to; }

$sessions = [];
try {
 $stmt = $pdo->prepare("
 SELECT ls.id, ls.status, ls.created_at, ls.expires_at, ls.ended_at, ls.duration_min,
 c.name AS course_name, c.code AS course_code, c.level,
 col.name AS college_name,
 u.full_name AS instructor_name,
 COUNT(DISTINCT qa.student_id) AS attended_count,
 COUNT(DISTINCT CASE WHEN qa.status='suspicious' THEN qa.student_id END) AS suspicious_count,
 COUNT(DISTINCT e.user_id) AS enrolled_count
 FROM lecture_sessions ls
 JOIN courses c ON c.id = ls.course_id
 LEFT JOIN colleges col ON col.id = ls.college_id
 LEFT JOIN users u ON u.id = ls.instructor_id
 LEFT JOIN qr_attendance qa ON qa.session_id = ls.id
 LEFT JOIN enrollments e ON e.course_id = ls.course_id
 {$where}
 GROUP BY ls.id, ls.status, ls.created_at, ls.expires_at, ls.ended_at, ls.duration_min,
 c.name, c.code, c.level, col.name, u.full_name
 ORDER BY ls.created_at DESC
 ");
 $stmt->execute($params);
 $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Courses for filter
$my_courses = [];
try {
 if ($role === 'instructor') {
 $cs = $pdo->prepare("SELECT DISTINCT c.id, c.name FROM courses c JOIN schedule_slots ss ON ss.course_id=c.id WHERE ss.instructor_id=? ORDER BY c.name");
 $cs->execute([$user_id]);
 } else {
 $cs = $pdo->query("SELECT id, name FROM courses ORDER BY name");
 }
 $my_courses = $cs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$level_names = [0=>'إعدادي',1=>'الأولى',2=>'الثانية',3=>'الثالثة',4=>'الرابعة'];
$status_map = ['active'=>['نشطة','bg-primary text-white'],'ended'=>['منتهية','bg-bg text-slate-600'],'expired'=>['انتهت الصلاحية','bg-primary text-white']];
?>

<div class="max-w-7xl mx-auto space-y-6">

 <div class="flex flex-wrap items-center justify-between gap-4">
 <div>
 <h1 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-history text-primary bg-bg p-2 rounded-xl"></i>
 سجل جلسات الحضور
 </h1>
 <p class="text-sm text-slate-500 mt-1">عرض وإدارة كل جلسات الحضور السابقة</p>
 </div>
 <a href="start_session.php" class="bg-primary text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-accent hover:text-white transition flex items-center gap-2 shadow-sm">
 <i class="fas fa-plus"></i> جلسة جديدة
 </a>
 </div>

 <!-- Filters -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
 <form method="GET" class="flex flex-wrap items-end gap-4">
 <input type="hidden" name="tab" value="absence">

 <div class="flex-1 min-w-[180px]">
 <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1.5">المقرر</label>
 <select name="course_id" class="w-full bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary">
 <option value="0">— الكل —</option>
 <?php foreach ($my_courses as $c): ?>
 <option value="<?php echo $c['id']; ?>" <?php echo $filter_course == $c['id'] ? 'selected' : ''; ?>>
 <?php echo htmlspecialchars($c['name']); ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>

 <div>
 <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1.5">الحالة</label>
 <select name="status" class="bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary">
 <option value="">— الكل —</option>
 <option value="active" <?php echo $filter_status==='active' ? 'selected':''; ?>>نشطة</option>
 <option value="ended" <?php echo $filter_status==='ended' ? 'selected':''; ?>>منتهية</option>
 <option value="expired" <?php echo $filter_status==='expired' ? 'selected':''; ?>>منتهية الصلاحية</option>
 </select>
 </div>

 <div>
 <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1.5">من تاريخ</label>
 <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>"
 class="bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary">
 </div>

 <div>
 <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1.5">إلى تاريخ</label>
 <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>"
 class="bg-bg border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary">
 </div>

 <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-xl font-bold hover:bg-accent hover:text-white transition flex items-center gap-2">
 <i class="fas fa-search"></i> بحث
 </button>
 </form>
 </div>

 <!-- Sessions List -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
 <h2 class="font-bold text-slate-700 flex items-center gap-2">
 <i class="fas fa-table text-primary"></i>
 الجلسات
 <span class="bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full"><?php echo count($sessions); ?></span>
 </h2>
 </div>

 <?php if (empty($sessions)): ?>
 <div class="p-14 text-center text-slate-400 font-bold">
 <i class="fas fa-calendar-times text-4xl mb-3 block text-slate-200"></i>
 لا توجد جلسات بعد. أنشئ أول جلسة الآن!
 </div>
 <?php else: ?>
 <div class="overflow-x-auto">
 <table class="w-full text-right text-sm">
 <thead class="bg-bg text-slate-500 font-bold uppercase text-xs border-b border-slate-100">
 <tr>
 <th class="px-5 py-3">المقرر</th>
 <th class="px-5 py-3 text-center">التاريخ</th>
 <th class="px-5 py-3 text-center">المدة</th>
 <th class="px-5 py-3 text-center">الحضور</th>
 <th class="px-5 py-3 text-center">مشبوهون</th>
 <th class="px-5 py-3 text-center">الحالة</th>
 <th class="px-5 py-3 text-center">إجراءات</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50">
 <?php foreach ($sessions as $s):
 [$st_label, $st_class] = $status_map[$s['status']] ?? ['—','bg-bg text-slate-600'];
 $pct = ($s['enrolled_count'] > 0) ? round($s['attended_count']/$s['enrolled_count']*100) : 0;
 ?>
 <tr class="hover:bg-bg transition group">
 <td class="px-5 py-3">
 <div class="font-bold text-slate-800"><?php echo htmlspecialchars($s['course_name']); ?></div>
 <div class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($s['course_code'] ?? ''); ?>
 — الفرقة <?php echo $level_names[$s['level'] ?? 0] ?? ''; ?></div>
 </td>
 <td class="px-5 py-3 text-center text-xs text-slate-600 font-mono">
 <?php echo date('Y-m-d', strtotime($s['created_at'])); ?><br>
 <span class="text-slate-400"><?php echo date('H:i', strtotime($s['created_at'])); ?></span>
 </td>
 <td class="px-5 py-3 text-center">
 <span class="bg-bg text-slate-600 px-2.5 py-1 rounded-lg text-xs font-bold">
 <?php echo $s['duration_min']; ?> د
 </span>
 </td>
 <td class="px-5 py-3 text-center">
 <span class="font-bold text-primary"><?php echo $s['attended_count']; ?></span>
 <span class="text-slate-400 text-xs"> / <?php echo $s['enrolled_count']; ?></span>
 <div class="text-xs text-slate-400 mt-0.5"><?php echo $pct; ?>%</div>
 </td>
 <td class="px-5 py-3 text-center">
 <?php if ((int)$s['suspicious_count'] > 0): ?>
 <span class="bg-primary text-white px-2.5 py-1 rounded-full text-xs font-bold">
 <i class="fas fa-exclamation-triangle ml-0.5"></i> <?php echo $s['suspicious_count']; ?>
 </span>
 <?php else: ?>
 <span class="text-slate-300 text-xs">—</span>
 <?php endif; ?>
 </td>
 <td class="px-5 py-3 text-center">
 <span class="<?php echo $st_class; ?> px-3 py-1 rounded-full text-xs font-bold"><?php echo $st_label; ?></span>
 </td>
 <td class="px-5 py-3 text-center">
 <div class="flex items-center gap-2 justify-center opacity-0 group-hover:opacity-100 transition">
 <a href="session_report.php?session_id=<?php echo $s['id']; ?>"
 class="bg-primary text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-accent hover:text-white transition">
 <i class="fas fa-chart-bar ml-0.5"></i> تقرير
 </a>
 <?php if ($s['status'] === 'active'): ?>
 <a href="start_session.php?session_id=<?php echo $s['id']; ?>"
 class="bg-bg text-primary border border-primary px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-primary hover:text-white hover:border-primary transition">
 <i class="fas fa-eye ml-0.5"></i> عرض
 </a>
 <?php endif; ?>
 </div>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endif; ?>
 </div>

</div>

<?php require_once '../includes/footer.php'; ?>
