<?php
// Configurações de conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "leiturabiblica";

// Tratamento de erros de conexão com o banco de dados
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception(
            "Erro ao conectar ao banco de dados: " . $conn->connect_error
        );
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
    exit();
}

// Função para executar consultas SQL preparadas
function executePreparedQuery($conn, $sql, $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Erro ao preparar consulta: " . $conn->error);
    }
    if ($params) {
        $stmt->bind_param(str_repeat("s", count($params)), ...$params);
    }
    if ($stmt->execute() === false) {
        throw new Exception("Erro ao executar consulta: " . $stmt->error);
    }
    return $stmt->get_result();
}
function tratarString($conn,$term,$i)
{
    // 1. Check for hyphens:
    if (strpos($term, "-") !== false) {
        // 2. Standardize hyphens:
        $term = str_replace("--", "-", $term);
        
        // 3. Split the term based on hyphens:
        $parts = explode("-", $term);
        // 4. Transcribe each part individually:
        $tratar_parts = [];
        if ($i == 0) {
            foreach ($parts as $part) {
                // Handle each part separately:
                $tratar_parts[] = tratarString($conn,$part,0);
            }
        }else {
            foreach ($parts as $part) {
                // Handle each part separately:
                $tratar_parts[] = tratarString($conn,mostrarIPA($conn,$part),2);
            }
        }
        
        // 5. Join the transcribed parts with a hyphen:
        //return join(" ", $tratar_parts);
    } else {
        // 6. Handle words without hyphens as before:
            if ($i == 0) {
                $term_processed = preg_replace(
                    ["/\'s/", "/\'S/", "/[^\w\s]|[\d]/"],
                    "",
                    $term
                );            
            return $term_processed;
            } else {
                $term_processed = preg_replace(
                    ["/\'s/", "/\'S/", "/[^\w\s]|[\d]/"],
                    "",
                    $term
                );            
            return mostrarIPA($conn,$term_processed);
            }
            
    }
}


function mostrarIPA($conn,$termo)
{

        $sql = "SELECT * FROM ipa WHERE ingles = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro ao preparar consulta: " . $conn->error);
        }

        // Bind dos parâmetros e execução da consulta
        $stmt->bind_param("s", $termo);
        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar consulta: " . $stmt->error);
        }

        // Obtenção dos resultados da consulta
        $results = $stmt->get_result();

        // Verificação se há resultados
        if ($results->num_rows > 0) {
            // Obtenção do resultado
            $row = $results->fetch_assoc();
            return $row["ipa"];
        } else {
            return null;
        }
}


// Obter dados do formulário ou usar valores padrão
$livro = $_GET["livro"] ?? "";
$capitulo = $_GET["capitulo"] ?? "";

// Consultar o banco de dados para obter o conteúdo do capítulo
if ($livro && $capitulo) {
    try {
        $sql = "SELECT * FROM capitulo WHERE livro = ? AND numero_cap = ?";
        try {
            $result = executePreparedQuery($conn, $sql, [$livro, $capitulo]);
        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage();
            exit();
        }        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $conteudo = $row["conteudo"];
            $conteudo_pt = $row["conteudo_pt"];
        } else {
            echo "Registro não encontrado";
        }
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
        exit();
    }
} else {
    // Obter o primeiro capítulo como padrão
    try {
        $sql = "SELECT * FROM capitulo LIMIT 1";
        $result = executePreparedQuery($conn, $sql);
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $conteudo = $row["conteudo"];
            $conteudo_pt = $row["conteudo_pt"];
            $livro = $row["livro"];
            $capitulo = $row["numero_cap"];
        } else {
            echo "Nenhum capítulo encontrado";
        }
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
        exit();
    }
}

// Fechar a conexão com o banco de dados
?>

<!DOCTYPE html>
<html lang="pt-br">
    
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Leitura da Bíblia</title>
        <link rel="stylesheet" href="css/estiloPadrao.css">
        <style>
        </style>
</head>

<body>
    <div class="container">
        <h1>Leitura da Bíblia</h1>
        <div>
            <form action="" method="get">
                <select id="livro" name="livro">
                    <?php try {
                        $sql = "SELECT * FROM biblioteca";
                        $result = executePreparedQuery($conn, $sql);
                        while ($row = $result->fetch_assoc()) {
                            $selected =
                                $row["livro"] == $livro ? "selected" : "";
                            echo "<option value='{$row["livro"]}' $selected data-cap = '{$row["capitulos"]}'>{$row["livro"]}</option>";
                        }
                    } catch (Exception $e) {
                        echo "Erro: " . $e->getMessage();
                        exit();
                    } ?>
                </select>
                <select id="capitulo" name="capitulo">
                    <?php try {
                        $sql = "SELECT * FROM biblioteca WHERE livro = ?";
                        $result = executePreparedQuery($conn, $sql, [$livro]);
                        $row = $result->fetch_assoc();
                        for ($i = 1; $i <= $row["capitulos"]; $i++) {
                            $selected = $i == $capitulo ? "selected" : "";
                            echo "<option value='$i' $selected >$i</option>";
                        }
                    } catch (Exception $e) {
                        echo "Erro: " . $e->getMessage();
                        exit();
                    } ?>
                </select>
                <input id="go" class="none" type="submit" value="go">
                <button type="submit" id="btn-read-full-chapter" class="btn-read-full-chapter">Ler Capítulo Completo</button>
            </form>
        </div>
        <div id="bible-text">
            <?php
            if (!isset($conteudo)) {
                echo "Erro: pesquisa inválida";
            } else {
                $conteudo = preg_replace("/—/", " - ", $conteudo);
                echo "<h1>{$livro} {$capitulo}</h1>";
                echo "<div id='transc_output'>";

                $paragrafos = explode("\n", $conteudo);
                $paragrafos_pt = explode("\n", $conteudo_pt);

                // Verifica se o número de parágrafos em inglês e português é o mesmo
                $total_paragrafos = min(count($paragrafos), count($paragrafos_pt));

                for ($i = 0; $i < $total_paragrafos; $i++) {
                    $paragrafo = $paragrafos[$i];
                    $paragrafo_pt = $paragrafos_pt[$i];

                    if (!empty($paragrafo)) {
                        echo "<div class=\"paragraph\">";
                        echo "<div class='paragrafo'>";

                        $palavras = explode(" ", $paragrafo);
                        foreach ($palavras as $palavra) {
                            $palavra = str_replace("-", " - ", $palavra);
                            echo "<div class='inline-unit'>";
                            if (!empty($palavra)) {
                                if (empty(tratarString($conn, $palavra, 0))) {
                                    echo "<div class='simbols1'>$palavra&nbsp;</div>";
                                    echo "<div class='simbols'>$palavra&nbsp;</div>";
                                } else {
                                    echo "<div class='orig_word'>$palavra&nbsp;</div>";
                                    $ipa = tratarString($conn, $palavra, 1);
                                    $class = $ipa === null ? 'notranscribed' : 'transcribed';
                                    $ipa = $ipa === null ? $palavra : $ipa;

                                    echo "<div class='$class'>$ipa&nbsp;</div>";
                                }
                            }
                            echo "</div>"; // Fecha .inline-unit
                        }

                        echo "</div>"; // Fecha .paragrafo
                        echo "<div class=\"paragrafo_pt\">";
                        echo "<p>$paragrafo_pt</p>";
                        echo "</div>"; // fecha .paragrafo_pt
                        echo "</div>"; // fecha .paragraph
                    }
                }

                echo "</div>"; // Fecha #transc_output
            }
            ?>
        </div> <!-- fecha a div #bible-text-->
    </div>
    <script>    
var select = document.getElementById("livro");
const capituloSelect = document.getElementById('capitulo');
const btnReadFullChapter = document.getElementById('btn-read-full-chapter');
const bibleTextDiv = document.getElementById('bible-text');
const go = document.getElementById('go');

select.addEventListener('change', populateCapitulos);
capituloSelect.addEventListener('change', submeter);
function submeter() {
    go.click();
}

function populateCapitulos() {
    var selectedOption = select.options[select.selectedIndex];
    var selectedChapter = parseInt(selectedOption.getAttribute("data-cap")); // Converter para número
    capituloSelect.innerHTML = '<option value="1">Capítulo</option>';
    for (let index = 1; index <= selectedChapter; index++) { // Ajuste aqui
        const option = document.createElement('option');
        option.value = index;
        option.textContent = index;
        capituloSelect.appendChild(option);
    }
}
</script>
</body>

</html>
<?php $conn->close();
?>
