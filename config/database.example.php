<?php

class Database {
    private $host;
    private $database;
    private $username;
    private $password;
    private $charset;

    public $lastError = null;

    public function __construct() {
        $this->host = $this->env('WHERE2GO_DB_HOST', 'localhost');
        $this->database = $this->env('WHERE2GO_DB_NAME', 'where2go');
        $this->username = $this->env('WHERE2GO_DB_USER', 'root');
        $this->password = $this->env('WHERE2GO_DB_PASS', '');
        $this->charset = $this->env('WHERE2GO_DB_CHARSET', 'utf8mb4');
    }

    public function getConnection() {
        $this->lastError = null;

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $this->host,
            $this->database,
            $this->charset
        );

        try {
            return new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $this->lastError = 'Unable to connect to the Where2Go database.';
            error_log('Where2Go database connection failed: ' . $e->getMessage());
            return null;
        }
    }

    private function env($key, $default) {
        $value = getenv($key);

        if ($value === false || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    }
}
