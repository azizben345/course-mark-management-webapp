<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use InvalidArgumentException;

class AdvisorService
{
    public function createAdvisorProfile(PDO $pdo, string $username, array $data): array
    {
        $required = ['full_name', 'advisor_id', 'email', 'department', 'advisee_quota'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Missing required advisor field: '$field'");
            }
        }


        $sql = "INSERT INTO advisors (user_id, full_name, advisor_id, email, department, advisee_quota, status)
                VALUES (
                    (SELECT id FROM users WHERE username = :username),
                    :full_name,
                    :advisor_id,
                    :email,
                    :department,
                    :advisee_quota,
                    :status
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'full_name' => $data['full_name'],
            'advisor_id' => $data['advisor_id'],
            'email' => $data['email'],
            'department' => $data['department'],
            'advisee_quota' => $data['advisee_quota'],

            'status' => $data['status'] ?? 'active'
        ]);

        return [
            'advisor_id' => $data['advisor_id'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'department' => $data['department'],
            'advisee_quota' => $data['advisee_quota'],

            'status' => $data['status'] ?? 'active'
        ];
    }
}
