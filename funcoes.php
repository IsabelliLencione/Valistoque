<?php

function limpar($valor) {
    if (is_array($valor)) return array_map('limpar', $valor);
    $valor = trim($valor);
    $valor = stripslashes($valor);
    $valor = htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    return $valor;
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarCpf($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) $d += $cpf[$c] * (($t + 1) - $c);
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

function formatarCpf($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' .
           substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

function exigirLogin($perfilNecessario = null) {
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['perfil'])) {
        header("Location: ../frontend/1tela.html?erro=naoautenticado");
        exit;
    }
    
    if (isset($_SESSION['ultima_atividade']) &&
        (time() - $_SESSION['ultima_atividade']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: ../frontend/1tela.html?erro=sessaoexpirada");
        exit;
    }
    $_SESSION['ultima_atividade'] = time();

    
    if ($perfilNecessario && $_SESSION['perfil'] !== $perfilNecessario) {
        header("HTTP/1.1 403 Forbidden");
        die("Acesso negado: você não tem permissão para acessar este recurso.");
    }
}

function ehAdmin() {
    return isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'adm';
}

function responderJson($sucesso, $dados = null, $mensagem = '', $statusHttp = 200) {
    http_response_code($statusHttp);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso'   => $sucesso,
        'mensagem'  => $mensagem,
        'dados'     => $dados,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function setFlash($tipo, $mensagem) {
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function getFlash() {
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function alertaJs($msg, $voltar = true) {
    $msg = addslashes($msg);
    if ($voltar) {
        echo "<script>alert('$msg'); history.back();</script>";
    } else {
        echo "<script>alert('$msg');</script>";
    }
    exit;
}

function redirecionar($url, $msg = null, $tipo = 'info') {
    if ($msg) setFlash($tipo, $msg);
    header("Location: $url");
    exit;
}

function registrarLog($acao, $detalhes = '') {
    global $pdo;
    if (!isset($_SESSION['usuario_id'])) return;
    try {
        $sql = "INSERT INTO log_atividade (usuario_id, perfil, acao, detalhes, ip, data_hora)
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['usuario_id'],
            $_SESSION['perfil'],
            $acao,
            $detalhes,
            $_SERVER['REMOTE_ADDR'] ?? 'desconhecido'
        ]);
    } catch (Exception $e) {
        error_log("Falha ao registrar log: " . $e->getMessage());
    }
}

function gerarCsrf() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


function validarCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}