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

// 2. Para cada categoria, buscar produtos e variantes
$produtosPorCategoria = [];
foreach ($topCats as $cat) {
    $stmtP = $pdo->prepare(
      "SELECT p.* FROM produtos p
         JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
         WHERE pc.id_categoria = :cat
         LIMIT 20"
    );
    $stmtP->execute([':cat' => $cat['idCATEGORIA']]);
    $items = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$p) {
        $stmtVar = $pdo->prepare("SELECT idVARIANTE, tamanho, cor, estoque FROM variantes WHERE id_produto = ?");
        $stmtVar->execute([$p['idPRODUTO']]);
        $p['variantes'] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
    }
    $produtosPorCategoria[$cat['nomeCATEGORIA']] = $items;
}

// 3. Buscar todos produtos aleatoriamente
$stmtAll = $pdo->query("SELECT p.*, (SELECT GROUP_CONCAT(tamanho,':',cor) FROM variantes v WHERE v.id_produto=p.idPRODUTO) AS variantes_raw FROM produtos p ORDER BY RAND()");
$allProducts = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
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
          <div class="product-card" data-variantes='<?= json_encode($p['variantes'], JSON_HEX_TAG) ?>'>
            <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
              <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>">
            </a>
            <div class="card-body">
              <h3 class="product-title">
                <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>"><?= htmlspecialchars($p['nomePRODUTO']) ?></a>
              </h3>
              <p class="product-price">R$ <?= number_format($p['precoPRODUTO'],2,',','.') ?></p>
              <button class="add-cart-btn" data-id="<?= $p['idPRODUTO'] ?>" data-nome="<?= htmlspecialchars($p['nomePRODUTO']) ?>">Adicionar ao Carrinho</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <!-- Todos os produtos abaixo -->
  <section class="all-products">
    <h2>Todos os Produtos</h2>
    <div class="grid">
      <?php foreach ($allProducts as $p): ?>
        <div class="product-card">
          <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
            <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>">
          </a>
          <div class="card-body">
            <h3 class="product-title">
              <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>"><?= htmlspecialchars($p['nomePRODUTO']) ?></a>
            </h3>
            <p class="product-price">R$ <?= number_format($p['precoPRODUTO'],2,',','.') ?></p>
            <button class="add-cart-btn" data-id="<?= $p['idPRODUTO'] ?>" data-nome="<?= htmlspecialchars($p['nomePRODUTO']) ?>">Adicionar ao Carrinho</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Modal de seleção -->
  <div class="modal" id="modal-selecao">
    <div class="modal-content">
      <h3 id="modal-nome-produto">Produto</h3>
      <input type="hidden" id="modal-id-produto">
      <label for="modal-tamanho">Tamanho:</label>
      <select id="modal-tamanho" required><option value="">Selecione</option></select>
      <label for="modal-cor">Cor:</label>
      <select id="modal-cor" required><option value="">Selecione</option></select>
      <p id="modal-stock">Estoque: -</p>
      <label for="modal-quantidade">Quantidade:</label>
      <input type="number" id="modal-quantidade" value="1" min="1" required>
      <button id="modal-add" class="btn-primary">Adicionar</button>
      <button id="modal-close" class="btn-secondary">Cancelar</button>
    </div>
  </div>

  <script>
  const modal = document.getElementById('modal-selecao');
  const selTam = document.getElementById('modal-tamanho');
  const selCor = document.getElementById('modal-cor');
  const stockInfo = document.getElementById('modal-stock');
  const inputQty = document.getElementById('modal-quantidade');
  let currentVars = [];

  document.querySelectorAll('.add-cart-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const card = btn.closest('.product-card');
      currentVars = JSON.parse(card.dataset.variantes || '[]');
      document.getElementById('modal-nome-produto').innerText = btn.dataset.nome;
      document.getElementById('modal-id-produto').value = btn.dataset.id;
      const sizes = [...new Set(currentVars.map(v=>v.tamanho))];
      selTam.innerHTML = '<option value="">Selecione</option>' + sizes.map(s=>`<option>${s}</option>`).join('');
      selCor.innerHTML = '<option value="">Selecione</option>';
      stockInfo.textContent = 'Estoque: -';
      inputQty.value = 1;
      modal.style.display = 'flex';
    });
  });

  selTam.addEventListener('change', ()=>{
    const size = selTam.value;
    const colors = [...new Set(currentVars.filter(v=>v.tamanho===size).map(v=>v.cor))];
    selCor.innerHTML = '<option value="">Selecione</option>' + colors.map(c=>`<option>${c}</option>`).join('');
    stockInfo.textContent = 'Estoque: -';
  });
  selCor.addEventListener('change', ()=>{
    const size = selTam.value, cor = selCor.value;
    const v = currentVars.find(v=>v.tamanho===size && v.cor===cor) || {};
    const st = v.estoque||0;
    stockInfo.textContent = 'Estoque: '+st;
    inputQty.max = st;
    inputQty.value = st>0?1:0;
  });

  document.getElementById('modal-close').addEventListener('click', ()=> modal.style.display='none');
  document.getElementById('modal-add').addEventListener('click', ()=>{
    const idVar = currentVars.find(v=>v.tamanho===selTam.value && v.cor===selCor.value)?.idVARIANTE;
    const qty = inputQty.value;
    const form = document.createElement('form');
    form.method='post'; form.action='cart.php';
    form.innerHTML = `<input type="hidden" name="variant_id" value="${idVar}">`+
                     `<input type="hidden" name="quantity" value="${qty}">`;
    document.body.appendChild(form);
    form.submit();
  });
  </script>

</body>
</html>