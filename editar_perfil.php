<?php
session_start();
require __DIR__ . '/config/config.inc.php';

// Definições de upload (mesmas do cadastro)
define('UPLOAD_DIR', __DIR__ . '/assets/img/perfis/');
define('UPLOAD_URL', 'assets/img/perfis/');

// Autentica usuário
if (empty($_SESSION['idUSUARIO'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // Busca dados atuais
    $stmt = $pdo->prepare("SELECT nomeUSUARIO, usuario, email, telefone, endereco, cidade, estado, cep, foto_perfil FROM usuarios WHERE idUSUARIO = :id");
    $stmt->execute([':id' => $idUsuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die('Usuário não encontrado.');
    }
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Editar Perfil - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/editar_perfil.css" />
</head>
<body>
<main class="editar-perfil">
  <h1>Configurar Perfil</h1>

  <?php if (!empty($_SESSION['message'])): ?>
    <div class="alert <?= $_SESSION['msg_type'] ?>">
      <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
  <?php endif; ?>

  <form action="salvar_perfil.php" method="post" enctype="multipart/form-data">
    <div class="form-group foto-perfil-group">
      <label>Foto de Perfil Atual</label><br>
      <img src="<?= UPLOAD_URL . htmlspecialchars($user['foto_perfil']) ?>" alt="Foto de Perfil" class="foto-preview" /><br>
      <label for="fotoPerfil">Alterar Foto</label>
      <input type="file" name="fotoPerfil" id="fotoPerfil" accept="image/*">
    </div>

    <div class="form-group">
      <label for="nome">Nome</label>
      <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($user['nomeUSUARIO']) ?>" required>
    </div>

    <div class="form-group">
      <label for="usuario">Usuário</label>
      <input type="text" name="usuario" id="usuario" value="<?= htmlspecialchars($user['usuario']) ?>" required>
    </div>

    <div class="form-group">
      <label for="email">E-mail</label>
      <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required>
    </div>

    <div class="form-group">
      <label for="telefone">Telefone</label>
      <input type="tel" name="telefone" id="telefone" value="<?= htmlspecialchars($user['telefone']) ?>">
    </div>

    <div class="form-group">
      <label for="endereco">Endereço</label>
      <input type="text" name="endereco" id="endereco" value="<?= htmlspecialchars($user['endereco']) ?>">
    </div>

    <div class="form-group">
      <label for="cidade">Cidade</label>
      <input type="text" name="cidade" id="cidade" value="<?= htmlspecialchars($user['cidade']) ?>">
    </div>

    <div class="form-group">
      <label for="estado">Estado</label>
      <input type="text" name="estado" id="estado" maxlength="2" value="<?= htmlspecialchars($user['estado']) ?>">
    </div>

    <div class="form-group">
      <label for="cep">CEP</label>
      <input type="text" name="cep" id="cep" value="<?= htmlspecialchars($user['cep']) ?>">
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-primary">Salvar Alterações</button>
    </div>
  </form>
</main>
</body>
</html>
