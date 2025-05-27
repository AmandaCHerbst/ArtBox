<?php
// excluir.php
require 'config/config.inc.php';

// Conexão PDO
try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

// Se veio um pedido de exclusão:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    $delId = (int) $_POST['delete_id'];
    // Remover produto
    $stmtDel = $pdo->prepare("DELETE FROM produtos WHERE idPRODUTO = :id");
    $stmtDel->execute([':id' => $delId]);
    // Redireciona para evitar repost
    header("Location: excluir.php");
    exit;
}

// Busca todos os produtos
$stmt = $pdo->query("SELECT idPRODUTO, nomePRODUTO, precoPRODUTO, quantidade FROM produtos ORDER BY data_cadastro DESC");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Produtos - ARTBOX</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f4f4f4; }
        form { margin: 0; }
        .btn-delete {
            padding: 4px 8px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-delete:hover { background: #c82333; }
    </style>
</head>
<body>
    <h1>Excluir Produtos</h1>
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
                            <form method="post" onsubmit="return confirm('Confirma exclusão do produto <?= addslashes($p['nomePRODUTO']) ?>?');">
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
