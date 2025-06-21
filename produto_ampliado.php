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

// Nome do artesão
$stmt = $pdo->prepare("SELECT nomeUSUARIO FROM usuarios WHERE idUSUARIO = :id");
$stmt->execute([':id' => $produto['id_artesao']]);
$nomeArtesao = $stmt->fetchColumn();

// Imagens adicionais
$imagens = [];
try {
    $stmtImgs = $pdo->prepare('SELECT caminho FROM produto_imagens WHERE id_produto = :id');
    $stmtImgs->execute([':id' => $idProduto]);
    $imagens = array_filter($stmtImgs->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {}

// Garante array e monta slides
if (!is_array($imagens)) {
    $imagens = [];
}
$slides = array_merge([$produto['imagemPRODUTO']], $imagens);
$totalSlides = count($slides);

// Variantes
$stmtVar = $pdo->prepare('SELECT idVARIANTE, tamanho, cor, estoque FROM variantes WHERE id_produto = :id');
$stmtVar->execute([':id' => $idProduto]);
$variantes = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = array_unique(array_column($variantes, 'tamanho'));
$cores    = array_unique(array_column($variantes, 'cor'));

// Recomendações
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
</head>
<body>
  <div class="pagina-produto">
    <div class="produto-detalhes">
      <div class="cabecalho-produto">

        <div class="carrossel-wrapper">
          <?php if ($totalSlides > 1): ?>
            <button class="nav left" onclick="prevSlide()">&#10094;</button>
          <?php endif; ?>
          <div class="carrossel" id="carrossel">
            <?php foreach ($slides as $i => $img): ?>
              <div class="slide"><img src="<?= htmlspecialchars($img) ?>" alt="" /></div>
            <?php endforeach; ?>
          </div>
          <?php if ($totalSlides > 1): ?>
            <button class="nav right" onclick="nextSlide()">&#10095;</button>
          <?php endif; ?>
        </div>

        <div class="info-produto-header">
          <h1><?= htmlspecialchars($produto['nomePRODUTO']) ?></h1>
          <p class="loja-info">Vendido por: <a href="perfil_publico.php?id=<?= $produto['id_artesao'] ?>"><?= htmlspecialchars($nomeArtesao) ?></a></p>
          <p class="preco">R$ <?= number_format($produto['precoPRODUTO'],2,',','.') ?></p>
          <p class="descricao"><?= nl2br(htmlspecialchars($produto['descricaoPRODUTO'])) ?></p>
        </div>
      </div>

      <div class="options-container"> 
        <div class="product-option"><label for="select-tamanho">Tamanho</label><select id="select-tamanho"><option value="">Selecione</option><?php foreach($tamanhos as $t): ?><option><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select></div>
        <div class="product-option"><label for="select-cor">Cor</label><select id="select-cor"><option value="">Selecione</option><?php foreach($cores as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
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
            <img src="<?= htmlspecialchars($rec['imagemPRODUTO']?:'assets/img/placeholder.png') ?>" />
            <div class="recomendado-info"><h4><?= htmlspecialchars($rec['nomePRODUTO']) ?></h4><p>R$ <?= number_format($rec['precoPRODUTO'],2,',','.') ?></p></div>
          </a>
        <?php endforeach; else: ?><p>Não há recomendações.</p><?php endif; ?>
      </div>
    </aside>
  </div>

  <div class="lightbox" id="lightbox">
    <button class="lightbox-nav prev" onclick="prevLightbox(event)">&#10094;</button>
    <img id="lightbox-img" onclick="event.stopPropagation()" />
    <button class="lightbox-nav next" onclick="nextLightbox(event)">&#10095;</button>
  </div>

  <div class="modal" id="modal-selecao">
    <div class="modal-content"> 
      <h3 id="modal-nome-produto"></h3>
      <input type="hidden" id="modal-id-produto" />
      <label>Tamanho:</label><select id="modal-tamanho"><option value="">Selecione</option></select>
      <label>Cor:</label><select id="modal-cor"><option value="">Selecione</option></select>
      <p id="modal-stock">Estoque: -</p>
      <label>Quantidade:</label><input id="modal-quantidade" type="number" min="1" value="1" />
      <div style="display:flex; gap:10px; margin-top:15px;"><button id="modal-add" class="btn-primary">Adicionar</button><button id="modal-close" class="btn-secondary">Cancelar</button></div>
    </div>
  </div>

  <script>
  window.addEventListener('DOMContentLoaded', () => {
    const carousel = document.getElementById('carrossel');
    const slides = Array.from(carousel.children);
    let current = 0;

    function showSlide(idx) {
      carousel.style.transform = `translateX(-${idx * 100}%)`;
      current = idx;
    }
    window.nextSlide = () => showSlide((current + 1) % slides.length);
    window.prevSlide = () => showSlide((current - 1 + slides.length) % slides.length);
    showSlide(0);

    const lightbox = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');

    function openLightbox(idx) {
      lbImg.src = slides[idx].querySelector('img').src;
      lightbox.style.display = 'flex';
      current = idx;
    }
    window.nextLightbox = e => { e.stopPropagation(); window.nextSlide(); openLightbox(current); };
    window.prevLightbox = e => { e.stopPropagation(); window.prevSlide(); openLightbox(current); };
    lightbox.addEventListener('click', () => lightbox.style.display = 'none');

    slides.forEach((slideEl, idx) => {
      slideEl.querySelector('img').addEventListener('click', () => openLightbox(idx));
    });

    const addBtn = document.querySelector('.add-cart-btn');
    const modal = document.getElementById('modal-selecao');
    const selT = document.getElementById('modal-tamanho');
    const selC = document.getElementById('modal-cor');
    const stock = document.getElementById('modal-stock');
    const qty = document.getElementById('modal-quantidade');
    const vars = <?= json_encode($variantes) ?>;

    addBtn.addEventListener('click', () => {
      modal.style.display = 'flex';
      document.getElementById('modal-nome-produto').innerText = addBtn.dataset.nome;
      document.getElementById('modal-id-produto').value = addBtn.dataset.id;
      selT.innerHTML = '<option value="">Selecione</option>' + [...new Set(vars.map(v => v.tamanho))].map(t => `<option>${t}</option>`).join('');
      selC.innerHTML = '<option value="">Selecione</option>';
      stock.textContent = 'Estoque: -'; qty.value = 1;
    });
    selT.addEventListener('change', () => {
      const size = selT.value;
      selC.innerHTML = '<option value="">Selecione</option>' + [...new Set(vars.filter(v => v.tamanho === size).map(v => v.cor))].map(c => `<option>${c}</option>`).join('');
      stock.textContent = 'Estoque: -';
    });
    selC.addEventListener('change', () => {
      const v = vars.find(v => v.tamanho === selT.value && v.cor === selC.value) || {};
      stock.textContent = 'Estoque: ' + (v.estoque || 0);
      qty.max = v.estoque || 1;
      qty.value = v.estoque ? 1 : 0;
    });
    document.getElementById('modal-close').addEventListener('click', () => modal.style.display = 'none');
    document.getElementById('modal-add').addEventListener('click', () => {
      const v = vars.find(v => v.tamanho === selT.value && v.cor === selC.value);
      if (!v) return;
      const f = document.createElement('form'); f.method = 'post'; f.action = 'cart.php';
      f.innerHTML = `<input type="hidden" name="variant_id" value="${v.idVARIANTE}"><input type="hidden" name="quantity" value="${qty.value}">`;
      document.body.appendChild(f); f.submit();
    });
  });
  </script>
</body>
</html>