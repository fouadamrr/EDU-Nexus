<?php
/**
 * EDU Nexus — PostgreSQL Database Configuration
 * Reads values from .env file securely.
 */

// Simple .env parser
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '5432');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'edu_nexus');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'postgres');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: 'postgres');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8');
