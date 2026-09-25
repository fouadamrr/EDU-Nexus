<?php
require_once '../includes/header.php';

if ($role !== 'instructor' && $role !== 'admin' && $role !== 'super_admin' && $role !== 'dean') {
 echo "<script>window.location.href='../index.php';</script>"; exit;
}

$exam_id = (int)($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) {
 echo "<script>window.location.href='manage_exam.php';</script>"; exit;
}

// Fetch exam info
$exam = null;
try {
 $stmt = $pdo->prepare("SELECT oe.*, c.name AS course_name FROM online_exams oe LEFT JOIN courses c ON c.id = oe.course_id WHERE oe.id = ?");
 $stmt->execute([$exam_id]);
 $exam = $stmt->fetch();
} catch(Exception $e){}

if (!$exam) {
 echo "<script>window.location.href='manage_exam.php';</script>"; exit;
}

// Only allow the creator or admin
if ($role === 'instructor' && (int)$exam['created_by'] !== (int)$user_id) {
 echo "<script>window.location.href='manage_exam.php';</script>"; exit;
}

$message = '';

// ── Handle Actions ──────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $action = $_POST['action'] ?? '';

 // ADD QUESTION
 if ($action === 'add_question') {
 $q_text = trim($_POST['question_text'] ?? '');
 $q_type = in_array($_POST['q_type'] ?? '', ['mcq','true_false','essay']) ? $_POST['q_type'] : 'mcq';
 $points = max(0.5, (float)($_POST['points'] ?? 1));

 if (empty($q_text)) {
 $message = '<div class="alert-box error">نص السؤال مطلوب.</div>';
 } else {
 try {
 // Get max order
 $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(order_index),0)+1 FROM online_questions WHERE exam_id=?");
 $maxOrder->execute([$exam_id]);
 $order = $maxOrder->fetchColumn();

 $sq = $pdo->prepare("INSERT INTO online_questions (exam_id, question_text, type, points, order_index) VALUES (?,?,?,?,?) RETURNING id");
 $sq->execute([$exam_id, $q_text, $q_type, $points, $order]);
 $q_id = $sq->fetchColumn();

 // Add choices for MCQ
 if ($q_type === 'mcq') {
 $choices = $_POST['choices'] ?? [];
 $correct = (int)($_POST['correct_choice'] ?? 0);
 foreach ($choices as $i => $ctxt) {
 $ctxt = trim($ctxt);
 if (empty($ctxt)) continue;
 $sc = $pdo->prepare("INSERT INTO online_choices (question_id, choice_text, is_correct, order_index) VALUES (?,?,?,?)");
 $sc->execute([$q_id, $ctxt, ($i === $correct) ? 'true' : 'false', $i]);
 }
 } elseif ($q_type === 'true_false') {
 $correct_tf = $_POST['correct_tf'] ?? 'true';
 $pdo->prepare("INSERT INTO online_choices (question_id, choice_text, is_correct, order_index) VALUES (?,?,?,?)")->execute([$q_id,'صحيح', $correct_tf==='true' ? 'true' : 'false', 0]);
 $pdo->prepare("INSERT INTO online_choices (question_id, choice_text, is_correct, order_index) VALUES (?,?,?,?)")->execute([$q_id,'خطأ', $correct_tf==='false' ? 'true' : 'false', 1]);
 }

 $message = '<div class="alert-box success"><i class="fas fa-check-circle"></i> تم إضافة السؤال بنجاح.</div>';
 } catch(Exception $e) {
 $message = '<div class="alert-box error">خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
 }
 }
 }

 // DELETE QUESTION
 if ($action === 'delete_question') {
 $q_id = (int)($_POST['q_id'] ?? 0);
 try {
 $pdo->prepare("DELETE FROM online_questions WHERE id=? AND exam_id=?")->execute([$q_id, $exam_id]);
 $message = '<div class="alert-box success">تم حذف السؤال.</div>';
 } catch(Exception $e){}
 }
}

// Fetch questions
$questions = [];
try {
 $sq = $pdo->prepare("SELECT * FROM online_questions WHERE exam_id = ? ORDER BY order_index");
 $sq->execute([$exam_id]);
 $questions = $sq->fetchAll();
 foreach ($questions as &$q) {
 $sc = $pdo->prepare("SELECT * FROM online_choices WHERE question_id = ? ORDER BY order_index");
 $sc->execute([$q['id']]);
 $q['choices'] = $sc->fetchAll();
 }
 unset($q);
} catch(Exception $e){}

$type_labels = ['quiz'=>'كويز','midterm'=>'اختبار ترمي','assignment'=>'واجب'];
$exam_created = isset($_GET['created']);
?>

<style>
.alert-box { padding:12px 18px; border-radius:10px; font-weight:bold; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.alert-box.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.alert-box.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.q-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:20px; margin-bottom:14px; transition:.2s; }
.q-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.06); border-color:#c7d2fe; }
.q-badge { display:inline-flex; align-items:center; gap:5px; background:#eef2ff; color:#4338ca; padding:3px 12px; border-radius:999px; font-size:.75rem; font-weight:700; }
.choice-item { display:flex; align-items:center; gap:10px; padding:10px 14px; background:#f8fafc; border-radius:10px; margin-bottom:8px; }
.choice-dot-correct { width:22px; height:22px; border-radius:50%; background:#10b981; color:white; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:.7rem; }
.choice-dot { width:22px; height:22px; border-radius:50%; background:#e2e8f0; flex-shrink:0; }
.form-section { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:24px; margin-top:24px; }
.tab-btn { padding:10px 20px; border-radius:8px; font-weight:600; font-size:.875rem; cursor:pointer; border:1px solid #e2e8f0; background:white; color:#475569; transition:.2s; }
.tab-btn.active { background:#4f46e5; color:white; border-color:#4f46e5; }
.tab-content { display:none; }
.tab-content.active { display:block; }
</style>

<div class="max-w-5xl mx-auto pb-10">

 <?php if($exam_created): ?>
 <div class="alert-box success mb-4"><i class="fas fa-party-horn"></i> 🎉 تم إنشاء الامتحان بنجاح! الآن أضف الأسئلة.</div>
 <?php endif; ?>

 <!-- Header -->
 <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm mb-6">
 <div class="flex items-center gap-4 flex-wrap">
 <div class="w-12 h-12 rounded-xl bg-primary flex items-center justify-center shadow-sm shadow-md">
 <i class="fas fa-question-circle text-white text-xl"></i>
 </div>
 <div class="flex-1">
 <h1 class="text-xl font-bold text-slate-800"><?php echo htmlspecialchars($exam['title']); ?></h1>
 <p class="text-sm text-slate-500 mt-0.5">
 <span class="q-badge"><?php echo $type_labels[$exam['type']] ?? $exam['type']; ?></span>
 <span class="mr-2 text-slate-400"><?php echo htmlspecialchars($exam['course_name'] ?? ''); ?></span>
 &mdash; <?php echo count($questions); ?> سؤال
 </p>
 </div>
 <div class="flex gap-2">
 <a href="create_question.php?exam_id=<?php echo $exam_id; ?>" class="bg-bg text-primary px-4 py-2 rounded-xl font-bold hover:bg-primary transition text-sm flex items-center gap-2">
 <i class="fas fa-pen-"></i> إدارة متقدمة للأسئلة
 </a>
 <a href="manage_exam.php" class="bg-bg text-slate-600 px-4 py-2 rounded-xl font-bold hover:bg-slate-200 transition text-sm flex items-center gap-2">
 <i class="fas fa-list"></i> كل الامتحانات
 </a>
 <a href="view_results.php?exam_id=<?php echo $exam_id; ?>" class="bg-bg text-white px-4 py-2 rounded-xl font-bold hover:bg-sky-500 transition text-sm flex items-center gap-2">
 <i class="fas fa-chart-bar"></i> النتائج
 </a>
 </div>
 </div>
 </div>

 <?php echo $message; ?>

 <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

 <!-- Questions List -->
 <div class="lg:col-span-3">
 <h2 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
 <i class="fas fa-list-ol text-primary"></i> الأسئلة الموجودة (<?php echo count($questions); ?>)
 </h2>

 <?php if(empty($questions)): ?>
 <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-12 text-center text-slate-400">
 <i class="fas fa-inbox text-4xl mb-3 block"></i>
 <p class="font-medium">لا توجد أسئلة بعد — أضف أول سؤال</p>
 </div>
 <?php else: ?>
 <?php foreach($questions as $i => $q): ?>
 <div class="q-card">
 <div class="flex items-start justify-between gap-3">
 <div class="flex items-start gap-3 flex-1">
 <div class="w-8 h-8 rounded-lg bg-bg text-primary flex items-center justify-center font-bold flex-shrink-0 text-sm mt-0.5"><?php echo $i+1; ?></div>
 <div class="flex-1">
 <p class="font-medium text-slate-800"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></p>
 <div class="mt-2 flex flex-wrap gap-2">
 <span class="q-badge">
 <?php 
 $tl=['mcq'=>'MCQ اختيار متعدد','true_false'=>'صح/خطأ','essay'=>'مقال/إجابة حرة'];
 echo $tl[$q['type']] ?? $q['type'];
 ?>
 </span>
 <span class="q-badge" style="background:#fef3c7;color:#92400e;"><?php echo $q['points']; ?> نقطة</span>
 </div>
 <?php if(!empty($q['choices'])): ?>
 <div class="mt-3 space-y-1">
 <?php foreach($q['choices'] as $c): ?>
 <div class="choice-item">
 <?php if($c['is_correct']): ?>
 <div class="choice-dot-correct"><i class="fas fa-check" style="font-size:.6rem;"></i></div>
 <?php else: ?>
 <div class="choice-dot"></div>
 <?php endif; ?>
 <span class="text-sm text-slate-700 <?php echo $c['is_correct'] ? 'font-bold text-primary' : ''; ?>"><?php echo htmlspecialchars($c['choice_text']); ?></span>
 </div>
 <?php endforeach; ?>
 </div>
 <?php elseif($q['type']==='essay'): ?>
 <div class="mt-3 choice-item">
 <i class="fas fa-pen text-primary"></i>
 <span class="text-sm text-slate-500 italic">إجابة حرة — يصححها الدكتور يدوياً</span>
 </div>
 <?php endif; ?>
 </div>
 </div>
 <form method="POST" onsubmit="return confirm('حذف هذا السؤال نهائياً؟')">
 <input type="hidden" name="action" value="delete_question">
 <input type="hidden" name="q_id" value="<?php echo $q['id']; ?>">
 <button type="submit" class="text-primary hover:text-primary hover:bg-bg w-8 h-8 rounded-lg flex items-center justify-center transition">
 <i class="fas fa-trash text-sm"></i>
 </button>
 </form>
 </div>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>
 </div>

 <!-- Add Question Panel -->
 <div class="lg:col-span-2">
 <div class="form-section sticky top-4">
 <h2 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
 <i class="fas fa-plus-circle text-primary"></i> إضافة سؤال جديد
 </h2>

 <!-- Question Type Tabs -->
 <div class="flex gap-2 mb-4 flex-wrap">
 <button type="button" class="tab-btn active" onclick="switchTab('mcq',this)">MCQ</button>
 <button type="button" class="tab-btn" onclick="switchTab('tf',this)">صح/خطأ</button>
 <button type="button" class="tab-btn" onclick="switchTab('essay',this)">مقال</button>
 </div>

 <!-- MCQ Form -->
 <form method="POST" id="form-mcq" class="tab-content active">
 <input type="hidden" name="action" value="add_question">
 <input type="hidden" name="q_type" value="mcq">
 <div class="mb-3">
 <label class="block text-xs font-bold text-slate-600 mb-1">نص السؤال *</label>
 <textarea name="question_text" required rows="3" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 bg-bg text-sm resize-none focus:ring-2 focus:ring-indigo-300" placeholder="اكتب نص السؤال هنا..."></textarea>
 </div>
 <div class="mb-2">
 <label class="block text-xs font-bold text-slate-600 mb-1">الخيارات (حدد الصحيح)</label>
 <?php for($i=0;$i<4;$i++): ?>
 <div class="flex items-center gap-2 mb-2">
 <input type="radio" name="correct_choice" value="<?php echo $i; ?>" <?php echo $i===0?'checked':''; ?> class="text-primary">
 <input type="text" name="choices[]" placeholder="الخيار <?php echo chr(65+$i); ?>" required
 class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm bg-bg focus:ring-2 focus:ring-indigo-300">
 </div>
 <?php endfor; ?>
 </div>
 <div class="mb-3">
 <label class="block text-xs font-bold text-slate-600 mb-1">النقاط</label>
 <input type="number" name="points" value="1" min="0.5" step="0.5" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-bg">
 </div>
 <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-xl font-bold hover:bg-primary transition text-sm">
 <i class="fas fa-plus me-1"></i> إضافة السؤال
 </button>
 </form>

 <!-- True/False Form -->
 <form method="POST" id="form-tf" class="tab-content">
 <input type="hidden" name="action" value="add_question">
 <input type="hidden" name="q_type" value="true_false">
 <div class="mb-3">
 <label class="block text-xs font-bold text-slate-600 mb-1">نص السؤال *</label>
 <textarea name="question_text" required rows="3" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 bg-bg text-sm resize-none focus:ring-2 focus:ring-indigo-300" placeholder="اكتب العبارة للحكم عليها..."></textarea>
 </div>
 <div class="mb-3">
 <label class="block text-xs font-bold text-slate-600 mb-1">الإجابة الصحيحة</label>
 <div class="flex gap-3">
 <label class="flex-1 flex items-center gap-2 p-3 bg-bg border border-primary rounded-xl cursor-pointer">
 <input type="radio" name="correct_tf" value="true" checked class="text-primary">
 <span class="font-bold text-primary">✓ صحيح</span>
 </label>
 <label class="flex-1 flex items-center gap-2 p-3 bg-bg border border-primary rounded-xl cursor-pointer">
 <input type="radio" name="correct_tf" value="false" class="text-primary">
 <span class="font-bold text-primary">✗ خطأ</span>
 </label>
 </div>
 </div>
 <div class="mb-3">
 <label class="block text-xs font-bold text-slate-600 mb-1">النقاط</label>
 <input type="number" name="points" value="1" min="0.5" step="0.5" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-bg">
 </div>
 <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-xl font-bold hover:bg-primary transition text-sm">
 <i class="fas fa-plus me-1"></i> إضافة السؤال
 </button>
 </form>

 <!-- Essay Form -->
 <form method="POST" id="form-essay" class="tab-content">
 <input type="hidden" name="action" value="add_question">
 <input type="hidden" name="q_type" value="essay">
 <div class="mb-3">
 <label class="block text-xs font-bold text-slate-600 mb-1">نص السؤال *</label>
 <textarea name="question_text" required rows="4" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 bg-bg text-sm resize-none focus:ring-2 focus:ring-indigo-300" placeholder="اكتب السؤال المقالي..."></textarea>
 </div>
 <div class="bg-bg border border-primary rounded-xl p-3 mb-3 text-xs text-primary">
 <i class="fas fa-info-circle me-1"></i>
 أسئلة المقال لا تُصحَّح تلقائياً — ستراجعها وتضع الدرجة يدوياً.
 </div>
 <div class="mb-3">
 <label class="block text-xs font-bold text-slate-600 mb-1">النقاط القصوى</label>
 <input type="number" name="points" value="5" min="1" step="1" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-bg">
 </div>
 <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-xl font-bold hover:bg-primary transition text-sm">
 <i class="fas fa-plus me-1"></i> إضافة السؤال
 </button>
 </form>
 </div>
 </div>
 </div>

 <!-- Done Button -->
 <?php if(count($questions) > 0): ?>
 <div class="mt-6 flex justify-center">
 <a href="manage_exam.php" class="bg-primary text-white px-10 py-3.5 rounded-xl font-bold hover:bg-primary transition shadow-md shadow-sm flex items-center gap-2 text-base">
 <i class="fas fa-check-double"></i> تم — حفظ الامتحان
 </a>
 </div>
 <?php endif; ?>
</div>

<script>
function switchTab(tab, btn) {
 document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
 document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
 btn.classList.add('active');
 document.getElementById('form-' + tab).classList.add('active');
}
</script>

<?php require_once '../includes/footer.php'; ?>
