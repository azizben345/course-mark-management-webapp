<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

class AdminService
{
    public function createAdminProfile(PDO $pdo, string $username): array {
        // If you have no extra admin data, you might not need an admin table.
        // This just confirms that the user has been created.
        return [
            'username' => $username,
            'role' => 'admin'
        ];
    }
}
