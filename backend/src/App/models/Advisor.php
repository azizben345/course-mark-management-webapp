<?php

namespace App\models;

use \PDO;

class Advisor
{
    private int $id;
    private string $name;
    private string $email;
    private ?string $department;

    public function __construct(int $id, string $name, string $email, ?string $department = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->department = $department;
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    // Setters
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setDepartment(?string $department): void
    {
        $this->department = $department;
    }

    // Get all advisors
    public static function getAll()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM advisors");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get a specific advisor by ID
    public static function getById($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM advisors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create a new advisor
    public static function create($name, $email, $department)
    {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO advisors (name, email, department) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $department]);
        return self::getById($pdo->lastInsertId());  // Return the newly created advisor
    }

    // Update an existing advisor
    public static function update($id, $data)
    {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE advisors SET name = ?, email = ?, department = ? WHERE id = ?");
        $stmt->execute([$data['name'], $data['email'], $data['department'], $id]);
        
        // Return the updated advisor
        return self::getById($id);
    }

    // Delete an advisor
    public static function delete($id)
    {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM advisors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;  // Return true if any row was deleted
    }
}