<?php
/**
 * Database Connection Handler
 * Provides PDO database connection with error handling
 */

class Database {
    private static $instance = null;
    private $pdo = null;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Load database configuration from environment
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->dbname = getenv('DB_NAME') ?: 'livonto_db';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        $this->charset = getenv('DB_CHARSET') ?: 'utf8mb4';
        
        $port = getenv('DB_PORT') ?: '3306';
        
        // Build DSN
        $dsn = "mysql:host={$this->host};port={$port};dbname={$this->dbname};charset={$this->charset}";
        
        // PDO options
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Use native prepared statements
            PDO::ATTR_PERSISTENT         => false, // Don't use persistent connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // Log error
            error_log("Database connection failed: " . $e->getMessage());
            // Don't die() here - let the calling code handle the error
            // This allows JSON responses to work properly
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get singleton instance of Database
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Execute a query and return results
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return PDOStatement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Execute a query and return all results as array
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return array
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return single row
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return array|false
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Execute a query and return single value
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return mixed
     */
    public function fetchValue($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Execute INSERT/UPDATE/DELETE and return affected rows
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return int Number of affected rows
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Get last inserted ID
     * @return string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin transaction
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     * @return bool
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    /**
     * Check if in transaction
     * @return bool
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
}

/**
 * Global helper function to get database instance
 * @return Database
 */
function db() {
    return Database::getInstance();
}

/**
 * Global helper function to get PDO connection
 * @return PDO
 */
function db_connection() {
    return Database::getInstance()->getConnection();
}

