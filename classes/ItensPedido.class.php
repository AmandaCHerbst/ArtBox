<?php
class ItensPedido {
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Insere os itens de um pedido
     * @param int $pedidoId
     * @param array $itensCarrinho
     * @return void
     */
    public function inserirItens(int $pedidoId, array $itensCarrinho): void {
        $sql = "
            INSERT INTO itens_pedido
                (id_pedido, id_produto, quantidade, preco_unitario)
            VALUES
                (:pedido, :produto, :quantidade, :preco)
        ";
        $stmt = $this->pdo->prepare($sql);

        foreach ($itensCarrinho as $item) {
            $stmt->execute([
                ':pedido'     => $pedidoId,
                ':produto'    => $item['id_produto'],
                ':quantidade' => $item['quantidade'],
                ':preco'      => $item['preco'],
            ]);
        }
    }

    /**
     * Lista os itens de um pedido
     * @param int $pedidoId
     * @return array
     */
    public function listarPorPedido(int $pedidoId): array {
        $sql = "
            SELECT ip.*, p.nomePRODUTO
            FROM itens_pedido ip
            JOIN produtos p ON ip.id_produto = p.idPRODUTO
            WHERE ip.id_pedido = :pedido
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pedido' => $pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}