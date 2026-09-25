<?php
require_once __DIR__ . '/Model.php';

class Book extends Model {
 protected string $table = 'resources'; // Map to resources table as per db.php legacy mapping
}
