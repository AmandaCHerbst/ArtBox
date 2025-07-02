<?php
session_start();
require __DIR__ . '/config/config.inc.php';

if (!isset($_SESSION['idUSUARIO']) || $_SESSION['tipo_usuario'] !== 'artesao') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: perfil_artesao.php");
    exit;
}

$idPedido = (int) $_GET['id'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare("UPDATE pedidos SET status = 'preparando' WHERE idPEDIDO = :id");
    $stmt->execute([':id' => $idPedido]);

    header("Location: perfil_artesao.php");
} catch (PDOException $e) {
    die("Erro ao confirmar pedido: " . $e->getMessage());
}
