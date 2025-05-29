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

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['product_id'], $_POST['quantity']) &&
    empty($_POST['quantities']) &&
    !isset($_POST['remove'])
) {
    $productId = (int) $_POST['product_id'];
    $qtyToAdd  = max(1, (int) $_POST['quantity']);

    $stmtStock = $pdo->prepare("SELECT quantidade FROM produtos WHERE idPRODUTO = ?");
    $stmtStock->execute([$productId]);
    $stock = (int) $stmtStock->fetchColumn();

    $current = $_SESSION['cart'][$productId] ?? 0;
    $_SESSION['cart'][$productId] = min($current + $qtyToAdd, $stock);

    header('Location: cart.php');
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update']) &&
    isset($_POST['quantities']) &&
    is_array($_POST['quantities'])
) {
    foreach ($_POST['quantities'] as $pid => $q) {
        $pid     = (int)$pid;
        $desired = max(0, (int)$q);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove'])) {
    $removeId = (int) $_POST['remove'];
    unset($_SESSION['cart'][$removeId]);
    header('Location: cart.php');
    exit;
}

if (!isset($_SESSION['idUSUARIO'])) {
    header('Location: login.php?redirect=cart.php');
    exit;
}

$items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT idPRODUTO AS id,
               nomePRODUTO AS nome,
               precoPRODUTO AS preco,
               quantidade  AS estoque,
               imagemPRODUTO AS imagem
        FROM produtos
        WHERE idPRODUTO IN ($ph)
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $qtd  = $_SESSION['cart'][$p['id']];
        $sub  = $p['preco'] * $qtd;
        $total += $sub;
        $items[] = [
            'id'         => $p['id'],
            'nome'       => $p['nome'],
            'preco'      => $p['preco'],
            'estoque'    => $p['estoque'],
            'imagem'     => $p['imagem'],
            'quantidade'=> $qtd,
            'subtotal'   => $sub,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Carrinho</title>
  <style>
    table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    th, td { border:1px solid #ddd; padding:8px; text-align:center; }
    .quantity-input { width:60px; }
    .btn { padding:8px 12px; border:none; border-radius:4px; cursor:pointer; }
    .btn-danger { background:#dc3545; color:#fff; }
    .btn-primary { background:#007bff; color:#fff; }
    .actions { text-align:right; margin-top:10px; }
    .total { text-align:right; font-size:1.2rem; margin-top:10px; }
  </style>
</head>
<body>
  <h1>Seu Carrinho</h1>

  <?php if (empty($items)): ?>
    <p>O carrinho está vazio.</p>
    <a href="index.php" class="btn btn-primary">Voltar às compras</a>
  <?php else: ?>

    <form method="post" action="cart.php">
      <input type="hidden" name="update" value="1">
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
            <td><img src="<?= htmlspecialchars($item['imagem']) ?>" width="50" height="50"></td>
            <td><?= htmlspecialchars($item['nome']) ?></td>
            <td>R$ <?= number_format($item['preco'],2,',','.') ?></td>
            <td>
              <input type="number"
                     name="quantities[<?= $item['id'] ?>]"
                     value="<?= $item['quantidade'] ?>"
                     min="0"
                     max="<?= $item['estoque'] ?>"
                     class="quantity-input">
            </td>
            <td>R$ <?= number_format($item['subtotal'],2,',','.') ?></td>
            <td>

              <form method="post" action="cart.php" style="display:inline">
                <input type="hidden" name="remove" value="<?= $item['id'] ?>">
                <button type="submit" class="btn btn-danger">Remover</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="actions">
        <button type="submit" class="btn btn-primary">Atualizar Carrinho</button>
      </div>
    </form>

    <div class="total">
      Total: R$ <?= number_format($total,2,',','.') ?>
    </div>
    <div class="actions">
      <button onclick="location.href='checkout.php'" class="btn btn-primary">
        Finalizar Compra
      </button>
    </div>

  <?php endif; ?>
</body>
</html>