<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/alertas_check.php';

exigirLogin();
$pdo = pdo();
$tipo = limpar($_GET['tipo'] ?? 'estoque');
$formato = strtolower(limpar($_GET['formato'] ?? 'json'));

$mapa = [
    'estoque' => 'SELECT e.id, p.nome AS produto, e.lote, e.validade, e.quantidade_caixas, e.produtos_por_caixa, e.peso_kg FROM estoque e INNER JOIN produto p ON p.id = e.id_produto ORDER BY p.nome ASC, e.validade ASC',
    'prateleira' => 'SELECT pr.id, p.nome AS produto, pr.codigo_prateleira, pr.lote, pr.validade, pr.quantidade_caixas, pr.unidades FROM prateleira pr INNER JOIN produto p ON p.id = pr.id_produto ORDER BY pr.codigo_prateleira ASC',
    'movimentacao' => 'SELECT id, id_produto, tipo, origem, destino, lote, quantidade_caixas, quantidade_unidades, usuario_nome, usuario_perfil, created_at FROM movimentacao ORDER BY created_at DESC',
    'alertas' => 'SELECT id, tipo, status, produto, lote, codigo_prateleira, referencia, mensagem, lido, created_at FROM alertas ORDER BY created_at DESC',
    'validade' => "SELECT produto, lote, referencia, status, created_at FROM alertas WHERE tipo IN ('validade_proxima', 'produto_vencido') ORDER BY created_at DESC",
];

if (!isset($mapa[$tipo])) {
    responderJson(false, 'Tipo de relatório inválido.', null, 422);
}

if ($tipo === 'alertas' || $tipo === 'validade') {
    recalcularAlertas($pdo);
}

$linhas = $pdo->query($mapa[$tipo])->fetchAll();
if ($formato === 'csv') {
    exportarCsv('relatorio_' . $tipo . '.csv', $linhas);
}

responderJson(true, 'Relatório carregado com sucesso.', $linhas);
