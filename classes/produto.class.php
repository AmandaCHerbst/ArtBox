<?php
class Produto {
    /** @var PDO  */
    private $pdo;

    /**
     * @param PDO 
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    /**
     * @param int 
     * @return array|false 
     */
    public function buscarPorId(int $id) {
        $sql = "
            SELECT p.*, 
                   GROUP_CONCAT(DISTINCT c.nomeCATEGORIA SEPARATOR ',') AS categorias
            FROM produtos p
            LEFT JOIN produto_categorias pc ON p.idPRODUTO = pc.id_produto
            LEFT JOIN categorias c ON pc.id_categoria = c.idCATEGORIA
            WHERE p.idPRODUTO = :id
            GROUP BY p.idPRODUTO
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array 
     */
    public function listar(): array {
        $sql = "SELECT * FROM produtos";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array 
     * @return int 
     */
    public function inserir(array $dados): int {
        $sql = "
            INSERT INTO produtos
                (nomePRODUTO, descricaoPRODUTO, tamanhos_disponiveis, cores_disponiveis,
                 precoPRODUTO, quantidade, imagemPRODUTO, id_artesao)
            VALUES
                (:nome, :descr, :tamanhos, :cores, :preco, :quant, :img, :art)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nome'     => $dados['nome'],
            ':descr'    => $dados['descricao'],
            ':tamanhos' => $dados['tamanhos'],
            ':cores'    => $dados['cores'],
            ':preco'    => $dados['preco'],
            ':quant'    => $dados['quantidade'],
            ':img'      => $dados['imagem'],
            ':art'      => $dados['id_artesao'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param int $id
     * @param array 
     * @return bool 
     */
    public function alterar(int $id, array $dados): bool {
        $sql = "
            UPDATE produtos SET
                nomePRODUTO = :nome,
                descricaoPRODUTO = :descr,
                tamanhos_disponiveis = :tamanhos,
                cores_disponiveis = :cores,
                precoPRODUTO = :preco,
                quantidade = :quant,
                imagemPRODUTO = :img
            WHERE idPRODUTO = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nome'     => $dados['nome'],
            ':descr'    => $dados['descricao'],
            ':tamanhos' => $dados['tamanhos'],
            ':cores'    => $dados['cores'],
            ':preco'    => $dados['preco'],
            ':quant'    => $dados['quantidade'],
            ':img'      => $dados['imagem'],
            ':id'       => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param int $id
     * @return bool 
     */
    public function excluir(int $id): bool {
        $sql = "DELETE FROM produtos WHERE idPRODUTO = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
