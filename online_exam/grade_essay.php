<?php
require_once '../includes/header.php';

if (!in_array($role, ['instructor','admin','super_admin','dean'])) {
 echo "<script>window.location.href='../index.php';</script>"; exit;
}

$exam_id = (int)($_GET['exam_id'] ?? 0);
$instructor_id = $user_id;
$message = '';

// Fetch exam
$exam = null;
try {
 $stmt = $pdo->prepare("SELECT oe.*, c.name AS course_name FROM online_exams oe LEFT JOIN courses c ON c.id=oe.course_id WHERE oe.id=?");
 $stmt->execute([$exam_id]);
 $exam = $stmt->fetch();
} catch(Exception $e){}

if (!$exam) { echo "<p>امتحان غير موجود.</p>"; exit; }
if ($role === 'instructor' && (int)$exam['created_by'] !== $instructor_id) {
 echo "<p>غير مصرح لك بالوصول.</p>"; exit;
}

// Handle manual grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'grade_essay') {
 $answer_id = (int)($_POST['answer_id'] ?? 0);
 $points_given = min((float)($_POST['max_pts'] ?? 0), max(0, (float)($_POST['points_given'] ?? 0)));
 $note = trim($_POST['instructor_note'] ?? '');

 try {
 $pdo->prepare("UPDATE online_answers SET points_earned=?, instructor_note=? WHERE id=?")
 ->execute([$points_given, $note ?: null, $answer_id]);

 // Recalculate total score for the submission
 $stmt = $pdo->prepare("SELECT submission_id FROM online_answers WHERE id=?");
 $stmt->execute([$answer_id]);
 $sub_id = $stmt->fetchColumn();

 if ($sub_id) {
 $stmt = $pdo->prepare("SELECT SUM(points_earned) AS total, SUM(oq.points) AS max_total
 FROM online_answers oa
 JOIN online_questions oq ON oq.id=oa.question_id
 WHERE oa.submission_id=?");
 $stmt->execute([$sub_id]);
 $totals = $stmt->fetch();
 $new_score = (float)($totals['total'] ?? 0);
 $new_max = (float)($totals['max_total'] ?? 0);
 $new_pct = $new_max > 0 ? round(($new_score / $new_max) * 100, 2) : 0;

 // Check if all essays graded
 $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM online_answers oa JOIN online_questions oq ON oq.id=oa.question_id WHERE oa.submission_id=? AND oq.type='essay' AND oa.points_earned IS NULL");
 $stmt2->execute([$sub_id]);
 $pending = (int)$stmt2->fetchColumn();
 $new_status = $pending === 0 ? 'graded' : 'submitted';

 $pdo->prepare("UPDATE online_submissions SET score=?, max_score=?, percentage=?, status=? WHERE id=?")
 ->execute([$new_score, $new_max, $new_pct, $new_status, $sub_id]);
 }

 $message = '<div class="alert-msg success">✅ تم حفظ الدرجة.</div>';
 } catch(Exception $e) {
 $message = '<div class="alert-msg error">خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
 }
}

// Fetch all essay answers for this exam
$essay_answers = [];
try {
 $stmt = $pdo->prepare("
 SELECT oa.id AS answer_id, oa.submission_id, oa.essay_answer, oa.points_earned, oa.instructor_note,
 oq.question_text, oq.points AS max_pts, oq.id AS question_id,
 u.full_name AS student_name, u.username
 FROM online_answers oa
 JOIN online_questions oq ON oq.id=oa.question_id
 JOIN online_submissions os ON os.id=oa.submission_id
 JOIN users u ON u.id=os.student_id
 WHERE oq.exam_id=? AND oq.type='essay'
 ORDER BY u.full_name, oq.order_index
 ");
 $stmt->execute([$exam_id]);
 $essay_answers = $stmt->fetchAll();
} catch(Exception $e){}

$pending_count = count(array_filter($essay_answers, fn($a) => is_null($a['points_earned'])));
?>

<style>
.alert-msg { padding:12px 18px; border-radius:10px; font-weight:bold; margin-bottom:16px; }
.alert-msg.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.alert-msg.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.essay-card { background:white; border:2px solid #e2e8f0; border-radius:16px; padding:20px; margin-bottom:16px; }
.essay-card.graded { border-color:#bbf7d0; }
.essay-card.pending { border-color:#fde68a; }
.student-badge { display:inline-flex; align-items:center; gap:6px; background:#eff6ff; color:#1d4ed8; padding:4px 12px; border-radius:999px; font-size:.8rem; font-weight:700; }
.essay-text-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; font-size:.9rem; white-space:pre-wrap; color:#475569; margin:10px 0; min-height:60px; }
.grade-form { display:flex; flex-wrap:wrap; align-items:center; gap:10px; background:#fafafa; border-radius:12px; padding:14px; border:1px solid #e2e8f0; margin-top:12px; }
</style>

<div class="max-w-4xl mx-auto pb-10">
 <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm mb-6 flex items-center gap-4 flex-wrap">
 <div class="w-12 h-12 rounded-xl bg-primary flex items-center justify-center shadow-sm shadow-md">
 <i class="fas fa-pen text-white text-xl"></i>
 </div>
 <div class="flex-1">
 <h1 class="text-xl font-bold text-slate-800">تصحيح أسئلة المقال</h1>
 <p class="text-sm text-slate-500"><?php echo htmlspecialchars($exam['title']); ?> — <?php echo $pending_count; ?> إجابة بانتظار التصحيح</p>
 </div>
 <a href="manage_exam.php" class="bg-bg text-slate-600 px-4 py-2 rounded-xl font-bold hover:bg-slate-200 transition text-sm flex items-center gap-2">
 <i class="fas fa-arrow-right"></i> رجوع
 </a>
 </div>

 <?php echo $message; ?>

 <?php if(empty($essay_answers)): ?>
 <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-12 text-center text-slate-400">
 <i class="fas fa-check-double text-4xl mb-3 block"></i>
 <p>لا توجد أسئلة مقال في هذا الامتحان أو لم يسلم أي طالب بعد.</p>
 </div>
 <?php else: ?>

 <!-- Group by student -->
 <?php 
 $by_student = [];
 foreach($essay_answers as $a) {
 $by_student[$a['username']][] = $a;
 }
 foreach($by_student as $uname => $answers): 
 $sname = $answers[0]['student_name'];
 ?>
 <div style="margin-bottom:8px;"><span class="student-badge"><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($sname); ?> (<?php echo htmlspecialchars($uname); ?>)</span></div>

 <?php foreach($answers as $a): 
 $graded = !is_null($a['points_earned']);
 $card_class = $graded ? 'graded' : 'pending';
 ?>
 <div class="essay-card <?php echo $card_class; ?>">
 <p style="font-weight:700;color:#1e293b;margin-bottom:6px;"><?php echo nl2br(htmlspecialchars($a['question_text'])); ?></p>
 <div class="essay-text-box">
 <?php echo !empty($a['essay_answer']) ? htmlspecialchars($a['essay_answer']) : '<em style="color:#94a3b8;">الطالب لم يكتب إجابة</em>'; ?>
 </div>
 
 <form method="POST" class="grade-form">
 <input type="hidden" name="action" value="grade_essay">
 <input type="hidden" name="answer_id" value="<?php echo $a['answer_id']; ?>">
 <input type="hidden" name="max_pts" value="<?php echo $a['max_pts']; ?>">
 <div style="display:flex;align-items:center;gap:8px;">
 <label style="font-size:.85rem;font-weight:700;color:#374151;">الدرجة:</label>
 <input type="number" name="points_given" value="<?php echo $a['points_earned'] ?? 0; ?>" min="0" max="<?php echo $a['max_pts']; ?>" step="0.5"
 style="width:80px;border:1px solid #d1d5db;border-radius:8px;padding:6px 10px;font-family:Cairo,sans-serif;"
 required>
 <span style="color:#64748b;font-size:.85rem;">من <?php echo $a['max_pts']; ?></span>
 </div>
 <input type="text" name="instructor_note" value="<?php echo htmlspecialchars($a['instructor_note'] ?? ''); ?>"
 style="flex:1;border:1px solid #d1d5db;border-radius:8px;padding:6px 12px;font-family:Cairo,sans-serif;font-size:.85rem;"
 placeholder="ملاحظة للطالب (اختياري)">
 <button type="submit" style="background:<?php echo $graded?'#059669':'#d97706'; ?>;color:white;padding:8px 18px;border-radius:10px;font-weight:700;border:none;cursor:pointer;font-family:Cairo,sans-serif;font-size:.85rem;">
 <?php echo $graded ? '✏ تعديل' : '✓ حفظ'; ?>
 </button>
 </form>

 <?php if($graded): ?>
 <div style="margin-top:8px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px 14px;font-size:.82rem;color:#166534;font-weight:700;">
 ✅ الدرجة: <?php echo $a['points_earned']; ?>/<?php echo $a['max_pts']; ?>
 <?php if($a['instructor_note']): ?> | ملاحظة: <?php echo htmlspecialchars($a['instructor_note']); ?><?php endif; ?>
 </div>
 <?php endif; ?>
 </div>
 <?php endforeach; ?>
 <hr style="border:none;border-top:2px dashed #e2e8f0;margin:20px 0;">
 <?php endforeach; ?>
 <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
