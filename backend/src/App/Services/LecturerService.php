<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use InvalidArgumentException;
use RuntimeException;

class LecturerService
{
    public function createLecturerProfile(PDO $pdo, string $username, array $data): array {
    $sql = "INSERT INTO lecturers (user_id, full_name, lecturer_id, email, department, status)
            VALUES (
                (SELECT id FROM users WHERE username = :username),
                :full_name,
                :lecturer_id,
                :email,
                :department,
                :status
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'full_name' => $data['full_name'],
        'lecturer_id' => $data['lecturer_id'],
        'email' => $data['email'],
        'department' => $data['department'],
        'status' => $data['status'] ?? 'active'
    ]);

    return [
        'lecturer_id' => $data['lecturer_id'],
        'full_name' => $data['full_name'],
        'email' => $data['email'],
        'department' => $data['department'],
        'status' => $data['status'] ?? 'active'
    ];
    }
}
