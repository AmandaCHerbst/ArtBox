<?php
session_start();
include 'menu.php';
require __DIR__ . '/config/config.inc.php';

// Garante que apenas artesões acessem
if (!isset($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    die('Acesso negado. Apenas artesões podem ver este relatório.');
}

$idArtesao = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
    SELECT
        p.idPEDIDO,
        p.data_pedido,
        u.nomeUSUARIO AS cliente,
        pr.nomePRODUTO,
        ip.quantidade,
        ip.preco_unitario,
        (ip.quantidade * ip.preco_unitario) AS subtotal,
        v.valor_tipologia AS tipologia,
        v.valor_especificacao AS especificacao
    FROM pedidos p
    JOIN usuarios u ON p.id_usuario = u.idUSUARIO
    JOIN itens_pedido ip ON ip.id_pedido = p.idPEDIDO
    JOIN produtos pr ON ip.id_produto = pr.idPRODUTO
    LEFT JOIN variantes v ON v.id_produto = pr.idPRODUTO
    WHERE pr.id_artesao = ?
    ORDER BY p.data_pedido DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idArtesao]);
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Erro ao buscar relatório: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Vendas</title>
    <link rel="stylesheet" href="assets/css/relatorio_vendas.css">
</head>
<body>
    <h1>Relatório de Vendas</h1>

    <?php if (empty($vendas)): ?>
        <p>Você ainda não possui vendas registradas.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Produto</th>
                    <th>Variante</th>
                    <th>Quantidade</th>
                    <th>Preço Unit.</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendas as $v): ?>
                    <tr>
                        <td><?= $v['idPEDIDO'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['data_pedido'])) ?></td>
                        <td><?= htmlspecialchars($v['cliente']) ?></td>
                        <td><?= htmlspecialchars($v['nomePRODUTO']) ?></td>
                        <td><?= htmlspecialchars($v['tipologia'] . ' / ' . $v['especificacao']) ?></td>
                        <td><?= $v['quantidade'] ?></td>
                        <td>R$ <?= number_format($v['preco_unitario'], 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($v['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="perfil_artesao.php">&larr; Voltar ao Painel</a>
</body>
</html>
