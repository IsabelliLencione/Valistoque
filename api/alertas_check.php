<?php
/**
 * ============================================================
 *  VALISTOQUE - Helper de Alertas
 *  Funções utilizadas por múltiplas APIs para gerar alertas
 *  de validade próxima, estoque baixo e produtos vencidos.
 * ============================================================
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../includes/config.php';
}

/**
 * Lê (ou inicializa) a configuração de alertas.
 */
function lerConfigAlertas() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM config_alertas ORDER BY id ASC LIMIT 1");
    $cfg  = $stmt->fetch();
    if (!$cfg) {
        $pdo->exec("INSERT INTO config_alertas (dias_val, quant_min_estoq, quant_min_prat)
                    VALUES (" . DIAS_VALIDADE_PADRAO . ", " . ESTOQUE_MIN_PADRAO . ", " . PRATELEIRA_MIN_PADRAO . ")");
        return [
            'dias_val'        => DIAS_VALIDADE_PADRAO,
            'quant_min_estoq' => ESTOQUE_MIN_PADRAO,
            'quant_min_prat'  => PRATELEIRA_MIN_PADRAO,
        ];
    }
    return $cfg;
}

/**
 * Cria um alerta apenas se ainda não existir um igual não lido.
 */
function criarAlertaSeNecessario($tipo, $idProduto, $idEstoque, $idPrateleira, $mensagem) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM alertas
        WHERE tipo_alerta = ?
          AND id_produto    <=> ?
          AND id_estoque    <=> ?
          AND id_prateleira <=> ?
          AND lido = 0
        LIMIT 1");
    $stmt->execute([$tipo, $idProduto, $idEstoque, $idPrateleira]);
    if ($stmt->fetch()) return false;

    $ins = $pdo->prepare("INSERT INTO alertas
        (id_produto, id_estoque, id_prateleira, tipo_alerta, mensagem, data_geracao)
        VALUES (?, ?, ?, ?, ?, NOW())");
    $ins->execute([$idProduto, $idEstoque, $idPrateleira, $tipo, $mensagem]);
    return true;
}

/**
 * Verifica e cria alertas para um produto específico.
 */
function verificarAlertasProduto($idProduto) {
    global $pdo;
    $cfg  = lerConfigAlertas();
    $dias = (int)$cfg['dias_val'];
    $minE = (int)$cfg['quant_min_estoq'];
    $minP = (int)$cfg['quant_min_prat'];

    // --- Estoque central ---
    $stmt = $pdo->prepare("SELECT e.*, p.nome FROM estoque e
                           INNER JOIN produto p ON p.id = e.id_produto
                           WHERE e.id_produto = ?");
    $stmt->execute([$idProduto]);
    foreach ($stmt->fetchAll() as $e) {
        if ((int)$e['quant_prod'] <= $minE && (int)$e['quant_prod'] > 0) {
            criarAlertaSeNecessario(
                'Estoque Baixo Central', $idProduto, $e['id'], null,
                "Estoque baixo: {$e['nome']} (lote {$e['lote']}) — {$e['quant_prod']} un."
            );
        }
        $diff = (strtotime($e['validade']) - time()) / 86400;
        if ($diff <= $dias && $diff >= 0) {
            criarAlertaSeNecessario(
                'Validade Próxima', $idProduto, $e['id'], null,
                "{$e['nome']} (lote {$e['lote']}) vence em " . max(0, (int)$diff) . " dia(s)."
            );
        } elseif ($diff < 0) {
            criarAlertaSeNecessario(
                'Produto Vencido', $idProduto, $e['id'], null,
                "{$e['nome']} (lote {$e['lote']}) está VENCIDO desde " . date('d/m/Y', strtotime($e['validade'])) . "."
            );
        }
    }

    // --- Prateleira ---
    $stmt = $pdo->prepare("SELECT pr.*, p.nome FROM prateleira pr
                           INNER JOIN produto p ON p.id = pr.id_produto
                           WHERE pr.id_produto = ?");
    $stmt->execute([$idProduto]);
    foreach ($stmt->fetchAll() as $pr) {
        if ((int)$pr['quant_item'] <= $minP) {
            criarAlertaSeNecessario(
                'Estoque Baixo Prateleira', $idProduto, null, $pr['id'],
                "Prateleira baixa: {$pr['nome']} — {$pr['quant_item']} un. (mín. {$minP})."
            );
        }
        $diff = (strtotime($pr['validade']) - time()) / 86400;
        if ($diff <= $dias && $diff >= 0) {
            criarAlertaSeNecessario(
                'Validade Próxima', $idProduto, null, $pr['id'],
                "Prateleira: {$pr['nome']} vence em " . max(0, (int)$diff) . " dia(s)."
            );
        }
    }
}

/**
 * Varre todos os produtos para regerar alertas (usado no dashboard).
 */
function varrerTodosAlertas() {
    global $pdo;
    $stmt = $pdo->query("SELECT id FROM produto");
    foreach ($stmt->fetchAll() as $p) {
        verificarAlertasProduto((int)$p['id']);
    }
}
