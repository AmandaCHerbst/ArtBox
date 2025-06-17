<?php
session_start();
require __DIR__ . '/config/config.inc.php';
require_once __DIR__ . '/classes/Produto.class.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar: " . $e->getMessage());
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
$coresArr    = array_filter(array_map('trim', explode(',', $_POST['color'] ?? '')));
$price       = (float) ($_POST['price'] ?? 0);
$stocks      = $_POST['stocks'] ?? [];      
$newCatsText = trim($_POST['new_categories'] ?? '');
$catIds      = $_POST['categories'] ?? [];

$totalEstoque = 0;
foreach ($stocks as $tam => $cores) {
    foreach ($cores as $cor => $qtd) {
        $totalEstoque += max(0, (int)$qtd);
    }
}

$produtoObj = new Produto($pdo);
$idProd     = $produtoObj->inserir([
    'nome'       => $nome,
    'descricao'  => $descricao,
    'tamanhos'   => implode(',', $sizesArr),
    'cores'      => implode(',', $coresArr),
    'preco'      => $price,
    'quantidade' => $totalEstoque,
    'imagem'     => $imgPath,
    'id_artesao' => $idArt
]);

if ($newCatsText !== '') {
    $novas = array_filter(array_map('trim', explode(',', $newCatsText)));
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
    $stmt2 = $pdo->prepare(
      "INSERT INTO produto_categorias (id_produto, id_categoria) VALUES (:prod, :cat)"
    );
    foreach ($catIds as $catId) {
        $stmt2->execute([':prod'=>$idProd, ':cat'=>$catId]);
    }
}

$insVar = $pdo->prepare("
    INSERT INTO variantes (id_produto, tamanho, cor, estoque)
    VALUES (:id_produto, :tamanho, :cor, :estoque)
");
foreach ($sizesArr as $tam) {
    $tam = trim($tam);
    if ($tam === '') continue;
    foreach ($coresArr as $cor) {
        $qtdVar = isset($stocks[$tam][$cor])
                  ? max(0, (int)$stocks[$tam][$cor])
                  : 0;
        $insVar->execute([
            ':id_produto' => $idProd,
            ':tamanho'    => $tam,
            ':cor'        => $cor,
            ':estoque'    => $qtdVar
        ]);
    }
}
header('Location: index.php?msg=Produto+cadastro+com+sucesso');
exit;
