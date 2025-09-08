<?php
// avaliar.php
session_start();
include 'menu.php';
require __DIR__ . '/config/config.inc.php';

if (empty($_SESSION['idUSUARIO'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = (int) $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Buscar pedidos do usuário com status "entregue"
    $sqlPedidos = <<<SQL
SELECT p.idPEDIDO, p.data_pedido, p.status
FROM pedidos p
WHERE p.id_usuario = :uid AND p.status = 'entregue'
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
  prod.idPRODUTO,
  prod.nomePRODUTO,
  prod.imagemPRODUTO
FROM itens_pedido ip
JOIN produtos prod ON ip.id_produto = prod.idPRODUTO
WHERE ip.id_pedido = :pid
SQL;
    $stmtItens = $pdo->prepare($sqlItens);

    // Preparar consulta para verificar se o usuário já avaliou o produto
    $stmtCheckComent = $pdo->prepare("SELECT nota, comentario, criado_em FROM comentarios WHERE produto_id = :prodid AND usuario_id = :uid ORDER BY criado_em DESC LIMIT 1");

} catch (PDOException $e) {
    die('Erro: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Avaliar Produtos - ARTBOX</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 920px;
            margin: 40px auto;
            padding: 0 20px;
            background-color: #fafafa;
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 24px;
            color: #5C3A21;
        }
        .pedido-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            padding: 18px;
        }
        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            color: #A95C38;
            font-weight: bold;
        }
        .itens-list .item {
            display:flex;
            gap:12px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            align-items:center;
        }
        .item img {
            width:70px;
            height:70px;
            object-fit:cover;
            border-radius:8px;
            border:1px solid #eee;
        }
        .item-info { flex:1; }
        .item-actions { width:170px; text-align:right; }
        .btn-avaliar {
            background-color: #5C3A21;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display:inline-block;
        }
        .btn-avaliar:hover { background:#A95C38; }
        .avaliado {
            font-size:0.95rem;
            color:#4b5563;
            background:#f4f6f8;
            padding:6px 10px;
            border-radius:8px;
            display:inline-block;
        }
        .nota-stars { color: #b08a3b; font-weight:bold; margin-right:6px; }
        .empty-msg { text-align:center; font-style:italic; color:#888; margin-top:40px; }
        a.back-link {
            display: block;
            margin-top: 30px;
            text-align: center;
            color: #A95C38;
            font-weight: bold;
            text-decoration: none;
        }
        a.back-link:hover { color:#5C3A21; text-decoration:underline; }
    </style>
</head>
<body>
    <h1>Avaliações — Produtos Entregues</h1>

    <?php if (empty($pedidos)): ?>
        <p class="empty-msg">Você não possui pedidos marcados como entregues no momento.</p>
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
                        if (empty($itens)) {
                            echo '<div class="empty-msg">Nenhum item encontrado neste pedido.</div>';
                        }
                        foreach ($itens as $item):
                            // checar se usuário já avaliou este produto
                            $stmtCheckComent->execute([':prodid' => $item['idPRODUTO'], ':uid' => $idUsuario]);
                            $comentExist = $stmtCheckComent->fetch(PDO::FETCH_ASSOC);
                    ?>
                        <div class="item">
                            <?php if (!empty($item['imagemPRODUTO'])): ?>
                                <img src="<?= htmlspecialchars($item['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($item['nomePRODUTO']) ?>">
                            <?php else: ?>
                                <img src="assets/placeholder.png" alt="sem imagem">
                            <?php endif; ?>

                            <div class="item-info">
                                <strong><?= htmlspecialchars($item['nomePRODUTO']) ?></strong><br>
                                Qtd: <?= (int)$item['quantidade'] ?> — R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
                            </div>

                            <div class="item-actions">
                                <?php if ($comentExist): ?>
                                    <div class="avaliado" title="<?= htmlspecialchars($comentExist['comentario']) ?>">
                                        <span class="nota-stars">
                                            <?php
                                                $n = (int)$comentExist['nota'];
                                                for ($i=1;$i<=5;$i++) echo $i <= $n ? '★' : '☆';
                                            ?>
                                        </span>
                                        Já avaliado
                                    </div>
                                    <div style="font-size:0.85rem;color:#666;margin-top:6px;">
                                        <?= date('d/m/Y', strtotime($comentExist['criado_em'])) ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Link para add_coment.php passando produto_id (pode adicionar pedido_id se quiser) -->
                                    <a class="btn-avaliar" href="add_coment.php?produto_id=<?= (int)$item['idPRODUTO'] ?>&pedido_id=<?= (int)$pedido['idPEDIDO'] ?>">Avaliar</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="perfil_normal.php" class="back-link">&larr; Voltar ao Perfil</a>
</body>
</html>
