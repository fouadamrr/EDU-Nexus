<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../database/db_connection.php';
$pdo = get_pdo();

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';
$college_id = (int)($_SESSION['college_id'] ?? 0);

if ($role !== 'student' || $user_id <= 0) { header("Location: ../index.php"); exit; }

// Schema creation handled manually.

// ── Fetch student's enrolled courses ───────────────────────────────────────
$course_ids = [];
try {
 $stmt = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id=? AND status IN ('active','completed')");
 $stmt->execute([$user_id]);
 $course_ids = array_column($stmt->fetchAll(), 'course_id');
} catch(Exception $e){}

// ── Fetch available exams ──────────────────────────────────────────────────
$available_exams = [];
try {
 if (!empty($course_ids)) {
 // Primary: exams for enrolled courses
 $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
 $stmt = $pdo->prepare("
 SELECT oe.*, c.name AS course_name,
 (SELECT id FROM online_submissions WHERE exam_id=oe.id AND student_id=?) AS my_submission_id,
 (SELECT status FROM online_submissions WHERE exam_id=oe.id AND student_id=?) AS my_status
 FROM online_exams oe
 LEFT JOIN courses c ON c.id=oe.course_id
 WHERE oe.status='active'
 AND oe.course_id IN ($placeholders)
 AND (oe.end_time IS NULL OR oe.end_time > NOW())
 ORDER BY oe.start_time ASC
 ");
 $stmt->execute(array_merge([$user_id, $user_id], $course_ids));
 $available_exams = $stmt->fetchAll();
 }

 // Fallback: if no enrollments found, show exams for same college
 if (empty($available_exams) && $college_id > 0) {
 $stmt = $pdo->prepare("
 SELECT oe.*, c.name AS course_name,
 (SELECT id FROM online_submissions WHERE exam_id=oe.id AND student_id=?) AS my_submission_id,
 (SELECT status FROM online_submissions WHERE exam_id=oe.id AND student_id=?) AS my_status
 FROM online_exams oe
 LEFT JOIN courses c ON c.id=oe.course_id
 WHERE oe.status='active'
 AND oe.college_id = ?
 AND (oe.end_time IS NULL OR oe.end_time > NOW())
 ORDER BY oe.start_time ASC
 ");
 $stmt->execute([$user_id, $user_id, $college_id]);
 $available_exams = $stmt->fetchAll();
 }

 // Last resort: show ALL active exams (if college_id not set either)
 if (empty($available_exams) && $college_id <= 0) {
 $stmt = $pdo->prepare("
 SELECT oe.*, c.name AS course_name,
 (SELECT id FROM online_submissions WHERE exam_id=oe.id AND student_id=?) AS my_submission_id,
 (SELECT status FROM online_submissions WHERE exam_id=oe.id AND student_id=?) AS my_status
 FROM online_exams oe
 LEFT JOIN courses c ON c.id=oe.course_id
 WHERE oe.status='active'
 AND (oe.end_time IS NULL OR oe.end_time > NOW())
 ORDER BY oe.start_time ASC
 ");
 $stmt->execute([$user_id, $user_id]);
 $available_exams = $stmt->fetchAll();
 }
} catch(Exception $e){}

// ── Debug info (shown only when no exams found) ────────────────────────────
$debug_info = '';
if (empty($available_exams)) {
 $active_count = 0;
 try {
 $r = $pdo->query("SELECT COUNT(*) FROM online_exams WHERE status='active'")->fetchColumn();
 $active_count = (int)$r;
 } catch(Exception $e){}
 $debug_info = $active_count;
}

// ── Past exams ─────────────────────────────────────────────────────────────
$past_exams = [];
try {
 $stmt = $pdo->prepare("
 SELECT os.*, oe.title, oe.type, oe.pass_score, oe.show_results,
 c.name AS course_name
 FROM online_submissions os
 JOIN online_exams oe ON oe.id=os.exam_id
 LEFT JOIN courses c ON c.id=oe.course_id
 WHERE os.student_id=? AND os.status IN ('submitted','graded')
 ORDER BY os.submitted_at DESC
 ");
 $stmt->execute([$user_id]);
 $past_exams = $stmt->fetchAll();
} catch(Exception $e){}

$type_labels = ['quiz'=>'كويز','midterm'=>'اختبار ترمي','assignment'=>'واجب'];
$type_colors = ['quiz'=>'#6366f1','midterm'=>'#0891b2','assignment'=>'#d97706'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>امتحاناتي | EDU Nexus</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
body { font-family:'Cairo',sans-serif; background:#f1f5f9; margin:0; color:#1e293b; }
.container { max-width:860px; margin:0 auto; padding:24px 16px; }
.top-bar { background:linear-gradient(135deg,#6366f1,#4f46e5); color:white; padding:24px; border-radius:20px; margin-bottom:24px; }
.section-title { font-weight:800; font-size:1.05rem; color:#1e293b; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.exam-card { background:white; border:2px solid #e2e8f0; border-radius:16px; padding:20px; margin-bottom:14px; transition:.2s; }
.exam-card:hover { border-color:#a5b4fc; box-shadow:0 4px 16px rgba(99,102,241,.1); }
.type-tag { display:inline-block; padding:3px 10px; border-radius:6px; color:white; font-size:.7rem; font-weight:700; }
.start-btn { background:#6366f1; color:white; padding:10px 24px; border-radius:10px; font-weight:800; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:.2s; border:none; font-family:Cairo,sans-serif; cursor:pointer; font-size:.9rem; }
.start-btn:hover { background:#4f46e5; }
.already-done { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:8px 16px; border-radius:10px; font-weight:700; font-size:.85rem; display:inline-flex; align-items:center; gap:6px; }
.score-badge { padding:4px 12px; border-radius:999px; font-weight:800; font-size:.85rem; }
.b-pass { background:#dcfce7; color:#166534; }
.b-fail { background:#fee2e2; color:#991b1b; }
.b-pending { background:#fef3c7; color:#92400e; }
.info-row { display:flex; flex-wrap:wrap; gap:16px; margin-top:10px; }
.info-chip { display:flex; align-items:center; gap:6px; font-size:.82rem; color:#64748b; }
</style>
</head>
<body>
<div class="container">

 <!-- Header -->
 <div class="top-bar">
 <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:4px;"><i class="fas fa-file-alt me-2"></i> امتحاناتي الإلكترونية</h1>
 <p style="opacity:.85;font-size:.9rem;">مرحباً <?php echo htmlspecialchars(explode(' ',$full_name)[0]); ?>! هنا ستجد الامتحانات المتاحة لك وسجل إجاباتك السابقة.</p>
 </div>

 <!-- Available Exams -->
 <div class="section-title"><i class="fas fa-clock" style="color:#6366f1;"></i> امتحانات متاحة الآن (<?php echo count($available_exams); ?>)</div>

 <?php if(empty($available_exams)): ?>
 <div style="background:white;border:1px dashed #cbd5e1;border-radius:16px;padding:32px;text-align:center;color:#94a3b8;margin-bottom:28px;">
 <i class="fas fa-hourglass-half" style="font-size:2rem;margin-bottom:12px;display:block;"></i>
 <p style="font-weight:700;color:#64748b;">لا توجد امتحانات متاحة حالياً.</p>
 <?php if ($debug_info !== '' && (int)$debug_info === 0): ?>
 <p style="font-size:.82rem;color:#94a3b8;margin-top:8px;">
 <i class="fas fa-info-circle"></i>
 لا يوجد أي امتحان بحالة "مفعّل" في النظام بعد — انتظر الأستاذ ليفعّل الامتحان.
 </p>
 <?php elseif($debug_info !== '' && (int)$debug_info > 0): ?>
 <p style="font-size:.82rem;color:#f59e0b;margin-top:8px;font-weight:700;">
 <i class="fas fa-exclamation-triangle"></i>
 يوجد <?php echo $debug_info; ?> امتحان مفعّل لكنه غير مرتبط بك —
 قد يكون السبب: لست مسجّل في المقرر، أو الامتحان لكلية مختلفة.
 </p>
 <?php endif; ?>
 </div>

 <?php else: ?>
 <?php foreach($available_exams as $e): 
 $already_submitted = !is_null($e['my_status']) && $e['my_status'] !== 'in_progress';
 $in_progress = $e['my_status'] === 'in_progress';
 ?>
 <div class="exam-card">
 <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
 <div style="flex:1;">
 <div style="margin-bottom:8px;">
 <span class="type-tag" style="background:<?php echo $type_colors[$e['type']] ?? '#6366f1'; ?>"><?php echo $type_labels[$e['type']] ?? $e['type']; ?></span>
 </div>
 <h3 style="font-weight:800;font-size:1.05rem;margin-bottom:4px;"><?php echo htmlspecialchars($e['title']); ?></h3>
 <p style="font-size:.85rem;color:#64748b;"><?php echo htmlspecialchars($e['course_name'] ?? ''); ?></p>
 <div class="info-row">
 <span class="info-chip"><i class="fas fa-clock"></i> <?php echo $e['duration_min']; ?> دقيقة</span>
 <?php if($e['end_time']): ?><span class="info-chip"><i class="fas fa-calendar-times"></i> ينتهي: <?php echo date('d/m/Y H:i',strtotime($e['end_time'])); ?></span><?php endif; ?>
 </div>
 <?php if($e['description']): ?>
 <p style="font-size:.82rem;color:#64748b;margin-top:8px;font-style:italic;"><?php echo htmlspecialchars($e['description']); ?></p>
 <?php endif; ?>
 </div>
 <div>
 <?php if($already_submitted): ?>
 <span class="already-done"><i class="fas fa-check-double"></i> سبق وسلّمته</span>
 <?php elseif($in_progress): ?>
 <a href="take_exam.php?exam_id=<?php echo $e['id']; ?>" class="start-btn" style="background:#d97706;">
 <i class="fas fa-redo"></i> متابعة الامتحان
 </a>
 <?php else: ?>
 <a href="take_exam.php?exam_id=<?php echo $e['id']; ?>" class="start-btn">
 <i class="fas fa-play-circle"></i> ابدأ الامتحان
 </a>
 <?php endif; ?>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>

 <!-- Past Exams -->
 <?php if(!empty($past_exams)): ?>
 <div class="section-title" style="margin-top:24px;"><i class="fas fa-history" style="color:#059669;"></i> امتحانات سابقة (<?php echo count($past_exams); ?>)</div>
 <?php foreach($past_exams as $pe): 
 $pct = (float)($pe['percentage'] ?? 0);
 $pass_score = (float)($pe['pass_score'] ?? 50);
 $passed = $pct >= $pass_score;
 $pending = $pe['status'] === 'submitted';
 ?>
 <div class="exam-card" style="border-color:#f1f5f9;">
 <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
 <div>
 <span class="type-tag" style="background:<?php echo $type_colors[$pe['type']] ?? '#6366f1'; ?>;opacity:.7;"><?php echo $type_labels[$pe['type']] ?? $pe['type']; ?></span>
 <h3 style="font-weight:700;font-size:.95rem;margin:6px 0 2px;"><?php echo htmlspecialchars($pe['title']); ?></h3>
 <p style="font-size:.8rem;color:#64748b;"><?php echo htmlspecialchars($pe['course_name'] ?? ''); ?></p>
 <div style="font-size:.8rem;color:#94a3b8;margin-top:4px;"><i class="fas fa-calendar-check"></i> <?php echo $pe['submitted_at'] ? date('d/m/Y H:i',strtotime($pe['submitted_at'])) : '—'; ?></div>
 </div>
 <div style="text-align:center;">
 <?php if($pending): ?>
 <span class="score-badge b-pending">⏳ انتظار تصحيح المقال</span>
 <?php else: ?>
 <div style="font-size:1.8rem;font-weight:800;color:<?php echo $passed?'#059669':'#dc2626'; ?>;"><?php echo round($pct); ?>%</div>
 <span class="score-badge <?php echo $passed?'b-pass':'b-fail'; ?>"><?php echo $passed?'✓ ناجح':'✗ راسب'; ?></span>
 <?php endif; ?>
 <?php if($pe['show_results']): ?>
 <div style="margin-top:8px;">
 <a href="exam_results.php?submission_id=<?php echo $pe['id']; ?>" style="color:#6366f1;font-size:.82rem;font-weight:700;text-decoration:none;">
 <i class="fas fa-eye"></i> مراجعة الإجابات
 </a>
 </div>
 <?php endif; ?>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>

 <div style="text-align:center;margin-top:24px;">
 <a href="../dashboard.php" style="color:#6366f1;font-weight:700;text-decoration:none;font-size:.9rem;">
 <i class="fas fa-home"></i> الرئيسية
 </a>
 </div>
</div>
</body>
</html>
