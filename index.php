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

/* ============================
   Top 3 Categorias
============================ */
$sqlTopCats = "
  SELECT c.idCATEGORIA, c.nomeCATEGORIA, COUNT(pc.id_produto) AS total
    FROM categorias c
    JOIN produto_categorias pc ON c.idCATEGORIA = pc.id_categoria
    JOIN variantes v ON pc.id_produto = v.id_produto
    WHERE v.estoque > 0
    GROUP BY c.idCATEGORIA
    ORDER BY total DESC
    LIMIT 3";
$topCats = $pdo->query($sqlTopCats)->fetchAll(PDO::FETCH_ASSOC);

$produtosPorCategoria = [];
foreach ($topCats as $cat) {
    $stmtP = $pdo->prepare(
      "SELECT p.*,
              (SELECT SUM(v2.estoque) FROM variantes v2 WHERE v2.id_produto = p.idPRODUTO) AS estoque_total
         FROM produtos p
         JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
         WHERE pc.id_categoria = :cat
           AND (SELECT SUM(v3.estoque) FROM variantes v3 WHERE v3.id_produto = p.idPRODUTO) > 0
         LIMIT 20"
    );
    $stmtP->execute([':cat' => $cat['idCATEGORIA']]);
    $items = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$p) {
        $stmtVar = $pdo->prepare(
            "SELECT idVARIANTE, valor_tipologia, valor_especificacao, estoque
             FROM variantes WHERE id_produto = ? AND estoque > 0"
        );
        $stmtVar->execute([$p['idPRODUTO']]);
        $p['variantes'] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($p);
    $produtosPorCategoria[$cat['nomeCATEGORIA']] = $items;
}

/* ============================
   Ordenação
============================ */
$order = $_GET['order'] ?? 'recente';

switch ($order) {
    case 'az':
        $orderBy = "p.nomePRODUTO ASC";
        break;
    case 'za':
        $orderBy = "p.nomePRODUTO DESC";
        break;
    case 'menor_preco':
        $orderBy = "p.precoPRODUTO ASC";
        break;
    case 'maior_preco':
        $orderBy = "p.precoPRODUTO DESC";
        break;
    default:
        $orderBy = "p.idPRODUTO DESC"; // mais recentes
}

/* ============================
   Busca ou Todos os Produtos
============================ */
$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $stmtAll = $pdo->prepare(
        "SELECT DISTINCT p.*,
                (SELECT SUM(v2.estoque) FROM variantes v2 WHERE v2.id_produto = p.idPRODUTO) AS estoque_total
         FROM produtos p
         LEFT JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
         LEFT JOIN categorias c    ON c.idCATEGORIA = pc.id_categoria
         LEFT JOIN variantes v     ON v.id_produto = p.idPRODUTO
         WHERE (
              p.nomePRODUTO         LIKE :q
           OR p.descricaoPRODUTO    LIKE :q
           OR p.nome_tipologia      LIKE :q
           OR p.nome_especificacao  LIKE :q
           OR c.nomeCATEGORIA       LIKE :q
           OR v.valor_tipologia     LIKE :q
           OR v.valor_especificacao LIKE :q
         )
         AND (SELECT SUM(v3.estoque) FROM variantes v3 WHERE v3.id_produto = p.idPRODUTO) > 0
         ORDER BY $orderBy"
    );
    $stmtAll->bindValue(':q', "%{$q}%", PDO::PARAM_STR);
    $stmtAll->execute();
} else {
    $sql = "SELECT p.*,
                   (SELECT SUM(v.estoque) FROM variantes v WHERE v.id_produto = p.idPRODUTO) AS estoque_total
            FROM produtos p
            WHERE (SELECT SUM(v2.estoque) FROM variantes v2 WHERE v2.id_produto = p.idPRODUTO) > 0
            ORDER BY $orderBy";
    $stmtAll = $pdo->prepare($sql);
    $stmtAll->execute();
}

$allProducts = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

foreach ($allProducts as &$p) {
    $stmtVar = $pdo->prepare(
        "SELECT idVARIANTE, valor_tipologia, valor_especificacao, estoque
         FROM variantes WHERE id_produto = ? AND estoque > 0"
    );
    $stmtVar->execute([$p['idPRODUTO']]);
    $p['variantes'] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
}
unset($p);

/* ============================
   Rótulos padrão
============================ */
$nomeTipologia = 'Tamanho';
$nomeEspecificacao = 'Cor';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Loja ARTBOX</title>
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
  .order-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 15px 0;
}

.order-form {
  display: flex;
  align-items: center;
  gap: 8px;
}

.order-form label {
  font-size: 14px;
  font-weight: 500;
  color: #444;
}

.order-form select {
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid #ccc;
  background: #fff;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.order-form select:hover {
  border-color: #888;
}

.order-form select:focus {
  outline: none;
  border-color: #5c7cfa;   /* azul bonito */
  box-shadow: 0 0 4px rgba(92,124,250,0.5);
}
</style>
</head>
<body>
<?php if (empty($q)): ?>
  <!-- Exibe categorias -->
  <?php foreach ($produtosPorCategoria as $catName => $items): ?>
    <section class="cat-section">
      <h2><?= htmlspecialchars($catName) ?></h2>
      <div class="cat-row">
        <?php foreach ($items as $p): ?>
          <div
            class="product-card"
            data-variantes='<?= json_encode($p['variantes'], JSON_HEX_TAG) ?>'
            data-tipologia="<?= htmlspecialchars($p['nome_tipologia'] ?? $nomeTipologia, ENT_QUOTES) ?>"
            data-especificacao="<?= htmlspecialchars($p['nome_especificacao'] ?? $nomeEspecificacao, ENT_QUOTES) ?>"
          >
            <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
              <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>">
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
<?php endif; ?>

<section class="all-products">
<div class="order-bar">
  <h2><?= !empty($q) ? 'Resultados para: '.htmlspecialchars($q) : 'Todos os Produtos' ?></h2>
  <form method="get" class="order-form">
    <?php if (!empty($_GET['q'])): ?>
      <input type="hidden" name="q" value="<?= htmlspecialchars($_GET['q']) ?>">
    <?php endif; ?>
    <label for="order">Ordenar:</label>
    <select name="order" id="order" onchange="this.form.submit()">
      <option value="recente"     <?= $order==='recente'?'selected':'' ?>>Mais Recentes</option>
      <option value="az"          <?= $order==='az'?'selected':'' ?>>A-Z</option>
      <option value="za"          <?= $order==='za'?'selected':'' ?>>Z-A</option>
      <option value="menor_preco" <?= $order==='menor_preco'?'selected':'' ?>>Menor Preço</option>
      <option value="maior_preco" <?= $order==='maior_preco'?'selected':'' ?>>Maior Preço</option>
    </select>
  </form>
</div>

  <?php if (!empty($allProducts)): ?>
    <div class="grid">
      <?php foreach ($allProducts as $p): ?>
        <div
          class="product-card"
          data-variantes='<?= json_encode($p['variantes'], JSON_HEX_TAG) ?>'
          data-tipologia="<?= htmlspecialchars($p['nome_tipologia'] ?? $nomeTipologia, ENT_QUOTES) ?>"
          data-especificacao="<?= htmlspecialchars($p['nome_especificacao'] ?? $nomeEspecificacao, ENT_QUOTES) ?>"
        >
          <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
            <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>">
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
  <?php else: ?>
    <div class="no-results" style="text-align:center; margin:50px 0;">
      <p>Nenhum resultado encontrado.</p>
    </div>
  <?php endif; ?>
</section>

<!-- Modal de seleção -->
<div class="modal" id="modal-selecao">
  <div class="modal-content">
    <h3 id="modal-nome-produto">Produto</h3>
    <input type="hidden" id="modal-id-produto">
    <label for="modal-tamanho"><?= htmlspecialchars($nomeTipologia) ?>:</label>
    <select id="modal-tamanho" required><option value="">Selecione</option></select>
    <label for="modal-cor"><?= htmlspecialchars($nomeEspecificacao) ?>:</label>
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

  // helper para obter o label element
  const labelTam = document.querySelector('label[for="modal-tamanho"]');
  const labelCor = document.querySelector('label[for="modal-cor"]');

  document.querySelectorAll('.add-cart-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const card = btn.closest('.product-card');
      currentVars = JSON.parse(card.dataset.variantes || '[]');

      // definir nome do produto no modal
      document.getElementById('modal-nome-produto').innerText = btn.dataset.nome;
      document.getElementById('modal-id-produto').value = btn.dataset.id;

      // pegar rótulos de tipologia/especificacao do próprio produto (data attributes)
      const rawTip = (card.dataset.tipologia || 'Tamanho').toString().replace(/:$/, '').trim();
      const rawEsp = (card.dataset.especificacao || 'Cor').toString().replace(/:$/, '').trim();

      // atualizar labels do modal dinamicamente
      labelTam.innerText = rawTip + ':';
      labelCor.innerText = rawEsp + ':';

      // popular selects com variantes
      const sizes = [...new Set(currentVars.map(v => v.valor_tipologia))];
      selTam.innerHTML = '<option value="">Selecione</option>' + sizes.map(s => `<option>${s}</option>`).join('');
      selCor.innerHTML = '<option value="">Selecione</option>';
      stockInfo.textContent = 'Estoque: -';
      inputQty.value = 1;
      inputQty.max = 99999;
      modal.style.display = 'flex';
    });
  });

  selTam.addEventListener('change', () => {
    const size = selTam.value;
    const colors = [...new Set(currentVars.filter(v => v.valor_tipologia === size).map(v => v.valor_especificacao))];
    selCor.innerHTML = '<option value="">Selecione</option>' + colors.map(c => `<option>${c}</option>`).join('');
    stockInfo.textContent = 'Estoque: -';
    inputQty.value = 1;
    inputQty.max = 99999;
  });

  selCor.addEventListener('change', () => {
    const size = selTam.value;
    const cor = selCor.value;
    const v = currentVars.find(v => v.valor_tipologia === size && v.valor_especificacao === cor) || {};
    const st = v.estoque || 0;
    stockInfo.textContent = 'Estoque: ' + st;
    inputQty.max = st;
    inputQty.value = st > 0 ? 1 : 0;
  });

  document.getElementById('modal-close').addEventListener('click', () => modal.style.display = 'none');
  document.getElementById('modal-add').addEventListener('click', () => {
    const selected = currentVars.find(v => v.valor_tipologia === selTam.value && v.valor_especificacao === selCor.value);
    const idVar = selected ? selected.idVARIANTE : null;
    const qty = inputQty.value;
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'cart.php';
    form.innerHTML = `<input type="hidden" name="variant_id" value="${idVar}">` +
                     `<input type="hidden" name="quantity" value="${qty}">`;
    document.body.appendChild(form);
    form.submit();
  });
</script>

</body>
</html>
