<?php
class Produto {
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Busca um produto pelo ID, incluindo nomes dinâmicos de tipologia e especificação
     * @param int $id
     * @return array|false
     */
    public function buscarPorId(int $id) {
        $sql = "
            SELECT
                p.idPRODUTO,
                p.nomePRODUTO,
                p.descricaoPRODUTO,
                p.precoPRODUTO,
                p.quantidade,
                p.imagemPRODUTO,
                p.id_artesao,
                p.nome_tipologia,
                p.nome_especificacao,
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
     * Lista todos os produtos
     * @return array
     */
    public function listar(): array {
        $sql = "SELECT * FROM produtos";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insere um novo produto, salvando nomes de tipologia e especificação
     * @param array $dados
     * @return int ID do produto
     */
    public function inserir(array $dados): int {
        $sql = "
            INSERT INTO produtos
                (nomePRODUTO, descricaoPRODUTO, nome_tipologia, nome_especificacao,
                 precoPRODUTO, quantidade, imagemPRODUTO, id_artesao)
            VALUES
                (:nome, :descr, :tiponome, :espnome, :preco, :quant, :img, :art)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nome'     => $dados['nome'],
            ':descr'    => $dados['descricao'],
            ':tiponome' => $dados['nome_tipologia'],
            ':espnome'  => $dados['nome_especificacao'],
            ':preco'    => $dados['preco'],
            ':quant'    => $dados['quantidade'],
            ':img'      => $dados['imagem'],
            ':art'      => $dados['id_artesao'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza um produto existente
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function alterar(int $id, array $dados): bool {
        $sql = "
            UPDATE produtos SET
                nomePRODUTO = :nome,
                descricaoPRODUTO = :descr,
                nome_tipologia = :tiponome,
                nome_especificacao = :espnome,
                precoPRODUTO = :preco,
                quantidade = :quant,
                imagemPRODUTO = :img
            WHERE idPRODUTO = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nome'     => $dados['nome'],
            ':descr'    => $dados['descricao'],
            ':tiponome' => $dados['nome_tipologia'],
            ':espnome'  => $dados['nome_especificacao'],
            ':preco'    => $dados['preco'],
            ':quant'    => $dados['quantidade'],
            ':img'      => $dados['imagem'],
            ':id'       => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Exclui um produto
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
