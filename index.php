<?php
// index.php - Lista de produtos
require 'config/config.inc.php';
try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

$stmt = $pdo->query("SELECT idPRODUTO, nomePRODUTO, precoPRODUTO, imagemPRODUTO FROM produtos");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja ARTBOX</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .product-card { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
        .product-card img { width: 100%; object-fit: cover; aspect-ratio: 1/1; }
        .card-body { padding: 10px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .product-title { font-size: 1.1rem; margin: 0 0 10px; }
        .product-price { font-weight: bold; margin: 0 0 10px; }
        .add-cart-btn { padding: 8px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .add-cart-btn:hover { background: #218838; }
    </style>
</head>
<body>
    <h1>Produtos</h1>
    <div class="grid">
        <?php foreach ($produtos as $p): ?>
            <div class="product-card">
                <img src="<?= htmlspecialchars($p['imagemPRODUTO']) ?>" alt="<?= htmlspecialchars($p['nomePRODUTO']) ?>">
                <div class="card-body">
                    <div>
                        <h2 class="product-title"><?= htmlspecialchars($p['nomePRODUTO']) ?></h2>
                        <p class="product-price">R$ <?= number_format($p['precoPRODUTO'], 2, ',', '.') ?></p>
                    </div>
                    <form action="cart.php" method="post">
                        <input type="hidden" name="product_id" value="<?= $p['idPRODUTO'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="add-cart-btn">Adicionar ao carrinho</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
