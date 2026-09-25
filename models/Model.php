<?php
// الكلاس الأساسي لكل الموديلز اللي في السيستم
require_once __DIR__ . '/../config/database.php';

abstract class Model {
 protected PDO $db;
 protected string $table;

 public function __construct() {
 $this->db = Database::getConnection();
 }

 public function findAll(array $conditions = [], string $orderBy = 'id ASC'): array {
 $sql = "SELECT * FROM {$this->table}";
 $params = [];
 
 if (!empty($conditions)) {
 $clauses = [];
 foreach ($conditions as $key => $value) {
 // بنحدد نوع المقارنة اللي هنمشي بيها
 $operator = '=';
 $placeholderKey = str_replace('.', '_', $key); // بننضف أسماء الكولومز عشان الـ PDO
 
 if (is_array($value) && count($value) === 2 && in_array(strtoupper($value[0]), ['!=', '>', '<', '>=', '<=', 'LIKE', 'ILIKE', 'IN'])) {
 $operator = strtoupper($value[0]);
 $val = $value[1];
 
 if ($operator === 'IN' && is_array($val)) {
 $placeholders = implode(',', array_fill(0, count($val), '?'));
 $clauses[] = "$key IN ($placeholders)";
 foreach($val as $v) $params[] = $v;
 continue;
 } else {
 $params[$placeholderKey] = $val;
 }
 } else {
 $params[$placeholderKey] = $value;
 }
 $clauses[] = "$key $operator :$placeholderKey";
 }
 if (!empty($clauses)) {
 $sql .= " WHERE " . implode(' AND ', $clauses);
 }
 }
 
 if ($orderBy) {
 $sql .= " ORDER BY $orderBy";
 }

 $stmt = $this->db->prepare($sql);
 
 // بنربط المتغيرات بالـ SQL عشان الـ SQL Injection والسرعة
 if (array_keys($params) !== range(0, count($params) - 1)) {
 $stmt->execute($params);
 } else {
 $stmt->execute($params);
 }
 
 return $stmt->fetchAll();
 }

 public function findById(int $id): ?array {
 $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
 $stmt->execute([':id' => $id]);
 $result = $stmt->fetch();
 return $result ?: null;
 }

 public function findOneBy(string $column, $value): ?array {
 $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE $column = :val LIMIT 1");
 $stmt->execute([':val' => $value]);
 $result = $stmt->fetch();
 return $result ?: null;
 }

 public function insert(array $data): int {
 $columns = implode(', ', array_keys($data));
 $placeholders = ':' . implode(', :', array_keys($data));
 $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders) RETURNING id";
 
 $stmt = $this->db->prepare($sql);
 $stmt->execute($data);
 return (int)$stmt->fetchColumn();
 }

 public function update(int $id, array $data): bool {
 if (empty($data)) return false;
 
 $sets = [];
 foreach (array_keys($data) as $key) {
 $sets[] = "$key = :$key";
 }
 
 $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = :id";
 $data['id'] = $id;
 
 $stmt = $this->db->prepare($sql);
 return $stmt->execute($data);
 }

 public function delete(int $id): bool {
 $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
 return $stmt->execute([':id' => $id]);
 }
 
 public function count(array $conditions = []): int {
 $sql = "SELECT COUNT(*) FROM {$this->table}";
 $params = [];
 
 if (!empty($conditions)) {
 $clauses = [];
 foreach ($conditions as $key => $value) {
 // مقارنة عادية (يساوي)
 $placeholderKey = str_replace('.', '_', $key);
 $clauses[] = "$key = :$placeholderKey";
 $params[$placeholderKey] = $value;
 }
 $sql .= " WHERE " . implode(' AND ', $clauses);
 }
 $stmt = $this->db->prepare($sql);
 $stmt->execute($params);
 return (int)$stmt->fetchColumn();
 }
}
