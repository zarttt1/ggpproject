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
}
?>