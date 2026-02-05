<?php

class Database {
    private $pdo;

    public function __construct($host, $dbname, $username, $password) {
        try {
            $dsn = "mysql:host=$host;port=3306;dbname=$dbname";
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Could not connect to the database: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function prepare($statement, $options = []) {
        return $this->pdo->prepare($statement, $options);
    }

    public function query($statement) {
        return $this->pdo->query($statement);
    }
}
