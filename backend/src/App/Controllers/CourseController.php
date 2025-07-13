<?php
namespace App\Controllers;
use PDOException;

class CourseController {
    protected $db;

    public function __construct($db) {
        $this->db = $db->getPDO(); // ✅ Ensure it's a PDO object
    }

    public function addCourse($request, $response, $args) {
        $data = $request->getParsedBody();
        error_log("DATA: " . json_encode($data)); 

        $sql = "INSERT INTO courses (course_code, course_name, lecturer_id)
                VALUES (:course_code, :course_name, :lecturer_id)";

        error_log("SQL: " . $sql);
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':course_code', $data['course_code']);
            $stmt->bindParam(':course_name', $data['course_name']);
            $stmt->bindParam(':lecturer_id', $data['lecturer_id']);

            $stmt->execute();

            $response->getBody()->write(json_encode(['message' => 'Course added successfully']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (PDOException $e) {
            error_log('Add Course Error: ' . $e->getMessage()); // Log to PHP error log
            error_log('Submitted data: ' . print_r($data, true)); // Log the submitted data

             $response->getBody()->write(json_encode([
              'error' => $e->getMessage(),
              'debug' => $data
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);

        }
    }
}
