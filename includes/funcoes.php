<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';

function responderJson(bool $sucesso, string $mensagem, mixed $dados = null, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function limpar(mixed $valor): string
{
    return trim((string) ($valor ?? ''));
}

function validarMetodo(array|string $permitidos): void
{
    $permitidos = (array) $permitidos;
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $permitidos, true)) {
        responderJson(false, 'Método não permitido.', null, 405);
    }
}

function obterDadosRequisicao(): array
{
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

    if ($metodo === 'GET') {
        return $_GET;
    }

    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input') ?: '[]';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    if ($metodo === 'POST') {
        return $_POST;
    }

    parse_str(file_get_contents('php://input') ?: '', $data);
    return is_array($data) ? $data : [];
}

function gerarCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrf(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function validarEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function normalizarCpf(string $cpf): string
{
    return preg_replace('/\D/', '', $cpf) ?? '';
}

function formatarCpf(string $cpf): string
{
    $cpf = normalizarCpf($cpf);
    if (strlen($cpf) !== 11) {
        return $cpf;
    }
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf) ?? $cpf;
}

function validarCPF(string $cpf): bool
{
    $cpf = normalizarCpf($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

function ipCliente(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $chave) {
        if (!empty($_SERVER[$chave])) {
            return trim(explode(',', (string) $_SERVER[$chave])[0]);
        }
    }
    return '0.0.0.0';
}

function perfilNormalizado(string $perfil): string
{
    $perfil = strtolower(limpar($perfil));
    return match ($perfil) {
        'administrador', 'admin', 'adm' => 'administrador',
        'funcionario', 'funcionário', 'func' => 'funcionario',
        default => '',
    };
}

function tabelaPorPerfil(string $perfil): string
{
    return match (perfilNormalizado($perfil)) {
        'administrador' => 'adm',
        'funcionario' => 'func',
        default => '',
    };
}

function perfilPorTabela(string $tabela): string
{
    return $tabela === 'adm' ? 'administrador' : 'funcionario';
}

function usuarioAtual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function exigirLogin(array $perfisPermitidos = ['administrador', 'funcionario']): array
{
    $usuario = usuarioAtual();
    if (!$usuario) {
        responderJson(false, 'Sessão expirada ou não autenticada.', null, 401);
    }

    if ($perfisPermitidos && !in_array($usuario['perfil'], $perfisPermitidos, true)) {
        responderJson(false, 'Acesso negado para este perfil.', null, 403);
    }

    return $usuario;
}

function exigirAdmin(): array
{
    return exigirLogin(['administrador']);
}

function iniciarSessaoUsuario(array $usuario, string $perfil, string $origemTabela): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id' => (int) $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'perfil' => perfilNormalizado($perfil),
        'origem_tabela' => $origemTabela,
    ];
    $_SESSION['ultimo_acesso'] = time();
}

function encerrarSessaoUsuario(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
    }
    session_destroy();
}

function registrarLog(?array $usuario, string $acao, string $entidade, string|int|null $entidadeId = null, array $detalhes = []): void
{
    try {
        $stmt = pdo()->prepare('INSERT INTO log_atividade (usuario_id, usuario_nome, usuario_perfil, acao, entidade, entidade_id, detalhes, ip) VALUES (:usuario_id, :usuario_nome, :usuario_perfil, :acao, :entidade, :entidade_id, :detalhes, :ip)');
        $stmt->execute([
            ':usuario_id' => $usuario['id'] ?? null,
            ':usuario_nome' => $usuario['nome'] ?? null,
            ':usuario_perfil' => $usuario['perfil'] ?? null,
            ':acao' => $acao,
            ':entidade' => $entidade,
            ':entidade_id' => (string) ($entidadeId ?? ''),
            ':detalhes' => json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip' => ipCliente(),
        ]);
    } catch (Throwable $e) {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        @file_put_contents($logDir . '/erros.log', '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}

function loginBloqueado(PDO $pdo, string $email, string $ip): ?string
{
    $stmt = $pdo->prepare('SELECT bloqueado_ate FROM login_tentativas WHERE email = :email AND ip = :ip LIMIT 1');
    $stmt->execute([':email' => $email, ':ip' => $ip]);
    $linha = $stmt->fetch();

    if (!$linha || empty($linha['bloqueado_ate'])) {
        return null;
    }

    if (strtotime((string) $linha['bloqueado_ate']) > time()) {
        return (string) $linha['bloqueado_ate'];
    }

    return null;
}

function registrarTentativaFalha(PDO $pdo, string $email, string $ip): void
{
    $stmt = $pdo->prepare('SELECT id, tentativas FROM login_tentativas WHERE email = :email AND ip = :ip LIMIT 1');
    $stmt->execute([':email' => $email, ':ip' => $ip]);
    $linha = $stmt->fetch();

    $tentativas = ((int) ($linha['tentativas'] ?? 0)) + 1;
    $bloqueadoAte = $tentativas >= LOGIN_MAX_ATTEMPTS ? date('Y-m-d H:i:s', strtotime('+' . LOGIN_BLOCK_MINUTES . ' minutes')) : null;

    if ($linha) {
        $upd = $pdo->prepare('UPDATE login_tentativas SET tentativas = :tentativas, bloqueado_ate = :bloqueado_ate, updated_at = NOW() WHERE id = :id');
        $upd->execute([
            ':tentativas' => $tentativas,
            ':bloqueado_ate' => $bloqueadoAte,
            ':id' => $linha['id'],
        ]);
        return;
    }

    $ins = $pdo->prepare('INSERT INTO login_tentativas (email, ip, tentativas, bloqueado_ate) VALUES (:email, :ip, :tentativas, :bloqueado_ate)');
    $ins->execute([
        ':email' => $email,
        ':ip' => $ip,
        ':tentativas' => $tentativas,
        ':bloqueado_ate' => $bloqueadoAte,
    ]);
}

function limparTentativasLogin(PDO $pdo, string $email, string $ip): void
{
    $stmt = $pdo->prepare('DELETE FROM login_tentativas WHERE email = :email AND ip = :ip');
    $stmt->execute([':email' => $email, ':ip' => $ip]);
}

function buscarUsuarioPorEmail(PDO $pdo, string $email, string $tabela): ?array
{
    if (!in_array($tabela, ['adm', 'func'], true)) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, nome, email, cpf, senha, ativo FROM {$tabela} WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    return $usuario ?: null;
}

function emailOuCpfEmUso(PDO $pdo, string $email, string $cpf, ?int $ignorarId = null, ?string $ignorarTabela = null): bool
{
    foreach (['adm', 'func'] as $tabela) {
        $sql = "SELECT id FROM {$tabela} WHERE (email = :email OR cpf = :cpf)";
        $params = [':email' => $email, ':cpf' => $cpf];

        if ($ignorarId && $ignorarTabela === $tabela) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignorarId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            return true;
        }
    }

    return false;
}

function registrarMovimentacao(PDO $pdo, array $dados, array $usuario): void
{
    $stmt = $pdo->prepare('INSERT INTO movimentacao (id_produto, tipo, origem, destino, lote, quantidade_caixas, quantidade_unidades, observacao, usuario_id, usuario_nome, usuario_perfil) VALUES (:id_produto, :tipo, :origem, :destino, :lote, :quantidade_caixas, :quantidade_unidades, :observacao, :usuario_id, :usuario_nome, :usuario_perfil)');
    $stmt->execute([
        ':id_produto' => $dados['id_produto'] ?? null,
        ':tipo' => $dados['tipo'] ?? '',
        ':origem' => $dados['origem'] ?? null,
        ':destino' => $dados['destino'] ?? null,
        ':lote' => $dados['lote'] ?? null,
        ':quantidade_caixas' => (int) ($dados['quantidade_caixas'] ?? 0),
        ':quantidade_unidades' => (int) ($dados['quantidade_unidades'] ?? 0),
        ':observacao' => $dados['observacao'] ?? null,
        ':usuario_id' => $usuario['id'] ?? null,
        ':usuario_nome' => $usuario['nome'] ?? null,
        ':usuario_perfil' => $usuario['perfil'] ?? null,
    ]);
}

function obterConfiguracaoAlertas(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM config_alertas ORDER BY id ASC LIMIT 1');
    $config = $stmt->fetch();
    if ($config) {
        return $config;
    }

    $pdo->exec('INSERT INTO config_alertas (dias_antes_validade, caixas_minimas_central, caixas_minimas_prateleira, intervalo_minutos) VALUES (30, 10, 5, 15)');
    $stmt = $pdo->query('SELECT * FROM config_alertas ORDER BY id ASC LIMIT 1');
    return $stmt->fetch() ?: [
        'dias_antes_validade' => 30,
        'caixas_minimas_central' => 10,
        'caixas_minimas_prateleira' => 5,
        'intervalo_minutos' => 15,
    ];
}

function exportarCsv(string $nomeArquivo, array $linhas): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");

    if (!empty($linhas)) {
        fputcsv($saida, array_keys($linhas[0]), ';');
        foreach ($linhas as $linha) {
            fputcsv($saida, $linha, ';');
        }
    }

    fclose($saida);
    exit;
}
