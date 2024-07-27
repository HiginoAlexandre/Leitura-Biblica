<?PHP
function getIPA($term){
    return 'example';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
    
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Leitura da Bíblia</title>
        <link rel="stylesheet" href="css/estiloPadrao.css">
        <style>
            div.inline-unit {
            display: inline-block;
        }
        
        .paragrafo {
            margin-bottom: 12px;
        }
        
        .orig_word {
            font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif;
        }

        .transcribed {
            color: #7e7a7a;
            font-size: smaller;
        }

        .notranscribed {
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
                    
                </select>
                <select id="capitulo" name="capitulo">
                    
                </select>
                <input id="go" class="none" type="submit" value="go">
                <button type="submit" id="btn-read-full-chapter" class="btn-read-full-chapter">Ler Capítulo Completo</button>
            </form>
        </div>
        <div id="bible-text">
            <?php
            if (isset($conteudo)) {
                echo "Erro: pesquisa inválida";
            } else {
                $livro = "John";
                $capitulo = 1;
                $conteudo ="Jesus Calls Philip and Nathanael 43The next day Jesus decided to leave for Galilee. Finding Philip, he said to him, “Follow me.” 44Philip, like Andrew and Peter, was from the town of Bethsaida. 45Philip found Nathanael and told him, “We have found the one Moses wrote about in the Law, and about whom the prophets also wrote—Jesus of Nazareth, the son of Joseph.” 46“Nazareth! Can anything good come from there?”";
                echo "<h1>$livro $capitulo</h1>";
                echo "<div id='transc_output'>";
                $paragrafos = explode("\n", $conteudo);
                foreach ($paragrafos as $paragrafo) {
                    if (!empty($paragrafo)) {
                        echo "<div class='paragrafo'>";
                        $palavras = explode(" ", $paragrafo);
                        foreach ($palavras as $palavra) {
                            echo "<div class='inline-unit'>";
                            if (strpos($palavra, '-') !== false) {
                                $tmp = explode("-", $palavra);
                                $ii = 0;
                                foreach ($tmp as $subpalavra) {
                                    $palavra_minuscula = preg_replace(['/´s/', '/´S/', "/[^\w\s]|[\d]/"], "", $subpalavra);
                                    if ($ii > 0) {
                                        echo "<div class='inline-unit'>"; // Abre uma nova div para cada subpalavra
                                        echo "<div class='simbols1'>-</div>";
                                        echo "<div class='simbols'>-</div>";
                                        echo "</div>"; // Fecha a div para a subpalavra
                                    }
                                    if (empty($palavra_minuscula)) {
                                        echo "<div class='inline-unit'>"; // Abre uma nova div para cada subpalavra
                                        echo "<div class='simbols1'>$subpalavra&nbsp</div>";
                                        echo "<div class='simbols'>$subpalavra&nbsp</div>";
                                        echo "</div>"; // Fecha a div para a subpalavra
                                    } else {
                                        echo "<div class='inline-unit'>"; // Abre uma nova div para cada subpalavra
                                        echo "<div class='orig_word'>$subpalavra&nbsp;</div>";
                                        $ipa = getIPA($palavra_minuscula);
                                        if ($ipa === null) {
                                            echo "<div class='notranscribed'>$subpalavra&nbsp</div>";
                                        } else {
                                            echo "<div class='transcribed'>$ipa&nbsp</div>";
                                        }
                                        echo "</div>"; // Fecha a div para a subpalavra
                                    }
                                    $ii++;
                                }
                            } else {
                                // Código para palavras sem hífen permanece inalterado
    $palavra_minuscula = preg_replace(['/´s/', '/´S/', '/[^\w\s]|[\d]/'], '', $palavra);
    if (empty($palavra_minuscula)) {
        echo "<div class='inline-unit'>"; // Abre uma nova div para cada palavra
        echo "<div class='orig_word'>$palavra&nbsp</div>";
        echo "<div class='simbols'>$palavra&nbsp</div>";
        echo "</div>"; // Fecha a div para a palavra
    } else {
        echo "<div class='inline-unit'>"; // Abre uma nova div para cada palavra
        echo "<div class='orig_word'>$palavra&nbsp</div>";
        $ipa = getIPA($palavra_minuscula);
        if ($ipa === null){
            echo "<div class='notranscribed'>$palavra&nbsp</div>";
        } else {
            echo "<div class='transcribed'>$ipa&nbsp</div>";
        }
        echo "</div>"; // Fecha a div para a palavra
    }
}
echo "</div>"; // Fecha .inline-unit

                        }
                        echo "</div>"; // Fecha .paragrafo
                    }
                }
                echo "</div>"; // Fecha #transc_output
            }
            ?>
        </div>
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
<?php
?>