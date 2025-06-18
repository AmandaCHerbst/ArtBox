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

$stmtVar = $pdo->prepare(
    "SELECT idVARIANTE, tamanho, cor, estoque FROM variantes WHERE id_produto = :id"
);
$stmtVar->execute([':id' => $idProduto]);
$variantes = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = array_unique(array_column($variantes, 'tamanho'));
$cores    = array_unique(array_column($variantes, 'cor'));

$categorias = !empty($produto['categorias']) ? explode(',', $produto['categorias']) : [];
$recomendados = [];
if ($categorias) {
    $ph = rtrim(str_repeat('?,', count($categorias)), ',');
    $sqlRec = "SELECT DISTINCT p.* FROM produtos p
               JOIN produto_categorias pc ON p.idPRODUTO=pc.id_produto
               JOIN categorias c ON pc.id_categoria=c.idCATEGORIA
               WHERE c.nomeCATEGORIA IN ($ph) AND p.idPRODUTO!=? LIMIT 4";
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
  <style>
  .pagina-produto { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; padding: 20px; }
  .produto-detalhes { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
  .cabecalho-produto { display: flex; gap: 20px; flex-wrap: wrap; }
  .imagem-ampliada img { width: 100%; max-width: 360px; border-radius: 8px; cursor: zoom-in; }
  .info-produto-header { flex: 1; display: flex; flex-direction: column; gap: 12px; }
  .info-produto-header h1 { margin:0; font-size:2rem; color:#333; }
  .preco { font-size:1.6rem; font-weight:bold; color:#e53935; margin:0; }
  .descricao { font-size:1rem; color:#555; line-height:1.5; }
  .options-container { display:flex; gap:15px; flex-wrap: wrap; margin:10px 0; }
  .product-option { flex:1; min-width:120px; }
  .product-option label { display:block; font-weight:600; margin-bottom:5px; }
  select, input[type=number] { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; }
  #stock-info { font-weight:600; margin:10px 0; }
  .action-buttons { display:flex; gap:15px; flex-wrap: wrap; margin-top:15px; }
  .btn-primary { flex:1; background:#43a047; color:#fff; border:none; padding:12px; border-radius:6px; cursor:pointer; }
  .btn-primary:disabled { background:#a5d6a7; cursor:not-allowed; }
  .btn-secondary { background:#fbc02d; color:#212529; border:none; padding:12px; border-radius:6px; cursor:pointer; }
  .favoritar-btn { align-self:flex-start; }
  .recomendacoes { background:#fafafa; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
  .recomendacoes h2 { margin-top:0; font-size:1.4rem; color:#333; }
  .recomendado-list { display:flex; flex-direction:column; gap:12px; }
  .recomendado-item { display:flex; align-items:center; gap:12px; padding:10px; background:#fff; border-radius:6px; box-shadow:0 1px 4px rgba(0,0,0,0.1); text-decoration:none; color:inherit; transition:transform .2s; }
  .recomendado-item:hover { transform:translateY(-3px); }
  .recomendado-item img { width:60px; height:60px; object-fit:cover; border-radius:6px; }
  .recomendado-info h4 { margin:0; font-size:1rem; color:#212121; }
  .recomendado-info p { margin:4px 0 0; font-weight:bold; color:#e53935; }
  .lightbox { display:none; position:fixed; top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8); justify-content:center;align-items:center; z-index:10000; backdrop-filter:blur(8px); }
  .lightbox img { max-width:90%; max-height:90%; border-radius:8px; }
  </style>
</head>
<body>
<div class="pagina-produto">
  <div class="produto-detalhes">
    <div class="cabecalho-produto">
      <div class="imagem-ampliada">
        <img id="main-img" src="<?= htmlspecialchars($produto['imagemPRODUTO']?:'assets/img/placeholder.png') ?>"
             alt="<?= htmlspecialchars($produto['nomePRODUTO']) ?>">
      </div>
      <div class="info-produto-header">
        <h1><?= htmlspecialchars($produto['nomePRODUTO']) ?></h1>
        <p class="preco">R$ <?= number_format($produto['precoPRODUTO'],2,',','.') ?></p>
        <p class="descricao"><?= nl2br(htmlspecialchars($produto['descricaoPRODUTO'])) ?></p>
      </div>
    </div>

    <div class="options-container">
      <div class="product-option">
        <label for="select-tamanho">Tamanho</label>
        <select id="select-tamanho" required><option value="">Selecione</option>
          <?php foreach ($tamanhos as $tam): ?>
            <option><?= htmlspecialchars($tam) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="product-option">
        <label for="select-cor">Cor</label>
        <select id="select-cor" required><option value="">Selecione</option>
          <?php foreach ($cores as $cor): ?>
            <option><?= htmlspecialchars($cor) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="product-option">
        <label>&nbsp;</label><p id="stock-info">Estoque: -</p>
      </div>
    </div>

    <form id="form-carrinho" action="cart.php" method="post" class="action-buttons">
      <input type="hidden" name="variant_id" id="input-variant-id">
      <input type="number" name="quantity" id="input-quantity" min="1" value="1" disabled required>
      <button type="submit" id="btn-add-cart" class="btn-primary" disabled>Adicionar ao Carrinho</button>
      <button type="submit" formaction="favoritar.php" class="btn-secondary favoritar-btn">Favoritar</button>
    </form>
  </div>

  <aside class="recomendacoes">
    <h2>Recomendações</h2>
    <div class="recomendado-list">
      <?php if ($recomendados): foreach ($recomendados as $rec): ?>
        <a href="produto_ampliado.php?id=<?= $rec['idPRODUTO'] ?>" class="recomendado-item">
          <img src="<?= htmlspecialchars($rec['imagemPRODUTO']?:'assets/img/placeholder.png') ?>" alt="">
          <div class="recomendado-info">
            <h4><?= htmlspecialchars($rec['nomePRODUTO']) ?></h4>
            <p>R$ <?= number_format($rec['precoPRODUTO'],2,',','.') ?></p>
          </div>
        </a>
      <?php endforeach; else: ?>
        <p>Não há recomendações disponíveis.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>

<div id="lightbox" class="lightbox"><img id="lightbox-img"></div>

<script>
const variantes = <?= json_encode($variantes) ?>;
const selTam = document.getElementById('select-tamanho');
const selCor = document.getElementById('select-cor');
const stockInfo = document.getElementById('stock-info');
const inputVarId = document.getElementById('input-variant-id');
const inputQty = document.getElementById('input-quantity');
const btnAdd = document.getElementById('btn-add-cart');

function updateVariant() {
  const tam = selTam.value, cor = selCor.value;
  const v = variantes.find(x => x.tamanho===tam && x.cor===cor) || {};
  const st = v.estoque||0;
  stockInfo.textContent = 'Estoque: '+st;
  inputQty.max=st; inputQty.value=1;
  btnAdd.disabled = !(st>0);
  inputQty.disabled = !(st>0);
  inputVarId.value = v.idVARIANTE||'';
}
selTam.addEventListener('change', updateVariant);
selCor.addEventListener('change', updateVariant);

const mainImg = document.getElementById('main-img');
const lightbox = document.getElementById('lightbox');
const lbImg = document.getElementById('lightbox-img');
mainImg.addEventListener('click', ()=>{
  lbImg.src = mainImg.src;
  lightbox.style.display='flex';
});
lightbox.addEventListener('click', ()=> lightbox.style.display='none');
</script>
</body>
</html>