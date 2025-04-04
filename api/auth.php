<?php
header('Content-Type: application/json');
session_start();

// Configurações de conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "leiturabiblica";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Erro ao conectar ao banco de dados: " . $conn->connect_error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                $stmt = $conn->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Registro realizado com sucesso']);
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo json_encode(['success' => false, 'message' => 'Usuário ou email já existe']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao registrar usuário']);
            }
        }
        break;

    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $username;
                echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        }
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logout realizado com sucesso']);
        break;

    case 'check_status':
        echo json_encode([
            'isLoggedIn' => isset($_SESSION['user_id']),
            'username' => $_SESSION['username'] ?? null
        ]);
        break;

    case 'recover_password':
        $email = $_POST['email'] ?? '';

        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email é obrigatório']);
            exit();
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            // Gera token de recuperação
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $expires, $email);

            if ($stmt->execute()) {
                // Aqui você implementaria o envio do email com o link de recuperação
                // Por enquanto, apenas retorna sucesso
                echo json_encode(['success' => true, 'message' => 'Instruções de recuperação enviadas para seu email']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao processar recuperação de senha']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Email não encontrado']);
        }
        break;

    case 'reset_password':
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($token) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Token e nova senha são obrigatórios']);
            exit();
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();

        if ($row = $stmt->get_result()->fetch_assoc()) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $row['id']);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Senha atualizada com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar senha']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Token inválido ou expirado']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida']);
        break;
}