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
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        try {
            // Calcula valor total (usando preços do carrinho)
            $valorTotal = 0.0;
            foreach ($carrinho as $item) {
                $valorTotal += (float)($item['preco'] ?? 0) * (int)($item['quantidade'] ?? 0);
            }

            // Insere pedido
            $stmt = $pdo->prepare(
                "INSERT INTO pedidos (id_usuario, valor_total, status) VALUES (:uid, :total, 'pendente')"
            );
            $stmt->execute([':uid' => $userId, ':total' => $valorTotal]);
            $pedidoId = (int)$pdo->lastInsertId();

            // Prepara statements
            $stmtItem = $pdo->prepare(
                "INSERT INTO itens_pedido (id_pedido, id_produto, id_variante, quantidade, preco_unitario)
                 VALUES (:pid, :prod, :var, :qtd, :preco)"
            );

            $stmtSelectVar = $pdo->prepare("SELECT estoque FROM variantes WHERE idVARIANTE = :var FOR UPDATE");
            $stmtUpdateVar = $pdo->prepare("UPDATE variantes SET estoque = estoque - :qtd WHERE idVARIANTE = :var");
            $stmtUpdateProd = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - :qtd WHERE idPRODUTO = :prod");

            // Insere itens e ajusta estoque
            foreach ($carrinho as $item) {
                $idProduto = (int)($item['id_produto'] ?? 0);
                $idVariante = isset($item['idVARIANTE']) ? (int)$item['idVARIANTE'] : null;
                $qtd = max(0, (int)($item['quantidade'] ?? 0));
                $preco = (float)($item['preco'] ?? 0);

                // Insere item (id_variante pode ser NULL)
                $stmtItem->execute([
                    ':pid' => $pedidoId,
                    ':prod' => $idProduto,
                    ':var' => $idVariante,
                    ':qtd' => $qtd,
                    ':preco' => $preco,
                ]);

                // Se houver variante, checar estoque e decrementar
                if (!empty($idVariante)) {
                    $stmtSelectVar->execute([':var' => $idVariante]);
                    $row = $stmtSelectVar->fetch(PDO::FETCH_ASSOC);
                    if ($row === false) {
                        // variante não existe
                        throw new Exception("Variante #{$idVariante} não encontrada.");
                    }
                    $estoqueAtual = (int)$row['estoque'];
                    if ($estoqueAtual < $qtd) {
                        throw new Exception("Estoque insuficiente para a variante #{$idVariante}. Disponível: {$estoqueAtual}, solicitado: {$qtd}.");
                    }
                    $stmtUpdateVar->execute([':qtd' => $qtd, ':var' => $idVariante]);
                } else {
                    // Sem variante: tentar decrementar a coluna produtos.quantidade (se existir)
                    try {
                        $stmtUpdateProd->execute([':qtd' => $qtd, ':prod' => $idProduto]);
                    } catch (\Exception $e) {
                        // se a coluna não existir, ignoramos (não queremos quebrar)
                    }
                }

                // Também tenta decrementar produtos.quantidade como redundância (se usar)
                try {
                    $stmtUpdateProd->execute([':qtd' => $qtd, ':prod' => $idProduto]);
                } catch (\Exception $e) {
                    // ignora se não existir
                }
            }

            // Identifica artesãos distintos envolvidos (a partir dos itens gravados)
            $artisanStmt = $pdo->prepare(
                "SELECT DISTINCT p.id_artesao
                 FROM itens_pedido ip
                 JOIN produtos p ON ip.id_produto = p.idPRODUTO
                 WHERE ip.id_pedido = :pid"
            );
            $artisanStmt->execute([':pid' => $pedidoId]);
            $artesaos = $artisanStmt->fetchAll(PDO::FETCH_COLUMN);

            // Registra em pedidos_artesao para cada artesão (evita duplicatas usando INSERT IGNORE-like)
            $paStmt = $pdo->prepare(
                "INSERT INTO pedidos_artesao (id_pedido, id_artesao, status) VALUES (:pid, :aid, 'pendente')"
            );
            foreach ($artesaos as $aid) {
                // proteção: somente insere se houver id
                if (empty($aid)) continue;
                $paStmt->execute([':pid' => $pedidoId, ':aid' => $aid]);
            }

            $pdo->commit();
            return $pedidoId;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza o status de aprovação de um artesão em um pedido
     * Se o artesão rejeitar, repõe o estoque dos itens que pertencem a esse artesão.
     *
     * @param int $pedidoId
     * @param int $idArtesao
     * @param string $novoStatus ('aprovado' ou 'rejeitado')
     * @return bool
     * @throws Exception
     */
    public function atualizarStatusArtesao(int $pedidoId, int $idArtesao, string $novoStatus): bool {
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        try {
            // Atualiza status e data de atualização
            $stmt = $pdo->prepare(
                "UPDATE pedidos_artesao
                 SET status = :status, data_atualizacao = NOW()
                 WHERE id_pedido = :pid AND id_artesao = :aid"
            );
            $stmt->execute([':status' => $novoStatus, ':pid' => $pedidoId, ':aid' => $idArtesao]);

            // Se rejeitado, repor estoque apenas dos itens desse artesão
            if ($novoStatus === 'rejeitado') {
                // Busca itens do pedido que pertencem a esse artesão (trava para update)
                $stmtItems = $pdo->prepare("
                    SELECT ip.idintens_pedido, ip.id_produto, ip.id_variante, ip.quantidade
                    FROM itens_pedido ip
                    JOIN produtos p ON p.idPRODUTO = ip.id_produto
                    WHERE ip.id_pedido = :pid
                      AND p.id_artesao = :aid
                    FOR UPDATE
                ");
                $stmtItems->execute([':pid' => $pedidoId, ':aid' => $idArtesao]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                $stmtUpdateVar = $pdo->prepare("UPDATE variantes SET estoque = estoque + :qtd WHERE idVARIANTE = :idvariante");
                $stmtUpdateProd = $pdo->prepare("UPDATE produtos SET quantidade = quantidade + :qtd WHERE idPRODUTO = :idproduto");

                foreach ($items as $it) {
                    $qty = (int)$it['quantidade'];
                    $idVar = !empty($it['id_variante']) ? (int)$it['id_variante'] : null;
                    $idProd = (int)$it['id_produto'];

                    if (!empty($idVar)) {
                        $stmtUpdateVar->execute([':qtd' => $qty, ':idvariante' => $idVar]);
                    } else {
                        // fallback: tenta repor em produtos.quantidade
                        try {
                            $stmtUpdateProd->execute([':qtd' => $qty, ':idproduto' => $idProd]);
                        } catch (\Exception $e) {
                            // ignora
                        }
                    }

                    // também tenta repor em produtos.quantidade por segurança
                    try {
                        $stmtUpdateProd->execute([':qtd' => $qty, ':idproduto' => $idProd]);
                    } catch (\Exception $e) {
                        // ignora se a coluna não existir
                    }
                }
            }

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lista pedidos pendentes para um artesão
     * @param int $idArtesao
     * @return array
     */
    public function listarPedidosPendentesPorArtesao(int $idArtesao): array {
        $sql = "
            SELECT p.idPEDIDO, u.nomeUSUARIO, p.valor_total, pa.status AS status_artesao, p.data_pedido
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
