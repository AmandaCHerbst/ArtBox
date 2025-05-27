<?php
/*
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
*/
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
  <div class="login-container">
    <?php if (!empty($_SESSION['message'])): ?>
      <div class="error-msg" <?php if($_SESSION['msg_type']==='success') echo 'style="color:#28a745;"'; ?>>
        <?= htmlspecialchars($_SESSION['message']) ?>
      </div>
      <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <form action="" method="post" class="login-form">
        <div class="login-header">
          <h1 class="form-title">CADASTRO</h1>
        </div>

        <div class="form-group">
          <label for="nome" class="form-label">Nome</label>
          <input type="text" name="nome" id="nome" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="usuario" class="form-label">Usuário</label>
          <input type="text" name="usuario" id="usuario" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="email" class="form-label">E-mail</label>
          <input type="email" name="email" id="email" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="telefone" class="form-label">Telefone</label>
          <input type="tel" name="telefone" id="telefone" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="tipo" class="form-label">Tipo de usuário</label>
          <select name="tipo" id="tipo" class="form-select" required>
            <option value="normal">Usuário Comum</option>
            <option value="artesao">Artesão</option>
          </select>
        </div>
        <div class="form-group">
          <label for="senha" class="form-label">Senha</label>
          <input type="password" name="senha" id="senha" class="form-input" required>
        </div>
        <div class="form-group">
          <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
          <input type="password" name="confirmar_senha" id="confirmar_senha" class="form-input" required>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-login">Cadastrar</button>
        </div>
    </form>

    <div class="login-footer">
      <p>Já tem conta? <a href="login.php">Entrar</a></p>
    </div>
  </div>
</body>
</html>