<?php
session_start();
include 'menu.php';
require __DIR__ . '/config/config.inc.php';
require_once __DIR__ . '/classes/Pedido.class.php';
require_once __DIR__ . '/classes/ItensPedido.class.php';

function sanitize_phone_for_whatsapp($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (substr($digits, 0, 2) !== '55') {
        if (strlen($digits) <= 11) $digits = '55' . $digits;
    }
    return $digits;
}

function format_br_currency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

if (!isset($_SESSION['idUSUARIO'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

$stmtUser = $pdo->prepare(
    "SELECT nomeUSUARIO AS nome, telefone, email, cpfUSUARIO AS cpf, cep, endereco
     FROM usuarios WHERE idUSUARIO = ?"
);
$stmtUser->execute([$_SESSION['idUSUARIO']]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];

$mensagemErro = '';
$compraFinalizada = false;
$waLinks = [];
$pedidoId = null;
$formattedTotal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dadosCliente = [
        'nome'     => trim($_POST['nome']),
        'telefone' => trim($_POST['telefone']),
        'email'    => trim($_POST['email']),
        'cpf'      => trim($_POST['cpf']),
        'cep'      => trim($_POST['cep']),
        'endereco' => trim($_POST['endereco']),
    ];

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

    if (empty($_SESSION['cart'])) {
        $mensagemErro = 'Carrinho vazio. Adicione produtos antes de finalizar.';
    } else {
        try {
            $cart = $_SESSION['cart'];

            $variantIds = array_map('intval', array_keys($cart));
            $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
            $sql = 
                "SELECT v.idVARIANTE, p.idPRODUTO, p.nomePRODUTO, p.precoPRODUTO
                 FROM variantes v
                 JOIN produtos p ON v.id_produto = p.idPRODUTO
                 WHERE v.idVARIANTE IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($variantIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = 0.0;
            $productIds = [];
            foreach ($rows as $row) {
                $vid = $row['idVARIANTE'];
                $qty = $cart[$vid]['quantidade'] ?? 0;
                $price = isset($cart[$vid]['preco']) ? $cart[$vid]['preco'] : $row['precoPRODUTO'];
                $total += ($price * $qty);
                $productIds[] = $row['idPRODUTO'];
            }
            $productIds = array_values(array_unique($productIds));
            $formattedTotal = format_br_currency($total);

            $pedidoService = new Pedido($pdo);
            $pedidoId = $pedidoService->criarPedido(
                $_SESSION['idUSUARIO'],
                $dadosCliente,
                $cart
            );

            // --- Novo bloco: monta links por artesão (subtotal) ---
            try {
                $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

                $detect_column = function($table, array $candidates) use ($pdo, $dbName) {
                    if (empty($candidates)) return null;
                    $placeholders = implode(',', array_fill(0, count($candidates), '?'));
                    $sql = "SELECT COLUMN_NAME
                            FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ($placeholders)";
                    $stmt = $pdo->prepare($sql);
                    $params = array_merge([$dbName, $table], $candidates);
                    $stmt->execute($params);
                    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($candidates as $c) {
                        if (in_array($c, $found, true)) return $c;
                    }
                    return null;
                };

                $possible_product_user_cols = [
                    'idUSUARIO','id_usuario','user_id','usuario_id',
                    'vendedor_id','idVendedor','id_vendedor','seller_id',
                    'idARTESAO','id_artesao','artesao_id'
                ];
                $productUserCol = $detect_column('produtos', $possible_product_user_cols);

                $possible_user_phone_cols = ['telefone','telefoneUSUARIO','celular','phone','telefone_artesao','telefone_usuario'];
                $userPhoneCol = $detect_column('usuarios', $possible_user_phone_cols);

                $possible_prod_phone_cols = ['telefone','telefonePRODUTO','telefone_artesao','telefone_vendedor','phone'];
                $prodPhoneCol = $detect_column('produtos', $possible_prod_phone_cols);

                // Reconstruir mapa das variantes do carrinho
                $variantMap = []; // idVARIANTE => ['idPRODUTO'=>..., 'preco'=>..., 'qty'=>..., 'nome'=>...]
                foreach ($rows as $r) {
                    $vid = $r['idVARIANTE'];
                    $prodId = $r['idPRODUTO'];
                    $qty = $cart[$vid]['quantidade'] ?? 0;
                    $price = isset($cart[$vid]['preco']) ? $cart[$vid]['preco'] : $r['precoPRODUTO'];
                    $variantMap[$vid] = [
                        'idPRODUTO' => $prodId,
                        'preco' => (float)$price,
                        'qty' => (int)$qty,
                        'nome' => $r['nomePRODUTO'] ?? '',
                    ];
                }

                $productSellerMap = []; // idPRODUTO => sellerId
                $productPhoneByProduct = []; // idPRODUTO => telefone (fallback)

                if ($productUserCol && !empty($productIds)) {
                    $placeholdersP = implode(',', array_fill(0, count($productIds), '?'));
                    $sql = "SELECT idPRODUTO, {$productUserCol} AS seller" . ($prodPhoneCol ? ", {$prodPhoneCol} AS prod_phone" : "") . "
                            FROM produtos WHERE idPRODUTO IN ($placeholdersP)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($productIds);
                    $prodRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($prodRows as $pr) {
                        $productSellerMap[(int)$pr['idPRODUTO']] = ($pr['seller'] !== null && $pr['seller'] !== '') ? $pr['seller'] : null;
                        if ($prodPhoneCol && !empty($pr['prod_phone'])) {
                            $productPhoneByProduct[(int)$pr['idPRODUTO']] = $pr['prod_phone'];
                        }
                    }
                } else {
                    // fallback: buscar telefone do produto caso exista
                    if (!empty($productIds) && $prodPhoneCol) {
                        $placeholdersP = implode(',', array_fill(0, count($productIds), '?'));
                        $stmt = $pdo->prepare("SELECT idPRODUTO, {$prodPhoneCol} AS prod_phone FROM produtos WHERE idPRODUTO IN ($placeholdersP)");
                        $stmt->execute($productIds);
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                            if (!empty($pr['prod_phone'])) $productPhoneByProduct[(int)$pr['idPRODUTO']] = $pr['prod_phone'];
                        }
                    }
                }

                // Calcular subtotais por sellerKey
                $sellerSubtotals = []; // sellerKey => subtotal
                $sellerProducts = [];
                foreach ($variantMap as $vid => $info) {
                    $pid = (int)$info['idPRODUTO'];
                    $lineTotal = $info['preco'] * $info['qty'];

                    if (isset($productSellerMap[$pid]) && $productSellerMap[$pid] !== null && $productSellerMap[$pid] !== '') {
                        $sellerKey = 'user:' . $productSellerMap[$pid];
                    } elseif (!empty($productPhoneByProduct[$pid])) {
                        $sellerKey = 'phone:' . preg_replace('/\D+/', '', $productPhoneByProduct[$pid]);
                    } else {
                        $sellerKey = 'prod:' . $pid;
                    }

                    if (!isset($sellerSubtotals[$sellerKey])) {
                        $sellerSubtotals[$sellerKey] = 0.0;
                        $sellerProducts[$sellerKey] = [];
                    }
                    $sellerSubtotals[$sellerKey] += $lineTotal;
                    $sellerProducts[$sellerKey][] = $info['nome'] . " x" . $info['qty'];
                }

                // Buscar telefones dos users (artesãos)
                $userPhones = []; // id => telefone
                $sellerUserIds = [];
                foreach ($sellerSubtotals as $sk => $_) {
                    if (strpos($sk, 'user:') === 0) {
                        $sellerUserIds[] = (int)substr($sk, 5);
                    }
                }
                $sellerUserIds = array_values(array_unique($sellerUserIds));
                if (!empty($sellerUserIds) && $userPhoneCol) {
                    $placeholdersU = implode(',', array_fill(0, count($sellerUserIds), '?'));
                    $stmtUsers = $pdo->prepare("SELECT idUSUARIO AS id, {$userPhoneCol} AS telefone FROM usuarios WHERE idUSUARIO IN ($placeholdersU)");
                    $stmtUsers->execute($sellerUserIds);
                    foreach ($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $ur) {
                        if (!empty($ur['telefone'])) $userPhones[(int)$ur['id']] = $ur['telefone'];
                    }
                }

                // Montar links WhatsApp por artesão
                $waLinks = [];
                foreach ($sellerSubtotals as $sellerKey => $subtotal) {
                    $phoneRaw = null;

                    if (strpos($sellerKey, 'user:') === 0) {
                        $uid = (int)substr($sellerKey, 5);
                        if (!empty($userPhones[$uid])) {
                            $phoneRaw = $userPhones[$uid];
                        } else {
                            // tentar telefone nos produtos desse user
                            if ($productUserCol) {
                                $stmtPhoneFallback = $pdo->prepare("SELECT DISTINCT " . ($prodPhoneCol ? "{$prodPhoneCol} AS telefone" : "NULL AS telefone") . "\n                                                         FROM produtos WHERE {$productUserCol} = ? LIMIT 1");
                                $stmtPhoneFallback->execute([$uid]);
                                $pf = $stmtPhoneFallback->fetch(PDO::FETCH_ASSOC);
                                if ($pf && !empty($pf['telefone'])) $phoneRaw = $pf['telefone'];
                            }
                        }
                    } elseif (strpos($sellerKey, 'phone:') === 0) {
                        $phoneRaw = substr($sellerKey, 6);
                    } elseif (strpos($sellerKey, 'prod:') === 0) {
                        $pid = (int)substr($sellerKey, 5);
                        if (!empty($productPhoneByProduct[$pid])) $phoneRaw = $productPhoneByProduct[$pid];
                    }

                    if ($phoneRaw) {
                        $digits = sanitize_phone_for_whatsapp($phoneRaw);
                        if ($digits === '') continue;

                        $msg = "Olá! Recebi o pedido {$pedidoId}. A sua parte é " . format_br_currency($subtotal) .
                               ". Poderia, por favor, enviar a chave PIX para o pagamento? Itens: " . implode(', ', array_unique($sellerProducts[$sellerKey] ?? []));

                        $link = 'https://wa.me/' . $digits . '?text=' . urlencode($msg);
                        $waLinks[] = [
                            'seller_key' => $sellerKey,
                            'phone_raw' => $phoneRaw,
                            'phone_clean' => $digits,
                            'subtotal' => $subtotal,
                            'link' => $link,
                        ];
                    }
                }

                // fallback: se nenhum link para artesãos, usa telefone do comprador
                if (empty($waLinks) && !empty($userData['telefone'])) {
                    $digits = sanitize_phone_for_whatsapp($userData['telefone']);
                    if ($digits !== '') {
                        $msg = "Olá! Comprei na sua loja, o pedido {$pedidoId}, dando o total de {$formattedTotal}, poderia me enviar a chave pix para o pagamento?";
                        $waLinks[] = [
                            'phone_raw' => $userData['telefone'],
                            'phone_clean' => $digits,
                            'link' => 'https://wa.me/' . $digits . '?text=' . urlencode($msg),
                        ];
                    }
                }

            } catch (Exception $ex) {
                // se algo falhar aqui, deixa $waLinks como está (vazio) e continua
                //error_log('Erro ao montar links WA: ' . $ex->getMessage());
            }

            // limpar carrinho e marcar compra finalizada
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
      border-radius: 12px;
      padding: 8px 14px;
    }

    /* Modal simples */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }
    .modal {
      background: #fff;
      padding: 22px;
      border-radius: 10px;
      width: 95%;
      max-width: 520px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .modal h3 { margin-top: 0; color: #5C3A21; }
    .wa-link {
      display:flex;
      gap:8px;
      align-items:center;
      justify-content:space-between;
      padding:10px;
      border-radius:8px;
      background:#f7f2ee;
      margin-bottom:8px;
      font-size:0.95rem;
    }
    .wa-actions button {
      margin-left:8px;
      padding:6px 10px;
      border-radius:8px;
      border:none;
      cursor:pointer;
      font-weight:bold;
    }
    .open-btn { background:#25D366; color:#fff; border-radius:8px; }
    .copy-btn { background:#A95C38; color:#fff; border-radius:8px; }
    .close-btn { background:#ccc; color:#333; border-radius:8px; padding:8px 12px; }
    
    .btn-whatsapp {
    display: inline-block;
    background-color: #25D366;
    color: white;
    font-weight: bold;
    padding: 10px 18px;
    margin: 6px 4px;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.2s ease;
    font-family: Arial, sans-serif;
}
.btn-whatsapp:hover {
    background-color: #1ebe5a;
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

        <?php if (!empty($waLinks)): ?>
          <p style="margin-top:12px;">Entre em contato com o(s) artesão(ões) para combinar o pagamento via PIX:</p>

          <div class="wa-buttons" style="margin-top:14px;">
            <?php foreach ($waLinks as $wa): ?>
              <a href="<?= htmlspecialchars($wa['link']) ?>" target="_blank" class="btn-whatsapp">
                💬 Fale com o artesão
              </a>
            <?php endforeach; ?>
          </div>

          <div style="margin-top:20px;">
            <a class="back-link btn btn-primary" href="index.php">← Voltar para a página principal</a>
          </div>
        <?php else: ?>
          <p style="margin-top:12px;">Não foi possível encontrar o telefone do artesão automaticamente. Entre em contato com o suporte ou verifique o perfil do produto para obter os dados de pagamento.</p>
          <div style="margin-top:14px;">
            <a class="back-link btn btn-primary" href="index.php">← Voltar para a página principal</a>
          </div>
        <?php endif; ?>
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

  <script>
    (function(){
      const modalBackdrop = document.getElementById('waModalBackdrop');
      const openModalBtn = document.getElementById('openModalBtn');
      const closeModalBtn = document.getElementById('closeModalBtn');

      function openModal(){ modalBackdrop.style.display = 'flex'; modalBackdrop.setAttribute('aria-hidden','false'); }
      function closeModal(){ modalBackdrop.style.display = 'none'; modalBackdrop.setAttribute('aria-hidden','true'); }

      if(openModalBtn) openModalBtn.addEventListener('click', openModal);
      if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
      modalBackdrop.addEventListener('click', function(e){ if(e.target === modalBackdrop) closeModal(); });

      <?php if ($compraFinalizada && !empty($waLinks)): ?>
        setTimeout(openModal, 300);
      <?php endif; ?>

      document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function(){
          const link = this.getAttribute('data-link');
          navigator.clipboard?.writeText(link).then(() => {
            this.textContent = 'Copiado!';
            setTimeout(()=> this.textContent = 'Copiar', 2000);
          }).catch(() => {
            alert('Não foi possível copiar automaticamente. Segure e copie o link: ' + link);
          });
        });
      });
    })();
  </script>
</body>
</html>