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
        $stmtVar = $pdo->prepare("SELECT idVARIANTE, valor_tipologia, valor_especificacao, estoque FROM variantes WHERE id_produto = ?");
        $stmtVar->execute([$p['idPRODUTO']]);
        $p['variantes'] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
    }
    $produtosPorCategoria[$cat['nomeCATEGORIA']] = $items;
}

// 3. Paginação para todos produtos
$pagina = max(1, (int)($_GET['page'] ?? 1));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

if ($pagina === 1) {
    $stmtAll = $pdo->prepare("SELECT * FROM produtos ORDER BY RAND() LIMIT :lim");
    $stmtAll->bindValue(':lim', $porPagina, PDO::PARAM_INT);
} else {
    $stmtAll = $pdo->prepare("SELECT * FROM produtos ORDER BY idPRODUTO LIMIT :off, :lim");
    $stmtAll->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmtAll->bindValue(':lim', $porPagina, PDO::PARAM_INT);
}
$stmtAll->execute();
$allProducts = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
foreach ($allProducts as &$p) {
    $stmtVar = $pdo->prepare("SELECT idVARIANTE, valor_tipologia, valor_especificacao, estoque FROM variantes WHERE id_produto = ?");
    $stmtVar->execute([$p['idPRODUTO']]);
    $p['variantes'] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
}
unset($p);

// 4. Definir nomes de tipologia e especificação antes do modal
$primeiro = $allProducts[0] ?? null;
$nomeTipologia = 'Tamanho';
$nomeEspecificacao = 'Cor';
if ($primeiro) {
    [$nomeTipologia]    = explode(':', $primeiro['tamanhos_disponiveis'] ?? 'Tamanho:');
    [$nomeEspecificacao] = explode(':', $primeiro['cores_disponiveis']   ?? 'Cor:');
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
  <?php foreach ($produtosPorCategoria as $catName => $items): ?>
    <section class="cat-section">
      <h2><?= htmlspecialchars($catName) ?></h2>
      <div class="cat-row">
        <?php foreach ($items as $p): ?>
          <div
            class="product-card"
            data-variantes='<?= json_encode($p['variantes'], JSON_HEX_TAG) ?>'
          >
            <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
              <img
                src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>"
                alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>"
              >
            </a>
            <div class="card-body">
              <h3 class="product-title">
                <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
                  <?= htmlspecialchars($p['nomePRODUTO']) ?>
                </a>
              </h3>
              <p class="product-price">R$ <?= number_format($p['precoPRODUTO'],2,',','.') ?></p>
              <button
                class="add-cart-btn"
                data-id="<?= $p['idPRODUTO'] ?>"
                data-nome="<?= htmlspecialchars($p['nomePRODUTO']) ?>"
              >
                Adicionar ao Carrinho
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="all-products">
    <h2>Todos os Produtos</h2>
    <div class="grid">
      <?php foreach ($allProducts as $p): ?>
        <div
          class="product-card"
          data-variantes='<?= json_encode($p['variantes'], JSON_HEX_TAG) ?>'
        >
          <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
            <img
              src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>"
              alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>"
            >
          </a>
          <div class="card-body">
            <h3 class="product-title">
              <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
                <?= htmlspecialchars($p['nomePRODUTO']) ?>
              </a>
            </h3>
            <p class="product-price">R$ <?= number_format($p['precoPRODUTO'],2,',','.') ?></p>
            <button
              class="add-cart-btn"
              data-id="<?= $p['idPRODUTO'] ?>"
              data-nome="<?= htmlspecialchars($p['nomePRODUTO']) ?>"
            >
              Adicionar ao Carrinho
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="modal" id="modal-selecao">
    <div class="modal-content">
      <h3 id="modal-nome-produto">Produto</h3>
      <input type="hidden" id="modal-id-produto">
      <label for="modal-tamanho"><?= htmlspecialchars($nomeTipologia) ?>:</label>
      <select id="modal-tamanho" required>
        <option value="">Selecione</option>
      </select>
      <label for="modal-cor"><?= htmlspecialchars($nomeEspecificacao) ?>:</label>
      <select id="modal-cor" required>
        <option value="">Selecione</option>
      </select>
      <p id="modal-stock">Estoque: -</p>
      <label for="modal-quantidade">Quantidade:</label>
      <input
        type="number"
        id="modal-quantidade"
        value="1"
        min="1"
        required
      >
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
        const sizes = [...new Set(currentVars.map(v => v.valor_tipologia))];
        selTam.innerHTML =
          '<option value="">Selecione</option>' +
          sizes.map(s => `<option>${s}</option>`).join('');
        selCor.innerHTML = '<option value="">Selecione</option>';
        stockInfo.textContent = 'Estoque: -';
        inputQty.value = 1;
        modal.style.display = 'flex';
      });
    });

    selTam.addEventListener('change', () => {
      const size = selTam.value;
      const colors = [...new Set(
        currentVars
          .filter(v => v.valor_tipologia === size)
          .map(v => v.valor_especificacao)
      )];
      selCor.innerHTML =
        '<option value="">Selecione</option>' +
        colors.map(c => `<option>${c}</option>`).join('');
      stockInfo.textContent = 'Estoque: -';
    });

    selCor.addEventListener('change', () => {
      const size = selTam.value;
      const cor = selCor.value;
      const v = currentVars.find(
        v => v.valor_tipologia === size && v.valor_especificacao === cor
      ) || {};
      const st = v.estoque || 0;
      stockInfo.textContent = 'Estoque: ' + st;
      inputQty.max = st;
      inputQty.value = st > 0 ? 1 : 0;
    });

    document.getElementById('modal-close').addEventListener('click', () => modal.style.display = 'none');

    document.getElementById('modal-add').addEventListener('click', () => {
      const selected = currentVars.find(
        v => v.valor_tipologia === selTam.value && v.valor_especificacao === selCor.value
      );
      const idVar = selected ? selected.idVARIANTE : null;
      const qty = inputQty.value;
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'cart.php';
      form.innerHTML =
        `<input type="hidden" name="variant_id" value="${idVar}">` +
        `<input type="hidden" name="quantity" value="${qty}">`;
      document.body.appendChild(form);
      form.submit();
    });
  </script>

</body>
</html>
