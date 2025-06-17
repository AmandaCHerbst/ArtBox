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

// valida parâmetro id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$idProduto = (int) $_GET['id'];

// buscar dados do produto
$produtoObj = new Produto($pdo);
$produto    = $produtoObj->buscarPorId($idProduto);
if (!$produto) {
    echo "<p>Produto não encontrado.</p>";
    exit;
}

// buscar variantes (tamanho, cor, estoque)
$stmtVar = $pdo->prepare(
    "SELECT idVARIANTE, tamanho, cor, estoque FROM variantes WHERE id_produto = :id"
);
$stmtVar->execute([':id' => $idProduto]);
$variantes = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

// extrair listas únicas de tamanhos e cores
$tamanhos = array_unique(array_column($variantes, 'tamanho'));
$cores    = array_unique(array_column($variantes, 'cor'));

// buscar recomendações
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
          <img src="<?= htmlspecialchars($produto['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($produto['nomePRODUTO']) ?>">
        <?php else: ?>
          <img src="assets/img/placeholder.png" alt="Sem imagem">
        <?php endif; ?>
      </div>
      <div class="info-produto-header">
        <h1><?= htmlspecialchars($produto['nomePRODUTO']) ?></h1>
        <p class="preco">R$ <?= number_format($produto['precoPRODUTO'], 2, ',', '.') ?></p>
        <p class="descricao"><?= nl2br(htmlspecialchars($produto['descricaoPRODUTO'])) ?></p>

        <!-- Seleção de tamanho -->
        <div class="product-option">
          <label for="select-tamanho">Tamanho:</label>
          <select id="select-tamanho" required>
            <option value="">Selecione</option>
            <?php foreach ($tamanhos as $tam): ?>
              <option value="<?= htmlspecialchars($tam) ?>"><?= htmlspecialchars($tam) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Seleção de cor -->
        <div class="product-option">
          <label for="select-cor">Cor:</label>
          <select id="select-cor" required>
            <option value="">Selecione</option>
            <?php foreach ($cores as $cor): ?>
              <option value="<?= htmlspecialchars($cor) ?>"><?= htmlspecialchars($cor) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Exibe estoque da variante selecionada -->
        <p id="stock-info">Estoque: -</p>

        <!-- Formulário para adicionar ao carrinho -->
        <form action="cart.php" method="post" class="action-form" id="form-carrinho">
          <input type="hidden" name="variant_id" id="input-variant-id" value="">
          <div class="product-option">
            <label for="input-quantity">Quantidade:</label>
            <input type="number" name="quantity" id="input-quantity" min="1" value="1" disabled required>
          </div>
          <button type="submit" class="btn btn-primary" disabled id="btn-add-cart">Adicionar ao Carrinho</button>
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
      <?php foreach ($recomendados as $rec): ?>
        <a href="produto_ampliado.php?id=<?= $rec['idPRODUTO'] ?>" class="recomendado-item">
          <img src="<?= htmlspecialchars($rec['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($rec['nomePRODUTO']) ?>">
          <div class="recomendado-info">
            <h4><?= htmlspecialchars($rec['nomePRODUTO']) ?></h4>
            <p>R$ <?= number_format($rec['precoPRODUTO'], 2, ',', '.') ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p>Não há recomendações disponíveis.</p>
    <?php endif; ?>
  </aside>
</div>

<script>
  const variantes = <?= json_encode($variantes) ?>;
  const selTam = document.getElementById('select-tamanho');
  const selCor = document.getElementById('select-cor');
  const stockInfo = document.getElementById('stock-info');
  const inputVarId = document.getElementById('input-variant-id');
  const inputQty = document.getElementById('input-quantity');
  const btnAdd = document.getElementById('btn-add-cart');

  function updateVariant() {
    const tam = selTam.value;
    const cor = selCor.value;
    if (!tam || !cor) {
      stockInfo.textContent = 'Estoque: -';
      inputQty.disabled = true;
      btnAdd.disabled = true;
      return;
    }
    const variante = variantes.find(v => v.tamanho === tam && v.cor === cor);
    const estoque = variante ? variante.estoque : 0;
    stockInfo.textContent = 'Estoque: ' + estoque;
    if (estoque > 0) {
      inputQty.max = estoque;
      inputQty.value = 1;
      inputQty.disabled = false;
      btnAdd.disabled = false;
      inputVarId.value = variante.idVARIANTE;
    } else {
      inputQty.disabled = true;
      btnAdd.disabled = true;
      inputVarId.value = '';
    }
  }

  selTam.addEventListener('change', updateVariant);
  selCor.addEventListener('change', updateVariant);
</script>
</body>
</html>