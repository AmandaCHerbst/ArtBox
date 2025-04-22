<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $senha = $_POST['senha'];

    if (isset($_SESSION['usuario']) && $_SESSION['usuario']['usuario'] === $id) {
        if (password_verify($senha, $_SESSION['usuario']['senha'])) {
            header('Location: home.php');
            exit();
        } else {
            $_SESSION['message'] = 'Senha incorreta.';
        }
    } else {
        $_SESSION['message'] = 'Usuário não encontrado.';
    }

    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
</head>
<body>
    <fieldset>
        <form action="" method="post">
            <h2>Login</h2><br>
            <label for="id">Usuário</label><br>
            <input class="input" type="text" name="id" id="id" placeholder="Nome" required><br>
            <label for="senha">Senha</label><br>
            <input class="input" type="password" name="senha" id="senha" placeholder="Senha" required><br>
            <button type="submit">Entrar</button><br>
            <button class="bnt-cad" type="button" onclick="window.location.href='index.php'">CADASTRO</button>
        </form>
    </fieldset>        
</body>
</html>
