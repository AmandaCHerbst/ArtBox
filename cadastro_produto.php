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
    <div class="product-form-header">
      <h1>Cadastro de Produto</h1>
    </div>

    <form action="upload.php" method="post" enctype="multipart/form-data">

      <div class="product-form-group">
        <label for="upload-image">Imagem do Produto</label>
        <input type="file" id="upload-image" name="product-image" required>
      </div>

      <div class="product-form-group">
        <label for="product-name">Nome do Produto</label>
        <input type="text" id="product-name" name="product-name" required>
      </div>

      <div class="product-form-group">
        <label>Tamanhos Disponíveis</label>
        <div class="options-inline">
          <?php $sizes = ['Único','PP','P','M','G','GG','XG','XGG']; ?>
          <?php foreach($sizes as $s): ?>
            <label><input type="checkbox" name="sizes[]" value="<?= $s ?>"> <?= $s ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php
        require 'config/config.inc.php';
        $pdo = new PDO(DSN, USUARIO, SENHA);
        $cats = $pdo->query("SELECT idCATEGORIA, nomeCATEGORIA FROM categorias")->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="product-form-group">
        <label>Categorias</label>
        <div class="options-inline">
          <?php foreach($cats as $c): ?>
            <label><input type="checkbox" name="categories[]" value="<?= $c['idCATEGORIA'] ?>"> <?= htmlspecialchars($c['nomeCATEGORIA']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="product-form-group">
        <label for="new-categories">Novas Categorias <small>(separadas por vírgula)</small></label>
        <input type="text" id="new-categories" name="new_categories" placeholder="ex: pintura, tela, tinta">
      </div>

      <div class="product-form-group">
        <label for="color">Cores Disponíveis</label>
        <input type="text" id="color" name="color" placeholder="ex: vermelho, azul">
      </div>

      <div class="product-form-group">
        <label for="quantity">Quantidade Disponível</label>
        <select id="quantity" name="quantity" required>
          <option value="">Selecione...</option>
          <?php for($i=1; $i<=10; $i++): ?>
            <option value="<?= $i ?>"><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="product-form-group">
        <label>Preço</label>
        <div class="price-input">
          <span>R$</span>
          <input type="number" name="price" placeholder="00,00" step="0.01" required>
        </div>
      </div>

      <button type="submit" class="btn-submit-product">Cadastrar Produto</button>

    </form>
    <div class="produto-footer">
      <p><a href="index.php">Voltar ao Início</a></p>
    </div>
  </div>
  </div>

</body>
</html>
