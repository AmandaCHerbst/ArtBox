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

// função de normalização (mesma do upload.php)
function normalize_for_compare(string $s): string {
    $s = trim($s);
    if ($s === '') return '';

    if (class_exists('Transliterator')) {
        try {
            $trans = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Any-Latin; Latin-ASCII');
            if ($trans) $s = $trans->transliterate($s);
        } catch (Throwable $e) {
            // fallback para iconv
        }
    } else {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($tmp !== false) $s = $tmp;
    }

    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s]/u', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
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
        // opcional: remover a imagem antiga do servidor (descomente se quiser)
        // if ($existingImage && file_exists(__DIR__ . '/' . $existingImage)) @unlink(__DIR__ . '/' . $existingImage);
    }
} elseif ($modo === 'novo') {
    die('Erro no upload da imagem principal.');
}

// Coleta dados do formulário
$nome      = trim($_POST['product-name'] ?? '');
$desc      = trim($_POST['product-description'] ?? '');
$tipNome   = trim($_POST['tipologia_nome'] ?? '');
$espNome   = trim($_POST['especificacao_nome'] ?? '');
$price     = (float) ($_POST['price'] ?? 0);
$stocks    = $_POST['stocks'] ?? [];
$catIdsRaw = $_POST['categories'] ?? [];
$newCats   = trim($_POST['new_categories'] ?? '');

// normalizar catIds vindos de checkboxes
$catIds = [];
if (is_array($catIdsRaw)) {
    foreach ($catIdsRaw as $c) {
        $c = (int)$c;
        if ($c > 0) $catIds[] = $c;
    }
}

$produtoObj = new Produto($pdo);

// usa transação para que tudo seja consistente
$pdo->beginTransaction();
try {
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
        if (is_array($stocks)) {
            foreach ($stocks as $t => $cores) {
                if (!is_array($cores)) continue;
                foreach ($cores as $c => $q) {
                    $totalEstoque += max(0, (int)$q);
                }
            }
        }
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
        if (!$idProd) throw new Exception("Erro ao inserir produto (id inválido).");
    }

    // --- LÓGICA DE CATEGORIAS NORMALIZADAS (igual ao upload.php) ---
    // 1) carregar mapa normalized => id
    $map = [];
    $stmtAll = $pdo->query("SELECT idCATEGORIA, nomeCATEGORIA FROM categorias");
    while ($row = $stmtAll->fetch(PDO::FETCH_ASSOC)) {
        $norm = normalize_for_compare((string)$row['nomeCATEGORIA']);
        if ($norm === '') continue;
        if (!isset($map[$norm])) $map[$norm] = (int)$row['idCATEGORIA'];
    }
    $insertCat = $pdo->prepare("INSERT INTO categorias (nomeCATEGORIA) VALUES (:nome)");

    // 2) processar novas categorias digitadas
    if ($newCats !== '') {
        $arr = array_filter(array_map('trim', explode(',', $newCats)), fn($v)=>$v!=='');
        foreach ($arr as $n) {
            $norm = normalize_for_compare($n);
            if ($norm === '') continue;

            if (isset($map[$norm])) {
                $catIds[] = $map[$norm];
            } else {
                $insertCat->execute([':nome' => $n]);
                $newId = (int)$pdo->lastInsertId();
                $map[$norm] = $newId;
                $catIds[] = $newId;
            }
        }
    }

    // 3) garantir inteiros e únicos
    $catIds = array_values(array_unique(array_map('intval', $catIds), SORT_NUMERIC));

    // 4) inserir relações produto <-> categoria
    if (!empty($catIds)) {
        $insCatRel = $pdo->prepare("INSERT INTO produto_categorias (id_produto,id_categoria) VALUES(:p,:c)");
        foreach ($catIds as $c) {
            $insCatRel->execute([':p'=>$idProd,':c'=>$c]);
        }
    }

    // Insere variantes
    $insVar = $pdo->prepare("INSERT INTO variantes (id_produto, valor_tipologia, valor_especificacao, estoque) VALUES(:p,:t,:e,:s)");
    if (is_array($stocks)) {
        foreach ($stocks as $t => $cores) {
            if (!is_array($cores)) continue;
            foreach ($cores as $c => $q) {
                $insVar->execute([':p'=>$idProd,':t'=>trim($t),':e'=>trim($c),':s'=>max(0,(int)$q)]);
            }
        }
    }

    // Upload imagens adicionais (até 20)
    if (!empty($_FILES['product-images']['tmp_name'][0])) {
        $files = $_FILES['product-images'];
        $max = min(count($files['name']), 20); // alterado para 20
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

    $pdo->commit();

    header('Location: perfil_artesao.php?msg=Produto+atualizado+com+sucesso');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erro ao processar produto: " . $e->getMessage());
}
