<?php
//php -S localhost:8000 -t public
//npm run serve
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require __DIR__ . '/../vendor/autoload.php';
//require_once __DIR__ . '/../src/Middleware/JwtMiddleware.php';
//(require __DIR__ . '/../src/Controllers/LecturerController.php')($app, $jwtMiddleware); // link to controller for lecturer routes

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteCollectorProxy; // Make sure this is imported

use App\db;
use App\Middleware\JwtMiddleware;
use App\Controllers\AuthController;
use App\Controllers\StudentController;
//use App\Controllers\AdvisorController;
//use App\Controllers\AdminController;
use App\Services\StudentService;
use App\Services\LecturerService;
use App\Services\AdvisorService;
use App\Services\AdminService;


// Removed: use Firebase\JWT\JWT; // Not needed directly in index.php now

// Removed: use InvalidArgumentException; // These are global exceptions, no need for `use` statements
// Removed: use RuntimeException;         // to suppress warnings if referenced without namespace

$app = AppFactory::create();
$secretKey = "my-secret-key"; // Keep this secure in production (environment variable)
$unprotectedRoutes = ['/api/register', '/api/login'];
$jwtMiddleware = new JwtMiddleware($secretKey, $unprotectedRoutes);
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);



// --- API Routes Definition ---

// --- Unprotected Routes (Login and Register) ---
// These routes do NOT have JWT middleware applied directly.

// POST /api/register - Handles user and student profile creation
$app->post('/api/register', function (Request $request, Response $response) use ($secretKey) {
    try {
        $registrationData = json_decode($request->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($registrationData)) {
            $errorBody = json_encode(['error' => 'Invalid JSON data provided. Please ensure your request body is valid JSON.']);
            $response->getBody()->write($errorBody);
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $database = new db();
        $studentService = new StudentService();
        $lecturerService = new LecturerService();
        $advisorService = new AdvisorService();
        $database = new db();
        $adminService = new AdminService($database);
        $authController = new AuthController($database, $studentService, $lecturerService, $advisorService, $adminService, $secretKey);


        $result = $authController->register($registrationData); // Delegate to AuthController

        $response->getBody()->write(json_encode($result));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    } catch (\InvalidArgumentException $e) { // Use \ for global exceptions if not using `use` statement
        $errorBody = json_encode(['error' => $e->getMessage()]);
        $response->getBody()->write($errorBody);
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) { // Use \ for global exceptions
        $statusCode = 500;
        if ($e->getCode() === 409) {
            $statusCode = 409;
        }
        $errorBody = json_encode(['error' => $e->getMessage()]);
        $response->getBody()->write($errorBody);
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
        $response->getBody()->write($errorBody);
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// POST /api/login - Handles user authentication and JWT token generation
$app->post('/api/login', function (Request $request, Response $response) use ($secretKey) {
    try {
        $credentials = json_decode($request->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($credentials)) {
            $errorBody = json_encode(['error' => 'Invalid JSON data provided for login.']);
            $response->getBody()->write($errorBody);
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $database = new db();
        $studentService = new StudentService();
        $lecturerService = new LecturerService();
        $advisorService = new AdvisorService();
        $database = new db();
        $adminService = new AdminService($database);
        $authController = new AuthController($database, $studentService, $lecturerService, $advisorService, $adminService, $secretKey);


        $result = $authController->login($credentials); // Delegate to AuthController

        $response->getBody()->write(json_encode($result));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

    } catch (\InvalidArgumentException $e) { // Use \ for global exceptions
        $errorBody = json_encode(['error' => $e->getMessage()]);
        $response->getBody()->write($errorBody);
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    } catch (\RuntimeException $e) { // Use \ for global exceptions
        $statusCode = $e->getCode() ?: 500;
        $errorBody = json_encode(['error' => $e->getMessage()]);
        $response->getBody()->write($errorBody);
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $errorBody = json_encode(['error' => 'An unexpected server error occurred during login: ' . $e->getMessage()]);
        $response->getBody()->write($errorBody);
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});


// Group for protected API routes, applying JwtMiddleware to all routes within this group
// The group's prefix is '/api'. Routes inside should NOT repeat '/api'.
$app->group('/api', function (RouteCollectorProxy $group) use ($secretKey){

    // NEW: Logout Route - Protected (User must be logged in to "logout" from backend)
    // Path becomes /api/logout (because of group prefix)
    $group->post('/logout', function (Request $request, Response $response) use ($secretKey) { // Added use($secretKey) for AuthController
        $database = new db();
        $studentService = new StudentService();
        $lecturerService = new LecturerService();
        $advisorService = new AdvisorService();
        $database = new db();
        $adminService = new AdminService($database);
        $authController = new AuthController($database, $studentService, $lecturerService, $advisorService, $adminService, $secretKey);

        try {
            $result = $authController->logout();

            $response->getBody()->write(json_encode($result));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $errorBody = json_encode(['error' => 'An error occurred during logout: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // GET /api/me/role - Example protected route to get user role from JWT
    // Path becomes /api/me/role
    $group->get('/me/role', function (Request $request, Response $response) {
        $jwt = $request->getAttribute('jwt');
        $role = $jwt->data->role ?? null;
        $response->getBody()->write(json_encode(['role' => $role]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    });

    // GET /api/students - Get all student profiles (requires authentication)
    // Path becomes /api/students
    $group->get('/students', function (Request $request, Response $response) {
        try {
            $database = new db();
            $controller = new StudentController($database);
            $students = $controller->getAll();

            $response->getBody()->write(json_encode($students));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $errorBody = json_encode(['error' => 'Failed to retrieve students: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // GET /api/students/{id} - Get a specific student profile by ID (requires authentication)
    // Path becomes /api/students/{id}
    $group->get('/students/{id}', function (Request $request, Response $response, array $args) {
        try {
            $id = (int)$args['id'];

            $database = new db();
            $controller = new StudentController($database);
            $student = $controller->getStudentById($id);

            if ($student === false) {
                $errorBody = json_encode(['error' => 'Student not found.']);
                $response->getBody()->write($errorBody);
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($student));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $errorBody = json_encode(['error' => 'Failed to retrieve student: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });


    $group->get('/users', function (Request $request, Response $response) {
    $database = new \App\db();
    $adminService = new \App\Services\AdminService($database); 
    
    $users = $adminService->getAllUsers();
    $response->getBody()->write(json_encode($users));
    
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json'); });
    
    $group->get('/students/{userId}/enrollments', function (Request $request, Response $response, array $args) {
        try {
            $userId = (int)$args['userId'];
            $database = new db();
            $controller = new StudentController($database);

            // IMPORTANT: Verify that the userId from the URL matches the logged-in user's ID from JWT
            $jwtPayload = $request->getAttribute('jwt'); // JWT payload from middleware
            $authenticatedUserId = $jwtPayload->data->id;
            $authenticatedUserRole = $jwtPayload->data->role;

            // Allow admin or the specific student to view enrollments
            if ($authenticatedUserId !== $userId && $authenticatedUserRole !== 'admin' && $authenticatedUserRole !== 'lecturer') {
                $errorBody = json_encode(['error' => 'Unauthorized: Cannot view enrollments for another user.']);
                $response->getBody()->write($errorBody);
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Call the correct method to get enrollments
            $enrollments = $controller->getStudentEnrollments($userId);

            $response->getBody()->write(json_encode($enrollments));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $errorBody = json_encode(['error' => 'Failed to retrieve student enrollments: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $group->get('/enrollments/{enrollmentId}/components-and-marks', function (Request $request, Response $response, array $args) {
        try {
            $enrollmentId = (int)$args['enrollmentId'];
            $database = new db();
            $controller = new StudentController($database);

            // Get authenticated user info from JWT
            $jwtPayload = $request->getAttribute('jwt');
            $authenticatedUserId = $jwtPayload->data->id;
            $authenticatedUserRole = $jwtPayload->data->role;

            // Call the controller method, passing authenticated user details for internal authorization
            $componentsAndMarks = $controller->getEnrollmentComponentsAndMarks(
                $enrollmentId,
                $authenticatedUserId,
                $authenticatedUserRole
            );

            $response->getBody()->write(json_encode($componentsAndMarks));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

        } catch (\RuntimeException $e) {
            // Catch RuntimeException thrown by the controller, using its specific code if available
            $statusCode = $e->getCode() ?: 500; // Use the code from the exception, or default to 500
            $errorBody = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            // Catch any other unexpected errors
            $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $group->get('/enrollments/{enrollmentId}/comparison', function (Request $request, Response $response, array $args) {
        try {
            $enrollmentId = (int)$args['enrollmentId'];
            $database = new db();
            $controller = new StudentController($database);

            $jwtPayload = $request->getAttribute('jwt');
            $authenticatedUserId = $jwtPayload->data->id;
            $authenticatedUserRole = $jwtPayload->data->role;

            $comparisonData = $controller->getEnrollmentComparisonData(
                $enrollmentId,
                $authenticatedUserId,
                $authenticatedUserRole
            );

            $response->getBody()->write(json_encode($comparisonData));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $statusCode = $e->getCode() ?: 500;
            $errorBody = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // NEW Route: GET /api/enrollments/{enrollmentId}/rank
    $group->get('/enrollments/{enrollmentId}/rank', function (Request $request, Response $response, array $args) {
        try {
            $enrollmentId = (int)$args['enrollmentId'];
            $database = new db();
            $controller = new StudentController($database);

            $jwtPayload = $request->getAttribute('jwt');
            $authenticatedUserId = $jwtPayload->data->id;
            $authenticatedUserRole = $jwtPayload->data->role;

            $rankData = $controller->getStudentClassRank(
                $enrollmentId,
                $authenticatedUserId,
                $authenticatedUserRole
            );

            $response->getBody()->write(json_encode($rankData));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $statusCode = $e->getCode() ?: 500;
            $errorBody = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    $group->get('/enrollments/{enrollmentId}/components-marks', function (Request $request, Response $response, array $args) {
        try {
            $enrollmentId = (int)$args['enrollmentId'];
            $database = new db();
            $controller = new StudentController($database);

            $jwtPayload = $request->getAttribute('jwt');
            $authenticatedUserId = $jwtPayload->data->id;
            $authenticatedUserRole = $jwtPayload->data->role;

            // This method already exists in StudentController.php and fetches components and marks.
            $data = $controller->getEnrollmentComponentsAndMarks(
                $enrollmentId,
                $authenticatedUserId,
                $authenticatedUserRole
            );

            $response->getBody()->write(json_encode($data));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $statusCode = $e->getCode() ?: 500;
            $errorBody = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            error_log("ERROR: Unexpected error in /enrollments/{enrollmentId}/components-marks: " . $e->getMessage());
            $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });


    $group->post('/enrollments/{enrollmentId}/performance-expectation', function (Request $request, Response $response, array $args) {
        try {
            $enrollmentId = (int)$args['enrollmentId'];
            $requestBody = $request->getBody()->getContents();
            $data = json_decode($requestBody, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $errorBody = json_encode(['error' => 'Invalid JSON data provided.']);
                $response->getBody()->write($errorBody);
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $hypotheticalComponentMarks = $data['hypothetical_component_marks'] ?? [];
            $hypotheticalFinalExamMark = $data['hypothetical_final_exam_mark'] ?? null;

            $database = new db();
            $controller = new StudentController($database);

            $jwtPayload = $request->getAttribute('jwt');
            $authenticatedUserId = $jwtPayload->data->id;
            $authenticatedUserRole = $jwtPayload->data->role;

            $projection = $controller->calculatePerformanceExpectationMarks(
                $enrollmentId,
                $hypotheticalComponentMarks,
                $hypotheticalFinalExamMark,
                $authenticatedUserId,
                $authenticatedUserRole
            );

            $response->getBody()->write(json_encode($projection));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $statusCode = $e->getCode() ?: 500;
            $errorBody = json_encode(['error' => $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            error_log("ERROR: Unexpected error in /enrollments/{enrollmentId}/performance-expectation: " . $e->getMessage());
            $errorBody = json_encode(['error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

    });

})->add($jwtMiddleware); // Apply the JWT middleware to this entire group

(require __DIR__ . '/../src/App/Controllers/LecturerController.php')($app, $jwtMiddleware); // link to controller for lecturer routes

// --- Global CORS Middleware ---
// This middleware must be added AFTER all route definitions and before $app->run().
// It ensures CORS headers are applied to ALL responses, including those from protected routes.
$app->add(function (Request $request, RequestHandler $handler): Response {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*') // Change to specific frontend domain(s) in production!
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->run();
