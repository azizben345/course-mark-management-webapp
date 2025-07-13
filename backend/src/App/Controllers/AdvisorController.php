<?php

namespace App\Controllers;

use App\db;
use App\models\Advisor;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class AdvisorController
{
    
    public function index(Request $request, Response $response, $args)
    {
        try {
            $advisors = Advisor::getAll();
            $response->getBody()->write(json_encode($advisors));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Failed to fetch advisors']));
        }
    }

    // Show a single advisor
    public function show(Request $request, Response $response, $args)
    {
        try {
            $advisor = Advisor::getById($args['id']);
            if (!$advisor) {
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['error' => 'Advisor not found']));
            }
            $response->getBody()->write(json_encode($advisor));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Failed to fetch advisor']));
        }
    }

    // Create a new advisor
    public function store(Request $request, Response $response, $args)
    {
        try {
            $data = $request->getParsedBody();
            
            // Validate required fields
            if (empty($data['name']) || empty($data['email'])) {
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['error' => 'Name and email are required']));
            }
            
            $advisor = Advisor::create($data['name'], $data['email'], $data['department'] ?? null);
            $response->getBody()->write(json_encode($advisor));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Failed to create advisor']));
        }
    }

    // Update an advisor
    public function update(Request $request, Response $response, $args)
    {
        try {
            $advisor = Advisor::getById($args['id']);
            if (!$advisor) {
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['error' => 'Advisor not found']));
            }
            
            $data = $request->getParsedBody();
            
            // Validate required fields
            if (empty($data['name']) || empty($data['email'])) {
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['error' => 'Name and email are required']));
            }
            
            $updatedAdvisor = Advisor::update($args['id'], $data);
            $response->getBody()->write(json_encode($updatedAdvisor));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Failed to update advisor']));
        }
    }

    // Delete an advisor
    public function destroy(Request $request, Response $response, $args)
    {
        try {
            $advisor = Advisor::getById($args['id']);
            if (!$advisor) {
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['error' => 'Advisor not found']));
            }
            
            $deleted = Advisor::delete($args['id']);
            if ($deleted) {
                return $response->withStatus(204);
            } else {
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['error' => 'Failed to delete advisor']));
            }
        } catch (Exception $e) {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Failed to delete advisor']));
        }
    }
}