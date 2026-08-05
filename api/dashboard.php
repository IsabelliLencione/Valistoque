<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/alertas_check.php';

exigirLogin();
$pdo = pdo();
recalcularAlertas($pdo);

$kpis = [
    'total_produtos' => (int) $pdo->query('SELECT COUNT(*) FROM produto')->fetchColumn(),
    'caixas_estoque_central' => (int) $pdo->query('SELECT COALESCE(SUM(quantidade_caixas), 0) FROM estoque')->fetchColumn(),
    'caixas_prateleira' => (int) $pdo->query('SELECT COALESCE(SUM(quantidade_caixas), 0) FROM prateleira')->fetchColumn(),
    'alertas_ativos' => (int) $pdo->query('SELECT COUNT(*) FROM alertas WHERE lido = 0')->fetchColumn(),
];

$grafico = $pdo->query("SELECT DATE(created_at) AS dia,
SUM(CASE WHEN tipo LIKE 'entrada%' THEN quantidade_caixas ELSE 0 END) AS entradas,
SUM(CASE WHEN tipo LIKE 'saida%' OR tipo LIKE 'transferencia%' THEN quantidade_caixas ELSE 0 END) AS saidas
FROM movimentacao
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY dia ASC")->fetchAll();

$validade = $pdo->query("SELECT produto, lote, referencia, status FROM alertas WHERE tipo IN ('validade_proxima', 'produto_vencido') ORDER BY status DESC, created_at DESC LIMIT 10")->fetchAll();
$estoqueBaixo = $pdo->query("SELECT produto, lote, codigo_prateleira, referencia, status FROM alertas WHERE tipo IN ('estoque_baixo_central', 'estoque_baixo_prateleira') ORDER BY status DESC, created_at DESC LIMIT 10")->fetchAll();
$ultimas = $pdo->query('SELECT * FROM movimentacao ORDER BY created_at DESC LIMIT 10')->fetchAll();
$maisMovimentados = $pdo->query("SELECT p.nome, SUM(m.quantidade_caixas) AS total_caixas
FROM movimentacao m
INNER JOIN produto p ON p.id = m.id_produto
GROUP BY p.id, p.nome
ORDER BY total_caixas DESC
LIMIT 5")->fetchAll();

responderJson(true, 'Dashboard carregado com sucesso.', [
    'kpis' => $kpis,
    'grafico_30_dias' => $grafico,
    'validade_proxima' => $validade,
    'estoque_baixo' => $estoqueBaixo,
    'ultimas_movimentacoes' => $ultimas,
    'produtos_mais_movimentados' => $maisMovimentados,
]);
