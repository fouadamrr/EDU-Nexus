<?php
require_once '../includes/header.php';

if (!in_array($role, ['instructor','admin','super_admin','dean'])) {
 echo "<script>window.location.href='../index.php';</script>"; exit;
}

$exam_id = (int)($_GET['exam_id'] ?? 0);

// Fetch exam
$exam = null;
try {
 $stmt = $pdo->prepare("SELECT oe.*, c.name AS course_name FROM online_exams oe LEFT JOIN courses c ON c.id=oe.course_id WHERE oe.id=?");
 $stmt->execute([$exam_id]);
 $exam = $stmt->fetch();
} catch(Exception $e){}

if (!$exam) { echo "<p>امتحان غير موجود.</p>"; exit; }

// Fetch all submissions with scores
$submissions = [];
try {
 $stmt = $pdo->prepare("
 SELECT os.*, u.full_name, u.username,
 COALESCE((SELECT COUNT(*) FROM exam_logs WHERE exam_id=os.exam_id AND student_id=os.student_id AND action IN ('tab_switch','copy_attempt','page_refresh')),0) AS cheat_events
 FROM online_submissions os
 JOIN users u ON u.id=os.student_id
 WHERE os.exam_id=?
 ORDER BY os.percentage DESC NULLS LAST, u.full_name
 ");
 $stmt->execute([$exam_id]);
 $submissions = $stmt->fetchAll();
} catch(Exception $e){}

// Stats
$total_subs = count($submissions);
$graded = array_filter($submissions, fn($s) => $s['status'] === 'graded');
$avg_pct = $total_subs > 0 ? round(array_sum(array_column($submissions,'percentage')) / $total_subs, 1) : 0;
$pass_score = (float)($exam['pass_score'] ?? 50);
$passed = count(array_filter($submissions, fn($s) => (float)$s['percentage'] >= $pass_score));
?>

<style>
.stats-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
.stat-card { background:white; border-radius:14px; border:1px solid #e2e8f0; padding:16px; text-align:center; }
.stat-val { font-size:1.6rem; font-weight:800; }
.stat-lbl { font-size:.75rem; color:#64748b; margin-top:4px; }
table { width:100%; border-collapse:collapse; }
thead tr { background:#f8fafc; }
th { padding:12px 14px; color:#64748b; font-size:.8rem; font-weight:700; text-align:right; border-bottom:2px solid #e2e8f0; }
td { padding:12px 14px; border-bottom:1px solid #f1f5f9; font-size:.9rem; vertical-align:middle; }
tr:hover td { background:#fafafa; }
.pct-bar { height:6px; background:#e2e8f0; border-radius:999px; overflow:hidden; width:80px; display:inline-block; }
.pct-fill { height:100%; border-radius:999px; }
.badge { padding:3px 10px; border-radius:999px; font-size:.72rem; font-weight:700; }
.b-graded { background:#dcfce7; color:#166534; }
.b-submitted{ background:#fef3c7; color:#92400e; }
.b-progress { background:#e0e7ff; color:#3730a3; }
.b-passed { background:#dcfce7; color:#166534; }
.b-failed { background:#fee2e2; color:#991b1b; }
.cheat-warn { background:#fef2f2; color:#dc2626; padding:2px 8px; border-radius:6px; font-size:.75rem; font-weight:700; }
@media(max-width:640px) { .stats-bar{grid-template-columns:1fr 1fr;} }
</style>

<div class="max-w-6xl mx-auto pb-10">
 <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm mb-6 flex items-center gap-4 flex-wrap">
 <div class="w-12 h-12 rounded-xl bg-primary flex items-center justify-center shadow-sm shadow-md">
 <i class="fas fa-chart-bar text-white text-xl"></i>
 </div>
 <div class="flex-1">
 <h1 class="text-xl font-bold text-slate-800">نتائج الامتحان</h1>
 <p class="text-sm text-slate-500"><?php echo htmlspecialchars($exam['title']); ?> — <?php echo htmlspecialchars($exam['course_name'] ?? ''); ?></p>
 </div>
 <div class="flex gap-2">
 <a href="grade_essay.php?exam_id=<?php echo $exam_id; ?>" class="bg-bg border border-primary text-primary px-4 py-2 rounded-xl font-bold hover:bg-primary transition text-sm flex items-center gap-2">
 <i class="fas fa-pen"></i> تصحيح مقال
 </a>
 <a href="manage_exam.php" class="bg-bg text-slate-600 px-4 py-2 rounded-xl font-bold hover:bg-slate-200 transition text-sm flex items-center gap-2">
 <i class="fas fa-arrow-right"></i> رجوع
 </a>
 </div>
 </div>

 <!-- Stats -->
 <div class="stats-bar">
 <div class="stat-card">
 <div class="stat-val" style="color:#6366f1;"><?php echo $total_subs; ?></div>
 <div class="stat-lbl">إجمالي التسليمات</div>
 </div>
 <div class="stat-card">
 <div class="stat-val" style="color:#059669;"><?php echo $passed; ?></div>
 <div class="stat-lbl">ناجح</div>
 </div>
 <div class="stat-card">
 <div class="stat-val" style="color:#dc2626;"><?php echo $total_subs - $passed; ?></div>
 <div class="stat-lbl">راسب</div>
 </div>
 <div class="stat-card">
 <div class="stat-val" style="color:#d97706;"><?php echo $avg_pct; ?>%</div>
 <div class="stat-lbl">المتوسط العام</div>
 </div>
 </div>

 <!-- Submissions Table -->
 <?php if(empty($submissions)): ?>
 <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-12 text-center text-slate-400">
 <i class="fas fa-inbox text-4xl mb-3 block"></i>
 <p>لم يقدم أي طالب هذا الامتحان بعد.</p>
 </div>
 <?php else: ?>
 <div class="bg-white border border-slate-100 shadow-sm rounded-2xl overflow-hidden">
 <div class="overflow-x-auto">
 <table>
 <thead>
 <tr>
 <th>#</th>
 <th>الطالب</th>
 <th>الدرجة</th>
 <th>النسبة</th>
 <th>الحالة</th>
 <th>النتيجة</th>
 <th>وقت التسليم</th>
 <th>أنشطة مشبوهة</th>
 <th>تفاصيل</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach($submissions as $i => $s): 
 $pct = (float)($s['percentage'] ?? 0);
 $passed_row = $pct >= $pass_score;
 $pct_color = $pct >= 75 ? '#059669' : ($pct >= $pass_score ? '#d97706' : '#dc2626');
 $status_map = ['graded'=>'مصحح','submitted'=>'تسليم','in_progress'=>'جارٍ'];
 $status_badge = ['graded'=>'b-graded','submitted'=>'b-submitted','in_progress'=>'b-progress'][$s['status']] ?? 'b-progress';
 ?>
 <tr>
 <td style="color:#94a3b8;"><?php echo $i+1; ?></td>
 <td>
 <div style="font-weight:700;"><?php echo htmlspecialchars($s['full_name']); ?></div>
 <div style="font-size:.75rem;color:#94a3b8;font-family:monospace;"><?php echo htmlspecialchars($s['username']); ?></div>
 </td>
 <td style="font-weight:800;"><?php echo number_format((float)$s['score'],1); ?> / <?php echo number_format((float)$s['max_score'],1); ?></td>
 <td>
 <div style="display:flex;align-items:center;gap:8px;">
 <div class="pct-bar"><div class="pct-fill" style="width:<?php echo min(100,$pct); ?>%;background:<?php echo $pct_color; ?>;"></div></div>
 <strong style="color:<?php echo $pct_color; ?>;"><?php echo number_format($pct,1); ?>%</strong>
 </div>
 </td>
 <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_map[$s['status']] ?? $s['status']; ?></span></td>
 <td><span class="badge <?php echo $passed_row?'b-passed':'b-failed'; ?>"><?php echo $passed_row?'✓ ناجح':'✗ راسب'; ?></span></td>
 <td style="font-size:.8rem;color:#64748b;">
 <?php echo $s['submitted_at'] ? date('d/m H:i', strtotime($s['submitted_at'])) : '—'; ?>
 </td>
 <td>
 <?php if((int)$s['cheat_events'] > 0): ?>
 <span class="cheat-warn"><i class="fas fa-exclamation-triangle"></i> <?php echo $s['cheat_events']; ?></span>
 <?php else: ?>
 <span style="color:#10b981;font-size:.8rem;">نظيف</span>
 <?php endif; ?>
 </td>
 <td>
 <a href="exam_results.php?submission_id=<?php echo $s['id']; ?>" style="color:#6366f1;font-weight:700;font-size:.82rem;text-decoration:none;hover:underline;">
 <i class="fas fa-eye"></i> عرض
 </a>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
