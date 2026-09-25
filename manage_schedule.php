<?php
require_once 'includes/header.php';

if (!in_array($role, ['admin', 'dean', 'affairs', 'super_admin'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('schedules');


require_once __DIR__ . '/config/database.php';
$db = Database::getConnection();

// ── Auto-create class_schedule table ──────────────────────────────────────────
$db->exec("
 CREATE TABLE IF NOT EXISTS class_schedule (
 id SERIAL PRIMARY KEY,
 course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
 day_of_week VARCHAR(20) NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NOT NULL,
 location VARCHAR(100),
 created_at TIMESTAMPTZ DEFAULT NOW()
 );
 CREATE INDEX IF NOT EXISTS idx_cs_course ON class_schedule(course_id);
");

$message = '';
$days_ar = [
 'Saturday' => 'السبت',
 'Sunday' => 'الأحد',
 'Monday' => 'الاثنين',
 'Tuesday' => 'الثلاثاء',
 'Wednesday' => 'الأربعاء',
 'Thursday' => 'الخميس',
];

// ── Handle Add ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
 $cid = (int)$_POST['course_id'];
 $day = $_POST['day_of_week'];
 $st = $_POST['start_time'];
 $et = $_POST['end_time'];
 $loc = trim($_POST['location'] ?? '');

 if ($cid && $day && $st && $et) {
 $stmt = $db->prepare("
 INSERT INTO class_schedule (course_id, day_of_week, start_time, end_time, location)
 VALUES (?, ?, ?, ?, ?)
 ");
 $stmt->execute([$cid, $day, $st, $et, $loc ?: null]);
 $message = '<div class="bg-primary text-white p-3 rounded mb-4">✅ تم إضافة موعد المحاضرة بنجاح</div>';
 } else {
 $message = '<div class="bg-primary text-white p-3 rounded mb-4">❌ يرجى ملء جميع الحقول المطلوبة</div>';
 }
}

// ── Handle Delete ─────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
 $db->prepare("DELETE FROM class_schedule WHERE id = ?")->execute([(int)$_GET['delete']]);
 $message = '<div class="bg-secondary text-white p-3 rounded mb-4">🗑️ تم حذف الموعد</div>';
}

$college_id = $_SESSION['college_id'] ?? 0;
$college_filter = "";
$params = [];
if (in_array($role, ['dean', 'affairs']) && $college_id) {
 $college_filter = " WHERE c.college_id = ? ";
 $params[] = $college_id;
} else if (in_array($role, ['dean', 'affairs']) && !$college_id) {
 // Failsafe if college_id is missing for localized admin
 $college_filter = " WHERE 1=0 ";
}

$courses_query = "SELECT id, code, name, level FROM courses c" . $college_filter . " ORDER BY level, name";
$stmt = $db->prepare($courses_query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$schedule_query = "
 SELECT cs.*, c.name AS course_name, c.code AS course_code, c.level AS course_level
 FROM class_schedule cs
 JOIN courses c ON cs.course_id = c.id
 " . $college_filter . "
 ORDER BY CASE cs.day_of_week
 WHEN 'Saturday' THEN 1 WHEN 'Sunday' THEN 2 WHEN 'Monday' THEN 3
 WHEN 'Tuesday' THEN 4 WHEN 'Wednesday' THEN 5 WHEN 'Thursday' THEN 6
 ELSE 7 END, cs.start_time
";
$stmt = $db->prepare($schedule_query);
$stmt->execute($params);
$schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

$level_ar = [0 => 'عام', 1 => 'الأولى', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة'];
?>

<div class="max-w-6xl mx-auto space-y-6">

 <div class="flex items-center justify-between">
 <div>
 <h2 class="text-2xl font-bold text-gray-800">
 <i class="fas fa-clock text-accent ml-2"></i>إدارة جدول المحاضرات
 </h2>
 <p class="text-sm text-gray-500 mt-1">حدد يوم ووقت ومكان كل محاضرة حتى تظهر للطلاب في جدولهم الدراسي</p>
 </div>
 <button onclick="document.getElementById('addModal').classList.remove('hidden')"
 class="bg-primary text-white px-5 py-2.5 rounded-xl shadow font-bold hover:bg-opacity-90 transition">
 <i class="fas fa-plus ml-1"></i> إضافة موعد محاضرة
 </button>
 </div>

 <?php echo $message; ?>

 <!-- Schedule Table -->
 <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden">
 <?php if (empty($schedule)): ?>
 <div class="py-20 text-center text-gray-400">
 <i class="fas fa-calendar-times text-5xl mb-4 block text-gray-200"></i>
 <p class="font-bold text-lg">لا توجد مواعيد محاضرات بعد</p>
 <p class="text-sm mt-1">اضغط على "إضافة موعد محاضرة" لبدء بناء الجدول</p>
 </div>
 <?php else: ?>
 <table class="w-full text-right text-sm">
 <thead class="bg-bg border-b border-gray-100 text-gray-500 font-bold">
 <tr>
 <th class="p-4">المقرر</th>
 <th class="p-4 text-center">الفرقة</th>
 <th class="p-4 text-center">اليوم</th>
 <th class="p-4 text-center">من</th>
 <th class="p-4 text-center">إلى</th>
 <th class="p-4">القاعة / المكان</th>
 <th class="p-4 text-center">حذف</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-50">
 <?php foreach ($schedule as $row):
 $lv = (int)($row['course_level'] ?? 0);
 $day_ar = $days_ar[$row['day_of_week']] ?? $row['day_of_week'];
 ?>
 <tr class="hover:bg-bg transition">
 <td class="p-4 font-bold text-primary">
 <?php echo htmlspecialchars($row['course_name']); ?>
 <span class="text-xs text-gray-400 font-mono ml-1">(<?php echo $row['course_code']; ?>)</span>
 </td>
 <td class="p-4 text-center">
 <span class="text-xs bg-primary text-white font-bold px-2 py-0.5 rounded">
 <?php echo $level_ar[$lv] ?? $lv; ?>
 </span>
 </td>
 <td class="p-4 text-center font-bold text-gray-700"><?php echo $day_ar; ?></td>
 <td class="p-4 text-center font-mono text-primary"><?php echo substr($row['start_time'], 0, 5); ?></td>
 <td class="p-4 text-center font-mono text-primary"><?php echo substr($row['end_time'], 0, 5); ?></td>
 <td class="p-4 text-gray-500"><?php echo htmlspecialchars($row['location'] ?? '—'); ?></td>
 <td class="p-4 text-center">
 <a href="?delete=<?php echo $row['id']; ?>"
 class="text-xs bg-bg border border-primary text-primary hover:bg-primary px-3 py-1 rounded transition"
 onclick="return confirm('حذف هذا الموعد؟')">
 <i class="fas fa-trash"></i>
 </a>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 <?php endif; ?>
 </div>
</div>

<!-- Modal: Add Schedule Slot -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
 <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
 <div class="flex justify-between items-center mb-5 border-b pb-3">
 <h3 class="text-xl font-bold"><i class="fas fa-clock text-primary ml-2"></i>إضافة موعد محاضرة</h3>
 <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-primary">
 <i class="fas fa-times text-xl"></i>
 </button>
 </div>
 <form method="POST" class="space-y-4">
 <input type="hidden" name="action" value="add">
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">المقرر الدراسي <span class="text-primary">*</span></label>
 <select name="course_id" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-primary text-sm">
 <option value="">اختر المقرر...</option>
 <?php foreach ($courses as $c): ?>
 <option value="<?php echo $c['id']; ?>">
 الفرقة <?php echo $c['level'] ?: '?'; ?> — <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['code']; ?>)
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">يوم المحاضرة <span class="text-primary">*</span></label>
 <select name="day_of_week" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-primary text-sm">
 <option value="">اختر اليوم...</option>
 <?php foreach ($days_ar as $en => $ar): ?>
 <option value="<?php echo $en; ?>"><?php echo $ar; ?></option>
 <?php endforeach; ?>
 </select>
 </div>
 <div class="grid grid-cols-2 gap-3">
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">من (وقت البداية) <span class="text-primary">*</span></label>
 <select name="start_time" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-primary text-sm">
 <?php for ($h = 8; $h < 17; $h++): ?>
 <option value="<?php echo sprintf('%02d:00', $h); ?>">
 <?php echo date('g:i A', strtotime(sprintf('%02d:00', $h))); ?>
 </option>
 <?php endfor; ?>
 </select>
 </div>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">إلى (وقت النهاية) <span class="text-primary">*</span></label>
 <select name="end_time" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-primary text-sm">
 <?php for ($h = 9; $h <= 17; $h++): ?>
 <option value="<?php echo sprintf('%02d:00', $h); ?>">
 <?php echo date('g:i A', strtotime(sprintf('%02d:00', $h))); ?>
 </option>
 <?php endfor; ?>
 </select>
 </div>
 </div>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">القاعة / المكان</label>
 <input type="text" name="location" placeholder="مثال: قاعة 101 - المبنى الرئيسي"
 class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-primary text-sm">
 </div>
 <div class="pt-3 flex gap-3">
 <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
 class="flex-1 bg-bg py-2 rounded-lg font-bold hover:bg-gray-200">إلغاء</button>
 <button type="submit" class="flex-1 bg-primary text-white py-2 rounded-lg font-bold hover:bg-opacity-90 shadow">
 <i class="fas fa-save ml-1"></i> حفظ الموعد
 </button>
 </div>
 </form>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
