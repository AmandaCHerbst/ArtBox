<?php
session_start();
require __DIR__ . '/config/config.inc.php';

define('UPLOAD_DIR', __DIR__ . '/assets/img/perfis/');
define('UPLOAD_URL', 'assets/img/perfis/');

if (empty($_SESSION['idUSUARIO'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = $_SESSION['idUSUARIO'];

try {
    $pdo = new PDO(DSN, USUARIO, SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE idUSUARIO = :id");
    $stmt->execute([':id' => $idUsuario]);
    $atual = $stmt->fetch(PDO::FETCH_ASSOC);
    $fotoAtual = $atual['foto_perfil'] ?? 'default.png';

    $nome     = trim($_POST['nome'] ?? '');
    $usuario  = trim($_POST['usuario'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade   = trim($_POST['cidade'] ?? '');
    $estado   = trim($_POST['estado'] ?? '');
    $cep      = trim($_POST['cep'] ?? '');

    $stmt = $pdo->prepare("SELECT idUSUARIO FROM usuarios WHERE email = :email AND idUSUARIO <> :id");
    $stmt->execute([':email' => $email, ':id' => $idUsuario]);
    if ($stmt->fetch()) {
        $_SESSION['message'] = "E-mail já cadastrado por outro usuário.";
        $_SESSION['msg_type'] = 'error';
        header('Location: editar_perfil.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT idUSUARIO FROM usuarios WHERE usuario = :usuario AND idUSUARIO <> :id");
    $stmt->execute([':usuario' => $usuario, ':id' => $idUsuario]);
    if ($stmt->fetch()) {
        $_SESSION['message'] = "Nome de usuário já em uso.";
        $_SESSION['msg_type'] = 'error';
        header('Location: editar_perfil.php');
        exit;
    }

    $fotoNome = $fotoAtual;
    if (!empty($_FILES['fotoPerfil']['name'])) {
        $arquivo    = $_FILES['fotoPerfil'];
        $extensao   = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg','jpeg','png','gif'];
        
        if (in_array($extensao, $permitidas) && $arquivo['size'] <= 2_000_000) {
            $novoNome = uniqid('usr_') . '.' . $extensao;
            $destino  = UPLOAD_DIR . $novoNome;
            if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
                throw new RuntimeException("Não foi possível criar a pasta de uploads.");
            }
            if (move_uploaded_file($arquivo['tmp_name'], $destino)) {
                if ($fotoAtual !== 'default.png' && file_exists(UPLOAD_DIR . $fotoAtual)) {
                    unlink(UPLOAD_DIR . $fotoAtual);
                }
                $fotoNome = $novoNome;
            } else {
                error_log("Falha ao mover upload de perfil: $destino");
            }
        }
    }

    // Atualiza dados do usuário
    $sql = "UPDATE usuarios SET
                nomeUSUARIO = :nome,
                usuario     = :usuario,
                email       = :email,
                telefone    = :telefone,
                endereco    = :endereco,
                cidade      = :cidade,
                estado      = :estado,
                cep         = :cep,
                foto_perfil = :foto
            WHERE idUSUARIO = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome'     => $nome,
        ':usuario'  => $usuario,
        ':email'    => $email,
        ':telefone' => $telefone,
        ':endereco' => $endereco,
        ':cidade'   => $cidade,
        ':estado'   => $estado,
        ':cep'      => $cep,
        ':foto'     => $fotoNome,
        ':id'       => $idUsuario
    ]);

    $_SESSION['message']  = "Perfil atualizado com sucesso!";
    $_SESSION['msg_type'] = 'success';
    header('Location: perfil_normal.php');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['message']  = "Erro ao atualizar perfil.";
    $_SESSION['msg_type'] = 'error';
    header('Location: editar_perfil.php');
    exit;
} catch (RuntimeException $e) {
    $_SESSION['message']  = $e->getMessage();
    $_SESSION['msg_type'] = 'error';
    header('Location: editar_perfil.php');
    exit;
}
?>
