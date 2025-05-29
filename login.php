<?php
session_start();
require __DIR__ . '/config/config.inc.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

$redirect = 'index.php';
if (!empty($_GET['redirect']) && preg_match('/^[\w\-]+\.php$/', $_GET['redirect'])) {
    $redirect = $_GET['redirect'];
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['id']   ?? '';
    $senha   = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare("
        SELECT idUSUARIO, nomeUSUARIO, senha, tipo_usuario
        FROM usuarios
        WHERE usuario = :usuario
    ");
    $stmt->execute([':usuario' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['idUSUARIO']    = $user['idUSUARIO'];
        $_SESSION['nomeUSUARIO']  = $user['nomeUSUARIO'];
        $_SESSION['tipo_usuario'] = $user['tipo_usuario'];

        header("Location: $redirect");
        exit;
    } else {
        $errorMessage = "Usuário ou senha inválidos.";
    }
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
    <h1>Entrar</h1>

    <?php if ($errorMessage): ?>
      <div class="error-msg"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <form action="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>"
          method="post" class="login-form">
      <div class="form-group">
        <label for="id">Usuário</label>
        <input type="text" id="id" name="id" required>
      </div>
      <div class="form-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required>
      </div>
      <button type="submit" class="btn-login">Entrar</button>
      <button type="button" class="btn-cad" onclick="location.href='cadastro_usuario.php'">
        Cadastrar-se
      </button>
    </form>

    <p><a href="esqueci_senha.php">Esqueci minha senha</a></p>
  </div>
</body>
</html>
