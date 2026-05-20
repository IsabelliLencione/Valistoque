<?php

require_once __DIR__ . '/../includes/config.php';
exigirLogin('adm'); // somente admin
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$perfil = $_GET['perfil'] ?? 'func';
if (!in_array($perfil, ['adm','func'], true)) {
    responderJson(false, null, 'Perfil inválido.', 400);
}
$tabela = ($perfil === 'adm') ? 'adm' : 'func';

try {
    switch ($metodo) {

        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT id, nome, Email, cpf, ativo, created_at
                                       FROM {$tabela} WHERE id = ?");
                $stmt->execute([$id]);
                $u = $stmt->fetch();
                if (!$u) responderJson(false, null, 'Não encontrado.', 404);
                responderJson(true, $u);
            } else {
                $stmt = $pdo->query("SELECT id, nome, Email, cpf, ativo, created_at
                                     FROM {$tabela} ORDER BY nome ASC");
                responderJson(true, $stmt->fetchAll());
            }
            break;

        case 'PUT':
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            $d = json_decode(file_get_contents('php://input'), true);
            $campos = ['nome','Email','ativo'];
            $sets = []; $params = [];
            foreach ($campos as $c) {
                if (array_key_exists($c, $d)) { $sets[] = "$c = ?"; $params[] = $d[$c]; }
            }
            if (isset($d['nova_senha']) && strlen($d['nova_senha']) >= 6) {
                $sets[] = "Senha = ?";
                $params[] = password_hash($d['nova_senha'], PASSWORD_DEFAULT);
            }
            if (empty($sets)) responderJson(false, null, 'Nada para atualizar.', 400);
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE {$tabela} SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($params);
            registrarLog('USUARIO_EDITAR', "{$perfil} ID {$id}");
            responderJson(true, null, 'Usuário atualizado.');
            break;

        case 'DELETE':
            if (!$id) responderJson(false, null, 'ID obrigatório.', 400);
            if ($perfil === 'adm' && $id === (int)$_SESSION['usuario_id']) {
                responderJson(false, null, 'Você não pode excluir sua própria conta.', 400);
            }
            $stmt = $pdo->prepare("DELETE FROM {$tabela} WHERE id = ?");
            $stmt->execute([$id]);
            registrarLog('USUARIO_EXCLUIR', "{$perfil} ID {$id}");
            responderJson(true, null, 'Usuário excluído.');
            break;

        default:
            responderJson(false, null, 'Método não suportado.', 405);
    }
} catch (PDOException $e) {
    error_log("API usuarios: " . $e->getMessage());
    responderJson(false, null, $e->getMessage(), 500);
}