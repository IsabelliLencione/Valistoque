<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/alertas_check.php';

$usuario = exigirLogin();
validarMetodo('POST');

$pdo = pdo();
$dados = obterDadosRequisicao();
$idProduto = (int) ($dados['id_produto'] ?? 0);
$lote = limpar($dados['lote'] ?? '');
$quantidade = (int) ($dados['quantidade'] ?? $dados['quantidade_caixas'] ?? 0);
$codigoPrateleira = limpar($dados['codigo_prateleira'] ?? 'PRAT-01');

if ($idProduto <= 0 || $lote === '' || $quantidade <= 0) {
    responderJson(false, 'Dados inválidos para transferência.', null, 422);
}

$stmt = $pdo->prepare('SELECT * FROM estoque WHERE id_produto = :id_produto AND lote = :lote LIMIT 1');
$stmt->execute([':id_produto' => $idProduto, ':lote' => $lote]);
$estoque = $stmt->fetch();

if (!$estoque || (int) $estoque['quantidade_caixas'] < $quantidade) {
    responderJson(false, 'Quantidade insuficiente no estoque central.', null, 409);
}

$pdo->beginTransaction();
try {
    $updEstoque = $pdo->prepare('UPDATE estoque SET quantidade_caixas = quantidade_caixas - :quantidade WHERE id = :id');
    $updEstoque->execute([':quantidade' => $quantidade, ':id' => (int) $estoque['id']]);

    $selPrat = $pdo->prepare('SELECT * FROM prateleira WHERE id_produto = :id_produto AND lote = :lote AND codigo_prateleira = :codigo_prateleira LIMIT 1');
    $selPrat->execute([
        ':id_produto' => $idProduto,
        ':lote' => $lote,
        ':codigo_prateleira' => $codigoPrateleira,
    ]);
    $prat = $selPrat->fetch();

    $unidades = $quantidade * max(1, (int) $estoque['produtos_por_caixa']);
    if ($prat) {
        $updPrat = $pdo->prepare('UPDATE prateleira SET quantidade_caixas = quantidade_caixas + :quantidade, unidades = unidades + :unidades WHERE id = :id');
        $updPrat->execute([':quantidade' => $quantidade, ':unidades' => $unidades, ':id' => (int) $prat['id']]);
        $idPrateleira = (int) $prat['id'];
    } else {
        $insPrat = $pdo->prepare('INSERT INTO prateleira (id_produto, codigo_prateleira, lote, validade, quantidade_caixas, unidades) VALUES (:id_produto, :codigo_prateleira, :lote, :validade, :quantidade_caixas, :unidades)');
        $insPrat->execute([
            ':id_produto' => $idProduto,
            ':codigo_prateleira' => $codigoPrateleira,
            ':lote' => $lote,
            ':validade' => $estoque['validade'],
            ':quantidade_caixas' => $quantidade,
            ':unidades' => $unidades,
        ]);
        $idPrateleira = (int) $pdo->lastInsertId();
    }

    registrarMovimentacao($pdo, [
        'id_produto' => $idProduto,
        'tipo' => 'transferencia_estoque_prateleira',
        'origem' => 'estoque_central',
        'destino' => $codigoPrateleira,
        'lote' => $lote,
        'quantidade_caixas' => $quantidade,
        'quantidade_unidades' => $unidades,
        'observacao' => 'Transferência para prateleira',
    ], $usuario);

    $pdo->commit();
    recalcularAlertas($pdo);
    registrarLog($usuario, 'transferir_estoque_prateleira', 'prateleira', $idPrateleira, ['lote' => $lote]);
    responderJson(true, 'Transferência realizada com sucesso.', ['id_prateleira' => $idPrateleira]);
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(false, 'Erro ao transferir produto.', ['erro' => $e->getMessage()], 500);
}
