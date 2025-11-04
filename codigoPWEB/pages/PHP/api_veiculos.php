<?php
header('Content-Type: application/json');
session_start();
require_once 'conexao.php'; // Seu script de conexão

$response = ['success' => false, 'message' => 'Requisição inválida.'];

// 1. VERIFICAÇÃO DE SEGURANÇA
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401); // Não autorizado
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
            // Se um ID for passado (ex: /api_veiculos.php?id=5), busca um único veículo para EDIÇÃO
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("
                    SELECT 
                        v.ID_VEICULO, v.PLACA, v.ANO_FAB,
                        m.ID_MODELO as modelo_id,
                        ma.ID_MARCA as marca_id,
                        c.ID_CONECTOR as conector_id,
                        co.ID_COR as cor_id
                    FROM veiculo v
                    JOIN modelo m ON v.MODELO = m.ID_MODELO
                    JOIN marca ma ON m.FK_MARCA = ma.ID_MARCA
                    JOIN conector c ON v.FK_CONECTOR = c.ID_CONECTOR
                    JOIN cor co ON v.FK_COR = co.ID_COR
                    WHERE v.ID_VEICULO = ? AND v.FK_USUARIO_ID_USER = ?
                ");
                $stmt->execute([$_GET['id'], $id_usuario_logado]);
                $veiculo = $stmt->fetch();
                if ($veiculo) {
                    $response = ['success' => true, 'data' => $veiculo];
                } else {
                    throw new Exception('Veículo não encontrado.');
                }
            } else {
                // Se nenhum ID for passado, busca TODOS os veículos do usuário para a LISTA
                $stmt = $pdo->prepare("
                    SELECT v.ID_VEICULO, v.PLACA, v.ANO_FAB, m.NOME as MODELO_NOME, ma.NOME as MARCA_NOME, c.NOME as CONECTOR_NOME, co.NOME as COR_NOME
                    FROM veiculo v
                    JOIN modelo m ON v.MODELO = m.ID_MODELO
                    JOIN marca ma ON m.FK_MARCA = ma.ID_MARCA
                    JOIN conector c ON v.FK_CONECTOR = c.ID_CONECTOR
                    JOIN cor co ON v.FK_COR = co.ID_COR
                    WHERE v.FK_USUARIO_ID_USER = ?
                    ORDER BY ma.NOME, m.NOME
                ");
                $stmt->execute([$id_usuario_logado]);
                $veiculos = $stmt->fetchAll();
                $response = ['success' => true, 'data' => $veiculos];
            }
            break;

        // --- (CREATE) Adicionar um novo veículo ---
        case 'POST':
            $dados = json_decode(file_get_contents('php://input'));
            if (!isset($dados->modelo_id, $dados->ano_fab, $dados->conector_id, $dados->placa, $dados->cor_id)) {
                throw new Exception('Todos os campos são obrigatórios.');
            }
            
            // Verifica se a placa já existe
            $stmt_check = $pdo->prepare("SELECT ID_VEICULO FROM veiculo WHERE PLACA = ?");
            $stmt_check->execute([$dados->placa]);
            if ($stmt_check->fetch()) {
                 throw new Exception('A placa informada já está cadastrada.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO veiculo (MODELO, ANO_FAB, FK_CONECTOR, PLACA, FK_COR, FK_USUARIO_ID_USER) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                (int)$dados->modelo_id, $dados->ano_fab, (int)$dados->conector_id, 
                $dados->placa, (int)$dados->cor_id, $id_usuario_logado
            ]);
            
            $response = ['success' => true, 'message' => 'Veículo cadastrado!', 'new_id' => $pdo->lastInsertId()];
            http_response_code(201);
            break;

        // --- (UPDATE) Atualizar um veículo existente ---
        case 'PUT':
            $id_veiculo = $_GET['id'] ?? null;
            $dados = json_decode(file_get_contents('php://input'));

            if (!$id_veiculo || !isset($dados->modelo_id, $dados->ano_fab, $dados->conector_id, $dados->placa, $dados->cor_id)) {
                throw new Exception('Dados incompletos ou ID do veículo não fornecido.');
            }

            // Verifica se a placa nova já pertence a OUTRO veículo
            $stmt_check = $pdo->prepare("SELECT ID_VEICULO FROM veiculo WHERE PLACA = ? AND ID_VEICULO != ?");
            $stmt_check->execute([$dados->placa, $id_veiculo]);
            if ($stmt_check->fetch()) {
                 throw new Exception('A placa informada já está em uso por outro veículo.');
            }

            $stmt = $pdo->prepare(
                "UPDATE veiculo SET MODELO = ?, ANO_FAB = ?, FK_CONECTOR = ?, PLACA = ?, FK_COR = ?
                 WHERE ID_VEICULO = ? AND FK_USUARIO_ID_USER = ?"
            );
            $stmt->execute([
                (int)$dados->modelo_id, $dados->ano_fab, (int)$dados->conector_id,
                $dados->placa, (int)$dados->cor_id,
                $id_veiculo, $id_usuario_logado
            ]);

            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Veículo atualizado com sucesso!'];
            } else {
                throw new Exception('Nenhuma alteração foi feita ou o veículo não foi encontrado.');
            }
            break;

        // --- (DELETE) Excluir um veículo ---
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
                throw new Exception('Veículo não encontrado ou você não tem permissão para excluí-lo.');
            }
            break;

        default:
            http_response_code(405); // Método não permitido
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