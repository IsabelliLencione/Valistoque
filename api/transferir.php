<?php
/**
 * ============================================================
 *  VALISTOQUE - API  /api/transferir.php
 *  POST -> Transfere quantidade do ESTOQUE para a PRATELEIRA
 *          { id_produto, lote, quantidade, codigo_prateleira? }
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/alertas_check.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(false, null, 'Use POST.', 405);
}

$d = json_decode(file_get_contents('php://input'), true) ?: $_POST;
foreach (['id_produto', 'lote', 'quantidade'] as $c) {
    if (empty($d[$c])) responderJson(false, null, "Campo '$c' obrigatório.", 400);
}

$idProduto  = (int)$d['id_produto'];
$lote       = $d['lote'];
$qtde       = (int)$d['quantidade'];
$codigoPrat = $d['codigo_prateleira'] ?? null;

if ($qtde <= 0) responderJson(false, null, 'Quantidade deve ser > 0.', 400);

try {
    $pdo->beginTransaction();

    // 1. Busca o lote no estoque
    $stmt = $pdo->prepare("SELECT * FROM estoque
                           WHERE id_produto = ? AND lote = ?
                           LIMIT 1 FOR UPDATE");
    $stmt->execute([$idProduto, $lote]);
    $est = $stmt->fetch();

    if (!$est) {
        $pdo->rollBack();
        responderJson(false, null, 'Lote não encontrado no estoque.', 404);
    }
    if ($est['quant_prod'] < $qtde) {
        $pdo->rollBack();
        responderJson(false, null, "Estoque insuficiente. Disponível: {$est['quant_prod']}.", 400);
    }

    // 2. Decrementa estoque
    $upd = $pdo->prepare("UPDATE estoque SET quant_prod = quant_prod - ? WHERE id = ?");
    $upd->execute([$qtde, $est['id']]);

    // 3. Pega peso unitário do produto
    $qProd = $pdo->prepare("SELECT peso FROM produto WHERE id = ?");
    $qProd->execute([$idProduto]);
    $pesoUnit = (float)$qProd->fetchColumn();

    // 4. Procura prateleira já existente com mesma validade
    $busca = $pdo->prepare("SELECT id, quant_item, peso_prat FROM prateleira
                            WHERE id_produto = ? AND validade = ?
                            LIMIT 1");
    $busca->execute([$idProduto, $est['validade']]);
    $prat = $busca->fetch();

    if ($prat) {
        $novaQtd  = $prat['quant_item'] + $qtde;
        $novoPeso = $prat['peso_prat']   + ($qtde * $pesoUnit);
        $u = $pdo->prepare("UPDATE prateleira
                            SET quant_item = ?, peso_prat = ?, data_reposicao = NOW()
                            WHERE id = ?");
        $u->execute([$novaQtd, $novoPeso, $prat['id']]);
        $idPrateleira = $prat['id'];
    } else {
        $ins = $pdo->prepare("INSERT INTO prateleira
                              (id_produto, codigo_prateleira, validade, quant_item, peso_prat)
                              VALUES (?,?,?,?,?)");
        $ins->execute([$idProduto, $codigoPrat, $est['validade'], $qtde, $qtde * $pesoUnit]);
        $idPrateleira = $pdo->lastInsertId();
    }

    // 5. Registra movimentação
    $mv = $pdo->prepare("INSERT INTO movimentacao
        (id_produto, tipo, origem, destino, quantidade, id_usuario, perfil_usuario, observacao)
        VALUES (?, 'transferencia', 'estoque', 'prateleira', ?, ?, ?, ?)");
    $mv->execute([
        $idProduto, $qtde, $_SESSION['usuario_id'], $_SESSION['perfil'],
        "Transferência lote {$lote} para prateleira #{$idPrateleira}"
    ]);

    $pdo->commit();

    // 6. Recalcula alertas
    verificarAlertasProduto($idProduto);

    registrarLog('TRANSFERENCIA', "Produto {$idProduto} | Qtd {$qtde} | Lote {$lote}");
    responderJson(true,
        ['id_prateleira' => (int)$idPrateleira],
        "Transferidos {$qtde} item(ns) para a prateleira."
    );

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Transferência: " . $e->getMessage());
    responderJson(false, null, 'Erro: ' . $e->getMessage(), 500);
}
