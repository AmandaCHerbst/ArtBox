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

$stmtUser = $pdo->prepare("SELECT nomeUSUARIO, foto_perfil FROM usuarios WHERE idUSUARIO = :id");
$stmtUser->execute([':id' => $idArtesao]);
$usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
if (!$usuario) {
    echo "<p>Usuário não encontrado.</p>";
    exit;
}

$stmtProd = $pdo->prepare("SELECT * FROM produtos WHERE id_artesao = :id");
$stmtProd->execute([':id' => $idArtesao]);
$produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Definir rótulos a partir do primeiro produto (fallback se não houver)
$primeiroProduto = $produtos[0] ?? null;
$nomeTipologia = 'Tamanho';
$nomeEspecificacao = 'Cor';
if ($primeiroProduto) {
    if (!empty($primeiroProduto['nome_tipologia'])) {
        $parts = explode(':', $primeiroProduto['nome_tipologia'], 2);
        $nomeTipologia = trim($parts[0]) ?: $nomeTipologia;
    }
    if (!empty($primeiroProduto['nome_especificacao'])) {
        $parts = explode(':', $primeiroProduto['nome_especificacao'], 2);
        $nomeEspecificacao = trim($parts[0]) ?: $nomeEspecificacao;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil de <?= htmlspecialchars($usuario['nomeUSUARIO']) ?> - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/perfil_publico.css">
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
  <img src="assets/img/perfis/<?= htmlspecialchars($usuario['foto_perfil']) ?>" alt="Foto de perfil" />
  <div>
    <h1>Perfil de <?= htmlspecialchars($usuario['nomeUSUARIO']) ?></h1>
    <nav><br><a href="index.php">Voltar as compras</a></nav>
  </div>
</header>

<main>
  <section>
    <h2>Produtos</h2>
    <div class="grid">
      <?php if ($produtos): ?>
        <?php foreach ($produtos as $p): ?>
          <?php
            $stmtVar = $pdo->prepare("SELECT idVARIANTE, valor_tipologia, valor_especificacao, estoque FROM variantes WHERE id_produto = ?");
            $stmtVar->execute([$p['idPRODUTO']]);
            $pVars = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
            // preparar rótulos por produto (fallback para os nomes globais)
            $rtTip = 'Tamanho';
            $rtEsp = 'Cor';
            if (!empty($p['nome_tipologia'])) {
                $parts = explode(':', $p['nome_tipologia'], 2);
                $rtTip = trim($parts[0]) ?: $rtTip;
            } else {
                $rtTip = $nomeTipologia;
            }
            if (!empty($p['nome_especificacao'])) {
                $parts = explode(':', $p['nome_especificacao'], 2);
                $rtEsp = trim($parts[0]) ?: $rtEsp;
            } else {
                $rtEsp = $nomeEspecificacao;
            }
          ?>
          <div
            class="produto-card"
            data-variantes='<?= json_encode($pVars, JSON_HEX_TAG) ?>'
            data-tipologia="<?= htmlspecialchars($rtTip, ENT_QUOTES) ?>"
            data-especificacao="<?= htmlspecialchars($rtEsp, ENT_QUOTES) ?>"
          >
            <a href="produto_ampliado.php?id=<?= $p['idPRODUTO'] ?>">
              <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>">
              <h3><?= htmlspecialchars($p['nomePRODUTO']) ?></h3>
            </a>
            <p><strong>R$ <?= number_format($p['precoPRODUTO'],2,',','.') ?></strong></p><br>
            <button class="add-cart-btn" data-id="<?= $p['idPRODUTO'] ?>" data-nome="<?= htmlspecialchars($p['nomePRODUTO']) ?>">Adicionar ao Carrinho</button>
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
    <label for="modal-tamanho"><?= htmlspecialchars($nomeTipologia) ?>:</label>
    <select id="modal-tamanho" required><option value=""><?= 'Selecione ' . htmlspecialchars($nomeTipologia) ?></option></select>
    <label for="modal-cor"><?= htmlspecialchars($nomeEspecificacao) ?>:</label>
    <select id="modal-cor" required><option value=""><?= 'Selecione ' . htmlspecialchars($nomeEspecificacao) ?></option></select>
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

const labelTam = document.querySelector('label[for="modal-tamanho"]');
const labelCor = document.querySelector('label[for="modal-cor"]');

document.querySelectorAll('.add-cart-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const card = btn.closest('.produto-card');
    currentVars = JSON.parse(card.dataset.variantes || '[]');
    document.getElementById('modal-nome-produto').innerText = btn.dataset.nome;
    document.getElementById('modal-id-produto').value = btn.dataset.id;

    // pegar rótulos do próprio produto (data attributes)
    const rawTip = (card.dataset.tipologia || 'Tamanho').toString().replace(/:$/, '').trim();
    const rawEsp = (card.dataset.especificacao || 'Cor').toString().replace(/:$/, '').trim();

    // atualizar labels e option placeholder
    labelTam.innerText = rawTip + ':';
    labelCor.innerText = rawEsp + ':';

    selTam.innerHTML = `<option value="">Selecione ${rawTip}</option>` + [...new Set(currentVars.map(v => v.valor_tipologia))].map(s => `<option>${s}</option>`).join('');
    selCor.innerHTML = `<option value="">Selecione ${rawEsp}</option>`;
    stockInfo.textContent = 'Estoque: -';
    inputQty.value = 1;
    inputQty.max = 99999;
    modal.style.display = 'flex';
  });
});

selTam.addEventListener('change', () => {
  const size = selTam.value;
  const colors = [...new Set(currentVars.filter(v => v.valor_tipologia === size).map(v => v.valor_especificacao))];
  const curEsp = (document.querySelector('label[for="modal-cor"]').innerText || 'Cor').replace(/:$/, '').trim();
  selCor.innerHTML = `<option value="">Selecione ${curEsp}</option>` + colors.map(c => `<option>${c}</option>`).join('');
  stockInfo.textContent = 'Estoque: -';
  inputQty.value = 1;
});

selCor.addEventListener('change', () => {
  const size = selTam.value, cor = selCor.value;
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
  if (!idVar) { alert('Selecione um ' + (labelTam.innerText.replace(':','')) + ' e ' + (labelCor.innerText.replace(':','')) + ' válidos.'); return; }
  const qty = inputQty.value;
  const form = document.createElement('form');
  form.method = 'post';
  form.action = 'cart.php';
  form.innerHTML = `<input type="hidden" name="variant_id" value="${idVar}">` + `<input type="hidden" name="quantity" value="${qty}">`;
  document.body.appendChild(form);
  form.submit();
});
</script>
</body>
</html>
