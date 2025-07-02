<?php
class Pedido {
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Cria um pedido completo com os itens do carrinho
     * @param int   $userId   ID do usuário
     * @param array $dadosCliente (não usado aqui, mas mantido para compatibilidade)
     * @param array $carrinho  formato ['variantId' => quantidade, ...]
     * @return int ID do pedido criado
     * @throws Exception
     */
    public function criarPedido(int $userId, array $dadosCliente, array $carrinho): int {
        $this->pdo->beginTransaction();
        try {
            // Calcula valor total
            $total = 0.0;
            $stmtPreco = $this->pdo->prepare(
                "SELECT p.precoPRODUTO, v.idVARIANTE, p.idPRODUTO 
                 FROM produtos p 
                 JOIN variantes v ON v.id_produto = p.idPRODUTO
                 WHERE v.idVARIANTE = :varId"
            );
            $itensDetalhados = [];
            foreach ($carrinho as $variantId => $qty) {
                $stmtPreco->execute([':varId' => $variantId]);
                $row = $stmtPreco->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['precoPRODUTO'] <= 0) {
                    throw new Exception("Item inválido ou sem preço: variante $variantId");
                }
                if ($qty < 1) continue;
                $price = (float)$row['precoPRODUTO'];
                $total += $price * $qty;
                $itensDetalhados[] = [
                    'variantId' => $variantId,
                    'productId' => (int)$row['idPRODUTO'],
                    'quantity'  => $qty,
                    'unitPrice' => $price,
                ];
            }

            // Insere pedido
            $stmtIns = $this->pdo->prepare(
                "INSERT INTO pedidos (id_usuario, valor_total, status) VALUES (:uid, :total, 'pago')"
            );
            $stmtIns->execute([ ':uid' => $userId, ':total' => $total ]);
            $pedidoId = (int)$this->pdo->lastInsertId();

            // Insere itens e atualiza estoque
            $stmtItem = $this->pdo->prepare(
                "INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario)
                 VALUES (:pid, :prod, :qtd, :preco)"
            );
            $stmtEstoque = $this->pdo->prepare(
                "UPDATE variantes SET estoque = estoque - :qtd WHERE idVARIANTE = :varId"
            );
            $stmtProdQty = $this->pdo->prepare(
                "UPDATE produtos SET quantidade = GREATEST(quantidade - :qtd, 0) WHERE idPRODUTO = :prodId"
            );

            foreach ($itensDetalhados as $item) {
                // insere item
                $stmtItem->execute([
                    ':pid'   => $pedidoId,
                    ':prod'  => $item['productId'],
                    ':qtd'   => $item['quantity'],
                    ':preco' => $item['unitPrice'],
                ]);
                // atualiza variantes
                $stmtEstoque->execute([
                    ':qtd'   => $item['quantity'],
                    ':varId' => $item['variantId'],
                ]);
                // atualiza produto
                $stmtProdQty->execute([
                    ':qtd'    => $item['quantity'],
                    ':prodId' => $item['productId'],
                ]);
            }

            $this->pdo->commit();
            return $pedidoId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
