<?php
session_start();
require __DIR__ . '/config/config.inc.php';
include 'menu.php';

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
    "SELECT idPRODUTO, nomePRODUTO AS nome,
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
  <link rel="stylesheet" href="assets/css/perfil_artesao.css">
</head>
<body>
  <header>
    <img src="assets/img/perfil.png" alt="Foto do Artesão">
    <div>
      <h1>Bem-vindo, <?= htmlspecialchars($_SESSION['nomeUSUARIO']) ?></h1>
      <nav> <br>
        <a href="cadastro_produto.php">Cadastrar Novo Produto</a>
        <!-- Relatório Geral de Vendas -->
        <a href="relatorio_vendas.php" class="btn-relatorio">Relatório de Vendas</a>
        <a href="logout.php">Sair</a>
      </nav>
    </div>
  </header>

  <main>
    <section>
      <h2>Meus Produtos</h2>
      <div class="grid">
        <?php if (count($produtos) > 0): ?>
          <?php foreach ($produtos as $p): ?>
            <div class="produto-card">
              <a href="relatorio_especifico.php?id=<?= $p['idPRODUTO'] ?>">
                <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>">
                <h3><?= htmlspecialchars($p['nome']) ?></h3>
                <p><?= htmlspecialchars($p['descricao']) ?></p>
              </a>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Você ainda não cadastrou nenhum produto.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>