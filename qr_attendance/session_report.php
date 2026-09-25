<?php
require_once '../includes/header.php';
require_permission('absence');

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$format = $_GET['format'] ?? 'html'; // 'json' for AJAX poll from start_session

// ── Security: verify access to this session ───────────────────────────────────
$session_data = null;
if ($session_id) {
 try {
 $where_extra = in_array($role, ['admin','super_admin']) ? '' : 'AND ls.instructor_id = ?';
 $params = in_array($role, ['admin','super_admin']) ? [$session_id] : [$session_id, $user_id];

 // Dean/affairs can see their college's sessions
 if (in_array($role, ['dean','affairs'])) {
 $where_extra = 'AND c.college_id = ?';
 $params = [$session_id, (int)($_SESSION['college_id'] ?? 0)];
 }

 $stmt = $pdo->prepare("
 SELECT ls.*, c.name AS course_name, c.code AS course_code, c.level,
 col.name AS college_name, u.full_name AS instructor_name
 FROM lecture_sessions ls
 JOIN courses c ON c.id = ls.course_id
 LEFT JOIN colleges col ON col.id = ls.college_id
 LEFT JOIN users u ON u.id = ls.instructor_id
 WHERE ls.id=? {$where_extra}
 ");
 $stmt->execute($params);
 $session_data = $stmt->fetch(PDO::FETCH_ASSOC);
 } catch (Exception $e) {}
}

// ── Fetch attendees ────────────────────────────────────────────────────────────
$attendees = [];
$suspicious = [];
$all_enrolled = [];
if ($session_data) {
 try {
 $stmt = $pdo->prepare("
 SELECT u.full_name, u.username, qa.marked_at, qa.status,
 qa.distance_m, qa.student_lat, qa.student_lng, qa.student_id
 FROM qr_attendance qa
 JOIN users u ON u.id = qa.student_id
 WHERE qa.session_id = ?
 ORDER BY qa.marked_at ASC
 ");
 $stmt->execute([$session_id]);
 $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
 $attendees = $rows;
 $suspicious = array_filter($rows, fn($r) => $r['status'] === 'suspicious');

 // All enrolled students (to compute absent count)
 $stmt2 = $pdo->prepare("
 SELECT u.id, u.full_name, u.username
 FROM enrollments e JOIN users u ON u.id = e.user_id
 WHERE e.course_id = (SELECT course_id FROM lecture_sessions WHERE id=?)
 ORDER BY u.full_name
 ");
 $stmt2->execute([$session_id]);
 $all_enrolled = $stmt2->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {}
}

// ── JSON mode for AJAX poll ───────────────────────────────────────────────────
if ($format === 'json') {
 header('Content-Type: application/json');
 $rows_out = array_map(fn($r) => [
 'full_name' => $r['full_name'],
 'username' => $r['username'],
 'marked_at' => date('H:i:s', strtotime($r['marked_at'])),
 'status' => $r['status'],
 'distance_m' => $r['distance_m'],
 ], $attendees);
 echo json_encode(['count' => count($attendees), 'rows' => $rows_out]);
 exit;
}

// ── Statistics ────────────────────────────────────────────────────────────────
$total_enrolled = count($all_enrolled);
$total_present = count(array_filter($attendees, fn($r) => $r['status'] === 'present'));
$total_suspicious = count(array_filter($attendees, fn($r) => $r['status'] === 'suspicious'));
$total_absent = $total_enrolled - count($attendees);
$attendance_pct = $total_enrolled > 0
 ? round((count($attendees) / $total_enrolled) * 100)
 : 0;

// Attended student IDs for "absent" calculation
$attended_ids = array_column($attendees, 'student_id');
$absent_students = array_filter($all_enrolled, fn($s) => !in_array($s['id'], $attended_ids));

$level_names = [0=>'إعدادي',1=>'الأولى',2=>'الثانية',3=>'الثالثة',4=>'الرابعة'];
?>

<div class="max-w-6xl mx-auto space-y-6 print:space-y-4">

 <?php if (!$session_data): ?>
 <div class="bg-bg border border-primary text-primary p-6 rounded-2xl text-center font-bold">
 <i class="fas fa-exclamation-circle text-3xl mb-2 block"></i>
 الجلسة غير موجودة أو ليس لديك صلاحية لعرضها.
 </div>
 <?php else: ?>

 <!-- Header -->
 <div class="flex flex-wrap items-center justify-between gap-4 no-print">
 <div>
 <h1 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-chart-bar text-primary bg-bg p-2 rounded-xl"></i>
 تقرير الحضور — <?php echo htmlspecialchars($session_data['course_name']); ?>
 </h1>
 <p class="text-sm text-slate-500 mt-1">
 <?php echo htmlspecialchars($session_data['course_code'] ?? ''); ?>
 — الفرقة <?php echo $level_names[$session_data['level'] ?? 0] ?? ''; ?>
 — <?php echo htmlspecialchars($session_data['college_name'] ?? ''); ?>
 </p>
 </div>
 <div class="flex items-center gap-3">
 <button onclick="window.print()" class="bg-bg text-slate-600 px-4 py-2 rounded-xl font-bold hover:bg-slate-200 transition text-sm flex items-center gap-2">
 <i class="fas fa-print"></i> طباعة
 </button>
 <a href="start_session.php?session_id=<?php echo $session_id; ?>"
 class="bg-primary text-white px-4 py-2 rounded-xl font-bold hover:bg-accent hover:text-white transition text-sm flex items-center gap-2">
 <i class="fas fa-arrow-right"></i> الجلسة
 </a>
 </div>
 </div>

 <!-- Session Meta Banner -->
 <div class="bg-bg rounded-2xl p-5 text-white flex flex-wrap items-center justify-between gap-4">
 <div>
 <p class="text-slate-300 text-xs font-bold uppercase tracking-wider mb-1">معلومات الجلسة</p>
 <div class="flex flex-wrap items-center gap-4 text-sm">
 <span><i class="fas fa-user-tie ml-1 text-slate-400"></i><?php echo htmlspecialchars($session_data['instructor_name'] ?? ''); ?></span>
 <span><i class="fas fa-clock ml-1 text-slate-400"></i>بدأت: <?php echo date('H:i', strtotime($session_data['created_at'])); ?></span>
 <span><i class="fas fa-calendar ml-1 text-slate-400"></i><?php echo date('Y-m-d', strtotime($session_data['created_at'])); ?></span>
 </div>
 </div>
 <?php
 $status_map = ['active'=>['نشطة','bg-primary'],'ended'=>['منتهية','bg-slate-500'],'expired'=>['انتهت الصلاحية','bg-primary']];
 [$s_label, $s_bg] = $status_map[$session_data['status']] ?? ['—','bg-slate-500'];
 ?>
 <span class="<?php echo $s_bg; ?> text-white px-4 py-2 rounded-xl font-bold text-sm"><?php echo $s_label; ?></span>
 </div>

 <!-- Stats Cards -->
 <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 text-center">
 <i class="fas fa-users text-primary text-2xl mb-2 block"></i>
 <p class="text-3xl font-black text-slate-800"><?php echo $total_enrolled; ?></p>
 <p class="text-xs text-slate-400 font-bold mt-1">إجمالي المسجلين</p>
 </div>
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 text-center">
 <i class="fas fa-user-check text-primary text-2xl mb-2 block"></i>
 <p class="text-3xl font-black text-primary"><?php echo $total_present; ?></p>
 <p class="text-xs text-slate-400 font-bold mt-1">حاضرون</p>
 </div>
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 text-center">
 <i class="fas fa-exclamation-triangle text-primary text-2xl mb-2 block"></i>
 <p class="text-3xl font-black text-primary"><?php echo $total_suspicious; ?></p>
 <p class="text-xs text-slate-400 font-bold mt-1">مشبوهون</p>
 </div>
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 text-center">
 <i class="fas fa-user-times text-primary text-2xl mb-2 block"></i>
 <p class="text-3xl font-black text-primary"><?php echo $total_absent; ?></p>
 <p class="text-xs text-slate-400 font-bold mt-1">غائبون</p>
 </div>
 </div>

 <!-- Progress Bar -->
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
 <div class="flex justify-between items-center mb-2">
 <span class="text-sm font-bold text-slate-600">نسبة الحضور الكلية</span>
 <span class="font-black text-2xl <?php echo $attendance_pct >= 75 ? 'text-primary' : 'text-primary'; ?>"><?php echo $attendance_pct; ?>%</span>
 </div>
 <div class="bg-bg rounded-full h-3 overflow-hidden">
 <div class="h-3 rounded-full transition-all <?php echo $attendance_pct >= 75 ? 'bg-primary' : 'bg-primary'; ?>"
 style="width:<?php echo $attendance_pct; ?>%"></div>
 </div>
 <?php if ($total_suspicious > 0): ?>
 <p class="text-xs text-primary font-medium mt-2 flex items-center gap-1">
 <i class="fas fa-exclamation-triangle"></i>
 <?php echo $total_suspicious; ?> طالب سجّل حضوره من موقع مشبوه (خارج نطاق الكلية)
 </p>
 <?php endif; ?>
 </div>

 <!-- Attendees Table -->
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
 <div class="px-6 py-4 border-b border-slate-100">
 <h2 class="font-bold text-slate-700 flex items-center gap-2">
 <i class="fas fa-list-check text-primary"></i>
 قائمة الطلاب الذين سجلوا الحضور
 <span class="bg-primary text-white text-xs px-2 py-0.5 rounded-full font-bold"><?php echo count($attendees); ?></span>
 </h2>
 </div>
 <?php if (empty($attendees)): ?>
 <div class="p-10 text-center text-slate-400 font-bold">
 <i class="fas fa-user-clock text-3xl mb-2 block text-slate-200"></i>
 لم يسجل أحد حضوره في هذه المحاضرة.
 </div>
 <?php else: ?>
 <div class="overflow-x-auto">
 <table class="w-full text-right text-sm">
 <thead class="bg-bg text-slate-500 font-bold uppercase text-xs border-b border-slate-100">
 <tr>
 <th class="px-5 py-3">الطالب</th>
 <th class="px-5 py-3 text-center">وقت التسجيل</th>
 <th class="px-5 py-3 text-center">المسافة</th>
 <th class="px-5 py-3 text-center">الحالة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50">
 <?php foreach ($attendees as $a): ?>
 <tr class="hover:bg-bg transition <?php echo $a['status'] === 'suspicious' ? 'bg-bg/40' : ''; ?>">
 <td class="px-5 py-3">
 <div class="font-bold text-slate-800"><?php echo htmlspecialchars($a['full_name']); ?></div>
 <div class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($a['username'] ?? ''); ?></div>
 </td>
 <td class="px-5 py-3 text-center text-slate-600 font-mono text-xs">
 <?php echo date('H:i:s', strtotime($a['marked_at'])); ?>
 </td>
 <td class="px-5 py-3 text-center">
 <?php if ($a['distance_m'] !== null): ?>
 <span class="font-bold <?php echo $a['distance_m'] > 1000 ? 'text-primary' : 'text-primary'; ?>">
 <?php echo $a['distance_m'] >= 1000 ? round($a['distance_m']/1000, 2) . ' كم' : round($a['distance_m']) . ' م'; ?>
 </span>
 <?php else: ?>
 <span class="text-slate-300 text-xs">غير محدد</span>
 <?php endif; ?>
 </td>
 <td class="px-5 py-3 text-center">
 <?php if ($a['status'] === 'suspicious'): ?>
 <span class="bg-primary text-white px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1 justify-center">
 <i class="fas fa-exclamation-triangle"></i> مشبوه
 </span>
 <?php elseif ($a['status'] === 'no_location'): ?>
 <span class="bg-bg text-slate-600 px-3 py-1 rounded-full text-xs font-bold">بدون موقع</span>
 <?php else: ?>
 <span class="bg-primary text-white px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1 justify-center">
 <i class="fas fa-check"></i> حاضر
 </span>
 <?php endif; ?>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endif; ?>
 </div>

 <!-- Absent Students Table -->
 <?php if (!empty($absent_students)): ?>
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
 <div class="px-6 py-4 border-b border-slate-100">
 <h2 class="font-bold text-slate-700 flex items-center gap-2">
 <i class="fas fa-user-times text-primary"></i>
 الطلاب الغائبون
 <span class="bg-primary text-white text-xs px-2 py-0.5 rounded-full font-bold"><?php echo count($absent_students); ?></span>
 </h2>
 </div>
 <div class="overflow-x-auto">
 <table class="w-full text-right text-sm">
 <thead class="bg-bg text-slate-500 font-bold uppercase text-xs border-b border-slate-100">
 <tr>
 <th class="px-5 py-3">#</th>
 <th class="px-5 py-3">الطالب</th>
 <th class="px-5 py-3">الرقم الجامعي</th>
 <th class="px-5 py-3 text-center">الحالة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50">
 <?php foreach (array_values($absent_students) as $i => $ab): ?>
 <tr class="hover:bg-bg transition">
 <td class="px-5 py-3 text-slate-400"><?php echo $i+1; ?></td>
 <td class="px-5 py-3 font-bold text-slate-700"><?php echo htmlspecialchars($ab['full_name']); ?></td>
 <td class="px-5 py-3 font-mono text-xs text-slate-400"><?php echo htmlspecialchars($ab['username'] ?? ''); ?></td>
 <td class="px-5 py-3 text-center">
 <span class="bg-primary text-white px-3 py-1 rounded-full text-xs font-bold">غائب</span>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 <?php endif; ?>

 <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
