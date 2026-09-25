<?php
require_once __DIR__ . '/includes/header.php';

// Check role
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
  echo "<script>window.location.href='index.php';</script>";
  exit;
}
require_permission('students');


// Ensure libraries are included
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>';

// Get the target semester
$stmtSem = $pdo->query("SELECT DISTINCT semester FROM enrollments WHERE semester IS NOT NULL ORDER BY semester DESC LIMIT 1");
$latest_semester = $stmtSem->fetchColumn() ?: 'Fall 2025';

$semester = $_GET['semester'] ?? $latest_semester;
$filterCollege = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;

if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_id'])) {
  $filterCollege = (int)$_SESSION['college_id'];
}

$college_params = [];
$college_query_chunk = "";
if ($filterCollege > 0) {
  $college_query_chunk = " AND s.college_id = :cid ";
  $college_params[':cid'] = $filterCollege;
}

// 1. Total Enrolled Students
$qTotal = "SELECT COUNT(*) FROM students s WHERE s.enrollment_status = 'enrolled' " . $college_query_chunk;
$stmtTotal = $pdo->prepare($qTotal);
$stmtTotal->execute($college_params);
$total_students = (int)$stmtTotal->fetchColumn();

// 2. Students who registered for at least one course this semester
$qReg = "SELECT COUNT(DISTINCT e.user_id) 
         FROM enrollments e 
         JOIN students s ON e.user_id = s.user_id 
         WHERE e.semester = :sem AND s.enrollment_status = 'enrolled' " . $college_query_chunk;
$paramsReg = $college_params;
$paramsReg[':sem'] = $semester;
$stmtReg = $pdo->prepare($qReg);
$stmtReg->execute($paramsReg);
$registered_students = (int)$stmtReg->fetchColumn();

$unregistered_students = max(0, $total_students - $registered_students);
$reg_percent = $total_students > 0 ? round(($registered_students / $total_students) * 100, 1) : 0;
$unreg_percent = $total_students > 0 ? round(($unregistered_students / $total_students) * 100, 1) : 0;

// 3. Stats by College
$qByCollege = "
  SELECT 
    c.name as college_name,
    COUNT(DISTINCT s.id) as total_students,
    COUNT(DISTINCT e.user_id) as registered_students
  FROM students s
  LEFT JOIN colleges c ON s.college_id = c.id
  LEFT JOIN enrollments e ON s.user_id = e.user_id AND e.semester = :sem
  WHERE s.enrollment_status = 'enrolled'
  " . $college_query_chunk . "
  GROUP BY c.name
  ORDER BY total_students DESC
";
$stmtByCol = $pdo->prepare($qByCollege);
$stmtByColParams = [':sem' => $semester];
if ($filterCollege > 0) {
  $stmtByColParams[':cid'] = $filterCollege;
}
$stmtByCol->execute($stmtByColParams);
$college_stats = $stmtByCol->fetchAll(PDO::FETCH_ASSOC);

// 4. Stats by Course
$qByCourse = "
  SELECT 
    c.id, c.code, c.name,
    u.full_name as instructor_name,
    COUNT(e.id) as registered_count
  FROM courses c
  LEFT JOIN users u ON c.instructor_id = u.id
  LEFT JOIN enrollments e ON c.id = e.course_id AND e.semester = :sem
";
$courseParams = [':sem' => $semester];
if ($filterCollege > 0) {
  $qByCourse .= " WHERE c.college_id = :cid ";
  $courseParams[':cid'] = $filterCollege;
}
$qByCourse .= "
  GROUP BY c.id, c.code, c.name, u.full_name
  HAVING COUNT(e.id) > 0
  ORDER BY registered_count DESC
";
$stmtByCourse = $pdo->prepare($qByCourse);
$stmtByCourse->execute($courseParams);
$course_stats = $stmtByCourse->fetchAll(PDO::FETCH_ASSOC);

$colleges = $pdo->query("SELECT id, name FROM colleges ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$semesters = $pdo->query("SELECT DISTINCT semester FROM enrollments WHERE semester IS NOT NULL ORDER BY semester DESC")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($semester, $semesters)) $semesters[] = $semester;
?>

<div class="max-w-7xl mx-auto space-y-8 animate-fade-in-up">

  <!-- Welcome Banner (Modernized) -->
  <div class="relative overflow-hidden bg-gradient-to-r from-primary via-indigo-600 to-blue-600 rounded-3xl p-8 md:p-12 text-white shadow-xl mb-8 border border-white/10 no-print">
    <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
    <div class="absolute -left-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-2xl"></div>
    <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8 group">
      <div class="text-center md:text-right text-white">
        <h1 class="text-4xl font-black mb-4 text-white flex items-center justify-center md:justify-start gap-4">
          <i class="fas fa-chart-line bg-white/20 p-3 rounded-2xl backdrop-blur-md"></i>
          إحصائيات الطلاب المسجلين
        </h1>
        <p class="text-white/80 text-xl font-medium max-w-2xl leading-relaxed">تحليل ذكي لكثافة تسجيل المقررات واستخراج التقارير الرسمية بدقة متناهية للفصل الدراسي الحالي.</p>
      </div>
      <div class="flex flex-wrap items-center gap-4 w-full md:w-auto justify-center">
        <button onclick="exportToExcel()" class="bg-white/10 backdrop-blur-md border border-white/20 text-white px-6 py-3.5 rounded-2xl font-bold hover:bg-white hover:text-primary transition-all flex items-center gap-3 shadow-lg transform hover:-translate-y-1">
          <i class="fas fa-file-excel text-lg"></i> تصدير Excel
        </button>
        <button onclick="exportToPDF()" class="bg-white/10 backdrop-blur-md border border-white/20 text-white px-6 py-3.5 rounded-2xl font-bold hover:bg-white hover:text-primary transition-all flex items-center gap-3 shadow-lg transform hover:-translate-y-1">
          <i class="fas fa-file-pdf text-lg"></i> تصدير PDF
        </button>
        <button onclick="window.print()" class="bg-white text-primary px-6 py-3.5 rounded-2xl font-bold hover:bg-slate-100 transition-all flex items-center gap-3 shadow-lg transform hover:-translate-y-1">
          <i class="fas fa-print text-lg"></i> طباعة فورية
        </button>
      </div>
    </div>
  </div>

  <!-- Filters Section -->
  <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 no-print">
    <form method="GET" action="stats_registered.php" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 items-end gap-6">
      <div class="w-full">
        <label class="block text-sm font-bold text-slate-500 mb-2.5 flex items-center gap-2 uppercase tracking-wider">
          <i class="fas fa-calendar-alt text-primary/70"></i> الفصل الدراسي
        </label>
        <select name="semester" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700">
          <?php foreach($semesters as $sem): ?>
            <option value="<?= htmlspecialchars($sem) ?>" <?= $semester === $sem ? 'selected' : '' ?>><?= htmlspecialchars($sem) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!in_array($role, ['dean', 'affairs'])): ?>
      <div class="w-full">
        <label class="block text-sm font-bold text-slate-500 mb-2.5 flex items-center gap-2 uppercase tracking-wider">
          <i class="fas fa-university text-primary/70"></i> تصفية حسب الكلية
        </label>
        <select name="college_id" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700">
          <option value="0">جميع كليات الجامعة</option>
          <?php foreach($colleges as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filterCollege === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      
      <button type="submit" class="bg-primary hover:bg-indigo-700 text-white font-bold px-10 py-4 rounded-2xl shadow-xl shadow-primary/20 transition-all h-[60px] flex items-center justify-center gap-3 transform hover:scale-[1.02] active:scale-95">
        <i class="fas fa-sync-alt text-lg"></i>
        تحديث البيانات الإحصائية
      </button>
    </form>
  </div>

  <!-- KPI Cards Container -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-8 no-print">
    <!-- Card 1 -->
    <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 flex flex-col gap-6 group hover:border-primary transition-all duration-300 transform hover:-translate-y-2">
      <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-300">
        <i class="fas fa-users text-2xl"></i>
      </div>
      <div>
        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">إجمالي الطلاب المقيدين</p>
        <div class="text-4xl font-black text-slate-800 counter-val" data-target="<?= $total_students ?>">0</div>
        <div class="flex items-center gap-2 mt-4 text-slate-400 text-xs">
          <i class="fas fa-info-circle"></i> حسب اختيار التصفية الحالي
        </div>
      </div>
    </div>

    <!-- Card 2 -->
    <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 flex flex-col gap-6 group hover:border-emerald-500 transition-all duration-300 transform hover:-translate-y-2">
      <div class="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 group-hover:bg-emerald-500 group-hover:text-white transition-all duration-300">
        <i class="fas fa-user-check text-2xl"></i>
      </div>
      <div>
        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">الطلاب المسجلين لمقررات</p>
        <div class="flex items-baseline justify-between mb-4">
          <div class="text-4xl font-black text-slate-800"><?= number_format($registered_students) ?></div>
          <span class="text-xl font-bold text-emerald-600"><?= $total_students > 0 ? $reg_percent : 0 ?>%</span>
        </div>
        <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden shadow-inner">
          <div class="bg-emerald-500 h-full rounded-full transition-all duration-1000 ease-out" style="width: <?= $reg_percent ?>%"></div>
        </div>
      </div>
    </div>

    <!-- Card 3 -->
    <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 flex flex-col gap-6 group hover:border-rose-500 transition-all duration-300 transform hover:-translate-y-2">
      <div class="w-16 h-16 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-600 group-hover:bg-rose-500 group-hover:text-white transition-all duration-300">
        <i class="fas fa-user-times text-2xl"></i>
      </div>
      <div>
        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">الطلاب غير المسجلين</p>
        <div class="flex items-baseline justify-between mb-4">
          <div class="text-4xl font-black text-slate-800"><?= number_format($unregistered_students) ?></div>
          <span class="text-xl font-bold text-rose-600"><?= $total_students > 0 ? $unreg_percent : 0 ?>%</span>
        </div>
        <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden shadow-inner">
          <div class="bg-rose-500 h-full rounded-full transition-all duration-1000 ease-out" style="width: <?= $unreg_percent ?>%"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts & Main Data Group -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Registration Rate by College -->
    <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30 no-print">
        <h3 class="font-bold text-slate-800 text-xl flex items-center gap-3">
          <i class="fas fa-university text-primary bg-primary/10 p-2.5 rounded-xl text-sm"></i> 
          معدلات التسجيل حسب الكليات
        </h3>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full text-right text-sm" id="collegeStatsTable">
          <thead class="bg-slate-50/50 border-y border-slate-100 text-slate-500">
            <tr>
              <th class="p-6 font-bold uppercase tracking-wider">الكلية</th>
              <th class="p-6 text-center font-bold uppercase tracking-wider">إجمالي المقيدين</th>
              <th class="p-6 text-center font-bold uppercase tracking-wider text-primary">المسجلين</th>
              <th class="p-6 text-center font-bold uppercase tracking-wider">النسبة المئوية</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 text-slate-700">
            <?php if(empty($college_stats)): ?>
              <tr><td colspan="4" class="p-12 text-center text-slate-400 font-bold">لا توجد بيانات متاحة حالياً</td></tr>
            <?php else: ?>
              <?php foreach($college_stats as $stat): 
                $t = (int)$stat['total_students'];
                $r = (int)$stat['registered_students'];
                $p = $t > 0 ? round(($r/$t)*100, 1) : 0;
              ?>
                <tr class="hover:bg-primary/5 transition-colors group">
                  <td class="p-6 font-bold text-slate-800"><?= htmlspecialchars($stat['college_name'] ?? 'غير محدد') ?></td>
                  <td class="p-6 text-center font-mono"><?= number_format($t) ?></td>
                  <td class="p-6 text-center text-primary font-black"><?= number_format($r) ?></td>
                  <td class="p-6 text-center">
                    <div class="flex items-center justify-center gap-4">
                      <span class="w-12 font-black text-slate-800"><?= $p ?>%</span>
                      <div class="w-32 bg-slate-100 h-2.5 rounded-full overflow-hidden no-print">
                        <div class="bg-primary h-full rounded-full transition-all duration-1000" style="width: <?= $p ?>%"></div>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Registration Intensity Chart -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 flex flex-col no-print">
      <h3 class="font-bold text-slate-800 text-xl mb-8 flex items-center gap-3">
        <i class="fas fa-chart-pie text-emerald-500 bg-emerald-50 p-2.5 rounded-xl text-sm"></i>
        كثافة التسجيل العامة
      </h3>
      <div class="relative w-full flex-1 min-h-[300px]">
        <canvas id="totalRegChart"></canvas>
      </div>
      <div class="mt-8 pt-6 border-t border-slate-50 grid grid-cols-2 gap-4 text-center">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">المسجلين</p>
          <p class="text-xl font-black text-emerald-600"><?= $reg_percent ?>%</p>
        </div>
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">غير المسجلين</p>
          <p class="text-xl font-black text-rose-600"><?= $unreg_percent ?>%</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Course Details Table -->
  <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row items-center justify-between gap-6 bg-slate-50/30 no-print">
      <h3 class="font-bold text-slate-800 text-xl flex items-center gap-3">
        <i class="fas fa-book-open text-primary bg-primary/10 p-2.5 rounded-xl text-sm"></i>
        تفاصيل التسجيل لكل مقرر دراسي
      </h3>
      <div class="relative w-full md:w-96">
        <input type="text" id="courseSearch" onkeyup="searchTable()" placeholder="ابحث بكود أو اسم المقرر..." class="w-full pl-12 pr-6 py-4 rounded-2xl border border-slate-200 bg-white text-sm font-bold shadow-inner focus:ring-4 focus:ring-primary/10 outline-none transition-all">
        <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400"></i>
      </div>
    </div>
    
    <div class="overflow-x-auto max-h-[600px] overflow-y-auto custom-scrollbar">
      <table class="w-full text-right text-sm" id="coursesTable">
        <thead class="bg-slate-50 text-slate-500 sticky top-0 z-10 shadow-sm border-b border-slate-100">
          <tr>
            <th class="p-6 font-bold cursor-pointer hover:text-primary transition-colors" onclick="sortTable(0)">الكود <i class="fas fa-sort text-xs opacity-30 ml-2"></i></th>
            <th class="p-6 font-bold cursor-pointer hover:text-primary transition-colors" onclick="sortTable(1)">اسم المقرر <i class="fas fa-sort text-xs opacity-30 ml-2"></i></th>
            <th class="p-6 font-bold cursor-pointer hover:text-primary transition-colors" onclick="sortTable(2)">المحاضر <i class="fas fa-sort text-xs opacity-30 ml-2"></i></th>
            <th class="p-6 text-center font-bold cursor-pointer hover:text-primary transition-colors" onclick="sortTable(3)">المسجلين <i class="fas fa-sort text-xs opacity-30 ml-2"></i></th>
            <th class="p-6 text-center font-bold no-print">الإجراءات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50 text-slate-700">
          <?php if(empty($course_stats)): ?>
            <tr><td colspan="5" class="p-16 text-center text-slate-400 font-bold">لا يوجد تسجيل في أي مقررات حالياً</td></tr>
          <?php else: ?>
            <?php foreach($course_stats as $cstat): ?>
              <tr class="hover:bg-primary/5 transition-all group">
                <td class="p-6 font-mono font-bold text-primary"><?= htmlspecialchars($cstat['code']) ?></td>
                <td class="p-6 font-bold text-slate-800"><?= htmlspecialchars($cstat['name']) ?></td>
                <td class="p-6 text-slate-500 font-medium"><?= htmlspecialchars($cstat['instructor_name'] ?? '—') ?></td>
                <td class="p-6 text-center">
                  <span class="bg-primary/10 text-primary px-4 py-1.5 rounded-xl font-black text-lg"><?= $cstat['registered_count'] ?></span>
                </td>
                <td class="p-6 text-center no-print">
                  <a href="report_course_students.php?course_id=<?= $cstat['id'] ?>&semester=<?= urlencode($semester) ?>" 
                     class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:border-primary hover:text-primary px-5 py-2.5 rounded-xl font-bold text-xs shadow-sm transition-all transform hover:scale-105 active:scale-95" target="_blank">
                    <i class="fas fa-users-viewfinder"></i> كشف الطلاب
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <!-- Signatures for Printing -->
    <div class="hidden print:flex justify-between items-start mt-20 px-12 pt-12 border-t border-slate-800">
      <div class="text-center font-bold">
        <p class="mb-10 text-xl border-b-2 border-slate-800 pb-2">شؤون الكلية</p>
        <p class="text-sm">التوقيع: ...........................</p>
      </div>
      <div class="text-center font-bold">
        <p class="mb-10 text-xl border-b-2 border-slate-800 pb-2">وكيل الكلية</p>
        <p class="text-sm">التوقيع: ...........................</p>
      </div>
      <div class="text-center font-bold">
        <p class="mb-10 text-xl border-b-2 border-slate-800 pb-2">عميد الكلية</p>
        <p class="text-sm">التوقيع: ...........................</p>
      </div>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // 1. Interactive Counters
  const counters = document.querySelectorAll('.counter-val');
  counters.forEach(counter => {
    const target = +counter.getAttribute('data-target');
    const updateCount = () => {
      const count = +counter.innerText.replace(/,/g, '');
      const speed = target / 30; 
      if (count < target) {
        counter.innerText = Math.ceil(count + speed).toLocaleString();
        setTimeout(updateCount, 25);
      } else {
        counter.innerText = target.toLocaleString();
      }
    };
    updateCount();
  });

  // 2. Registration Analysis Chart
  const ctx = document.getElementById('totalRegChart');
  if (ctx) {
    new Chart(ctx.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['مسجل', 'غير مسجل'],
        datasets: [{
          data: [<?= $registered_students ?>, <?= $unregistered_students ?>],
          backgroundColor: ['#10b981', '#f43f5e'],
          borderWidth: 8,
          borderColor: '#ffffff',
          hoverOffset: 12
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '80%',
        plugins: {
          legend: { 
            position: 'bottom',
            labels: { 
              padding: 20,
              font: { family: 'Cairo, Tajawal, sans-serif', weight: 'bold' } 
            } 
          },
        }
      }
    });
  }
});

// Table Interactivity
function searchTable() {
  const input = document.getElementById("courseSearch");
  const filter = input.value.toUpperCase();
  const table = document.getElementById("coursesTable");
  const tr = table.getElementsByTagName("tr");
  for (let i = 1; i < tr.length; i++) {
    const txtValue = tr[i].textContent || tr[i].innerText;
    tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
  }
}

function sortTable(n) {
  const table = document.getElementById("coursesTable");
  let switching = true, i, x, y, shouldSwitch, dir = "asc", switchcount = 0;
  while (switching) {
    switching = false;
    let rows = table.rows;
    for (i = 1; i < (rows.length - 1); i++) {
      shouldSwitch = false;
      x = rows[i].getElementsByTagName("td")[n];
      y = rows[i + 1].getElementsByTagName("td")[n];
      let xVal = x.textContent || x.innerText;
      let yVal = y.textContent || y.innerText;
      if (!isNaN(parseFloat(xVal))) { xVal = parseFloat(xVal); yVal = parseFloat(yVal); }
      if (dir == "asc") { if (xVal > yVal) { shouldSwitch = true; break; } } 
      else { if (xVal < yVal) { shouldSwitch = true; break; } }
    }
    if (shouldSwitch) {
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
      switchcount++;
    } else if (switchcount == 0 && dir == "asc") { dir = "desc"; switching = true; }
  }
}

// Exports
function exportToExcel() {
  const table = document.getElementById("coursesTable");
  const wb = XLSX.utils.table_to_book(table, {sheet: "Registration"});
  XLSX.writeFile(wb, "University_Registration_Report.xlsx");
}

function exportToPDF() {
  const element = document.querySelector(".max-w-7xl");
  element.classList.add("pdf-capture-active");
  html2pdf().set({
    margin: 40,
    filename: 'Registration_Statistics.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true },
    jsPDF: { unit: 'pt', format: 'a4', orientation: 'portrait' }
  }).from(element).save().then(() => element.classList.remove("pdf-capture-active"));
}
</script>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.animate-fade-in-up { 
  animation: translateUp 0.6s ease-out forwards; 
}
@keyframes translateUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

@media print {
  body { background: white !important; font-size: 12pt; }
  .no-print, header, nav, #sidebar { display: none !important; }
  .max-w-7xl { max-width: 100% !important; margin: 0 !important; width: 100% !important; }
  table { border-collapse: collapse !important; width: 100% !important; }
  th, td { border: 1px solid #000 !important; }
  .counter-val { font-size: 24pt !important; }
  canvas { display: none !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
