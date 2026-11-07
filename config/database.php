<?php
// Load environment variables DARI FILE .env DEMO (bukan Demo Company)
$env_file = __DIR__ . '/../.env';  // /var/www/demo.atmopro.my.id/.env

if (file_exists($env_file) && is_readable($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $envParts = explode('=', $line, 2);
            if (count($envParts) == 2) {
                $envKey = trim($envParts[0]);
                $envValue = trim($envParts[1]);
                putenv("$envKey=$envValue");
                $_ENV[$envKey] = $envValue;
            }
        }
    }
}

// Define constants from environment (dengan fallback DEMO)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('BASE_URL', getenv('BASE_URL') ?: 'https://demo.atmopro.my.id');
define('BASE_PATH', dirname(__DIR__));

date_default_timezone_set(getenv('TIMEZONE') ?: 'Asia/Jakarta');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch(PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}
