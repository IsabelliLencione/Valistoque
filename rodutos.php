<?php

require_once __DIR__ . '/../includes/config.php';
exigirLogin();                                      // usuário precisa estar logado
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($metodo) {

        // -------------------- LISTAR / CONSULTAR --------------------
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM produto WHERE id = ?");
                $stmt->execute([$id]);
                $produto = $stmt->fetch();
                if (!$produto) responderJson(false, null, 'Produto não encontrado.', 404);
                responderJson(true, $produto);
            } else {
                $busca     = $_GET['busca']     ?? '';
                $categoria = $_GET['categoria'] ?? '';
                $sql = "SELECT p.*,
                               COALESCE(SUM(e.quant_prod), 0)  AS total_estoque,
                               COALESCE(SUM(pr.quant_item), 0) AS total_prateleira
                        FROM produto p
                        LEFT JOIN estoque    e  ON e.id_produto  = p.id
                        LEFT JOIN prateleira pr ON pr.id_produto = p.id
                        WHERE 1=1";
                $params = [];
                if ($busca !== '') {
                    $sql .= " AND (p.nome LIKE ? OR p.codigo_barras LIKE ? OR p.lote LIKE ?)";
                    $b = "%$busca%";
                    array_push($params, $b, $b, $b);
                }
                if ($categoria !== '') {
                    $sql .= " AND p.categoria = ?";
                    $params[] = $categoria;
                }
                $sql .= " GROUP BY p.id ORDER BY p.nome ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                responderJson(true, $stmt->fetchAll());
            }
            break;

        // -------------------- CRIAR --------------------
        case 'POST':
            $dados = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $obrigatorios = ['nome', 'validade', 'peso', 'lote'];
            foreach ($obrigatorios as $campo) {
                if (empty($dados[$campo])) responderJson(false, null, "Campo '{$campo}' obrigatório.", 400);
            }
            $sql = "INSERT INTO produto
                    (nome, codigo_barras, categoria, validade, peso, lote, preco_custo, preco_venda)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $dados['nome'],
                $dados['codigo_barras'] ?? null,
                $dados['categoria']     ?? null,
                $dados['validade'],
                (float)$dados['peso'],
                $dados['lote'],
                (float)($dados['preco_custo'] ?? 0),
                (float)($dados['preco_venda'] ?? 0),
            ]);
            $novoId = $pdo->lastInsertId();
            registrarLog('PRODUTO_CRIAR', "ID {$novoId} - " . $dados['nome']);
            responderJson(true, ['id' => (int)$novoId], 'Produto cadastrado com sucesso!', 201);
            break;

        // -------------------- ATUALIZAR --------------------
        case 'PUT':
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            $dados = json_decode(file_get_contents('php://input'), true);
            if (!$dados) responderJson(false, null, 'JSON inválido.', 400);

            $campos = ['nome','codigo_barras','categoria','validade','peso','lote','preco_custo','preco_venda'];
            $sets = []; $params = [];
            foreach ($campos as $c) {
                if (array_key_exists($c, $dados)) {
                    $sets[]   = "{$c} = ?";
                    $params[] = $dados[$c];
                }
            }
            if (empty($sets)) responderJson(false, null, 'Nada para atualizar.', 400);
            $params[] = $id;
            $sql = "UPDATE produto SET " . implode(', ', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            registrarLog('PRODUTO_EDITAR', "ID {$id}");
            responderJson(true, null, 'Produto atualizado.');
            break;

        // -------------------- EXCLUIR --------------------
        case 'DELETE':
            if (!ehAdmin()) responderJson(false, null, 'Apenas administradores podem excluir.', 403);
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            $stmt = $pdo->prepare("DELETE FROM produto WHERE id = ?");
            $stmt->execute([$id]);
            registrarLog('PRODUTO_EXCLUIR', "ID {$id}");
            responderJson(true, null, 'Produto removido.');
            break;

        default:
            responderJson(false, null, 'Método não suportado.', 405);
    }
} catch (PDOException $e) {
    error_log("API produtos: " . $e->getMessage());
    responderJson(false, null, 'Erro no servidor: ' . $e->getMessage(), 500);
}