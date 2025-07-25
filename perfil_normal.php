<?php
session_start();
require __DIR__ . '/config/config.inc.php';
include 'menu.php';

if (empty($_SESSION['idUSUARIO']) || ! in_array($_SESSION['tipo_usuario'], ['normal', 'artesao'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Dados do usuário
    $stmtUser = $pdo->prepare("SELECT nomeUSUARIO, foto_perfil FROM usuarios WHERE idUSUARIO = :id");
    $stmtUser->execute([':id' => $idUsuario]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // Favoritos com estoque agregado
    $stmtFav = $pdo->prepare(
        "SELECT
            p.idPRODUTO,
            p.nomePRODUTO,
            p.imagemPRODUTO,
            COALESCE(SUM(v.estoque), 0) AS estoque
         FROM favoritos f
         JOIN produtos p ON f.idPRODUTO = p.idPRODUTO
         LEFT JOIN variantes v ON p.idPRODUTO = v.id_produto
         WHERE f.idUSUARIO = :id
         GROUP BY p.idPRODUTO"
    );
    $stmtFav->execute([':id' => $idUsuario]);
    $favoritos = $stmtFav->fetchAll(PDO::FETCH_ASSOC);

    // Monta preferências a partir dos favoritos existentes (para recomendações)
    $stmtFavDados = $pdo->prepare(
        "SELECT p.nomePRODUTO, p.nome_tipologia, p.nome_especificacao, pc.id_categoria
         FROM favoritos f
         JOIN produtos p ON f.idPRODUTO = p.idPRODUTO
         LEFT JOIN produto_categorias pc ON pc.id_produto = p.idPRODUTO
         WHERE f.idUSUARIO = :id"
    );
    $stmtFavDados->execute([':id' => $idUsuario]);
    $favoritoDados = $stmtFavDados->fetchAll(PDO::FETCH_ASSOC);

    $nomes = [];
    $tipologias = [];
    $especificacoes = [];
    $categorias = [];

    foreach ($favoritoDados as $fav) {
        if (!empty($fav['nomePRODUTO']))      $nomes[] = $fav['nomePRODUTO'];
        if (!empty($fav['nome_tipologia']))   $tipologias[] = $fav['nome_tipologia'];
        if (!empty($fav['nome_especificacao'])) $especificacoes[] = $fav['nome_especificacao'];
        if (!empty($fav['id_categoria']))     $categorias[] = $fav['id_categoria'];
    }

    $where = [];
    $params = [':id' => $idUsuario];

    if (!empty($nomes)) {
        $likes = [];
        foreach ($nomes as $i => $nome) {
            $param = ":nome$i";
            $likes[] = "p.nomePRODUTO LIKE $param";
            $params[$param] = '%'.$nome.'%';
        }
        $where[] = '('.implode(' OR ', $likes).')';
    }
    if (!empty($categorias)) {
        $inCat = implode(',', array_map('intval', $categorias));
        $where[] = "pc.id_categoria IN ($inCat)";
    }
    if (!empty($tipologias)) {
        $ph = [];
        foreach ($tipologias as $i => $t) {
            $key = ":tipologia_$i";
            $ph[] = $key;
            $params[$key] = $t;
        }
        $where[] = 'p.nome_tipologia IN ('.implode(',', $ph).')';
    }
    if (!empty($especificacoes)) {
        $ph = [];
        foreach ($especificacoes as $i => $e) {
            $key = ":especificacao_$i";
            $ph[] = $key;
            $params[$key] = $e;
        }
        $where[] = 'p.nome_especificacao IN ('.implode(',', $ph).')';
    }

    $whereSQL = implode(' OR ', $where);

    $sqlRecom = "
        SELECT DISTINCT
          p.idPRODUTO,
          p.nomePRODUTO,
          p.imagemPRODUTO
        FROM produtos p
        LEFT JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
        LEFT JOIN variantes v ON p.idPRODUTO = v.id_produto
        WHERE p.idPRODUTO NOT IN (
            SELECT idPRODUTO FROM favoritos WHERE idUSUARIO = :id
        )";
    if (!empty($whereSQL)) {
        $sqlRecom .= " AND ($whereSQL)";
    }
    $sqlRecom .= " GROUP BY p.idPRODUTO
                   HAVING SUM(v.estoque) > 0
                   LIMIT 12";

    $stmtRecom = $pdo->prepare($sqlRecom);
    $stmtRecom->execute($params);
    $recomendados = $stmtRecom->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil do Usuário - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/perfil_normal.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
  .produto-card { position: relative; background-color: #fff; border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.06); overflow: hidden; transition: transform 0.2s ease; text-align: center;}
  .badge-indisponivel { position: absolute; top: 8px; right: 8px; background-color: #e74c3c; color: #fff; padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; z-index: 10;}
  a.indisponivel { cursor: not-allowed; opacity: 0.6;}
  </style>
</head>
<body>
<header>
  <section class="pedidos-status">
    <div class="status-item">
      <a href="#">
        <i class="fa-regular fa-credit-card fa-2x"></i>
        <p>Não pago</p>
      </a>
    </div>
    <div class="status-item">
      <a href="preparando.php">
        <i class="fa-solid fa-box-archive fa-2x"></i>
        <p>Preparando</p>
      </a>
    </div>
    <div class="status-item">
      <a href="#">
        <i class="fa-solid fa-truck-front fa-2x"></i>
        <p>A caminho</p>
      </a>
    </div>
    <div class="status-item">
      <a href="#">
        <i class="fa-regular fa-star fa-2x" style="color: #FFD43B;"></i>
        <p>Avaliar</p>
      </a>
    </div>
  </section>
</header>

<main>
  <section class="perfil-usuario">
    <img src="assets/img/perfis/<?= htmlspecialchars($usuario['foto_perfil']) ?>" alt="Foto de perfil" class="foto-perfil" /> 
    <h1><?= htmlspecialchars($usuario['nomeUSUARIO']) ?></h1>
    <a href="editar_perfil.php" class="btn-config" title="Configurar perfil">
      <i class="fa-solid fa-gear"></i>
    </a>
    <div class="botoes">
      <a href="index.php" class="btn">Voltar às Compras</a>
      <?php if ($_SESSION['tipo_usuario'] === 'artesao'): ?>
        <a href="perfil_artesao.php" class="btn">Loja</a>
      <?php endif; ?>
      <a href="logout.php" class="btn btn-sair">Sair</a>
    </div>
  </section>

  <section class="favoritos">
    <h2>Meus Favoritos</h2>
    <div class="grid">
      <?php if (count($favoritos) > 0): ?>
        <?php foreach ($favoritos as $f): ?>
          <div class="produto-card">
            <?php if ($f['estoque'] > 0): ?>
              <a href="produto_ampliado.php?id=<?= $f['idPRODUTO'] ?>">
            <?php else: ?>
              <a href="recomendacoes.php?produto=<?= $f['idPRODUTO'] ?>" class="indisponivel">
            <?php endif; ?>

                <img src="<?= htmlspecialchars($f['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($f['nomePRODUTO']) ?>">
                <h3><?= htmlspecialchars($f['nomePRODUTO']) ?></h3>

                <?php if ($f['estoque'] == 0): ?>
                  <div class="badge-indisponivel">Indisponível</div>
                <?php endif; ?>
              </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>Você ainda não favoritou nenhum produto.</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="recomendados">
    <h2>Recomendações para Você</h2>
    <div class="grid">
      <?php if (count($recomendados) > 0): ?>
        <?php foreach ($recomendados as $r): ?>
          <div class="produto-card">
            <a href="produto_ampliado.php?id=<?= $r['idPRODUTO'] ?>">
              <img src="<?= htmlspecialchars($r['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($r['nomePRODUTO']) ?>">
              <h3><?= htmlspecialchars($r['nomePRODUTO']) ?></h3>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>Não há recomendações no momento.</p>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>