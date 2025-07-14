<?php
class ItensPedido {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

public function inserirItens(int $pedidoId, array $itensCarrinho): void {
    $sql = "
        INSERT INTO itens_pedido
            (id_pedido, id_produto, id_variante, quantidade, preco_unitario)
        VALUES
            (:pedido, :produto, :variante, :quantidade, :preco)
    ";
    $stmt = $this->pdo->prepare($sql);

    foreach ($itensCarrinho as $item) {
        $varianteId = $item['idVARIANTE']    
                   ?? $item['id_variante']  
                   ?? null;

        $stmt->execute([
            ':pedido'     => $pedidoId,
            ':produto'    => $item['id_produto'],
            ':variante'   => $varianteId,
            ':quantidade' => $item['quantidade'],
            ':preco'      => $item['preco'],
        ]);
    }
}

public function listarPorPedido(int $pedidoId): array {
    $sql = "
        SELECT
            ip.*,
            ip.id_variante,
            p.nomePRODUTO
        FROM itens_pedido ip
        JOIN produtos p ON ip.id_produto = p.idPRODUTO
        WHERE ip.id_pedido = :pedido
    ";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':pedido' => $pedidoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}