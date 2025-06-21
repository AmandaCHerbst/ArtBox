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
    header('Location: index.php');
    exit;
}
$idProduto = (int) $_GET['id'];

$produtoObj = new Produto($pdo);
$produto = $produtoObj->buscarPorId($idProduto);
if (!$produto) {
    echo '<p>Produto não encontrado.</p>';
    exit;
}

$stmt = $pdo->prepare("SELECT nomeUSUARIO FROM usuarios WHERE idUSUARIO = :id");
$stmt->execute([':id' => $produto['id_artesao']]);
$nomeArtesao = $stmt->fetchColumn();

$imagens = [];
try {
    $stmtImgs = $pdo->prepare('SELECT caminho FROM produto_imagens WHERE id_produto = :id');
    $stmtImgs->execute([':id' => $idProduto]);
    $imagens = array_filter($stmtImgs->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
}

$stmtVar = $pdo->prepare('SELECT idVARIANTE, tamanho, cor, estoque FROM variantes WHERE id_produto = :id');
$stmtVar->execute([':id' => $idProduto]);
$variantes = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = array_unique(array_column($variantes, 'tamanho'));
$cores    = array_unique(array_column($variantes, 'cor'));

$recomendados = [];
$cats = !empty($produto['categorias']) ? explode(',', $produto['categorias']) : [];
if ($cats) {
    $ph = rtrim(str_repeat('?,', count($cats)), ',');
    $sql = "SELECT DISTINCT p.* FROM produtos p
            JOIN produto_categorias pc ON p.idPRODUTO=pc.id_produto
            JOIN categorias c ON pc.id_categoria=c.idCATEGORIA
            WHERE c.nomeCATEGORIA IN ($ph) AND p.idPRODUTO!=? LIMIT 4";
    $stmtRec = $pdo->prepare($sql);
    $stmtRec->execute(array_merge($cats, [$idProduto]));
    $recomendados = $stmtRec->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($produto['nomePRODUTO']) ?> - ARTBOX</title>
<link rel="stylesheet" href="assets/css/produto_ampliado.css">
<style>
.pagina-produto { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; padding: 20px; }
.produto-detalhes { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.cabecalho-produto { display: flex; gap: 20px; flex-wrap: wrap; }
.carrossel-wrapper { position: relative; width: 100%; max-width: 360px; overflow: hidden; }
.carrossel { display: flex; transition: transform 0.5s ease-in-out; }
.slide { min-width: 100%; flex-shrink: 0; }
.slide img { width: 100%; height: 360px; object-fit: cover; border-radius: 8px; cursor: zoom-in; }
.nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: #fff; border: none; padding: 8px; cursor: pointer; }
.nav.left { left: 10px; }
.nav.right { right: 10px; }
.info-produto-header { flex: 1; display: flex; flex-direction: column; gap: 12px; }
.info-produto-header h1 { margin: 0; font-size: 2rem; color: #333; }
.preco { font-size: 1.6rem; font-weight: bold; color: #e53935; margin: 0; }
.descricao { font-size: 1rem; color: #555; line-height: 1.5; }
.loja-info a { color: #B8860B; text-decoration: none; font-weight: 500; }
.options-container { display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0; }
.product-option { flex: 1; min-width: 120px; }
.product-option label { display: block; font-weight: 600; margin-bottom: 5px; }
select, input[type=number] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
#stock-info { font-weight: 600; margin-top: 5px; }
.action-buttons { display: flex; gap: 15px; }
.btn-primary { flex: 1; background: #43a047; color: #fff; border: none; padding: 12px; border-radius: 6px; cursor: pointer; }
.btn-primary:disabled { background: #a5d6a7; }
.btn-secondary { background: #fbc02d; color: #212529; border: none; padding: 12px; border-radius: 6px; cursor: pointer; }
.favoritar-btn { margin-left: auto; }
.lightbox { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); justify-content: center; align-items: center; z-index: 10000; }
.lightbox img { max-width: 90%; max-height: 90%; border-radius: 8px; }
.recomendacoes { background: #fafafa; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.recomendacoes h2 { margin-top: 0; font-size: 1.4rem; color: #333; }
.recomendado-list { display: flex; flex-direction: column; gap: 12px; }
.recomendado-item { display: flex; align-items: center; gap: 12px; padding: 10px; background: #fff; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); text-decoration: none; color: inherit; transition: transform .2s; }
.recomendado-item:hover { transform: translateY(-3px); }
.recomendado-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; }
.recomendado-info h4 { margin: 0; font-size: 1rem; color: #212121; }
.recomendado-info p { margin: 4px 0 0; font-weight: bold; color: #e53935; }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); justify-content: center; align-items: center; z-index: 10001; }
.modal-content { background: #fff; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; }
.modal-content label { display: block; margin-top: 10px; }
</style>
</head>
<body>
<div class="pagina-produto">
  <div class="produto-detalhes">
    <div class="cabecalho-produto">

      <div class="carrossel-wrapper">
        <button class="nav left" onclick="prevSlide()">&#10094;</button>
        <div class="carrossel" id="carrossel">
          <?php foreach (array_merge([$produto['imagemPRODUTO']], $imagens) as $img): ?>
            <div class="slide"><img src="<?= htmlspecialchars($img ?: 'assets/img/placeholder.png') ?>" onclick="openLightbox(this.src)"></div>
          <?php endforeach; ?>
        </div>
        <button class="nav right" onclick="nextSlide()">&#10095;</button>
      </div>

      <div class="info-produto-header">
        <h1><?= htmlspecialchars($produto['nomePRODUTO']) ?></h1>
        <p class="loja-info">
  Vendido por:
  <a href="perfil_publico.php?id=<?= $produto['id_artesao'] ?>">
    <?= htmlspecialchars($nomeArtesao) ?>
  </a>
</p>
        <p class="preco">R$ <?= number_format($produto['precoPRODUTO'],2,',','.') ?></p>
        <p class="descricao"><?= nl2br(htmlspecialchars($produto['descricaoPRODUTO'])) ?></p>
      </div>
    </div>

    <div class="options-container">
      <div class="product-option">
        <label for="select-tamanho">Tamanho</label>
        <select id="select-tamanho"><option value="">Selecione</option><?php foreach($tamanhos as $t): ?><option><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select>
      </div>
      <div class="product-option">
        <label for="select-cor">Cor</label>
        <select id="select-cor"><option value="">Selecione</option><?php foreach($cores as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select>
      </div>
      <p id="stock-info">Estoque: -</p>
    </div>

    <div class="action-buttons">
      <button type="button" class="btn-primary add-cart-btn" data-id="<?= $produto['idPRODUTO'] ?>" data-nome="<?= htmlspecialchars($produto['nomePRODUTO']) ?>">Adicionar ao Carrinho</button>
      <button type="button" class="btn-secondary" onclick="location.href='favoritar.php?id='+<?= $produto['idPRODUTO'] ?>">Favoritar</button>
    </div>
  </div>

  <aside class="recomendacoes">
    <h2>Recomendações</h2>
    <div class="recomendado-list">
      <?php if($recomendados): foreach($recomendados as $rec): ?>
        <a href="produto_ampliado.php?id=<?= $rec['idPRODUTO'] ?>" class="recomendado-item">
          <img src="<?= htmlspecialchars($rec['imagemPRODUTO']?:'assets/img/placeholder.png') ?>">
          <div class="recomendado-info"><h4><?= htmlspecialchars($rec['nomePRODUTO']) ?></h4><p>R$ <?= number_format($rec['precoPRODUTO'],2,',','.') ?></p></div>
        </a>
      <?php endforeach; else: ?><p>Não há recomendações.</p><?php endif; ?>
    </div>
  </aside>
</div>

<div class="lightbox" id="lightbox" onclick="this.style.display='none'">
  <img id="lightbox-img">
</div>

<div class="modal" id="modal-selecao">
  <div class="modal-content">
    <h3 id="modal-nome-produto"></h3>
    <input type="hidden" id="modal-id-produto">
    <label>Tamanho:</label><select id="modal-tamanho"><option value="">Selecione</option></select>
    <label>Cor:</label><select id="modal-cor"><option value="">Selecione</option></select>
    <p id="modal-stock">Estoque: -</p>
    <label>Quantidade:</label><input id="modal-quantidade" type="number" min="1" value="1">
    <div style="display:flex; gap:10px; margin-top:15px;"><button id="modal-add" class="btn-primary">Adicionar</button><button id="modal-close" class="btn-secondary">Cancelar</button></div>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
  const car = document.getElementById('carrossel');
  const slides = Array.from(car.children);
  let current = 0;
  function showSlide(idx) { car.style.transform = `translateX(${-idx*100}%)`; }
  window.nextSlide = () => { current = (current + 1) % slides.length; showSlide(current); };
  window.prevSlide = () => { current = (current - 1 + slides.length) % slides.length; showSlide(current); };
  showSlide(0);

  slides.forEach(sl => {
    sl.querySelector('img').addEventListener('click', () => {
      const lb = document.getElementById('lightbox');
      document.getElementById('lightbox-img').src = sl.querySelector('img').src;
      lb.style.display = 'flex';
    });
  });

  const addBtn = document.querySelector('.add-cart-btn');
  const modal = document.getElementById('modal-selecao');
  const selT = document.getElementById('modal-tamanho');
  const selC = document.getElementById('modal-cor');
  const stock = document.getElementById('modal-stock');
  const qty  = document.getElementById('modal-quantidade');
  const vars = <?= json_encode($variantes) ?>;

  addBtn.addEventListener('click', () => {
    modal.style.display = 'flex';
    document.getElementById('modal-nome-produto').innerText = addBtn.dataset.nome;
    document.getElementById('modal-id-produto').value = addBtn.dataset.id;
    selT.innerHTML = '<option value="">Selecione</option>' + [...new Set(vars.map(v=>v.tamanho))].map(t=>`<option>${t}</option>`).join('');
    selC.innerHTML = '<option value="">Selecione</option>';
    stock.textContent = 'Estoque: -'; qty.value = 1;
  });
  selT.addEventListener('change', () => {
    const size = selT.value;
    selC.innerHTML = '<option value="">Selecione</option>' + [...new Set(vars.filter(v=>v.tamanho===size).map(v=>v.cor))].map(c=>`<option>${c}</option>`).join('');
    stock.textContent = 'Estoque: -';
  });
  selC.addEventListener('change', () => {
    const v = vars.find(v=>v.tamanho===selT.value && v.cor===selC.value) || {};
    stock.textContent = 'Estoque: '+(v.estoque||0);
    qty.max = v.estoque||1;
    qty.value = v.estoque?1:0;
  });
  document.getElementById('modal-close').addEventListener('click', () => modal.style.display='none');
  document.getElementById('modal-add').addEventListener('click', () => {
    const v = vars.find(v=>v.tamanho===selT.value && v.cor===selC.value);
    if (!v) return;
    const f = document.createElement('form'); f.method = 'post'; f.action = 'cart.php';
    f.innerHTML = `<input type="hidden" name="variant_id" value="${v.idVARIANTE}"><input type="hidden" name="quantity" value="${qty.value}">`;
    document.body.appendChild(f); f.submit();
  });
});
</script>
</body>
</html>