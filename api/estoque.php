<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/alertas_check.php';

$usuario = exigirLogin();
$pdo = pdo();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $sql = 'SELECT e.*, p.nome AS produto, p.categoria, p.marca FROM estoque e INNER JOIN produto p ON p.id = e.id_produto';
    $where = [];
    $params = [];

    if (!empty($_GET['id'])) {
        $where[] = 'e.id = :id';
        $params[':id'] = (int) $_GET['id'];
    }
    if (!empty($_GET['id_produto'])) {
        $where[] = 'e.id_produto = :id_produto';
        $params[':id_produto'] = (int) $_GET['id_produto'];
    }
    if (!empty($_GET['lote'])) {
        $where[] = 'e.lote = :lote';
        $params[':lote'] = limpar($_GET['lote']);
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY e.validade ASC, p.nome ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    responderJson(true, 'Estoque carregado com sucesso.', !empty($_GET['id']) ? ($dados[0] ?? null) : $dados);
}

$dados = obterDadosRequisicao();

if ($metodo === 'POST') {
    $idProduto = (int) ($dados['id_produto'] ?? 0);
    $lote = limpar($dados['lote'] ?? '');
    $validade = limpar($dados['validade'] ?? '');
    $caixas = (int) ($dados['quantidade_caixas'] ?? $dados['caixas'] ?? 0);
    $produtosPorCaixa = (int) ($dados['produtos_por_caixa'] ?? $dados['proporcao'] ?? 1);
    $peso = (float) ($dados['peso_kg'] ?? 0);

    if ($idProduto <= 0 || $lote === '' || $validade === '' || $caixas <= 0) {
        responderJson(false, 'Dados inválidos para entrada de estoque.', null, 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM estoque WHERE id_produto = :id_produto AND lote = :lote LIMIT 1');
    $stmt->execute([':id_produto' => $idProduto, ':lote' => $lote]);
    $existente = $stmt->fetch();

    if ($existente) {
        $upd = $pdo->prepare('UPDATE estoque SET quantidade_caixas = quantidade_caixas + :caixas, validade = :validade, produtos_por_caixa = :produtos_por_caixa, peso_kg = :peso_kg WHERE id = :id');
        $upd->execute([
            ':caixas' => $caixas,
            ':validade' => $validade,
            ':produtos_por_caixa' => max(1, $produtosPorCaixa),
            ':peso_kg' => $peso,
            ':id' => (int) $existente['id'],
        ]);
        $idEstoque = (int) $existente['id'];
    } else {
        $ins = $pdo->prepare('INSERT INTO estoque (id_produto, lote, validade, quantidade_caixas, produtos_por_caixa, peso_kg, created_by) VALUES (:id_produto, :lote, :validade, :quantidade_caixas, :produtos_por_caixa, :peso_kg, :created_by)');
        $ins->execute([
            ':id_produto' => $idProduto,
            ':lote' => $lote,
            ':validade' => $validade,
            ':quantidade_caixas' => $caixas,
            ':produtos_por_caixa' => max(1, $produtosPorCaixa),
            ':peso_kg' => $peso,
            ':created_by' => $usuario['id'] ?? null,
        ]);
        $idEstoque = (int) $pdo->lastInsertId();
    }

    registrarMovimentacao($pdo, [
        'id_produto' => $idProduto,
        'tipo' => 'entrada_estoque',
        'origem' => 'fornecedor',
        'destino' => 'estoque_central',
        'lote' => $lote,
        'quantidade_caixas' => $caixas,
        'quantidade_unidades' => $caixas * max(1, $produtosPorCaixa),
        'observacao' => 'Entrada no estoque central',
    ], $usuario);

    recalcularAlertas($pdo);
    registrarLog($usuario, 'entrada_estoque', 'estoque', $idEstoque, ['lote' => $lote]);
    responderJson(true, 'Entrada registrada com sucesso.', ['id' => $idEstoque], 201);
}

if ($metodo === 'PUT') {
    $id = (int) ($_GET['id'] ?? $dados['id'] ?? 0);
    if ($id <= 0) {
        responderJson(false, 'ID do estoque inválido.', null, 422);
    }

    $stmt = $pdo->prepare('UPDATE estoque SET lote = :lote, validade = :validade, quantidade_caixas = :quantidade_caixas, produtos_por_caixa = :produtos_por_caixa, peso_kg = :peso_kg WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':lote' => limpar($dados['lote'] ?? ''),
        ':validade' => limpar($dados['validade'] ?? ''),
        ':quantidade_caixas' => (int) ($dados['quantidade_caixas'] ?? $dados['caixas'] ?? 0),
        ':produtos_por_caixa' => max(1, (int) ($dados['produtos_por_caixa'] ?? $dados['proporcao'] ?? 1)),
        ':peso_kg' => (float) ($dados['peso_kg'] ?? 0),
    ]);
    recalcularAlertas($pdo);
    registrarLog($usuario, 'atualizar_estoque', 'estoque', $id);
    responderJson(true, 'Registro de estoque atualizado com sucesso.');
}

if ($metodo === 'DELETE') {
    exigirAdmin();
    $id = (int) ($_GET['id'] ?? $dados['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM estoque WHERE id = :id');
    $stmt->execute([':id' => $id]);
    recalcularAlertas($pdo);
    registrarLog($usuario, 'excluir_estoque', 'estoque', $id);
    responderJson(true, 'Registro de estoque removido com sucesso.');
}

responderJson(false, 'Método não permitido.', null, 405);
