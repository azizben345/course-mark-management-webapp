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

(require __DIR__ . '/../src/Controllers/LecturerController.php')($app, $jwtMiddleware); // link to controller for lecturer routes

// CORS preflight support
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->run();
