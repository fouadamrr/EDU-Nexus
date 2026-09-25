<?php
require_once 'includes/header.php';

/** @var UniversityDB $db */

// Fetch student's current GPA from student_details
$current_gpa_default = '';
$total_credits_default = '';
if ($role === 'student') {
 $details = $db->find('student_details', 'user_id', $user_id);
 if ($details) {
 $current_gpa_default = number_format((float)($details['gpa'] ?? 0), 2);
 // Count earned credit hours
 $grades = $db->findAll('grades', ['user_id' => $user_id]);
 $all_courses = $db->findAll('courses');
 $courses_map = array_column($all_courses, null, 'id');
 $total_ch = 0;
 foreach ($grades as $g) {
 if (isset($courses_map[$g['course_id']])) {
 $total_ch += (int)($courses_map[$g['course_id']]['credit_hours'] ?? 3);
 }
 }
 $total_credits_default = $total_ch > 0 ? $total_ch : '';
 }
}
?>

<div class="max-w-4xl mx-auto space-y-6">

 <!-- Page Header -->
 <div class="flex items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
 <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center text-primary">
 <i class="fas fa-calculator text-xl"></i>
 </div>
 <div>
 <h2 class="text-2xl font-bold text-slate-800">حاسبة المعدل التراكمي (GPA)</h2>
 <p class="text-sm text-slate-500 mt-0.5">احسب معدلك المتوقع بعد إضافة مقررات الفصل الحالي</p>
 </div>
 </div>

 <!-- GPA Scale Reference -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
 <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
 <i class="fas fa-table text-primary"></i> جدول التقديرات والنقاط
 </h3>
 <div class="overflow-x-auto">
 <table class="w-full text-center text-sm">
 <thead class="bg-bg border-b border-slate-100">
 <tr>
 <th class="px-3 py-2 font-bold text-slate-600">التقدير</th>
 <th class="px-3 py-2 font-bold text-slate-600">الحرف</th>
 <th class="px-3 py-2 font-bold text-slate-600">النقاط</th>
 <th class="px-3 py-2 font-bold text-slate-600">النسبة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50">
 <?php
 $scale = [
 ['ممتاز', 'A+', 4.0, '97-100%', 'emerald'],
 ['ممتاز', 'A', 4.0, '93-96%', 'emerald'],
 ['ممتاز ناقص', 'A-', 3.7, '90-92%', 'teal'],
 ['جيد جداً', 'B+', 3.3, '87-89%', 'blue'],
 ['جيد جداً', 'B', 3.0, '83-86%', 'blue'],
 ['جيد جداً ناقص', 'B-', 2.7, '80-82%', 'sky'],
 ['جيد', 'C+', 2.3, '77-79%', 'amber'],
 ['جيد', 'C', 2.0, '73-76%', 'amber'],
 ['جيد ناقص', 'C-', 1.7, '70-72%', 'orange'],
 ['مقبول', 'D+', 1.3, '67-69%', 'rose'],
 ['مقبول', 'D', 1.0, '60-66%', 'rose'],
 ['راسب', 'F', 0.0, 'أقل من 60%', 'red'],
 ];
 $colors = ['emerald' => 'text-primary bg-bg', 'teal' => 'text-primary bg-bg', 'blue' => 'text-primary bg-bg', 'sky' => 'text-sky-700 bg-bg', 'amber' => 'text-primary bg-bg', 'orange' => 'text-primary bg-bg', 'rose' => 'text-primary bg-bg', 'red' => 'text-primary bg-bg'];
 foreach ($scale as $row):
 ?>
 <tr class="hover:bg-bg/50">
 <td class="px-3 py-2 text-slate-600"><?php echo $row[0]; ?></td>
 <td class="px-3 py-2">
 <span class="inline-flex items-center justify-center w-10 h-7 rounded-lg font-bold text-sm <?php echo $colors[$row[3] === 'أقل من 60%' ? 'red' : $row[4]]; ?>"><?php echo $row[1]; ?></span>
 </td>
 <td class="px-3 py-2 font-bold text-slate-700"><?php echo number_format($row[2], 1); ?></td>
 <td class="px-3 py-2 text-slate-500"><?php echo $row[3]; ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>

 <!-- Calculator -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
 <h3 class="font-bold text-slate-700 mb-6 flex items-center gap-2">
 <i class="fas fa-calculator text-primary"></i> حاسبة المعدل
 </h3>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
 <!-- Current GPA -->
 <div>
 <label class="block text-sm font-bold text-slate-600 mb-2">معدلك التراكمي الحالي (CGPA)</label>
 <input type="number" id="current_gpa" value="<?php echo $current_gpa_default; ?>" min="0" max="4" step="0.01"
 placeholder="مثال: 3.20"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-300 focus:border-primary bg-bg focus:bg-white transition-colors font-bold text-lg text-center text-primary"
 oninput="calculate()">
 <p class="text-xs text-slate-400 mt-1 text-center">من 0.00 إلى 4.00</p>
 </div>
 <!-- Current Credits -->
 <div>
 <label class="block text-sm font-bold text-slate-600 mb-2">إجمالي الساعات المعتمدة المكتسبة</label>
 <input type="number" id="current_credits" value="<?php echo $total_credits_default; ?>" min="0" step="1"
 placeholder="مثال: 60"
 class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-300 focus:border-primary bg-bg focus:bg-white transition-colors font-bold text-lg text-center text-primary"
 oninput="calculate()">
 <p class="text-xs text-slate-400 mt-1 text-center">الساعات حتى الفصل الماضي</p>
 </div>
 </div>

 <!-- New Courses Table -->
 <div class="mb-6">
 <div class="flex items-center justify-between mb-3">
 <h4 class="font-bold text-slate-600 flex items-center gap-2">
 <i class="fas fa-plus-circle text-primary"></i>
 مقررات الفصل الحالي (المتوقعة)
 </h4>
 <button onclick="addRow()" class="text-sm bg-primary text-white hover:bg-primary px-4 py-1.5 rounded-lg font-bold transition-colors">
 <i class="fas fa-plus mr-1"></i> إضافة مقرر
 </button>
 </div>

 <div class="overflow-x-auto rounded-xl border border-slate-100">
 <table class="w-full text-sm" id="courses-table">
 <thead class="bg-bg border-b border-slate-100">
 <tr>
 <th class="px-4 py-3 text-right font-bold text-slate-500">اسم المقرر (اختياري)</th>
 <th class="px-4 py-3 font-bold text-slate-500 text-center">الساعات</th>
 <th class="px-4 py-3 font-bold text-slate-500 text-center">التقدير المتوقع</th>
 <th class="px-4 py-3 font-bold text-slate-500 text-center">النقاط</th>
 <th class="px-2 py-3"></th>
 </tr>
 </thead>
 <tbody id="courses-body">
 <!-- Rows added by JS -->
 </tbody>
 </table>
 </div>
 </div>

 <!-- Result -->
 <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
 <div class="bg-bg border border-primary rounded-2xl p-5 text-center">
 <p class="text-sm font-bold text-primary mb-1">الساعات الجديدة</p>
 <p class="text-3xl font-bold text-accent" id="new-credits-display">0</p>
 <p class="text-xs text-primary mt-1">ساعات الفصل الحالي</p>
 </div>
 <div class="bg-bg border border-primary rounded-2xl p-5 text-center">
 <p class="text-sm font-bold text-primary mb-1">إجمالي الساعات</p>
 <p class="text-3xl font-bold text-accent" id="total-credits-display">0</p>
 <p class="text-xs text-primary mt-1">بعد الفصل الحالي</p>
 </div>
 <div id="result-card" class="bg-bg border border-primary rounded-2xl p-5 text-center">
 <p class="text-sm font-bold text-primary mb-1">المعدل التراكمي المتوقع</p>
 <p class="text-3xl font-bold text-primary" id="new-gpa-display">--.--</p>
 <p class="text-xs text-primary mt-1" id="result-grade-label">--</p>
 </div>
 </div>

 <!-- Interpretation bar -->
 <div class="mt-5">
 <div class="flex justify-between text-xs text-slate-400 mb-1">
 <span>راسب (0.0)</span>
 <span>مقبول (1.0)</span>
 <span>جيد (2.0)</span>
 <span>جيد جداً (3.0)</span>
 <span>ممتاز (4.0)</span>
 </div>
 <div class="h-3 bg-bg rounded-full relative overflow-hidden">
 <div id="gpa-bar-indicator" class="absolute top-0 h-full w-1 bg-white shadow-lg rounded-full transition-all duration-500" style="left:0%"></div>
 </div>
 </div>
 </div>
</div>

<script>
const GRADE_POINTS = {
 'A+': 4.0, 'A': 4.0, 'A-': 3.7,
 'B+': 3.3, 'B': 3.0, 'B-': 2.7,
 'C+': 2.3, 'C': 2.0, 'C-': 1.7,
 'D+': 1.3, 'D': 1.0, 'F': 0.0
};
const GRADES = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'];

function getGradeLabel(gpa) {
 if (gpa >= 3.7) return 'ممتاز';
 if (gpa >= 3.3) return 'جيد جداً +';
 if (gpa >= 3.0) return 'جيد جداً';
 if (gpa >= 2.7) return 'جيد +';
 if (gpa >= 2.0) return 'جيد';
 if (gpa >= 1.7) return 'مقبول +';
 if (gpa >= 1.0) return 'مقبول';
 return 'راسب';
}

function addRow(name='', credits='3', grade='B') {
 const tbody = document.getElementById('courses-body');
 const row = document.createElement('tr');
 row.className = 'border-b border-slate-50 hover:bg-bg/50 group';

 const gradeOptions = GRADES.map(g =>
 `<option value="${g}" ${g === grade ? 'selected' : ''}>${g} (${GRADE_POINTS[g].toFixed(1)})</option>`
 ).join('');

 row.innerHTML = `
 <td class="px-4 py-2">
 <input type="text" placeholder="اسم المقرر..." value="${name}"
 class="w-full text-sm border-0 bg-transparent focus:outline-none text-slate-600 placeholder-slate-300" oninput="calculate()">
 </td>
 <td class="px-4 py-2">
 <input type="number" value="${credits}" min="1" max="6" step="1"
 class="w-16 text-center border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-bold focus:ring-2 focus:ring-violet-200 block mx-auto" oninput="calculate()">
 </td>
 <td class="px-4 py-2">
 <select class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-violet-200 block mx-auto" onchange="calculate()">
 ${gradeOptions}
 </select>
 </td>
 <td class="px-4 py-2 text-center">
 <span class="grade-points font-bold text-primary">${GRADE_POINTS[grade].toFixed(1)}</span>
 </td>
 <td class="px-2 py-2 text-center">
 <button onclick="this.closest('tr').remove(); calculate();"
 class="w-7 h-7 rounded-lg text-slate-300 hover:text-primary hover:bg-bg transition-colors flex items-center justify-center mx-auto">
 <i class="fas fa-times text-xs"></i>
 </button>
 </td>
 `;
 tbody.appendChild(row);

 // Update points display on select change
 const select = row.querySelector('select');
 const points = row.querySelector('.grade-points');
 select.addEventListener('change', () => {
 points.textContent = GRADE_POINTS[select.value].toFixed(1);
 });

 calculate();
}

function calculate() {
 const currentGPA = parseFloat(document.getElementById('current_gpa').value) || 0;
 const currentCredits = parseInt(document.getElementById('current_credits').value) || 0;

 const rows = document.querySelectorAll('#courses-body tr');
 let newPoints = 0, newCredits = 0;

 rows.forEach(row => {
 const creditInput = row.querySelector('input[type=number]');
 const gradeSelect = row.querySelector('select');
 if (!creditInput || !gradeSelect) return;
 const ch = parseInt(creditInput.value) || 0;
 const pts = GRADE_POINTS[gradeSelect.value] ?? 0;
 newCredits += ch;
 newPoints += ch * pts;
 });

 const totalCredits = currentCredits + newCredits;
 const oldQualPoints = currentGPA * currentCredits;
 const newGPA = totalCredits > 0 ? (oldQualPoints + newPoints) / totalCredits : 0;

 document.getElementById('new-credits-display').textContent = newCredits;
 document.getElementById('total-credits-display').textContent = totalCredits;

 if (isNaN(newGPA) || totalCredits === 0) {
 document.getElementById('new-gpa-display').textContent = '--.--';
 document.getElementById('result-grade-label').textContent = '--';
 document.getElementById('gpa-bar-indicator').style.left = '0%';
 return;
 }

 const displayGPA = Math.min(Math.max(newGPA, 0), 4).toFixed(2);
 document.getElementById('new-gpa-display').textContent = displayGPA;
 document.getElementById('result-grade-label').textContent = getGradeLabel(parseFloat(displayGPA));

 // Update result card color
 const card = document.getElementById('result-card');
 const gpaVal = parseFloat(displayGPA);
 card.className = 'rounded-2xl p-5 text-center border transition-colors ';
 if (gpaVal >= 3.5) card.className += 'bg-bg border-primary';
 else if (gpaVal >= 3.0) card.className += 'bg-bg border-primary';
 else if (gpaVal >= 2.0) card.className += 'bg-bg border-primary';
 else card.className += 'bg-bg border-primary';

 // GPA bar
 const pct = (gpaVal / 4.0) * 100;
 document.getElementById('gpa-bar-indicator').style.left = Math.min(pct, 98) + '%';
}

// Init: add 4 default rows
window.addEventListener('DOMContentLoaded', () => {
 addRow('', '3', 'B');
 addRow('', '3', 'B+');
 addRow('', '3', 'A-');
 addRow('', '3', 'C+');
});
</script>

<?php require_once 'includes/footer.php'; ?>
