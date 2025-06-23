<?php
// src/controllers/StudentController.php
namespace App\Controllers;

use App\db;
use PDO;
use PDOException;
use RuntimeException;

class StudentController {
    public function __construct(private db $database){}

    public function getAll() : array {
        try {
            $pdo = $this->database->getPDO();
            $stmt = $pdo->query('SELECT student_id, matric_no, username, student_name, email, pin, status FROM students');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to fetch all students: " . $e->getMessage(), 0, $e);
        }
    }

    public function getStudentById(int $userId) {
        try {
            $pdo = $this->database->getPDO();

            // DEBUG LOG: Log the incoming userId
            error_log("DEBUG: getStudentById called with userId: " . $userId);

            // Step 1: Get the username from the 'users' table using the provided userId
            $userSql = "SELECT username FROM users WHERE id = :userId";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $userStmt->execute();
            $userResult = $userStmt->fetch(PDO::FETCH_ASSOC);

            // DEBUG LOG: Log the result of the user lookup
            if ($userResult) {
                error_log("DEBUG: User found in 'users' table. Username: " . $userResult['username']);
            } else {
                error_log("DEBUG: No user found in 'users' table for userId: " . $userId);
            }

            if (!$userResult || empty($userResult['username'])) {
                return false; // No corresponding user, so no student profile can be linked
            }

            $username = $userResult['username']; // This is the username we need to query the 'students' table

            // DEBUG LOG: Log the username obtained from the users table
            error_log("DEBUG: Retrieved username from 'users' table: " . $username);


            // Step 2: Use the retrieved username to get the student's details from the 'students' table
            $studentSql = "SELECT student_id, matric_no, username, student_name, email, pin, status
                           FROM students
                           WHERE username = :username"; // Query the students table using the username (the foreign key)
            $studentStmt = $pdo->prepare($studentSql);
            $studentStmt->bindParam(':username', $username); // Bind the username from the users table
            $studentStmt->execute();
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC); // Fetch a single row

            // DEBUG LOG: Log the result of the student profile lookup
            if ($student) {
                error_log("DEBUG: Student profile found for username: " . $username . ". Data: " . json_encode($student));
            } else {
                error_log("DEBUG: No student profile found in 'students' table for username: " . $username);
            }

            return $student; // Will return false if no student record exists for that username

        } catch (PDOException $e) {
            // DEBUG LOG: Log any PDO exceptions
            error_log("ERROR: PDOException in getStudentById: " . $e->getMessage());
            throw new RuntimeException("Database error fetching student profile: " . $e->getMessage(), 0, $e);
        }
    }

    public function getStudentEnrollments(int $userId){
        try{
            $pdo = $this->database->getPDO();

            // DEBUG LOG: Log the incoming userId
            error_log("DEBUG: getEnrollment called with userId: " . $userId);

            // Step 1: Get the student's matric_no from the users and students tables.
            $userMatricSql = "SELECT s.matric_no FROM users u JOIN students s ON u.username = s.username WHERE u.id = :userId";
            $userMatricStmt = $pdo->prepare($userMatricSql);
            $userMatricStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $userMatricStmt->execute();
            $studentMatricNo = $userMatricStmt->fetchColumn(); // Use fetchColumn to get just the value

            if (!$studentMatricNo) {
                error_log("DEBUG: No student matric_no found for userId: " . $userId);
                return []; // No student profile found for this user ID
            }

            error_log("DEBUG: Retrieved student_matric_no: " . $studentMatricNo . " for userId: " . $userId);

            //Step 3: Use the retrieved matric no from the students table
           $enrollmentSql = "SELECT
                                e.enrollment_id,
                                e.student_matric_no,
                                e.course_code,
                                c.course_name,      -- Joined from courses table
                                e.lecturer_id,
                                l.lecturer_name,    -- Joined from lecturers table
                                e.academic_year,
                                e.final_exam_mark,
                                e.total_ca,
                                e.final_total
                            FROM enrollments e
                            JOIN courses c ON e.course_code = c.course_code
                            JOIN lecturers l ON e.lecturer_id = l.lecturer_id
                            WHERE e.student_matric_no = :studentMatricNo
                            ORDER BY e.academic_year DESC, c.course_name ASC";
            $enrollmentStmt = $pdo->prepare($enrollmentSql);
            $enrollmentStmt->bindParam(':studentMatricNo', $studentMatricNo); // Bind the username from the users table
            $enrollmentStmt->execute();

            $enrollments = $enrollmentStmt->fetchAll(PDO::FETCH_ASSOC); // Fetch ALL enrollments

            if ($enrollments) {
                error_log("DEBUG: Enrollments found for matric_no: " . $studentMatricNo . ". Count: " . count($enrollments));
            } else {
                error_log("DEBUG: No enrollments found for matric_no: " . $studentMatricNo);
            }

            return $enrollments;

        }catch (PDOException $e) {
            // DEBUG LOG: Log any PDO exceptions
            error_log("ERROR: PDOException in getStudentById: " . $e->getMessage());
            throw new RuntimeException("Database error fetching student profile: " . $e->getMessage(), 0, $e);
        }
    }

    public function getEnrollmentComponentsAndMarks(int $enrollmentId, int $authenticatedUserId, string $authenticatedUserRole): array
    {
        try {
            $pdo = $this->database->getPDO();

            // Step 1: Fetch enrollment details AND associated student_user_id AND lecturer_id
            $enrollmentCheckSql = "SELECT
                                    e.student_matric_no,
                                    s.username,
                                    u.id as student_user_id,
                                    e.lecturer_id,
                                    ul.id as lecturer_user_id -- Assuming lecturers also have user accounts
                                FROM enrollments e
                                JOIN students s ON e.student_matric_no = s.matric_no
                                JOIN users u ON s.username = u.username
                                LEFT JOIN lecturers l ON e.lecturer_id = l.lecturer_id
                                LEFT JOIN users ul ON l.username = ul.username -- Link lecturer to user table
                                WHERE e.enrollment_id = :enrollmentId";
            $enrollmentCheckStmt = $pdo->prepare($enrollmentCheckSql);
            $enrollmentCheckStmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $enrollmentCheckStmt->execute();
            $enrollmentInfo = $enrollmentCheckStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrollmentInfo) {
                throw new RuntimeException('Enrollment not found.', 404); // Use a custom code for clarity
            }

            // Step 2: Perform Authorization Check
            $isStudent = ($authenticatedUserId === (int)$enrollmentInfo['student_user_id']);
            $isAdmin = ($authenticatedUserRole === 'admin');
            $isLecturer = ($authenticatedUserRole === 'lecturer' && $authenticatedUserId === (int)$enrollmentInfo['lecturer_user_id']);


            if (!$isStudent && !$isAdmin && !$isLecturer) {
                throw new RuntimeException('Unauthorized: Cannot view marks for this enrollment.', 403); // Use a custom code for clarity
            }

            // Step 3: Fetch the actual assessment components and marks
            // Make sure to use the correct table name 'assessment_marks'
            $sql = "SELECT
                        ac.component_id,
                        ac.component_name,
                        ac.max_mark,
                        am.mark_obtained -- Corrected table alias to 'am' for assessment_marks
                    FROM assessment_components ac
                    JOIN enrollments e ON ac.course_code = e.course_code AND ac.lecturer_id = e.lecturer_id
                    LEFT JOIN assessment_marks am ON e.enrollment_id = am.enrollment_id AND ac.component_id = am.component_id
                    WHERE e.enrollment_id = :enrollmentId
                    ORDER BY ac.component_name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("ERROR: PDOException in getEnrollmentComponentsAndMarks: " . $e->getMessage());
            throw new RuntimeException("Database error fetching enrollment components and marks: " . $e->getMessage(), 500, $e);
        } catch (RuntimeException $e) {
            // Re-throw the RuntimeException with its custom code (404, 403)
            throw $e;
        } catch (\Throwable $e) {
            error_log("ERROR: Unexpected error in getEnrollmentComponentsAndMarks: " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred: " . $e->getMessage(), 500, $e);
        }
    }
}
