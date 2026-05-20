<?php

require_once __DIR__ . '/../includes/config.php';
exigirLogin();

$tipo    = $_GET['tipo']    ?? 'estoque';
$formato = $_GET['formato'] ?? 'json';
$inicio  = $_GET['inicio']  ?? null;
$fim     = $_GET['fim']     ?? null;

try {
    $titulo = ''; $dados = []; $cols = [];

    switch ($tipo) {
        case 'estoque':
            $titulo = 'Relatório de Estoque Central';
            $cols   = ['produto','lote','quantidade','validade','data_entrada'];
            $dados  = $pdo->query("
                SELECT p.nome AS produto, e.lote, e.quant_prod AS quantidade,
                       e.validade, e.data_entrada
                FROM estoque e
                INNER JOIN produto p ON p.id = e.id_produto
                ORDER BY p.nome, e.validade")->fetchAll();
            break;

        case 'prateleira':
            $titulo = 'Relatório de Prateleiras';
            $cols   = ['produto','codigo','quantidade','peso','validade'];
            $dados  = $pdo->query("
                SELECT p.nome AS produto, pr.codigo_prateleira AS codigo,
                       pr.quant_item AS quantidade, pr.peso_prat AS peso, pr.validade
                FROM prateleira pr
                INNER JOIN produto p ON p.id = pr.id_produto
                ORDER BY p.nome")->fetchAll();
            break;

        case 'movimentacao':
            $titulo = 'Histórico de Movimentação';
            $cols   = ['data','produto','tipo','origem','destino','quantidade','perfil'];
            $sql = "SELECT m.data_hora AS data, p.nome AS produto, m.tipo,
                           m.origem, m.destino, m.quantidade, m.perfil_usuario AS perfil
                    FROM movimentacao m
                    INNER JOIN produto p ON p.id = m.id_produto
                    WHERE 1=1";
            $params = [];
            if ($inicio) { $sql .= " AND m.data_hora >= ?"; $params[] = $inicio . ' 00:00:00'; }
            if ($fim)    { $sql .= " AND m.data_hora <= ?"; $params[] = $fim    . ' 23:59:59'; }
            $sql .= " ORDER BY m.data_hora DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll();
            break;

        case 'alertas':
            $titulo = 'Alertas Ativos';
            $cols   = ['tipo','produto','mensagem','data'];
            $dados  = $pdo->query("
                SELECT a.tipo_alerta AS tipo, p.nome AS produto,
                       a.mensagem, a.data_geracao AS data
                FROM alertas a
                LEFT JOIN produto p ON p.id = a.id_produto
                WHERE a.lido = 0
                ORDER BY a.data_geracao DESC")->fetchAll();
            break;

        case 'validade':
            $titulo = 'Produtos Próximos da Validade';
            $cols   = ['produto','lote','quantidade','validade','dias_restantes'];
            $dados  = $pdo->query("
                SELECT p.nome AS produto, e.lote, e.quant_prod AS quantidade,
                       e.validade, DATEDIFF(e.validade, CURDATE()) AS dias_restantes
                FROM estoque e
                INNER JOIN produto p ON p.id = e.id_produto
                WHERE DATEDIFF(e.validade, CURDATE()) <= 30
                ORDER BY e.validade ASC")->fetchAll();
            break;

        default:
            responderJson(false, null, 'Tipo de relatório inválido.', 400);
    }

    if ($formato === 'csv') {
        $nomeArq = 'relatorio_' . $tipo . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nomeArq}\"");
        $out = fopen('php://output', 'w');
        // BOM UTF-8 para Excel
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, $cols, ';');
        foreach ($dados as $linha) fputcsv($out, $linha, ';');
        fclose($out);
        exit;
    }

    responderJson(true, [
        'titulo'  => $titulo,
        'colunas' => $cols,
        'linhas'  => $dados,
        'total'   => count($dados),
        'gerado_em' => date('d/m/Y H:i:s'),
    ]);

} catch (PDOException $e) {
    error_log("Relatórios: " . $e->getMessage());
    responderJson(false, null, $e->getMessage(), 500);
}