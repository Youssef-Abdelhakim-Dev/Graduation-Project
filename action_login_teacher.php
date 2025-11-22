<?php
declare(strict_types=1);

class Teacher {
    public PDO $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    // Check if email already exists
    public function emailExists(string $email): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM teachers WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
    }

    // Insert teacher record
    public function create(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO teachers (username, subject, email, password, phone, year, image)
                VALUES (:username, :subject, :email, :password, :phone, :year, :image)
            ");
            $success = $stmt->execute([
                'username' => $data['username'],
                'subject'  => $data['subject'],
                'email'    => $data['email'],
                'password' => $data['password'],
                'phone'    => $data['phone'],
                'year'     => $data['year'],
                'image'    => $data['image']
            ]);

            return ['success' => $success];
        } catch (Exception $e) {
            error_log("DB Insert Error: " . $e->getMessage());
            return ['success' => false];
        }
    }
}
?>