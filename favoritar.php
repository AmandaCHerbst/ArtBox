<?php
session_start();
if (!isset($_SESSION['idUSUARIO'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config/config.inc.php';

try {
    $pdo = new PDO(DSN, USUARIO, SENHA);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$idProduto = (int) $_GET['id'];
$idUsuario = (int) $_SESSION['idUSUARIO'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM favoritos WHERE idUSUARIO = :u AND idPRODUTO = :p");
$stmt->execute([':u' => $idUsuario, ':p' => $idProduto]);
$jaFavorito = $stmt->fetchColumn() > 0;

if ($jaFavorito) {
    $del = $pdo->prepare("DELETE FROM favoritos WHERE idUSUARIO = :u AND idPRODUTO = :p");
    $del->execute([':u' => $idUsuario, ':p' => $idProduto]);
} else {
    $ins = $pdo->prepare("INSERT INTO favoritos (idUSUARIO, idPRODUTO) VALUES (:u, :p)");
    $ins->execute([':u' => $idUsuario, ':p' => $idProduto]);
}

$status = $jaFavorito ? 'removido' : 'adicionado';
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$sep = parse_url($referer, PHP_URL_QUERY) ? '&' : '?';
header('Location: ' . $referer . $sep . 'fav=' . $status);
exit;
