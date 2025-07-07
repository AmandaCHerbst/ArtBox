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

// Verifica artesão logado
if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    header('Location: login.php');
    exit;
}

$idArt = $_SESSION['idUSUARIO'];

// Valida ID do produto (se em edição)
$idProd = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$modo = $idProd ? 'editar' : 'novo';

// Carrega imagem atual para edição
$existingImage = '';
if ($modo === 'editar') {
    $stmt = $pdo->prepare("SELECT imagemPRODUTO FROM produtos WHERE idPRODUTO = :id AND id_artesao = :art");
    $stmt->execute([':id' => $idProd, ':art' => $idArt]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) die('Produto não encontrado ou acesso negado.');
    $existingImage = $row['imagemPRODUTO'];
}

// Processa upload de nova imagem principal (opcional)
$imgPath = $existingImage;
$allowedExt = ['jpg','jpeg','png','gif'];

if (!empty($_FILES['product-image']['tmp_name'])) {
    if ($_FILES['product-image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['product-image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            die('Formato de imagem principal não suportado.');
        }
        $newName = uniqid('upd_main_', true) . "." . $ext;
        $dest = __DIR__ . '/uploads/' . $newName;
        if (!move_uploaded_file($_FILES['product-image']['tmp_name'], $dest)) {
            die('Erro ao mover imagem principal.');
        }
        $imgPath = 'uploads/' . $newName;
    }
} elseif ($modo === 'novo') {
    die('Erro no upload da imagem principal.');
}

// Coleta dados do formulário
$nome      = trim($_POST['product-name'] ?? '');
$desc      = trim($_POST['product-description'] ?? '');
$tipNome   = trim($_POST['tipologia_nome'] ?? '');
$espNome   = trim($_POST['especificacao_nome'] ?? '');
$price     = (float) ($_POST['price'] ?? 0);$stocks    = $_POST['stocks'] ?? [];
$catIds    = $_POST['categories'] ?? [];
$newCats   = trim($_POST['new_categories'] ?? '');

// Atualiza ou insere produto
if ($modo === 'editar') {
    $sql = "UPDATE produtos SET
                nomePRODUTO = :nome,
                descricaoPRODUTO = :desc,
                precoPRODUTO = :preco,
                nome_tipologia = :tip,
                nome_especificacao = :esp,
                imagemPRODUTO = :img
             WHERE idPRODUTO = :id AND id_artesao = :art";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':desc' => $desc,
        ':preco'=> $price,
        ':tip'  => $tipNome,
        ':esp'  => $espNome,
        ':img'  => $imgPath,
        ':id'   => $idProd,
        ':art'  => $idArt
    ]);

    // Remove antigas categorias e variantes
    $pdo->prepare("DELETE FROM produto_categorias WHERE id_produto = :id")->execute([':id'=>$idProd]);
    $pdo->prepare("DELETE FROM variantes WHERE id_produto = :id")->execute([':id'=>$idProd]);
} else {
    // Insere novo produto
    $totalEstoque = 0;
    foreach ($stocks as $t => $cores) {
        foreach ($cores as $c => $q) {
            $totalEstoque += max(0, (int)$q);
        }
    }
    $produtoObj = new Produto($pdo);
    $idProd = $produtoObj->inserir([
        'nome'               => $nome,
        'descricao'          => $desc,
        'nome_tipologia'     => $tipNome,
        'nome_especificacao' => $espNome,
        'preco'              => $price,
        'quantidade'         => $totalEstoque,
        'imagem'             => $imgPath,
        'id_artesao'         => $idArt
    ]);
}

// Insere categorias novas e existentes
if (!empty($catIds)) {
    $insCat = $pdo->prepare("INSERT INTO produto_categorias (id_produto,id_categoria) VALUES(:p,:c)");
    foreach ($catIds as $c) {
        $insCat->execute([':p'=>$idProd,':c'=>$c]);
    }
}
if (!empty($newCats)) {
    $arr = array_filter(array_map('trim', explode(',', $newCats)));
    $sel = $pdo->prepare("SELECT idCATEGORIA FROM categorias WHERE nomeCATEGORIA=:n");
    $ins = $pdo->prepare("INSERT INTO categorias (nomeCATEGORIA) VALUES(:n)");
    foreach ($arr as $n) {
        $sel->execute([':n'=>$n]);
        if ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
            $cid = $r['idCATEGORIA'];
        } else {
            $ins->execute([':n'=>$n]);
            $cid = $pdo->lastInsertId();
        }
        $pdo->prepare("INSERT INTO produto_categorias (id_produto,id_categoria) VALUES(:p,:c)")
            ->execute([':p'=>$idProd,':c'=>$cid]);
    }
}

// Insere variantes
$insVar = $pdo->prepare("INSERT INTO variantes (id_produto, valor_tipologia, valor_especificacao, estoque) VALUES(:p,:t,:e,:s)");
foreach ($stocks as $t => $cores) {
    foreach ($cores as $c => $q) {
        $insVar->execute([':p'=>$idProd,':t'=>$t,':e'=>$c,':s'=>max(0,(int)$q)]);
    }
}

// Upload imagens adicionais
if (!empty($_FILES['product-images']['tmp_name'][0])) {
    $files = $_FILES['product-images'];
    $max = min(count($files['name']),5);
    for ($i=0;$i<$max;$i++){
        if ($files['error'][$i]===UPLOAD_ERR_OK){
            $ext = strtolower(pathinfo($files['name'][$i],PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt)){
                $new = uniqid('upd_ext_',true).".".$ext;
                $dest = __DIR__.'/uploads/'.$new;
                if (move_uploaded_file($files['tmp_name'][$i],$dest)){
                    $pdo->prepare("INSERT INTO produto_imagens (id_produto,caminho) VALUES(:p,:c)")
                        ->execute([':p'=>$idProd,':c'=>'uploads/'.$new]);
                }
            }
        }
    }
}

header('Location: perfil_artesao.php?msg=Produto+atualizado+com+sucesso');
exit;
