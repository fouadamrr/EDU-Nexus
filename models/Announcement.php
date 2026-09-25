<?php
require_once __DIR__ . '/Model.php';

class Announcement extends Model {
 protected string $table = 'announcements';

 /**
 * هات آخر الإعلانات لكل رتبة وكلية
 */
 public function getLatest(string $role, ?int $collegeId = null, int $limit = 5): array {
 // بنشوف لو الرتبة مسموح لها تشوف الإعلان، ولو هو تبع كلية معينة ولا لكل الكليات
 
 $sql = "SELECT * FROM {$this->table} 
 WHERE (target_roles ILIKE :role OR target_roles IS NULL OR target_roles = '')";
 
 if ($collegeId !== null && $collegeId > 0) {
 $sql .= " AND (college_id = :cid OR college_id IS NULL)";
 } else {
 $sql .= " AND college_id IS NULL";
 }
 
 $sql .= " ORDER BY date DESC, id DESC LIMIT :limit";
 
 $stmt = $this->db->prepare($sql);
 $stmt->bindValue(':role', "%$role%", PDO::PARAM_STR);
 if ($collegeId !== null && $collegeId > 0) {
 $stmt->bindValue(':cid', $collegeId, PDO::PARAM_INT);
 }
 $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
 $stmt->execute();
 
 return $stmt->fetchAll();
 }
}
