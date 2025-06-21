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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$idArtesao = (int)$_GET['id'];

$stmtUser = $pdo->prepare("SELECT nomeUSUARIO FROM usuarios WHERE idUSUARIO = :id");
$stmtUser->execute([':id' => $idArtesao]);
$usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
if (!$usuario) {
    echo "<p>Usuário não encontrado.</p>";
    exit;
}

$stmtProd = $pdo->prepare("SELECT * FROM produtos WHERE id_artesao = :id");
$stmtProd->execute([':id' => $idArtesao]);
$produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil de <?= htmlspecialchars($usuario['nomeUSUARIO']) ?> - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/perfil_artesao.css">
  <style>
  .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
  .modal-content { background: #fff; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; }
  .btn-primary { background: #43a047; color: #fff; border: none; padding: 12px; border-radius: 6px; cursor: pointer; }
  .btn-secondary { background: #fbc02d; color: #212529; border: none; padding: 12px; border-radius: 6px; cursor: pointer; }
  .add-cart-btn { background: #43a047; color: #fff; border: none; padding: 10px 14px; border-radius: 4px; cursor: pointer; }
  .add-cart-btn:hover { background: #388e3c; }
  </style>
</head>
<body>
  <header>
    <img src="assets/img/perfil.png" alt="Foto do Artesão">
    <div>
      <h1>Perfil de <?= htmlspecialchars($usuario['nomeUSUARIO']) ?></h1>
      <nav> <br>
        <a href="index.php">Voltar as compras</a>
      </nav>
    </div>
  </header>

  <main>
    <section>
      <h2>Produtos</h2>
      <div class="grid">
        <?php if ($produtos): ?>
          <?php foreach ($produtos as $p): ?>
            <?php
              $stmtVar = $pdo->prepare("SELECT idVARIANTE, tamanho, cor, estoque FROM variantes WHERE id_produto = ?");
              $stmtVar->execute([$p['idPRODUTO']]);
              $pVars = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="produto-card" data-variantes='<?= json_encode($pVars, JSON_HEX_TAG) ?>'>
              <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
                <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>">
                <h3><?= htmlspecialchars($p['nomePRODUTO']) ?></h3>
              </a>
              <p><?= htmlspecialchars($p['descricaoPRODUTO']) ?></p>
              <p><strong>R$ <?= number_format($p['precoPRODUTO'],2,',','.') ?></strong></p>
              <button class="add-cart-btn"
                      data-id="<?= $p['idPRODUTO'] ?>"
                      data-nome="<?= htmlspecialchars($p['nomePRODUTO']) ?>">
                Adicionar ao Carrinho
              </button>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Este artesão ainda não cadastrou produtos.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>

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
      <div style="display:flex; gap:10px; margin-top:15px;">
        <button id="modal-add" class="btn-primary">Adicionar</button>
        <button id="modal-close" class="btn-secondary">Cancelar</button>
      </div>
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
        const card = btn.closest('.produto-card');
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
