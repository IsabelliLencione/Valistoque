<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';

$admin = exigirAdmin();
$pdo = pdo();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$perfil = perfilNormalizado((string) ($_GET['perfil'] ?? ''));
$tabelas = $perfil ? [tabelaPorPerfil($perfil)] : ['adm', 'func'];

if ($metodo === 'GET') {
    $saida = [];
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query("SELECT id, nome, email, cpf, ativo, created_at, updated_at FROM {$tabela} ORDER BY nome ASC");
        $saida[perfilPorTabela($tabela)] = $stmt->fetchAll();
    }
    responderJson(true, 'Usuários carregados com sucesso.', $saida);
}

$dados = obterDadosRequisicao();
$id = (int) ($dados['id'] ?? $_GET['id'] ?? 0);
$perfilDestino = perfilNormalizado((string) ($dados['perfil'] ?? $_GET['perfil'] ?? $perfil));
$tabela = tabelaPorPerfil($perfilDestino);

if ($id <= 0 || $tabela === '') {
    responderJson(false, 'Informe id e perfil válidos.', null, 422);
}

if ($metodo === 'PUT') {
    $nome = limpar($dados['nome'] ?? '');
    $email = strtolower(limpar($dados['email'] ?? ''));
    $cpf = formatarCpf((string) ($dados['cpf'] ?? ''));
    $ativo = array_key_exists('ativo', $dados) ? (int) ((bool) $dados['ativo']) : 1;

    if ($nome === '' || !validarEmail($email) || !validarCPF($cpf)) {
        responderJson(false, 'Dados inválidos para atualização.', null, 422);
    }

    if (emailOuCpfEmUso($pdo, $email, $cpf, $id, $tabela)) {
        responderJson(false, 'Email ou CPF já cadastrados.', null, 409);
    }

    $sql = "UPDATE {$tabela} SET nome = :nome, email = :email, cpf = :cpf, ativo = :ativo";
    $params = [':nome' => $nome, ':email' => $email, ':cpf' => $cpf, ':ativo' => $ativo, ':id' => $id];

    if (!empty($dados['senha'])) {
        $sql .= ', senha = :senha, password_updated_at = NOW()';
        $params[':senha'] = password_hash((string) $dados['senha'], PASSWORD_BCRYPT);
    }

    $sql .= ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    registrarLog($admin, 'atualizar_usuario', 'usuario', $id, ['perfil' => $perfilDestino]);
    responderJson(true, 'Usuário atualizado com sucesso.');
}

if ($metodo === 'DELETE') {
    if (($admin['id'] ?? 0) === $id && $perfilDestino === 'administrador') {
        responderJson(false, 'Você não pode excluir o próprio usuário logado.', null, 409);
    }

    $stmt = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
    $stmt->execute([':id' => $id]);

    registrarLog($admin, 'excluir_usuario', 'usuario', $id, ['perfil' => $perfilDestino]);
    responderJson(true, 'Usuário excluído com sucesso.');
}

responderJson(false, 'Método não permitido.', null, 405);
