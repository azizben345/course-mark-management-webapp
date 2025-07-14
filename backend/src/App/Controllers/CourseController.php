<?php
namespace App\Controllers;

use App\db;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CourseController {
    private $database;

    public function __construct($database) {
        $this->database = $database;
    }

    public function addCourse(Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);

    // Ensure the necessary data exists
    if (!isset($data['course_code'], $data['course_name'], $data['lecturer_id'])) {
        $response->getBody()->write(json_encode(['error' => 'Missing required fields']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Get the PDO instance from the database object
    $pdo = $this->database->getPDO();

    // Check if course code already exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = :course_code");
    $checkStmt->execute(['course_code' => $data['course_code']]);
    $courseExists = $checkStmt->fetchColumn();

    if ($courseExists) {
        $response->getBody()->write(json_encode(['error' => 'Course code already exists']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Prepare SQL query to insert new course
    $query = "INSERT INTO courses (course_code, course_name, lecturer_id) VALUES (:course_code, :course_name, :lecturer_id)";
    $stmt = $pdo->prepare($query);  // Prepare the query on the PDO instance

    // Bind parameters
    $stmt->bindParam(':course_code', $data['course_code']);
    $stmt->bindParam(':course_name', $data['course_name']);
    $stmt->bindParam(':lecturer_id', $data['lecturer_id']);

    // Execute the query and handle the response
    if ($stmt->execute()) {
        $response->getBody()->write(json_encode(['message' => 'Course added successfully']));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    } else {
        $response->getBody()->write(json_encode(['error' => 'Failed to add course']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}

}
