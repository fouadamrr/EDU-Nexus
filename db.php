<?php
// تظبيط قاعدة البيانات والاتصال بالـ PDO
require_once __DIR__ . '/database/db_connection.php';

// الاتصال الأساسي والمباشر بقاعدة البيانات
$pdo = get_pdo();

// الكلاس اللي شايل كل الشغل وعمليات الداتا بيز
if (!class_exists('UniversityDB')) {
class UniversityDB
{
 private PDO $pdo;
 private ?int $college_id;

 // بنربط أسماء الجداول عشان لو فيه أي اختلاف في أسماء الداتا بيز
 private array $tableMap = [
 'student_details' => 'students',
 'academic_warnings' => 'academic_warnings',
 'class_schedule' => 'class_schedule',
 'borrowed_books' => 'borrowed_books',
 'books' => 'resources',
 ];

 public function __construct(?int $college_id = null)
 {
 $this->pdo = get_pdo();

 // بنحاول نعرف الكلية لوحدنا من السيشين لو متبعتتش
 if ($college_id !== null) {
 $this->college_id = $college_id;
 } elseif (isset($_SESSION['college_id'])) {
 $this->college_id = (int) $_SESSION['college_id'];
 } else {
 $this->college_id = null;
 }
 }

 // شوية حاجات مساعدة من جوه

 private function table(string $name): string
 {
 return $this->tableMap[$name] ?? $name;
 }

 private function hasCollegeScope(string $table): bool
 {
 $global = ['colleges', 'roles', 'permissions', 'role_permissions'];
 return !in_array($table, $global, true);
 }

 // هاتلي كل اللي في الجدول دا
 public function findAll(string $table, array $conditions = []): array
 {
 $t = $this->table($table);
 $where = [];
 $params = [];

 // بنجيب البيانات بتاعة الكلية اللي اليوزر تابع ليها بس
 if ($this->college_id !== null && $this->hasCollegeScope($t)) {
 $where[] = 'college_id = :_cid';
 $params[':_cid'] = $this->college_id;
 }

 foreach ($conditions as $col => $val) {
 $key = ':' . $col;
 $where[] = '"' . $col . '" = ' . $key;
 $params[$key] = $val;
 }

 $sql = "SELECT * FROM {$t}";
 if ($where) {
 $sql .= ' WHERE ' . implode(' AND ', $where);
 }

 $stmt = $this->pdo->prepare($sql);
 $stmt->execute($params);
 return $stmt->fetchAll();
 }

 // هات لي سطر واحد بس
 public function find(string $table, string $key, mixed $value): ?array
 {
 $t = $this->table($table);
 $where = ['"' . $key . '" = :val'];
 $params = [':val' => $value];

 if ($this->college_id !== null && $this->hasCollegeScope($t)) {
 $where[] = 'college_id = :_cid';
 $params[':_cid'] = $this->college_id;
 }

 $sql = "SELECT * FROM {$t} WHERE " . implode(' AND ', $where) . ' LIMIT 1';
 $stmt = $this->pdo->prepare($sql);
 $stmt->execute($params);
 $row = $stmt->fetch();
 return $row ?: null;
 }

 // دخل سطر جديد في الجدول
 public function insert(string $table, array $data): int
 {
 $t = $this->table($table);

 // بنضيف رقم الكلية لوحده لو نسينا والجدول محتاجه
 if ($this->college_id !== null && $this->hasCollegeScope($t) && !isset($data['college_id'])) {
 $data['college_id'] = $this->college_id;
 }

 // بنشيل الـ id عشان السيريال بتاع الداتا بيز يشتغل براحته
 unset($data['id']);

 $cols = array_keys($data);
 $marks = array_map(fn($c) => ':' . $c, $cols);
 $colList = implode(', ', array_map(fn($c) => '"' . $c . '"', $cols));
 $valList = implode(', ', $marks);

 $sql = "INSERT INTO {$t} ({$colList}) VALUES ({$valList}) RETURNING id";
 $stmt = $this->pdo->prepare($sql);

 $params = [];
 foreach ($data as $k => $v) {
 $params[':' . $k] = $v;
 }
 $stmt->execute($params);

 return (int) $stmt->fetchColumn();
 }

 // عدل سطر كان موجود قبل كدا
 public function update(string $table, int $id, array $newData): bool
 {
 $t = $this->table($table);
 unset($newData['id']);

 if (empty($newData)) {
 return false;
 }

 $sets = [];
 $params = [':_id' => $id];
 foreach ($newData as $k => $v) {
 $key = ':' . $k;
 $sets[] = '"' . $k . '" = ' . $key;
 $params[$key] = $v;
 }

 $sql = "UPDATE {$t} SET " . implode(', ', $sets) . ' WHERE id = :_id';
 $stmt = $this->pdo->prepare($sql);
 $stmt->execute($params);

 return $stmt->rowCount() > 0;
 }

 // امسح سطر بالـ id بتاعه
 public function delete(string $table, int $id): bool
 {
 $t = $this->table($table);
 $stmt = $this->pdo->prepare("DELETE FROM {$t} WHERE id = :id");
 $stmt->execute([':id' => $id]);
 return $stmt->rowCount() > 0;
 }

 // امسح على حسب شروط معينة
 public function deleteWhere(string $table, array $conditions = []): int
 {
 if (empty($conditions)) {
 return 0;
 }

 $t = $this->table($table);
 $where = [];
 $params = [];

 foreach ($conditions as $col => $val) {
 $key = ':' . $col;
 $where[] = '"' . $col . '" = ' . $key;
 $params[$key] = $val;
 }

 $sql = "DELETE FROM {$t} WHERE " . implode(' AND ', $where);
 $stmt = $this->pdo->prepare($sql);
 $stmt->execute($params);

 return $stmt->rowCount();
 }

 // لو حبيت تكتب كويري SQL صريح من دماغك
 public function safeQuery(string $sql, array $params = []): PDOStatement
 {
 $stmt = $this->pdo->prepare($sql);
 if (!empty($params)) {
 $stmt->execute($params);
 }
 return $stmt;
 }

 // دالة زيادة عشان نفضل شغالين مع الأجزاء القديمة من الكود
 public function getTable(string $table): array
 {
 return $this->findAll($table);
 }

 // حاجات زيادة كدا للكليات
 public function getAllColleges(): array
 {
 $stmt = $this->pdo->query('SELECT * FROM colleges ORDER BY id');
 return $stmt->fetchAll();
 }

 public function getCollegeById(int $id): ?array
 {
 $stmt = $this->pdo->prepare('SELECT * FROM colleges WHERE id = :id LIMIT 1');
 $stmt->execute([':id' => $id]);
 $row = $stmt->fetch();
 return $row ?: null;
 }

 // تحكم في الإعدادات من هنا
 public function getSetting(string $key, ?int $college_id = null): ?string
 {
 $cid = $college_id ?? $this->college_id;
 $sql = 'SELECT value FROM settings WHERE key = :key';
 $params = [':key' => $key];

 if ($cid !== null) {
 $sql .= ' AND college_id = :cid';
 $params[':cid'] = $cid;
 } else {
 $sql .= ' AND college_id IS NULL';
 }

 $sql .= ' LIMIT 1';
 $stmt = $this->pdo->prepare($sql);
 $stmt->execute($params);
 $row = $stmt->fetch();
 return $row ? $row['value'] : null;
 }

 public function setSetting(string $key, string $value, ?int $college_id = null): void
 {
 $cid = $college_id ?? $this->college_id;
 $sql = 'INSERT INTO settings (key, value, college_id)
 VALUES (:key, :value, :cid)
 ON CONFLICT (college_id, key)
 DO UPDATE SET value = EXCLUDED.value';
 $stmt = $this->pdo->prepare($sql);
 $stmt->execute([':key' => $key, ':value' => $value, ':cid' => $cid]);
 }

 // بنسجل أي حركة بتحصل في السيستم عشان الرقابة
 public function log(string $action, string $details = '', ?int $user_id = null): void
 {
 $uid = $user_id ?? ($_SESSION['user_id'] ?? null);
 $ip = $_SERVER['REMOTE_ADDR'] ?? null;
 $stmt = $this->pdo->prepare(
 'INSERT INTO logs (user_id, college_id, action, details, ip_address)
 VALUES (:uid, :cid, :action, :details, :ip)'
 );
 $stmt->execute([
 ':uid' => $uid,
 ':cid' => $this->college_id,
 ':action' => $action,
 ':details' => $details,
 ':ip' => $ip,
 ]);
 }
}
}

// بنبدأ السيشين ونجهز أوبجكت الداتا بيز عشان نستخدمه في الموقع كله
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

$college_id = isset($_SESSION['college_id']) ? (int) $_SESSION['college_id'] : null;

/** @var UniversityDB $db */
$db = new UniversityDB($college_id);

/** @var PDO $pdo */