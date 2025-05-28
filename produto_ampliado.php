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

$categorias = [];
if (!empty($produto['categorias'])) {
    $categorias = explode(',', $produto['categorias']);
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
  <div class="page-container">
    <div class="produto-detalhe-container">
      <div class="imagem-ampliada">
        <?php if (!empty($produto['imagemPRODUTO'])): ?>
          <img src="<?= htmlspecialchars($produto['imagemPRODUTO']) ?>"
               alt="<?= htmlspecialchars($produto['nomePRODUTO']) ?>">
        <?php else: ?>
          <p>Imagem não disponível</p>
        <?php endif; ?>
      </div>

      <div class="info-produto">
        <h1><?= htmlspecialchars($produto['nomePRODUTO']) ?></h1>
        <p class="preco">
          Preço: R$ <?= number_format($produto['precoPRODUTO'], 2, ',', '.') ?>
        </p>

        <p class="descricao">
          <?= nl2br(htmlspecialchars($produto['descricaoPRODUTO'])) ?>
        </p>

        <?php if (!empty($produto['tamanhos_disponiveis'])): ?>
          <p>
            <strong>Tamanhos disponíveis:</strong>
            <?= htmlspecialchars($produto['tamanhos_disponiveis']) ?>
          </p>
        <?php endif; ?>

        <?php if (!empty($produto['cores_disponiveis'])): ?>
          <p>
            <strong>Cores disponíveis:</strong>
            <?= htmlspecialchars($produto['cores_disponiveis']) ?>
          </p>
        <?php endif; ?>

        <?php if (!empty($categorias)): ?>
          <p>
            <strong>Categorias:</strong>
            <?= htmlspecialchars(implode(', ', $categorias)) ?>
          </p>
        <?php endif; ?>

        <p class="estoque">
          Estoque disponível: <?= $produto['quantidade'] ?>
        </p>

        <form action="cart.php" method="post" class="action-form">
          <input type="hidden" name="product_id" value="<?= $produto['idPRODUTO'] ?>">
          <label for="quantity">Quantidade:</label>
          <input type="number" name="quantity" id="quantity"
                 min="1" max="<?= $produto['quantidade'] ?>" value="1">
          <button type="submit" class="btn btn-primary">
            Adicionar ao Carrinho
          </button>
        </form>

        <form action="favoritar.php" method="post" class="action-form">
          <input type="hidden" name="id_produto" value="<?= $produto['idPRODUTO'] ?>">
          <button type="submit" class="btn btn-secondary">
            Favoritar
          </button>
        </form>
      </div>
    </div>

    <aside class="recomendacoes">
      <h2>Recomendações</h2>
      <p>Ainda não há recomendações implementadas.</p>
    </aside>
  </div>
</body>
</html>
