<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/alertas_check.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($metodo) {

        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT pr.*, p.nome AS produto_nome
                    FROM prateleira pr
                    INNER JOIN produto p ON p.id = pr.id_produto
                    WHERE pr.id = ?");
                $stmt->execute([$id]);
                $r = $stmt->fetch();
                if (!$r) responderJson(false, null, 'Não encontrado.', 404);
                responderJson(true, $r);
            } else {
                $sql = "SELECT pr.*, p.nome AS produto_nome, p.codigo_barras
                        FROM prateleira pr
                        INNER JOIN produto p ON p.id = pr.id_produto
                        ORDER BY pr.validade ASC";
                $stmt = $pdo->query($sql);
                responderJson(true, $stmt->fetchAll());
            }
            break;

        case 'POST':
            $d = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            foreach (['id_produto', 'validade', 'quant_item', 'peso_prat'] as $c) {
                if (!isset($d[$c])) responderJson(false, null, "Campo '$c' obrigatório.", 400);
            }
            $sql = "INSERT INTO prateleira
                    (id_produto, codigo_prateleira, validade, quant_item, peso_prat)
                    VALUES (?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                (int)$d['id_produto'], $d['codigo_prateleira'] ?? null,
                $d['validade'], (int)$d['quant_item'], (float)$d['peso_prat']
            ]);
            $novoId = $pdo->lastInsertId();
            verificarAlertasProduto((int)$d['id_produto']);
            registrarLog('PRATELEIRA_CRIAR', "Prateleira {$novoId}");
            responderJson(true, ['id' => (int)$novoId], 'Item adicionado à prateleira!', 201);
            break;

        case 'PUT':
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            $d = json_decode(file_get_contents('php://input'), true);
            $campos = ['validade','quant_item','peso_prat','codigo_prateleira'];
            $sets = []; $params = [];
            foreach ($campos as $c) {
                if (array_key_exists($c, $d)) { $sets[] = "$c = ?"; $params[] = $d[$c]; }
            }
            if (empty($sets)) responderJson(false, null, 'Nada para atualizar.', 400);
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE prateleira SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($params);

            $q = $pdo->prepare("SELECT id_produto FROM prateleira WHERE id = ?");
            $q->execute([$id]);
            $idProd = $q->fetchColumn();
            if ($idProd) verificarAlertasProduto((int)$idProd);

            registrarLog('PRATELEIRA_EDITAR', "ID {$id}");
            responderJson(true, null, 'Prateleira atualizada.');
            break;

        case 'DELETE':
            if (!ehAdmin()) responderJson(false, null, 'Apenas admin.', 403);
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            $stmt = $pdo->prepare("DELETE FROM prateleira WHERE id = ?");
            $stmt->execute([$id]);
            registrarLog('PRATELEIRA_EXCLUIR', "ID {$id}");
            responderJson(true, null, 'Removido.');
            break;

        default:
            responderJson(false, null, 'Método não suportado.', 405);
    }
} catch (PDOException $e) {
    error_log("API prateleira: " . $e->getMessage());
    responderJson(false, null, $e->getMessage(), 500);
}