<?php
session_start();
require __DIR__ . '/config/config.inc.php';
include 'menu.php';
require_once 'classes/Pedido.class.php';

// Autenticação de artesão
if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    header('Location: login.php');
    exit;
}

$idArtesao = $_SESSION['idUSUARIO'];

// Conexão PDO
try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

// Busca dados do artesão
$stmtUser = $pdo->prepare("SELECT nomeUSUARIO, foto_perfil FROM usuarios WHERE idUSUARIO = :id");
$stmtUser->execute([':id' => $idArtesao]);
$usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

// Produtos com estoque total > 0 (ativos)
$stmtAtivos = $pdo->prepare(
    "SELECT p.idPRODUTO,
            p.nomePRODUTO AS nome,
            p.descricaoPRODUTO AS descricao,
            p.imagemPRODUTO AS imagem,
            SUM(v.estoque) AS estoque_total
     FROM produtos p
     JOIN variantes v ON v.id_produto = p.idPRODUTO
     WHERE p.id_artesao = :id
     GROUP BY p.idPRODUTO
     HAVING estoque_total > 0"
);
$stmtAtivos->execute([':id' => $idArtesao]);
$produtosAtivos = $stmtAtivos->fetchAll(PDO::FETCH_ASSOC);

// Produtos com estoque total = 0 (arquivados)
$stmtArquivados = $pdo->prepare(
    "SELECT p.idPRODUTO,
            p.nomePRODUTO AS nome,
            p.descricaoPRODUTO AS descricao,
            p.imagemPRODUTO AS imagem
     FROM produtos p
     JOIN (
        SELECT id_produto, SUM(estoque) AS total
        FROM variantes
        GROUP BY id_produto
     ) vsum ON vsum.id_produto = p.idPRODUTO
     WHERE p.id_artesao = :id
       AND vsum.total = 0"
);
$stmtArquivados->execute([':id' => $idArtesao]);
$produtosArquivados = $stmtArquivados->fetchAll(PDO::FETCH_ASSOC);

// Instancia serviço de pedidos
$pedidoService = new Pedido($pdo);
$pedidosPendentes = $pedidoService->listarPedidosPendentesPorArtesao($idArtesao);

// Processar aprovação/rejeição via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'], $_POST['acao'])) {
    $pedidoId = (int)$_POST['pedido_id'];
    $acao = $_POST['acao'];
    if (in_array($acao, ['aprovado', 'rejeitado'])) {
        $pedidoService->atualizarStatusArtesao($pedidoId, $idArtesao, $acao);
        header("Location: perfil_artesao.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Perfil do Artesão - ARTBOX</title>
  <link rel="stylesheet" href="assets/css/estilos.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
  <style>
body { font-family: Arial, sans-serif; padding: 20px; background-color: #fafafa; color: #333; }
main { padding: 20px; }
.tab-panel.hidden { display: none; }
.appbar { display: flex; align-items: center; justify-content: space-between; background-color: #ffffff; padding: 10px 20px; color: #333; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
.profile-pic { width: 60px; height: 60px; border-radius: 50%; border: 2px solid #ccc; object-fit: cover; }
.appbar-content { flex: 1; margin-left: 15px; }
.appbar-content h1 { margin: 0; font-size: 1.3rem; color: #222; }
.tabs { margin-top: 8px; }
.tab-button { background: transparent; border: none; color: #555; font-size: 1rem; padding: 8px 12px; cursor: pointer; border-bottom: 2px solid transparent; transition: color 0.3s ease, border-bottom 0.3s ease; margin-right: 10px; }
.tab-button.active { color: #007bff; border-bottom: 2px solid #007bff; }
.tab-button:hover { color: #007bff; }
.appbar-actions .btn { padding: 6px 12px; text-decoration: none; font-size: 0.9rem; border-radius: 4px; font-weight: 600; margin-left: 8px; transition: background-color 0.3s ease; }
.appbar-actions .btn.novo { background-color: #007bff; color: #fff; }
.appbar-actions .btn.novo:hover { background-color: #0056b3; }
.appbar-actions .btn.vendas { background-color: #28a745; color: #fff; }
.appbar-actions .btn.vendas:hover { background-color: #1e7e34; }
.appbar-actions .btn.sair { background-color: #dc3545; color: #fff; }
.appbar-actions .btn.sair:hover { background-color: #b02a37; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
.produto-card { background-color: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s ease, box-shadow 0.2s ease; }
.produto-card:hover { transform: translateY(-4px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
.produto-card img { width: 100%; height: 150px; object-fit: cover; }
.produto-card h3 { font-size: 1rem; margin: 10px; color: #2c3e50; }
.empty { text-align: center; font-size: 1rem; color: #7f8c8d; margin-top: 20px; }
.produto-card.arquivado { opacity: 0.6; position: relative; }
.produto-card.arquivado .badge { position: absolute; top: 8px; right: 8px; background-color: #e74c3c; color: #ecf0f1; padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
th { background-color: #f5f5f5; }
form { margin: 0; }
form button { cursor: pointer; background-color: #007bff; border: none; color: white; padding: 5px 10px; border-radius: 3px; margin-right: 5px; transition: background-color 0.3s ease; }
form button:hover { background-color: #0056b3; }
  </style>
</head>
<body>
  <header class="appbar">
    <img class="profile-pic" src="assets/img/perfis/<?= htmlspecialchars($usuario['foto_perfil']) ?>" alt="Perfil">
    <div class="appbar-content">
      <h1>Olá, <?= htmlspecialchars($usuario['nomeUSUARIO']) ?></h1>
      <nav class="tabs">
        <button class="tab-button active" data-tab="ativos">Meus Produtos</button>
        <button class="tab-button" data-tab="arquivados">Arquivados</button>
        <button class="tab-button" data-tab="pendentes">Pendentes</button>
      </nav>
    </div>
    <div class="appbar-actions">
      <a href="cadastro_produto.php" class="btn novo">+ Novo Produto</a>
      <a href="relatorio_vendas.php" class="btn vendas">Vendas</a>
      <a href="logout.php" class="btn sair">Sair</a>
    </div>
  </header>

  <main>
    <section id="ativos" class="tab-panel">
      <?php if ($produtosAtivos): ?>
        <div class="grid">
          <?php foreach ($produtosAtivos as $produto): ?>
            <div class="produto-card">
              <a href="relatorio_especifico.php?id=<?= $produto['idPRODUTO'] ?>">
                <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" />
                <h3><?= htmlspecialchars($produto['nome']) ?></h3>
                <p><?= htmlspecialchars($produto['descricao']) ?></p>
                <p><strong>Estoque:</strong> <?= $produto['estoque_total'] ?></p>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty">Nenhum produto ativo no momento.</p>
      <?php endif; ?>
    </section>

    <section id="arquivados" class="tab-panel hidden">
      <?php if ($produtosArquivados): ?>
        <div class="grid">
          <?php foreach ($produtosArquivados as $produto): ?>
            <div class="produto-card arquivado">
              <a href="relatorio_especifico.php?id=<?= $produto['idPRODUTO'] ?>">
                <span class="badge">Esgotado</span>
                <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" />
                <h3><?= htmlspecialchars($produto['nome']) ?></h3>
                <p><?= htmlspecialchars($produto['descricao']) ?></p>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty">Nenhum produto arquivado.</p>
      <?php endif; ?>
    </section>

    <section id="pendentes" class="tab-panel hidden">
      <?php if ($pedidosPendentes): ?>
        <table>
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Cliente</th>
              <th>Valor</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pedidosPendentes as $pedido): ?>
              <tr>
                <td>#<?= $pedido['idPEDIDO'] ?></td>
                <td><?= htmlspecialchars($pedido['nomeUSUARIO']) ?></td>
                <td>R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars(ucfirst($pedido['status_artesao'] ?? 'Pendente')) ?></td>
                <td>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="pedido_id" value="<?= $pedido['idPEDIDO'] ?>">
                    <button type="submit" name="acao" value="aprovado">Confirmar</button>
                  </form>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="pedido_id" value="<?= $pedido['idPEDIDO'] ?>">
                    <button type="submit" name="acao" value="rejeitado">Cancelar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty">Nenhum pedido pendente.</p>
      <?php endif; ?>
    </section>
  </main>

  <script>
    const buttons = document.querySelectorAll('.tab-button');
    const panels = document.querySelectorAll('.tab-panel');
    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        buttons.forEach(b => b.classList.remove('active'));
        panels.forEach(p => p.classList.add('hidden'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.remove('hidden');
      });
    });
  </script>
</body>
</html>
