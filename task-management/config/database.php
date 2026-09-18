<?php
/**
 * Database Configuration - Sipway Campus Task Management
 * Change these values according to your WAMP/XAMPP settings
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'sipway_tasks');
define('DB_USER', 'root');
define('DB_PASS', ''); // Empty for default WAMP/XAMPP
define('DB_CHARSET', 'utf8mb4');

/**
 * Create PDO connection
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log the error instead of showing it
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
?>
