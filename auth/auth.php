<?php
session_start();

class Auth {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->setupDatabase();
    }

    private function setupDatabase() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS user_preferences (
            user_id INT PRIMARY KEY,
            show_phonetics BOOLEAN DEFAULT true,
            dark_mode BOOLEAN DEFAULT false,
            font_size INT DEFAULT 16,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $this->conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS collections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $this->conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS collection_verses (
            collection_id INT,
            book VARCHAR(50),
            chapter INT,
            verse INT,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (collection_id) REFERENCES collections(id)
        )";
        $this->conn->query($sql);
    }

    public function register($username, $email, $password) {
        if (empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Todos os campos são obrigatórios'];
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $user_id = $this->conn->insert_id;
                $stmt = $this->conn->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                return ['success' => true, 'message' => 'Registro realizado com sucesso'];
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => false, 'message' => 'Usuário ou email já existe'];
            }
            return ['success' => false, 'message' => 'Erro ao registrar usuário'];
        }
    }

    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $username;
                return ['success' => true, 'message' => 'Login realizado com sucesso'];
            }
        }
        return ['success' => false, 'message' => 'Usuário ou senha inválidos'];
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logout realizado com sucesso'];
    }

    public function getUserPreferences($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function updateUserPreferences($user_id, $preferences) {
        $sql = "UPDATE user_preferences SET ";
        $types = "";
        $values = [];
        
        foreach ($preferences as $key => $value) {
            $sql .= "$key = ?, ";
            $types .= is_bool($value) ? "i" : (is_int($value) ? "i" : "s");
            $values[] = $value;
        }
        
        $sql = rtrim($sql, ", ") . " WHERE user_id = ?";
        $types .= "i";
        $values[] = $user_id;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        return $stmt->execute();
    }
}
?>