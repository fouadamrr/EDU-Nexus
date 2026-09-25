<?php
require_once '../includes/header.php';

if (!in_array($role, ['instructor','admin','super_admin','dean'])) {
 echo "<script>window.location.href='../index.php';</script>"; exit;
}

$message = '';
$errors = [];
$edit_q = null; // question being edited

// ── Fetch exams for dropdown ──────────────────────────────────────────────
$exams = [];
try {
 if (in_array($role, ['admin','super_admin'])) {
 $exams = $pdo->query("SELECT oe.id, oe.title, c.name AS course_name FROM online_exams oe LEFT JOIN courses c ON c.id=oe.course_id ORDER BY oe.created_at DESC")->fetchAll();
 } else {
 $stmt = $pdo->prepare("SELECT oe.id, oe.title, c.name AS course_name FROM online_exams oe LEFT JOIN courses c ON c.id=oe.course_id WHERE oe.created_by=? ORDER BY oe.created_at DESC");
 $stmt->execute([$user_id]);
 $exams = $stmt->fetchAll();
 }
} catch(Exception $e){}

// ── Pre-select exam_id from GET ───────────────────────────────────────────
$selected_exam_id = (int)($_GET['exam_id'] ?? 0);

// ── Load question for editing ─────────────────────────────────────────────
$edit_id = (int)($_GET['edit'] ?? 0);
if ($edit_id > 0) {
 try {
 $stmt = $pdo->prepare("SELECT * FROM online_questions WHERE id=?");
 $stmt->execute([$edit_id]);
 $edit_q = $stmt->fetch();
 if ($edit_q) {
 $selected_exam_id = (int)$edit_q['exam_id'];
 $sc = $pdo->prepare("SELECT * FROM online_choices WHERE question_id=? ORDER BY order_index");
 $sc->execute([$edit_id]);
 $edit_q['choices'] = $sc->fetchAll();
 }
 } catch(Exception $e){}
}

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $action = $_POST['action'] ?? '';
 $exam_id = (int)($_POST['exam_id'] ?? 0);
 $q_text = trim($_POST['question_text'] ?? '');
 $q_type = in_array($_POST['q_type'] ?? '', ['mcq','true_false','essay']) ? $_POST['q_type'] : 'mcq';
 $points = max(0.5, (float)($_POST['points'] ?? 1));
 $q_id_edit = (int)($_POST['q_id_edit'] ?? 0);

 // ── Validation ────────────────────────────────────────────────────────
 if ($exam_id <= 0) $errors[] = 'يرجى اختيار الامتحان.';
 if (empty($q_text)) $errors[] = 'نص السؤال لا يمكن أن يكون فارغاً.';

 if ($q_type === 'mcq') {
 $choices = array_map('trim', $_POST['choices'] ?? []);
 $correct = (int)($_POST['correct_choice'] ?? -1);
 $valid_choices = array_filter($choices, fn($c) => $c !== '');
 if (count($valid_choices) < 2) $errors[] = 'يجب إدخال خيارين على الأقل.';
 if ($correct < 0 || $correct > 3) $errors[] = 'يرجى تحديد الإجابة الصحيحة.';
 if (!isset($choices[$correct]) || trim($choices[$correct]) === '') $errors[] = 'الخيار المحدد كإجابة صحيحة فارغ.';
 }

 if (empty($errors)) {
 try {
 $pdo->beginTransaction();

 if ($action === 'add_question') {
 // Get next order index
 $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(order_index),0)+1 FROM online_questions WHERE exam_id=?");
 $maxOrder->execute([$exam_id]);
 $order = (int)$maxOrder->fetchColumn();

 $sq = $pdo->prepare("INSERT INTO online_questions (exam_id, question_text, type, points, order_index) VALUES (?,?,?,?,?) RETURNING id");
 $sq->execute([$exam_id, $q_text, $q_type, $points, $order]);
 $new_q_id = (int)$sq->fetchColumn();

 _insertChoices($pdo, $new_q_id, $q_type, $_POST);
 $pdo->commit();
 $message = _alert('success', 'تم إضافة السؤال بنجاح! يمكنك إضافة المزيد.');
 $selected_exam_id = $exam_id;

 } elseif ($action === 'edit_question' && $q_id_edit > 0) {
 $pdo->prepare("UPDATE online_questions SET question_text=?, type=?, points=? WHERE id=?")
 ->execute([$q_text, $q_type, $points, $q_id_edit]);

 // Rebuild choices
 $pdo->prepare("DELETE FROM online_choices WHERE question_id=?")->execute([$q_id_edit]);
 _insertChoices($pdo, $q_id_edit, $q_type, $_POST);
 $pdo->commit();
 $message = _alert('success', 'تم تعديل السؤال بنجاح.');
 $edit_q = null; // clear edit mode
 $selected_exam_id = $exam_id;
 }

 } catch(Exception $e) {
 $pdo->rollBack();
 $errors[] = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
 }
 }

 if (!empty($errors)) {
 $message = _alert('error', implode('<br>', $errors));
 $selected_exam_id = $exam_id;
 }
}

// ── Handle DELETE via GET ─────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
 $del_id = (int)$_GET['delete'];
 $back_eid = (int)($_GET['exam_id'] ?? 0);
 try {
 $pdo->prepare("DELETE FROM online_questions WHERE id=?")->execute([$del_id]);
 header("Location: create_question.php?exam_id={$back_eid}&deleted=1"); exit;
 } catch(Exception $e){}
}

// ── Fetch questions for selected exam ─────────────────────────────────────
$questions = [];
if ($selected_exam_id > 0) {
 try {
 $sq = $pdo->prepare("SELECT * FROM online_questions WHERE exam_id=? ORDER BY order_index");
 $sq->execute([$selected_exam_id]);
 $questions = $sq->fetchAll();
 foreach ($questions as &$q) {
 $sc = $pdo->prepare("SELECT * FROM online_choices WHERE question_id=? ORDER BY order_index");
 $sc->execute([$q['id']]);
 $q['choices'] = $sc->fetchAll();
 } unset($q);
 } catch(Exception $e){}
}

// ── Helper functions ──────────────────────────────────────────────────────
function _insertChoices(PDO $pdo, int $qid, string $type, array $post): void {
 if ($type === 'mcq') {
 $choices = array_map('trim', $post['choices'] ?? []);
 $correct = (int)($post['correct_choice'] ?? 0);
 foreach ($choices as $i => $txt) {
 if ($txt === '') continue;
 $pdo->prepare("INSERT INTO online_choices (question_id, choice_text, is_correct, order_index) VALUES (?,?,?,?)")
 ->execute([$qid, $txt, ($i === $correct) ? 'true' : 'false', $i]);
 }
 } elseif ($type === 'true_false') {
 $correct_tf = ($post['correct_tf'] ?? 'true') === 'true';
 $pdo->prepare("INSERT INTO online_choices (question_id, choice_text, is_correct, order_index) VALUES (?,?,?,?)")->execute([$qid, 'صحيح', $correct_tf ? 'true' : 'false', 0]);
 $pdo->prepare("INSERT INTO online_choices (question_id, choice_text, is_correct, order_index) VALUES (?,?,?,?)")->execute([$qid, 'خطأ', !$correct_tf ? 'true' : 'false', 1]);
 }
 // essay: no choices
}

function _alert(string $type, string $msg): string {
 $icons = ['success'=>'fa-check-circle','error'=>'fa-exclamation-triangle'];
 $cls = $type === 'success' ? 'bg-bg border-primary text-primary' : 'bg-bg border-primary text-primary';
 return "<div class=\"flex items-start gap-3 {$cls} border rounded-xl p-4 mb-5\"><i class=\"fas {$icons[$type]} mt-0.5 flex-shrink-0\"></i><span>{$msg}</span></div>";
}

// Prepare defaults for edit form
$is_editing = !is_null($edit_q);
$def_text = $is_editing ? htmlspecialchars($edit_q['question_text']) : '';
$def_type = $is_editing ? $edit_q['type'] : 'mcq';
$def_points = $is_editing ? $edit_q['points'] : 1;
$def_choices = array_pad(array_column($edit_q['choices'] ?? [], 'choice_text'), 4, '');
$def_correct = -1;
$def_correct_tf = 'true';
if ($is_editing) {
 foreach ($edit_q['choices'] ?? [] as $ci => $cv) {
 if ($cv['is_correct']) {
 if ($edit_q['type'] === 'mcq') $def_correct = $ci;
 if ($edit_q['type'] === 'true_false') $def_correct_tf = ($cv['choice_text'] === 'صحيح') ? 'true' : 'false';
 }
 }
}

$deleted_msg = isset($_GET['deleted']) ? _alert('success', 'تم حذف السؤال بنجاح.') : '';
$created_msg = isset($_GET['created']) ? '
<div style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border-radius:14px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
 <div style="width:44px;height:44px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
 <i class="fas fa-check-double" style="font-size:1.2rem;"></i>
 </div>
 <div>
 <div style="font-weight:800;font-size:1rem;margin-bottom:3px;">🎉 تم إنشاء الامتحان بنجاح!</div>
 <div style="font-size:.85rem;opacity:.9;">الامتحان محدد أدناه — ابدأ بإضافة الأسئلة من النموذج على اليمين.</div>
 </div>
</div>' : '';

?>

<style>
/* ── Layout ─────────────────────────────────────────────── */
.page-wrap { max-width:1100px; margin:0 auto; padding:0 0 60px; }
.grid-2 { display:grid; grid-template-columns:1fr 1.6fr; gap:24px; align-items:start; }
@media(max-width:900px){ .grid-2{grid-template-columns:1fr;} }

/* ── Form card ──────────────────────────────────────────── */
.card { background:#fff; border:1.5px solid #e2e8f0; border-radius:18px; padding:28px; }
.card-title { font-size:1.05rem; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:9px; margin-bottom:22px; }
.card-title i { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; }

/* ── Form controls ──────────────────────────────────────── */
.lbl { display:block; font-size:.8rem; font-weight:700; color:#374151; margin-bottom:6px; }
.req { color:#ef4444; margin-right:2px; }
.inp { width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:10px 14px; font-family:inherit; font-size:.9rem; color:#1e293b; background:#f8fafc; outline:none; transition:.15s; }
.inp:focus { border-color:#6366f1; background:#fff; box-shadow:0 0 0 3px rgba(99,102,241,.12); }
textarea.inp { resize:vertical; min-height:90px; }
select.inp { cursor:pointer; }
.inp-group { margin-bottom:18px; }

/* ── Type selector ──────────────────────────────────────── */
.type-tabs { display:flex; gap:8px; margin-bottom:20px; }
.type-tab { flex:1; padding:10px; border:2px solid #e2e8f0; border-radius:10px; text-align:center; cursor:pointer; font-weight:700; font-size:.82rem; color:#64748b; background:#f8fafc; transition:.15s; }
.type-tab:hover { border-color:#a5b4fc; color:#4f46e5; }
.type-tab.active { border-color:#6366f1; background:#eef2ff; color:#4f46e5; }

/* ── MCQ choices ────────────────────────────────────────── */
.choice-row { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.choice-row input[type=radio] { width:18px; height:18px; accent-color:#6366f1; flex-shrink:0; cursor:pointer; }
.choice-row .choice-letter { width:28px; height:28px; border-radius:8px; background:#e0e7ff; color:#4338ca; font-weight:800; font-size:.8rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.choice-row input[type=radio]:checked + .choice-letter { background:#6366f1; color:#fff; }

/* ── T/F ───────────────────────────────────────────────── */
.tf-option { flex:1; border:2px solid #e2e8f0; border-radius:12px; padding:14px; display:flex; align-items:center; gap:10px; cursor:pointer; transition:.15s; }
.tf-option:has(input:checked).tf-true { border-color:#10b981; background:#f0fdf4; }
.tf-option:has(input:checked).tf-false { border-color:#ef4444; background:#fef2f2; }
.tf-option input[type=radio] { accent-color:#6366f1; width:16px; height:16px; }

/* ── Submit btn ─────────────────────────────────────────── */
.btn-primary { width:100%; background:#6366f1; color:#fff; border:none; border-radius:12px; padding:13px; font-family:inherit; font-size:.95rem; font-weight:800; cursor:pointer; transition:.15s; display:flex; align-items:center; justify-content:center; gap:8px; }
.btn-primary:hover { background:#4f46e5; }
.btn-edit { background:#f59e0b; }
.btn-edit:hover { background:#d97706; }

/* ── Questions list ─────────────────────────────────────── */
.q-item { background:#fff; border:1.5px solid #e2e8f0; border-radius:14px; padding:18px; margin-bottom:12px; transition:.2s; }
.q-item:hover { border-color:#c7d2fe; box-shadow:0 3px 12px rgba(99,102,241,.08); }
.q-num-badge { width:30px; height:30px; border-radius:8px; background:#eef2ff; color:#6366f1; font-weight:800; font-size:.82rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.type-pill { padding:3px 10px; border-radius:999px; font-size:.7rem; font-weight:700; }
.type-mcq { background:#ede9fe; color:#7c3aed; }
.type-tf { background:#dcfce7; color:#166534; }
.type-essay{ background:#fef3c7; color:#92400e; }
.pts-pill { padding:3px 10px; border-radius:999px; font-size:.7rem; font-weight:700; background:#fff7ed; color:#c2410c; }
.choice-display { display:flex; align-items:center; gap:8px; padding:7px 12px; border-radius:8px; background:#f8fafc; margin-top:6px; font-size:.85rem; }
.correct-dot { width:18px; height:18px; border-radius:50%; background:#10b981; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wrong-dot { width:18px; height:18px; border-radius:50%; background:#e2e8f0; flex-shrink:0; }
.action-btn { padding:6px 12px; border-radius:8px; font-size:.78rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:5px; border:1.5px solid; transition:.15s; cursor:pointer; font-family:inherit; }
.btn-edit-sm { background:#fffbeb; border-color:#fde68a; color:#92400e; }
.btn-edit-sm:hover { background:#fef3c7; }
.btn-del-sm { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
.btn-del-sm:hover { background:#fee2e2; }
.empty-state { border:2px dashed #e2e8f0; border-radius:14px; padding:48px; text-align:center; color:#94a3b8; }
</style>

<div class="page-wrap">

 <!-- Page header -->
 <div class="card mb-6">
 <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
 <div style="display:flex;align-items:center;gap:14px;">
 <div style="width:48px;height:48px;border-radius:14px;background:#6366f1;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 16px rgba(99,102,241,.35);">
 <i class="fas fa-question-circle" style="color:#fff;font-size:1.25rem;"></i>
 </div>
 <div>
 <h1 style="font-size:1.25rem;font-weight:800;color:#1e293b;margin:0;">إدارة الأسئلة</h1>
 <p style="font-size:.82rem;color:#64748b;margin:3px 0 0;">أضف وعدّل وامسح أسئلة الامتحانات الإلكترونية</p>
 </div>
 </div>
 <a href="manage_exam.php" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#f1f5f9;border-radius:10px;font-weight:700;font-size:.85rem;color:#475569;text-decoration:none;">
 <i class="fas fa-arrow-right"></i> الامتحانات
 </a>
 </div>
 </div>

 <?php echo $created_msg . $deleted_msg . $message; ?>


 <div class="grid-2">

 <!-- ══════════════ LEFT: FORM ══════════════ -->
 <div class="card" style="position:sticky;top:16px;">

 <div class="card-title">
 <?php if($is_editing): ?>
 <i class="fas fa-edit" style="background:#fef3c7;color:#d97706;"></i> تعديل السؤال #<?php echo $edit_q['id']; ?>
 <?php else: ?>
 <i class="fas fa-plus-circle" style="background:#ede9fe;color:#7c3aed;"></i> إضافة سؤال جديد
 <?php endif; ?>
 </div>

 <form method="POST" id="qForm" onsubmit="return validateForm()">
 <input type="hidden" name="action" value="<?php echo $is_editing ? 'edit_question' : 'add_question'; ?>">
 <?php if($is_editing): ?>
 <input type="hidden" name="q_id_edit" value="<?php echo $edit_q['id']; ?>">
 <?php endif; ?>

 <!-- Exam selector -->
 <div class="inp-group">
 <label class="lbl">الامتحان <span class="req">*</span></label>
 <select name="exam_id" id="examSelect" class="inp" required onchange="reloadQuestions(this.value)" <?php echo $is_editing?'disabled':''; ?>>
 <option value="">— اختر الامتحان —</option>
 <?php foreach($exams as $ex): ?>
 <option value="<?php echo $ex['id']; ?>" <?php echo $selected_exam_id==$ex['id']?'selected':''; ?>>
 <?php echo htmlspecialchars($ex['title']); ?>
 <?php if($ex['course_name']): ?>(<?php echo htmlspecialchars($ex['course_name']); ?>)<?php endif; ?>
 </option>
 <?php endforeach; ?>
 </select>
 <?php if($is_editing): ?>
 <input type="hidden" name="exam_id" value="<?php echo $edit_q['exam_id']; ?>">
 <?php endif; ?>
 </div>

 <!-- Question text -->
 <div class="inp-group">
 <label class="lbl">نص السؤال <span class="req">*</span></label>
 <textarea name="question_text" class="inp" id="qText" placeholder="اكتب نص السؤال هنا..." required><?php echo $def_text; ?></textarea>
 </div>

 <!-- Points -->
 <div class="inp-group">
 <label class="lbl">النقاط <span class="req">*</span></label>
 <input type="number" name="points" class="inp" value="<?php echo $def_points; ?>" min="0.5" max="100" step="0.5" required>
 </div>

 <!-- Question Type Tabs -->
 <div class="inp-group">
 <label class="lbl">نوع السؤال <span class="req">*</span></label>
 <input type="hidden" name="q_type" id="qTypeInput" value="<?php echo $def_type; ?>">
 <div class="type-tabs">
 <div class="type-tab <?php echo $def_type==='mcq'?'active':''; ?>" onclick="setType('mcq',this)">
 <i class="fas fa-list-ul" style="margin-bottom:3px;display:block;"></i>MCQ
 </div>
 <div class="type-tab <?php echo $def_type==='true_false'?'active':''; ?>" onclick="setType('true_false',this)">
 <i class="fas fa-check-circle" style="margin-bottom:3px;display:block;"></i>صح / خطأ
 </div>
 <div class="type-tab <?php echo $def_type==='essay'?'active':''; ?>" onclick="setType('essay',this)">
 <i class="fas fa-pen" style="margin-bottom:3px;display:block;"></i>مقال
 </div>
 </div>
 </div>

 <!-- MCQ Choices -->
 <div id="mcq-section" style="display:<?php echo $def_type==='mcq'?'block':'none'; ?>">
 <label class="lbl">الخيارات <span class="req">*</span> <span style="font-weight:400;color:#94a3b8;">(اختر الإجابة الصحيحة)</span></label>
 <div id="choicesList">
 <?php
 $letters = ['A','B','C','D'];
 for($i=0;$i<4;$i++):
 $val = htmlspecialchars($def_choices[$i] ?? '');
 $checked = ($def_correct === $i) ? 'checked' : ($def_correct === -1 && $i === 0 ? 'checked' : '');
 ?>
 <div class="choice-row">
 <input type="radio" name="correct_choice" value="<?php echo $i; ?>" <?php echo $checked; ?> title="هذا هو الجواب الصحيح">
 <div class="choice-letter"><?php echo $letters[$i]; ?></div>
 <input type="text" name="choices[]" class="inp" style="margin-bottom:0;"
 placeholder="الخيار <?php echo $letters[$i]; ?>"
 value="<?php echo $val; ?>">
 </div>
 <?php endfor; ?>
 </div>
 <p style="font-size:.75rem;color:#94a3b8;margin-top:6px;"><i class="fas fa-info-circle"></i> اضغط على دائرة التحديد الدائري (radio) لتحديد الإجابة الصحيحة.</p>
 </div>

 <!-- True/False -->
 <div id="tf-section" style="display:<?php echo $def_type==='true_false'?'block':'none'; ?>">
 <label class="lbl">الإجابة الصحيحة <span class="req">*</span></label>
 <div style="display:flex;gap:12px;margin-bottom:10px;">
 <label class="tf-option tf-true">
 <input type="radio" name="correct_tf" value="true" <?php echo $def_correct_tf==='true'?'checked':''; ?>>
 <div>
 <div style="font-weight:800;color:#065f46;font-size:.9rem;">✓ صحيح</div>
 <div style="font-size:.75rem;color:#6ee7b7;">True</div>
 </div>
 </label>
 <label class="tf-option tf-false">
 <input type="radio" name="correct_tf" value="false" <?php echo $def_correct_tf==='false'?'checked':''; ?>>
 <div>
 <div style="font-weight:800;color:#991b1b;font-size:.9rem;">✗ خطأ</div>
 <div style="font-size:.75rem;color:#fca5a5;">False</div>
 </div>
 </label>
 </div>
 </div>

 <!-- Essay notice -->
 <div id="essay-section" style="display:<?php echo $def_type==='essay'?'block':'none'; ?>">
 <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:14px;margin-bottom:10px;">
 <p style="margin:0;font-size:.85rem;color:#92400e;font-weight:700;"><i class="fas fa-info-circle me-1"></i> سؤال مقال</p>
 <p style="margin:6px 0 0;font-size:.8rem;color:#78350f;">لا توجد خيارات — سيكتب الطالب إجابة حرة ويصححها الدكتور يدوياً لاحقاً.</p>
 </div>
 </div>

 <!-- Submit -->
 <button type="submit" class="btn-primary <?php echo $is_editing?'btn-edit':''; ?>" style="margin-top:8px;">
 <i class="fas <?php echo $is_editing?'fa-save':'fa-plus-circle'; ?>"></i>
 <?php echo $is_editing ? 'حفظ التعديلات' : 'إضافة السؤال'; ?>
 </button>
 <?php if($is_editing): ?>
 <a href="create_question.php?exam_id=<?php echo $edit_q['exam_id']; ?>" style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;padding:11px;border:1.5px solid #e2e8f0;border-radius:12px;font-weight:700;font-size:.88rem;color:#64748b;text-decoration:none;">
 <i class="fas fa-times"></i> إلغاء التعديل
 </a>
 <?php endif; ?>
 </form>
 </div>

 <!-- ══════════════ RIGHT: QUESTIONS LIST ══════════════ -->
 <div>
 <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
 <h2 style="font-size:1rem;font-weight:800;color:#1e293b;margin:0;display:flex;align-items:center;gap:8px;">
 <i class="fas fa-list-ol" style="color:#6366f1;"></i>
 الأسئلة الحالية
 <span style="background:#eef2ff;color:#6366f1;padding:2px 10px;border-radius:999px;font-size:.75rem;"><?php echo count($questions); ?></span>
 </h2>
 <?php if($selected_exam_id > 0): ?>
 <a href="add_questions.php?exam_id=<?php echo $selected_exam_id; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#eef2ff;border-radius:9px;font-weight:700;font-size:.78rem;color:#6366f1;text-decoration:none;">
 <i class="fas fa-eye"></i> عرض الامتحان
 </a>
 <?php endif; ?>
 </div>

 <?php if($selected_exam_id <= 0): ?>
 <div class="empty-state">
 <i class="fas fa-hand-point-up" style="font-size:2rem;display:block;margin-bottom:12px;opacity:.5;"></i>
 <p style="font-weight:700;margin-bottom:4px;">اختر امتحاناً أولاً</p>
 <p style="font-size:.85rem;">ستظهر هنا أسئلته بعد الاختيار.</p>
 </div>

 <?php elseif(empty($questions)): ?>
 <div class="empty-state">
 <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:12px;opacity:.5;"></i>
 <p style="font-weight:700;margin-bottom:4px;">لا توجد أسئلة بعد</p>
 <p style="font-size:.85rem;">أضف أول سؤال من النموذج على اليمين.</p>
 </div>

 <?php else: ?>
 <?php foreach($questions as $qi => $q): 
 $type_cls = ['mcq'=>'type-mcq','true_false'=>'type-tf','essay'=>'type-essay'][$q['type']] ?? 'type-mcq';
 $type_lbl = ['mcq'=>'MCQ','true_false'=>'صح/خطأ','essay'=>'مقال'][$q['type']] ?? $q['type'];
 ?>
 <div class="q-item" id="qi-<?php echo $q['id']; ?>">
 <div style="display:flex;align-items:flex-start;gap:12px;">
 <div class="q-num-badge"><?php echo $qi+1; ?></div>
 <div style="flex:1;min-width:0;">
 <p style="font-weight:700;color:#1e293b;margin:0 0 8px;line-height:1.5;"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></p>
 <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-bottom:10px;">
 <span class="type-pill <?php echo $type_cls; ?>"><?php echo $type_lbl; ?></span>
 <span class="pts-pill"><?php echo $q['points']; ?> نقطة</span>
 </div>

 <!-- Choices display -->
 <?php if(!empty($q['choices'])): ?>
 <div>
 <?php foreach($q['choices'] as $ci => $ch): ?>
 <div class="choice-display">
 <?php if($ch['is_correct']): ?>
 <div class="correct-dot" title="الإجابة الصحيحة"><i class="fas fa-check" style="font-size:.55rem;"></i></div>
 <span style="font-weight:700;color:#065f46;"><?php echo htmlspecialchars($ch['choice_text']); ?></span>
 <span style="margin-right:auto;font-size:.7rem;color:#10b981;font-weight:700;">✓ صحيح</span>
 <?php else: ?>
 <div class="wrong-dot"></div>
 <span style="color:#475569;"><?php echo htmlspecialchars($ch['choice_text']); ?></span>
 <?php endif; ?>
 </div>
 <?php endforeach; ?>
 </div>
 <?php elseif($q['type']==='essay'): ?>
 <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;font-size:.8rem;color:#92400e;">
 <i class="fas fa-pen me-1"></i> إجابة حرة — تصحيح يدوي
 </div>
 <?php endif; ?>

 <!-- Actions -->
 <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
 <a href="create_question.php?edit=<?php echo $q['id']; ?>&exam_id=<?php echo $q['exam_id']; ?>" class="action-btn btn-edit-sm">
 <i class="fas fa-edit"></i> تعديل
 </a>
 <a href="create_question.php?delete=<?php echo $q['id']; ?>&exam_id=<?php echo $q['exam_id']; ?>"
 onclick="return confirm('هل أنت متأكد من حذف هذا السؤال؟')"
 class="action-btn btn-del-sm">
 <i class="fas fa-trash"></i> حذف
 </a>
 </div>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 
 <!-- Finish button -->
 <div style="text-align:center;margin-top:20px;">
 <a href="manage_exam.php" style="display:inline-flex;align-items:center;gap:8px;padding:13px 32px;background:#10b981;color:#fff;border-radius:12px;font-weight:800;text-decoration:none;box-shadow:0 6px 16px rgba(16,185,129,.3);">
 <i class="fas fa-check-double"></i> تم — الرجوع للامتحانات
 </a>
 </div>
 <?php endif; ?>
 </div>

 </div><!-- end grid -->
</div>

<script>
// ── Type switching ──────────────────────────────────────────────────────
function setType(type, el) {
 document.querySelectorAll('.type-tab').forEach(t => t.classList.remove('active'));
 el.classList.add('active');
 document.getElementById('qTypeInput').value = type;
 document.getElementById('mcq-section').style.display = type === 'mcq' ? 'block' : 'none';
 document.getElementById('tf-section').style.display = type === 'true_false' ? 'block' : 'none';
 document.getElementById('essay-section').style.display = type === 'essay' ? 'block' : 'none';
}

// ── Exam change → reload page ──────────────────────────────────────────
function reloadQuestions(val) {
 if (val) window.location.href = 'create_question.php?exam_id=' + val;
}

// ── Client-side validation ─────────────────────────────────────────────
function validateForm() {
 const type = document.getElementById('qTypeInput').value;
 const text = document.getElementById('qText').value.trim();

 if (!text) {
 alert('يرجى كتابة نص السؤال.');
 document.getElementById('qText').focus();
 return false;
 }

 if (type === 'mcq') {
 const choices = document.querySelectorAll('input[name="choices[]"]');
 const filled = Array.from(choices).filter(c => c.value.trim() !== '');
 if (filled.length < 2) {
 alert('يرجى كتابة خيارين على الأقل.');
 return false;
 }
 const correct = document.querySelector('input[name="correct_choice"]:checked');
 if (!correct) {
 alert('يرجى تحديد الإجابة الصحيحة بالضغط على الدائرة (◯) بجانب الخيار.');
 return false;
 }
 // Make sure selected correct choice is not empty
 const correctIdx = parseInt(correct.value);
 if (!choices[correctIdx] || choices[correctIdx].value.trim() === '') {
 alert('الخيار المحدد كإجابة صحيحة فارغ. يرجى كتابة نص له أو اختيار خيار آخر.');
 return false;
 }
 }
 return true;
}

// ── Highlight selected radio row ──────────────────────────────────────
document.addEventListener('change', function(e) {
 if (e.target.name === 'correct_choice') {
 document.querySelectorAll('.choice-row').forEach((row, i) => {
 const radio = row.querySelector('input[type=radio]');
 const letter = row.querySelector('.choice-letter');
 if (radio && radio.checked) {
 letter.style.background = '#6366f1';
 letter.style.color = '#fff';
 row.style.border = '1.5px solid #6366f1';
 row.style.borderRadius = '10px';
 row.style.paddingLeft = '8px';
 } else {
 if (letter) { letter.style.background = '#e0e7ff'; letter.style.color = '#4338ca'; }
 row.style.border = '';
 row.style.paddingLeft = '';
 }
 });
 }
});

// Trigger once on load for edit mode
document.querySelector('input[name="correct_choice"]:checked')?.dispatchEvent(new Event('change', {bubbles:true}));
</script>

<?php require_once '../includes/footer.php'; ?>
