<?php
require_once 'includes/header.php';

// التأكد إن جدول الرسائل موجود في الداتا بيز
try {
 $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
 id SERIAL PRIMARY KEY,
 sender_id INT NOT NULL,
 receiver_id INT NOT NULL,
 subject VARCHAR(255) NOT NULL,
 body TEXT,
 is_read BOOLEAN DEFAULT FALSE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 )");
} catch (Exception $e) {
 // Ignore error if table exists or issues
}

$folder = $_GET['folder'] ?? 'inbox';
$action = $_GET['action'] ?? 'list';
$msg_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_msg = '';
$error_msg = '';

// التعامل مع الإجراءات (زي إرسال رسالة أو حذفها)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
 $receiver_id = (int)$_POST['receiver_id'];
 $subject = trim($_POST['subject']);
 $body = trim($_POST['body']);
 
 $can_send = true;
 // بنشوف الخصوصية: الطلاب ميبعتوش لبعض، والعمداء لطلاب كليتهم بس
 if (in_array($role, ['student', 'dean'])) {
 $stmt_check = $pdo->prepare("SELECT role, college_id FROM users WHERE id = ?");
 $stmt_check->execute([$receiver_id]);
 $target = $stmt_check->fetch(PDO::FETCH_ASSOC);
 
 if ($role === 'student' && $target['role'] === 'student') {
 $error_msg = "سياسة الخصوصية: لا يسمح للطلاب بمراسلة الطلاب الآخرين.";
 $can_send = false;
 } elseif ($role === 'dean' && $target['role'] === 'student' && $target['college_id'] != $_SESSION['college_id']) {
 $error_msg = "سياسة الاختصاص: لا يسمح للعميد بمراسلة طلاب الكليات الأخرى.";
 $can_send = false;
 }
 }

 if ($can_send && $receiver_id > 0 && !empty($subject)) {
 try {
 $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)");
 $stmt->execute([$user_id, $receiver_id, $subject, $body]);
 $success_msg = "تم إرسال الرسالة بنجاح!";
 $action = 'list';
 $folder = 'sent';
 } catch (Exception $e) {
 $error_msg = "حدث خطأ أثناء إرسال الرسالة: " . $e->getMessage();
 }
 } elseif ($can_send) {
 $error_msg = "يرجى تعبئة الحقول المطلوبة واختيار المستلم.";
 }
}

if ($action === 'delete' && $msg_id > 0) {
 try {
 $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
 $stmt->execute([$msg_id, $user_id, $user_id]);
 $success_msg = "تم حذف الرسالة.";
 $action = 'list';
 } catch (Exception $e) {}
}

// بنجيب المستخدمين المتاحين عشان تبعتلم رسايل
$all_users = [];
if ($action === 'compose') {
 try {
 $sql = "SELECT id, full_name, role, college_id FROM users WHERE id != ?";
 $params = [$user_id];
 
 if ($role === 'student') {
 $sql .= " AND role != 'student'";
 } elseif ($role === 'dean') {
 $sql .= " AND (role != 'student' OR college_id = ?)";
 $params[] = $_SESSION['college_id'];
 }
 
 $sql .= " ORDER BY role, full_name";
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {}
}

$folders = [
 'inbox' => ['title' => 'صندوق الوارد', 'icon' => 'fa-inbox'],
 'sent' => ['title' => 'الرسائل المرسلة', 'icon' => 'fa-paper-plane'],
];

$current_folder = $folders[$folder] ?? $folders['inbox'];

// بنجيب لستة الرسايل من الداتا بيز
$messages = [];
if ($action === 'list') {
 try {
 if ($folder === 'inbox') {
 $stmt = $pdo->prepare("SELECT m.*, u.full_name as other_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? ORDER BY m.created_at DESC");
 } else {
 $stmt = $pdo->prepare("SELECT m.*, u.full_name as other_name FROM messages m JOIN users u ON m.receiver_id = u.id WHERE m.sender_id = ? ORDER BY m.created_at DESC");
 }
 $stmt->execute([$user_id]);
 $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {}
}

// عرض تفاصيل الرسالة
$view_msg = null;
if ($action === 'view' && $msg_id > 0) {
 try {
 $stmt = $pdo->prepare("SELECT m.*, u1.full_name as sender_name, u2.full_name as receiver_name 
 FROM messages m 
 JOIN users u1 ON m.sender_id = u1.id 
 JOIN users u2 ON m.receiver_id = u2.id 
 WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)");
 $stmt->execute([$msg_id, $user_id, $user_id]);
 $view_msg = $stmt->fetch(PDO::FETCH_ASSOC);
 
 // Mark as read if receiver
 if ($view_msg && $view_msg['receiver_id'] == $user_id && !$view_msg['is_read']) {
 $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE id = ?")->execute([$msg_id]);
 $view_msg['is_read'] = true;
 }
 } catch (Exception $e) {}
}
?>

<div class="max-w-6xl mx-auto space-y-6 animate-fade-in-up">
 <!-- عنوان الصفحة والبحث -->
 <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 bg-bg rounded-xl flex items-center justify-center text-primary">
 <i class="fas <?php echo $current_folder['icon'] ?? 'fa-envelope-open-text'; ?> text-xl"></i>
 </div>
 <div>
 <h2 class="text-2xl font-bold text-secondary">البريد الداخلي - <?php echo $current_folder['title'] ?? 'قراءة رسالة'; ?></h2>
 <p class="text-sm text-slate-500 mt-0.5">إدارة المراسلات الداخلية والتعميمات</p>
 </div>
 </div>
 <div class="flex gap-2">
 <?php if ($action !== 'list' || $folder !== 'inbox'): ?>
 <a href="messages.php?folder=inbox" class="bg-bg text-slate-700 px-5 py-2.5 rounded-xl hover:bg-slate-200 transition-all shadow-sm font-bold flex items-center gap-2">
 <i class="fas fa-inbox"></i> الوارد
 </a>
 <?php endif; ?>
 <a href="messages.php?action=compose" class="bg-primary text-white px-5 py-2.5 rounded-xl hover:bg-primary transition-all shadow-md flex items-center gap-2 font-bold">
 <i class="fas fa-plus"></i> رسالة جديدة
 </a>
 </div>
 </div>

 <?php if ($success_msg): ?>
 <div class="bg-bg border border-primary text-primary px-4 py-3 rounded-lg flex items-center gap-2 font-bold">
 <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
 </div>
 <?php endif; ?>
 <?php if ($error_msg): ?>
 <div class="bg-bg border border-primary text-primary px-4 py-3 rounded-lg flex items-center gap-2 font-bold">
 <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
 </div>
 <?php endif; ?>

 <!-- الجزء الخاص بالرسايل (عرض أو كتابة) -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 
 <?php if ($action === 'compose'): ?>
 <!-- فورم كتابة رسالة جديدة -->
 <form method="POST" class="p-8 space-y-6">
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">إلى (المستلم):</label>
 <select name="receiver_id" required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-primary bg-white">
 <option value="">-- اختر المستلم --</option>
 <?php foreach($all_users as $u): ?>
 <option value="<?php echo $u['id']; ?>">
 <?php echo htmlspecialchars($u['full_name']) . ' (' . htmlspecialchars($u['role']) . ')'; ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">الموضوع:</label>
 <input type="text" name="subject" required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-primary bg-white" placeholder="أدخل موضوع الرسالة...">
 </div>
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">محتوى الرسالة:</label>
 <textarea name="body" rows="6" class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-primary bg-white" placeholder="اكتب رسالتك هنا..."></textarea>
 </div>
 <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
 <a href="messages.php" class="bg-bg text-slate-600 px-6 py-2.5 rounded-xl font-bold hover:bg-slate-200 transition">إلغاء</a>
 <button type="submit" name="send_message" class="bg-primary text-white px-8 py-2.5 rounded-xl font-bold hover:bg-primary transition shadow">
 <i class="fas fa-paper-plane ml-2"></i> إرسال
 </button>
 </div>
 </form>
 
 <?php elseif ($action === 'view' && $view_msg): ?>
 <!-- عرض محتوى الرسالة لما تفتحها -->
 <div class="p-8">
 <div class="flex justify-between items-start mb-6 pb-6 border-b border-slate-100">
 <div>
 <h3 class="text-2xl font-bold text-slate-800 mb-2"><?php echo htmlspecialchars($view_msg['subject']); ?></h3>
 <div class="flex items-center gap-2 text-sm text-slate-600">
 <span class="font-bold text-slate-700">من:</span> <?php echo htmlspecialchars($view_msg['sender_name']); ?>
 <span class="mx-2 text-slate-300">|</span>
 <span class="font-bold text-slate-700">إلى:</span> <?php echo htmlspecialchars($view_msg['receiver_name']); ?>
 <span class="mx-2 text-slate-300">|</span>
 <i class="far fa-clock"></i> <?php echo date('Y-m-d H:i', strtotime($view_msg['created_at'])); ?>
 </div>
 </div>
 <div>
 <a href="messages.php?action=delete&id=<?php echo $view_msg['id']; ?>" onclick="return confirm('تأكيد الحذف؟')" class="text-white bg-bg hover:bg-secondary hover:text-white px-4 py-2 rounded-lg font-bold transition text-sm">
 <i class="fas fa-trash-alt"></i> حذف
 </a>
 </div>
 </div>
 <div class="prose max-w-none text-slate-700 leading-relaxed min-h-[150px] whitespace-pre-wrap flex-grow">
 <?php echo htmlspecialchars($view_msg['body']); ?>
 </div>
 
 <div class="mt-8 pt-6 border-t border-slate-100">
 <a href="messages.php?action=compose&reply_to=<?php echo $view_msg['id']; ?>" class="bg-bg text-slate-700 px-6 py-2 rounded-xl font-bold hover:bg-slate-200 transition inline-flex items-center gap-2 text-sm">
 <i class="fas fa-reply"></i> رد
 </a>
 </div>
 </div>
 
 <?php else: ?>
 <!-- عرض لستة الرسايل (الوارد أو الصادر) -->
 <div class="p-4 border-b border-slate-50 flex items-center justify-between bg-bg/50">
 <div class="text-sm font-bold text-slate-500">
 <?php echo count($messages); ?> رسائل
 </div>
 <div class="flex items-center gap-2">
 <a href="messages.php?folder=<?php echo $folder; ?>" class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-slate-200 text-slate-500 transition"><i class="fas fa-sync-alt"></i></a>
 </div>
 </div>

 <div class="divide-y divide-slate-100">
 <?php if (empty($messages)): ?>
 <div class="p-12 text-center text-slate-500">
 <i class="fas fa-folder-open text-4xl mb-3 text-slate-300"></i>
 <p class="font-bold">لا توجد رسائل في هذا المجلد.</p>
 </div>
 <?php else: ?>
 <?php foreach ($messages as $m): 
 $is_unread = ($folder === 'inbox' && !$m['is_read']);
 ?>
 <a href="messages.php?action=view&id=<?php echo $m['id']; ?>" class="p-4 hover:bg-bg transition-colors flex items-center gap-4 group block <?php echo $is_unread ? 'bg-bg/30' : ''; ?>">
 <div class="w-6 flex-shrink-0 text-slate-300 group-hover:text-primary transition-colors">
 <i class="<?php echo $is_unread ? 'fas fa-envelope text-primary' : 'far fa-envelope-open'; ?>"></i>
 </div>
 <div class="w-48 flex-shrink-0 truncate <?php echo $is_unread ? 'font-black text-primary' : 'font-bold text-slate-700'; ?>">
 <?php echo htmlspecialchars($m['other_name'] ?? 'مستخدم غير معروف'); ?>
 </div>
 <div class="flex-1 overflow-hidden">
 <span class="<?php echo $is_unread ? 'font-bold text-slate-900' : 'font-medium text-slate-800'; ?>">
 <?php echo htmlspecialchars($m['subject']); ?>
 </span>
 <span class="text-slate-400 text-sm mx-2">-</span>
 <span class="text-slate-500 text-sm truncate inline-block max-w-[200px] align-bottom">
 <?php echo htmlspecialchars(mb_substr($m['body'], 0, 50)); ?>...
 </span>
 </div>
 <div class="w-24 text-left text-xs text-slate-400 <?php echo $is_unread ? 'font-bold text-primary' : 'font-medium'; ?>">
 <?php echo date('Y-m-d', strtotime($m['created_at'])); ?>
 </div>
 </a>
 <?php endforeach; ?>
 <?php endif; ?>
 </div>
 <?php endif; ?>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
