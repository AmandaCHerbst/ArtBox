<?php
session_start();                                       
include 'menu.php';
require __DIR__ . '/config/config.inc.php';
            
try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity']) && !isset($_POST['quantities'])) {
    $productId = (int) $_POST['product_id'];
    $qtyToAdd  = max(1, (int) $_POST['quantity']);

    $stmtStock = $pdo->prepare("SELECT quantidade FROM produtos WHERE idPRODUTO = ?");
    $stmtStock->execute([$productId]);
    $stock = (int) $stmtStock->fetchColumn();

    $currentQty = $_SESSION['cart'][$productId] ?? 0;
    $newQty = min($currentQty + $qtyToAdd, $stock);
    $_SESSION['cart'][$productId] = $newQty;

    header('Location: cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantities']) && is_array($_POST['quantities'])) {
    foreach ($_POST['quantities'] as $prodId => $qty) {
        $pid     = (int) $prodId;
        $desired = max(0, (int) $qty);

        $stmtStock = $pdo->prepare("SELECT quantidade FROM produtos WHERE idPRODUTO = ?");
        $stmtStock->execute([$pid]);
        $stock = (int) $stmtStock->fetchColumn();

        if ($desired > 0) {
            $_SESSION['cart'][$pid] = min($desired, $stock);
        } else {
            unset($_SESSION['cart'][$pid]);
        }
    }
    header('Location: cart.php');
    exit;
}

$items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in  = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT idPRODUTO, nomePRODUTO, precoPRODUTO, imagemPRODUTO, quantidade AS estoque FROM produtos WHERE idPRODUTO IN ($in)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $prod) {
        $pid      = $prod['idPRODUTO'];
        $qty      = $_SESSION['cart'][$pid];
        $subtotal = $prod['precoPRODUTO'] * $qty;
        $total   += $subtotal;
        $items[] = [
            'id'        => $pid,
            'nome'      => $prod['nomePRODUTO'],
            'preco'     => $prod['precoPRODUTO'],
            'imagem'    => $prod['imagemPRODUTO'],
            'quantidade'=> $qty,
            'subtotal'  => $subtotal,
            'estoque'   => $prod['estoque'],
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
        .quantity-input { width: 60px; }
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
        <center><p>O carrinho está vazio.</p> <br>
        <a href="index.php"> <button class="btn btn-primary">Voltar as compras</button></a></center>
    <?php else: ?>
        <form method="post" action="cart.php">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Imagem</th>
                        <th>Produto</th>
                        <th>Preço</th>
                        <th>Quantidade</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($item['imagem']) ?>" alt=""></td>
                        <td><?= htmlspecialchars($item['nome']) ?></td>
                        <td>R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                        <td>
                            <input type="number" name="quantities[<?= $item['id'] ?>]"
                                   value="<?= $item['quantidade'] ?>"
                                   min="0" max="<?= $item['estoque'] ?>"
                                   class="quantity-input">
                        </td>
                        <td>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                        <td><form method="post" action="cart.php" style="display:inline;">
                                <input type="hidden" name="quantities[<?= $item['id'] ?>]" value="0">
                                <button type="submit" class="btn btn-danger">Remover</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn btn-primary">Atualizar Carrinho</button>
        </form>

        <div class="total">
            Total: R$ <?= number_format($total, 2, ',', '.') ?>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="window.location.href='checkout.php'">Finalizar Compra</button>
        </div>
    <?php endif; ?>
</body>
</html>