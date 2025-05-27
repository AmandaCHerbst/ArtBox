<?php
session_start();                                       
require __DIR__ . '/config/config.inc.php';
             
try {
    $pdo = new PDO(DSN, USUARIO, SENHA);                
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = $_POST['nome'] ?? '';
    $usuario    = $_POST['usuario'] ?? '';
    $email      = $_POST['email'] ?? '';
    $telefone   = $_POST['telefone'] ?? '';
    $tipo       = $_POST['tipo'] ?? 'normal';
    $senha      = $_POST['senha'] ?? '';
    $confirmar  = $_POST['confirmar_senha'] ?? '';

    if ($senha !== $confirmar) {
        $_SESSION['message']  = "Senhas não coincidem.";
        $_SESSION['msg_type'] = 'error';
        header("Location: cadastro_usuario.php");
        exit();
    }

    $stmt = $pdo->prepare("SELECT idUSUARIO FROM usuarios WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['message']  = "E-mail já cadastrado.";
        $_SESSION['msg_type'] = 'error';
        header("Location: cadastro_usuario.php");
        exit();
    }

    $stmt = $pdo->prepare("SELECT idUSUARIO FROM usuarios WHERE usuario = :user");
    $stmt->execute([':user' => $usuario]);
    if ($stmt->fetch()) {
        $_SESSION['message']  = "Nome de usuário já em uso.";
        $_SESSION['msg_type'] = 'error';
        header("Location: cadastro_usuario.php");
        exit();
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);

    // Insere usuário
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios
         (nomeUSUARIO, usuario, email, senha, telefone, tipo_usuario)
         VALUES (:nome, :user, :email, :senha, :tel, :tipo)"
    );
    $stmt->execute([
        ':nome'  => $nome,
        ':user'  => $usuario,
        ':email' => $email,
        ':senha' => $hash,
        ':tel'   => $telefone,
        ':tipo'  => $tipo
    ]);

    if ($stmt->rowCount()) {
        $_SESSION['message']  = "Cadastro realizado com sucesso!";
        $_SESSION['msg_type'] = 'success';
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['message']  = "Erro ao cadastrar.";
        $_SESSION['msg_type'] = 'error';
        header("Location: cadastro_usuario.php");
        exit();
    }
}
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
          <label for="nome">Nome</label>
          <input type="text" name="nome" id="nome" required>
        </div>

        <div class="form-group">
          <label for="usuario">Usuário</label>
          <input type="text" name="usuario" id="usuario" required>
        </div>

        <div class="form-group">
          <label for="email">E-mail</label>
          <input type="email" name="email" id="email" required>
        </div>

        <div class="form-group">
          <label for="telefone">Telefone</label>
          <input type="tel" name="telefone" id="telefone">
        </div>

        <div class="form-group">
          <label for="tipo">Tipo de usuário</label>
          <select name="tipo" id="tipo" required>
            <option value="normal">Usuário Comum</option>
            <option value="artesao">Artesão</option>
          </select>
        </div>

        <div class="form-group">
          <label for="senha">Senha</label>
          <input type="password" name="senha" id="senha" required>
        </div>

        <div class="form-group">
          <label for="confirmar_senha">Confirmar Senha</label>
          <input type="password" name="confirmar_senha" id="confirmar_senha" required>
        </div>

        <div class="form-actions">
          <button type="submit">Cadastrar</button>
        </div>
    </form>

    <div class="login-footer">
      <p>Já tem conta? <a href="login.php">Entrar</a></p>
    </div>
  </div>
</body>
</html>