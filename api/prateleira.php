<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/alertas_check.php';

$usuario = exigirLogin();
$pdo = pdo();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $sql = 'SELECT pr.*, p.nome AS produto FROM prateleira pr INNER JOIN produto p ON p.id = pr.id_produto';
    $where = [];
    $params = [];

    if (!empty($_GET['id'])) {
        $where[] = 'pr.id = :id';
        $params[':id'] = (int) $_GET['id'];
    }
    if (!empty($_GET['codigo_prateleira'])) {
        $where[] = 'pr.codigo_prateleira = :codigo_prateleira';
        $params[':codigo_prateleira'] = limpar($_GET['codigo_prateleira']);
    }
    if (!empty($_GET['id_produto'])) {
        $where[] = 'pr.id_produto = :id_produto';
        $params[':id_produto'] = (int) $_GET['id_produto'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY pr.codigo_prateleira ASC, pr.validade ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    responderJson(true, 'Prateleiras carregadas com sucesso.', !empty($_GET['id']) ? ($dados[0] ?? null) : $dados);
}

$dados = obterDadosRequisicao();

if ($metodo === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO prateleira (id_produto, codigo_prateleira, lote, validade, quantidade_caixas, unidades) VALUES (:id_produto, :codigo_prateleira, :lote, :validade, :quantidade_caixas, :unidades)');
    $stmt->execute([
        ':id_produto' => (int) ($dados['id_produto'] ?? 0),
        ':codigo_prateleira' => limpar($dados['codigo_prateleira'] ?? ''),
        ':lote' => limpar($dados['lote'] ?? ''),
        ':validade' => limpar($dados['validade'] ?? ''),
        ':quantidade_caixas' => (int) ($dados['quantidade_caixas'] ?? $dados['caixas'] ?? 0),
        ':unidades' => (int) ($dados['unidades'] ?? 0),
    ]);
    $id = (int) $pdo->lastInsertId();
    recalcularAlertas($pdo);
    registrarLog($usuario, 'criar_prateleira', 'prateleira', $id);
    responderJson(true, 'Item de prateleira cadastrado com sucesso.', ['id' => $id], 201);
}

if ($metodo === 'PUT') {
    $id = (int) ($_GET['id'] ?? $dados['id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE prateleira SET codigo_prateleira = :codigo_prateleira, lote = :lote, validade = :validade, quantidade_caixas = :quantidade_caixas, unidades = :unidades WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':codigo_prateleira' => limpar($dados['codigo_prateleira'] ?? ''),
        ':lote' => limpar($dados['lote'] ?? ''),
        ':validade' => limpar($dados['validade'] ?? ''),
        ':quantidade_caixas' => (int) ($dados['quantidade_caixas'] ?? $dados['caixas'] ?? 0),
        ':unidades' => (int) ($dados['unidades'] ?? 0),
    ]);
    recalcularAlertas($pdo);
    registrarLog($usuario, 'atualizar_prateleira', 'prateleira', $id);
    responderJson(true, 'Item de prateleira atualizado com sucesso.');
}

if ($metodo === 'DELETE') {
    exigirAdmin();
    $id = (int) ($_GET['id'] ?? $dados['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM prateleira WHERE id = :id');
    $stmt->execute([':id' => $id]);
    recalcularAlertas($pdo);
    registrarLog($usuario, 'excluir_prateleira', 'prateleira', $id);
    responderJson(true, 'Item de prateleira removido com sucesso.');
}

responderJson(false, 'Método não permitido.', null, 405);
