<?php
namespace App\Controllers;

use App\db;
use PDO;
use PDOException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class PasswordResetController
{
    private $pdo;

    // Constructor accepting PDO object
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Method for handling password reset
    public function resetPassword(Request $request, Response $response, $args)
    {
        // Get POST data
        $data = json_decode($request->getBody()->getContents(), true);

        // Check if the required fields exist in the request data
        if (!isset($data['current_password'], $data['new_password'], $data['user_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Missing required fields.'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $currentPassword = $data['current_password'];
        $newPassword = $data['new_password'];
        $userId = $data['user_id'];  // For example, the user ID is also sent

        try {
            // Check current password (retrieve the hashed password from the DB)
            $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // If the user exists and current password matches
            if ($user && password_verify($currentPassword, $user['password'])) {
                // Hash the new password and update in the database
                $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT);

                // Update the password in the database
                $updateStmt = $this->pdo->prepare("UPDATE users SET password = :new_password WHERE id = :user_id");
                $updateStmt->bindParam(':new_password', $hashedNewPassword);
                $updateStmt->bindParam(':user_id', $userId);
                $updateStmt->execute();

                // Send success response
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Password updated successfully'
                ]));
                return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            } else {
                // Old password is incorrect
                $response->getBody()->write(json_encode([
                    'error' => 'Old password is incorrect'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        } catch (PDOException $e) {
            // Handle any errors (database issues, etc.)
            $response->getBody()->write(json_encode([
                'error' => 'An error occurred: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
