<?php
require_once __DIR__ . '/Model.php';

class User extends Model {
 protected string $table = 'users';

 // اتأكد من بيانات الدخول (اليوزر نيم والباسورد)
 public function authenticate(string $username, string $password): ?array {
 $user = $this->findOneBy('username', $username);
 if ($user && password_verify($password, $user['password'])) {
 return $user;
 }
 return null;
 }

 // هات كل الطلبة اللي في كلية معينة وممكن تفلتر بالاسم
 public function getStudentsByCollege(int $collegeId, string $search = ''): array {
 $sql = "SELECT * FROM {$this->table} WHERE role = 'student' AND college_id = :cid";
 $params = [':cid' => $collegeId];
 
 if (!empty($search)) {
 $sql .= " AND (username ILIKE :search OR full_name ILIKE :search)";
 $params[':search'] = "%{$search}%";
 }
 $sql .= " ORDER BY full_name ASC";
 
 $stmt = $this->db->prepare($sql);
 $stmt->execute($params);
 return $stmt->fetchAll();
 }
 
 // هات بيانات الطالب وكل تفاصيله الأكاديمية (من جدولين)
 public function getStudentWithDetails(int $id): ?array {
 $sql = "SELECT u.*, sd.national_id, sd.major, sd.level, sd.enrollment_status, sd.gpa 
 FROM {$this->table} u 
 LEFT JOIN students sd ON u.id = sd.user_id 
 WHERE u.id = :id AND u.role = 'student' LIMIT 1";
 $stmt = $this->db->prepare($sql);
 $stmt->execute([':id' => $id]);
 $res = $stmt->fetch();
 return $res ?: null;
 }
}
