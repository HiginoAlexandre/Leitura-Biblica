<?php
// adicionar.php
header('Content-Type: text/html; charset=utf-8');

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "leiturabiblica";

// Conexão com o banco
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Função para limpar palavras (similar ao processWord)
function limparPalavra($palavra) {
    return preg_replace(["/\'s/", "/\'S/", "/[^\w\s]|[\d]/"], "", $palavra);
}

// Processar formulário
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $palavras = $_POST['palavras'] ?? [];
    $textarea_palavras = $_POST['textarea_palavras'] ?? '';

    if (!empty($textarea_palavras)) {
        $linhas = explode("\n", $textarea_palavras);
        foreach ($linhas as $linha) {
            $partes = array_map('trim', explode('-', $linha));
            if (count($partes) === 2) {
                $palavras[] = [
                    'ingles' => $partes[0],
                    'ipa' => $partes[1]
                ];
            }
        }
    }

    if (!empty($palavras)) {
        $success_count = 0;
        $error_count = 0;

        foreach ($palavras as $palavra) {
            $ingles = trim($palavra['ingles']);
            $ipa = trim($palavra['ipa']);

            if (!empty($ingles) && !empty($ipa)) {
                try {
                    $sql = "INSERT INTO ipa (ingles, ipa) VALUES (:ingles, :ipa)
                            ON DUPLICATE KEY UPDATE ipa = :ipa";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':ingles', $ingles);
                    $stmt->bindParam(':ipa', $ipa);

                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } catch(PDOException $e) {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0 && $error_count === 0) {
            header('Location: index.html');
            exit();
        }

        if ($error_count > 0) {
            $message = "{$error_count} palavra(s) não puderam ser adicionadas.";
            $message_type = 'warning';
        }
    }
}

// Buscar palavras do formulário anterior
$palavras_pendentes = $_POST['palavras_pendentes'] ?? [];
if (is_string($palavras_pendentes)) {
    $palavras_pendentes = json_decode($palavras_pendentes, true) ?: [];
}

// Filtrar palavras pendentes para ignorar itens que não contenham letras
$palavras_pendentes = array_filter($palavras_pendentes, function($palavra) {
    return preg_match('/[a-zA-Z]/', $palavra['ingles']);
});

// APLICAR limparPalavra() A TODAS AS PALAVRAS PENDENTES
$palavras_pendentes = array_map(function($palavra) {
    return [
        'ingles' => limparPalavra($palavra['ingles']),
        'ipa' => $palavra['ipa'] ?? ''
    ];
}, $palavras_pendentes);

// REMOVER PALAVRAS DUPLICADAS (baseado na palavra em inglês)
$palavras_unicas = [];
$palavras_vistas = [];

foreach ($palavras_pendentes as $palavra) {
    $ingles_normalizado = strtolower(trim($palavra['ingles']));
    
    // Se ainda não vimos esta palavra, adicionar à lista
    if (!in_array($ingles_normalizado, $palavras_vistas)) {
        $palavras_vistas[] = $ingles_normalizado;
        $palavras_unicas[] = $palavra;
    }
}

$palavras_pendentes = $palavras_unicas;

// Gerar lista de palavras pendentes no formato "palavra_inglês - transcrição_fonética"
$lista_palavras_pendentes = array_map(function($palavra) {
    return $palavra['ingles'] . ' - ' . ($palavra['ipa'] ?? '');
}, $palavras_pendentes);
$lista_palavras_pendentes_texto = implode("\n", $lista_palavras_pendentes);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Transcrições IPA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 800px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .message {
            padding: 15px 30px;
            margin: 0;
            border-radius: 0;
        }
        
        .message.success {
            background-color: #d1fae5;
            color: #065f46;
            border-bottom: 2px solid #10b981;
        }
        
        .message.warning {
            background-color: #fef3c7;
            color: #92400e;
            border-bottom: 2px solid #f59e0b;
        }
        
        .content {
            padding: 30px;
        }
        
        .word-count {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .word-count h3 {
            color: #4f46e5;
            font-size: 18px;
        }
        
        .count-badge {
            background: #4f46e5;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-grid {
            display: grid;
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .form-row:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        input[type="text"] {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .english-input {
            font-weight: 600;
            color: #1e293b;
        }
        
        .ipa-input {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #7c3aed;
        }
        
        .remove-btn {
            background: #ef4444;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .remove-btn:hover {
            background: #dc2626;
            transform: scale(1.05);
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #4f46e5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #cbd5e1;
        }
        
        .ipa-example {
            background: #f1f5f9;
            border-left: 4px solid #4f46e5;
            padding: 15px;
            margin-top: 20px;
            border-radius: 0 8px 8px 0;
        }
        
        .ipa-example h4 {
            color: #4f46e5;
            margin-bottom: 10px;
        }
        
        .example-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dashed #cbd5e1;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .remove-btn {
                width: 100%;
                margin-top: 10px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-keyboard"></i> Adicionar Transcrições IPA</h1>
            <p>Adicione as transcrições fonéticas para as palavras pendentes</p>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <p><i class="fas fa-info-circle"></i> <?php echo $message; ?></p>
        </div>
        <?php endif; ?>
        
        <div class="content">
            <?php if (!empty($palavras_pendentes)): ?>
            <form method="POST" action="">
                <div class="word-count">
                    <h3>Palavras Pendentes (Sem Duplicatas)</h3>
                    <span class="count-badge"><?php echo count($palavras_pendentes); ?> palavra(s) únicas</span>
                </div>
                
                <div class="form-grid" id="wordsContainer">
                    <?php foreach ($palavras_pendentes as $index => $palavra): ?>
                    <div class="form-row" data-index="<?php echo $index; ?>">
                        <div class="form-group">
                            <label for="ingles_<?php echo $index; ?>">Palavra em Inglês</label>
                            <input type="text" 
                                   id="ingles_<?php echo $index; ?>" 
                                   name="palavras[<?php echo $index; ?>][ingles]" 
                                   value="<?php echo htmlspecialchars($palavra['ingles']); ?>" 
                                   class="english-input" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="ipa_<?php echo $index; ?>">Transcrição IPA</label>
                            <input type="text" 
                                   id="ipa_<?php echo $index; ?>" 
                                   name="palavras[<?php echo $index; ?>][ipa]" 
                                   value="<?php echo htmlspecialchars($palavra['ipa'] ?? ''); ?>" 
                                   class="ipa-input" 
                                   placeholder="/transkripʃən/" 
                                   required>
                        </div>
                        
                        <button type="button" class="remove-btn" onclick="removeWord(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <input type="hidden" name="palavras_pendentes" id="palavrasPendentes" 
                       value="<?php echo htmlspecialchars(json_encode($palavras_pendentes)); ?>">
                
                <div class="ipa-example">
                    <h4><i class="fas fa-lightbulb"></i> Exemplos de Transcrição IPA</h4>
                    <div class="example-item">
                        <span>hello</span>
                        <span>/həˈloʊ/</span>
                    </div>
                    <div class="example-item">
                        <span>world</span>
                        <span>/wɜːrld/</span>
                    </div>
                    <div class="example-item">
                        <span>pronunciation</span>
                        <span>/prəˌnʌn.siˈeɪ.ʃən/</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="textareaPalavras">Lista de Palavras Pendentes (Sem Duplicatas)</label>
                    <textarea id="textareaPalavras" class="form-control" rows="10" name="textarea_palavras"><?php echo htmlspecialchars($lista_palavras_pendentes_texto); ?></textarea>
                    <button type="button" class="btn btn-secondary" onclick="copiarTexto()">
                        <i class="fas fa-copy"></i> Copiar Lista
                    </button>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='index.html'">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Todas as Transcrições
                    </button>
                </div>
                
                <button type="button" class="btn btn-primary" onclick="preencherInputs()">
                    <i class="fas fa-edit"></i> Preencher
                </button>
            </form>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>Todas as palavras já têm transcrição!</h3>
                <p>Não há palavras pendentes para adicionar.</p>
                <button class="btn btn-primary" style="margin-top: 20px;" onclick="window.location.href='index.html'">
                    <i class="fas fa-arrow-left"></i> Voltar para a Bíblia IPA
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function removeWord(button) {
            const row = button.closest('.form-row');
            const index = row.dataset.index;
            
            // Remover do array de palavras pendentes
            const palavrasPendentes = JSON.parse(document.getElementById('palavrasPendentes').value);
            palavrasPendentes.splice(index, 1);
            document.getElementById('palavrasPendentes').value = JSON.stringify(palavrasPendentes);
            
            // Remover a linha
            row.remove();
            
            // Atualizar índices das linhas restantes
            updateRowIndexes();
            
            // Atualizar contador
            updateWordCount();
            
            // Se não houver mais palavras, mostrar mensagem
            if (palavrasPendentes.length === 0) {
                location.reload();
            }
        }
        
        function updateRowIndexes() {
            const rows = document.querySelectorAll('.form-row');
            rows.forEach((row, newIndex) => {
                row.dataset.index = newIndex;
                
                // Atualizar IDs e names dos inputs
                const englishInput = row.querySelector('.english-input');
                const ipaInput = row.querySelector('.ipa-input');
                
                englishInput.id = `ingles_${newIndex}`;
                englishInput.name = `palavras[${newIndex}][ingles]`;
                
                ipaInput.id = `ipa_${newIndex}`;
                ipaInput.name = `palavras[${newIndex}][ipa]`;
            });
        }
        
        function updateWordCount() {
            const count = document.querySelectorAll('.form-row').length;
            const badge = document.querySelector('.count-badge');
            if (badge) {
                badge.textContent = `${count} palavra(s) únicas`;
            }
        }
        
        function copiarTexto() {
            const textarea = document.getElementById('textareaPalavras');
            const prompt = `me envia esta lista abaixo seguindo com a sua transcrição fonética, no formato palavra - Transcrição_IPA, sem comentário, ou argumentações\n\n` + textarea.value;
            navigator.clipboard.writeText(prompt).then(() => {
                alert('Texto copiado para a área de transferência!');
            });
        }
        
        function preencherInputs() {
            const textarea = document.getElementById('textareaPalavras');
            const linhas = textarea.value.split('\n');
            const mapaTranscricoes = {};

            // Criar um mapa com as palavras e suas transcrições
            linhas.forEach(linha => {
                const partes = linha.split(' - ');
                if (partes.length === 2) {
                    const palavra = partes[0].trim();
                    const transcricao = partes[1].trim();
                    mapaTranscricoes[palavra] = transcricao;
                }
            });

            // Preencher os inputs com base no mapa
            document.querySelectorAll('.form-row').forEach(row => {
                const inputPalavra = row.querySelector('.english-input');
                const inputIPA = row.querySelector('.ipa-input');

                if (inputPalavra && inputIPA) {
                    const palavra = inputPalavra.value.trim();
                    if (mapaTranscricoes[palavra]) {
                        inputIPA.value = mapaTranscricoes[palavra];
                    }
                }
            });

            alert('Inputs preenchidos com as transcrições correspondentes!');
        }
        
        // Auto-focus no primeiro campo IPA
        document.addEventListener('DOMContentLoaded', function() {
            const firstIpaInput = document.querySelector('.ipa-input');
            if (firstIpaInput) {
                firstIpaInput.focus();
            }
        });
    </script>
</body>
</html>