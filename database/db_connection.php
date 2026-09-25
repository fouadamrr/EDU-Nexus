<?php
/**
 * EDU Nexus — PostgreSQL PDO Connection (Singleton)
 */

require_once __DIR__ . '/config.php';

if (!function_exists('get_pdo')) {
function get_pdo(): PDO {
 static $pdo = null;
 if ($pdo === null) {
 $dsn = sprintf(
 'pgsql:host=%s;port=%s;dbname=%s',
 DB_HOST, DB_PORT, DB_NAME
 );
 try {
 $pdo = new PDO($dsn, DB_USER, DB_PASS, [
 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES => false,
 ]);
 // Ensure UTF-8 communication
 $pdo->exec("SET client_encoding = 'UTF8'");
 } catch (PDOException $e) {
 error_log('[EDU Nexus DB] Connection failed: ' . $e->getMessage());
 die('<h2 style="color:red;font-family:sans-serif;">Database connection failed. Please check database/config.php and ensure PostgreSQL is running.</h2>
 <pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
 }
 }
 return $pdo;
}
}
