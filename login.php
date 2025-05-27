<?php 
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
  <div class="login-container">
    <div class="login-header">
      <h1>Entrar</h1>
    </div>

    <?php if (!empty($errorMessage)): ?>
      <div class="error-msg"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <form class="login-form" action="" method="post">
      <div class="form-group">
        <label for="id">Usuário</label>
        <input type="text" id="id" name="id" placeholder="Nome de usuário" required>
      </div>
      <div class="form-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" placeholder="Senha" required>
      </div>
      <button type="submit" class="btn-login">Entrar</button>
      <button type="button" class="btn-cad" onclick="window.location.href='cadastro_usuario.php'">
        Cadastrar-se
      </button>
    </form>

    <div class="login-footer">
      <p><a href="esqueci_senha.php">Esqueci minha senha</a></p>
    </div>
  </div>
</body>
</html>
