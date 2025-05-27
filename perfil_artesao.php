<?php
session_start();                                      
require __DIR__ . '/config/config.inc.php';
            
try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    header('Location: login.php');
    exit;
}

$idArtesao = $_SESSION['idUSUARIO'];

$stmt = $pdo->prepare(
    "SELECT nomePRODUTO AS nome,
            descricaoPRODUTO AS descricao,
            imagemPRODUTO AS imagem
     FROM produtos
     WHERE id_artesao = :id"
);
$stmt->execute([':id' => $idArtesao]);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil do Artesão - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/perfil.css">
</head>
<body>
  <header>
    <img src="caminho/para/imagem.jpg" alt="Foto do Artesão" width="150" height="150">
    <h1>Bem-vindo, <?= htmlspecialchars($_SESSION['nomeUSUARIO']) ?></h1>
    <nav>
      <a href="cadastro_produto.php">Cadastrar Novo Produto</a>
      <a href="pedidos.php">Gerenciar Pedidos</a>
      <a href="logout.php">Sair</a>
    </nav>
  </header>

  <main>
    <section>
      <h2>Meus Produtos</h2>
      <?php if (count($produtos) > 0): ?>
        <?php foreach ($produtos as $p): ?>
          <div class="produto-card">
            <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" width="100" height="100">
            <h3><?= htmlspecialchars($p['nome']) ?></h3>
            <p><?= htmlspecialchars($p['descricao']) ?></p>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>Você ainda não cadastrou nenhum produto.</p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
