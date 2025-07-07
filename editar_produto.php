<?php
session_start();
require __DIR__ . '/config/config.inc.php';

// Autenticação de artesão
if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    header('Location: login.php');
    exit;
}

$idProduto = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idProduto) {
    header('Location: perfil_artesao.php');
    exit;
}

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Carrega dados do produto
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE idPRODUTO = :id");
    $stmt->execute([':id' => $idProduto]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$produto) throw new Exception('Produto não encontrado');

    // Carrega categorias existentes
    $allCats = $pdo->query(
        "SELECT idCATEGORIA, nomeCATEGORIA FROM categorias ORDER BY nomeCATEGORIA"
    )->fetchAll(PDO::FETCH_ASSOC);
    $stmtCats = $pdo->prepare("SELECT id_categoria FROM produto_categorias WHERE id_produto = :id");
    $stmtCats->execute([':id' => $idProduto]);
    $catsSelecionadas = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

    // Carrega imagens adicionais
    $stmtImgs = $pdo->prepare("SELECT caminho FROM produto_imagens WHERE id_produto = :id");
    $stmtImgs->execute([':id' => $idProduto]);
    $imagens = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);

    // Carrega variantes existentes e estoques
    $stmtVar = $pdo->prepare(
        "SELECT valor_tipologia, valor_especificacao, estoque FROM variantes WHERE id_produto = :id"
    );
    $stmtVar->execute([':id' => $idProduto]);
    $variantes = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die('Erro: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Produto - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/cadastro_produto.css">
</head>
<body>
  <div class="product-form-container">
    <h1>Editar Produto</h1>
    <form action="update_produto.php" method="post" enctype="multipart/form-data" onsubmit="return validarVariantes();">
    <input type="hidden" name="id" value="<?= $idProduto ?>">

      <div class="product-form-group">
        <label>Imagem Atual</label>
        <img src="<?= htmlspecialchars($produto['imagemPRODUTO']) ?>" alt="Imagem do produto" style="max-width:200px;" />
      </div>
      <div class="product-form-group">
        <label for="upload-image">Substituir Imagem (opcional)</label>
        <input type="file" id="upload-image" name="product-image" accept="image/*">
      </div>

      <div class="product-form-group">
        <label for="extra-images">Adicionar Imagens Adicionais</label>
        <input type="file" id="extra-images" name="product-images[]" accept="image/*" multiple>
      </div>

      <div class="product-form-group">
        <label for="product-name">Nome do Produto</label>
        <input type="text" id="product-name" name="product-name" value="<?= htmlspecialchars($produto['nomePRODUTO']) ?>" required>
      </div>

      <div class="product-form-group">
        <label for="product-description">Descrição do Produto</label>
        <textarea id="product-description" name="product-description" rows="4" required><?= htmlspecialchars($produto['descricaoPRODUTO']) ?></textarea>
      </div>

      <div class="product-form-group">
        <label>Categorias</label>
        <div class="options-inline">
          <?php foreach($allCats as $c): ?>
            <label>
              <input type="checkbox" name="categories[]" value="<?= $c['idCATEGORIA'] ?>"
                <?= in_array($c['idCATEGORIA'], $catsSelecionadas) ? 'checked' : '' ?> >
              <?= htmlspecialchars($c['nomeCATEGORIA']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="product-form-group">
        <label for="new-categories">Novas Categorias <small>(separadas por vírgula)</small></label>
        <input type="text" id="new-categories" name="new_categories" placeholder="ex: pintura, tela">
      </div>

      <div class="product-form-group">
        <label for="price">Preço</label>
        <div class="price-input">
          <span>R$</span>
          <input type="number" id="price" name="price" step="0.01" value="<?= number_format($produto['precoPRODUTO'],2,'.','') ?>" required>
        </div>
      </div>

      <div class="product-form-group">
        <label for="tipologia-nome">Nome da Tipologia</label>
        <input type="text" id="tipologia-nome" name="tipologia_nome" value="<?= htmlspecialchars($produto['nome_tipologia']) ?>" required>
      </div>

      <div class="product-form-group">
        <label for="tipologia-valores">Valores da Tipologia <small>(separados por vírgula)</small></label>
        <input type="text" id="tipologia-valores" name="tipologia_valores" value="<?= implode(',', array_unique(array_column($variantes,'valor_tipologia'))) ?>" required>
      </div>

      <div class="product-form-group">
        <label for="especificacao-nome">Nome da Especificação</label>
        <input type="text" id="especificacao-nome" name="especificacao_nome" value="<?= htmlspecialchars($produto['nome_especificacao']) ?>" required>
      </div>

      <div class="product-form-group">
        <label for="especificacao-valores">Valores da Especificação <small>(separados por vírgula)</small></label>
        <input type="text" id="especificacao-valores" name="especificacao_valores" value="<?= implode(',', array_unique(array_column($variantes,'valor_especificacao'))) ?>" required>
      </div>

      <div class="product-form-group">
        <button id="generate-variants">Atualizar Estoques por Variante</button>
      </div>

      <div id="variant-stocks">
        <?php // gera tabela inicial de variantes com os valores existentes ?>
      </div>

      <button type="submit" class="btn-submit-product">Salvar Alterações</button>
    </form>
  </div>

  <script>
    document.getElementById('generate-variants').addEventListener('click', function(e) {
      e.preventDefault();
      const nomeTipologia = document.getElementById('tipologia-nome').value.trim();
      const nomeEspecificacao = document.getElementById('especificacao-nome').value.trim();

      const valoresTipologia = document.getElementById('tipologia-valores').value
                                 .split(',').map(v => v.trim()).filter(v => v);
      const valoresEspecificacao = document.getElementById('especificacao-valores').value
                                 .split(',').map(v => v.trim()).filter(v => v);

      const container = document.getElementById('variant-stocks');
      container.innerHTML = '';

      if (valoresTipologia.length === 0 || valoresEspecificacao.length === 0) {
        container.innerHTML = '<p style="color:red;">Informe os valores de tipologia e especificação.</p>';
        return;
      }

      const table = document.createElement('table');
      table.style.borderCollapse = 'collapse';
      table.innerHTML = `
        <tr>
          <th style="border:1px solid #ddd;padding:5px;">${nomeTipologia}</th>
          <th style="border:1px solid #ddd;padding:5px;">${nomeEspecificacao}</th>
          <th style="border:1px solid #ddd;padding:5px;">Estoque</th>
        </tr>
      `;
      valoresTipologia.forEach(t => {
        valoresEspecificacao.forEach(e => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td style="border:1px solid #ddd;padding:5px;">${t}</td>
            <td style="border:1px solid #ddd;padding:5px;">${e}</td>
            <td style="border:1px solid #ddd;padding:5px;">
              <input type="number" name="stocks[${t}][${e}]" min="0" value="0" required style="width:60px;">
            </td>
          `;
          table.appendChild(row);
        });
      });
      container.appendChild(table);
    });

    function validarVariantes() {
      const variantContainer = document.getElementById('variant-stocks');
      const hasInputs = variantContainer.querySelectorAll('input[name^="stocks["]').length > 0;
      if (!hasInputs) {
        alert('Você precisa gerar os estoques por variante antes de cadastrar o produto.');
        return false;
      }
      return true;
    }
  </script>
</body>
</html>
