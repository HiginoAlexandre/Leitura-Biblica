<?php
// Array com os diretórios que você deseja explorar
$directories = [
    'http://localhost/Videos/',
    'http://localhost/Music/',
    'http://localhost/Imagens/',
    'http://localhost/Documentos/'
];

// Função para listar arquivos e diretórios e exibir na página
function listarDiretorio($diretorio) {
    $conteudo = scandir($diretorio);
    echo "<ul>";
    foreach ($conteudo as $item) {
        // Excluir arquivos e diretórios ocultos
        if ($item != "." && $item != "..") {
            // Verificar se é um diretório
            if (is_dir($diretorio . $item)) {
                echo "<li><a href=\"?dir=$diretorio$item\">$item</a></li>";
            } else {
                // Se for um arquivo de vídeo, exibir um player de vídeo
                $extensao = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if ($extensao == 'mp4' || $extensao == 'avi' || $extensao == 'mov' || $extensao == 'wmv') {
                    echo "<li><video width='320' height='240' controls><source src='$diretorio$item' type='video/mp4'>Seu navegador não suporta o elemento video.</video></li>";
                } else {
                    // Se for outro tipo de arquivo, apenas exibir o nome
                    echo "<li>$item</li>";
                }
            }
        }
    }
    echo "</ul>";
}

// Se um diretório foi selecionado, exibir seu conteúdo
if (isset($_GET['dir'])) {
    $diretorioSelecionado = $_GET['dir'];
    echo "<h2>Conteúdo de $diretorioSelecionado</h2>";
    listarDiretorio($diretorioSelecionado);
} else {
    // Se nenhum diretório foi selecionado, exibir links para os diretórios principais
    echo "<h2>Selecione um diretório:</h2>";
    echo "<ul>";
    foreach ($directories as $dir) {
        $nomeDir = basename($dir);
        echo "<li><a href=\"?dir=$dir\">$nomeDir</a></li>";
    }
    echo "</ul>";
}
?>
