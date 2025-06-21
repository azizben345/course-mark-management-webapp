<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/jwtMiddleware.php';


use Slim\Factory\AppFactory; 
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;

$secretKey = "my-secret-key";

$app = AppFactory::create();
$jwtMiddleware = new JwtMiddleware($secretKey);

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

//publicly accessible route
//login
$app->post('/login', function (Request $request, Response $response) use ($secretKey) {
    $data = json_decode($request->getBody()->getContents(), true);

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (!$username || !$password) {
        $response->getBody()->write(json_encode(['error' => 'Username and password required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // if ($user && password_verify($password, $user['password'])) {
    if ($user && $password === $user['password']) {

        $issuedAt = time();
        $expire = $issuedAt + 3600;

        $payload = [
            'user' => $user['username'],
            'role' => $user['role'],
            'iat' => $issuedAt,
            'exp' => $expire
        ];

        $token = JWT::encode($payload, $secretKey, 'HS256');

        $response->getBody()->write(json_encode(['token' => $token]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
    return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
});

// get user role
$app->get('/me/role', function (Request $request, Response $response) {
    $jwt = $request->getAttribute('jwt');
    if (!$jwt) {
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
    $role = $jwt->role ?? null;
    $response->getBody()->write(json_encode(['role' => $role]));
    return $response->withHeader('Content-Type', 'application/json');
})->add($jwtMiddleware);

// manage students route
$app->get('/manage-students/{lecturer_id}', function (Request $request, Response $response, $args) {
    $lecturer_id = $args['lecturer_id'];  // Get the lecturer_id from the URL

    // Fetch assessment components for the lecturer's courses
    $pdo = getPDO();

    // Get all assessment components for this lecturer
    $stmt_assessments = $pdo->prepare("
        SELECT 
            ac.component_id, 
            ac.component_name, 
            ac.course_code
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
            e.final_total
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
                'components' => [],
                'students' => []
            ];
        }

        $courses[$assessment['course_code']]['components'][] = $assessment;
    }

    // Add students to the relevant courses
    foreach ($students as $student) {
        $course_code = $student['course_code'];
        if (isset($courses[$course_code])) {
            $student['marks'] = [];
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
                $student['marks'][] = [
                    'component_name' => $assessment['component_name'],
                    'mark_obtained' => $mark ? $mark['mark_obtained'] : 'N/A'
                ];
            }

            // Add the student to the correct course
            $courses[$course_code]['students'][] = $student;
        }
    }

    // Return combined response with courses and students
    $response->getBody()->write(json_encode([
        'courses' => array_values($courses)  // return courses grouped by course_code
    ]));

    return $response->withHeader('Content-Type', 'application/json');
});

// create enrollment route
$app->post('/enrollments', function (Request $request, Response $response) {
    $pdo = getPDO();
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
});

// update enrollment route
$app->put('/students/{enrollment_id}', function (Request $request, Response $response, $args) {
    $enrollment_id = $args['enrollment_id'];
    $data = json_decode($request->getBody()->getContents(), true);
    $final_exam_mark = $data['final_exam_mark'] ?? 0;

    // Get the student's assessment marks from the database
    $pdo = getPDO();
    $stmt_marks = $pdo->prepare("
        SELECT SUM(am.mark_obtained) as ca_total
        FROM assessment_marks am
        JOIN enrollments e ON am.enrollment_id = e.enrollment_id
        WHERE e.enrollment_id = :enrollment_id
    ");
    $stmt_marks->execute(['enrollment_id' => $enrollment_id]);
    $marks = $stmt_marks->fetch();

    // Calculate the total (70% CA, 30% final exam)
    $ca_total = $marks['ca_total'] ?? 0;
    $final_total = ($ca_total * 0.7) + ($final_exam_mark * 0.3);

    // Update the final_exam_mark and final_total in the database
    $stmt_update = $pdo->prepare("
        UPDATE enrollments
        SET final_exam_mark = :final_exam_mark, final_total = :final_total
        WHERE enrollment_id = :enrollment_id
    ");
    $stmt_update->execute([
        'final_exam_mark' => $final_exam_mark,
        'final_total' => $final_total,
        'enrollment_id' => $enrollment_id
    ]);

    $response->getBody()->write(json_encode(['message' => 'Student record updated successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});

// delete enrollment route
$app->delete('/students/{enrollment_id}', function (Request $request, Response $response, $args) {
    $enrollment_id = $args['enrollment_id'];

    $pdo = getPDO();

    // Delete the student from the enrollments table
    $stmt = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = :enrollment_id");
    $stmt->execute(['enrollment_id' => $enrollment_id]);

    $response->getBody()->write(json_encode(['message' => 'Student record deleted successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});

// get courses assigned to a lecturer
// Route to fetch courses assigned to the lecturer
$app->get('/lecturer/{lecturer_id}/courses', function (Request $request, Response $response, $args) {
    $lecturer_id = $args['lecturer_id'];

    $pdo = getPDO();
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
});

// route to get or fetch all assessment components based on the lecturer's courses
$app->get('/lecturer/{lecturer_id}/get-assessment-components', function (Request $request, Response $response, $args) {
    $pdo = getPDO();
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
});

// route to get assessment components based on component_id
$app->get('/lecturer/{lecturer_id}/get-assessment-component/{component_id}', function (Request $request, Response $response, $args) {
    $lecturer_id = $args['lecturer_id'];
    $component_id = $args['component_id'];  // Get the specific component_id from the URL

    // Fetch the assessment component details for the specific component_id
    $pdo = getPDO();
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
});

// route to create a new assessment component
$app->post('/lecturer/{lecturer_id}/create-assessment-components', function (Request $request, Response $response, $args) {
    $lecturer_id = $args['lecturer_id'];
    $data = json_decode($request->getBody()->getContents(), true);

    $course_code = $data['course_code'];
    $component_name = $data['component_name'];
    $max_mark = $data['max_mark'];

    $pdo = getPDO();
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
    $pdo = getPDO();
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
});

// route to clear all assessment marks for a specific assessment component
$app->delete('/lecturer/{lecturer_id}/assessment-components/{component_id}/clear-all', function (Request $request, Response $response, $args) {
    $lecturer_id = $args['lecturer_id'];
    $component_id = $args['component_id'];  // This should be the correct component_id

    // Check if the component belongs to the lecturer
    $pdo = getPDO();
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
});

// route to delete an assessment component
$app->delete('/lecturer/{lecturer_id}/delete-assessment-components/{component_id}', function (Request $request, Response $response, $args) {
    $lecturer_id = $args['lecturer_id'];
    $component_id = $args['component_id'];

    $pdo = getPDO();
    $stmt = $pdo->prepare("DELETE FROM assessment_components WHERE component_id = :component_id AND lecturer_id = :lecturer_id");
    $stmt->execute(['component_id' => $component_id, 'lecturer_id' => $lecturer_id]);

    $response->getBody()->write(json_encode(['message' => 'Assessment component deleted successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});

// GET ALL students
$app->get('/students', function ($request, $response) {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT * FROM STUDENTS");
    $products = $stmt->fetchAll();

    $response->getBody()->write(json_encode($products));
    return $response->withHeader('Content-Type', 'application/json');
})->add($jwtMiddleware);

// //GET 1 PRODUCT - accessible to all regs - normal user and admin
// $app->get('/product/{id}', function ($request, $response, $args) {
//     $id = $args['id'];

//     if (!is_numeric($id)) {
//         $response->getBody()->write(json_encode(['error' => 'Invalid product ID']));
//         return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
//     }

//     $pdo = getPDO();
//     $stmt = $pdo->prepare("SELECT * FROM PRODUCT WHERE id = ?");
//     $stmt->execute([$id]);
//     $product = $stmt->fetch();

//     if (!$product) {
//         $response->getBody()->write(json_encode(['error' => 'Product not found']));
//         return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
//     }

//     $response->getBody()->write(json_encode($product));
//     return $response->withHeader('Content-Type', 'application/json');
// })->add($jwtMiddleware);

// // POST /product – for admin only
// $app->post('/product', function ($request, $response) use ($secretKey) {
//     $jwt = $request->getAttribute('jwt');

//     if (($jwt->role ?? '') !== 'admin') {
//         $error = ['error' => 'Access denied: admin only'];
//         $response->getBody()->write(json_encode($error));
//         return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
//     }

//     $data = json_decode($request->getBody()->getContents(), true);

//     $pdo = getPDO();
//     $stmt = $pdo->prepare("INSERT INTO PRODUCT (name, price, image) VALUES (?, ?, ?)");
//     $stmt->execute([
//         $data['name'] ?? null,
//         $data['price'] ?? null,
//         $data['image'] ?? null
//     ]);

//     $response->getBody()->write(json_encode(['message' => 'Product added']));
//     return $response->withHeader('Content-Type', 'application/json');
// })->add(new JwtMiddleware($secretKey));

// CORS preflight support
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->run();
