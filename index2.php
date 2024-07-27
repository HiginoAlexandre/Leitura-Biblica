<?php
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "leiturabiblica";
    
    // coneção com a base de dados usando o mysqli
    $conn = new mysqli($servername, $username, $password,$dbname);
    
    // verificar as conexão realmente resultou
    if ($conn->connect_error){
        echo "erro com a conexão com a base de dados";
    }
    if (isset($_GET['livro']) && isset($_GET['capitulo']) ) {
        // Recebe os dados do formulário
        $livro = $_GET['livro'];
        $capitulo = $_GET['capitulo'];
        $sql = "SELECT * FROM capitulo WHERE livro = '$livro' AND numero_cap = '$capitulo'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            // Exibe os resultados
            $row = $result->fetch_assoc();
            $conteudo = $row["conteudo"];
        }else{
            echo "registro não encontrado";
        }
    }else {
        $sql = "SELECT * FROM capitulo;";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $conteudo = $row["conteudo"];
                $livro = $row['livro'];
                $capitulo = $row['numero_cap'];
            }
    }
?>

<!DOCTYPE html>
<html lang="pt-br">
    
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Leitura da Bíblia</title>
        <link rel="stylesheet" href="css/estiloPadrao.css">
        <script src="js/script.js" defer></script>
        <style>
            div.inline-unit{
                display: inline-block;
            }
            .paragrafo{
                margin-bottom: 12px;
            }
            .orig_word{
                font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif;
                /* font-family: 'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif; */
            }
            .transcribed{
                color: #7e7a7a;
                font-size: smaller;
            }
            .notranscribed{
                color: red;
            }
        </style>
</head>

<body>
<div class="container">
        <h1>Leitura da Bíblia</h1>
        <div>
            <form action="" method="get">
                <select id="livro" name="livro">
                    <?php
                        $sql = "SELECT * FROM biblioteca;";
                        $results = $conn->query($sql);
                        while($row = $results->fetch_assoc()){
                            echo "<option value=\"".$row["livro"]."\" data-cap = \"".$row["capitulos"];
                            if ($row["livro"] != $livro) {
                                echo "\">";
                            } else {
                                echo "\" selected >";
                            }
                            echo $row["livro"]."</option>";
                        }
                    ?>
                </select>
                <select id="capitulo" name="capitulo">
                    <?php
                        $sql = "SELECT * FROM biblioteca WHERE livro = \"$livro\"";
                        $results = $conn->query($sql);
                        $row = $results->fetch_assoc();
                        $i = 1;
                        while ($i<=$row["capitulos"]){
                            echo "<option value=\"$i\"";
                            if ($i==$capitulo) {echo "selected";}
                            echo ">$i</option>";
                            $i++;
                            
                        }
                    ?>
                    <!-- As opções dos capítulos serão preenchidas com JavaScript -->
                </select>
                <input id="go" class="none" type="submit" value="go">
                <button type="submit" id="btn-read-full-chapter" class="btn-read-full-chapter">Ler Capítulo Completo</button>
            </form>
        </div>
        <div id="bible-text">
            <?php
                if (!isset($conteudo)) {
                    echo "erro pesquisa inválida";
                } else {
                    $texto = $conteudo;

$paragrafos = explode("\n", $texto);
echo "<h1> $livro $capitulo </h1>";
echo "<div id='transc_output'>";
foreach ($paragrafos as $paragrafo) {
    if(empty($paragrafo)){continue;}
    echo "<div class='paragrafo'>";
    $palavras = preg_split('/[\s-]+/', $paragrafo); // Alteração para considerar hífens como separadores
    foreach ($palavras as $palavra) {
        echo "<div class='inline-unit'>";
        // Converter a palavra para minúsculas
        $palavra_minuscula = strtolower(preg_replace("/[^\w\s]|[\d]/", "", $palavra));
        if(empty($palavra_minuscula)){
            echo "<div class='orig_word'>$palavra&nbsp</div>";
            echo "<div class='simbols'>".$palavra."&nbsp</div>";
        }else {
            // Substituir apóstrofos por espaços vazios
            $palavra_minuscula = str_replace("'", "", $palavra_minuscula);
            
            // Consultar o banco de dados para obter a transcrição IPA da palavra
            $sql = "SELECT * FROM ipa WHERE ingles = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $palavra_minuscula);
            $stmt->execute();
            $results = $stmt->get_result();
            if ($results->num_rows > 0) {
                $row2 = $results->fetch_assoc();
                // Exibir a palavra original e sua transcrição IPA
                echo "<div class='orig_word'>$palavra&nbsp</div>";
                echo "<div class='transcribed'>".$row2["ipa"]."&nbsp</div>";
            } else {
                // Se não houver transcrição disponível, apenas exiba a palavra original
                echo "<div class='orig_word'>$palavra&nbsp</div>";
                echo "<div class='notranscribed'>".$palavra."&nbsp</div>";
            }
        }
        echo "</div>"; // Fecha .inline-unit
    }
    echo "</div>"; // Fecha div.paragrafo
}
echo "</div>"; // Fecha div#transc_output

                echo "</div>"; // Fecha #transc_output
                }
            ?>
        </div>
    </div>
</body>

</html>


















$texto = $conteudo;

$paragrafos = explode("\n", $texto);
echo "<h1> $livro $capitulo </h1>";
echo "<div id='transc_output'>";
foreach ($paragrafos as $paragrafo) {
    if(empty($paragrafo)){continue;}
    echo "<div class='paragrafo'>";
    $palavras = preg_split('/[\s-]+/', $paragrafo); // Alteração para considerar hífens como separadores
    foreach ($palavras as $palavra) {
        echo "<div class='inline-unit'>";
        // Converter a palavra para minúsculas
        $palavra_minuscula = strtolower(preg_replace("/[^\w\s]|[\d]/", "", $palavra));
        if(empty($palavra_minuscula)){
            echo "<div class='orig_word'>$palavra&nbsp</div>";
            echo "<div class='simbols'>".$palavra."&nbsp</div>";
        }else {
            // Substituir apóstrofos por espaços vazios
            $palavra_minuscula = str_replace("'", "", $palavra_minuscula);
            
            // Consultar o banco de dados para obter a transcrição IPA da palavra
            $sql = "SELECT * FROM ipa WHERE ingles = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $palavra_minuscula);
            $stmt->execute();
            $results = $stmt->get_result();
            if ($results->num_rows > 0) {
                $row2 = $results->fetch_assoc();
                // Exibir a palavra original e sua transcrição IPA
                echo "<div class='orig_word'>$palavra&nbsp</div>";
                echo "<div class='transcribed'>".$row2["ipa"]."&nbsp</div>";
            } else {
                // Se não houver transcrição disponível, apenas exiba a palavra original
                echo "<div class='orig_word'>$palavra&nbsp</div>";
                echo "<div class='notranscribed'>".$palavra."&nbsp</div>";
            }
        }
        echo "</div>"; // Fecha .inline-unit
    }
    echo "</div>"; // Fecha div.paragrafo
}
echo "</div>"; // Fecha div#transc_output
