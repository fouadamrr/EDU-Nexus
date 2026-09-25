<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Student.php';

class ProfileController {
 
     public function getProfileData(int $target_id, int $user_id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.id as u_id, u.username, u.full_name, u.role, u.college_id as u_college_id, 
                   u.status, u.profile_pic, u.phone as global_phone, u.national_id as global_nid,
                   s.user_id, s.major, s.level, s.enrollment_status, s.gpa, s.nomination_card,
                   s.address, s.birth_date, s.phone as s_phone, s.national_id as s_nid,
                   c.name as college_name 
            FROM users u 
            LEFT JOIN students s ON u.id = s.user_id 
            LEFT JOIN colleges c ON (s.college_id = c.id OR u.college_id = c.id)
            WHERE u.id = ?
        ");
        $stmt->execute([$target_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            // Unify data across tables
            $data['phone'] = !empty($data['global_phone']) ? $data['global_phone'] : ($data['s_phone'] ?? 'غير مسجل');
            $data['national_id'] = !empty($data['global_nid']) ? $data['global_nid'] : ($data['s_nid'] ?? 'غير مسجل');
            // Ensure ID is the correct user ID
            $data['id'] = $data['u_id'];
        }
        
        return $data ?: null;
    }

 public function handleProfileUpload(array $files, int $target_id): ?string {
 if (isset($files['profile_pic']) && $files['profile_pic']['error'] === UPLOAD_ERR_OK) {
 $upload_dir = 'uploads/';
 if (!file_exists($upload_dir)) {
 mkdir($upload_dir, 0777, true);
 }

 $file = $files['profile_pic'];
 $filename = basename($file['name']);
 $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

 // Supported formats check
 $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
 if (!in_array($extension, $allowed_extensions)) {
     return "<div class='bg-red-500 p-4 mb-4 text-white rounded-xl shadow-lg'>
         <i class='fas fa-exclamation-triangle mr-2'></i>
         عذراً، صيغة الملف (." . $extension . ") غير مدعومة. 
         <br><small>يرجى رفع صورة بصيغة JPG أو PNG. (صيغة iPhone HEIC غير مدعومة حالياً).</small>
     </div>";
 }

 // Basic sanitization
 $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
 $target_path = $upload_dir . time() . '_' . $filename;

 if (move_uploaded_file($file['tmp_name'], $target_path)) {
 $userModel = new User();
 $userModel->update($target_id, ['profile_pic' => $target_path]);
 return "<div class='bg-accent p-4 mb-4 text-white'>تم تحديث الصورة الشخصية بنجاح!</div>";
 }
 }
 return null;
 }

 public function handleAddressUpdate(string $new_address, int $target_id): string {
 $studentModel = new Student();
 $existing = $studentModel->findOneBy('user_id', $target_id);
 if ($existing) {
 $studentModel->update($existing['id'], ['address' => htmlspecialchars($new_address)]);
 } else {
 $studentModel->insert(['user_id' => $target_id, 'address' => htmlspecialchars($new_address)]);
 }
 return "<div class='bg-accent p-4 mb-4 text-white'>تم تحديث العنوان!</div>";
 }
}
