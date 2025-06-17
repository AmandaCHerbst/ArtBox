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


$busca = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = [];
if ($busca !== '') {
    $palavras = preg_split('/\s+/', $busca);
    $clauses = [];
    foreach ($palavras as $i => $palavra) {
        $clauses[] = "(nomePRODUTO LIKE :t$i OR descricaoPRODUTO LIKE :t$i OR cores_disponiveis LIKE :t$i)";
        $params[":t$i"] = "%$palavra%";
    }
    $sql = "SELECT * FROM produtos WHERE " . implode(' AND ', $clauses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query("SELECT * FROM produtos");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} 

$variantsData = [];
foreach ($produtos as $p) {
    $stmtVar = $pdo->prepare("SELECT idVARIANTE, tamanho, cor, estoque FROM variantes WHERE id_produto = ?");
    $stmtVar->execute([$p['idPRODUTO']]);
    $variantsData[$p['idPRODUTO']] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
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
    <h1><?= $busca ? "Resultados para '".htmlspecialchars($busca)."'" : 'Recomendados' ?></h1>
    <div class="grid">
        <?php foreach ($produtos as $p): ?>
          <div class="product-card">
            <?php if ($p['imagemPRODUTO']): ?>
              <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
                <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="">
              </a>
            <?php endif; ?>
            <div class="card-body">
              <h2 class="product-title">
                <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>" style="text-decoration: none; color: inherit;">
                  <?= htmlspecialchars($p['nomePRODUTO']) ?>
                </a>
              </h2>
              <p class="product-price">R$ <?= number_format($p['precoPRODUTO'],2,',','.') ?></p>
              <button class="add-cart-btn" data-id="<?= $p['idPRODUTO'] ?>">Adicionar ao carrinho</button>
            </div>
          </div>
        <?php endforeach; ?>
    </div>

    <div id="cart-modal" class="modal">
      <div class="modal-content">
        <h3>Adicionar ao Carrinho</h3>
        <form id="modal-form" method="post" action="cart.php">
          <input type="hidden" name="variant_id" id="modal-variant-id">
          <label>Tamanho:
            <select id="modal-tamanho" name="tamanho" required></select>
          </label>
          <label>Cor:
            <select id="modal-cor" name="cor" required></select>
          </label>
          <p id="modal-stock">Estoque: -</p>
          <label>Qtd:
            <input type="number" name="quantity" id="modal-quantity" min="1" value="1" required>
          </label>
          <button type="submit" class="btn-primary">Adicionar</button>
          <button type="button" id="modal-close">Cancelar</button>
        </form>
      </div>
    </div>

    <script>
      const variants = <?= json_encode($variantsData) ?>;
      const modal = document.getElementById('cart-modal');
      const selTam = document.getElementById('modal-tamanho');
      const selCor = document.getElementById('modal-cor');
      const stockInfo = document.getElementById('modal-stock');
      const inputVar = document.getElementById('modal-variant-id');
      const inputQty = document.getElementById('modal-quantity');
      let currentVars = [];

      document.querySelectorAll('.add-cart-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const pid = btn.dataset.id;
          currentVars = variants[pid];
          const sizes = [...new Set(currentVars.map(v => v.tamanho))];
          selTam.innerHTML = '<option value="">Selecione</option>' + sizes.map(t => `<option>${t}</option>`).join('');
          selCor.innerHTML = '<option value="">Selecione</option>';
          stockInfo.textContent = 'Estoque: -';
          selTam.value = '';
          selCor.value = '';
          inputQty.value = 1;
          inputVar.value = '';
          modal.style.display = 'flex';
        });
      });

      selTam.addEventListener('change', () => {
        const selectedSize = selTam.value;
        const filteredColors = [...new Set(currentVars.filter(v => v.tamanho === selectedSize).map(v => v.cor))];
        selCor.innerHTML = '<option value="">Selecione</option>' + filteredColors.map(c => `<option>${c}</option>`).join('');
        updateModal();
      });

      selCor.addEventListener('change', updateModal);

      function updateModal() {
        const t = selTam.value;
        const c = selCor.value;
        const v = currentVars.find(x => x.tamanho === t && x.cor === c);
        const st = v ? v.estoque : 0;
        stockInfo.textContent = 'Estoque: ' + st;
        inputQty.max = st;
        inputVar.value = v ? v.idVARIANTE : '';
      }

      document.getElementById('modal-close').addEventListener('click', () => modal.style.display = 'none');
    </script>
</body>
</html>