<?php
session_start();
require __DIR__ . '/config/config.inc.php';
include 'menu.php';
require_once 'classes/Pedido.class.php';

if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    header('Location: login.php');
    exit;
}

$idArtesao = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

$stmtUser = $pdo->prepare("SELECT nomeUSUARIO, foto_perfil FROM usuarios WHERE idUSUARIO = :id");
$stmtUser->execute([':id' => $idArtesao]);
$usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

$stmtAtivos = $pdo->prepare(
    "SELECT p.idPRODUTO,
            p.nomePRODUTO AS nome,
            p.precoPRODUTO AS preco,
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

$stmtArquivados = $pdo->prepare(
    "SELECT p.idPRODUTO,
            p.nomePRODUTO AS nome,
            p.precoPRODUTO AS preco,
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

$pedidoService = new Pedido($pdo);
$pedidosPendentes = $pedidoService->listarPedidosPendentesPorArtesao($idArtesao);

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
  <link rel="stylesheet" href="assets/css/perfil_artesao.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
  <style>
#pendentes table { width: 100%; border-collapse: collapse; margin: 20px auto; max-width: 800px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05);}
#pendentes th, #pendentes td { padding: 12px 15px; text-align: center; border-bottom: 1px solid #eee; font-size: 0.95rem; color: #555;}
#pendentes th { background-color: #EDE4DB; color: #5C3A21; font-weight: 600;}
#pendentes tr:last-child td { border-bottom: none;}
#pendentes .status,
#pendentes .status strong { color: #4E8C50; font-weight: bold;}
#pendentes button[type="submit"] { padding: 6px 12px; border-radius: 12px; border: none; color: #fff; cursor: pointer; transition: background-color 0.3s; font-size: 0.9rem;}
#pendentes button[value="aprovado"] { background-color: #4B7F52;}
#pendentes button[value="aprovado"]:hover { background-color: #3b6641;}
#pendentes button[value="rejeitado"] { background-color: #B33A3A;}
#pendentes button[value="rejeitado"]:hover { background-color: #922c2c;}
  </style>
</head>
<body>
  <section class="perfil-usuario">
    <img src="assets/img/perfis/<?= htmlspecialchars($usuario['foto_perfil']) ?>" alt="Perfil">
    <h1>Olá, <?= htmlspecialchars($usuario['nomeUSUARIO']) ?></h1>
  </section>

  <header class="appbar">
    <nav class="tabs">
      <button class="tab-button active" data-tab="ativos">Meus Produtos</button>
      <button class="tab-button" data-tab="arquivados">Arquivados</button>
      <button class="tab-button" data-tab="pendentes">Pendentes</button>
    </nav>
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
              <a href="editar_produto.php?id=<?= $produto['idPRODUTO'] ?>">
                <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" />
                <h3><?= htmlspecialchars($produto['nome']) ?></h3>
                <p><strong>Preço:</strong> R$ <?= number_format($produto['preco'], 2, ',', '.') ?></p>
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
              <a href="editar_produto.php?id=<?= $produto['idPRODUTO'] ?>">
                <span class="badge">Esgotado</span>
                <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" />
                <h3><?= htmlspecialchars($produto['nome']) ?></h3>
                <p><strong>Preço:</strong> R$ <?= number_format($produto['preco'], 2, ',', '.') ?></p>
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