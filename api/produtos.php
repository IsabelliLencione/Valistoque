<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigirLogin();
$pdo = pdo();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM produto WHERE id = :id');
        $stmt->execute([':id' => (int) $_GET['id']]);
        $produto = $stmt->fetch();
        responderJson((bool) $produto, $produto ? 'Produto encontrado.' : 'Produto não encontrado.', $produto, $produto ? 200 : 404);
    }

    $busca = '%' . limpar($_GET['busca'] ?? '') . '%';
    $categoria = limpar($_GET['categoria'] ?? '');
    $sql = 'SELECT * FROM produto WHERE (nome LIKE :busca OR marca LIKE :busca OR codigo_barras LIKE :busca)';
    $params = [':busca' => $busca];
    if ($categoria !== '') {
        $sql .= ' AND categoria = :categoria';
        $params[':categoria'] = $categoria;
    }
    $sql .= ' ORDER BY nome ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    responderJson(true, 'Produtos carregados com sucesso.', $stmt->fetchAll());
}

$dados = obterDadosRequisicao();

if ($metodo === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO produto (nome, categoria, marca, codigo_barras, unidade_medida, peso_kg) VALUES (:nome, :categoria, :marca, :codigo_barras, :unidade_medida, :peso_kg)');
    $stmt->execute([
        ':nome' => limpar($dados['nome'] ?? ''),
        ':categoria' => limpar($dados['categoria'] ?? ''),
        ':marca' => limpar($dados['marca'] ?? ''),
        ':codigo_barras' => limpar($dados['codigo_barras'] ?? ''),
        ':unidade_medida' => limpar($dados['unidade_medida'] ?? 'un'),
        ':peso_kg' => (float) ($dados['peso_kg'] ?? 0),
    ]);
    $id = (int) $pdo->lastInsertId();
    registrarLog($usuario, 'criar_produto', 'produto', $id);
    responderJson(true, 'Produto cadastrado com sucesso.', ['id' => $id], 201);
}

if ($metodo === 'PUT') {
    $id = (int) ($_GET['id'] ?? $dados['id'] ?? 0);
    if ($id <= 0) {
        responderJson(false, 'ID do produto inválido.', null, 422);
    }

    $stmt = $pdo->prepare('UPDATE produto SET nome = :nome, categoria = :categoria, marca = :marca, codigo_barras = :codigo_barras, unidade_medida = :unidade_medida, peso_kg = :peso_kg WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':nome' => limpar($dados['nome'] ?? ''),
        ':categoria' => limpar($dados['categoria'] ?? ''),
        ':marca' => limpar($dados['marca'] ?? ''),
        ':codigo_barras' => limpar($dados['codigo_barras'] ?? ''),
        ':unidade_medida' => limpar($dados['unidade_medida'] ?? 'un'),
        ':peso_kg' => (float) ($dados['peso_kg'] ?? 0),
    ]);
    registrarLog($usuario, 'atualizar_produto', 'produto', $id);
    responderJson(true, 'Produto atualizado com sucesso.');
}

if ($metodo === 'DELETE') {
    exigirAdmin();
    $id = (int) ($_GET['id'] ?? $dados['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM produto WHERE id = :id');
    $stmt->execute([':id' => $id]);
    registrarLog($usuario, 'excluir_produto', 'produto', $id);
    responderJson(true, 'Produto excluído com sucesso.');
}

responderJson(false, 'Método não permitido.', null, 405);
