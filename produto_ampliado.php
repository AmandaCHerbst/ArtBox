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

list($nomeTipologia, $valoresTipologia) = explode(':', $produto['tamanhos_disponiveis'] ?? 'Tipologia:');
list($nomeEspecificacao, $valoresEspecificacao) = explode(':', $produto['cores_disponiveis'] ?? 'Especificações:');

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

$slides = array_merge([$produto['imagemPRODUTO']], $imagens);
$totalSlides = count($slides);

// Variantes
$stmtVar = $pdo->prepare('SELECT idVARIANTE, valor_tipologia, valor_especificacao, estoque FROM variantes WHERE id_produto = :id');
$stmtVar->execute([':id' => $idProduto]);
$variantes = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = array_unique(array_column($variantes, 'valor_tipologia'));
$cores    = array_unique(array_column($variantes, 'valor_especificacao'));

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
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
  <div class="pagina-produto">
    <div class="produto-detalhes">
      <div class="cabecalho-produto">
        <div class="carrossel-wrapper">
          <button class="nav left" onclick="prevSlide()">&#10094;</button>
          <div class="carrossel" id="carrossel">
            <?php foreach ($slides as $i => $img): ?>
              <div class="slide"><img src="<?= htmlspecialchars($img) ?>" alt="" onclick="openLightbox(<?= $i ?>)" /></div>
            <?php endforeach; ?>
          </div>
          <button class="nav right" onclick="nextSlide()">&#10095;</button>
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
          <div class="product-option">
            <label for="select-tamanho"><?= htmlspecialchars($nomeTipologia) ?></label>
            <select id="select-tamanho"><option value="">Selecione</option><?php foreach($tamanhos as $t): ?><option><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select>
          </div>
          <div class="product-option">
            <label for="select-cor"><?= htmlspecialchars($nomeEspecificacao) ?></label>
            <select id="select-cor"><option value="">Selecione</option><?php foreach($cores as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select>
          </div>
          <p id="stock-info">Estoque: -</p>
        </div>

        <div class="action-buttons">
          <input type="hidden" name="variant_id" id="input-var-id">
          <input type="number" name="quantity" value="1" min="1" id="input-quantidade">
          <button type="submit" class="btn-primary"><span class="material-icons" aria-hidden="true">add_shopping_cart</span></button>
          <button type="button" class="btn-secondary" onclick="location.href='favoritar.php?id=<?= $produto['idPRODUTO'] ?>'"><span class="material-icons" aria-hidden="true">favorite</span></button>
        </div>
      </form>
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

  <div class="lightbox" id="lightbox" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:9999;">
    <button onclick="navigateLightbox(-1)" style="position:absolute;left:20px;font-size:2rem;color:#fff;z-index:10001">&#10094;</button>
    <img id="lightbox-img" style="max-height:80%;max-width:80%;border-radius:10px" />
    <button onclick="navigateLightbox(1)" style="position:absolute;right:20px;font-size:2rem;color:#fff;z-index:10001">&#10095;</button>
  </div>

  <script>
  const variantes = <?= json_encode($variantes) ?>;
  const selT = document.getElementById('select-tamanho');
  const selC = document.getElementById('select-cor');
  const stock = document.getElementById('stock-info');
  const qty = document.getElementById('input-quantidade');

  selT.addEventListener('change', () => {
    const size = selT.value;
    const cores = [...new Set(variantes.filter(v => v.valor_tipologia === size).map(v => v.valor_especificacao))];
    selC.innerHTML = '<option value="">Selecione</option>' + cores.map(c => `<option>${c}</option>`).join('');
    stock.textContent = 'Estoque: -';
  });

  selC.addEventListener('change', () => {
    const v = variantes.find(v => v.valor_tipologia === selT.value && v.valor_especificacao === selC.value) || {};
    stock.textContent = 'Estoque: ' + (v.estoque || 0);
    qty.max = v.estoque || 1;
    qty.value = v.estoque ? 1 : 0;
  });

  function validarCarrinho() {
    const tam = selT.value;
    const cor = selC.value;
    const variante = variantes.find(v => v.valor_tipologia === tam && v.valor_especificacao === cor);

    if (!tam || !cor || !variante) {
      alert('Selecione um tamanho e uma cor válidos.');
      return false;
    }
    document.getElementById('input-var-id').value = variante.idVARIANTE;
    return true;
  }

  const slides = Array.from(document.querySelectorAll('.slide'));
  let currentSlide = 0;

  function showSlide(index) {
    const carrossel = document.getElementById('carrossel');
    carrossel.style.transform = `translateX(-${index * 100}%)`;
    currentSlide = index;
  }

  function nextSlide() {
    currentSlide = (currentSlide + 1) % slides.length;
    showSlide(currentSlide);
  }

  function prevSlide() {
    currentSlide = (currentSlide - 1 + slides.length) % slides.length;
    showSlide(currentSlide);
  }

  function openLightbox(index) {
    const lightbox = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');
    lbImg.src = slides[index].querySelector('img').src;
    lightbox.style.display = 'flex';
    currentSlide = index;
  }

  function navigateLightbox(direction) {
    currentSlide = (currentSlide + direction + slides.length) % slides.length;
    openLightbox(currentSlide);
  }

  document.getElementById('lightbox').addEventListener('click', (e) => {
    if (e.target.id === 'lightbox') {
      document.getElementById('lightbox').style.display = 'none';
    }
  });

  showSlide(0);
  </script>
</body>
</html>
