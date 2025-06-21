<?php
require 'config/config.inc.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    $delId = (int) $_POST['delete_id'];

    $stmtImg = $pdo->prepare("SELECT imagemPRODUTO FROM produtos WHERE idPRODUTO = :id");
    $stmtImg->execute([':id' => $delId]);
    $imgRow = $stmtImg->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    try {
        // 1. Excluir imagens adicionais do banco e do servidor
        $stmtImgs = $pdo->prepare("SELECT caminho FROM produto_imagens WHERE id_produto = :id");
        $stmtImgs->execute([':id' => $delId]);
        $imgs = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);

        foreach ($imgs as $caminho) {
            $arquivo = __DIR__ . '/' . $caminho;
            if (file_exists($arquivo)) {
                unlink($arquivo); // exclui arquivo fisicamente
            }
        }

        $stmtDelImgs = $pdo->prepare("DELETE FROM produto_imagens WHERE id_produto = :id");
        $stmtDelImgs->execute([':id' => $delId]);

        // 2. Excluir variantes
        $stmtVar = $pdo->prepare("DELETE FROM variantes WHERE id_produto = :id");
        $stmtVar->execute([':id' => $delId]);

        // 3. Excluir categorias vinculadas
        $stmtCat = $pdo->prepare("DELETE FROM produto_categorias WHERE id_produto = :id");
        $stmtCat->execute([':id' => $delId]);

        // 4. Excluir imagem principal do produto (se existir)
        if ($imgRow && !empty($imgRow['imagemPRODUTO'])) {
            $caminhoImagem = __DIR__ . '/' . $imgRow['imagemPRODUTO'];
            if (file_exists($caminhoImagem)) {
                unlink($caminhoImagem);
            }
        }

        // 5. Excluir o produto
        $stmtDel = $pdo->prepare("DELETE FROM produtos WHERE idPRODUTO = :id");
        $stmtDel->execute([':id' => $delId]);

        $pdo->commit();
        $msg = "Produto excluído com sucesso.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Erro ao excluir produto: " . $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT idPRODUTO, nomePRODUTO, precoPRODUTO, quantidade FROM produtos ORDER BY data_cadastro DESC");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Excluir Produtos - ARTBOX</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f4f4f4; }
        .btn-delete {
            padding: 5px 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-delete:hover { background: #c82333; }
        .mensagem { margin-bottom: 15px; padding: 10px; background: #e0ffe0; color: #2e7d32; border-left: 5px solid #43a047; }
    </style>
</head>
<body>
    <h1>Excluir Produtos</h1>

    <?php if ($msg): ?>
        <div class="mensagem"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($produtos)): ?>
        <p>Nenhum produto cadastrado.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Preço (R$)</th>
                    <th>Qtd.</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['idPRODUTO']) ?></td>
                        <td><?= htmlspecialchars($p['nomePRODUTO']) ?></td>
                        <td><?= number_format($p['precoPRODUTO'], 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars($p['quantidade']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Confirma exclusão de <?= addslashes($p['nomePRODUTO']) ?>?');">
                                <input type="hidden" name="delete_id" value="<?= $p['idPRODUTO'] ?>">
                                <button type="submit" class="btn-delete">Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
