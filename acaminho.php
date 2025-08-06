<?php
session_start();
include 'menu.php'; // se usar menu comum
require __DIR__ . '/config/config.inc.php';

if (empty($_SESSION['idUSUARIO'])) {
    header('Location: login.php');
    exit;
}


$idUsuario = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Processa a confirmação de entrega
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar_entrega_id'])) {
        $pedidoId = (int) $_POST['confirmar_entrega_id'];

        // Atualiza status do pedido para "entregue"
        $stmtUpdate = $pdo->prepare("UPDATE pedidos SET status = 'entregue' WHERE idPEDIDO = :id AND id_usuario = :uid");
        $stmtUpdate->execute([':id' => $pedidoId, ':uid' => $idUsuario]);

        // Redireciona para evitar reenvio
        header('Location: caminho.php');
        exit;
    }

    // Buscar pedidos do usuário com status "enviado"
    $sqlPedidos = <<<SQL
SELECT p.idPEDIDO, p.data_pedido, p.status
FROM pedidos p
WHERE p.id_usuario = :uid AND p.status = 'enviado'
ORDER BY p.data_pedido DESC
SQL;
    $stmt = $pdo->prepare($sqlPedidos);
    $stmt->execute([':uid' => $idUsuario]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preparar consulta para itens do pedido
    $sqlItens = <<<SQL
SELECT
  ip.id_pedido,
  ip.quantidade,
  ip.preco_unitario,
  p.nomePRODUTO,
  p.imagemPRODUTO
FROM itens_pedido ip
JOIN produtos p ON ip.id_produto = p.idPRODUTO
WHERE ip.id_pedido = :pid
SQL;
    $stmtItens = $pdo->prepare($sqlItens);

} catch (PDOException $e) {
    die('Erro: ' . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Pedidos a Caminho - ARTBOX</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            background-color: #fafafa;
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #5C3A21;
        }
        .pedido-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            padding: 20px;
        }
        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            color: #A95C38;
            font-weight: bold;
        }
        .itens-list .item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .btn-confirmar {
            background-color: #5C3A21;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
            width: 100%;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        .btn-confirmar:hover {
            background-color: #A95C38;
        }
        .empty-msg {
            text-align: center;
            font-style: italic;
            color: #888;
            margin-top: 40px;
        }
        a.back-link {
            display: block;
            margin-top: 40px;
            text-align: center;
            color: #A95C38;
            font-weight: bold;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        a.back-link:hover {
            color: #5C3A21;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Pedidos a Caminho</h1>

    <?php if (empty($pedidos)): ?>
        <p class="empty-msg">Você não possui pedidos a caminho no momento.</p>
    <?php else: ?>
        <?php foreach ($pedidos as $pedido): ?>
            <div class="pedido-card">
                <div class="pedido-header">
                    <div>Pedido #<?= htmlspecialchars($pedido['idPEDIDO']) ?></div>
                    <div><?= date('d/m/Y \à\s H:i', strtotime($pedido['data_pedido'])) ?></div>
                </div>

                <div class="itens-list">
                    <?php
                        $stmtItens->execute([':pid' => $pedido['idPEDIDO']]);
                        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($itens as $item):
                    ?>
                        <div class="item">
                            <strong><?= htmlspecialchars($item['nomePRODUTO']) ?></strong> —
                            Qtd: <?= (int)$item['quantidade'] ?> —
                            R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post">
                    <input type="hidden" name="confirmar_entrega_id" value="<?= $pedido['idPEDIDO'] ?>" />
                    <button type="submit" class="btn-confirmar">Confirmar Entrega</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="perfil_normal.php" class="back-link">&larr; Voltar ao Perfil</a>
</body>
</html>
