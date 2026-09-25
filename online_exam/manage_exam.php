<?php
require_once '../includes/header.php';

if ($role !== 'instructor' && $role !== 'admin' && $role !== 'super_admin' && $role !== 'dean') {
 echo "<script>window.location.href='../index.php';</script>"; exit;
}

// Backend permission guard — instructors need 'absence' perm (attendance module tied to teaching)
// All roles here are assumed to have the right; super_admin / admin always have all permissions.
require_permission('exams');

// Auto-create tables if needed
try { $pdo->exec(file_get_contents(__DIR__ . '/../database/online_exam_schema.sql')); } catch(Exception $e){}

$instructor_id = $user_id;
$message = '';

// Handle status toggle / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $action = $_POST['action'] ?? '';
 $exam_id = (int)($_POST['exam_id'] ?? 0);

 if ($action === 'toggle_status' && $exam_id > 0) {
 try {
 $cur = $pdo->prepare("SELECT status FROM online_exams WHERE id=?");
 $cur->execute([$exam_id]);
 $cur_status = $cur->fetchColumn();
 $new_status = ($cur_status === 'active') ? 'inactive' : 'active';
 $pdo->prepare("UPDATE online_exams SET status=? WHERE id=?")->execute([$new_status, $exam_id]);
 $message = '<div class="alert-msg success">تم تغيير حالة الامتحان إلى: <strong>' . ($new_status==='active'?'مفعّل':'غير مفعّل') . '</strong></div>';
 } catch(Exception $e){}
 }

 if ($action === 'delete_exam' && $exam_id > 0) {
 try {
 $pdo->prepare("DELETE FROM online_exams WHERE id=?")->execute([$exam_id]);
 $message = '<div class="alert-msg success">تم حذف الامتحان بنجاح.</div>';
 } catch(Exception $e){
 $message = '<div class="alert-msg error">لا يمكن الحذف: ' . htmlspecialchars($e->getMessage()) . '</div>';
 }
 }
}

// Fetch exams
$exams = [];
try {
 if (in_array($role, ['admin','super_admin'])) {
 $stmt = $pdo->query("
 SELECT oe.*, c.name AS course_name,
 u.full_name AS instructor_name,
 (SELECT COUNT(*) FROM online_questions WHERE exam_id=oe.id) AS q_count,
 (SELECT COUNT(*) FROM online_submissions WHERE exam_id=oe.id AND status='submitted') AS sub_count
 FROM online_exams oe
 LEFT JOIN courses c ON c.id=oe.course_id
 LEFT JOIN users u ON u.id=oe.created_by
 ORDER BY oe.created_at DESC
 ");
 } else {
 $stmt = $pdo->prepare("
 SELECT oe.*, c.name AS course_name,
 u.full_name AS instructor_name,
 (SELECT COUNT(*) FROM online_questions WHERE exam_id=oe.id) AS q_count,
 (SELECT COUNT(*) FROM online_submissions WHERE exam_id=oe.id AND status='submitted') AS sub_count
 FROM online_exams oe
 LEFT JOIN courses c ON c.id=oe.course_id
 LEFT JOIN users u ON u.id=oe.created_by
 WHERE oe.created_by = ?
 ORDER BY oe.created_at DESC
 ");
 $stmt->execute([$instructor_id]);
 }
 $exams = $stmt->fetchAll();
} catch(Exception $e){}

$type_labels = ['quiz'=>'کویز','midterm'=>'اختبار ترمي','assignment'=>'واجب'];
$type_colors = ['quiz'=>'#6366f1','midterm'=>'#0891b2','assignment'=>'#d97706'];
?>

<style>
.alert-msg { padding:12px 18px; border-radius:10px; font-weight:bold; margin-bottom:16px; }
.alert-msg.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.alert-msg.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.exam-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; transition:.2s; }
.exam-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); border-color:#c7d2fe; }
.status-badge { padding:4px 12px; border-radius:999px; font-size:.75rem; font-weight:700; }
.status-active { background:#dcfce7; color:#166534; }
.status-inactive { background:#f1f5f9; color:#64748b; }
.status-closed { background:#fee2e2; color:#991b1b; }
.type-tag { display:inline-block; padding:3px 10px; border-radius:6px; color:white; font-size:.7rem; font-weight:700; }
.stat-pill { display:flex; align-items:center; gap:5px; color:#64748b; font-size:.8rem; }
</style>

<div class="max-w-6xl mx-auto pb-10">
 <!-- Header -->
 <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm mb-6 flex items-center justify-between flex-wrap gap-4">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 rounded-xl bg-primary flex items-center justify-center shadow-sm shadow-md">
 <i class="fas fa-file-alt text-white text-xl"></i>
 </div>
 <div>
 <h1 class="text-xl font-bold text-slate-800">الامتحانات الإلكترونية</h1>
 <p class="text-sm text-slate-500"><?php echo count($exams); ?> امتحان منشأ</p>
 </div>
 </div>
 <div class="flex gap-2 flex-wrap">
 <a href="create_question.php" class="bg-bg text-slate-700 px-4 py-2.5 rounded-xl font-bold hover:bg-slate-200 transition flex items-center gap-2 text-sm">
 <i class="fas fa-question-circle"></i> إدارة الأسئلة
 </a>
 <a href="create_exam.php" class="bg-primary text-white px-5 py-2.5 rounded-xl font-bold hover:bg-primary transition shadow-md shadow-sm flex items-center gap-2">
 <i class="fas fa-plus"></i> امتحان جديد
 </a>
 </div>
 </div>

 <?php echo $message; ?>

 <?php if(empty($exams)): ?>
 <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-16 text-center text-slate-400">
 <i class="fas fa-file-circle-plus text-5xl mb-4 block opacity-40"></i>
 <p class="font-bold text-lg mb-2">لا توجد امتحانات بعد</p>
 <p class="text-sm mb-6">ابدأ بإنشاء أول امتحان إلكتروني لطلابك</p>
 <a href="create_exam.php" class="bg-primary text-white px-6 py-2.5 rounded-xl font-bold hover:bg-primary transition inline-flex items-center gap-2">
 <i class="fas fa-plus"></i> إنشاء امتحان
 </a>
 </div>
 <?php else: ?>
 <div class="space-y-4">
 <?php foreach($exams as $exam): 
 $status_class = 'status-' . ($exam['status'] ?? 'inactive');
 $status_label = ['active'=>'✅ مفعّل','inactive'=>'⏸ غير مفعّل','closed'=>'🔒 مغلق'][$exam['status']] ?? $exam['status'];
 ?>
 <div class="exam-card">
 <div class="p-5 flex items-start gap-4 flex-wrap">
 <div class="flex-1 min-w-0">
 <div class="flex items-center gap-3 flex-wrap mb-2">
 <span class="type-tag" style="background:<?php echo $type_colors[$exam['type']] ?? '#6366f1'; ?>">
 <?php echo $type_labels[$exam['type']] ?? $exam['type']; ?>
 </span>
 <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
 </div>
 <h3 class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($exam['title']); ?></h3>
 <p class="text-sm text-slate-500 mt-0.5"><?php echo htmlspecialchars($exam['course_name'] ?? 'مقرر غير محدد'); ?></p>
 
 <div class="flex flex-wrap gap-4 mt-3">
 <span class="stat-pill"><i class="fas fa-question-circle"></i> <?php echo $exam['q_count']; ?> سؤال</span>
 <span class="stat-pill"><i class="fas fa-users"></i> <?php echo $exam['sub_count']; ?> تسليم</span>
 <span class="stat-pill"><i class="fas fa-clock"></i> <?php echo $exam['duration_min']; ?> دقيقة</span>
 <?php if($exam['start_time']): ?>
 <span class="stat-pill"><i class="fas fa-calendar"></i> يبدأ: <?php echo date('d/m/Y H:i', strtotime($exam['start_time'])); ?></span>
 <?php endif; ?>
 <?php if($exam['end_time'] && $exam['end_time'] < date('Y-m-d H:i:s')): ?>
 <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:700;">
 <i class="fas fa-exclamation-triangle"></i> وقت النهاية انتهى! الطلاب لا يرون هذا الامتحان
 </span>
 <?php endif; ?>
 </div>
 </div>
 <div class="flex items-center gap-2 flex-wrap">
 <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="px-3 py-2 bg-bg border border-primary text-white rounded-lg text-sm font-bold hover:bg-sky-500 transition flex items-center gap-1.5">
 <i class="fas fa-edit"></i> تعديل
 </a>
 <a href="add_questions.php?exam_id=<?php echo $exam['id']; ?>" class="px-3 py-2 bg-bg border border-slate-200 text-slate-600 rounded-lg text-sm font-bold hover:bg-bg transition flex items-center gap-1.5">
 <i class="fas fa-question"></i> الأسئلة
 </a>
 <a href="view_results.php?exam_id=<?php echo $exam['id']; ?>" class="px-3 py-2 bg-bg border border-primary text-white rounded-lg text-sm font-bold hover:bg-sky-500 transition flex items-center gap-1.5">
 <i class="fas fa-chart-bar"></i> النتائج
 </a>
 <a href="grade_essay.php?exam_id=<?php echo $exam['id']; ?>" class="px-3 py-2 bg-bg border border-primary text-primary rounded-lg text-sm font-bold hover:bg-primary transition flex items-center gap-1.5">
 <i class="fas fa-pen"></i> تصحيح مقال
 </a>
 <form method="POST" class="inline">
 <input type="hidden" name="action" value="toggle_status">
 <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
 <button type="submit" class="px-3 py-2 bg-bg border border-primary text-primary rounded-lg text-sm font-bold hover:bg-primary transition flex items-center gap-1.5">
 <?php echo $exam['status']==='active' ? '<i class="fas fa-pause"></i> إيقاف' : '<i class="fas fa-play"></i> تفعيل'; ?>
 </button>
 </form>
 <form method="POST" onsubmit="return confirm('حذف الامتحان نهائياً مع كل بياناته؟')" class="inline">
 <input type="hidden" name="action" value="delete_exam">
 <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
 <button type="submit" class="px-3 py-2 bg-bg border border-primary text-primary rounded-lg text-sm font-bold hover:bg-primary transition flex items-center gap-1.5">
 <i class="fas fa-trash"></i>
 </button>
 </form>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
