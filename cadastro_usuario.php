<?php
session_start();
require __DIR__ . '/config/config.inc.php';

define('UPLOAD_DIR', __DIR__ . '/assets/img/perfis/');
define('UPLOAD_URL', 'assets/img/perfis/');

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['nome'] ?? '');
    $usuario   = trim($_POST['usuario'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telefone  = trim($_POST['telefone'] ?? '');
    $tipo      = $_POST['tipo'] ?? 'normal';
    $senha     = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';

    if ($senha !== $confirmar) {
        $_SESSION['message']  = "Senhas não coincidem.";
        $_SESSION['msg_type'] = 'error';
        header('Location: cadastro_usuario.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT idUSUARIO FROM usuarios WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['message']  = "E-mail já cadastrado.";
        $_SESSION['msg_type'] = 'error';
        header('Location: cadastro_usuario.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT idUSUARIO FROM usuarios WHERE usuario = :usuario");
    $stmt->execute([':usuario' => $usuario]);
    if ($stmt->fetch()) {
        $_SESSION['message']  = "Nome de usuário já em uso.";
        $_SESSION['msg_type'] = 'error';
        header('Location: cadastro_usuario.php');
        exit;
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);

    $fotoNome = 'default.png';

    if (!empty($_FILES['fotoPerfil']['name'])) {
        $arquivo    = $_FILES['fotoPerfil'];
        $extensao   = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg','jpeg','png','gif'];

        if (in_array($extensao, $permitidas) && $arquivo['size'] <= 2_000_000) {
            $fotoNome = uniqid('usr_') . '.' . $extensao;
            $destino  = UPLOAD_DIR . $fotoNome;

            if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
                die('Não foi possível criar a pasta de uploads.');
            }

            if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
                error_log("Falha ao mover upload para: $destino");
                $fotoNome = 'default.png';
            }
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios
         (nomeUSUARIO, usuario, email, senha, telefone, tipo_usuario, foto_perfil)
         VALUES (:nome, :usuario, :email, :senha, :tel, :tipo, :foto)"
    );

    $stmt->execute([
        ':nome'    => $nome,
        ':usuario' => $usuario,
        ':email'   => $email,
        ':senha'   => $hash,
        ':tel'     => $telefone,
        ':tipo'    => $tipo,
        ':foto'    => $fotoNome
    ]);

    if ($stmt->rowCount()) {
        $_SESSION['message']  = "Cadastro realizado com sucesso!";
        $_SESSION['msg_type'] = 'success';
        header('Location: login.php');
        exit;
    } else {
        $_SESSION['message']  = "Erro ao cadastrar.";
        $_SESSION['msg_type'] = 'error';
        header('Location: cadastro_usuario.php');
        exit;
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
      <div class="error-msg" <?php if($_SESSION['msg_type'] === 'success') echo 'style="color:#28a745;"'; ?>>
        <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <form action="" method="post" class="login-form" enctype="multipart/form-data">
      <h1 class="form-title">CADASTRO</h1>
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
        <label for="fotoPerfil">Foto de perfil</label>
        <input type="file" name="fotoPerfil" id="fotoPerfil" accept="image/*">
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

    <p class="login-footer">Já tem conta? <a href="login.php">Entrar</a></p>
  </div>
</body>
</html>