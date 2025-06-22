<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use InvalidArgumentException;

class StudentService{
    public function createStudentProfile(PDO $pdo, string $username, array $studentData): array
    {
        // --- 1. Basic Input Validation for Student Profile Data ---
        $requiredFields = ['matric_no', 'student_name', 'email', 'pin'];
        foreach ($requiredFields as $field) {
            if (empty($studentData[$field])) {
                throw new InvalidArgumentException("Missing required student profile field: '$field'.");
            }
        }

        try {
            $matricNo = $studentData['matric_no'];
            $studentName = $studentData['student_name'];
            $email = $studentData['email'];
            $pin = $studentData['pin']; // Assuming this needs hashing too.

            // Hash the PIN securely (as per your schema VARCHAR(255) suggests hashing)
            $hashedPin = password_hash($pin, PASSWORD_DEFAULT);
            if ($hashedPin === false) {
                 throw new \RuntimeException("Failed to hash PIN for student profile.");
            }

            $status = 'Active'; // Default status for new students

            // --- 2. Insert into 'students' table ---
            $sql = "INSERT INTO students (matric_no, username, student_name, email, pin, status)
                           VALUES (:matric_no, :username, :student_name, :email, :pin, :status)";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':matric_no', $matricNo);
            $stmt->bindParam(':username', $username); // Link to the user via username
            $stmt->bindParam(':student_name', $studentName);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':pin', $hashedPin);
            $stmt->bindParam(':status', $status);
            $stmt->execute();

            $newStudentId = (int)$pdo->lastInsertId(); // Get the auto-generated ID for the student profile

            return [
                'student_id' => $newStudentId,
                'matric_no' => $matricNo,
                'message' => 'Student profile created successfully.'
            ];

        } catch (PDOException $e) {
            // Check for unique constraint violation on matric_no
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'matric_no')) {
                throw new \RuntimeException("Student registration failed: Matric number already exists.", 409, $e); // 409 Conflict
            }
            // Re-throw other PDO exceptions as a general runtime exception
            throw new \RuntimeException("Database error creating student profile: " . $e->getMessage(), 0, $e);
        }
    }
}