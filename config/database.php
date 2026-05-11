<?php

class Database {
    private $host = "localhost";
    private $port = "3308";
    private $dbname = "ipos_db";
    private $username = "root";
    private $password = "root";   // ← set your MySQL root password here if needed

    public $conn;

    public function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->dbname}",
                $this->username,
                $this->password
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }

        return $this->conn;
    }
}