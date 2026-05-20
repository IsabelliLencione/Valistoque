<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/alertas_check.php';   // helper para gerar alertas
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($metodo) {

        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT e.*, p.nome AS produto_nome, p.codigo_barras
                    FROM estoque e
                    INNER JOIN produto p ON p.id = e.id_produto
                    WHERE e.id = ?");
                $stmt->execute([$id]);
                $r = $stmt->fetch();
                if (!$r) responderJson(false, null, 'Não encontrado.', 404);
                responderJson(true, $r);
            } else {
                $sql = "SELECT e.*, p.nome AS produto_nome, p.codigo_barras, p.categoria
                        FROM estoque e
                        INNER JOIN produto p ON p.id = e.id_produto";
                $params = [];
                if (!empty($_GET['id_produto'])) {
                    $sql     .= " WHERE e.id_produto = ?";
                    $params[] = (int)$_GET['id_produto'];
                }
                $sql .= " ORDER BY e.validade ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                responderJson(true, $stmt->fetchAll());
            }
            break;

        case 'POST':
            $d = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            foreach (['id_produto', 'lote', 'quant_prod', 'validade'] as $c) {
                if (!isset($d[$c])) responderJson(false, null, "Campo '{$c}' obrigatório.", 400);
            }

            // Verifica se já existe esse lote no estoque -> soma quantidade
            $check = $pdo->prepare("SELECT id, quant_prod FROM estoque
                                    WHERE id_produto = ? AND lote = ? LIMIT 1");
            $check->execute([(int)$d['id_produto'], $d['lote']]);
            $existente = $check->fetch();

            if ($existente) {
                $novaQtd = $existente['quant_prod'] + (int)$d['quant_prod'];
                $upd = $pdo->prepare("UPDATE estoque SET quant_prod = ?,
                                      validade = ? WHERE id = ?");
                $upd->execute([$novaQtd, $d['validade'], $existente['id']]);
                $idGravado = $existente['id'];
            } else {
                $ins = $pdo->prepare("INSERT INTO estoque
                                      (id_produto, lote, quant_prod, validade)
                                      VALUES (?,?,?,?)");
                $ins->execute([
                    (int)$d['id_produto'], $d['lote'],
                    (int)$d['quant_prod'], $d['validade']
                ]);
                $idGravado = $pdo->lastInsertId();
            }

            // Registra movimentação
            $mv = $pdo->prepare("INSERT INTO movimentacao
                (id_produto, tipo, origem, destino, quantidade, id_usuario, perfil_usuario, observacao)
                VALUES (?, 'entrada', 'externo', 'estoque', ?, ?, ?, ?)");
            $mv->execute([
                (int)$d['id_produto'], (int)$d['quant_prod'],
                $_SESSION['usuario_id'], $_SESSION['perfil'],
                $d['observacao'] ?? 'Entrada de estoque'
            ]);

            registrarLog('ESTOQUE_ENTRADA',
                "Produto {$d['id_produto']} | Qtd {$d['quant_prod']} | Lote {$d['lote']}");

            // Atualiza alertas
            verificarAlertasProduto((int)$d['id_produto']);
            responderJson(true, ['id' => (int)$idGravado], 'Entrada registrada!', 201);
            break;

        case 'PUT':
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            $d = json_decode(file_get_contents('php://input'), true);
            $campos = ['quant_prod', 'validade', 'lote']; $sets = []; $params = [];
            foreach ($campos as $c) {
                if (array_key_exists($c, $d)) { $sets[] = "$c = ?"; $params[] = $d[$c]; }
            }
            if (empty($sets)) responderJson(false, null, 'Nada para atualizar.', 400);
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE estoque SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($params);

            // Identifica produto p/ checar alerta
            $q = $pdo->prepare("SELECT id_produto FROM estoque WHERE id = ?");
            $q->execute([$id]);
            $idProd = $q->fetchColumn();
            if ($idProd) verificarAlertasProduto((int)$idProd);

            registrarLog('ESTOQUE_EDITAR', "ID {$id}");
            responderJson(true, null, 'Estoque atualizado.');
            break;

        case 'DELETE':
            if (!ehAdmin()) responderJson(false, null, 'Apenas admin.', 403);
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            $stmt = $pdo->prepare("DELETE FROM estoque WHERE id = ?");
            $stmt->execute([$id]);
            registrarLog('ESTOQUE_EXCLUIR', "ID {$id}");
            responderJson(true, null, 'Removido.');
            break;

        default:
            responderJson(false, null, 'Método não suportado.', 405);
    }
} catch (PDOException $e) {
    error_log("API estoque: " . $e->getMessage());
    responderJson(false, null, $e->getMessage(), 500);
}