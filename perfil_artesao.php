<?php
session_start();

// Verifica se o usuário está logado
/*if (!isset($_SESSION['id_artesao'])) {
    header("Location: login.php");
    exit();
}

$idArtesao = $_SESSION['id_artesao'];
*/
// Conexão com o banco de dados
$conn = new mysqli("localhost", "usuario", "senha", "ArtBoxBanco");
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

// Consulta os produtos cadastrados pelo artesão
$sql = "SELECT nome, descricao, imagem FROM produtos WHERE id_artesao = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idArtesao);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Perfil do Artesão</title>
</head>
<body>

  <!-- Perfil -->
  <header>
    <img src="caminho/para/imagem.jpg" alt="Foto do Artesão" width="150" height="150">
    <h1>Bem-vindo, <?php echo htmlspecialchars($_SESSION['nome']); ?></h1>
  </header>

  <!-- Link para cadastro de produto -->
  <section>
    <a href="cadastro_produto.php">Cadastrar Novo Produto</a>
  </section>

  <!-- Navegação -->
  <section>
    <h2>Gerenciar Pedidos</h2>
    <button onclick="location.href='pedidos.php'">Pedidos</button>
    <button onclick="location.href='a_caminho.php'">A Caminho</button>
    <button onclick="location.href='realizados.php'">Realizados</button>
  </section>

  <!-- Produtos do artesão -->
  <section>
    <h2>Meus Produtos</h2>

    <?php if ($result->num_rows > 0): ?>
      <?php while ($produto = $result->fetch_assoc()): ?>
        <div>
          <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" alt="Imagem do Produto" width="100" height="100">
          <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
          <p><?php echo htmlspecialchars($produto['descricao']); ?></p>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>Você ainda não cadastrou nenhum produto.</p>
    <?php endif; ?>

  </section>

</body>
</html>
