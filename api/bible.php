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

function getChapter($conn, $book, $chapter) {
    $stmt = $conn->prepare("SELECT * FROM capitulo WHERE livro = ? AND numero_cap = ?");
    $stmt->bind_param("si", $book, $chapter);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'content' => $row['conteudo'],
            'content_pt' => $row['conteudo_pt'],
            'book' => $row['livro'],
            'chapter' => $row['numero_cap']
        ];
    }
    return null;
}

function searchVerses($conn, $query) {
    $search = "%$query%";
    $stmt = $conn->prepare("SELECT * FROM capitulo WHERE conteudo LIKE ? OR conteudo_pt LIKE ? LIMIT 50");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getCollection($conn, $user_id, $collection_id) {
    $stmt = $conn->prepare("SELECT * FROM collections WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $collection_id, $user_id);
    $stmt->execute();
    $collection = $stmt->get_result()->fetch_assoc();

    if ($collection) {
        $stmt = $conn->prepare("SELECT * FROM collection_verses WHERE collection_id = ?");
        $stmt->bind_param("i", $collection_id);
        $stmt->execute();
        $collection['verses'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    return $collection;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));
$endpoint = end($path_parts);

switch ($method) {
    case 'GET':
        if (isset($_GET['book']) && isset($_GET['chapter'])) {
            $chapter = getChapter($conn, $_GET['book'], $_GET['chapter']);
            if ($chapter) {
                echo json_encode($chapter);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Capítulo não encontrado']);
            }
        } elseif (isset($_GET['search'])) {
            $results = searchVerses($conn, $_GET['search']);
            echo json_encode(['results' => $results]);
        } elseif ($auth->isLoggedIn() && isset($_GET['collection_id'])) {
            $collection = getCollection($conn, $_SESSION['user_id'], $_GET['collection_id']);
            if ($collection) {
                echo json_encode($collection);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Coleção não encontrada']);
            }
        }
        break;

    case 'POST':
        if (!$auth->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Usuário não autenticado']);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if ($endpoint === 'collections') {
            $stmt = $conn->prepare("INSERT INTO collections (user_id, name, description) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $_SESSION['user_id'], $data['name'], $data['description']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao criar coleção']);
            }
        } elseif ($endpoint === 'verses') {
            $stmt = $conn->prepare("INSERT INTO collection_verses (collection_id, book, chapter, verse) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isii", $data['collection_id'], $data['book'], $data['chapter'], $data['verse']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao adicionar versículo']);
            }
        }
        break;

    case 'DELETE':
        if (!$auth->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Usuário não autenticado']);
            break;
        }

        if ($endpoint === 'collections' && isset($_GET['id'])) {
            $stmt = $conn->prepare("DELETE FROM collections WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $_GET['id'], $_SESSION['user_id']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao excluir coleção']);
            }
        } elseif ($endpoint === 'verses' && isset($_GET['collection_id']) && isset($_GET['verse_id'])) {
            $stmt = $conn->prepare("DELETE FROM collection_verses WHERE collection_id = ? AND id = ?");
            $stmt->bind_param("ii", $_GET['collection_id'], $_GET['verse_id']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao remover versículo']);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}

$conn->close();
?>