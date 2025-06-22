<?php
// namespace App\Controllers;

// use Psr\Http\Message\ResponseInterface as Response;
// use Psr\Http\Message\ServerRequestInterface as Request;
// use Firebase\JWT\JWT;

// return function ($app) {
//     $app->post('/login', function (Request $request, Response $response) {
//         $data = json_decode($request->getBody()->getContents(), true);
//         $username = $data['username'] ?? '';
//         $password = $data['password'] ?? '';

//         $pdo = getPDO();
//         $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
//         $stmt->execute([$username]);
//         $user = $stmt->fetch();

//         if ($user && $password === $user['password']) {
//             $payload = [
//                 'user' => $user['username'],
//                 'role' => $user['role'],
//                 'iat' => time(),
//                 'exp' => time() + 3600
//             ];
//             $token = JWT::encode($payload, "my-secret-key", 'HS256');
//             $response->getBody()->write(json_encode(['token' => $token]));
//             return $response->withHeader('Content-Type', 'application/json');
//         }

//         $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
//         return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
//     });
// };