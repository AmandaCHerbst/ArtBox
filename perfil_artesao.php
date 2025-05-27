<?php
// perfil_artesao.php
session_start();
require __DIR__ . '/config/config.inc.php';
include 'menu.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    header('Location: login.php');
    exit;
}

$idArtesao = $_SESSION['idUSUARIO'];

$stmt = $pdo->prepare(
    "SELECT nomePRODUTO AS nome,
            descricaoPRODUTO AS descricao,
            imagemPRODUTO AS imagem
     FROM produtos
     WHERE id_artesao = :id"
);
$stmt->execute([':id' => $idArtesao]);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil do Artesão - ARTBOX</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    header { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; }
    header img { border-radius: 50%; object-fit: cover; width: 150px; height: 150px; }
    header h1 { margin: 0; font-size: 2rem; }
    header nav a {
      margin-right: 15px;
      text-decoration: none;
      padding: 8px 16px;
      background-color: #007bff;
      color: white;
      border-radius: 5px;
      transition: background-color 0.3s ease;
    }
    header nav a:hover { background-color: #0056b3; }

    section h2 { margin-top: 0; font-size: 1.5rem; }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 20px;
    }
    .produto-card {
      border: 1px solid #ddd;
      border-radius: 8px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 10px;
      background-color: #f9f9f9;
      transition: transform 0.2s ease;
    }
    .produto-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .produto-card img {
      width: 100%;
      object-fit: cover;
      aspect-ratio: 1/1;
      border-bottom: 1px solid #ddd;
      margin-bottom: 10px;
    }
    .produto-card h3 {
      font-size: 1.1rem;
      margin: 0 0 10px;
    }
    .produto-card p {
      flex: 1;
      text-align: center;
      margin: 0;
    }
  </style>
</head>
<body>
  <header>
    <img src="caminho/para/imagem.jpg" alt="Foto do Artesão">
    <div>
      <h1>Bem-vindo, <?= htmlspecialchars($_SESSION['nomeUSUARIO']) ?></h1> <br>
      <nav>
        <a href="cadastro_produto.php">Cadastrar Novo Produto</a>
        <a href="pedidos.php">Gerenciar Pedidos</a>
        <a href="logout.php">Sair</a>
      </nav>
    </div>
  </header>

  <main>
    <section>
      <h2>Meus Produtos</h2>
      <div class="grid">
        <?php if (count($produtos) > 0): ?>
          <?php foreach ($produtos as $p): ?>
            <div class="produto-card">
              <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>">
              <h3><?= htmlspecialchars($p['nome']) ?></h3>
              <p><?= htmlspecialchars($p['descricao']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Você ainda não cadastrou nenhum produto.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>