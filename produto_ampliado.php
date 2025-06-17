<?php
session_start();
include "menu.php";
require __DIR__ . '/config/config.inc.php';
require_once __DIR__ . '/classes/Produto.class.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$idProduto = (int) $_GET['id'];
$produtoObj = new Produto($pdo);
$produto    = $produtoObj->buscarPorId($idProduto);

if (!$produto) {
    echo "<p>Produto não encontrado.</p>";
    exit;
}

$categorias = !empty($produto['categorias'])
    ? explode(',', $produto['categorias'])
    : [];

$recomendados = [];
if ($categorias) {
    $placeholders = rtrim(str_repeat('?,', count($categorias)), ',');
    $sqlRec = "
        SELECT DISTINCT p.*
        FROM produtos p
        JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
        JOIN categorias c ON pc.id_categoria = c.idCATEGORIA
        WHERE c.nomeCATEGORIA IN ($placeholders)
          AND p.idPRODUTO != ?
        LIMIT 4";
    $params = array_merge($categorias, [$idProduto]);
    $stmtRec = $pdo->prepare($sqlRec);
    $stmtRec->execute($params);
    $recomendados = $stmtRec->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($produto['nomePRODUTO']) ?> - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/produto_ampliado.css">
</head>
<body>
<div class="pagina-produto">
  <div class="produto-detalhes">
    <header>
      <div class="imagem-ampliada">
        <?php if (!empty($produto['imagemPRODUTO'])): ?>
          <img src="<?= htmlspecialchars($produto['imagemPRODUTO']) ?>"
               alt="<?= htmlspecialchars($produto['nomePRODUTO']) ?>">
        <?php else: ?>
          <img src="assets/img/placeholder.png" alt="Sem imagem">
        <?php endif; ?>
      </div>
      <div class="info-produto-header">
        <h1><?= htmlspecialchars($produto['nomePRODUTO']) ?></h1>
        <p class="preco">
          R$ <?= number_format($produto['precoPRODUTO'], 2, ',', '.') ?>
        </p>
        <p class="descricao">
          <?= nl2br(htmlspecialchars($produto['descricaoPRODUTO'])) ?>
        </p>
        <?php if (!empty($produto['tamanhos_disponiveis'])): ?>
          <p><strong>Tamanhos:</strong> <?= htmlspecialchars($produto['tamanhos_disponiveis']) ?></p>
        <?php endif; ?>
        <?php if (!empty($produto['cores_disponiveis'])): ?>
          <p><strong>Cores:</strong> <?= htmlspecialchars($produto['cores_disponiveis']) ?></p>
        <?php endif; ?>
        <p class="estoque">Estoque: <?= $produto['quantidade'] ?></p>
        <form action="cart.php" method="post" class="action-form">
          <input type="hidden" name="product_id" value="<?= $produto['idPRODUTO'] ?>">
          <label for="quantity">Quantidade:</label>
          <input type="number" name="quantity" id="quantity" min="1" max="<?= $produto['quantidade'] ?>" value="1">
          <button type="submit" class="btn btn-primary">Adicionar ao Carrinho</button>
        </form>
        <form action="favoritar.php" method="post" class="action-form">
          <input type="hidden" name="id_produto" value="<?= $produto['idPRODUTO'] ?>">
          <button type="submit" class="btn btn-secondary">Favoritar</button>
        </form>
      </div>
    </header>
  </div>
  <aside class="recomendacoes">
    <h2>Recomendações</h2>
    <?php if ($recomendados): ?>
      <div>
        <?php foreach ($recomendados as $rec): ?>
          <a href="produto_ampliado.php?id=<?= $rec['idPRODUTO'] ?>" class="recomendado-item">
            <img src="<?= htmlspecialchars($rec['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($rec['nomePRODUTO']) ?>">
            <div class="recomendado-info">
              <h4><?= htmlspecialchars($rec['nomePRODUTO']) ?></h4>
              <p>R$ <?= number_format($rec['precoPRODUTO'], 2, ',', '.') ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p>Não há recomendações disponíveis.</p>
    <?php endif; ?>
  </aside>
</div>
</body>
</html>
