<?php
// app/models/User.php

class User {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT user_id, username, password, role, status FROM users WHERE username = :username");
        
        $stmt->execute(['username' => $username]);
        
        return $stmt->fetch(); 
    }

    public function create($username, $password) {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (user_id, username, password, role, status) VALUES (UUID(), :user, :pass, 'user', 'pending')");
            return $stmt->execute(['user' => $username, 'pass' => $hashed]);
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                return "Username already exists.";
            }
            return "Error: " . $e->getMessage();
        }
    }
}
?>