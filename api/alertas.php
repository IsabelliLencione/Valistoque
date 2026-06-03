<?php
/**
 * ============================================================
 *  VALISTOQUE - API REST  /api/alertas.php
 *  GET    -> Lista alertas (?todos=1 para incluir lidos)
 *            ?varrer=1 força regeração; ?rota=config retorna config
 *  POST   -> ?id=N&acao=ler  marca alerta como lido
 *  PUT    -> ?rota=config    atualiza parâmetros (apenas admin)
 *  DELETE -> ?id=N           exclui alerta (apenas admin)
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/alertas_check.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$acao   = $_GET['acao'] ?? null;

try {
    if ($metodo === 'GET' && !empty($_GET['varrer'])) {
        varrerTodosAlertas();
    }

    if ($metodo === 'PUT' && ($_GET['rota'] ?? '') === 'config') {
        if (!ehAdmin()) responderJson(false, null, 'Apenas admin.', 403);
        $d = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE config_alertas
                               SET dias_val = ?, quant_min_estoq = ?, quant_min_prat = ?
                               WHERE id = (SELECT id FROM (SELECT id FROM config_alertas ORDER BY id LIMIT 1) t)");
        $stmt->execute([
            (int)($d['dias_val']        ?? DIAS_VALIDADE_PADRAO),
            (int)($d['quant_min_estoq'] ?? ESTOQUE_MIN_PADRAO),
            (int)($d['quant_min_prat']  ?? PRATELEIRA_MIN_PADRAO),
        ]);
        registrarLog('CONFIG_ALERTAS_ATUALIZAR', json_encode($d));
        responderJson(true, lerConfigAlertas(), 'Configuração atualizada.');
    }

    switch ($metodo) {

        case 'GET':
            if (($_GET['rota'] ?? '') === 'config') {
                responderJson(true, lerConfigAlertas());
            }

            $todos = !empty($_GET['todos']);
            $sql = "SELECT a.*, p.nome AS produto_nome
                    FROM alertas a
                    LEFT JOIN produto p ON p.id = a.id_produto
                    WHERE 1=1";
            if (!$todos) $sql .= " AND a.lido = 0";
            $sql .= " ORDER BY a.data_geracao DESC LIMIT 200";
            $stmt = $pdo->query($sql);
            $lista = $stmt->fetchAll();

            $resumo = [
                'total'   => count($lista),
                'validade'=> 0, 'vencido' => 0,
                'estoque' => 0, 'prateleira'=> 0
            ];
            foreach ($lista as $a) {
                switch ($a['tipo_alerta']) {
                    case 'Validade Próxima':         $resumo['validade']++;   break;
                    case 'Produto Vencido':          $resumo['vencido']++;    break;
                    case 'Estoque Baixo Central':    $resumo['estoque']++;    break;
                    case 'Estoque Baixo Prateleira': $resumo['prateleira']++; break;
                }
            }
            responderJson(true, ['resumo' => $resumo, 'lista' => $lista]);
            break;

        case 'POST':
            if (!$id || $acao !== 'ler') responderJson(false, null, 'Parâmetros inválidos.', 400);
            $stmt = $pdo->prepare("UPDATE alertas SET lido = 1 WHERE id = ?");
            $stmt->execute([$id]);
            responderJson(true, null, 'Alerta marcado como lido.');
            break;

        case 'DELETE':
            if (!ehAdmin()) responderJson(false, null, 'Apenas admin.', 403);
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            $stmt = $pdo->prepare("DELETE FROM alertas WHERE id = ?");
            $stmt->execute([$id]);
            registrarLog('ALERTA_EXCLUIR', "ID {$id}");
            responderJson(true, null, 'Alerta excluído.');
            break;

        default:
            responderJson(false, null, 'Método não suportado.', 405);
    }
} catch (PDOException $e) {
    error_log("API alertas: " . $e->getMessage());
    responderJson(false, null, $e->getMessage(), 500);
}
