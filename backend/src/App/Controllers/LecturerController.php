<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\db;

// require_once __DIR__ . '/../utils/db.php';

return function ($app, $jwtMiddleware) {
    // manage students route
    $app->get('/manage-students/{lecturer_id}', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id'];  // get the lecturer_id from the URL

        // Fetch assessment components for the lecturer's courses
        $db = new db();      
        $pdo = $db->getPDO(); 


        // Get all assessment components for this lecturer
        $stmt_assessments = $pdo->prepare("
            SELECT 
                ac.component_id, 
                ac.component_name, 
                ac.course_code,
                ac.max_mark
            FROM 
                assessment_components ac
            WHERE 
                ac.lecturer_id = :lecturer_id
        ");
        $stmt_assessments->execute(['lecturer_id' => $lecturer_id]);
        $assessments = $stmt_assessments->fetchAll();

        // Fetch students and their marks for each assessment component
        $stmt_students = $pdo->prepare("
            SELECT 
                e.course_code,
                s.matric_no, 
                s.student_name, 
                e.enrollment_id, 
                e.final_exam_mark, 
                e.final_total,
                e.total_ca
            FROM 
                enrollments e
            JOIN 
                students s ON e.student_matric_no = s.matric_no
            WHERE 
                e.lecturer_id = :lecturer_id
        ");
        $stmt_students->execute(['lecturer_id' => $lecturer_id]);
        $students = $stmt_students->fetchAll();

        // Organize assessments by course
        $courses = [];
        foreach ($assessments as $assessment) {
            if (!isset($courses[$assessment['course_code']])) {
                $courses[$assessment['course_code']] = [
                    'course_code' => $assessment['course_code'],
                    'course_name' => $assessment['course_name'],
                    'components' => [],
                    'students' => []
                ];
            }

            $courses[$assessment['course_code']]['components'][] = $assessment;
        }

        // Add students to the relevant courses and calculate total_ca
        foreach ($students as $student) {
            $course_code = $student['course_code'];
            if (isset($courses[$course_code])) {
                $student['marks'] = [];
                $total_ca = 0;  // Variable to accumulate continuous assessment marks

                foreach ($courses[$course_code]['components'] as $assessment) {
                    $stmt_marks = $pdo->prepare("
                        SELECT 
                            am.mark_obtained
                        FROM 
                            assessment_marks am
                        WHERE 
                            am.enrollment_id = :enrollment_id AND am.component_id = :component_id
                    ");
                    $stmt_marks->execute([
                        'enrollment_id' => $student['enrollment_id'],
                        'component_id' => $assessment['component_id']
                    ]);
                    $mark = $stmt_marks->fetch();

                    $mark_obtained = $mark ? $mark['mark_obtained'] : 0;  // Default to 0 if no mark found
                    $total_ca += $mark_obtained;  // Accumulate marks for total_ca

                    // Add the mark to the student's marks array
                    $student['marks'][] = [
                        'component_name' => $assessment['component_name'],
                        'mark_obtained' => $mark_obtained
                    ];
                }

                // Add the final exam mark to calculate final_total
                $final_exam_mark = $student['final_exam_mark'] ?: 0;
                $final_total = $total_ca + $final_exam_mark;

                // Update the total_ca and final_total in the enrollments table
                $stmt_update = $pdo->prepare("
                    UPDATE enrollments
                    SET total_ca = :total_ca, final_total = :final_total
                    WHERE enrollment_id = :enrollment_id
                ");
                $stmt_update->execute([
                    'total_ca' => $total_ca,
                    'final_total' => $final_total,
                    'enrollment_id' => $student['enrollment_id']
                ]);

                // Add the student to the correct course
                $courses[$course_code]['students'][] = $student;
            }
        }

        // Return combined response with courses and students
        $response->getBody()->write(json_encode([
            'courses' => array_values($courses)  // return courses grouped by course_code
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // create enrollment route
    $app->post('/enrollments', function (Request $request, Response $response) {
        $db = new db();      
        $pdo = $db->getPDO(); 

        $data = json_decode($request->getBody()->getContents(), true);
        
        $student_matric_no = $data['student_matric_no'];
        $course_code = $data['course_code'];
        $lecturer_id = $data['lecturer_id'];
        $academic_year = $data['academic_year'];
        $assessment_marks = $data['assessment_marks']; // Array of component_id and marks
        $final_exam_mark = $data['final_exam_mark'];

        // Calculate total assessment marks (sum of marks from components)
        $total_assessment_marks = 0;
        foreach ($assessment_marks as $mark) {
            $total_assessment_marks += $mark['mark_obtained'];
        }

        // Calculate final total
        $final_total = ($total_assessment_marks * 0.7) + ($final_exam_mark * 0.3);

        // Insert enrollment data
        $stmt = $pdo->prepare("
            INSERT INTO enrollments (student_matric_no, course_code, lecturer_id, academic_year, final_exam_mark, total_ca, final_total)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $student_matric_no,
            $course_code,
            $lecturer_id,
            $academic_year,
            $final_exam_mark,
            $total_assessment_marks,
            $final_total
        ]);
        $enrollment_id = $pdo->lastInsertId();  // Get the last inserted enrollment ID

        // Insert marks for each assessment component
        foreach ($assessment_marks as $mark) {
            $stmt = $pdo->prepare("
                INSERT INTO assessment_marks (enrollment_id, component_id, mark_obtained)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $enrollment_id,
                $mark['component_id'],
                $mark['mark_obtained']
            ]);
        }

        // Return a success response
        $response->getBody()->write(json_encode(['message' => 'Enrollment created successfully.']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // update enrollment route [to update final exam mark]
    $app->put('/students/{enrollment_id}', function (Request $request, Response $response, $args) {
        $enrollment_id = $args['enrollment_id'];
        $data = json_decode($request->getBody()->getContents(), true);

        $final_exam_mark = $data['final_exam_mark'] ?? null;

        if (!$final_exam_mark) {
            $response->getBody()->write(json_encode(['error' => 'Final exam mark is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = new db();      
        $pdo = $db->getPDO(); 

        $stmt = $pdo->prepare("
            UPDATE enrollments
            SET final_exam_mark = :final_exam_mark
            WHERE enrollment_id = :enrollment_id
        ");
        $stmt->execute([
            'final_exam_mark' => $final_exam_mark,
            'enrollment_id' => $enrollment_id
        ]);

        // Recalculate the final_total after updating final_exam_mark
        $stmt_recalc = $pdo->prepare("
            UPDATE enrollments
            SET final_total = (SELECT total_ca + :final_exam_mark FROM enrollments WHERE enrollment_id = :enrollment_id)
            WHERE enrollment_id = :enrollment_id
        ");
        $stmt_recalc->execute([
            'final_exam_mark' => $final_exam_mark,
            'enrollment_id' => $enrollment_id
        ]);

        $response->getBody()->write(json_encode(['message' => 'Final exam mark updated successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // delete enrollment route
    $app->delete('/students/{enrollment_id}', function (Request $request, Response $response, $args) {
        $enrollment_id = $args['enrollment_id'];

        $db = new db();      
        $pdo = $db->getPDO(); 

        // Delete the student from the enrollments table
        $stmt = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = :enrollment_id");
        $stmt->execute(['enrollment_id' => $enrollment_id]);

        $response->getBody()->write(json_encode(['message' => 'Student record deleted successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // get courses assigned to a lecturer
    // Route to fetch courses assigned to the lecturer
    $app->get('/lecturer/{lecturer_id}/courses', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id'];

        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("
            SELECT c.course_code, c.course_name
            FROM courses c
            JOIN enrollments e ON c.course_code = e.course_code
            WHERE e.lecturer_id = :lecturer_id
            GROUP BY c.course_code
        ");
        $stmt->execute(['lecturer_id' => $lecturer_id]);
        $courses = $stmt->fetchAll();

        $response->getBody()->write(json_encode($courses));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // route to get or fetch all assessment components based on the lecturer's courses
    $app->get('/lecturer/{lecturer_id}/get-assessment-components', function (Request $request, Response $response, $args) {
        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("
            SELECT ac.component_id, ac.component_name, ac.max_mark, c.course_code, c.course_name,
                (SELECT COUNT(*) FROM assessment_marks am WHERE am.component_id = ac.component_id) AS student_count
            FROM assessment_components ac
            JOIN courses c ON ac.course_code = c.course_code
        ");
        $stmt->execute();
        $components = $stmt->fetchAll();

        // Group by course_code
        $groupedComponents = [];
        foreach ($components as $component) {
            $groupedComponents[$component['course_code']][] = $component;
        }

        $response->getBody()->write(json_encode($groupedComponents));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // route to get assessment components based on component_id
    $app->get('/lecturer/{lecturer_id}/get-assessment-component/{component_id}', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id'];
        $component_id = $args['component_id'];  // Get the specific component_id from the URL

        // Fetch the assessment component details for the specific component_id
        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("
            SELECT ac.component_id, ac.component_name, ac.max_mark, ac.course_code, c.course_name, 
                (SELECT COUNT(*) FROM assessment_marks am WHERE am.component_id = ac.component_id) AS student_count
            FROM assessment_components ac
            JOIN courses c ON ac.course_code = c.course_code
            WHERE ac.lecturer_id = :lecturer_id AND ac.component_id = :component_id
        ");
        $stmt->execute([
            'lecturer_id' => $lecturer_id,
            'component_id' => $component_id
        ]);
        $component = $stmt->fetch();

        if ($component) {
            $response->getBody()->write(json_encode(['component' => $component]));
        } else {
            $response->getBody()->write(json_encode(['error' => 'Component not found']));
            return $response->withStatus(404);
        }

        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // route to get components for student component
    $app->get('/lecturer/{lecturer_id}/assessment-components/{component_id}', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id'];  // Get lecturer_id from URL
        $component_id = $args['component_id'];  // Get component_id from URL

        $db = new db();      
        $pdo = $db->getPDO(); 

        // Get component details
        $stmt_component = $pdo->prepare("
            SELECT * 
            FROM assessment_components 
            WHERE component_id = :component_id AND lecturer_id = :lecturer_id
        ");
        $stmt_component->execute(['component_id' => $component_id, 'lecturer_id' => $lecturer_id]);
        $component = $stmt_component->fetch();

        // Get students enrolled in this component
        $stmt_students = $pdo->prepare("
            SELECT 
                e.enrollment_id,
                e.student_matric_no,
                s.student_name,
                am.mark_id,
                am.mark_obtained
            FROM enrollments e
            JOIN students s ON e.student_matric_no = s.matric_no
            LEFT JOIN assessment_marks am ON e.enrollment_id = am.enrollment_id AND am.component_id = :component_id
            WHERE e.course_code = :course_code
        ");
        $stmt_students->execute(['component_id' => $component_id, 'course_code' => $component['course_code']]);
        $students = $stmt_students->fetchAll();

        // Send data back
        $response->getBody()->write(json_encode([
            'component' => $component,
            'students' => $students
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    // route to create a new assessment component
    $app->post('/lecturer/{lecturer_id}/create-assessment-components', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id'];
        $data = json_decode($request->getBody()->getContents(), true);

        $course_code = $data['course_code'];
        $component_name = $data['component_name'];
        $max_mark = $data['max_mark'];

        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("
            INSERT INTO assessment_components (course_code, lecturer_id, component_name, max_mark)
            VALUES (:course_code, :lecturer_id, :component_name, :max_mark)
        ");
        $stmt->execute([
            'course_code' => $course_code,
            'lecturer_id' => $lecturer_id,
            'component_name' => $component_name,
            'max_mark' => $max_mark
        ]);

        $response->getBody()->write(json_encode(['message' => 'Assessment component created successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // route to update an assessment marks - only mark update
    // Update existing assessment mark
    $app->put('/lecturer/{lecturer_id}/assessment-marks/{component_id}/update-mark/{enrollment_id}', function (Request $request, Response $response, $args) {
        $component_id = $args['component_id'];
        $enrollment_id = $args['enrollment_id'];
        $data = json_decode($request->getBody()->getContents(), true);
        $mark_obtained = $data['mark_obtained'];

        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("UPDATE assessment_marks SET mark_obtained = ? WHERE enrollment_id = ? AND component_id = ?");
        $stmt->execute([$mark_obtained, $enrollment_id, $component_id]);

        $response->getBody()->write(json_encode(['message' => 'Assessment mark updated successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    });
    // Check if the assessment mark exists for a student
    $app->get('/lecturer/{lecturer_id}/assessment-marks/{component_id}/check-mark/{enrollment_id}', function (Request $request, Response $response, $args) {
        $component_id = $args['component_id'];
        $enrollment_id = $args['enrollment_id'];
        
        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("SELECT 1 FROM assessment_marks WHERE enrollment_id = :enrollment_id AND component_id = :component_id LIMIT 1");
        $stmt->execute(['enrollment_id' => $enrollment_id, 'component_id' => $component_id]);
        $exists = $stmt->fetchColumn();

        $response->getBody()->write(json_encode(['exists' => (bool)$exists]));
        return $response->withHeader('Content-Type', 'application/json');
    });
    // Create new assessment mark if no record exists
    $app->post('/lecturer/{lecturer_id}/assessment-marks/{component_id}/create-mark/{enrollment_id}', function (Request $request, Response $response, $args) {
        $component_id = $args['component_id'];
        $enrollment_id = $args['enrollment_id'];
        $data = json_decode($request->getBody()->getContents(), true);
        $mark_obtained = $data['mark_obtained'];

        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("INSERT INTO assessment_marks (enrollment_id, component_id, mark_obtained) VALUES (?, ?, ?)");
        $stmt->execute([$enrollment_id, $component_id, $mark_obtained]);

        $response->getBody()->write(json_encode(['message' => 'Assessment mark created successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // route to update an assessment component
    $app->put('/lecturer/{lecturer_id}/assessment-components/{component_id}/update', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id'];
        $component_id = $args['component_id'];

        // Get data from the request body
        $data = json_decode($request->getBody()->getContents(), true);
        $component_name = $data['component_name'];
        $max_mark = $data['max_mark'];

        // Check if the component belongs to the lecturer
        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM assessment_components
            WHERE component_id = :component_id AND lecturer_id = :lecturer_id
        ");
        $stmt->execute(['component_id' => $component_id, 'lecturer_id' => $lecturer_id]);
        $componentExists = $stmt->fetchColumn();

        if (!$componentExists) {
            $response->getBody()->write(json_encode(['error' => 'Component does not belong to this lecturer']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Update the assessment component
        $stmt_update = $pdo->prepare("
            UPDATE assessment_components
            SET component_name = :component_name, max_mark = :max_mark
            WHERE component_id = :component_id
        ");
        $stmt_update->execute([
            'component_name' => $component_name,
            'max_mark' => $max_mark,
            'component_id' => $component_id
        ]);

        $response->getBody()->write(json_encode(['message' => 'Assessment component updated successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // route to clear all assessment marks for a specific assessment component
    $app->delete('/lecturer/{lecturer_id}/assessment-components/{component_id}/clear-all', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id'];
        $component_id = $args['component_id'];  // This should be the correct component_id

        // Check if the component belongs to the lecturer
        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM assessment_components
            WHERE component_id = :component_id AND lecturer_id = :lecturer_id
        ");
        $stmt->execute(['component_id' => $component_id, 'lecturer_id' => $lecturer_id]);
        $componentExists = $stmt->fetchColumn();

        if (!$componentExists) {
            $response->getBody()->write(json_encode(['error' => 'Component does not belong to this lecturer']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Delete all marks for the specified component
        $stmt_delete_marks = $pdo->prepare("
            DELETE FROM assessment_marks WHERE component_id = :component_id
        ");
        $stmt_delete_marks->execute(['component_id' => $component_id]);

        $response->getBody()->write(json_encode(['message' => 'All marks for this assessment component have been cleared']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // route to delete an assessment component
    $app->delete('/lecturer/{lecturer_id}/delete-assessment-components/{component_id}', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id'];
        $component_id = $args['component_id'];

        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("DELETE FROM assessment_components WHERE component_id = :component_id AND lecturer_id = :lecturer_id");
        $stmt->execute(['component_id' => $component_id, 'lecturer_id' => $lecturer_id]);

        $response->getBody()->write(json_encode(['message' => 'Assessment component deleted successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // GET ALL students
    $app->get('/students', function ($request, $response) {
        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->query("SELECT * FROM STUDENTS");
        $products = $stmt->fetchAll();

        $response->getBody()->write(json_encode($products));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($jwtMiddleware);

    // get student name based on matric_no
    $app->get('/student-name/{matric_no}', function (Request $request, Response $response, $args) {
        $matric_no = $args['matric_no'];

        $db = new db();      
        $pdo = $db->getPDO(); 
        $stmt = $pdo->prepare("SELECT student_name FROM students WHERE matric_no = :matric_no");
        $stmt->execute(['matric_no' => $matric_no]);
        $student = $stmt->fetch();

        if ($student) {
            $response->getBody()->write(json_encode(['name' => $student['student_name']]));
        } else {
            $response->getBody()->write(json_encode(['error' => 'Student not found']));
        }

        return $response->withHeader('Content-Type', 'application/json');
    });

    // create enrollment route
    $app->post('/lecturer/{lecturer_id}/assessment-components/{component_id}/enroll', function (Request $request, Response $response, $args) {
        $lecturer_id = $args['lecturer_id']; // Get lecturer_id from URL
        $component_id = $args['component_id']; // Get component_id from URL
        $data = json_decode($request->getBody()->getContents(), true);

        $matric_no = $data['matric_no'];
        $student_name = $data['student_name'];
        $course_code = $data['course_code']; // Get course_code from request body
        $lecturer_id_from_body = $data['lecturer_id']; // Get lecturer_id from request body

        // Check if the lecturer_id matches the one in the URL (for security)
        if ($lecturer_id != $lecturer_id_from_body) {
            $response->getBody()->write(json_encode(['message' => 'Unauthorized request']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $db = new db();      
        $pdo = $db->getPDO(); 

        // Check if the student is already enrolled
        $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_matric_no = ? AND course_code = ?");
        $stmt->execute([$matric_no, $course_code]);
        $existingEnrollment = $stmt->fetch();

        if ($existingEnrollment) {
            $response->getBody()->write(json_encode(['message' => 'Student is already enrolled in this course']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Insert the new enrollment record
        try {
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_matric_no, course_code, academic_year, lecturer_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$matric_no, $course_code, '24/25', $lecturer_id_from_body]);

            $response->getBody()->write(json_encode(['message' => 'Student enrolled successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['message' => 'Error enrolling student', 'error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    })->add($jwtMiddleware);
};
