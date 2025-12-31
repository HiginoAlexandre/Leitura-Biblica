<?php
// api.php - API para servir dados da Bíblia em JSON

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Para desenvolvimento

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
    echo json_encode(["error" => $e->getMessage()]);
    exit();
}

// Função para executar consultas SQL preparadas
function executePreparedQuery($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    if ($params) {
        $types = str_repeat("s", count($params));
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute() === false) {
        throw new Exception("Erro ao executar consulta: " . $stmt->error);
    }
    return $stmt->get_result();
}

// Função para obter transcrição IPA
function getIPA($conn, $termo) {
    $sql = "SELECT ipa FROM ipa WHERE ingles = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $stmt->error);
    }
    $stmt->bind_param("s", $termo);
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar consulta: " . $stmt->error);
    }
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['ipa'] : null;
}

// Função para processar palavras
function processWord($conn, $palavra) {
    // Substituir hífens e outros caracteres especiais por espaços antes de processar
    $palavra = str_replace("—", "-", $palavra);

    // Remove caracteres especiais e números
    $palavra_limpa = preg_replace(["/\'s/", "/\'S/", "/[^\w\s]|[\d]/"], "", $palavra);

    if (empty($palavra_limpa)) {
        return [
            'original' => $palavra,
            'ipa' => $palavra,
            'has_ipa' => false
        ];
    }

    // Verifica se tem hífen
    if (strpos($palavra, "-") !== false) {
        $partes = explode("-", $palavra);
        $partes_processadas = [];

        foreach ($partes as $parte) {
            $partes_processadas[] = processWord($conn, $parte);
        }

        return [
            'original' => $palavra,
            'parts' => $partes_processadas,
            'has_ipa' => true,
            'is_composite' => true
        ];
    }

    $ipa = getIPA($conn, $palavra_limpa);

    return [
        'original' => $palavra,
        'clean' => $palavra_limpa,
        'ipa' => $ipa ?: $palavra,
        'has_ipa' => $ipa !== null,
        'is_composite' => false
    ];
}

// Determinar a ação da API
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_livros':
            $sql = "SELECT livro, capitulos FROM biblioteca";
            $result = executePreparedQuery($conn, $sql);
            $livros = [];
            while ($row = $result->fetch_assoc()) {
                $livros[] = $row;
            }
            echo json_encode(["success" => true, "livros" => $livros]);
            break;
            
        case 'get_capitulo':
            $livro = $_GET['livro'] ?? '';
            $capitulo = $_GET['capitulo'] ?? '';
            
            if (!$livro || !$capitulo) {
                throw new Exception("Livro e capítulo são obrigatórios");
            }
            
            // Obter conteúdo do capítulo
            $sql = "SELECT * FROM capitulo WHERE livro = ? AND numero_cap = ?";
            $result = executePreparedQuery($conn, $sql, [$livro, $capitulo]);
            
            if ($result->num_rows === 0) {
                throw new Exception("Capítulo não encontrado");
            }
            
            $row = $result->fetch_assoc();
            $conteudo = $row["conteudo"];
            $conteudo_pt = $row["conteudo_pt"];
            
            // Processar parágrafos
            $paragrafos_en = explode("\n", $conteudo);
            $paragrafos_pt = explode("\n", $conteudo_pt);
            $total_paragrafos = min(count($paragrafos_en), count($paragrafos_pt));
            
            $paragrafos_processados = [];
            
            for ($i = 0; $i < $total_paragrafos; $i++) {
                $paragrafo_en = $paragrafos_en[$i];
                $paragrafo_pt = $paragrafos_pt[$i];
                
                if (empty($paragrafo_en)) continue;
                
                $palavras = explode(" ", $paragrafo_en);
                $palavras_processadas = [];
                
                foreach ($palavras as $palavra) {
                    $palavras_processadas[] = processWord($conn, $palavra);
                }
                
                $paragrafos_processados[] = [
                    'ingles' => $palavras_processadas,
                    'portugues' => $paragrafo_pt
                ];
            }
            
            echo json_encode([
                "success" => true,
                "livro" => $livro,
                "capitulo" => $capitulo,
                "paragrafos" => $paragrafos_processados
            ]);
            break;
            
        default:
            echo json_encode([
                "success" => false,
                "error" => "Ação não especificada",
                "available_actions" => ["get_livros", "get_capitulo"]
            ]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>