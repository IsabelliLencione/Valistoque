<?php
/**
 * ============================================================
 *  VALISTOQUE - API  /api/dashboard.php
 *  Retorna KPIs gerais, gráficos e listas resumidas para a
 *  tela inicial do sistema.
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/alertas_check.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

try {
    varrerTodosAlertas(); // garante alertas atualizados

    // 1. KPIs gerais
    $totalProdutos   = (int)$pdo->query("SELECT COUNT(*) FROM produto")->fetchColumn();
    $totalEstoque    = (int)$pdo->query("SELECT COALESCE(SUM(quant_prod),0) FROM estoque")->fetchColumn();
    $totalPrateleira = (int)$pdo->query("SELECT COALESCE(SUM(quant_item),0) FROM prateleira")->fetchColumn();
    $totalAlertas    = (int)$pdo->query("SELECT COUNT(*) FROM alertas WHERE lido = 0")->fetchColumn();

    // Valor total do estoque (sem JOIN duplicado)
    $valorEstoque = (float)$pdo->query("
        SELECT COALESCE(SUM(total.qt * p.preco_custo),0)
        FROM produto p
        LEFT JOIN (
            SELECT id_produto, SUM(quant_prod) AS qt FROM estoque    GROUP BY id_produto
            UNION ALL
            SELECT id_produto, SUM(quant_item) AS qt FROM prateleira GROUP BY id_produto
        ) total ON total.id_produto = p.id
    ")->fetchColumn();

    // 2. Validades próximas
    $cfg  = lerConfigAlertas();
    $dias = (int)$cfg['dias_val'];
    $valProx = $pdo->prepare("
        SELECT p.nome, e.lote, e.quant_prod, e.validade,
               DATEDIFF(e.validade, CURDATE()) AS dias_restantes
        FROM estoque e
        INNER JOIN produto p ON p.id = e.id_produto
        WHERE DATEDIFF(e.validade, CURDATE()) BETWEEN 0 AND ?
        ORDER BY e.validade ASC LIMIT 5");
    $valProx->execute([$dias]);
    $validade_proxima = $valProx->fetchAll();

    // 3. Estoque baixo
    $minE = (int)$cfg['quant_min_estoq'];
    $estBaixo = $pdo->prepare("
        SELECT p.nome, e.lote, e.quant_prod, e.validade
        FROM estoque e
        INNER JOIN produto p ON p.id = e.id_produto
        WHERE e.quant_prod <= ?
        ORDER BY e.quant_prod ASC LIMIT 5");
    $estBaixo->execute([$minE]);
    $estoque_baixo = $estBaixo->fetchAll();

    // 4. Últimas movimentações
    $movs = $pdo->query("
        SELECT m.*, p.nome AS produto_nome
        FROM movimentacao m
        LEFT JOIN produto p ON p.id = m.id_produto
        ORDER BY m.data_hora DESC LIMIT 10")->fetchAll();

    // 5. Gráfico — entradas x saídas (últimos 30 dias)
    $grafico = $pdo->query("
        SELECT DATE(data_hora) AS dia,
               SUM(CASE WHEN tipo = 'entrada' THEN quantidade ELSE 0 END) AS entradas,
               SUM(CASE WHEN tipo = 'saida'   THEN quantidade ELSE 0 END) AS saidas
        FROM movimentacao
        WHERE data_hora >= (CURDATE() - INTERVAL 30 DAY)
        GROUP BY DATE(data_hora)
        ORDER BY dia ASC")->fetchAll();

    // 6. Resumo de alertas por tipo
    $alertResumo = $pdo->query("
        SELECT tipo_alerta, COUNT(*) AS qtd
        FROM alertas WHERE lido = 0
        GROUP BY tipo_alerta")->fetchAll();

    // 7. Produtos mais movimentados
    $maisMov = $pdo->query("
        SELECT p.nome, SUM(m.quantidade) AS total
        FROM movimentacao m
        INNER JOIN produto p ON p.id = m.id_produto
        WHERE m.data_hora >= (CURDATE() - INTERVAL 30 DAY)
        GROUP BY m.id_produto
        ORDER BY total DESC LIMIT 5")->fetchAll();

    responderJson(true, [
        'kpis' => [
            'total_produtos'   => $totalProdutos,
            'total_estoque'    => $totalEstoque,
            'total_prateleira' => $totalPrateleira,
            'total_alertas'    => $totalAlertas,
            'valor_estoque'    => round($valorEstoque, 2),
        ],
        'validade_proxima'      => $validade_proxima,
        'estoque_baixo'         => $estoque_baixo,
        'ultimas_movimentacoes' => $movs,
        'grafico_30dias'        => $grafico,
        'resumo_alertas'        => $alertResumo,
        'mais_movimentados'     => $maisMov,
        'usuario'               => [
            'nome'   => $_SESSION['usuario_nome']  ?? '',
            'perfil' => $_SESSION['perfil']        ?? '',
            'email'  => $_SESSION['usuario_email'] ?? '',
        ],
    ]);

} catch (PDOException $e) {
    error_log("API dashboard: " . $e->getMessage());
    responderJson(false, null, $e->getMessage(), 500);
}
