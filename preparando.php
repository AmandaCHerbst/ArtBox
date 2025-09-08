<?php
session_start();
include 'menu.php';
require __DIR__ . '/config/config.inc.php';
require_once 'classes/Pedido.class.php';
require_once 'classes/ItensPedido.class.php';

if (!isset($_SESSION['idUSUARIO'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pedidoService = new Pedido($pdo);
    $itensService = new ItensPedido($pdo);

    // Busca todos os pares (pedido, artesão) que já foram aprovados — independentemente do status do pedido
    $stmt = $pdo->prepare(
        "SELECT DISTINCT p.idPEDIDO, p.valor_total, p.data_pedido, pa.id_artesao, pa.data_atualizacao
         FROM pedidos_artesao pa
         JOIN pedidos p ON pa.id_pedido = p.idPEDIDO
         WHERE p.id_usuario = :idUsuario
           AND pa.status = 'aprovado'
         ORDER BY p.data_pedido DESC, pa.data_atualizacao DESC"
    );
    $stmt->execute([':idUsuario' => $idUsuario]);
    $aprovados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

/**
 * Formata valor como moeda BR
 */
function format_br_currency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pedidos em Preparo - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/pedidos_preparo.css">
  <style>
    /* Pequeno refinamento visual para cards por artesão */
    .pedido-card { background: #fff; border-radius: 8px; padding: 16px; margin: 14px auto; max-width: 900px; box-shadow: 0 6px 18px rgba(0,0,0,0.04); }
    .pedido-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
    .pedido-valor { font-weight:700; color:#5C3A21; }
    .itens-list { margin-top:8px; padding-left:10px; }
    .item { padding:6px 0; border-bottom:1px solid #f0f0f0; }
    .meta { font-size:0.9rem; color:#666; }
    .subsection { margin-top:10px; padding:10px; border-radius:6px; background:#fbfbfb; }
    .empty-msg { text-align:center; color:#666; margin-top:30px; }
  </style>
</head>
<body>
  <header class="page-header">
    <h1>Seus Itens em Preparo</h1>
    <p class="meta">Aqui aparecem os itens que os artesãos já aprovaram — você pode acompanhar cada parte do pedido separadamente.</p>
  </header>
  <main>
    <?php if (empty($aprovados)): ?>
      <p class="empty-msg">Você ainda não possui itens aprovados para preparo.</p>
    <?php else: ?>
      <?php
        // Para cada par (pedido, artesão aprovado) buscamos apenas os itens daquele artesão dentro do pedido
        $stmtItems = $pdo->prepare("
            SELECT ip.*, p.nomePRODUTO, p.id_artesao
            FROM itens_pedido ip
            JOIN produtos p ON ip.id_produto = p.idPRODUTO
            WHERE ip.id_pedido = :pid
              AND p.id_artesao = :aid
        ");

        $stmtArtNome = $pdo->prepare("SELECT nomeUSUARIO FROM usuarios WHERE idUSUARIO = :aid");
      ?>

      <?php foreach ($aprovados as $ap): ?>
        <?php
          $pedidoId = (int)$ap['idPEDIDO'];
          $idArt = (int)$ap['id_artesao'];

          // pega nome do artesão
          $stmtArtNome->execute([':aid' => $idArt]);
          $artRow = $stmtArtNome->fetch(PDO::FETCH_ASSOC);
          $nomeArt = $artRow ? $artRow['nomeUSUARIO'] : "Artesão #{$idArt}";

          // pega somente os itens deste pedido pertencentes a este artesão
          $stmtItems->execute([':pid' => $pedidoId, ':aid' => $idArt]);
          $itens = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

          if (empty($itens)) {
              // nada deste artesão nesse pedido (proteção) => pular
              continue;
          }

          // calcular subtotal desses itens
          $subtotal = 0.0;
          foreach ($itens as $it) {
              $qty = (int)($it['quantidade'] ?? 0);
              $preco = isset($it['preco_unitario']) ? (float)$it['preco_unitario'] : 0.0;
              $subtotal += ($preco * $qty);
          }
        ?>

        <div class="pedido-card">
          <div class="pedido-header">
            <div>
              <h2>Pedido #<?= htmlspecialchars($pedidoId) ?></h2>
              <div class="meta">Artesão: <strong><?= htmlspecialchars($nomeArt) ?></strong> — aprovado em <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ap['data_atualizacao'] ?? $ap['data_pedido']))) ?></div>
            </div>
            <div style="text-align:right;">
              <div class="pedido-valor"><?= format_br_currency($subtotal) ?></div>
              <div class="meta">Status: <strong>Em preparo</strong></div>
            </div>
          </div>

          <div class="subsection">
            <div class="itens-list">
              <?php foreach ($itens as $item): ?>
                <div class="item">
                  • <?= htmlspecialchars($item['nomePRODUTO']) ?>
                  <?php if (!empty($item['id_variante'])): ?>
                    (variante #<?= htmlspecialchars($item['id_variante']) ?>)
                  <?php endif; ?>
                  — Qtd: <?= (int)$item['quantidade'] ?> — R$ <?= number_format((float)$item['preco_unitario'], 2, ',', '.') ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</body>
</html>
