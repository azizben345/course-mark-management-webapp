<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use App\db;

class AdminService
{
    private $db;

    public function createAdminProfile(PDO $pdo, string $username): array {
        // If you have no extra admin data, you might not need an admin table.
        // This just confirms that the user has been created.
        return [
            'username' => $username,
            'role' => 'admin'
        ];
    }

    public function __construct(db $db) {
        $this->db = $db;
    }

    public function getAllUsers() {
        $pdo = $this->db->getPDO(); 

        $query = "
            SELECT full_name AS name, email, 'admin' AS role FROM admins
            UNION
            SELECT full_name AS name, email, 'advisor' AS role FROM advisors
            UNION
            SELECT full_name AS name, email, 'lecturer' AS role FROM lecturers
            UNION
            SELECT full_name AS name, email, 'student' AS role FROM students
        ";

        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
