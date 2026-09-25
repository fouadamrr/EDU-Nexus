<?php
// Mock session to simulate a student
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'student';
$_SESSION['full_name'] = 'Test Student';
$_SESSION['college_id'] = 1;

// Include the actual index file
require_once '/var/www/html/finance/index.php';
