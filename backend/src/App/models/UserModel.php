<?php
namespace App\Models;

use App\db;

class UserModel
{
    private $db;

    public function __construct(db $database)
    {
        $this->db = $database;
    }

    // Get user by username
    public function getUserByUsername($username)
    {
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // Get user by ID
    public function getUserById($userId)
    {
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :userId');
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // Update user password
    public function updateUserPassword($userId, $newPassword)
    {
        $pdo = $this->db->getPDO();
        $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :userId');
        $stmt->bindParam(':password', $newPassword);
        $stmt->bindParam(':userId', $userId);
        return $stmt->execute();
    }
}
