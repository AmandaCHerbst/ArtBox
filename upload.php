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
    die("Erro no upload da imagem principal.");
}
$allowed = ['jpg','jpeg','png','gif'];
$extMain = strtolower(pathinfo($_FILES['product-image']['name'], PATHINFO_EXTENSION));
if (!in_array($extMain, $allowed)) {
    die("Tipo de arquivo principal não permitido.");
}
$newNameMain = uniqid('prod_main_', true) . "." . $extMain;
$destMain    = __DIR__ . "/uploads/" . $newNameMain;
if (!move_uploaded_file($_FILES['product-image']['tmp_name'], $destMain)) {
    die("Falha ao mover o arquivo principal.");
}
$imgPathMain = "uploads/" . $newNameMain;

if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    die("Acesso negado.");
}
$idArt = $_SESSION['idUSUARIO'];

$nome        = trim($_POST['product-name'] ?? '');
$descricao   = trim($_POST['product-description'] ?? '');
$tipologiaNome = trim($_POST['tipologia_nome'] ?? '');
$especificacaoNome = trim($_POST['especificacao_nome'] ?? '');
$tipologiaValores = array_filter(array_map('trim', explode(',', $_POST['tipologia_valores'] ?? '')));
$especificacaoValores = array_filter(array_map('trim', explode(',', $_POST['especificacao_valores'] ?? '')));
$price       = (float) ($_POST['price'] ?? 0);
$stocks      = $_POST['stocks'] ?? [];
$newCatsText = trim($_POST['new_categories'] ?? '');
$catIds      = $_POST['categories'] ?? [];

$totalEstoque = 0;
foreach ($stocks as $t => $esp) {
    foreach ($esp as $e => $qtd) {
        $totalEstoque += max(0, (int)$qtd);
    }
}

$produtoObj = new Produto($pdo);
$idProd     = $produtoObj->inserir([
    'nome'       => $nome,
    'descricao'  => $descricao,
    'nome_tipologia' => $tipologiaNome,
    'nome_especificacao' => $especificacaoNome,
    'preco'      => $price,
    'quantidade' => $totalEstoque,
    'imagem'     => $imgPathMain,
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
        $stmt2->execute([':prod' => $idProd, ':cat' => $catId]);
    }
}

$insVar = $pdo->prepare(
    "INSERT INTO variantes (id_produto, valor_tipologia, valor_especificacao, estoque)
     VALUES (:id_produto, :tipologia, :especificacao, :estoque)"
);
foreach ($stocks as $tam => $cores) {
    $tam = trim($tam);
    if ($tam === '') continue;
    foreach ($cores as $cor => $qtd) {
        $qtdVar = isset($stocks[$tam][$cor]) ? max(0, (int)$stocks[$tam][$cor]) : 0;
        $insVar->execute([
            ':id_produto' => $idProd,
            ':tipologia'    => $tam,
            ':especificacao'        => $cor,
            ':estoque'    => $qtdVar
        ]);
    }
}

if (isset($_FILES['product-images'])) {
    $extraFiles = $_FILES['product-images'];
    $countFiles = count($extraFiles['name']);
    $maxExtras = 5;
    for ($i = 0; $i < min($countFiles, $maxExtras); $i++) {
        if ($extraFiles['error'][$i] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($extraFiles['name'][$i], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $newName = uniqid('prod_extra_', true) . "." . $ext;
                $dest    = __DIR__ . "/uploads/" . $newName;
                if (move_uploaded_file($extraFiles['tmp_name'][$i], $dest)) {
                    $imgPath = "uploads/" . $newName;
                    $stmtImg = $pdo->prepare(
                        "INSERT INTO produto_imagens (id_produto, caminho) VALUES (:prod, :caminho)"
                    );
                    $stmtImg->execute([':prod' => $idProd, ':caminho' => $imgPath]);
                }
            }
        }
    }
}

header('Location: index.php?msg=Produto+cadastro+com+sucesso');
exit;