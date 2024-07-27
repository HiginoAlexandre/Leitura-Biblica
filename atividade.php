<?php
    $username = "root";
    $servername = "localhost";
    $password = "";
    $dbname = "leiturabiblica";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error){
        echo "erro na conexão";
    }else {
        $sql = "SELECT * FROM ipa WHERE ingles = \"noAny\";";
        $results = $conn->query($sql);
        $palavra = "twenty-five";
        $palavras = explode("-" $palavra);

        for($palavras as $palavras_individual){
            $palavra_minuscula = strtolower(preg_replace("/[^\w\s]|[\d]/", "", $palavras_individual));

        }
        echo $palavra_minuscula;
        // while ($row = $results->fetch_assoc()) {
        //    echo $row["ingles"]."<br>";
        // };

    }
    
?>