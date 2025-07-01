<?php
session_start();
include 'menu.php';
require __DIR__ . '/config/config.inc.php';

// Verifica se usuário está logado
if (!isset($_SESSION['idUSUARIO'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$compraFinalizada = false;
$mensagemErro = '';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dados do cliente
    $nome     = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $email    = trim($_POST['email']);
    $cpf      = trim($_POST['cpf']);
    $cep      = trim($_POST['cep']);
    $endereco = trim($_POST['endereco']);

    // Verifica se há itens no carrinho
    if (empty($_SESSION['cart'])) {
        $mensagemErro = 'Carrinho vazio. Adicione produtos antes de finalizar.';
    } else {
        try {
            // Inicia transação
            $pdo->beginTransaction();

            // Calcula valor total e insere pedido
            $total = 0;
            $produtosDoArtesao = [];
            foreach ($_SESSION['cart'] as $varId => $qty) {
                $stmt = $pdo->prepare("SELECT p.precoPRODUTO, p.id_artesao FROM produtos p JOIN variantes v ON v.id_produto = p.idPRODUTO WHERE v.idVARIANTE = ?");
                $stmt->execute([$varId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $price = (float) $row['precoPRODUTO'];
                $total += $price * $qty;

                // Guardar artesão de cada produto
                $produtosDoArtesao[$varId] = $row['id_artesao'];
            }

            // Insere em pedidos
            $stmtInsertPedido = $pdo->prepare(
                'INSERT INTO pedidos (id_usuario, valor_total, status) VALUES (?, ?, "pago")'
            );
            $stmtInsertPedido->execute([$_SESSION['idUSUARIO'], $total]);
            $pedidoId = $pdo->lastInsertId();

            // Processa cada item
            $stmtInsertItem = $pdo->prepare(
                'INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)'
            );
            $stmtUpdateVar   = $pdo->prepare(
                'UPDATE variantes SET estoque = estoque - ? WHERE idVARIANTE = ?'
            );
            $stmtCheckStock  = $pdo->prepare(
                'SELECT estoque, id_produto FROM variantes WHERE idVARIANTE = ?'
            );
            $stmtUpdateProd  = $pdo->prepare(
                'UPDATE produtos SET quantidade = GREATEST(quantidade - ?, 0) WHERE idPRODUTO = ?'
            );

            foreach ($_SESSION['cart'] as $varId => $qty) {
                // Busca dados do variant
                $stmtCheckStock->execute([$varId]);
                $row = $stmtCheckStock->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['estoque'] < $qty) {
                    throw new Exception('Estoque insuficiente para o item ' . $varId);
                }
                $productId = $row['id_produto'];
                $unitPrice = null;
                $stmtPrice = $pdo->prepare(
                    'SELECT precoPRODUTO FROM produtos p JOIN variantes v ON v.id_produto = p.idPRODUTO WHERE v.idVARIANTE = ?'
                );
                $stmtPrice->execute([$varId]);
                $unitPrice = (float) $stmtPrice->fetchColumn();

                $stmtInsertItem->execute([$pedidoId, $productId, $qty, $unitPrice]);
                $stmtUpdateVar->execute([$qty, $varId]);
                $stmtUpdateProd->execute([$qty, $productId]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            $compraFinalizada = true;

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagemErro = 'Erro ao processar pedido: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Finalizar Compra</title>
    <link rel="stylesheet" href="assets/css/checkout.css">
</head>
<body>

<?php if ($compraFinalizada): ?>
    <div class="success-message">
        <h2>Compra finalizada com sucesso!</h2>
        <p>Obrigado por comprar conosco.</p>
        <a class="back-link" href="index.php">← Voltar para a página principal</a>
    </div>
<?php else: ?>
    <h1>Finalizar Compra</h1>
    <?php if ($mensagemErro): ?>
        <div class="error-message"><?= htmlspecialchars($mensagemErro) ?></div>
    <?php endif; ?>
    <form method="post" action="checkout.php">
        <label for="nome">Nome Completo:</label>
        <input type="text" name="nome" id="nome" value="" required>

        <label for="telefone">Telefone:</label>
        <input type="text" name="telefone" id="telefone" value="" required>

        <label for="email">Email:</label>
        <input type="email" name="email" id="email" value="" required>

        <label for="cpf">CPF:</label>
        <input type="text" name="cpf" id="cpf" value="" required>

        <label for="cep">CEP:</label>
        <input type="text" name="cep" id="cep" value="" required>

        <label for="endereco">Endereço:</label>
        <input type="text" name="endereco" id="endereco" value="" required>

        <button type="submit" class="btn">Confirmar Compra</button>
    </form>
<?php endif; ?>

</body>
</html>