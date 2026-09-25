<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../database/db_connection.php';
$pdo = get_pdo();

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

if ($role !== 'student' || $user_id <= 0) { header("Location: ../index.php"); exit; }

$submission_id = (int)($_POST['submission_id'] ?? $_GET['submission_id'] ?? 0);
$auto_submit = isset($_GET['auto']) || isset($_POST['auto']);

if ($submission_id <= 0) { header("Location: student_exams.php"); exit; }

// Fetch submission
$submission = null;
try {
 $stmt = $pdo->prepare("SELECT * FROM online_submissions WHERE id=? AND student_id=?");
 $stmt->execute([$submission_id, $user_id]);
 $submission = $stmt->fetch();
} catch(Exception $e){}

if (!$submission) { header("Location: student_exams.php"); exit; }
if ($submission['status'] === 'submitted') { header("Location: exam_results.php?submission_id={$submission_id}"); exit; }

$exam_id = (int)$submission['exam_id'];

// Fetch exam
$exam = null;
try {
 $stmt = $pdo->prepare("SELECT * FROM online_exams WHERE id=?");
 $stmt->execute([$exam_id]);
 $exam = $stmt->fetch();
} catch(Exception $e){}

if (!$exam) { header("Location: student_exams.php"); exit; }

// Fetch questions
$questions = [];
try {
 $stmt = $pdo->prepare("SELECT * FROM online_questions WHERE exam_id=?");
 $stmt->execute([$exam_id]);
 $questions = $stmt->fetchAll();
} catch(Exception $e){}

// ── Process each answer & auto-grade ─────────────────────────────────────
$total_auto_score = 0;
$max_score = 0;
$has_essay = false;

$pdo->beginTransaction();
try {
 foreach ($questions as $q) {
 $qid = (int)$q['id'];
 $qtype = $q['type'];
 $points = (float)$q['points'];
 $max_score += $points;

 $selected_choice_id = null;
 $essay_answer = null;
 $is_correct = null;
 $points_earned = 0;

 if ($qtype === 'mcq' || $qtype === 'true_false') {
 $selected = (int)($_POST['answer'][$qid] ?? 0);
 if ($selected > 0) {
 $selected_choice_id = $selected;
 // Check if correct
 $sc = $pdo->prepare("SELECT is_correct FROM online_choices WHERE id=? AND question_id=?");
 $sc->execute([$selected, $qid]);
 $choice_row = $sc->fetch();
 if ($choice_row && $choice_row['is_correct']) {
 $is_correct = true;
 $points_earned = $points;
 $total_auto_score += $points;
 } else {
 $is_correct = false;
 }
 }
 } elseif ($qtype === 'essay') {
 $essay_answer = trim($_POST['essay'][$qid] ?? '');
 $has_essay = true;
 $is_correct = null; // needs manual grading
 $points_earned = 0; // will be set later
 }

 // Cast is_correct to proper PostgreSQL boolean (true/false/null)
 // PHP's false becomes "" when bound, which PostgreSQL rejects for boolean columns
 $is_correct_db = ($is_correct === null) ? null : ($is_correct ? true : false);

 // Upsert answer
 // Use explicit CAST in SQL to avoid PDO converting PHP false → "" which PostgreSQL rejects
 $is_correct_sql = ($is_correct === null) ? 'NULL' : ($is_correct ? 'TRUE' : 'FALSE');
 $sa = $pdo->prepare("
 INSERT INTO online_answers (submission_id, question_id, selected_choice_id, essay_answer, is_correct, points_earned)
 VALUES (?,?,?,?,{$is_correct_sql},?)
 ON CONFLICT (submission_id, question_id) DO NOTHING
 ");
 $sa->execute([$submission_id, $qid, $selected_choice_id, $essay_answer ?: null, $points_earned]);
 }

 // Determine submission status
 $new_status = $has_essay ? 'submitted' : 'graded';
 $percentage = $max_score > 0 ? round(($total_auto_score / $max_score) * 100, 2) : 0;

 $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
 $pdo->prepare("
 UPDATE online_submissions SET 
 submitted_at = NOW(),
 score = ?,
 max_score = ?,
 percentage = ?,
 status = ?
 WHERE id=?
 ")->execute([$total_auto_score, $max_score, $percentage, $new_status, $submission_id]);

 // Log submission
 $pdo->prepare("INSERT INTO exam_logs (exam_id, student_id, action, details, ip_address) VALUES (?,?,?,?,?)")
 ->execute([$exam_id, $user_id, 'submitted', "Score: {$total_auto_score}/{$max_score}", $ip]);

 // Log cheat count if any
 $cheat_count = (int)($_POST['cheat_count'] ?? 0);
 if ($cheat_count > 0) {
 $pdo->prepare("INSERT INTO exam_logs (exam_id, student_id, action, details, ip_address) VALUES (?,?,?,?,?)")
 ->execute([$exam_id, $user_id, 'cheating_summary', "Total suspicious events: {$cheat_count}", $ip]);
 }

 $pdo->commit();

 // Redirect to results
 header("Location: exam_results.php?submission_id={$submission_id}" . ($auto_submit ? '&auto=1' : ''));
 exit;

} catch (Exception $e) {
 $pdo->rollBack();
 error_log('[Online Exam Submit Error] ' . $e->getMessage());
 echo '<!DOCTYPE html><html lang="ar" dir="rtl"><body style="font-family:Cairo;padding:32px;text-align:center;">
 <h2 style="color:#dc2626;">حدث خطأ أثناء التسليم</h2>
 <p>' . htmlspecialchars($e->getMessage()) . '</p>
 <a href="take_exam.php?exam_id=' . $exam_id . '" style="color:#6366f1;">المحاولة مرة أخرى</a>
 </body></html>';
}
