<?php
namespace App\Controllers;

use Slim\Psr7\Request;
use Slim\Psr7\Response;
use PDO;
use PDOException;

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
        // Get POST data (assuming you're sending old and new password)
        $data = json_decode($request->getBody()->getContents(), true);

        // Ensure all required data is present
        if (!isset($data['oldPassword'], $data['newPassword'], $data['userId'])) {
            return $response->withJson(['error' => 'Missing parameters'], 400);
        }

        $oldPassword = $data['oldPassword'];
        $newPassword = $data['newPassword'];
        $userId = $data['userId'];

        try {
            // Check current password (you may need to retrieve the hashed password from the DB)
            $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = :userId");
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($oldPassword, $user['password'])) {
                // Passwords match, so proceed with password update
                $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT);

                // Update the password in the database
                $updateStmt = $this->pdo->prepare("UPDATE users SET password = :newPassword WHERE id = :userId");
                $updateStmt->bindParam(':newPassword', $hashedNewPassword);
                $updateStmt->bindParam(':userId', $userId);
                $updateStmt->execute();

                // Send success response
                $response->getBody()->write(json_encode(['success' => true, 'message' => 'Password updated successfully']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            } else {
                // Old password is incorrect
                $response->getBody()->write(json_encode(['error' => 'Old password is incorrect']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        } catch (PDOException $e) {
            // Handle any errors (database issues, etc.)
            $response->getBody()->write(json_encode(['error' => 'An error occurred: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
