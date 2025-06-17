<?php
require_once _DIR_ . '/config/config.inc.php';

class Usuarios {
    private $id;
    private $nomeUSUARIO;
    private $email;
    private $senha;
    private $telefone;
    private $endereco;
    private $cidade;
    private $estado;
    private $cep;
    private $tipo_usuario;


    public function __construct( $id, $nomeUSUARIO, $email, $senha, $telefone, $endereco, $cidade, $estado, $cep, $tipo_usuario) {
        $this->id = $id;
        $this->nomeUSUARIO = $nomeUSUARIO;
        $this->email = $email;
        $this->senha = $senha;
        $this->telefone = $telefone;
        $this->endereco = $endereco;
        $this->cidade = $cidade;
        $this->estado = $estado;
        $this->cep = $cep;
        $this->tipo_usuario = $tipo_usuario;
    }
    
//...
    public function inserir(): bool {
        $conexao = new PDO(DSN, USUARIO, SENHA);
        $sql = "INSERT INTO pergunta (texto, tipo, opcoes, obrigatoria) VALUES (:texto, :tipo, :opcoes, :obrigatoria)";
        $comando = $conexao->prepare($sql);
        $comando->bindValue(':texto', $this->getTexto());
        $comando->bindValue(':tipo', $this->getTipo());
        $comando->bindValue(':opcoes', $this->getOpcoes());
        $comando->bindValue(':obrigatoria', $this->getObrigatoria(), PDO::PARAM_BOOL);
        return $comando->execute();
    }

    public static function listar($tipo = 0, $info = ''): array {
        $conexao = new PDO(DSN, USUARIO, SENHA);
        $sql = "SELECT * FROM pergunta";
        if ($tipo > 0) {
            switch ($tipo) {
                case 1:
                    $sql .= " WHERE id = :info";
                    break;
                case 2:
                    $sql .= " WHERE texto LIKE :info";
                    $info = "%$info%";
                    break;
            }
        }
        $comando = $conexao->prepare($sql);
        if ($tipo > 0) {
            $comando->bindValue(':info', $info);
        }
        $comando->execute();
        return $comando->fetchAll();
    }

    public function alterar(): bool {
        $conexao = new PDO(DSN, USUARIO, SENHA);
        $sql = "UPDATE pergunta SET texto = :texto, tipo = :tipo, opcoes = :opcoes, obrigatoria = :obrigatoria WHERE id = :id";
        $comando = $conexao->prepare($sql);
        $comando->bindValue(':id', $this->getId());
        $comando->bindValue(':texto', $this->getTexto());
        $comando->bindValue(':tipo', $this->getTipo());
        $comando->bindValue(':opcoes', $this->getOpcoes());
        $comando->bindValue(':obrigatoria', $this->getObrigatoria(), PDO::PARAM_BOOL);
        return $comando->execute();
    }

    public function excluir(): bool {
        $conexao = new PDO(DSN, USUARIO, SENHA);
        $sql = "DELETE FROM pergunta WHERE id = :id";
        $comando = $conexao->prepare($sql);
        $comando->bindValue(':id', $this->getId());
        return $comando->execute();
    }
    
    public function setId($id) {
        if ($id < 0) throw new Exception("ID inválido");
        $this->id = $id;
    }
    public function setTexto($texto) {
        if (empty($texto)) throw new Exception("O texto da pergunta não pode ser vazio");
        $this->texto = $texto;
    }
    public function setTipo($tipo) {
        if (empty($tipo)) throw new Exception("O tipo da pergunta deve ser definido");
        $this->tipo = $tipo;
    }
    public function setOpcoes($opcoes = "") {
        $this->opcoes = $opcoes;
    }
    public function setObrigatoria($obrigatoria = 0) {
        $this->obrigatoria = $obrigatoria;
    }

    public function getId(): int {
        return $this->id;
    }
    public function getTexto(): string {
        return $this->texto;
    }
    public function getTipo(): string {
        return $this->tipo;
    }
    public function getOpcoes(): string {
        return $this->opcoes;
    }
    public function getObrigatoria(): int {
        return $this->obrigatoria;
    }
}
?>