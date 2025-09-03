<?php
session_start();
include 'menu.php';
require __DIR__ . '/config/config.inc.php';

if (!isset($_SESSION['idUSUARIO'])) {
    header('Location: login.php?redirect=pagamento.php');
    exit;
}

$idUsuario = $_SESSION['idUSUARIO'];

function format_br_currency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
function sanitize_phone_for_whatsapp($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (substr($digits, 0, 2) !== '55') {
        if (strlen($digits) <= 11) $digits = '55' . $digits;
    }
    return $digits;
}

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare(
        "SELECT p.*
         FROM pedidos p
         WHERE p.id_usuario = :uid
           AND EXISTS (
               SELECT 1 FROM pedidos_artesao pa
               WHERE pa.id_pedido = p.idPEDIDO
                 AND pa.status IN ('pendente','rejeitado')
           )
         ORDER BY p.data_pedido DESC"
    );
    $stmt->execute([':uid' => $idUsuario]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtArt = $pdo->prepare(
        "SELECT pa.idPEDIDOS_ARTESAO, pa.id_artesao, pa.status AS status_artesao, u.nomeUSUARIO, u.telefone
         FROM pedidos_artesao pa
         LEFT JOIN usuarios u ON u.idUSUARIO = pa.id_artesao
         WHERE pa.id_pedido = :pid
           AND pa.status IN ('pendente','rejeitado')"
    );

    $stmtItens = $pdo->prepare(
        "SELECT ip.idintens_pedido, ip.id_produto, ip.quantidade, ip.preco_unitario, p.nomePRODUTO
         FROM itens_pedido ip
         LEFT JOIN produtos p ON p.idPRODUTO = ip.id_produto
         WHERE ip.id_pedido = :pid"
    );

} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Pagamentos Pendentes - ARTBOX</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/pagamento.css">
  <style>
    body { font-family: 'Quicksand', sans-serif; background:#fafafa; color:#333; margin:0; padding:20px; }
    .container { max-width:1000px; margin:0 auto; }
    .page-header h1 { text-align:center; color:#5C3A21; margin-bottom:18px; }
    .card { background:#fff; border-radius:10px; padding:18px; box-shadow:0 4px 14px rgba(0,0,0,0.06); margin-bottom:16px; }
    .pedido-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:12px; flex-wrap:wrap; }
    .pedido-info { font-size:0.95rem; color:#555; }
    .pedido-id { font-weight:700; color:#5C3A21; }
    .pedido-valor { font-weight:700; }
    .itens-list { margin-top:10px; padding-top:8px; border-top:1px dashed #eee; }
    .item { margin:6px 0; color:#444; font-size:0.95rem; }
    .artesao-list { margin-top:12px; display:flex; flex-direction:column; gap:8px; }
    .artesao { display:flex; justify-content:space-between; align-items:center; gap:10px; background:#f7f2ee; padding:10px; border-radius:8px; }
    .artesao .meta { font-size:0.95rem; color:#333; }
    .status-pendente { color:#b77900; font-weight:700; }
    .status-rejeitado { color:#b33a3a; font-weight:700; }
    .btn-whatsapp { display:inline-block; background:#25D366; color:#fff; padding:8px 12px; border-radius:8px; text-decoration:none; font-weight:700; }
    .btn-whatsapp:hover { background:#1ebe5a; }
    .empty { text-align:center; color:#777; padding:40px 0; }
    .small { font-size:0.9rem; color:#666; }
  </style>
</head>
<body>
  <div class="container">
    <header class="page-header">
      <h1>Pedidos com pagamento pendente / rejeitado</h1>
      <p class="small" style="text-align:center;">Aqui aparecem pedidos que ainda estão pendentes com o artesão ou que foram rejeitados pelo artesão.</p>
    </header>

    <?php if (empty($pedidos)): ?>
      <div class="empty card">
        <p>Você não possui pedidos pendentes ou rejeitados com artesãos no momento.</p>
        <p><a href="index.php" class="btn-whatsapp" style="background:#A95C38">← Voltar à loja</a></p>
      </div>
    <?php else: ?>

      <?php foreach ($pedidos as $pedido): ?>
        <div class="card">
          <div class="pedido-header">
            <div>
              <div class="pedido-id">Pedido #<?= htmlspecialchars($pedido['idPEDIDO']) ?></div>
              <div class="pedido-info">Data: <?= htmlspecialchars($pedido['data_pedido']) ?></div>
            </div>
            <div style="text-align:right;">
              <div class="pedido-valor"><?= format_br_currency($pedido['valor_total']) ?></div>
              <div class="small">Status do pedido: <strong><?= htmlspecialchars(ucfirst($pedido['status'] ?? 'pendente')) ?></strong></div>
            </div>
          </div>

          <div class="itens-list">
            <?php
              $stmtItens->execute([':pid' => $pedido['idPEDIDO']]);
              $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
              if ($itens):
                foreach ($itens as $it): ?>
                  <div class="item">• <?= htmlspecialchars($it['nomePRODUTO'] ?? 'Produto') ?> — Qtd: <?= (int)$it['quantidade'] ?> — <?= format_br_currency($it['preco_unitario']) ?></div>
                <?php endforeach;
              else: ?>
                <div class="item">Nenhum item encontrado para este pedido.</div>
              <?php endif; ?>
          </div>

          <div class="artesao-list">
            <?php
              $stmtArt->execute([':pid' => $pedido['idPEDIDO']]);
              $arts = $stmtArt->fetchAll(PDO::FETCH_ASSOC);
              if ($arts):
                foreach ($arts as $a):
                  $telefone = $a['telefone'] ?? '';
                  $phoneClean = sanitize_phone_for_whatsapp($telefone);
                  $waLink = '';
                  if ($phoneClean !== '') {
                      $msg = "Olá! Comprei na sua loja, o pedido {$pedido['idPEDIDO']}, dando o total de " . format_br_currency($pedido['valor_total']) . ". Poderia me enviar a chave PIX para o pagamento?";
                      $waLink = 'https://wa.me/' . $phoneClean . '?text=' . urlencode($msg);
                  }
            ?>
              <div class="artesao">
                <div class="meta">
                  <div><strong><?= htmlspecialchars($a['nomeUSUARIO'] ?? 'Artesão') ?></strong></div>
                  <div class="small">Status: 
                    <?php if ($a['status_artesao'] === 'pendente'): ?>
                      <span class="status-pendente">Pendente</span>
                    <?php else: ?>
                      <span class="status-rejeitado"><?= htmlspecialchars(ucfirst($a['status_artesao'])) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($telefone)): ?>
                    <div class="small">Telefone: <?= htmlspecialchars($telefone) ?></div>
                  <?php endif; ?>
                </div>

                <div>
                  <?php if ($waLink): ?>
                    <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" class="btn-whatsapp" rel="noopener">💬 Fale com o artesão</a>
                  <?php else: ?>
                    <span class="small">Telefone não cadastrado</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach;
              else: ?>
                <div class="artesao">
                  <div class="meta">Nenhum artesão pendente/rejeitado encontrado para este pedido.</div>
                </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </div>
</body>
</html>
