<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use InvalidArgumentException;
use RuntimeException;

class StudentService
{
    public function createStudentProfile(PDO $pdo, string $username, array $studentData): array
    {
        // 1. Validate required fields
        $requiredFields = ['matric_no', 'full_name', 'email', 'year_of_study', 'programme'];
        foreach ($requiredFields as $field) {
            if (empty($studentData[$field])) {
                throw new InvalidArgumentException("Missing required student profile field: '$field'.");
            }
        }

        // 2. Extract fields from data
        $matricNo = $studentData['matric_no'];
        $fullName = $studentData['full_name'];
        $email = $studentData['email'];
        $yearOfStudy = $studentData['year_of_study'];
        $programme = $studentData['programme'];
        $status = 'Active'; // Default status

        try {
            // 3. Insert into `students` table
            $sql = "INSERT INTO students (username, full_name, matric_no, email, year_of_study, programme, status)
                    VALUES (:username, :full_name, :matric_no, :email, :year_of_study, :programme, :status)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':username' => $username,
                ':full_name' => $fullName,
                ':matric_no' => $matricNo,
                ':email' => $email,
                ':year_of_study' => $yearOfStudy,
                ':programme' => $programme,
                ':status' => $status
            ]);

            return [
                'message' => 'Student profile created successfully.',
                'matric_no' => $matricNo,
                'email' => $email
            ];

        } catch (PDOException $e) {
            // Handle duplicate Matric No
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'matric_no')) {
                throw new RuntimeException("Student registration failed: Matric number already exists.", 409, $e);
            }

            throw new RuntimeException("Database error while creating student profile: " . $e->getMessage(), 500, $e);
        }
    }
}
