<?php
require_once 'includes/header.php';

if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs', 'instructor'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('schedules');



// Filters from GET
$filter_level = isset($_GET['level']) ? (int)$_GET['level'] : 0;
$filter_doctor = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$filter_college = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;

// Fetch Colleges for the filter (Admin only)
$all_colleges = [];
if (in_array($role, ['super_admin', 'admin'])) {
 try {
 $stmtCol = $pdo->query("SELECT id, name FROM colleges ORDER BY name");
 $all_colleges = $stmtCol->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {}
}

// Fetch Instructors for the filter
$instructors = [];
try {
 $stmtIns = $pdo->query("SELECT id, full_name FROM users WHERE role = 'instructor' ORDER BY full_name");
 $instructors = $stmtIns->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch schedule with instructor info
$schedule = [];
try {
 $query = "
 SELECT cs.*, c.name as course_name, c.code, c.level, u.full_name as instructor_name 
 FROM class_schedule cs 
 JOIN courses c ON cs.course_id = c.id 
 LEFT JOIN users u ON u.id = c.instructor_id
 WHERE 1=1
 ";
 
 $params = [];
 $college_id = $_SESSION['college_id'] ?? 0;
 if (in_array($role, ['dean', 'affairs'])) {
 if ($college_id) {
 $query .= " AND c.college_id = ?";
 $params[] = $college_id;
 } else {
 $query .= " AND 1=0"; // Security: Dean without college assigned sees nothing
 }
 } elseif ($filter_college > 0) {
 $query .= " AND c.college_id = ?";
 $params[] = $filter_college;
 }
 if ($filter_level > 0) {
 $query .= " AND c.level = ?";
 $params[] = $filter_level;
 }
 if ($filter_doctor > 0) {
 $query .= " AND c.instructor_id = ?";
 $params[] = $filter_doctor;
 }
 
 $query .= " ORDER BY cs.start_time";
 
 $stmt = $pdo->prepare($query);
 $stmt->execute($params);
 $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
 error_log("Error fetching schedule: " . $e->getMessage());
}

$days_ar = [
 'Saturday' => 'السبت',
 'Sunday' => 'الأحد',
 'Monday' => 'الاثنين',
 'Tuesday' => 'الثلاثاء',
 'Wednesday' => 'الأربعاء',
 'Thursday' => 'الخميس',
];

$level_ar = [0 => 'الكل', 1 => 'الأولى', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة'];

// Fixed hourly columns 08:00 → 17:00
$hour_start = 8;
$hour_end = 17;
$hours = range($hour_start, $hour_end - 1); 

function toMins(string $t): int {
 [$h, $m] = explode(':', $t);
 return (int)$h * 60 + (int)$m;
}

$grouped = [];
foreach ($days_ar as $en => $ar) { $grouped[$en] = []; }
foreach ($schedule as $row) {
 $startH = (int)substr($row['start_time'], 0, 2);
 $grouped[$row['day_of_week']][$startH][] = $row;
}

$palette = [
 1 => ['card' => 'bg-bg border-primary', 'title' => 'text-primary'],
 2 => ['card' => 'bg-bg border-primary', 'title' => 'text-primary'],
 3 => ['card' => 'bg-bg border-primary', 'title' => 'text-primary'],
];
?>

<style>
  @media print {
    @page { 
      size: A4 landscape; 
      margin: 0.3cm; 
    }
    header, .no-print, .sidebar, aside, #sidebar, .horizontal-tabs, .fixed, .print-hidden, #notif-btn, footer {
      display: none !important;
    }
    body, html {
      height: auto !important;
      overflow: visible !important;
      background: #fff !important;
      color: #000 !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    main {
       display: block !important;
       overflow: visible !important;
       padding: 0 !important;
       margin: 0 !important;
       width: 100% !important;
    }
    .print-container {
      width: 100% !important;
      max-width: none !important;
      box-shadow: none !important;
      border: none !important;
      padding: 0 !important;
      margin: 0 !important;
      background: white !important;
    }
    .overflow-x-auto {
      overflow: visible !important;
      display: block !important;
      width: 100% !important;
      border: none !important;
    }
    table {
      width: 100% !important;
      min-width: 100% !important;
      table-layout: fixed !important;
      border: 1.5px solid #000 !important;
      border-collapse: collapse !important;
      font-size: 10pt !important;
    }
    thead { display: table-header-group !important; }
    th {
      background-color: #f9fafb !important;
      color: #000 !important;
      border: 1px solid #000 !important;
      padding: 1px !important;
      font-weight: 900 !important;
      height: 25px !important;
    }
    td {
      border: 1px solid #000 !important;
      padding: 1px 3px !important;
      vertical-align: top !important;
      background-color: transparent !important;
      height: auto !important;
    }
    .group\/card {
      border: none !important;
      padding: 0 !important;
      margin: 0 !important;
      background: none !important;
      box-shadow: none !important;
      border-radius: 0 !important;
      page-break-inside: avoid !important;
    }
    .group\/card p {
      font-size: 11pt !important;
      font-weight: 800 !important;
      margin-bottom: 2px !important;
      color: #000 !important;
      line-height: 1.1 !important;
    }
    .animate-fade-in-up, .transition-all, .duration-300, .transform {
      animation: none !important;
      transition: none !important;
      transform: none !important;
    }
    .hidden.print\:block { 
      display: flex !important; 
      justify-content: space-between;
      align-items: center;
      margin-bottom: 5px !important; 
      border-bottom: 2px solid #000;
      padding-bottom: 5px;
    }
    .print-logo-box {
      border: none !important;
      padding: 0 !important;
      margin: 0 !important;
      text-align: right;
    }
    .print-info-grid {
      display: flex !important;
      gap: 15px;
      font-size: 7pt;
      font-weight: bold;
    }
    .print-time-sub { display: none !important; } /* Hide until labels to save space */
  }
</style>

<div class="space-y-6 animate-fade-in-up max-w-full mx-auto px-2 print-container">
  <!-- Welcome Banner (Modernized) -->
  <div class="relative overflow-hidden bg-gradient-to-r from-primary via-indigo-600 to-blue-600 rounded-2xl p-8 md:p-10 text-white shadow-card mb-8 border border-white/10 no-print">
    <div class="absolute -right-10 -top-10 w-48 h-48 bg-white opacity-10 rounded-full blur-3xl"></div>
    <div class="absolute -left-5 -bottom-5 w-32 h-32 bg-white opacity-10 rounded-full blur-2xl"></div>
    <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
      <div class="text-center md:text-right">
        <h1 class="text-2xl md:text-3xl font-bold mb-2 flex items-center gap-3">
          <i class="fas fa-calendar-alt"></i>
          <?php echo $page_title; ?> (مجمع)
        </h1>
        <p class="text-white/80 text-sm md:text-base font-medium">الجدول الأسبوعي الشامل — تصفية وطباعة جداول المحاضرين بدقة.</p>
      </div>
      <div class="flex items-center gap-3">
        <button onclick="window.print()" class="bg-white text-primary px-6 py-3 rounded-xl font-bold hover:bg-bg transition-all flex items-center gap-2 shadow-lg">
          <i class="fas fa-print"></i> طباعة الجدول
        </button>
      </div>
    </div>
  </div>

  <!-- Filters Section -->
  <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 no-print mb-8">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 items-end gap-5">
      <?php if (in_array($role, ['super_admin', 'admin'])): ?>
      <div class="w-full">
        <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
          <i class="fas fa-university text-primary/60 text-xs"></i> الكلية
        </label>
        <select name="college_id" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all font-medium text-slate-700">
          <option value="0">جميع الكليات</option>
          <?php foreach($all_colleges as $col): ?>
          <option value="<?php echo $col['id']; ?>" <?php echo $filter_college == $col['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($col['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="w-full">
        <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
          <i class="fas fa-layer-group text-primary/60 text-xs"></i> المستوى الدراسي
        </label>
        <select name="level" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all font-medium text-slate-700">
          <option value="0">جميع الفرق</option>
          <?php foreach($level_ar as $lv => $name): if($lv == 0) continue; ?>
          <option value="<?php echo $lv; ?>" <?php echo $filter_level == $lv ? 'selected' : ''; ?>>الفرقة <?php echo $name; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="w-full">
        <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
          <i class="fas fa-user-tie text-primary/60 text-xs"></i> المحاضر
        </label>
        <select name="doctor_id" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all font-medium text-slate-700">
          <option value="0">جميع المحاضرين</option>
          <?php foreach($instructors as $doc): ?>
          <option value="<?php echo $doc['id']; ?>" <?php echo $filter_doctor == $doc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($doc['full_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if($filter_level > 0 || $filter_doctor > 0 || $filter_college > 0): ?>
      <div class="flex items-center h-[52px]">
        <a href="lecture_schedules.php" class="text-sm font-bold text-primary hover:text-indigo-700 transition-all flex items-center gap-2 bg-primary/5 px-4 py-2 rounded-xl">
          <i class="fas fa-times-circle"></i> إلغاء التصفية
        </a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- Print Title (Only visible on print) -->
  <div class="hidden print:block mb-2">
    <div class="print-logo-box">
      <h1 class="text-lg font-black text-black uppercase tracking-tighter">EDU NEXUS UNIVERSITY</h1>
      <p class="text-[6pt] text-gray-500 font-bold">نموذج الجدول الدراسي الموحد - ربيع ٢٠٢٦</p>
    </div>
    <div class="print-info-grid">
      <?php if ($filter_college > 0): 
        $col_name = '';
        foreach($all_colleges as $c_item) { if($c_item['id'] == $filter_college) { $col_name = $c_item['name']; break; } }
      ?>
      <span>الكلية: <?php echo htmlspecialchars($col_name); ?></span>
      <?php endif; ?>
      <?php if ($filter_level > 0): ?> <span>الفرقة: <?php echo $level_ar[$filter_level]; ?></span> <?php endif; ?>
      <?php if ($filter_doctor > 0): 
        $doc_name = '';
        foreach($instructors as $d) { if($d['id'] == $filter_doctor) { $doc_name = $d['full_name']; break; } }
      ?> 
      <span>المحاضر: <?php echo htmlspecialchars($doc_name); ?></span> 
      <?php endif; ?>
      <span>تاريخ: <?php echo date('Y/m/d'); ?></span>
    </div>
  </div>

  <!-- Timetable Grid -->
  <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-sm print:shadow-none print:border-none">
    <table class="border-collapse text-sm w-full" style="min-width:1050px;" dir="rtl">
      <thead>
        <tr class="bg-slate-50 border-b border-slate-200 shadow-sm relative z-10 print:static">
          <th class="px-5 py-4 font-bold text-slate-700 text-center border-l border-slate-200 bg-slate-50 sticky right-0 z-20 print:static print:w-16">اليوم</th>
          <?php foreach ($hours as $h): ?>
          <th class="px-2 py-4 font-bold border-l border-slate-100 group transition-all print:text-black">
            <div class="flex flex-col items-center gap-1">
              <span class="text-primary text-base print:text-black font-black print:text-sm"><?php printf('%02d:00', $h); ?></span>
              <span class="text-[10px] text-slate-400 font-medium uppercase tracking-tighter print:hidden">حتى <?php printf('%02d:00', $h + 1); ?></span>
            </div>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>

 <tbody>
 <?php
 $rowIdx = 0;
 foreach ($days_ar as $en => $ar):
 $rowBg = ($rowIdx % 2 === 0) ? 'bg-white' : 'bg-bg/70';
 $rowIdx++;
 $skip = array_fill_keys($hours, false);
 ?>
 <tr class="<?php echo $rowBg; ?> border-b border-slate-100 hover:bg-bg/20 transition-colors align-top">
 <td class="px-3 py-4 font-bold text-secondary whitespace-nowrap border-l border-slate-200 bg-bg text-center align-middle">
 <?php echo $ar; ?>
 </td>

 <?php foreach ($hours as $h):
 if ($skip[$h]) continue;
 $entries = $grouped[$en][$h] ?? [];

 if (!empty($entries)):
 $first = $entries[0];
 $startMin = toMins(substr($first['start_time'], 0, 5));
 $endMin = toMins(substr($first['end_time'], 0, 5));
 $durH = max(1, (int)ceil(($endMin - $startMin) / 60));
 $span = min($durH, $hour_end - $h);

 for ($s = $h + 1; $s < $h + $span; $s++) {
 if (isset($skip[$s])) $skip[$s] = true;
 }
 ?>
  <td colspan="<?php echo $span; ?>" class="px-2 py-2 border-l border-slate-100 align-top relative">
    <div class="flex flex-col gap-2 h-full">
      <?php foreach ($entries as $s):
        $dur = max(1, (int)ceil((toMins(substr($s['end_time'],0,5)) - toMins(substr($s['start_time'],0,5))) / 60));
        // Dynamic border colors based on duration
        $borderColor = $dur >= 3 ? 'border-primary' : ($dur >= 2 ? 'border-indigo-400' : 'border-blue-300');
        $bgColor = $dur >= 3 ? 'bg-primary/5' : ($dur >= 2 ? 'bg-indigo-50/50' : 'bg-blue-50/30');
      ?>
      <div class="group/card <?php echo $bgColor; ?> border-2 <?php echo $borderColor; ?> rounded-2xl p-4 shadow-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-1">
        <p class="font-black text-slate-800 text-sm leading-tight mb-2 group-hover/card:text-primary transition-colors">
          <?php echo htmlspecialchars($s['course_name']); ?>
        </p>
        <div class="space-y-1">
          <div class="flex justify-between items-center text-[10px] font-bold">
            <span class="text-slate-500 bg-slate-100 px-2 py-0.5 rounded-lg print:hidden"><i class="fas fa-hashtag ml-1 opacity-50"></i><?php echo htmlspecialchars($s['code']); ?></span>
            <span class="text-primary bg-primary/10 px-2 py-0.5 rounded-lg font-mono print:hidden">
              <?php echo substr($s['start_time'],0,5); ?> – <?php echo substr($s['end_time'],0,5); ?>
            </span>
          </div>
          <div class="flex flex-wrap gap-1.5 mt-1">
            <span class="inline-flex items-center gap-1.5 text-[9px] bg-white border border-slate-200 text-slate-700 px-2 py-1 rounded-xl shadow-sm print:border-none print:shadow-none print:p-0">
              <i class="fas fa-location-dot text-primary print:hidden"></i>
              <span class="hidden print:inline">مكان: </span><?php echo htmlspecialchars($s['location'] ?? 'غير محدد'); ?>
            </span>
            <span class="inline-flex items-center gap-1.5 text-[9px] bg-white border border-slate-200 text-slate-600 px-2 py-1 rounded-xl shadow-sm print:hidden">
              <i class="fas fa-users text-indigo-500"></i>
              الفرقة <?php echo $level_ar[$s['level']] ?? $s['level']; ?>
            </span>
          </div>
          <?php if (!empty($s['instructor_name'])): ?>
          <div class="text-[10px] text-primary font-bold mt-2 pt-1 border-t border-slate-100 flex items-center gap-1.5 print:text-black print:border-slate-300 <?php echo ($filter_doctor != 0) ? 'hidden print:flex' : ''; ?>">
            <i class="fas fa-user-graduate text-xs print:hidden"></i> 
            <span class="hidden print:inline">د/ </span><?php echo htmlspecialchars($s['instructor_name']); ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </td>
 <?php else: ?>
 <td class="border-l border-slate-100 text-center align-middle py-4 text-slate-200 select-none text-lg">—</td>
 <?php endif; ?>
 <?php endforeach; ?>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>

  <!-- Legends & Support -->
  <div class="mt-10 flex flex-col md:flex-row justify-between items-center gap-6 no-print border-t border-slate-100 pt-8">
    <div class="flex flex-wrap gap-6 items-center">
      <p class="text-sm font-bold text-slate-500 uppercase tracking-wider">مفتاح الجدول:</p>
      <div class="flex items-center gap-2">
        <span class="w-5 h-5 rounded-lg border-2 border-blue-300 bg-blue-50/30"></span>
        <span class="text-xs font-bold text-slate-600">ساعة واحدة</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="w-5 h-5 rounded-lg border-2 border-indigo-400 bg-indigo-50/50"></span>
        <span class="text-xs font-bold text-slate-600">ساعتان</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="w-5 h-5 rounded-lg border-2 border-primary bg-primary/5"></span>
        <span class="text-xs font-bold text-slate-600">3 ساعات+</span>
      </div>
    </div>
    <div class="bg-slate-50 px-4 py-2 rounded-xl border border-slate-100 italic text-[11px] text-slate-400 font-medium">
      * ملاحظة: يتم الاعتماد على وقت البداية لتوزيع المواد في الأعمدة الإحصائية.
    </div>
  </div>
</div>

<style>
  .animate-fade-in-up { animation: fadeInUp 0.6s ease-out forwards; }
  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
  .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
  .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<?php require_once 'includes/footer.php'; ?>
