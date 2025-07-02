<?php
session_start();
include 'menu.php';
require __DIR__ . '/config/config.inc.php';
require_once __DIR__ . '/classes/Pedido.class.php';
require_once __DIR__ . '/classes/ItensPedido.class.php';

// Verifica login
if (!isset($_SESSION['idUSUARIO'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

// Conexão PDO
try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

// Prefill dados do usuário
$stmtUser = $pdo->prepare(
    "SELECT nomeUSUARIO AS nome, telefone, email, cpfUSUARIO AS cpf, cep, endereco
     FROM usuarios WHERE idUSUARIO = ?"
);
$stmtUser->execute([$_SESSION['idUSUARIO']]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];

$mensagemErro = '';
$compraFinalizada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dados do cliente
    $dadosCliente = [
        'nome'     => trim($_POST['nome']),
        'telefone' => trim($_POST['telefone']),
        'email'    => trim($_POST['email']),
        'cpf'      => trim($_POST['cpf']),
        'cep'      => trim($_POST['cep']),
        'endereco' => trim($_POST['endereco']),
    ];

    // Persistir dados no perfil
    $upd = $pdo->prepare(
        "UPDATE usuarios SET
            nomeUSUARIO = :nome,
            telefone    = :telefone,
            email       = :email,
            cpfUSUARIO  = :cpf,
            cep         = :cep,
            endereco    = :endereco
         WHERE idUSUARIO = :uid"
    );
    $upd->execute([
        ':nome'     => $dadosCliente['nome'],
        ':telefone' => $dadosCliente['telefone'],
        ':email'    => $dadosCliente['email'],
        ':cpf'      => $dadosCliente['cpf'],
        ':cep'      => $dadosCliente['cep'],
        ':endereco' => $dadosCliente['endereco'],
        ':uid'      => $_SESSION['idUSUARIO'],
    ]);

    // Verificar carrinho
    if (empty($_SESSION['cart'])) {
        $mensagemErro = 'Carrinho vazio. Adicione produtos antes de finalizar.';
    } else {
        try {
            // Cria pedido
            $pedidoService = new Pedido($pdo);
            $pedidoId = $pedidoService->criarPedido(
                $_SESSION['idUSUARIO'],
                $dadosCliente,
                $_SESSION['cart']
            );

            // Limpa carrinho
            $_SESSION['cart'] = [];
            $compraFinalizada = true;
        } catch (Exception $e) {
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
        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($userData['nome'] ?? '') ?>" required>

        <label for="telefone">Telefone:</label>
        <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($userData['telefone'] ?? '') ?>" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" required>

        <label for="cpf">CPF:</label>
        <input type="text" id="cpf" name="cpf" value="<?= htmlspecialchars($userData['cpf'] ?? '') ?>" required>

        <label for="cep">CEP:</label>
        <input type="text" id="cep" name="cep" value="<?= htmlspecialchars($userData['cep'] ?? '') ?>" required>

        <label for="endereco">Endereço:</label>
        <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($userData['endereco'] ?? '') ?>" required>

        <button type="submit" class="btn">Confirmar Compra</button>
    </form>
<?php endif; ?>
</body>
</html>