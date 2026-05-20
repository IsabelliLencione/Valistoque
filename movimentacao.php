<?php
/**
 * ============================================================
 *  VALISTOQUE - API REST  /api/movimentacao.php
 * ============================================================
 *  GET  -> histórico de entradas/saídas/transferências.
 *  Filtros: ?id_produto, ?tipo, ?inicio (YYYY-MM-DD), ?fim
 *
 *  POST -> registra saída manual (ex.: venda, perda, descarte)
 *          { id_produto, origem, quantidade, observacao }
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/alertas_check.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    switch ($metodo) {

        case 'GET':
            $sql = "SELECT m.*, p.nome AS produto_nome
                    FROM movimentacao m
                    LEFT JOIN produto p ON p.id = m.id_produto
                    WHERE 1=1";
            $params = [];
            if (!empty($_GET['id_produto'])) {
                $sql .= " AND m.id_produto = ?"; $params[] = (int)$_GET['id_produto'];
            }
            if (!empty($_GET['tipo']) &&
                in_array($_GET['tipo'], ['entrada','saida','transferencia'], true)) {
                $sql .= " AND m.tipo = ?"; $params[] = $_GET['tipo'];
            }
            if (!empty($_GET['inicio'])) {
                $sql .= " AND m.data_hora >= ?"; $params[] = $_GET['inicio'] . ' 00:00:00';
            }
            if (!empty($_GET['fim'])) {
                $sql .= " AND m.data_hora <= ?"; $params[] = $_GET['fim'] . ' 23:59:59';
            }
            $sql .= " ORDER BY m.data_hora DESC LIMIT 500";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            responderJson(true, $stmt->fetchAll());
            break;

        case 'POST':
            $d = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            foreach (['id_produto','origem','quantidade'] as $c) {
                if (empty($d[$c])) responderJson(false, null, "Campo '$c' obrigatório.", 400);
            }
            if (!in_array($d['origem'], ['estoque','prateleira'], true)) {
                responderJson(false, null, "Origem deve ser 'estoque' ou 'prateleira'.", 400);
            }
            $idProd = (int)$d['id_produto'];
            $qtde   = (int)$d['quantidade'];
            if ($qtde <= 0) responderJson(false, null, 'Quantidade inválida.', 400);

            $pdo->beginTransaction();

            if ($d['origem'] === 'estoque') {
                // Tira do estoque (FIFO por validade)
                $busca = $pdo->prepare("SELECT * FROM estoque
                                        WHERE id_produto = ? AND quant_prod > 0
                                        ORDER BY validade ASC FOR UPDATE");
                $busca->execute([$idProd]);
                $restante = $qtde;
                foreach ($busca->fetchAll() as $row) {
                    if ($restante <= 0) break;
                    $debitar = min($restante, (int)$row['quant_prod']);
                    $upd = $pdo->prepare("UPDATE estoque SET quant_prod = quant_prod - ? WHERE id = ?");
                    $upd->execute([$debitar, $row['id']]);
                    $restante -= $debitar;
                }
                if ($restante > 0) {
                    $pdo->rollBack();
                    responderJson(false, null, "Estoque insuficiente. Faltam {$restante} un.", 400);
                }
            } else {
                $busca = $pdo->prepare("SELECT * FROM prateleira
                                        WHERE id_produto = ? AND quant_item > 0
                                        ORDER BY validade ASC FOR UPDATE");
                $busca->execute([$idProd]);
                $restante = $qtde;
                foreach ($busca->fetchAll() as $row) {
                    if ($restante <= 0) break;
                    $debitar = min($restante, (int)$row['quant_item']);
                    $upd = $pdo->prepare("UPDATE prateleira
                                          SET quant_item = quant_item - ?,
                                              peso_prat  = peso_prat  - (? * (peso_prat / NULLIF(quant_item,0)))
                                          WHERE id = ?");
                    $upd->execute([$debitar, $debitar, $row['id']]);
                    $restante -= $debitar;
                }
                if ($restante > 0) {
                    $pdo->rollBack();
                    responderJson(false, null, "Prateleira insuficiente. Faltam {$restante} un.", 400);
                }
            }

            $mv = $pdo->prepare("INSERT INTO movimentacao
                (id_produto, tipo, origem, destino, quantidade, id_usuario, perfil_usuario, observacao)
                VALUES (?, 'saida', ?, 'externo', ?, ?, ?, ?)");
            $mv->execute([
                $idProd, $d['origem'], $qtde,
                $_SESSION['usuario_id'], $_SESSION['perfil'],
                $d['observacao'] ?? 'Saída manual'
            ]);
            $pdo->commit();

            verificarAlertasProduto($idProd);
            registrarLog('MOVIMENTACAO_SAIDA',
                "Produto {$idProd} | Origem {$d['origem']} | Qtd {$qtde}");
            responderJson(true, null, "Saída registrada ({$qtde} un.).", 201);
            break;

        default:
            responderJson(false, null, 'Método não suportado.', 405);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("API movimentação: " . $e->getMessage());
    responderJson(false, null, $e->getMessage(), 500);
}