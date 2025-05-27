<?php
session_start();
include 'menu.php';

$compraFinalizada = false;

// Simulando o processamento do pedido após envio
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Coleta de dados
    $nome = $_POST["nome"];
    $telefone = $_POST["telefone"];
    $email = $_POST["email"];
    $cpf = $_POST["cpf"];
    $cep = $_POST["cep"];
    $endereco = $_POST["endereco"];

    // Aqui você pode salvar no banco ou enviar para processamento do pedido...

    // Limpa o carrinho após a finalização
    $_SESSION['cart'] = [];

    $compraFinalizada = true;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Finalizar Compra</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        form { max-width: 500px; margin: 0 auto; }
        label { display: block; margin-top: 10px; }
        input { width: 100%; padding: 8px; margin-top: 5px; }
        .btn { margin-top: 20px; padding: 10px 15px; background-color: #28a745; color: white; border: none; cursor: pointer; }
        .btn:hover { background-color: #218838; }
        .success-message { text-align: center; padding: 30px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .back-link { display: block; text-align: center; margin-top: 20px; }
    </style>
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
    <form method="post" action="checkout.php">
        <label for="nome">Nome Completo:</label>
        <input type="text" name="nome" id="nome" required>

        <label for="telefone">Telefone:</label>
        <input type="text" name="telefone" id="telefone" required>

        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>

        <label for="cpf">CPF:</label>
        <input type="text" name="cpf" id="cpf" required>

        <label for="cep">CEP:</label>
        <input type="text" name="cep" id="cep" required>

        <label for="endereco">Endereço:</label>
        <input type="text" name="endereco" id="endereco" required>

        <button type="submit" class="btn">Confirmar Compra</button>
    </form>
<?php endif; ?>

</body>
</html>
