<?php
require 'config/config.inc.php';
$pdo = new PDO(DSN, USUARIO, SENHA);

if (!isset($_FILES['product-image']) || $_FILES['product-image']['error'] !== UPLOAD_ERR_OK) {
  die("Erro no upload da imagem.");
}
$allowed = ['jpg','jpeg','png','gif'];
$ext = strtolower(pathinfo($_FILES['product-image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
  die("Tipo de arquivo não permitido.");
}

$newName = uniqid('prod_', true) . "." . $ext;
$dest = __DIR__ . "/uploads/" . $newName;
if (!move_uploaded_file($_FILES['product-image']['tmp_name'], $dest)) {
  die("Falha ao mover o arquivo.");
}

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

$catIds = [];

if (!empty($_POST['categories'])) {
    $catIds = $_POST['categories']; 
}

if (!empty($_POST['new_categories'])) {
    $novas = array_filter(array_map('trim', explode(',', $_POST['new_categories'])));
    $selectCat = $pdo->prepare("SELECT idCATEGORIA FROM categorias WHERE nomeCATEGORIA = :nome");
    $insertCat = $pdo->prepare("INSERT INTO categorias (nomeCATEGORIA) VALUES (:nome)");
    foreach ($novas as $nomeCat) {
        $selectCat->execute([':nome' => $nomeCat]);
        $row = $selectCat->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $catIds[] = $row['idCATEGORIA'];
        } else {
            $insertCat->execute([':nome' => $nomeCat]);
            $catIds[] = $pdo->lastInsertId();
        }
    }
}

if (!empty($catIds)) {
    $sql2 = "INSERT INTO produto_categorias (id_produto, id_categoria) VALUES (:prod, :cat)";
    $stmt2 = $pdo->prepare($sql2);
    foreach ($catIds as $catId) {
        $stmt2->execute([
            ':prod' => $idProd,
            ':cat'  => $catId
        ]);
    }
}

header("Location: index.php?msg=Produto+cadastro+com+sucesso");
exit;
