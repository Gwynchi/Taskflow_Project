<?php
// TaskFlow Database Configuration

// Determine environment: local development (XAMPP) vs ByetHost production
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = in_array($httpHost, ['localhost', '127.0.0.1'], true)
    || (strpos($httpHost, 'localhost:') === 0);

if ($isLocal) {
    // Local development settings (XAMPP defaults)
    $host   = 'localhost';
    $dbname = 'taskflow_db';
    $dbuser = 'root';
    $dbpass = '';
} else {
    // ========================================================
    // ByetHost Production Settings
    // You can find these in your VistaPanel (cpanel.byethost.com):
    // --------------------------------------------------------
    // 1. $host   : Go to VistaPanel -> "MySQL Databases", look for "MySQL Host Name"
    //              (Common examples: sql200.byethost8.com, sql308.byethost8.com, or sqlXXX.byetcluster.com)
    // 2. $dbname : Database name you created in VistaPanel (e.g., b8_43230731_taskflow)
    // 3. $dbuser : Your VistaPanel username (e.g., b8_43230731)
    // 4. $dbpass : Your VistaPanel account password (the one you log in with)
    // ========================================================
    $host   = 'sql110.byethost8.com';  // <-- Replace with your MySQL Host Name from VistaPanel
    $dbname = 'b8_43230731_taskflow_db'; // <-- Replace with your created Database Name
    $dbuser = 'b8_43230731';          // <-- Your VistaPanel account username
    $dbpass = 'taskflow123.!?!..'; // <-- Replace with your VistaPanel account password
}

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass, $options);
} catch (PDOException $e) {
    if (function_exists('ob_get_length') && ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage() . '. Please verify config/database.php settings and ensure your database is created in VistaPanel.'
    ], JSON_UNESCAPED_SLASHES);
    exit;
}
