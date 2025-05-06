<?php
require_once _DIR_ . 'Usuarios.class.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id'])? (int) $_POST['id']: 0;
    $nomeUSUARIO = isset($_POST['nomeUSUARIO'])? $_POST['nomeUSUARIO']: '';
    $email = isset($_POST['email'])? $_POST['email']: '';
    $senha  = isset($_POST['senha'])   ? $_POST['senha']: '';
    $telefone = isset($_POST['telefone'])? $_POST['telefone']: '';
    $endereco = isset($_POST['endereco'])? $_POST['endereco']: '';
    $cidade = isset($_POST['cidade'])? $_POST['cidade']: '';
    $estado = isset($_POST['estado'])? $_POST['estado']: '';
    $cep = isset($_POST['cep'])? $_POST['cep']: '';
    $tipo_usuario = isset($_POST['tipo_usuario'])? $_POST['tipo_usuario']: '';
    $acao = isset($_POST['acao'])? $_POST['acao']: '';
}


    $usuario = new Usuarios($id, $nomeUSUARIO, $email, $senha, $telefone, $endereco, $cidade, $estado, $cep, $tipo_usuario);
    //MUDAR
    if ($acao == 'salvar') {
        $resultado = ($id > 0) ? $usuarios->alterar() : $usuarios->inserir();
    } elseif ($acao == 'excluir') {
        $resultado = $usuarios->excluir();
    }

    if ($resultado) header('Location: index.php');
    else echo 'Erro ao salvar/excluir usuarios.';
    else {
    $id = isset($_GET['id']) ? $_GET['id'] : 0;
    if ($id > 0) {
        $res = Usuarios::listar(1, $id);
        if ($res) $usuarios = $res[0];
    }
    $busca = isset($_GET['busca']) ? $_GET['busca'] : '';
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 0;
    $lista = usuarios::listar($tipo, $busca);
}
?>