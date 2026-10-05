<?php


define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', ''); 
define('DB_CHARSET', '');

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
           
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
?>
