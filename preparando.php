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

    // Consulta pedidos com status "preparando"
    $stmt = $pdo->prepare(
        "SELECT DISTINCT p.*
         FROM pedidos p
         JOIN pedidos_artesao pa ON pa.id_pedido = p.idPEDIDO
         WHERE p.id_usuario = :idUsuario
           AND pa.status = 'aprovado'
         ORDER BY p.data_pedido DESC"
    );
    $stmt->execute([':idUsuario' => $idUsuario]);
    $pedidosPreparando = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pedidos em Preparo - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/pedidos_preparo.css">
</head>
<body>
  <header class="page-header">
    <h1>Seus Pedidos em Preparo</h1>
  </header>
  <main>
    <?php if (empty($pedidosPreparando)): ?>
      <p class="empty-msg">Você ainda não possui pedidos em preparo.</p>
    <?php else: ?>
      <?php foreach ($pedidosPreparando as $pedido): ?>
        <div class="pedido-card">
          <div class="pedido-header">
            <h2>Pedido #<?= $pedido['idPEDIDO'] ?></h2>
            <span class="pedido-valor">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></span>
          </div>
          <p class="status">Status: <strong>Em preparo</strong></p>
          <div class="itens-list">
            <?php
              $itens = $itensService->listarPorPedido($pedido['idPEDIDO']);
              foreach ($itens as $item):
            ?>
              <div class="item">
                • <?= htmlspecialchars($item['nomePRODUTO']) ?> — Qtd: <?= $item['quantidade'] ?> — R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</body>
</html>
