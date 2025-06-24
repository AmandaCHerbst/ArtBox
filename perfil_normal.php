<?php
session_start();
require __DIR__ . '/config/config.inc.php';
include 'menu.php';

if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'normal') {
    header('Location: login.php');
    exit;
}

$idUsuario = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscar favoritos
    $stmtFav = $pdo->prepare("SELECT p.idPRODUTO, p.nomePRODUTO, p.imagemPRODUTO
                              FROM favoritos f
                              JOIN produtos p ON f.idPRODUTO = p.idPRODUTO
                              WHERE f.idUSUARIO = :id");
    $stmtFav->execute([':id' => $idUsuario]);
    $favoritos = $stmtFav->fetchAll(PDO::FETCH_ASSOC);

    // Buscar dados dos favoritos para recomendações
    $stmtFavDados = $pdo->prepare("
        SELECT p.nomePRODUTO, p.nome_tipologia, p.nome_especificacao, pc.id_categoria
        FROM favoritos f
        JOIN produtos p ON f.idPRODUTO = p.idPRODUTO
        LEFT JOIN produto_categorias pc ON pc.id_produto = p.idPRODUTO
        WHERE f.idUSUARIO = :id
    ");
    $stmtFavDados->execute([':id' => $idUsuario]);
    $favoritoDados = $stmtFavDados->fetchAll(PDO::FETCH_ASSOC);

    $nomes = [];
    $tipologias = [];
    $especificacoes = [];
    $categorias = [];

    foreach ($favoritoDados as $fav) {
        if (!empty($fav['nomePRODUTO'])) $nomes[] = $fav['nomePRODUTO'];
        if (!empty($fav['nome_tipologia'])) $tipologias[] = $fav['nome_tipologia'];
        if (!empty($fav['nome_especificacao'])) $especificacoes[] = $fav['nome_especificacao'];
        if (!empty($fav['id_categoria'])) $categorias[] = $fav['id_categoria'];
    }

    $where = [];
    $params = [':id' => $idUsuario];

    if (!empty($nomes)) {
        $nomeLikes = [];
        foreach ($nomes as $i => $nome) {
            $param = ":nome$i";
            $nomeLikes[] = "p.nomePRODUTO LIKE $param";
            $params[$param] = '%' . $nome . '%';
        }
        $where[] = '(' . implode(' OR ', $nomeLikes) . ')';
    }

    if (!empty($categorias)) {
        $inCategoria = implode(',', array_map('intval', $categorias));
        $where[] = "pc.id_categoria IN ($inCategoria)";
    }

    if (!empty($tipologias)) {
        $tipologiaPlaceholders = [];
        foreach ($tipologias as $i => $tip) {
            $key = ":tipologia_$i";
            $tipologiaPlaceholders[] = $key;
            $params[$key] = $tip;
        }
        $where[] = "p.nome_tipologia IN (" . implode(',', $tipologiaPlaceholders) . ")";
    }

    if (!empty($especificacoes)) {
        $especificacaoPlaceholders = [];
        foreach ($especificacoes as $i => $esp) {
            $key = ":especificacao_$i";
            $especificacaoPlaceholders[] = $key;
            $params[$key] = $esp;
        }
        $where[] = "p.nome_especificacao IN (" . implode(',', $especificacaoPlaceholders) . ")";
    }

    $whereSQL = implode(' OR ', $where);

    $sqlRecom = "
        SELECT DISTINCT p.idPRODUTO, p.nomePRODUTO, p.imagemPRODUTO
        FROM produtos p
        LEFT JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
        WHERE p.idPRODUTO NOT IN (
            SELECT idPRODUTO FROM favoritos WHERE idUSUARIO = :id
        )
    ";

    if (!empty($whereSQL)) {
        $sqlRecom .= " AND ($whereSQL)";
    }

    $sqlRecom .= " LIMIT 12";

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

</head>
<body>
  <header>
    <!-- Menu já incluso -->
    <section class="pedidos-status">
      <div class="status-item"><a href="#"><span>💳</span><p>Não pago</p></a></div>
      <div class="status-item"><a href="#"><span>📦</span><p>Preparando</p></a></div>
      <div class="status-item"><a href="#"><span>🚛</span><p>A caminho</p></a></div>
      <div class="status-item"><a href="#"><span>⭐</span><p>Avaliar</p></a></div>
    </section>
  </header>

  <main>
    <section class="perfil-usuario">
      <img src="assets/img/perfil_normal.png" alt="Foto do Usuário">
      <h1><?= htmlspecialchars($_SESSION['nomeUSUARIO']) ?></h1>
      <div class="botoes">
        <a href="index.php" class="btn">Voltar às Compras</a>
        <a href="logout.php" class="btn btn-sair">Sair</a>
      </div>
    </section>

    <section class="favoritos">
      <h2>Meus Favoritos</h2>
      <div class="grid">
        <?php if (count($favoritos) > 0): ?>
          <?php foreach ($favoritos as $f): ?>
            <div class="produto-card">
              <a href="produto_ampliado.php?id=<?= $f['idPRODUTO'] ?>">
                <img src="<?= htmlspecialchars($f['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($f['nomePRODUTO']) ?>">
                <h3><?= htmlspecialchars($f['nomePRODUTO']) ?></h3>
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