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
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finalizar Compra - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/checkout.css">
  <style>
    body {
  font-family: 'Quicksand', sans-serif;
  background-color: #fafafa;
  color: #333;
  margin: 0;
  padding: 20px;
}

.checkout-container {
  max-width: 600px;
  margin: 0 auto;
  background: #fff;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.page-header h1 {
  font-size: 2rem;
  text-align: center;
  color: #5C3A21;
  margin-bottom: 20px;
}

.error-message {
  background: #ffe5e0;
  color: #b33a3a;
  padding: 10px 15px;
  border-radius: 6px;
  margin-bottom: 15px;
  text-align: center;
}

.success-message {
  text-align: center;
  padding: 30px 20px;
  background: #d4edda;
  border: 1px solid #c3e6cb;
  border-radius: 8px;
}

.back-link {
  display: inline-block;
  margin-top: 20px;
}

.checkout-form .form-group {
  margin-bottom: 15px;
}

.checkout-form label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
  color: #5C3A21;
}

.checkout-form input {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
}

.btn {
  padding: 10px 18px;
  border: none;
  border-radius: 20px;
  font-weight: bold;
  cursor: pointer;
  transition: background-color 0.3s;
}

.btn-success {
  background-color: #4B7F52;
  color: #fff;
  width: 100%;
  margin-top: 20px;
}

.btn-success:hover {
  background-color: #3b6641;
}

.btn-primary, .btn-danger {
  /* caso use em messages */
  border-radius: 12px;
  padding: 8px 14px;
}
  </style>
</head>
<body>
    <br>
  <div class="checkout-container">
    <?php if ($compraFinalizada): ?>
      <div class="success-message">
        <h2>Compra finalizada com sucesso!</h2>
        <p>Obrigado por comprar conosco.</p>
        <a class="back-link btn btn-primary" href="index.php">← Voltar para a página principal</a>
      </div>
    <?php else: ?>
      <header class="page-header">
        <h1>Finalizar Compra</h1>
      </header>
      <?php if ($mensagemErro): ?>
        <div class="error-message"><?= htmlspecialchars($mensagemErro) ?></div>
      <?php endif; ?>
      <form method="post" action="checkout.php" class="checkout-form">
        <div class="form-group">
          <label for="nome">Nome Completo:</label>
          <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($userData['nome'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="telefone">Telefone:</label>
          <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($userData['telefone'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="email">Email:</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="cpf">CPF:</label>
          <input type="text" id="cpf" name="cpf" value="<?= htmlspecialchars($userData['cpf'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="cep">CEP:</label>
          <input type="text" id="cep" name="cep" value="<?= htmlspecialchars($userData['cep'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="endereco">Endereço:</label>
          <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($userData['endereco'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn btn-success">Confirmar Compra</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>