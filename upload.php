<?php
session_start();
require __DIR__ . '/config/config.inc.php';
require_once __DIR__ . '/classes/Produto.class.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if (!isset($_FILES['product-image']) || $_FILES['product-image']['error'] !== UPLOAD_ERR_OK) {
    die("Erro no upload da imagem.");
}

$allowed = ['jpg','jpeg','png','gif'];
$ext = strtolower(pathinfo($_FILES['product-image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    die("Tipo de arquivo não permitido.");
}

$newName = uniqid('prod_', true) . "." . $ext;
$dest    = __DIR__ . "/uploads/" . $newName;
if (!move_uploaded_file($_FILES['product-image']['tmp_name'], $dest)) {
    die("Falha ao mover o arquivo.");
}
$imgPath = "uploads/" . $newName;

if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    die("Acesso negado.");
}
$idArt = $_SESSION['idUSUARIO'];

$nome        = trim($_POST['product-name'] ?? '');
$descricao   = trim($_POST['product-description'] ?? '');
$sizesArr    = $_POST['sizes'] ?? [];
$tamanhos    = implode(',', array_map('trim', $sizesArr));
$cores       = trim($_POST['color'] ?? '');
$quant       = (int) ($_POST['quantity'] ?? 0);
$price       = (float) ($_POST['price'] ?? 0);

$produtoObj = new Produto($pdo);
$idNovo = $produtoObj->inserir([
    'nome'       => $nome,
    'descricao'  => $descricao,
    'tamanhos'   => $tamanhos,
    'cores'      => $cores,
    'preco'      => $price,
    'quantidade' => $quant,
    'imagem'     => $imgPath,
    'id_artesao' => $idArt
]);

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nome'     => $nome,
    ':descr'    => $descricao,
    ':tamanhos' => $tamanhos,
    ':cores'    => $cores,
    ':price'    => $price,
    ':quant'    => $quant,
    ':img'      => $imgPath,
    ':art'      => $idArt
]);

$idProd = $pdo->lastInsertId();

// Categorias (existentes e novas), mesma lógica anterior...
$catIds = $_POST['categories'] ?? [];
if (!empty($_POST['new_categories'])) {
    $novas = array_filter(
        array_map('trim', explode(',', $_POST['new_categories']))
    );
    $selectCat = $pdo->prepare("SELECT idCATEGORIA FROM categorias WHERE nomeCATEGORIA = :nome");
    $insertCat = $pdo->prepare("INSERT INTO categorias (nomeCATEGORIA) VALUES (:nome)");
    foreach ($novas as $nomeCat) {
        $selectCat->execute([':nome' => $nomeCat]);
        if ($row = $selectCat->fetch(PDO::FETCH_ASSOC)) {
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

header('Location: index.php?msg=Produto+cadastro+com+sucesso');
exit;
