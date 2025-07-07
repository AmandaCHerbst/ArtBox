<?php
session_start();
require __DIR__ . '/config/config.inc.php';
include 'menu.php';

if (empty($_SESSION['idUSUARIO']) || !in_array($_SESSION['tipo_usuario'], ['normal', 'artesao'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['produto']) || !is_numeric($_GET['produto'])) {
    echo "<p>Produto inválido.</p>";
    exit;
}

$idProduto = (int) $_GET['produto'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obter dados do produto indisponível
    $stmtProduto = $pdo->prepare("SELECT nome_tipologia, nome_especificacao FROM produtos WHERE idPRODUTO = :id");
    $stmtProduto->execute([':id' => $idProduto]);
    $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        echo "<p>Produto não encontrado.</p>";
        exit;
    }

    // Buscar produtos similares com estoque > 0
    $stmt = $pdo->prepare("SELECT DISTINCT p.idPRODUTO, p.nomePRODUTO, p.imagemPRODUTO
        FROM produtos p
        JOIN variantes v ON p.idPRODUTO = v.id_produto
        WHERE p.idPRODUTO != :id
        AND p.nome_tipologia = :tipologia
        AND p.nome_especificacao = :especificacao
        AND v.estoque > 0
        LIMIT 12");

    $stmt->execute([
        ':id' => $idProduto,
        ':tipologia' => $produto['nome_tipologia'],
        ':especificacao' => $produto['nome_especificacao']
    ]);

    $recomendados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recomendações - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/perfil_normal.css">
</head>
<body>
<main>
  <section class="perfil-usuario">
    <h1>Recomendações</h1>
    <p>Esses produtos são parecidos com o que você tentou acessar.</p>
    <a href="perfil_normal.php" class="btn">Voltar ao Perfil</a>
  </section>

  <section class="recomendados">
    <div class="grid">
      <?php if (count($recomendados) > 0): ?>
        <?php foreach ($recomendados as $r): ?>
          <div class="produto-card">
            <a href="produto_ampliado.php?id=<?= $r['idPRODUTO'] ?>">
              <img src="<?= htmlspecialchars($r['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($r['nomePRODUTO']) ?>">
              <h3><?= htmlspecialchars($r['nomePRODUTO']) ?></h3>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>Não encontramos produtos similares disponíveis.</p>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>
