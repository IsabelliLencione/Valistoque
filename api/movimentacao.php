<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/alertas_check.php';

$usuario = exigirLogin();
$pdo = pdo();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $sql = 'SELECT m.*, p.nome AS produto FROM movimentacao m LEFT JOIN produto p ON p.id = m.id_produto WHERE 1=1';
    $params = [];

    if (!empty($_GET['id_produto'])) {
        $sql .= ' AND m.id_produto = :id_produto';
        $params[':id_produto'] = (int) $_GET['id_produto'];
    }
    if (!empty($_GET['tipo'])) {
        $sql .= ' AND m.tipo = :tipo';
        $params[':tipo'] = limpar($_GET['tipo']);
    }
    if (!empty($_GET['inicio'])) {
        $sql .= ' AND DATE(m.created_at) >= :inicio';
        $params[':inicio'] = limpar($_GET['inicio']);
    }
    if (!empty($_GET['fim'])) {
        $sql .= ' AND DATE(m.created_at) <= :fim';
        $params[':fim'] = limpar($_GET['fim']);
    }

    $sql .= ' ORDER BY m.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    responderJson(true, 'Movimentações carregadas com sucesso.', $stmt->fetchAll());
}

if ($metodo === 'POST') {
    $dados = obterDadosRequisicao();
    $idProduto = (int) ($dados['id_produto'] ?? 0);
    $quantidadeSolicitada = (int) ($dados['quantidade_caixas'] ?? $dados['caixas'] ?? 0);
    $destino = limpar($dados['destino'] ?? 'saida');

    if ($idProduto <= 0 || $quantidadeSolicitada <= 0) {
        responderJson(false, 'Dados inválidos para saída.', null, 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM estoque WHERE id_produto = :id_produto AND quantidade_caixas > 0 ORDER BY validade ASC, created_at ASC');
    $stmt->execute([':id_produto' => $idProduto]);
    $lotes = $stmt->fetchAll();

    $totalDisponivel = array_sum(array_map(static fn(array $item): int => (int) $item['quantidade_caixas'], $lotes));
    if ($totalDisponivel < $quantidadeSolicitada) {
        responderJson(false, 'Quantidade insuficiente em estoque para a saída FIFO.', ['disponivel' => $totalDisponivel], 409);
    }

    $restante = $quantidadeSolicitada;
    foreach ($lotes as $lote) {
        if ($restante <= 0) {
            break;
        }

        $consumir = min($restante, (int) $lote['quantidade_caixas']);
        $upd = $pdo->prepare('UPDATE estoque SET quantidade_caixas = quantidade_caixas - :consumir WHERE id = :id');
        $upd->execute([':consumir' => $consumir, ':id' => (int) $lote['id']]);

        registrarMovimentacao($pdo, [
            'id_produto' => $idProduto,
            'tipo' => 'saida_fifo',
            'origem' => 'estoque_central',
            'destino' => $destino,
            'lote' => $lote['lote'],
            'quantidade_caixas' => $consumir,
            'quantidade_unidades' => $consumir * max(1, (int) $lote['produtos_por_caixa']),
            'observacao' => 'Saída com consumo FIFO por validade',
        ], $usuario);

        $restante -= $consumir;
    }

    recalcularAlertas($pdo);
    registrarLog($usuario, 'saida_fifo', 'movimentacao', null, ['id_produto' => $idProduto, 'quantidade' => $quantidadeSolicitada]);
    responderJson(true, 'Saída registrada com sucesso.', ['quantidade_caixas' => $quantidadeSolicitada]);
}

responderJson(false, 'Método não permitido.', null, 405);
