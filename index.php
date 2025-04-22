<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $usuario = $_POST['usuario'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    if ($senha !== $confirmar_senha) {
        $_SESSION['message'] = 'Senhas não coincidem.';
        header('Location: index.php');
        exit();
    }
    $_SESSION['usuario'] = [
        'nome' => $nome,
        'usuario' => $usuario,
        'email' => $email,
        'telefone' => $telefone,
        'senha' => password_hash($senha, PASSWORD_BCRYPT) 
    ];

    $_SESSION['message'] = 'Cadastro realizado com sucesso!';
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
    <link rel="stylesheet" href="assets/css/cadastro.css">
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">

</head>
<body>
    <form action="" method="post" class="form-row">
        <fieldset>
            <h1 class="cad">CADASTRO</h1><br>
            <label for="nome">Nome</label>  
            <input type="text" name="nome" id="nome" required><br>
            <label for="usuario">Usuário</label>
            <input type="text" name="usuario" id="usuario" required><br>
            <label for="email">E-mail</label>
            <input type="email" name="email" id="email" required><br>
            <label for="telefone">Telefone</label>
            <input type="tel" name="telefone" id="telefone" required><br>
            <label for="senha">Senha</label>
            <input type="password" name="senha" id="senha" required><br>
            <label for="confirmar_senha">Confirmar Senha</label>
            <input type="password" name="confirmar_senha" id="confirmar_senha" required><br>   
            <button type="submit">Cadastrar</button>
        </fieldset>
    </form>
</body>
</html>
