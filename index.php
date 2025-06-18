<?php
session_start();
include 'menu.php';
require __DIR__ . '/config/config.inc.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

// 1. Buscar top 3 categorias com mais produtos
$sqlTopCats = "
  SELECT c.idCATEGORIA, c.nomeCATEGORIA, COUNT(pc.id_produto) AS total
    FROM categorias c
    JOIN produto_categorias pc ON c.idCATEGORIA = pc.id_categoria
  GROUP BY c.idCATEGORIA
  ORDER BY total DESC
  LIMIT 3";
$topCats = $pdo->query($sqlTopCats)->fetchAll(PDO::FETCH_ASSOC);

// 2. Para cada categoria, buscar produtos
$produtosPorCategoria = [];
foreach ($topCats as $cat) {
    $stmtP = $pdo->prepare(
      "SELECT p.* 
         FROM produtos p
         JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
         WHERE pc.id_categoria = :cat
         LIMIT 10"
    );
    $stmtP->execute([':cat' => $cat['idCATEGORIA']]);
    $produtosPorCategoria[$cat['nomeCATEGORIA']] = $stmtP->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Carregar busca geral para sessão "Recomendados"
$busca = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = [];
if ($busca !== '') {
    // ... mantêm sua lógica de busca existente ...
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE nomePRODUTO LIKE :q");
    $stmt->execute([':q' => "%$busca%"]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $produtos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Loja ARTBOX</title>
  <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>

  <!-- Seções por categoria -->
  <?php foreach ($produtosPorCategoria as $catName => $items): ?>
    <section class="cat-section">
      <h2><?= htmlspecialchars($catName) ?></h2>
      <div class="cat-row">
        <?php foreach ($items as $p): ?>
          <div class="product-card">
            <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
              <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="">
            </a>
            <div class="card-body">
                  <h3 class="product-title">
                      <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>"><?= htmlspecialchars($p['nomePRODUTO']) ?></a>
                  </h3>
                      <p class="product-price">R$ <?= number_format($p['precoPRODUTO'], 2, ',', '.') ?></p>
                  <button class="add-cart-btn" data-id="<?= $p['idPRODUTO'] ?>" data-nome="<?= htmlspecialchars($p['nomePRODUTO']) ?>">Adicionar ao Carrinho</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <!-- Recomendados ou busca geral -->
  <?php if ($busca): ?>
    <h1>Resultados para '<?= htmlspecialchars($busca) ?>'</h1>
    <div class="grid">
      <?php foreach ($produtos as $p): ?>
        <div class="product-card">
          <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
            <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="">
          </a>
          <div class="card-body">
            <h3><a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>"><?= htmlspecialchars($p['nomePRODUTO']) ?></a></h3>
            <p>R$ <?= number_format($p['precoPRODUTO'],2,',','.') ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <!-- Modal de seleção -->
<div class="modal" id="modal-selecao">
  <div class="modal-content">
    <h3 id="modal-nome-produto">Produto</h3>
    <form action="carrinho_adicionar.php" method="post">
      <input type="hidden" name="id_produto" id="modal-id-produto">
      
      <label for="tamanho">Tamanho:</label>
      <select name="tamanho" id="modal-tamanho" required>
        <option value="">Selecione</option>
        <option value="P">P</option>
        <option value="M">M</option>
        <option value="G">G</option>
      </select>

      <label for="cor">Cor:</label>
      <select name="cor" id="modal-cor" required>
        <option value="">Selecione</option>
        <option value="Vermelho">Vermelho</option>
        <option value="Preto">Preto</option>
        <option value="Azul">Azul</option>
      </select>

      <label for="quantidade">Quantidade:</label>
      <input type="number" name="quantidade" id="modal-quantidade" value="1" min="1" required>

      <button type="submit" class="btn-primary">Adicionar</button>
      <button type="button" id="modal-close">Cancelar</button>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('.add-cart-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const nome = btn.dataset.nome;

    document.getElementById('modal-id-produto').value = id;
    document.getElementById('modal-nome-produto').innerText = nome;

    document.getElementById('modal-selecao').style.display = 'flex';
  });
});

document.getElementById('modal-close').addEventListener('click', () => {
  document.getElementById('modal-selecao').style.display = 'none';
});
</script>

</body>
</html>