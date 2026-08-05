<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/alertas_check.php';

$usuario = exigirLogin();
$pdo = pdo();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    if (!empty($_GET['varrer'])) {
        $gerados = recalcularAlertas($pdo);
        responderJson(true, 'Alertas recalculados com sucesso.', $gerados);
    }

    if (($_GET['rota'] ?? '') === 'config') {
        responderJson(true, 'Configuração carregada com sucesso.', obterConfiguracaoAlertas($pdo));
    }

    $sql = 'SELECT * FROM alertas';
    if (empty($_GET['todos'])) {
        $sql .= ' WHERE lido = 0';
    }
    $sql .= ' ORDER BY created_at DESC, status DESC';
    $stmt = $pdo->query($sql);
    responderJson(true, 'Alertas carregados com sucesso.', $stmt->fetchAll());
}

if ($metodo === 'POST') {
    $id = (int) ($_GET['id'] ?? 0);
    $acao = limpar($_GET['acao'] ?? '');
    if ($id <= 0 || $acao !== 'ler') {
        responderJson(false, 'Ação inválida.', null, 422);
    }
    $stmt = $pdo->prepare('UPDATE alertas SET lido = 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);
    responderJson(true, 'Alerta marcado como lido.');
}

$dados = obterDadosRequisicao();

if ($metodo === 'PUT') {
    exigirAdmin();
    if (($_GET['rota'] ?? '') !== 'config') {
        responderJson(false, 'Rota inválida para PUT.', null, 422);
    }

    $stmt = $pdo->prepare('UPDATE config_alertas SET dias_antes_validade = :dias, caixas_minimas_central = :central, caixas_minimas_prateleira = :prateleira, intervalo_minutos = :intervalo WHERE id = 1');
    $stmt->execute([
        ':dias' => (int) ($dados['dias_antes_validade'] ?? 30),
        ':central' => (int) ($dados['caixas_minimas_central'] ?? 10),
        ':prateleira' => (int) ($dados['caixas_minimas_prateleira'] ?? 5),
        ':intervalo' => (int) ($dados['intervalo_minutos'] ?? 15),
    ]);

    $gerados = recalcularAlertas($pdo);
    registrarLog($usuario, 'atualizar_config_alerta', 'config_alertas', 1);
    responderJson(true, 'Configurações de alerta atualizadas com sucesso.', ['alertas_gerados' => count($gerados)]);
}

if ($metodo === 'DELETE') {
    exigirAdmin();
    $id = (int) ($_GET['id'] ?? $dados['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM alertas WHERE id = :id');
    $stmt->execute([':id' => $id]);
    registrarLog($usuario, 'excluir_alerta', 'alertas', $id);
    responderJson(true, 'Alerta excluído com sucesso.');
}

responderJson(false, 'Método não permitido.', null, 405);
