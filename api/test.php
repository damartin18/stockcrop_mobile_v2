<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "DB_HOST=" . getenv("DB_HOST") . "<br>";
echo "DB_PORT=" . getenv("DB_PORT") . "<br>";
echo "DB_USER=" . getenv("DB_USER") . "<br>";
echo "DB_NAME=" . getenv("DB_NAME") . "<br>";

require_once 'db_connect.php';

echo "Database connection successful";
?>



