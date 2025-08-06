<?php
session_start();
include 'menu.php'; // Se precisar do menu, mantenha esta linha
require __DIR__ . '/config/config.inc.php';

if (empty($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    header('Location: login.php');
    exit;
}

$idArtesao = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Processar atualização do status do pedido para "enviado"
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['enviar_pedido_id'])) {
        $pedidoId = (int) $_POST['enviar_pedido_id'];

        // Atualiza o status geral do pedido para 'enviado'
        $stmtUpdate = $pdo->prepare("UPDATE pedidos SET status = 'enviado' WHERE idPEDIDO = :id");
        $stmtUpdate->execute([':id' => $pedidoId]);

        // Opcional: atualizar o status do artesão para 'aprovado' (ou outro status que represente "pedido enviado")
        $stmtUpdateArtesao = $pdo->prepare("UPDATE pedidos_artesao SET status = 'aprovado' WHERE id_pedido = :id AND id_artesao = :art");
        $stmtUpdateArtesao->execute([':id' => $pedidoId, ':art' => $idArtesao]);

        // Redireciona para evitar reenvio do formulário
        header('Location: relatorio_vendas.php');
        exit;
    }

    // Buscar pedidos aprovados para este artesão
    $sqlPedidos = <<<SQL
SELECT DISTINCT p.idPEDIDO, p.data_pedido, p.status, u.nomeUSUARIO AS cliente
FROM pedidos p
JOIN pedidos_artesao pa ON p.idPEDIDO = pa.id_pedido
JOIN usuarios u ON u.idUSUARIO = p.id_usuario
WHERE pa.id_artesao = :idArt
  AND pa.status = 'aprovado'
ORDER BY p.data_pedido DESC
SQL;
    $stmt = $pdo->prepare($sqlPedidos);
    $stmt->execute([':idArt' => $idArtesao]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar pedidos por status (pendente, enviado, entregue)
    $grupos = ['pendente' => [], 'enviado' => [], 'entregue' => []];

    // Preparar consulta para os itens do pedido
    $sqlItens = <<<SQL
SELECT
  ip.id_pedido,
  ip.quantidade,
  ip.preco_unitario,
  ip.id_variante,
  p.nomePRODUTO,
  p.imagemPRODUTO,
  v.valor_tipologia   AS tipologia,
  v.valor_especificacao AS especificacao
FROM itens_pedido ip
JOIN produtos p ON ip.id_produto = p.idPRODUTO
LEFT JOIN variantes v ON ip.id_variante = v.idVARIANTE
WHERE ip.id_pedido = :pid
SQL;
    $stmtItens = $pdo->prepare($sqlItens);

    foreach ($pedidos as $pedido) {
        $stmtItens->execute([':pid' => $pedido['idPEDIDO']]);
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itens as $item) {
            $grupos[strtolower($pedido['status'])][] = [
                'idPEDIDO'      => $pedido['idPEDIDO'],
                'data_pedido'   => $pedido['data_pedido'],
                'cliente'       => $pedido['cliente'],
                'nomePRODUTO'   => $item['nomePRODUTO'],
                'imagem'        => $item['imagemPRODUTO'] ?: 'assets/img/sem-imagem.png',
                'quantidade'    => $item['quantidade'],
                'preco_unitario'=> $item['preco_unitario'],
                'subtotal'      => $item['quantidade'] * $item['preco_unitario'],
                'tipologia'     => $item['tipologia'] ?? '-',
                'especificacao' => $item['especificacao'] ?? '-',
            ];
        }
    }

} catch (PDOException $e) {
    die('Erro ao buscar relatório: ' . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Relatório de Vendas</title>
    <link rel="stylesheet" href="assets/css/relatorio_vendas.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css" />
    <style>
        /* (Aqui vai todo o seu CSS conforme enviado, para o estilo das seções e botões) */
        body {
            font-family: 'Quicksand', sans-serif;
            background-color: #fafafa;
            margin: 40px auto;
            max-width: 1200px;
            padding: 0 20px;
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 40px;
            color: #5C3A21;
            font-size: 2em;
        }
        h2 {
            color: #A95C38;
            margin-top: 40px;
            font-size: 1.4em;
            border-bottom: 2px solid #A95C38;
            padding-bottom: 6px;
        }
        .status-section {
            margin-bottom: 60px;
        }
        .empty {
            text-align: center;
            color: #888;
            font-style: italic;
            margin-top: 20px;
        }
        .carousel {
            margin: 20px 0;
        }
        .carousel .card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 16px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            margin: 10px;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: all 0.3s ease;
        }
        .card.hovered {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        .product-thumb {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background-color: #f0f0f0;
            border-bottom: 1px solid #eee;
        }
        .card-content {
            padding: 16px 18px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-content h3 {
            font-size: 1.1em;
            margin: 0 0 10px;
            color: #5C3A21;
        }
        .card-content p {
            margin: 4px 0;
            font-size: 0.95em;
        }
        .card-content strong {
            color: #555;
        }
        .variant-badge {
            display: inline-block;
            background-color: #EDE4DB;
            color: #5C3A21;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
            margin-left: 4px;
        }
        .slick-prev, .slick-next,
        .custom-prev, .custom-next {
            background-color: #A95C38;
            border: none;
            color: white;
            font-size: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            z-index: 2;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .slick-prev:hover, .slick-next:hover,
        .custom-prev:hover, .custom-next:hover {
            background-color: #8C3F28;
        }
        .custom-prev {
            position: absolute;
            top: 50%;
            left: -18px;
            transform: translateY(-50%);
        }
        .custom-next {
            position: absolute;
            top: 50%;
            right: -18px;
            transform: translateY(-50%);
        }
        a.back-link {
            display: block;
            text-align: center;
            margin-top: 40px;
            text-decoration: none;
            color: #A95C38;
            font-weight: bold;
            transition: color 0.2s ease;
        }
        a.back-link:hover {
            text-decoration: underline;
            color: #5C3A21;
        }
        .btn-enviar {
            background-color: #A95C38;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 8px;
            transition: background-color 0.3s ease;
            width: 100%;
            font-size: 1em;
        }
        .btn-enviar:hover {
            background-color: #8C3F28;
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>Relatório de Vendas</h1>

        <?php foreach (['pendente' => 'Preparando', 'enviado' => 'A caminho', 'entregue' => 'Entregues'] as $key => $titulo): ?>
            <section class="status-section">
                <h2><?= htmlspecialchars($titulo) ?></h2>

                <?php if (empty($grupos[$key])): ?>
                    <p class="empty">Nenhuma venda nesta seção.</p>
                <?php else: ?>
                    <div class="carousel" id="carousel-<?= $key ?>">
                        <?php foreach ($grupos[$key] as $v): ?>
                            <article class="card">
                                <img src="<?= htmlspecialchars($v['imagem']) ?>" alt="Imagem do produto" class="product-thumb" />
                                <div class="card-content">
                                    <h3>Pedido #<?= $v['idPEDIDO'] ?></h3>
                                    <p><strong>Data:</strong> <?= date('d/m/Y \à\s H:i', strtotime($v['data_pedido'])) ?></p>
                                    <p><strong>Cliente:</strong> <?= htmlspecialchars($v['cliente']) ?></p>
                                    <p><strong>Produto:</strong> <?= htmlspecialchars($v['nomePRODUTO']) ?></p>
                                    <!--<p><strong>Variante:</strong> 
                                        <span class="variant-badge"><?= htmlspecialchars($v['tipologia'] . ' / ' . $v['especificacao']) ?></span>
                                    </p>-->
                                    <p><strong>Qtd.:</strong> <?= (int)$v['quantidade'] ?></p>
                                    <p><strong>Unitário:</strong> R$ <?= number_format($v['preco_unitario'], 2, ',', '.') ?></p>
                                    <p><strong>Subtotal:</strong> R$ <?= number_format($v['subtotal'], 2, ',', '.') ?></p>

                                    <?php if ($key === 'pendente'): ?>
                                        <form method="post" style="margin-top:10px;">
                                            <input type="hidden" name="enviar_pedido_id" value="<?= $v['idPEDIDO'] ?>" />
                                            <button type="submit" class="btn-enviar">Marcar como Enviado</button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <a class="back-link" href="perfil_artesao.php">&larr; Voltar ao Painel</a>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script>
        $(function() {
            ['pendente', 'enviado', 'entregue'].forEach(function(key) {
                $('#carousel-' + key).slick({
                    infinite: false,
                    slidesToShow: 3,
                    slidesToScroll: 1,
                    arrows: true,
                    dots: false,
                    prevArrow: '<button class="slick-prev custom-prev">&lt;</button>',
                    nextArrow: '<button class="slick-next custom-next">&gt;</button>',
                    responsive: [
                        { breakpoint: 1024, settings: { slidesToShow: 2 } },
                        { breakpoint: 768, settings: { slidesToShow: 1 } }
                    ]
                });
            });
        });
    </script>
</body>
</html>
