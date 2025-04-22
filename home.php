<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$arquivo_json = 'dados.json';
$produtos = array();

if (file_exists($arquivo_json)) {
    $json_data = file_get_contents($arquivo_json);
    $dados = json_decode($json_data, true);

    if ($dados === null && json_last_error() !== JSON_ERROR_NONE) {
        $dados = array();
    }

    $produtos = isset($dados['produtos']) ? $dados['produtos'] : array();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artbox</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">

</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <img src="assets/img/favicon.png" alt="Logo">
                <h1><a href="cadastro.php">CADASTRO ARTBOX</a></h1>
            </div>
            <div class="search-bar">
                <input type="text" placeholder="Pesquisar...">
                <img src="assets/img/lupa.svg" alt="Search">
            </div>
            <div class="profile-icon">
                <img src="assets/img/user.svg" alt="Profile">
            </div>
        </header>

        <div class="content">
            <aside>
                <div class="category-icon">
                    <img src="assets/img/01.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/02.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/03.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/04.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/05.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/06.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/07.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/08.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/09.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/10.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/11.png" alt="Categoria 1">
                </div>
                <div class="category-icon">
                    <img src="assets/img/12.png" alt="Categoria 1">
                </div>
            </aside>

            <main>
                <h2>RECOMENDADOS</h2>
                <div class="products">
                    <?php foreach ($produtos as $produto): ?>
                        <div class="product">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars($produto['image']); ?>" alt="Imagem do Produto">
                            </div>
                            <div class="product-details">
                                <p><?php echo htmlspecialchars($produto['nome']); ?></p>
                                <span>R$ <?php echo number_format($produto['price'], 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
