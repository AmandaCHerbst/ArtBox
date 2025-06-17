<?php
session_start();
require __DIR__ . '/config/config.inc.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar: " . $e->getMessage());
}
$cats = $pdo->query("SELECT idCATEGORIA, nomeCATEGORIA FROM categorias")
            ->fetchAll(PDO::FETCH_ASSOC);
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

    <form id="product-form" action="upload.php" method="post" enctype="multipart/form-data">

      <div class="product-form-group">
        <label for="upload-image">Imagem do Produto</label>
        <input type="file" id="upload-image" name="product-image" required>
      </div>

      <div class="product-form-group">
        <label for="product-name">Nome do Produto</label>
        <input type="text" id="product-name" name="product-name" required>
      </div>

      <div class="product-form-group">
        <label for="product-description">Descrição do Produto</label>
        <textarea id="product-description"
                  name="product-description"
                  rows="4"
                  placeholder="Detalhes do produto"
                  required></textarea>
      </div>

      <div class="product-form-group">
        <label>Categorias</label>
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
        <label>Tamanhos Disponíveis</label>
        <div class="options-inline">
          <?php $sizes = ['Único','PP','P','M','G','GG','XG','XGG']; ?>
          <?php foreach($sizes as $s): ?>
            <label>
              <input type="checkbox" name="sizes[]" value="<?= $s ?>"> <?= $s ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="product-form-group">
        <label for="color">Cores Disponíveis <small>(separadas por vírgula)</small></label>
        <input type="text" id="color" name="color" placeholder="ex: vermelho, azul" required>
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
      const sizes = Array.from(document.querySelectorAll('input[name="sizes[]"]:checked'))
                         .map(cb => cb.value.trim())
                         .filter(v => v);
      const colors = document.getElementById('color').value
                         .split(',')
                         .map(c => c.trim())
                         .filter(v => v);

      const container = document.getElementById('variant-stocks');
      container.innerHTML = '';

      if (sizes.length === 0 || colors.length === 0) {
        container.innerHTML = '<p style="color:red;">Selecione pelo menos um tamanho e informe ao menos uma cor.</p>';
        return;
      }

      const table = document.createElement('table');
      table.style.borderCollapse = 'collapse';
      table.innerHTML = `
        <tr>
          <th style="border:1px solid #ddd;padding:5px;">Tamanho</th>
          <th style="border:1px solid #ddd;padding:5px;">Cor</th>
          <th style="border:1px solid #ddd;padding:5px;">Estoque</th>
        </tr>
      `;
      sizes.forEach(tam => {
        colors.forEach(cor => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td style="border:1px solid #ddd;padding:5px;">${tam}</td>
            <td style="border:1px solid #ddd;padding:5px;">${cor}</td>
            <td style="border:1px solid #ddd;padding:5px;">
              <input type="number"
                     name="stocks[${tam}][${cor}]"
                     min="0"
                     value="0"
                     required
                     style="width:60px;">
            </td>
          `;
          table.appendChild(row);
        });
      });
      container.appendChild(table);
    });
  </script>
</body>
</html>