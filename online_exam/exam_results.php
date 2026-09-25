<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../database/db_connection.php';
$pdo = get_pdo();

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

if (!in_array($role, ['student','instructor','admin','super_admin','dean']) || $user_id <= 0) {
 header("Location: ../index.php"); exit;
}

$submission_id = (int)($_GET['submission_id'] ?? 0);
$auto = isset($_GET['auto']);

// Fetch submission + exam
$sub = null; $exam = null; $answers = [];
try {
 $stmt = $pdo->prepare("
 SELECT os.*, oe.title, oe.show_results, oe.pass_score, oe.type, oe.duration_min,
 c.name AS course_name, u.full_name AS student_name
 FROM online_submissions os
 JOIN online_exams oe ON oe.id = os.exam_id
 LEFT JOIN courses c ON c.id = oe.course_id
 LEFT JOIN users u ON u.id = os.student_id
 WHERE os.id = ?
 ");
 $stmt->execute([$submission_id]);
 $sub = $stmt->fetch();
} catch(Exception $e){}

if (!$sub) { echo "<p>نتيجة غير موجودة.</p>"; exit; }

// Access control: only owner or instructor/admin
if ($role === 'student' && (int)$sub['student_id'] !== $user_id) {
 header("Location: student_exams.php"); exit;
}

$exam_id = (int)$sub['exam_id'];

// Fetch answers with question and choice details
try {
 $stmt = $pdo->prepare("
 SELECT oa.*, oq.question_text, oq.type AS qtype, oq.points AS max_pts,
 oc.choice_text AS selected_text
 FROM online_answers oa
 JOIN online_questions oq ON oq.id = oa.question_id
 LEFT JOIN online_choices oc ON oc.id = oa.selected_choice_id
 WHERE oa.submission_id = ?
 ORDER BY oq.order_index
 ");
 $stmt->execute([$submission_id]);
 $answers = $stmt->fetchAll();
} catch(Exception $e){}

// Fetch correct choices for each question
$correct_choices = [];
try {
 foreach ($answers as $a) {
 if ($a['qtype'] !== 'essay') {
 $sc = $pdo->prepare("SELECT choice_text FROM online_choices WHERE question_id=? AND is_correct=true LIMIT 1");
 $sc->execute([$a['question_id']]);
 $correct_choices[$a['question_id']] = $sc->fetchColumn();
 }
 }
} catch(Exception $e){}

$score = (float)($sub['score'] ?? 0);
$max_score = (float)($sub['max_score'] ?? 0);
$percentage = (float)($sub['percentage'] ?? 0);
$pass_score = (float)($sub['pass_score'] ?? 50);
$passed = $percentage >= $pass_score;
$has_essay_pending = $sub['status'] === 'submitted'; // not 'graded' yet

$type_labels = ['quiz'=>'كويز','midterm'=>'اختبار ترمي','assignment'=>'واجب'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نتيجة <?php echo htmlspecialchars($sub['title']); ?> | EDU Nexus</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
body { font-family:'Cairo',sans-serif; background:#f1f5f9; margin:0; color:#1e293b; }
.container { max-width:800px; margin:0 auto; padding:24px 16px; }
.result-hero { border-radius:24px; padding:40px 32px; text-align:center; color:white; margin-bottom:24px; }
.result-hero.passed { background:linear-gradient(135deg,#059669,#10b981); }
.result-hero.failed { background:linear-gradient(135deg,#dc2626,#ef4444); }
.result-hero.pending { background:linear-gradient(135deg,#d97706,#f59e0b); }
.score-ring { width:140px; height:140px; border-radius:50%; border:8px solid rgba(255,255,255,.3); display:flex; align-items:center; justify-content:center; margin:0 auto 20px; flex-direction:column; }
.score-big { font-size:2.2rem; font-weight:800; }
.score-label { font-size:.75rem; opacity:.8; }
.section-card { background:white; border-radius:16px; border:1px solid #e2e8f0; padding:20px; margin-bottom:16px; }
.section-title { font-weight:800; font-size:1rem; color:#1e293b; border-bottom:2px solid #f1f5f9; padding-bottom:12px; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.answer-item { padding:16px; border-radius:12px; margin-bottom:12px; border:2px solid; }
.answer-correct { border-color:#bbf7d0; background:#f0fdf4; }
.answer-wrong { border-color:#fecaca; background:#fef2f2; }
.answer-essay { border-color:#fde68a; background:#fffbeb; }
.answer-pending { border-color:#e2e8f0; background:#f8fafc; }
.q-text { font-weight:700; color:#1e293b; margin-bottom:8px; }
.chosen { display:flex; align-items:center; gap:8px; font-size:.9rem; margin-top:6px; }
.correct-tag { color:#059669; font-weight:700; }
.wrong-tag { color:#dc2626; font-weight:700; }
.correct-ans { color:#059669; font-size:.82rem; margin-top:4px; }
.essay-ans { background:white; border-radius:8px; padding:12px; font-size:.9rem; color:#475569; border:1px solid #e2e8f0; margin-top:6px; white-space:pre-wrap; }
.stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px; }
.stat-box { background:white; border-radius:12px; border:1px solid #e2e8f0; padding:16px; text-align:center; }
.stat-val { font-size:1.4rem; font-weight:800; color:#1e293b; }
.stat-lbl { font-size:.75rem; color:#64748b; margin-top:4px; }
@media(max-width:640px) { .stats-grid{ grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>
<div class="container">

 <!-- Result Hero -->
 <?php if($has_essay_pending): ?>
 <div class="result-hero pending">
 <div class="score-ring">
 <div class="score-big"><?php echo round($percentage,0); ?>%</div>
 <div class="score-label">حتى الآن</div>
 </div>
 <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:8px;">⏳ بانتظار تصحيح المقال</h1>
 <p style="opacity:.9;font-size:.95rem;">تم تسليم إجاباتك. الدرجة الكاملة ستظهر بعد تصحيح أسئلة المقال من قِبل الدكتور.</p>
 </div>
 <?php elseif ($auto): ?>
 <div class="result-hero <?php echo $passed?'passed':'failed'; ?>">
 <div class="score-ring">
 <div class="score-big"><?php echo round($percentage,0); ?>%</div>
 <div class="score-label">النتيجة</div>
 </div>
 <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:8px;">⏰ انتهى وقت الامتحان — تٌسلَّم تلقائياً</h1>
 <p style="opacity:.9">تم تسليم امتحانك أوتوماتيكياً لانتهاء الوقت المحدد.</p>
 </div>
 <?php else: ?>
 <div class="result-hero <?php echo $passed?'passed':'failed'; ?>">
 <div class="score-ring">
 <div class="score-big"><?php echo round($percentage,0); ?>%</div>
 <div class="score-label">النتيجة النهائية</div>
 </div>
 <h1 style="font-size:1.8rem;font-weight:800;margin-bottom:8px;">
 <?php echo $passed ? '🎉 أحسنت! لقد نجحت' : '😔 للأسف لم تنجح'; ?>
 </h1>
 <p style="opacity:.9;font-size:1rem;"><?php echo htmlspecialchars($sub['title']); ?></p>
 <p style="opacity:.75;font-size:.85rem;margin-top:4px;"><?php echo htmlspecialchars($sub['course_name'] ?? ''); ?></p>
 </div>
 <?php endif; ?>

 <!-- Stats -->
 <div class="stats-grid">
 <div class="stat-box">
 <div class="stat-val" style="color:#6366f1;"><?php echo number_format($score,1); ?> / <?php echo number_format($max_score,1); ?></div>
 <div class="stat-lbl">الدرجة</div>
 </div>
 <div class="stat-box">
 <div class="stat-val" style="color:<?php echo $passed?'#059669':'#dc2626'; ?>;"><?php echo round($percentage,1); ?>%</div>
 <div class="stat-lbl">النسبة المئوية</div>
 </div>
 <div class="stat-box">
 <div class="stat-val" style="color:#d97706;"><?php echo $sub['pass_score'] ?? 50; ?>%</div>
 <div class="stat-lbl">درجة النجاح</div>
 </div>
 </div>

 <!-- Answers Review (only if show_results) -->
 <?php if($sub['show_results'] && !empty($answers)): ?>
 <div class="section-card">
 <div class="section-title"><i class="fas fa-list-check" style="color:#6366f1;"></i> مراجعة الإجابات</div>
 <?php foreach($answers as $i => $a): 
 $qtype = $a['qtype'];
 $correct = !is_null($a['is_correct']) && $a['is_correct'];
 $wrong = !is_null($a['is_correct']) && !$a['is_correct'];
 $essay = $qtype === 'essay';
 
 $card_class = $essay ? 'answer-essay' : ($correct ? 'answer-correct' : 'answer-wrong');
 ?>
 <div class="answer-item <?php echo $card_class; ?>">
 <div class="q-text">
 <?php echo ($i+1) . '. ' . nl2br(htmlspecialchars($a['question_text'])); ?>
 <span style="float:left;font-size:.75rem;color:#94a3b8;">(<?php echo $a['points_earned']; ?> / <?php echo $a['max_pts']; ?> نقطة)</span>
 </div>

 <?php if ($essay): ?>
 <div class="chosen" style="color:#92400e;"><i class="fas fa-pen"></i> إجابتك:</div>
 <div class="essay-ans"><?php echo !empty($a['essay_answer']) ? htmlspecialchars($a['essay_answer']) : '<em style="color:#94a3b8;">لم تكتب إجابة</em>'; ?></div>
 <?php if(!is_null($a['points_earned']) && $a['points_earned'] > 0): ?>
 <div class="correct-ans"><i class="fas fa-star"></i> الدرجة المنحوحة: <?php echo $a['points_earned']; ?> نقطة <?php echo $a['instructor_note'] ? '— ' . htmlspecialchars($a['instructor_note']) : ''; ?></div>
 <?php else: ?>
 <div class="correct-ans" style="color:#d97706;">⏳ بانتظار التصحيح</div>
 <?php endif; ?>
 <?php else: ?>
 <?php if(!empty($a['selected_text'])): ?>
 <div class="chosen">
 <?php if($correct): ?>
 <i class="fas fa-check-circle" style="color:#059669;"></i>
 <span class="correct-tag">إجابتك صحيحة: <?php echo htmlspecialchars($a['selected_text']); ?></span>
 <?php else: ?>
 <i class="fas fa-times-circle" style="color:#dc2626;"></i>
 <span class="wrong-tag">إجابتك خاطئة: <?php echo htmlspecialchars($a['selected_text']); ?></span>
 <?php endif; ?>
 </div>
 <?php if($wrong && isset($correct_choices[$a['question_id']])): ?>
 <div class="correct-ans"><i class="fas fa-lightbulb"></i> الإجابة الصحيحة: <?php echo htmlspecialchars($correct_choices[$a['question_id']]); ?></div>
 <?php endif; ?>
 <?php else: ?>
 <div class="chosen" style="color:#94a3b8;"><i class="fas fa-minus-circle"></i> لم تختر إجابة</div>
 <?php endif; ?>
 <?php endif; ?>
 </div>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>

 <!-- Back Button -->
 <div style="text-align:center;margin-top:24px;">
 <a href="student_exams.php" style="display:inline-flex;align-items:center;gap:8px;background:#6366f1;color:white;padding:12px 28px;border-radius:12px;font-weight:800;text-decoration:none;">
 <i class="fas fa-arrow-right"></i> العودة للامتحانات
 </a>
 </div>

</div>
</body>
</html>
