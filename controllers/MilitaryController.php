<?php
require_once __DIR__ . '/../db.php';

class MilitaryController {

 public function getAvailableSessions(): array {
 global $pdo;
 try {
 // Show all 'open' sessions that haven't ended yet
 $stmt = $pdo->prepare("SELECT * FROM military_sessions WHERE status = 'open' AND end_date >= CURRENT_DATE ORDER BY start_date ASC");
 $stmt->execute();
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (Exception $e) {
 return [];
 }
 }

 public function getStudentRegistration(int $user_id): ?array {
 global $pdo;
 try {
 $stmt = $pdo->prepare("
 SELECT r.*, s.title, s.start_date, s.end_date, c.name as college_name, st.national_id
 FROM military_registrations r
 JOIN military_sessions s ON r.session_id = s.id
 LEFT JOIN users u ON r.user_id = u.id
 LEFT JOIN colleges c ON u.college_id = c.id
 LEFT JOIN students st ON r.user_id = st.user_id
 WHERE r.user_id = ?
 ORDER BY r.created_at DESC
 LIMIT 1
 ");
 $stmt->execute([$user_id]);
 return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
 } catch (Exception $e) {
 return null;
 }
 }

 public function handleRegistration(int $user_id, array $post, array $files): string {
 global $pdo;
 $session_id = (int)($post['session_id'] ?? 0);

 if (!$session_id) return '<div class="bg-primary text-white p-4 rounded-xl mb-4">يرجى اختيار دورة صالحة.</div>';

 // Check if already registered
 if ($this->getStudentRegistration($user_id)) {
 return '<div class="bg-primary text-white p-4 rounded-xl mb-4">أنت مسجل بالفعل في دورة.</div>';
 }

 // Upload files
 $uploadDir = __DIR__ . '/../uploads/military/';
 if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

 $idCardPath = $this->uploadFile($files['id_card'] ?? null, $uploadDir, "id_{$user_id}_" . time());
 $uniCardPath = $this->uploadFile($files['uni_card'] ?? null, $uploadDir, "uni_{$user_id}_" . time());

 if (!$idCardPath || !$uniCardPath) {
 return '<div class="bg-primary text-white p-4 rounded-xl mb-4">فشل في رفع الملفات المطلوبة. تأكد من جودة الصور.</div>';
 }

 try {
 $stmt = $pdo->prepare("INSERT INTO military_registrations (user_id, session_id, id_card_path, uni_card_path, status) VALUES (?, ?, ?, ?, 'pending')");
 $stmt->execute([$user_id, $session_id, $idCardPath, $uniCardPath]);
 return '<div class="bg-primary text-white p-4 rounded-xl mb-4 font-bold flex items-center gap-2"><i class="fas fa-check-circle"></i> تم إرسال طلب التسجيل بنجاح.</div>';
 } catch (Exception $e) {
 return '<div class="bg-primary text-white p-4 rounded-xl mb-4">خطأ في قاعدة البيانات: ' . $e->getMessage() . '</div>';
 }
 }

 private function uploadFile(?array $file, string $dir, string $newName): ?string {
 if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
 
 $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
 $finalName = $newName . '.' . $ext;
 $target = $dir . $finalName;

 if (move_uploaded_file($file['tmp_name'], $target)) {
 return 'uploads/military/' . $finalName;
 }
 return null;
 }

 // --- ADMIN METHODS ---

 public function getAllSessions(): array {
 global $pdo;
 return $pdo->query("SELECT * FROM military_sessions ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
 }

 public function createSession(array $data): bool {
 global $pdo;
 // Explicitly set status = 'open' so the session is visible to students immediately
 $stmt = $pdo->prepare("INSERT INTO military_sessions (title, start_date, end_date, capacity, status) VALUES (?, ?, ?, ?, 'open')");
 return $stmt->execute([$data['title'], $data['start_date'], $data['end_date'], $data['capacity'] ?? 100]);
 }

 public function getRegistrations(): array {
 global $pdo;
 $stmt = $pdo->prepare("
 SELECT r.*, u.full_name, u.username, s.title as session_title, c.name as college_name, st.national_id
 FROM military_registrations r
 JOIN users u ON r.user_id = u.id
 JOIN military_sessions s ON r.session_id = s.id
 LEFT JOIN colleges c ON u.college_id = c.id
 LEFT JOIN students st ON u.id = st.user_id
 ORDER BY r.created_at DESC
 ");
 $stmt->execute();
 return $stmt->fetchAll(PDO::FETCH_ASSOC);
 }

 public function updateRegistrationStatus(int $reg_id, string $status): bool {
 global $pdo;
 $stmt = $pdo->prepare("UPDATE military_registrations SET status = ? WHERE id = ?");
 return $stmt->execute([$status, $reg_id]);
 }
}
