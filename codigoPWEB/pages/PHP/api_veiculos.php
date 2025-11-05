<?php
header('Content-Type: application/json');
session_start();
require_once 'conexao.php'; // conexão PDO

$response = ['success' => false, 'message' => 'Requisição inválida.'];

// 1. VERIFICAÇÃO DE SEGURANÇA
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    $response['message'] = 'Acesso não autorizado. Faça login primeiro.';
    echo json_encode($response);
    exit;
}

$id_usuario_logado = $_SESSION['usuario_id'];
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    switch ($metodo) {
        // --- (READ) Buscar veículos ---
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("
                    SELECT 
                        v.ID_VEICULO, v.PLACA, v.ANO_FAB, v.NIVEL_BATERIA,
                        m.ID_MODELO AS modelo_id, m.NOME AS MODELO_NOME,
                        m.CAPACIDADE_BATERIA, m.CONSUMO_MEDIO,
                        ma.ID_MARCA AS marca_id, ma.NOME AS MARCA_NOME,
                        c.ID_CONECTOR AS conector_id, c.NOME AS CONECTOR_NOME,
                        co.ID_COR AS cor_id, co.NOME AS COR_NOME
                    FROM veiculo v
                    JOIN modelo m ON v.MODELO = m.ID_MODELO
                    JOIN marca ma ON m.FK_MARCA = ma.ID_MARCA
                    JOIN conector c ON v.FK_CONECTOR = c.ID_CONECTOR
                    JOIN cor co ON v.FK_COR = co.ID_COR
                    WHERE v.ID_VEICULO = ? AND v.FK_USUARIO_ID_USER = ?
                ");
                $stmt->execute([$_GET['id'], $id_usuario_logado]);
                $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($veiculo) {
                    $response = ['success' => true, 'data' => $veiculo];
                } else {
                    throw new Exception('Veículo não encontrado.');
                }
            } else {
                $stmt = $pdo->prepare("
                    SELECT 
                        v.ID_VEICULO, v.PLACA, v.ANO_FAB, v.NIVEL_BATERIA,
                        m.NOME AS MODELO_NOME, m.CAPACIDADE_BATERIA, m.CONSUMO_MEDIO,
                        ma.NOME AS MARCA_NOME, 
                        c.NOME AS CONECTOR_NOME, co.NOME AS COR_NOME
                    FROM veiculo v
                    JOIN modelo m ON v.MODELO = m.ID_MODELO
                    JOIN marca ma ON m.FK_MARCA = ma.ID_MARCA
                    JOIN conector c ON v.FK_CONECTOR = c.ID_CONECTOR
                    JOIN cor co ON v.FK_COR = co.ID_COR
                    WHERE v.FK_USUARIO_ID_USER = ?
                    ORDER BY ma.NOME, m.NOME
                ");
                $stmt->execute([$id_usuario_logado]);
                $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $veiculos];
            }
            break;

        // --- (CREATE) Adicionar veículo ---
        case 'POST':
            $dados = json_decode(file_get_contents('php://input'));

            if (!isset($dados->modelo_id, $dados->ano_fab, $dados->conector_id, $dados->placa, $dados->cor_id)) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
            }

            // Verifica placa duplicada
            $stmt_check = $pdo->prepare("SELECT ID_VEICULO FROM veiculo WHERE PLACA = ?");
            $stmt_check->execute([$dados->placa]);
            if ($stmt_check->fetch()) {
                throw new Exception('A placa informada já está cadastrada.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO veiculo (MODELO, ANO_FAB, FK_CONECTOR, PLACA, FK_COR, NIVEL_BATERIA, FK_USUARIO_ID_USER)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$dados->modelo_id,
                $dados->ano_fab,
                (int)$dados->conector_id,
                $dados->placa,
                (int)$dados->cor_id,
                isset($dados->nivel_bateria) ? (int)$dados->nivel_bateria : 100,
                $id_usuario_logado
            ]);

            $response = [
                'success' => true,
                'message' => 'Veículo cadastrado!',
                'new_id' => $pdo->lastInsertId()
            ];
            http_response_code(201);
            break;

        // --- (UPDATE) Atualizar veículo ---
        case 'PUT':
            $id_veiculo = $_GET['id'] ?? null;
            $dados = json_decode(file_get_contents('php://input'));

            if (!$id_veiculo || !isset($dados->modelo_id, $dados->ano_fab, $dados->conector_id, $dados->placa, $dados->cor_id, $dados->nivel_bateria)) {
                throw new Exception('Dados incompletos ou ID não informado.');
            }

            // Verifica placa duplicada
            $stmt_check = $pdo->prepare("SELECT ID_VEICULO FROM veiculo WHERE PLACA = ? AND ID_VEICULO != ?");
            $stmt_check->execute([$dados->placa, $id_veiculo]);
            if ($stmt_check->fetch()) {
                throw new Exception('A placa informada já está em uso por outro veículo.');
            }

            // Limitar nível de bateria entre 0 e 100%
            $nivel = (int)$dados->nivel_bateria;
            if ($nivel < 0 || $nivel > 100) {
                throw new Exception('O nível de bateria deve estar entre 0 e 100%.');
            }

            $stmt = $pdo->prepare("
                UPDATE veiculo 
                SET MODELO = ?, ANO_FAB = ?, FK_CONECTOR = ?, PLACA = ?, FK_COR = ?, NIVEL_BATERIA = ?
                WHERE ID_VEICULO = ? AND FK_USUARIO_ID_USER = ?
            ");
            $stmt->execute([
                (int)$dados->modelo_id,
                $dados->ano_fab,
                (int)$dados->conector_id,
                $dados->placa,
                (int)$dados->cor_id,
                $nivel,
                $id_veiculo,
                $id_usuario_logado
            ]);

            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Veículo atualizado com sucesso.'];
            } else {
                throw new Exception('Nenhuma alteração foi feita ou veículo não encontrado.');
            }
            break;

        // --- (DELETE) Excluir veículo ---
        case 'DELETE':
            $id_veiculo = $_GET['id'] ?? null;
            if (!$id_veiculo) {
                throw new Exception('ID do veículo não fornecido para exclusão.');
            }

            $stmt = $pdo->prepare("DELETE FROM veiculo WHERE ID_VEICULO = ? AND FK_USUARIO_ID_USER = ?");
            $stmt->execute([$id_veiculo, $id_usuario_logado]);

            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Veículo excluído com sucesso.'];
            } else {
                throw new Exception('Veículo não encontrado ou sem permissão.');
            }
            break;

        default:
            http_response_code(405);
            $response['message'] = 'Método de requisição não permitido.';
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>