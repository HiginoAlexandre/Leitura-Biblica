<?php
header('Content-Type: application/json');
require_once '../auth/auth.php';

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
    $auth = new Auth($conn);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

switch ($action) {
    case 'list':
        $stmt = $conn->prepare("SELECT * FROM collections WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $collections = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'collections' => $collections]);
        break;

    case 'create':
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nome da coleção é obrigatório']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO collections (user_id, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $name, $description);

        if ($stmt->execute()) {
            $collection_id = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'collection' => [
                    'id' => $collection_id,
                    'name' => $name,
                    'description' => $description
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar coleção']);
        }
        break;

    case 'view':
        $collection_id = $_GET['id'] ?? 0;
        
        // Verifica se a coleção pertence ao usuário
        $stmt = $conn->prepare("SELECT * FROM collections WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $collection_id, $user_id);
        $stmt->execute();
        $collection = $stmt->get_result()->fetch_assoc();

        if (!$collection) {
            echo json_encode(['success' => false, 'message' => 'Coleção não encontrada']);
            exit();
        }

        // Busca os versículos da coleção
        $stmt = $conn->prepare("SELECT * FROM collection_verses WHERE collection_id = ?");
        $stmt->bind_param("i", $collection_id);
        $stmt->execute();
        $verses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $collection['verses'] = $verses;
        echo json_encode(['success' => true, 'collection' => $collection]);
        break;

    case 'add_verse':
        $data = json_decode(file_get_contents('php://input'), true);
        $collection_id = $data['collection_id'] ?? 0;
        $verse = $data['verse'] ?? null;

        if (!$verse || !$collection_id) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit();
        }

        // Verifica se a coleção pertence ao usuário
        $stmt = $conn->prepare("SELECT id FROM collections WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $collection_id, $user_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Coleção não encontrada']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO collection_verses (collection_id, book, chapter, verse) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isii", $collection_id, $verse['book'], $verse['chapter'], $verse['verse']);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao adicionar versículo']);
        }
        break;

    case 'remove_verse':
        $data = json_decode(file_get_contents('php://input'), true);
        $collection_id = $data['collection_id'] ?? 0;
        $verse_id = $data['verse_id'] ?? 0;

        // Verifica se a coleção pertence ao usuário
        $stmt = $conn->prepare("SELECT id FROM collections WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $collection_id, $user_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Coleção não encontrada']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM collection_verses WHERE collection_id = ? AND id = ?");
        $stmt->bind_param("ii", $collection_id, $verse_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover versículo']);
        }
        break;

    case 'delete':
        $collection_id = $_GET['id'] ?? 0;

        // Verifica se a coleção pertence ao usuário
        $stmt = $conn->prepare("SELECT id FROM collections WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $collection_id, $user_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Coleção não encontrada']);
            exit();
        }

        // Remove os versículos da coleção
        $stmt = $conn->prepare("DELETE FROM collection_verses WHERE collection_id = ?");
        $stmt->bind_param("i", $collection_id);
        $stmt->execute();

        // Remove a coleção
        $stmt = $conn->prepare("DELETE FROM collections WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $collection_id, $user_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir coleção']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida']);
        break;
}