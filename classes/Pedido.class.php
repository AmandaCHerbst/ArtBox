<?php
class Pedido {
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Cria um pedido completo com itens e registra artesãos para aprovação
     * @param int $userId
     * @param array $dadosCliente
     * @param array $carrinho
     * @return int ID do pedido criado
     * @throws Exception
     */
    public function criarPedido(int $userId, array $dadosCliente, array $carrinho): int {
        $this->pdo->beginTransaction();
        try {
            // Calcula valor total
            $valorTotal = 0;
            foreach ($carrinho as $item) {
                $valorTotal += ($item['preco'] ?? 0) * ($item['quantidade'] ?? 0);
            }
            // Insere pedido
            $stmt = $this->pdo->prepare(
                "INSERT INTO pedidos (id_usuario, valor_total, status) VALUES (:uid, :total, 'pendente')"
            );
            $stmt->execute([':uid' => $userId, ':total' => $valorTotal]);
            $pedidoId = (int)$this->pdo->lastInsertId();

            // Insere itens e diminui estoque
            foreach ($carrinho as $item) {
                $stmtItem = $this->pdo->prepare(
                    "INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario)
                     VALUES (:pid, :prod, :qtd, :preco)"
                );
                $stmtItem->execute([
                    ':pid'   => $pedidoId,
                    ':prod'  => $item['id_produto'],
                    ':qtd'   => $item['quantidade'],
                    ':preco' => $item['preco'],
                ]);
                if (!empty($item['idVARIANTE'])) {
                    $stmtEst = $this->pdo->prepare(
                        "UPDATE variantes SET estoque = estoque - :qtd WHERE idVARIANTE = :var"
                    );
                    $stmtEst->execute([':qtd' => $item['quantidade'], ':var' => $item['idVARIANTE']]);
                }
            }

            // Identifica artesãos distintos envolvidos
            $artisanStmt = $this->pdo->prepare(
                "SELECT DISTINCT p.id_artesao
                 FROM itens_pedido ip
                 JOIN produtos p ON ip.id_produto = p.idPRODUTO
                 WHERE ip.id_pedido = :pid"
            );
            $artisanStmt->execute([':pid' => $pedidoId]);
            $artesaos = $artisanStmt->fetchAll(PDO::FETCH_COLUMN);

            // Registra em pedidos_artesao para cada artesão
            $paStmt = $this->pdo->prepare(
                "INSERT INTO pedidos_artesao (id_pedido, id_artesao) VALUES (:pid, :aid)"
            );
            foreach ($artesaos as $aid) {
                $paStmt->execute([':pid' => $pedidoId, ':aid' => $aid]);
            }

            $this->pdo->commit();
            return $pedidoId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza o status de aprovação de um artesão em um pedido
     * @param int $pedidoId
     * @param int $idArtesao
     * @param string $novoStatus ('aprovado' ou 'rejeitado')
     * @return void
     */
    public function atualizarStatusArtesao(int $pedidoId, int $idArtesao, string $novoStatus): void {
        $stmt = $this->pdo->prepare(
            "UPDATE pedidos_artesao SET status = :status WHERE id_pedido = :pid AND id_artesao = :aid"
        );
        $stmt->execute([':status' => $novoStatus, ':pid' => $pedidoId, ':aid' => $idArtesao]);
    }

    /**
     * Lista pedidos pendentes para um artesão
     * @param int $idArtesao
     * @return array
     */
    public function listarPedidosPendentesPorArtesao(int $idArtesao): array {
        $sql = "
            SELECT p.idPEDIDO, u.nomeUSUARIO, p.valor_total, pa.status AS status_artesao
            FROM pedidos_artesao pa
            JOIN pedidos p ON pa.id_pedido = p.idPEDIDO
            JOIN usuarios u ON u.idUSUARIO = p.id_usuario
            WHERE pa.id_artesao = :aid AND pa.status = 'pendente'
            ORDER BY p.data_pedido DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':aid' => $idArtesao]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista pedidos aprovados para um cliente (em preparo)
     * @param int $idUsuario
     * @return array
     */
    public function listarPedidosEmPreparo(int $idUsuario): array {
        $sql = "
            SELECT DISTINCT p.*
            FROM pedidos_artesao pa
            JOIN pedidos p ON pa.id_pedido = p.idPEDIDO
            WHERE p.id_usuario = :uid AND pa.status = 'aprovado'
            ORDER BY p.data_pedido DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
