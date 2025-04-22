<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["product-image"]["name"]);
    $uploadOk = 1;

    $check = getimagesize($_FILES["product-image"]["tmp_name"]);
    if ($check !== false) {
        $uploadOk = 1;
    } else {
        echo "Arquivo não é uma imagem.";
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["product-image"]["tmp_name"], $target_file)) {
            $produto = array(
                'nome' => $_POST['product-name'],
                'sizes' => isset($_POST['sizes']) ? $_POST['sizes'] : array(),
                'color' => $_POST['color'],
                'quantity' => $_POST['quantity'],
                'price' => $_POST['price'],
                'image' => $target_file
            );

            if (!isset($_SESSION['produtos'])) {
                $_SESSION['produtos'] = array();
            }
            $_SESSION['produtos'][] = $produto;

            $arquivo_json = 'dados.json';

            if (file_exists($arquivo_json)) {
                $json_data = file_get_contents($arquivo_json);
                $dados = json_decode($json_data, true);

                if ($dados === null && json_last_error() !== JSON_ERROR_NONE) {
                    $dados = array();
                }
            } else {
                $dados = array();
            }

            $dados['produtos'] = $_SESSION['produtos'];

            $json_data = json_encode($dados, JSON_PRETTY_PRINT);

            if (file_put_contents($arquivo_json, $json_data) === false) {
                echo "Desculpe, houve um erro ao salvar os dados no arquivo JSON.";
                exit();
            }

            header("Location: home.php");
            exit();
        } else {
            echo "Desculpe, houve um erro ao fazer o upload do arquivo.";
        }
    }
}
?>
