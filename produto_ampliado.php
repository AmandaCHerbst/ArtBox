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

$idProduto = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idProduto) {
    header('Location: index.php');
    exit;
}

$produtoObj = new Produto($pdo);
$produto = $produtoObj->buscarPorId($idProduto);
if (!$produto) {
    echo '<p>Produto não encontrado.</p>';
    exit;
}

// verifica favorito
$isFavorito = false;
if (isset($_SESSION['idUSUARIO'])) {
    $fs = $pdo->prepare("SELECT COUNT(*) FROM favoritos WHERE idUSUARIO = :u AND idPRODUTO = :p");
    $fs->execute([':u' => $_SESSION['idUSUARIO'], ':p' => $idProduto]);
    $isFavorito = ($fs->fetchColumn() > 0);
}

// toast message
$toastText = '';
if (isset($_GET['fav'])) {
    if ($_GET['fav'] === 'adicionado') {
        $toastText = 'Produto adicionado aos favoritos!';
    } elseif ($_GET['fav'] === 'removido') {
        $toastText = 'Produto removido dos favoritos.';
    }
}

// vendedor
$stmt = $pdo->prepare("SELECT nomeUSUARIO FROM usuarios WHERE idUSUARIO = :id");
$stmt->execute([':id' => $produto['id_artesao']]);
$nomeArtesao = $stmt->fetchColumn();

// imagens adicionais
try {
    $stmtImgs = $pdo->prepare('SELECT caminho FROM produto_imagens WHERE id_produto = :id');
    $stmtImgs->execute([':id' => $idProduto]);
    $imagens = array_filter($stmtImgs->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    $imagens = [];
}
$slides = array_merge([$produto['imagemPRODUTO']], $imagens);

// variantes
$stmtVar = $pdo->prepare('SELECT idVARIANTE, valor_tipologia, valor_especificacao, estoque FROM variantes WHERE id_produto = :id');
$stmtVar->execute([':id' => $idProduto]);
$variantes = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = array_unique(array_column($variantes, 'valor_tipologia'));
$cores = array_unique(array_column($variantes, 'valor_especificacao'));

// recomendados
$recomendados = [];
$cats = !empty($produto['categorias']) ? explode(',', $produto['categorias']) : [];
if ($cats) {
    $ph = rtrim(str_repeat('?,', count($cats)), ',');
    $sql = "SELECT DISTINCT p.* FROM produtos p
            JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
            JOIN categorias c ON pc.id_categoria = c.idCATEGORIA
            WHERE c.nomeCATEGORIA IN ($ph) AND p.idPRODUTO != ? LIMIT 4";
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
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .btn-secondary { background: transparent; padding: 0; border: none; }
    .btn-secondary i { font-size: 32px; transition: color 0.3s ease; cursor: pointer; }
    .btn-secondary i.far { color: #555; }
    .btn-secondary i.fas { color: red; }
    .btn-primary i { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 48; font-size: 32px; color: #fff; }
    .toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.85); color: #fff; padding: 12px 20px; border-radius: 8px; opacity: 0; pointer-events: none; transition: opacity 0.5s ease; font-size: 0.95rem; z-index: 10000; display: flex; align-items: center; gap: 8px; }
    .toast.show { opacity: 1; pointer-events: auto; }
    .close-lightbox-btn {
      position: absolute;
      top: 20px;
      right: 20px;
      font-size: 2rem;
      background: transparent;
      border: none;
      color: white;
      cursor: pointer;
      z-index: 10002;
    }
  </style>
</head>
<body>
  <div id="toast" class="toast"><?= $toastText ?></div>
  <div class="pagina-produto">
    <div class="produto-detalhes">
      <div class="cabecalho-produto">
        <div class="carrossel-wrapper">
          <button class="nav left" onclick="prevSlide()">&#10094;</button>
          <div class="carrossel" id="carrossel">
            <?php foreach ($slides as $i => $img): ?>
              <div class="slide"><img src="<?= htmlspecialchars($img) ?>" alt="" onclick="openLightbox(<?= $i ?>)"></div>
            <?php endforeach; ?>
          </div>
          <button class="nav right" onclick="nextSlide()">&#10095;</button>
          <button class="nav left" onclick="prevSlide()">&#10094;</button>
        </div>
        <div class="info-produto-header">
          <h1><?= htmlspecialchars($produto['nomePRODUTO']) ?></h1>
          <p class="loja-info">Vendido por: <a href="perfil_publico.php?id=<?= $produto['id_artesao'] ?>"><?= htmlspecialchars($nomeArtesao) ?></a></p>
          <p class="preco">R$ <?= number_format($produto['precoPRODUTO'],2,',','.') ?></p>
          <p class="descricao"><?= nl2br(htmlspecialchars($produto['descricaoPRODUTO'])) ?></p>
        </div>
      </div>
      <form method="post" action="cart.php" onsubmit="return validarCarrinho();">
        <div class="options-container">
          <div class="product-option"><label for="select-tamanho">Tamanho</label><select id="select-tamanho"><option value="">Selecione</option><?php foreach($tamanhos as $t): ?><option><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select></div>
          <div class="product-option"><label for="select-cor">Cor</label><select id="select-cor"><option value="">Selecione</option><?php foreach($cores as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
          <p id="stock-info">Estoque: -</p>
        </div>
        <div class="action-buttons">
          <input type="hidden" name="variant_id" id="input-var-id">
          <input type="number" name="quantity" id="input-quantidade" value="1" min="1">
          <button type="submit" class="btn-primary"><span class="material-symbols-outlined">shopping_cart</span></button>
          <a href="favoritar.php?id=<?= $produto['idPRODUTO'] ?>" class="btn-secondary" title="<?= $isFavorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>">
            <i class="<?= $isFavorito ? 'fas' : 'far' ?> fa-heart"></i>
          </a>
        </div>
      </form>
    </div>

    <aside class="recomendacoes">
      <h2>Recomendações</h2>
      <div class="recomendado-list">
        <?php if($recomendados): foreach($recomendados as $rec): ?>
          <a href="produto_ampliado.php?id=<?= $rec['idPRODUTO'] ?>" class="recomendado-item">
            <img src="<?= htmlspecialchars($rec['imagemPRODUTO'] ?: 'assets/img/placeholder.png') ?>">
            <div class="recomendado-info"><h4><?= htmlspecialchars($rec['nomePRODUTO']) ?></h4><p>R$ <?= number_format($rec['precoPRODUTO'],2,',','.') ?></p></div>
          </a>
        <?php endforeach; else: ?><p>Não há recomendações.</p><?php endif; ?>
      </div>
    </aside>
  </div>

  <script>
    window.addEventListener('DOMContentLoaded', () => {
      const toast = document.getElementById('toast');
      if (toast.textContent.trim()) {
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
      }
    });
    const variantes = <?= json_encode($variantes) ?>;
    const selT = document.getElementById('select-tamanho'), selC = document.getElementById('select-cor'), stock = document.getElementById('stock-info'), qty = document.getElementById('input-quantidade');
    selT.addEventListener('change', () => { const size = selT.value; const cores = [...new Set(variantes.filter(v => v.valor_tipologia===size).map(v=>v.valor_especificacao))]; selC.innerHTML = '<option value="">Selecione</option>'+cores.map(c=>`<option>${c}</option>`).join(''); stock.textContent='Estoque: -'; });
    selC.addEventListener('change', () => { const v = variantes.find(v=>v.valor_tipologia===selT.value&&v.valor_especificacao===selC.value)||{}; stock.textContent='Estoque: '+(v.estoque||0); qty.max=v.estoque||1; qty.value=v.estoque?1:0; });
    function validarCarrinho(){ const tam=selT.value, cor=selC.value, variante=variantes.find(v=>v.valor_tipologia===tam&&v.valor_especificacao===cor); if(!tam||!cor||!variante){alert('Selecione um tamanho e uma cor válidos.');return false;} document.getElementById('input-var-id').value=variante.idVARIANTE;return true; }
    const slides = Array.from(document.querySelectorAll('.slide')), carousel=document.getElementById('carrossel'); let current=0;
    function showSlide(i){carousel.style.transform=`translateX(-${i*100}%)`;current=i;}
    function nextSlide(){showSlide((current+1)%slides.length);} function prevSlide(){showSlide((current-1+slides.length)%slides.length);}
    showSlide(0);
    function openLightbox(i){ const lb=document.getElementById('lightbox'); const img=lb.querySelector('img'); img.src=slides[i].querySelector('img').src; lb.style.display='flex'; current=i; }
    function navigateLightbox(d){ openLightbox((current+d+slides.length)%slides.length); }
    function closeLightbox(){ document.getElementById('lightbox').style.display='none'; }
  </script>
  <div class="lightbox" id="lightbox" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:9999;">
    <button class="close-lightbox-btn" onclick="closeLightbox()">&times;</button>
    <button class="lightbox-nav prev" onclick="navigateLightbox(-1)">&#10094;</button>
    <img style="max-width:80%;max-height:80%;border-radius:8px;" alt="" />
    <button class="lightbox-nav next" onclick="navigateLightbox(1)">&#10095;</button>
  </div>
</body>
</html>