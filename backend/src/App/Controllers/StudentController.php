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

            error_log("DEBUG: getStudentById called with userId: " . $userId);

            $userSql = "SELECT username FROM users WHERE id = :userId";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $userStmt->execute();
            $userResult = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($userResult) {
                error_log("DEBUG: User found in 'users' table. Username: " . $userResult['username']);
            } else {
                error_log("DEBUG: No user found in 'users' table for userId: " . $userId);
            }

            if (!$userResult || empty($userResult['username'])) {
                return false;
            }

            $username = $userResult['username'];

            error_log("DEBUG: Retrieved username from 'users' table: " . $username);

            $studentSql = "SELECT student_id, matric_no, username, student_name, email, pin, status
                           FROM students
                           WHERE username = :username";
            $studentStmt = $pdo->prepare($studentSql);
            $studentStmt->bindParam(':username', $username);
            $studentStmt->execute();
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                error_log("DEBUG: Student profile found for username: " . $username . ". Data: " . json_encode($student));
            } else {
                error_log("DEBUG: No student profile found in 'students' table for username: " . $username);
            }

            return $student;

        } catch (PDOException $e) {
            error_log("ERROR: PDOException in getStudentById: " . $e->getMessage());
            throw new RuntimeException("Database error fetching student profile: " . $e->getMessage(), 0, $e);
        }
    }

    public function getStudentEnrollments(int $userId){
        try{
            $pdo = $this->database->getPDO();

            error_log("DEBUG: getEnrollment called with userId: " . $userId);

            $userMatricSql = "SELECT s.matric_no FROM users u JOIN students s ON u.username = s.username WHERE u.id = :userId";
            $userMatricStmt = $pdo->prepare($userMatricSql);
            $userMatricStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $userMatricStmt->execute();
            $studentMatricNo = $userMatricStmt->fetchColumn();

            if (!$studentMatricNo) {
                error_log("DEBUG: No student matric_no found for userId: " . $userId);
                return [];
            }

            error_log("DEBUG: Retrieved student_matric_no: " . $studentMatricNo . " for userId: " . $userId);

           $enrollmentSql = "SELECT
                                e.enrollment_id,
                                e.student_matric_no,
                                e.course_code,
                                c.course_name,
                                e.lecturer_id,
                                l.lecturer_name,
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
            $enrollmentStmt->bindParam(':studentMatricNo', $studentMatricNo);
            $enrollmentStmt->execute();

            $enrollments = $enrollmentStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($enrollments) {
                error_log("DEBUG: Enrollments found for matric_no: " . $studentMatricNo . ". Count: " . count($enrollments));
            } else {
                error_log("DEBUG: No enrollments found for matric_no: " . $studentMatricNo);
            }

            return $enrollments;

        }catch (PDOException $e) {
            error_log("ERROR: PDOException in getStudentById: " . $e->getMessage());
            throw new RuntimeException("Database error fetching student profile: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fetches assessment components for a given enrollment and the student's marks.
     * Includes the student's final exam mark for context in the What-If tool.
     *
     * @param int $enrollmentId The enrollment ID.
     * @param int $authenticatedUserId The ID of the authenticated user.
     * @param string $authenticatedUserRole The role of the authenticated user.
     * @return array An associative array containing 'components' (array of component data) and 'your_final_exam_mark'.
     * @throws RuntimeException
     */
    public function getEnrollmentComponentsAndMarks(int $enrollmentId, int $authenticatedUserId, string $authenticatedUserRole): array
    {
        try {
            $pdo = $this->database->getPDO();

            // Authorization check and fetch student's current final exam mark
            $enrollmentCheckSql = "SELECT
                                    e.student_matric_no,
                                    s.username,
                                    u.id as student_user_id,
                                    e.lecturer_id,
                                    ul.id as lecturer_user_id,
                                    e.course_code,
                                    e.final_exam_mark as your_final_exam_mark
                                FROM enrollments e
                                JOIN students s ON e.student_matric_no = s.matric_no
                                JOIN users u ON s.username = u.username
                                LEFT JOIN lecturers l ON e.lecturer_id = l.lecturer_id
                                LEFT JOIN users ul ON l.username = ul.username
                                WHERE e.enrollment_id = :enrollmentId";
            $enrollmentCheckStmt = $pdo->prepare($enrollmentCheckSql);
            $enrollmentCheckStmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $enrollmentCheckStmt->execute();
            $enrollmentInfo = $enrollmentCheckStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrollmentInfo) {
                throw new RuntimeException('Enrollment not found.', 404);
            }

            $isStudent = ($authenticatedUserId === (int)$enrollmentInfo['student_user_id']);
            $isAdmin = ($authenticatedUserRole === 'admin');
            $isLecturer = ($authenticatedUserRole === 'lecturer' && $authenticatedUserId === (int)$enrollmentInfo['lecturer_user_id']);

            if (!$isStudent && !$isAdmin && !$isLecturer) {
                throw new RuntimeException('Unauthorized: Cannot view marks for this enrollment.', 403);
            }

            $yourFinalExamMark = $enrollmentInfo['your_final_exam_mark'];


            $sql = "SELECT
                        ac.component_id,
                        ac.component_name,
                        ac.max_mark,
                        am.mark_obtained
                    FROM assessment_components ac
                    JOIN enrollments e ON ac.course_code = e.course_code AND ac.lecturer_id = e.lecturer_id
                    LEFT JOIN assessment_marks am ON e.enrollment_id = am.enrollment_id AND ac.component_id = am.component_id
                    WHERE e.enrollment_id = :enrollmentId
                    ORDER BY ac.component_name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $stmt->execute();
            $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'components' => $components,
                'your_final_exam_mark' => $yourFinalExamMark !== null ? (float)$yourFinalExamMark : null
            ];

        } catch (PDOException $e) {
            error_log("ERROR: PDOException in getEnrollmentComponentsAndMarks: " . $e->getMessage());
            throw new RuntimeException("Database error fetching enrollment components and marks: " . $e->getMessage(), 500, $e);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log("ERROR: Unexpected error in getEnrollmentComponentsAndMarks: " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred: " . $e->getMessage(), 500, $e);
        }
    }


    /**
     * Get aggregated assessment marks for an enrollment, including student's mark and class average.
     * This method fetches all component marks and calculates class averages for a given enrollment.
     *
     * @param int $enrollmentId The enrollment ID.
     * @param int $authenticatedUserId The ID of the authenticated user.
     * @param string $authenticatedUserRole The role of the authenticated user.
     * @return array Contains student's marks and class averages for components, total CA, and final total.
     * @throws RuntimeException
     */
    public function getEnrollmentComparisonData(int $enrollmentId, int $authenticatedUserId, string $authenticatedUserRole): array
    {
        try {
            $pdo = $this->database->getPDO();

            // Authorization check (similar to getEnrollmentComponentsAndMarks)
            $enrollmentCheckSql = "SELECT
                                    e.student_matric_no,
                                    s.username,
                                    u.id as student_user_id,
                                    e.lecturer_id,
                                    ul.id as lecturer_user_id,
                                    e.course_code
                                FROM enrollments e
                                JOIN students s ON e.student_matric_no = s.matric_no
                                JOIN users u ON s.username = u.username
                                LEFT JOIN lecturers l ON e.lecturer_id = l.lecturer_id
                                LEFT JOIN users ul ON l.username = ul.username
                                WHERE e.enrollment_id = :enrollmentId";
            $enrollmentCheckStmt = $pdo->prepare($enrollmentCheckSql);
            $enrollmentCheckStmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $enrollmentCheckStmt->execute();
            $enrollmentInfo = $enrollmentCheckStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrollmentInfo) {
                throw new RuntimeException('Enrollment not found.', 404);
            }

            $isStudent = ($authenticatedUserId === (int)$enrollmentInfo['student_user_id']);
            $isAdmin = ($authenticatedUserRole === 'admin');
            $isLecturer = ($authenticatedUserRole === 'lecturer' && $authenticatedUserId === (int)$enrollmentInfo['lecturer_user_id']);

            if (!$isStudent && !$isAdmin && !$isLecturer) {
                throw new RuntimeException('Unauthorized: Cannot view comparison data for this enrollment.', 403);
            }

            $courseCode = $enrollmentInfo['course_code'];
            $lecturerId = $enrollmentInfo['lecturer_id'];
            $studentMatricNo = $enrollmentInfo['student_matric_no'];

            // 1. Get all assessment components for this course/lecturer
            $componentsSql = "SELECT
                                ac.component_id,
                                ac.component_name,
                                ac.max_mark
                              FROM assessment_components ac
                              WHERE ac.course_code = :courseCode AND ac.lecturer_id = :lecturerId
                              ORDER BY ac.component_name ASC";
            $componentsStmt = $pdo->prepare($componentsSql);
            $componentsStmt->bindParam(':courseCode', $courseCode);
            $componentsStmt->bindParam(':lecturerId', $lecturerId, PDO::PARAM_INT);
            $componentsStmt->execute();
            $components = $componentsStmt->fetchAll(PDO::FETCH_ASSOC);

            // If no components, return early
            if (empty($components)) {
                return [
                    'components' => [],
                    'your_total_ca' => null,
                    'your_final_exam_mark' => null,
                    'your_final_total' => null,
                    'class_average_total_ca' => null,
                    'class_average_final_exam_mark' => null,
                    'class_average_final_total' => null,
                ];
            }

            // 2. Get marks for ALL students in this enrollment's course (for class average)
            // This needs to link through enrollments to get all students for this course/lecturer
            $allStudentsMarksSql = "SELECT
                                        e.enrollment_id,
                                        e.student_matric_no,
                                        am.component_id,
                                        am.mark_obtained,
                                        e.final_exam_mark,
                                        e.total_ca,
                                        e.final_total
                                    FROM enrollments e
                                    LEFT JOIN assessment_marks am ON e.enrollment_id = am.enrollment_id
                                    WHERE e.course_code = :courseCode AND e.lecturer_id = :lecturerId";
            $allStudentsMarksStmt = $pdo->prepare($allStudentsMarksSql);
            $allStudentsMarksStmt->bindParam(':courseCode', $courseCode);
            $allStudentsMarksStmt->bindParam(':lecturerId', $lecturerId, PDO::PARAM_INT);
            $allStudentsMarksStmt->execute();
            $allMarks = $allStudentsMarksStmt->fetchAll(PDO::FETCH_ASSOC);

            $studentComponentMarks = []; // Store your specific student's component marks
            $classComponentTotals = []; // Sum of marks for each component across class
            $classComponentCounts = []; // Count of students who have marks for each component
            $allStudentFinalTotals = []; // Array of all students' final_total marks
            $allStudentTotalCAs = []; // Array of all students' total_ca marks
            $allStudentFinalExamMarks = []; // Array of all students' final_exam_marks

            // Initialize totals and counts for class averages
            foreach ($components as $comp) {
                $classComponentTotals[$comp['component_id']] = 0;
                $classComponentCounts[$comp['component_id']] = 0;
            }

            // Process all fetched marks to populate structures
            $processedEnrollments = []; // To ensure each enrollment's totals are added only once
            foreach ($allMarks as $mark) {
                if ($mark['student_matric_no'] === $studentMatricNo) {
                    $studentComponentMarks[$mark['component_id']] = $mark['mark_obtained'];
                }

                if ($mark['mark_obtained'] !== null) {
                    $classComponentTotals[$mark['component_id']] += (float)$mark['mark_obtained'];
                    $classComponentCounts[$mark['component_id']]++;
                }

                // Collect overall totals and final exam marks per unique enrollment_id
                if (!isset($processedEnrollments[$mark['enrollment_id']])) {
                    if ($mark['final_total'] !== null) {
                        $allStudentFinalTotals[] = (float)$mark['final_total'];
                    }
                     if ($mark['total_ca'] !== null) {
                        $allStudentTotalCAs[] = (float)$mark['total_ca'];
                    }
                    if ($mark['final_exam_mark'] !== null) {
                        $allStudentFinalExamMarks[] = (float)$mark['final_exam_mark'];
                    }
                    $processedEnrollments[$mark['enrollment_id']] = true;
                }
            }

            $formattedComponents = [];
            foreach ($components as $comp) {
                $classAverage = ($classComponentCounts[$comp['component_id']] > 0)
                                ? ($classComponentTotals[$comp['component_id']] / $classComponentCounts[$comp['component_id']])
                                : null;
                $formattedComponents[] = [
                    'component_id' => $comp['component_id'],
                    'component_name' => $comp['component_name'],
                    'max_mark' => (float)$comp['max_mark'],
                    'your_mark' => array_key_exists($comp['component_id'], $studentComponentMarks) ? (float)$studentComponentMarks[$comp['component_id']] : null,
                    'class_average' => $classAverage
                ];
            }

            // Calculate overall averages
            $yourEnrollmentDetails = $pdo->prepare("SELECT final_exam_mark, total_ca, final_total FROM enrollments WHERE enrollment_id = :enrollmentId");
            $yourEnrollmentDetails->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $yourEnrollmentDetails->execute();
            $yourDetails = $yourEnrollmentDetails->fetch(PDO::FETCH_ASSOC);

            $classAverageTotalCA = count($allStudentTotalCAs) > 0 ? array_sum($allStudentTotalCAs) / count($allStudentTotalCAs) : null;
            $classAverageFinalExamMark = count($allStudentFinalExamMarks) > 0 ? array_sum($allStudentFinalExamMarks) / count($allStudentFinalExamMarks) : null;
            $classAverageFinalTotal = count($allStudentFinalTotals) > 0 ? array_sum($allStudentFinalTotals) / count($allStudentFinalTotals) : null;


            return [
                'components' => $formattedComponents,
                'your_total_ca' => $yourDetails ? (float)$yourDetails['total_ca'] : null,
                'your_final_exam_mark' => $yourDetails ? (float)$yourDetails['final_exam_mark'] : null,
                'your_final_total' => $yourDetails ? (float)$yourDetails['final_total'] : null,
                'class_average_total_ca' => $classAverageTotalCA,
                'class_average_final_exam_mark' => $classAverageFinalExamMark,
                'class_average_final_total' => $classAverageFinalTotal,
            ];

        } catch (PDOException $e) {
            error_log("ERROR: PDOException in getEnrollmentComparisonData: " . $e->getMessage());
            throw new RuntimeException("Database error fetching comparison data: " . $e->getMessage(), 500, $e);
        } catch (RuntimeException $e) {
            throw $e; // Re-throw with custom status code
        } catch (\Throwable $e) {
            error_log("ERROR: Unexpected error in getEnrollmentComparisonData: " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred: " . $e->getMessage(), 500, $e);
        }
    }


    /**
     * Get a student's rank and percentile within their class for a specific enrollment.
     *
     * @param int $enrollmentId The enrollment ID.
     * @param int $authenticatedUserId The ID of the authenticated user.
     * @param string $authenticatedUserRole The role of the authenticated user.
     * @return array Contains student's rank, percentile, total students, and class average.
     * @throws RuntimeException
     */
    public function getStudentClassRank(int $enrollmentId, int $authenticatedUserId, string $authenticatedUserRole): array
    {
        try {
            $pdo = $this->database->getPDO();

            // Authorization check (similar to other methods)
            $enrollmentCheckSql = "SELECT
                                    e.student_matric_no,
                                    s.username,
                                    u.id as student_user_id,
                                    e.lecturer_id,
                                    ul.id as lecturer_user_id,
                                    e.course_code,
                                    e.academic_year,
                                    e.final_total AS your_final_total -- Get your student's final total directly
                                FROM enrollments e
                                JOIN students s ON e.student_matric_no = s.matric_no
                                JOIN users u ON s.username = u.username
                                LEFT JOIN lecturers l ON e.lecturer_id = l.lecturer_id
                                LEFT JOIN users ul ON l.username = ul.username
                                WHERE e.enrollment_id = :enrollmentId";
            $enrollmentCheckStmt = $pdo->prepare($enrollmentCheckSql);
            $enrollmentCheckStmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $enrollmentCheckStmt->execute();
            $enrollmentInfo = $enrollmentCheckStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrollmentInfo) {
                throw new RuntimeException('Enrollment not found.', 404);
            }

            $isStudent = ($authenticatedUserId === (int)$enrollmentInfo['student_user_id']);
            $isAdmin = ($authenticatedUserRole === 'admin');
            $isLecturer = ($authenticatedUserRole === 'lecturer' && $authenticatedUserId === (int)$enrollmentInfo['lecturer_user_id']);

            if (!$isStudent && !$isAdmin && !$isLecturer) {
                throw new RuntimeException('Unauthorized: Cannot view class rank data for this enrollment.', 403);
            }

            $courseCode = $enrollmentInfo['course_code'];
            $lecturerId = $enrollmentInfo['lecturer_id'];
            $studentMatricNo = $enrollmentInfo['student_matric_no'];
            $yourFinalTotal = $enrollmentInfo['your_final_total']; // Your student's final total

            // Fetch all final totals for students in this course for ranking
            // Only consider students who have a non-NULL final_total
            $allFinalTotalsSql = "SELECT
                                    e.final_total
                                FROM enrollments e
                                WHERE e.course_code = :courseCode AND e.lecturer_id = :lecturerId
                                  AND e.final_total IS NOT NULL
                                ORDER BY e.final_total DESC"; // Order by mark for ranking
            $allFinalTotalsStmt = $pdo->prepare($allFinalTotalsSql);
            $allFinalTotalsStmt->bindParam(':courseCode', $courseCode);
            $allFinalTotalsStmt->bindParam(':lecturerId', $lecturerId, PDO::PARAM_INT);
            $allFinalTotalsStmt->execute();
            $allFinalTotals = $allFinalTotalsStmt->fetchAll(PDO::FETCH_COLUMN, 0); // Get just the final_total values

            // Calculate class average
            $classAverageFinalTotal = null;
            if (!empty($allFinalTotals)) {
                $classAverageFinalTotal = array_sum($allFinalTotals) / count($allFinalTotals);
            }

            // Calculate rank and percentile
            $yourRank = null;
            $yourPercentile = null;
            $totalStudentsWithMarks = count($allFinalTotals);

            if ($yourFinalTotal !== null && $totalStudentsWithMarks > 0) {
                // Find your rank: count how many students scored higher or equal to you
                // If marks are identical, they share the same rank (dense rank)
                $rank = 1;
                foreach ($allFinalTotals as $mark) {
                    if ($mark > $yourFinalTotal) {
                        $rank++;
                    }
                }
                $yourRank = $rank;

                // Calculate percentile: (Number of students below you / Total students with marks) * 100
                // For simplified percentile: (Number of students with score <= yours / Total students) * 100
                // Let's use simpler: (count of students *at or below* your score) / total students * 100
                $countAtOrBelow = 0;
                foreach($allFinalTotals as $mark) {
                    if ($mark <= $yourFinalTotal) {
                        $countAtOrBelow++;
                    }
                }
                $yourPercentile = ($countAtOrBelow / $totalStudentsWithMarks) * 100;

            }

            return [
                'your_final_total' => $yourFinalTotal,
                'your_rank' => $yourRank,
                'total_students' => $totalStudentsWithMarks,
                'your_percentile' => $yourPercentile,
                'class_average_final_total' => $classAverageFinalTotal
            ];

        } catch (PDOException $e) {
            error_log("ERROR: PDOException in getStudentClassRank: " . $e->getMessage());
            throw new RuntimeException("Database error fetching class rank data: " . $e->getMessage(), 500, $e);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log("ERROR: Unexpected error in getStudentClassRank: " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred: " . $e->getMessage(), 500, $e);
        }
    }


    /**
     * Calculates projected marks and grade based on hypothetical inputs for outstanding components.
     *
     * @param int $enrollmentId The enrollment ID for which to perform the what-if analysis.
     * @param array $hypotheticalComponentMarks An associative array of component_id => mark.
     * @param float|null $hypotheticalFinalExamMark Hypothetical final exam mark.
     * @param int $authenticatedUserId The ID of the authenticated user.
     * @param string $authenticatedUserRole The role of the authenticated user.
     * @return array Contains projected total CA, final exam mark, final total, and grade.
     * @throws RuntimeException
     */
    public function calculatePerformanceExpectationMarks(
        int $enrollmentId,
        array $hypotheticalComponentMarks,
        ?float $hypotheticalFinalExamMark,
        int $authenticatedUserId,
        string $authenticatedUserRole
    ): array {
        try {
            $pdo = $this->database->getPDO();

            // Authorization check: Ensure the authenticated user is the student, lecturer, or admin for this enrollment
            $enrollmentCheckSql = "SELECT
                                    e.student_matric_no,
                                    s.username,
                                    u.id as student_user_id,
                                    e.lecturer_id,
                                    ul.id as lecturer_user_id,
                                    e.final_exam_mark as current_final_exam_mark,
                                    e.total_ca as current_total_ca,
                                    e.final_total as current_final_total
                                FROM enrollments e
                                JOIN students s ON e.student_matric_no = s.matric_no
                                JOIN users u ON s.username = u.username
                                LEFT JOIN lecturers l ON e.lecturer_id = l.lecturer_id
                                LEFT JOIN users ul ON l.username = ul.username
                                WHERE e.enrollment_id = :enrollmentId";
            $enrollmentCheckStmt = $pdo->prepare($enrollmentCheckSql);
            $enrollmentCheckStmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $enrollmentCheckStmt->execute();
            $enrollmentInfo = $enrollmentCheckStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrollmentInfo) {
                throw new RuntimeException('Enrollment not found.', 404);
            }

            $isStudent = ($authenticatedUserId === (int)$enrollmentInfo['student_user_id']);
            $isAdmin = ($authenticatedUserRole === 'admin');
            $isLecturer = ($authenticatedUserRole === 'lecturer' && $authenticatedUserId === (int)$enrollmentInfo['lecturer_user_id']);

            if (!$isStudent && !$isAdmin && !$isLecturer) {
                throw new RuntimeException('Unauthorized: Cannot perform performance expectation analysis for this enrollment.', 403);
            }

            $studentMatricNo = $enrollmentInfo['student_matric_no'];
            $lecturerId = $enrollmentInfo['lecturer_id'];

            // Fetch all assessment components for this course AND the student's existing marks
            $sql = "SELECT
                        ac.component_id,
                        ac.component_name,
                        ac.max_mark,
                        am.mark_obtained -- student's actual mark
                    FROM assessment_components ac
                    JOIN enrollments e ON ac.course_code = e.course_code AND ac.lecturer_id = e.lecturer_id
                    LEFT JOIN assessment_marks am ON e.enrollment_id = am.enrollment_id AND ac.component_id = am.component_id
                    WHERE e.enrollment_id = :enrollmentId
                    ORDER BY ac.component_name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
            $stmt->execute();
            $componentsAndMarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($componentsAndMarks)) {
                return [
                    'projected_total_ca' => null,
                    'projected_final_exam_mark' => null,
                    'projected_final_total' => null,
                    'projected_grade' => null,
                    'message' => 'No assessment components defined for this course.'
                ];
            }


            $projectedTotalCA = 0;
            $totalCAMaxMark = 0;
            $finalExamMaxMark = 30;

            // Calculate projected Continuous Assessment (CA) total
            foreach ($componentsAndMarks as $component) {
                $componentId = (int)$component['component_id'];
                $maxMark = (float)$component['max_mark'];
                $totalCAMaxMark += $maxMark;

                if ($component['mark_obtained'] !== null) {
                    $projectedTotalCA += (float)$component['mark_obtained'];
                } elseif (isset($hypotheticalComponentMarks[$componentId]) && $hypotheticalComponentMarks[$componentId] !== '') {
                    $hypoMark = (float)$hypotheticalComponentMarks[$componentId];
                    $projectedTotalCA += max(0, min($hypoMark, $maxMark));
                }
            }

            $projectedTotalCA = max(0, min($projectedTotalCA, 70));


            // Determine projected Final Exam mark
            $projectedFinalExamMark = null;
            if ($enrollmentInfo['current_final_exam_mark'] !== null) {
                $projectedFinalExamMark = (float)$enrollmentInfo['current_final_exam_mark'];
            } elseif ($hypotheticalFinalExamMark !== null && $hypotheticalFinalExamMark !== '') {
                $hypoFinalExam = (float)$hypotheticalFinalExamMark;
                $projectedFinalExamMark = max(0, min($hypoFinalExam, $finalExamMaxMark));
            }

            // Calculate projected overall final total
            $projectedFinalTotal = null;
            if ($projectedTotalCA !== null && $projectedFinalExamMark !== null) {
                $projectedFinalTotal = $projectedTotalCA + $projectedFinalExamMark;
                $projectedFinalTotal = max(0, min($projectedFinalTotal, 100));
            }


            // Determine projected grade
            $projectedGrade = null;
            if ($projectedFinalTotal !== null) {
                if ($projectedFinalTotal >= 85) {
                    $projectedGrade = 'A';
                } elseif ($projectedFinalTotal >= 75) {
                    $projectedGrade = 'B';
                } elseif ($projectedFinalTotal >= 65) {
                    $projectedGrade = 'C';
                } elseif ($projectedFinalTotal >= 50) {
                    $projectedGrade = 'D';
                } else {
                    $projectedGrade = 'F';
                }
            }

            return [
                'projected_total_ca' => $projectedTotalCA,
                'projected_final_exam_mark' => $projectedFinalExamMark,
                'projected_final_total' => $projectedFinalTotal,
                'projected_grade' => $projectedGrade,
            ];

        } catch (PDOException $e) {
            error_log("ERROR: PDOException in calculatePerformanceExpectationMarks: " . $e->getMessage());
            throw new RuntimeException("Database error during performance expectation calculation: " . $e->getMessage(), 500, $e);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log("ERROR: Unexpected error in calculatePerformanceExpectationMarks: " . $e->getMessage());
            throw new RuntimeException("An unexpected error occurred during performance expectation calculation: " . $e->getMessage(), 500, $e);
        }
    }
}
