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

// função de normalização (remove acentos, pontuação, espaços extras e deixa em lowercase)
function normalize_for_compare(string $s): string {
    $s = trim($s);
    if ($s === '') return '';

    // transliteração (preferencial)
    if (class_exists('Transliterator')) {
        try {
            $trans = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Any-Latin; Latin-ASCII');
            if ($trans) {
                $s = $trans->transliterate($s);
            }
        } catch (Throwable $e) {
            // fallback para iconv abaixo
        }
    } else {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($tmp !== false) $s = $tmp;
    }

    $s = mb_strtolower($s, 'UTF-8');
    // remove tudo que não for letra/número/espaço
    $s = preg_replace('/[^a-z0-9\s]/u', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

// validação e upload imagem principal
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

// permissões
if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    die("Acesso negado.");
}
$idArt = $_SESSION['idUSUARIO'];

// inputs do formulário (sanitizados minimamente)
$nome        = trim((string)($_POST['product-name'] ?? ''));
$descricao   = trim((string)($_POST['product-description'] ?? ''));
$tipologiaNome = trim((string)($_POST['tipologia_nome'] ?? ''));
$especificacaoNome = trim((string)($_POST['especificacao_nome'] ?? ''));
$tipologiaValores = array_filter(array_map('trim', explode(',', (string)($_POST['tipologia_valores'] ?? ''))));
$especificacaoValores = array_filter(array_map('trim', explode(',', (string)($_POST['especificacao_valores'] ?? ''))));
$price       = (float) ($_POST['price'] ?? 0);
$stocks      = $_POST['stocks'] ?? [];
$newCatsText = trim((string)($_POST['new_categories'] ?? ''));
$catIdsRaw   = $_POST['categories'] ?? [];

// normalizar catIds vindos de checkboxes (podem ser strings)
$catIds = [];
if (is_array($catIdsRaw)) {
    foreach ($catIdsRaw as $c) {
        $c = (int)$c;
        if ($c > 0) $catIds[] = $c;
    }
}

// calcular estoque total
$totalEstoque = 0;
if (is_array($stocks)) {
    foreach ($stocks as $t => $esp) {
        if (!is_array($esp)) continue;
        foreach ($esp as $e => $qtd) {
            $totalEstoque += max(0, (int)$qtd);
        }
    }
}

$produtoObj = new Produto($pdo);

// vamos usar transação para consistência: produto + categorias + variantes + imagens extras
$pdo->beginTransaction();
try {
    // inserir produto (assumindo que inserir() retorna o ID do produto)
    $idProd = $produtoObj->inserir([
        'nome'       => $nome,
        'descricao'  => $descricao,
        'nome_tipologia' => $tipologiaNome,
        'nome_especificacao' => $especificacaoNome,
        'preco'      => $price,
        'quantidade' => $totalEstoque,
        'imagem'     => $imgPathMain,
        'id_artesao' => $idArt
    ]);

    if (!$idProd) {
        throw new Exception("Não foi possível inserir o produto (id inválido retornado).");
    }

    // --- LÓGICA DE CATEGORIAS NORMALIZADAS ---
    // 1) carregar todas categorias existentes e montar mapa normalized => id
    $map = [];
    $stmtAll = $pdo->query("SELECT idCATEGORIA, nomeCATEGORIA FROM categorias");
    while ($row = $stmtAll->fetch(PDO::FETCH_ASSOC)) {
        $norm = normalize_for_compare((string)$row['nomeCATEGORIA']);
        if ($norm === '') continue;
        // se houver colisão de normalização, mantemos o primeiro id encontrado
        if (!isset($map[$norm])) {
            $map[$norm] = (int)$row['idCATEGORIA'];
        }
    }

    // preparar statements para inserir nova categoria se precisar
    $insertCat = $pdo->prepare("INSERT INTO categorias (nomeCATEGORIA) VALUES (:nome)");

    // 2) processar novas categorias digitadas (se houver)
    if ($newCatsText !== '') {
        $novas = array_filter(array_map('trim', explode(',', $newCatsText)), fn($v) => $v !== '');
        foreach ($novas as $nomeCatRaw) {
            $nomeCat = (string)$nomeCatRaw;
            $norm = normalize_for_compare($nomeCat);
            if ($norm === '') continue;

            if (isset($map[$norm])) {
                // categoria já existe (por normalização) -> usar id existente
                $catIds[] = $map[$norm];
            } else {
                // insere nova categoria com o nome original do usuário
                $insertCat->execute([':nome' => $nomeCat]);
                $newId = (int)$pdo->lastInsertId();
                $map[$norm] = $newId;
                $catIds[] = $newId;
            }
        }
    }

    // 3) garantir que todos os catIds sejam inteiros e únicos
    $catIds = array_values(array_unique(array_map('intval', $catIds), SORT_NUMERIC));

    // 4) inserir relações produto <-> categoria
    if (!empty($catIds)) {
        $stmt2 = $pdo->prepare(
          "INSERT INTO produto_categorias (id_produto, id_categoria) VALUES (:prod, :cat)"
        );
        foreach ($catIds as $catId) {
            $stmt2->execute([':prod' => $idProd, ':cat' => $catId]);
        }
    }

    // --- VARIANTES ---
    $insVar = $pdo->prepare(
        "INSERT INTO variantes (id_produto, valor_tipologia, valor_especificacao, estoque)
         VALUES (:id_produto, :tipologia, :especificacao, :estoque)"
    );
    if (is_array($stocks)) {
        foreach ($stocks as $tam => $cores) {
            $tam = trim((string)$tam);
            if ($tam === '') continue;
            if (!is_array($cores)) continue;
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
    }

    // --- IMAGENS EXTRAS ---
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

    // commit final
    $pdo->commit();

    header('Location: index.php?msg=Produto+cadastro+com+sucesso');
    exit;

} catch (Exception $e) {
    // rollback e mensagem de erro
    if ($pdo->inTransaction()) $pdo->rollBack();
    // opcional: remover imagem principal que já foi movida se quiser
    // @unlink($destMain);
    die("Erro ao cadastrar produto: " . $e->getMessage());
}
