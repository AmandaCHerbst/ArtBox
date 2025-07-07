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

if (!isset($_SESSION['idUSUARIO'])) {
    header('Location: login.php?redirect=cart.php');
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['variant_id'], $_POST['quantity'])) {
        $varId = (int)$_POST['variant_id'];
        $qtyToAdd = max(1, (int)$_POST['quantity']);
        $stmtStock = $pdo->prepare("SELECT estoque FROM variantes WHERE idVARIANTE = ?");
        $stmtStock->execute([$varId]);
        $stock = (int)$stmtStock->fetchColumn();

        $stmtVar = $pdo->prepare(
            "SELECT v.idVARIANTE, v.valor_tipologia AS tamanho, v.valor_especificacao AS cor, v.estoque,
                    p.idPRODUTO, p.precoPRODUTO
             FROM variantes v
             JOIN produtos p ON v.id_produto = p.idPRODUTO
             WHERE v.idVARIANTE = ?"
        );
        $stmtVar->execute([$varId]);
        $varData = $stmtVar->fetch(PDO::FETCH_ASSOC);

        if ($varData) {
            $produtoId = $varData['idPRODUTO'];
            $preco = $varData['precoPRODUTO'];

            if (isset($_SESSION['cart'][$varId])) {
                $_SESSION['cart'][$varId]['quantidade'] = min(
                    $_SESSION['cart'][$varId]['quantidade'] + $qtyToAdd,
                    $stock
                );
            } else {
                $_SESSION['cart'][$varId] = [
                    'id_produto' => $produtoId,
                    'idVARIANTE' => $varId,
                    'quantidade' => min($qtyToAdd, $stock),
                    'preco'      => $preco,
                ];
            }
        }

        header('Location: cart.php');
        exit;
    }

    if (isset($_POST['update']) || isset($_POST['checkout'])) {
        if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
            foreach ($_POST['quantities'] as $variantId => $qty) {
                $variantId = (int)$variantId;
                $desired = max(0, (int)$qty);

                $stmtStock = $pdo->prepare("SELECT estoque FROM variantes WHERE idVARIANTE = ?");
                $stmtStock->execute([$variantId]);
                $stock = (int)$stmtStock->fetchColumn();

                if ($desired > 0 && isset($_SESSION['cart'][$variantId])) {
                    $_SESSION['cart'][$variantId]['quantidade'] = min($desired, $stock);
                } else {
                    unset($_SESSION['cart'][$variantId]);
                }
            }
        }
    }

    if (isset($_POST['checkout'])) {
        header('Location: checkout.php');
        exit;
    }

    if (isset($_POST['remove'])) {
        $variantId = (int)$_POST['remove'];
        unset($_SESSION['cart'][$variantId]);
    }

    header('Location: cart.php');
    exit;
}

$items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    $variantIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
    $sql = 
        "SELECT v.idVARIANTE, v.valor_tipologia AS tamanho, v.valor_especificacao AS cor, v.estoque,
                p.idPRODUTO, p.nomePRODUTO, p.precoPRODUTO, p.imagemPRODUTO
         FROM variantes v
         JOIN produtos p ON v.id_produto = p.idPRODUTO
         WHERE v.idVARIANTE IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($variantIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $qty = $_SESSION['cart'][$row['idVARIANTE']]['quantidade'] ?? 0;
        $preco = $_SESSION['cart'][$row['idVARIANTE']]['preco'] ?? $row['precoPRODUTO'];
        $sub = $preco * $qty;
        $total += $sub;
        $items[] = [
            'variantId' => $row['idVARIANTE'],
            'productId' => $row['idPRODUTO'],
            'name'      => $row['nomePRODUTO'] . " - " . $row['tamanho'] . " / " . $row['cor'],
            'price'     => $preco,
            'stock'     => $row['estoque'],
            'image'     => $row['imagemPRODUTO'],
            'quantity'  => $qty,
            'subtotal'  => $sub,
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
  <link rel="stylesheet" href="assets/css/cart.css">
  <style>
  /* Estilo harmonioso e clean para o Carrinho */
  body {
    font-family: 'Quicksand', sans-serif;
    background-color: #fafafa;
    color: #333;
    margin: 0;
    padding: 20px;
  }
  .container {
    max-width: 1000px;
    margin: 0 auto;
  }
  .page-header h1 {
    text-align: center;
    font-size: 2rem;
    color: #5C3A21;
    margin-bottom: 30px;
  }
  .empty-container {
    text-align: center;
    padding: 40px 0;
  }
  .empty-msg {
    font-size: 1.2rem;
    color: #777;
    margin-bottom: 20px;
  }
  .btn-primary, .btn-success, .btn-danger {
    padding: 10px 16px;
    border: none;
    border-radius: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
  }
  .btn-primary { background-color: #A95C38; color: #fff; }
  .btn-primary:hover { background-color: #8A4528; }
  .btn-success { background-color: #4B7F52; color: #fff; }
  .btn-success:hover { background-color: #3b6641; }
  .btn-danger { background-color: #B33A3A; color: #fff; }
  .btn-danger:hover { background-color: #922c2c; }
  .table-container { overflow-x: auto; margin-bottom: 20px; }
  table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  }
  th, td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #eee;
  }
  th { background-color: #EDE4DB; color: #5C3A21; font-weight: bold; }
  td img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
  }
  .quantity-input {
    width: 60px;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 6px;
    text-align: center;
  }
  .actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }
  .total-container { text-align: right; margin-top: 20px; }
  .total { font-size: 1.4rem; font-weight: bold; }
</style>
</head>
<body>
  <div class="container">
    <header class="page-header">
      <h1>Seu Carrinho</h1>
    </header>

    <?php if (empty($items)): ?>
      <div class="empty-container">
        <p class="empty-msg">O carrinho está vazio.</p>
        <a href="index.php" class="btn btn-primary">Voltar às compras</a>
      </div>
    <?php else: ?>
      <form method="post" action="cart.php">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Imagem</th>
                <th>Produto</th>
                <th>Preço</th>
                <th>Quantidade</th>
                <th>Subtotal</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item): ?>
              <tr>
                <td><img src="<?= htmlspecialchars($item['image']) ?>" alt="Produto"></td>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td>R$ <?= number_format($item['price'], 2, ',', '.') ?></td>
                <td>
                  <input type="number"
                         name="quantities[<?= $item['variantId'] ?>]"
                         value="<?= $item['quantity'] ?>"
                         min="0"
                         max="<?= $item['stock'] ?>"
                         class="quantity-input">
                </td>
                <td>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                <td>
                  <button type="submit" name="remove" value="<?= $item['variantId'] ?>" class="btn btn-danger">
                    Remover
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="actions">
          <button type="submit" name="update" class="btn btn-primary">Atualizar Carrinho</button>
          <button type="submit" name="checkout" class="btn btn-success">Finalizar Compra</button>
        </div>
      </form>

      <div class="total-container">
        <p class="total">Total: R$ <?= number_format($total, 2, ',', '.') ?></p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>