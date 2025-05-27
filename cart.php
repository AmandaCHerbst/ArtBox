<?php
// cart.php - Gerenciamento do carrinho de compras
session_start();
require 'config/config.inc.php';

// Inicializa o carrinho caso não exista
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Se veio via POST, adiciona/atualiza item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $productId = (int) $_POST['product_id'];
    $qty = max(1, (int) $_POST['quantity']);

    // Se já tem no carrinho, soma a quantidade
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $qty;
    } else {
        $_SESSION['cart'][$productId] = $qty;
    }

    // Redireciona para evitar reenvio de formulário
    header('Location: cart.php');
    exit;
}

// Conecta ao banco para buscar dados dos produtos no carrinho
try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

$items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in  = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT idPRODUTO, nomePRODUTO, precoPRODUTO, imagemPRODUTO FROM produtos WHERE idPRODUTO IN ($in)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Combina dados e calcula subtotal
    foreach ($products as $prod) {
        $pid = $prod['idPRODUTO'];
        $qty = $_SESSION['cart'][$pid];
        $subtotal = $prod['precoPRODUTO'] * $qty;
        $total += $subtotal;
        $items[] = [
            'id' => $pid,
            'nome' => $prod['nomePRODUTO'],
            'preco' => $prod['precoPRODUTO'],
            'imagem' => $prod['imagemPRODUTO'],
            'quantidade' => $qty,
            'subtotal' => $subtotal
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - ARTBOX</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .cart-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .cart-table th, .cart-table td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .cart-table img { width: 50px; height: 50px; object-fit: cover; }
        .total { font-size: 1.2rem; text-align: right; margin-bottom: 20px; }
        .actions { text-align: right; }
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0069d9; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <h1>Seu Carrinho</h1>

    <?php if (empty($items)): ?>
        <p>O carrinho está vazio. <a href="index.php">Voltar às compras</a></p>
    <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Imagem</th>
                    <th>Produto</th>
                    <th>Preço</th>
                    <th>Quantidade</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><img src="<?= htmlspecialchars($item['imagem']) ?>" alt=""></td>
                    <td><?= htmlspecialchars($item['nome']) ?></td>
                    <td>R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                    <td><?= $item['quantidade'] ?></td>
                    <td>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total">
            Total: R$ <?= number_format($total, 2, ',', '.') ?>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="window.location.href='checkout.php'">Finalizar Compra</button>
        </div>
    <?php endif; ?>
</body>
</html>
