<?php
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
    "SELECT idPRODUTO, nomePRODUTO AS nome,
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Perfil do Artesão - ARTBOX</title>
  <?php //<link rel="stylesheet" href="assets/css/perfil_artesao.css" /> ?>
  <link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
      background-color: #fafafa;
      color: #333;
    }
    header {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 20px;
    }
    header img {
      border-radius: 50%;
      object-fit: cover;
      width: 150px;
      height: 150px;
      border: 2px solid #007bff;
    }
    header h1 {
      margin: 0;
      font-size: 2rem;
      font-weight: 600;
      color: #222;
    }
    header nav {
      margin-top: 10px;
    }
    header nav a {
      display: inline-flex;
      align-items: center;
      margin-right: 15px;
      text-decoration: none;
      padding: 8px 16px;
      background-color: #007bff;
      color: white;
      border-radius: 5px;
      font-weight: 600;
      transition: background-color 0.3s ease;
      user-select: none;
    }
    header nav a:hover {
      background-color: #0056b3;
    }
    header nav a .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 48;
      font-size: 28px;
      color: #e3e3e3;
      margin-right: 8px;
      vertical-align: middle;
      user-select: none;
      transition: color 0.3s ease;
    }
    header nav a:hover .material-symbols-outlined {
      color: #fff;
    }

    section h2 {
      margin-top: 0;
      font-size: 1.5rem;
      color: #222;
    }
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
      background-color: #fff;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      cursor: pointer;
      user-select: none;
    }
    .produto-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.12);
    }
    .produto-card img {
      width: 100%;
      object-fit: cover;
      aspect-ratio: 1/1;
      border-bottom: 1px solid #ddd;
      margin-bottom: 10px;
      border-radius: 6px 6px 0 0;
    }
    .produto-card h3 {
      font-size: 1.1rem;
      margin: 0 0 10px;
      text-align: center;
      color: #333;
    }
    .produto-card p {
      flex: 1;
      text-align: center;
      margin: 0;
      color: #555;
    }
  </style>
</head>
<body>
  <header>
    <img src="assets/img/perfil.png" alt="Foto do Artesão" />
    <div>
      <h1>Bem-vindo, <?= htmlspecialchars($_SESSION['nomeUSUARIO']) ?></h1>
      <nav>
        <a href="cadastro_produto.php">
          <span class="material-symbols-outlined">add_circle</span>
          Cadastrar Novo Produto
        </a>
        <a href="relatorio_vendas.php" class="btn-relatorio">
          <span class="material-symbols-outlined">assignment</span>
          Relatório de Vendas
        </a>
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
              <a href="relatorio_especifico.php?id=<?= $p['idPRODUTO'] ?>">
                <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" />
                <h3><?= htmlspecialchars($p['nome']) ?></h3>
              </a>
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
