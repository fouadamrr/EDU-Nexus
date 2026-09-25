<?php
// config/database.php
// STRICT PDO POSTGRESQL CONNECTION

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', '5432');
defined('DB_NAME') || define('DB_NAME', 'edu_nexus');
defined('DB_USER') || define('DB_USER', 'postgres');
defined('DB_PASS') || define('DB_PASS', 'postgres');


class Database {
 private static $instance = null;
 private $pdo;

 private function __construct() {
 try {
 $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
 $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES => false,
 ]);
 } catch (PDOException $e) {
 // Log database errors securely instead of echoing to screen
 error_log("Database Connection Error: " . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
 die("Critical system error. Could not connect to the database.");
 }
 }

 public static function getConnection(): PDO {
 if (self::$instance === null) {
 self::$instance = new self();
 }
 return self::$instance->pdo;
 }
}
