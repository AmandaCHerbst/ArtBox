<?php
session_start();
require __DIR__ . '/config/config.inc.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar: " . $e->getMessage());
}

$cats = $pdo->query(
    "SELECT c.idCATEGORIA, c.nomeCATEGORIA, COUNT(pc.id_produto) AS uso
     FROM categorias c
     LEFT JOIN produto_categorias pc ON c.idCATEGORIA = pc.id_categoria
     GROUP BY c.idCATEGORIA, c.nomeCATEGORIA
     ORDER BY uso DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro de Produto - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/cadastro_produto.css">
</head>
<body>

  <div class="product-form-container">
    <h1>Cadastro de Produto</h1>

    <form action="upload.php" method="post" enctype="multipart/form-data" onsubmit="return validarVariantes()">

      <div class="product-form-group">
        <label for="upload-image">Imagem do Produto</label>
        <input type="file" id="upload-image" name="product-image" accept="image/*" required>
      </div>

      <div class="product-form-group">
        <label for="extra-images">Imagens Adicionais (até 5)</label>
        <input type="file" id="extra-images" name="product-images[]" accept="image/*" multiple>
      </div>

      <div class="product-form-group">
        <label for="product-name">Nome do Produto</label>
        <input type="text" id="product-name" name="product-name" required>
      </div>

      <div class="product-form-group">
        <label for="product-description">Descrição do Produto</label>
        <textarea id="product-description" name="product-description" rows="4" placeholder="Detalhes do produto" required></textarea>
      </div>

      <div class="product-form-group">
        <label>Categorias (Top 5 mais usadas)</label>
        <div class="options-inline">
          <?php foreach($cats as $c): ?>
            <label>
              <input type="checkbox" name="categories[]" value="<?= $c['idCATEGORIA'] ?>">
              <?= htmlspecialchars($c['nomeCATEGORIA']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="product-form-group">
        <label for="new-categories">Novas Categorias <small>(separadas por vírgula)</small></label>
        <input type="text" id="new-categories" name="new_categories" placeholder="ex: pintura, tela, tinta">
      </div>

      <div class="product-form-group">
        <label for="price">Preço</label>
        <div class="price-input">
          <span>R$</span>
          <input type="number" id="price" name="price" placeholder="00,00" step="0.01" required>
        </div>
      </div>

      <div class="product-form-group">
        <label for="tipologia-nome">Nome da Tipologia</label>
        <input type="text" id="tipologia-nome" name="tipologia_nome" placeholder="e, Medida, Volume" required>
      </div>

      <div class="product-form-group">
        <label for="tipologia-valores">Valores da Tipologia <small>(separados por vírgula)</small></label>
        <input type="text" id="tipologia-valores" name="tipologia_valores" placeholder="ex: P, M, G ou 30cm, 50cm, 1m" required>
      </div>

      <div class="product-form-group">
        <label for="especificacao-nome">Nome da Especificação</label>
        <input type="text" id="especificacao-nome" name="especificacao_nome" placeholder="ex: Cor, Estampa, Material" required>
      </div>

      <div class="product-form-group">
        <label for="especificacao-valores">Valores da Especificação <small>(separados por vírgula)</small></label>
        <input type="text" id="especificacao-valores" name="especificacao_valores" placeholder="ex: vermelho, azul, floral" required>
      </div>

      <div class="product-form-group">
        <button id="generate-variants">Gerar Estoques por Variante</button>
      </div>

      <div id="variant-stocks"></div>

      <button type="submit" class="btn-submit-product">Cadastrar Produto</button>
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