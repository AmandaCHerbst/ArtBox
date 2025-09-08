<?php
session_start();
require __DIR__ . '/config/config.inc.php';

if (empty($_SESSION['idUSUARIO'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = (int) $_SESSION['idUSUARIO'];
$idProduto = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);
$idPedido  = filter_input(INPUT_GET, 'pedido_id', FILTER_VALIDATE_INT);

if (!$idProduto || !$idPedido) {
    die('Dados inválidos.');
}

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nota = (int)($_POST['nota'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if ($nota < 1 || $nota > 5) {
            $erro = "Selecione uma nota válida.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO comentarios (produto_id, usuario_id, pedido_id, nota, comentario) VALUES (:prod, :usr, :ped, :nota, :coment)");
            $stmt->execute([
                ':prod' => $idProduto,
                ':usr'  => $idUsuario,
                ':ped'  => $idPedido,
                ':nota' => $nota,
                ':coment' => $comentario
            ]);
            header("Location: avaliar.php");
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT nomePRODUTO, imagemPRODUTO FROM produtos WHERE idPRODUTO = :id");
    $stmt->execute([':id' => $idProduto]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Avaliar Produto - ARTBOX</title>
<style>
    body { font-family: Arial, sans-serif; background:#fafafa; color:#333; max-width:640px; margin:40px auto; padding:20px; }
    h1 { color:#5C3A21; margin-bottom:20px; }
    .produto { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
    .produto img { width:80px; height:80px; border-radius:8px; border:1px solid #ddd; object-fit:cover; }
    form { background:#fff; padding:20px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
    label { display:block; margin-bottom:8px; font-weight:bold; }
    textarea { width:100%; min-height:100px; padding:10px; border-radius:8px; border:1px solid #ddd; resize:vertical; }
    button { background:#5C3A21; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:bold; margin-top:12px; }
    button:hover { background:#A95C38; }
    .erro { color:red; margin-bottom:12px; }
    /* estrelas */
    .stars {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-start;
        gap: 5px;
        font-size: 2rem;
        cursor: pointer;
        margin-bottom:15px;
    }
    .stars input { display:none; }
    .stars label { color:#ccc; transition: color 0.2s; }
    .stars input:checked ~ label { color:#b08a3b; }
    .stars label:hover,
    .stars label:hover ~ label { color:#d4af37; }
</style>
</head>
<body>
    <h1>Avaliar Produto</h1>

    <div class="produto">
        <img src="<?= htmlspecialchars($produto['imagemPRODUTO'] ?: 'assets/placeholder.png') ?>" alt="">
        <div><strong><?= htmlspecialchars($produto['nomePRODUTO']) ?></strong></div>
    </div>

    <?php if (!empty($erro)) echo "<p class='erro'>".htmlspecialchars($erro)."</p>"; ?>

    <form method="post">
        <label>Nota:</label>
        <div class="stars">
            <?php for ($i=5;$i>=1;$i--): ?>
                <input type="radio" id="star<?= $i ?>" name="nota" value="<?= $i ?>">
                <label for="star<?= $i ?>">★</label>
            <?php endfor; ?>
        </div>

        <label for="comentario">Comentário (opcional):</label>
        <textarea name="comentario" id="comentario"></textarea>

        <button type="submit">Enviar Avaliação</button>
    </form>
</body>
</html>
