<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica se todos os campos existem no POST
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';
    $tipo = $_POST['tipo'] ?? 'normal';

    // Valida senha
    if ($senha !== $confirmar) {
        $_SESSION['message'] = "Senhas não coincidem.";
        header("Location: cadastro_usuario.php");
        exit();
    }

    // Conexão corrigida com o nome do banco certo
    $conn = new mysqli("localhost", "root", "", "ArtBoxBanco");

    if ($conn->connect_error) {
        die("Erro na conexão: " . $conn->connect_error);
    }

    // Verifica se o e-mail já existe
    $stmt = $conn->prepare("SELECT idUSUARIO FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $_SESSION['message'] = "E-mail já cadastrado.";
        header("Location: cadastro_usuario.php");
        exit();
    }

    // Hash da senha
    $hash = password_hash($senha, PASSWORD_BCRYPT);

    // Cadastro no banco - corrigido para incluir 'usuario'
   $stmt = $conn->prepare("INSERT INTO usuarios (nomeUSUARIO, email, senha, telefone, tipo_usuario) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $nome, $email, $hash, $telefone, $tipo);

    $stmt->execute();

    $_SESSION['message'] = "Cadastro realizado com sucesso!";
    header("Location: login.php");
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
            <label for="tipo">Tipo de usuário</label>
            <select name="tipo" id="tipo" required>
                <option value="normal">Usuário Comum</option>
                <option value="artesao">Artesão</option>
            </select><br>
            <label for="senha">Senha</label>
            <input type="password" name="senha" id="senha" required><br>
            <label for="confirmar_senha">Confirmar Senha</label>
            <input type="password" name="confirmar_senha" id="confirmar_senha" required><br>   
            <button type="submit">Cadastrar</button>
        </fieldset>
    </form>
</body>
</html>
