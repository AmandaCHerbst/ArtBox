<?php
// upload.php
require 'config/config.inc.php';
$pdo = new PDO(DSN, USUARIO, SENHA);

// 1) Validação do upload
if (!isset($_FILES['product-image']) || $_FILES['product-image']['error'] !== UPLOAD_ERR_OK) {
  die("Erro no upload da imagem.");
}
$allowed = ['jpg','jpeg','png','gif'];
$ext = strtolower(pathinfo($_FILES['product-image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
  die("Tipo de arquivo não permitido.");
}
// 2) Move para uploads/
$newName = uniqid('prod_', true) . "." . $ext;
$dest = __DIR__ . "/uploads/" . $newName;
if (!move_uploaded_file($_FILES['product-image']['tmp_name'], $dest)) {
  die("Falha ao mover o arquivo.");
}

// 3) Prepara dados do form
$nome      = $_POST['product-name'];
$sizes     = isset($_POST['sizes']) ? implode(',', $_POST['sizes']) : '';
$color     = $_POST['color'] ?? '';
$quant     = (int)$_POST['quantity'];
$price     = (float)$_POST['price'];
$descr     = "Tamanhos: $sizes | Cores: $color";
$imgPath   = "uploads/" . $newName;
$idArt     = 1; // artesão fixo por enquanto

// 4) Insere em produtos
$sql = "INSERT INTO produtos 
  (nomePRODUTO, descricaoPRODUTO, precoPRODUTO, quantidade, imagemPRODUTO, id_artesao)
  VALUES (:nome, :descr, :price, :quant, :img, :art)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':nome'  => $nome,
  ':descr' => $descr,
  ':price' => $price,
  ':quant' => $quant,
  ':img'   => $imgPath,
  ':art'   => $idArt
]);
$idProd = $pdo->lastInsertId();

// 5) Salva nas categorias escolhidas
if (!empty($_POST['categories'])) {
  $sql2 = "INSERT INTO produto_categorias (id_produto, id_categoria) VALUES (:prod, :cat)";
  $stmt2 = $pdo->prepare($sql2);
  foreach ($_POST['categories'] as $catId) {
    $stmt2->execute([':prod'=>$idProd, ':cat'=>$catId]);
  }
}

// 6) Redireciona ou exibe sucesso
header("Location: index.php?msg=Produto+cadastro+com+sucesso");
exit;
