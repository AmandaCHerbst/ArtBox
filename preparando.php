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
   $stmt = $pdo->prepare("
  SELECT DISTINCT p.*
  FROM pedidos p
  JOIN pedidos_artesao pa ON pa.id_pedido = p.idPEDIDO
  WHERE p.id_usuario = :idUsuario
    AND pa.status = 'aprovado'
  ORDER BY p.data_pedido DESC
");
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
  <title>Pedidos em Preparo</title>
  <link rel="stylesheet" href="assets/css/estilos.css">
  <style>
    body { font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px; }
    h1 { text-align: center; color: #333; }
    .pedido { background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 0 8px rgba(0,0,0,0.1); }
    .pedido h2 { margin-top: 0; color: #007bff; }
    .item { margin-left: 15px; padding: 5px 0; }
    .status { font-weight: bold; color: #28a745; }
    .vazio { text-align: center; margin-top: 50px; color: #777; }
  </style>
</head>
<body>
  <h1>Seus Pedidos em Preparo</h1>

  <?php if (empty($pedidosPreparando)): ?>
    <p class="vazio">Você ainda não possui pedidos em preparo.</p>
  <?php else: ?>
    <?php foreach ($pedidosPreparando as $pedido): ?>
      <div class="pedido">
        <h2>Pedido #<?= $pedido['idPEDIDO'] ?> - Valor Total: R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></h2>
        <p class="status">Status: Em preparo</p>
        <div class="itens">
          <?php
            $itens = $itensService->listarPorPedido($pedido['idPEDIDO']);
            foreach ($itens as $item):
          ?>
            <div class="item">
              • <?= htmlspecialchars($item['nomePRODUTO']) ?> - Quantidade: <?= $item['quantidade'] ?> - Preço unitário: R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>