<?php
/**
 * Database Configuration & Connection
 * PDO singleton with UTF-8, exception mode
 */

class Database {
    private static $instance = null;
    private $connection;

    private $db_name = 'u527069138_attendify';
    private $username = 'u527069138_attendify';
    private $password = '$#84Zxu7J';
    private $charset = 'utf8mb4';

    private function __construct() {
        // Use remote IP for local development, and localhost for the production server
        $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
        $host = $isLocal ? '193.203.184.197' : 'localhost';

        try {
            $dsn = "mysql:host={$host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed',
                'error'   => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Get singleton database instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}
