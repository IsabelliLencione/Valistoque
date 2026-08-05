<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';

function recalcularAlertas(PDO $pdo): array
{
    $config = obterConfiguracaoAlertas($pdo);
    $diasAntes = (int) ($config['dias_antes_validade'] ?? 30);
    $minCentral = (int) ($config['caixas_minimas_central'] ?? 10);
    $minPrateleira = (int) ($config['caixas_minimas_prateleira'] ?? 5);

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM alertas');

        $gerados = [];

        $sqlCentral = 'SELECT p.nome AS produto, e.lote, e.validade, e.quantidade_caixas FROM estoque e INNER JOIN produto p ON p.id = e.id_produto';
        foreach ($pdo->query($sqlCentral)->fetchAll() as $item) {
            $diasRestantes = (int) ceil((strtotime((string) $item['validade']) - strtotime(date('Y-m-d'))) / 86400);

            if ($diasRestantes <= $diasAntes) {
                $tipo = $diasRestantes <= 0 ? 'produto_vencido' : 'validade_proxima';
                $status = $diasRestantes <= 0 ? 'critico' : 'aviso';
                $referencia = $diasRestantes <= 0 ? 'Validade vencida' : $diasRestantes . ' dia(s) restantes';
                $mensagem = $diasRestantes <= 0
                    ? 'Produto vencido no estoque central.'
                    : 'Produto próximo da validade no estoque central.';

                $gerados[] = [
                    'tipo' => $tipo,
                    'status' => $status,
                    'produto' => $item['produto'],
                    'lote' => $item['lote'],
                    'codigo_prateleira' => null,
                    'referencia' => $referencia,
                    'mensagem' => $mensagem,
                ];
            }

            if ((int) $item['quantidade_caixas'] <= $minCentral) {
                $gerados[] = [
                    'tipo' => 'estoque_baixo_central',
                    'status' => ((int) $item['quantidade_caixas'] <= max(1, (int) ceil($minCentral / 2))) ? 'critico' : 'aviso',
                    'produto' => $item['produto'],
                    'lote' => $item['lote'],
                    'codigo_prateleira' => null,
                    'referencia' => $item['quantidade_caixas'] . ' caixa(s)',
                    'mensagem' => 'Quantidade abaixo do mínimo no estoque central.',
                ];
            }
        }

        $sqlPrateleira = 'SELECT p.nome AS produto, pr.lote, pr.validade, pr.codigo_prateleira, pr.quantidade_caixas FROM prateleira pr INNER JOIN produto p ON p.id = pr.id_produto';
        foreach ($pdo->query($sqlPrateleira)->fetchAll() as $item) {
            if ((int) $item['quantidade_caixas'] <= $minPrateleira) {
                $gerados[] = [
                    'tipo' => 'estoque_baixo_prateleira',
                    'status' => ((int) $item['quantidade_caixas'] <= max(1, (int) ceil($minPrateleira / 2))) ? 'critico' : 'aviso',
                    'produto' => $item['produto'],
                    'lote' => $item['lote'],
                    'codigo_prateleira' => $item['codigo_prateleira'],
                    'referencia' => $item['quantidade_caixas'] . ' caixa(s)',
                    'mensagem' => 'Quantidade abaixo do mínimo na prateleira.',
                ];
            }
        }

        $stmt = $pdo->prepare('INSERT INTO alertas (tipo, status, produto, lote, codigo_prateleira, referencia, mensagem) VALUES (:tipo, :status, :produto, :lote, :codigo_prateleira, :referencia, :mensagem)');
        foreach ($gerados as $alerta) {
            $stmt->execute([
                ':tipo' => $alerta['tipo'],
                ':status' => $alerta['status'],
                ':produto' => $alerta['produto'],
                ':lote' => $alerta['lote'],
                ':codigo_prateleira' => $alerta['codigo_prateleira'],
                ':referencia' => $alerta['referencia'],
                ':mensagem' => $alerta['mensagem'],
            ]);
        }

        $pdo->commit();
        return $gerados;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
