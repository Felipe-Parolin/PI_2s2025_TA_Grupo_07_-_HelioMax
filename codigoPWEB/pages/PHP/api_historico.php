<?php
header('Content-Type: application/json');
session_start();
require_once 'conexao.php';

$response = ['success' => false, 'message' => 'Requisição inválida.'];

// Verificação de segurança
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    $response['message'] = 'Acesso não autorizado. Faça login primeiro.';
    echo json_encode($response);
    exit;
}

$id_usuario_logado = $_SESSION['usuario_id'];
$metodo = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($metodo) {
        // Listar histórico
        case 'GET':
            if ($action === 'list') {
                $stmt = $pdo->prepare("
                    SELECT 
                        h.*,
                        CONCAT(ma.NOME, ' ', m.NOME) as VEICULO_NOME
                    FROM historico_rota h
                    LEFT JOIN veiculo v ON h.FK_VEICULO = v.ID_VEICULO
                    LEFT JOIN modelo m ON v.MODELO = m.ID_MODELO
                    LEFT JOIN marca ma ON m.FK_MARCA = ma.ID_MARCA
                    WHERE h.FK_USUARIO = ?
                    ORDER BY h.DATA_SIMULACAO DESC
                ");
                $stmt->execute([$id_usuario_logado]);
                $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = ['success' => true, 'routes' => $rotas];
                
            } elseif ($action === 'details' && isset($_GET['id'])) {
                $id_historico = (int)$_GET['id'];
                
                $stmt = $pdo->prepare("
                    SELECT 
                        h.*,
                        CONCAT(ma.NOME, ' ', m.NOME) as VEICULO_NOME
                    FROM historico_rota h
                    LEFT JOIN veiculo v ON h.FK_VEICULO = v.ID_VEICULO
                    LEFT JOIN modelo m ON v.MODELO = m.ID_MODELO
                    LEFT JOIN marca ma ON m.FK_MARCA = ma.ID_MARCA
                    WHERE h.ID_HISTORICO = ? AND h.FK_USUARIO = ?
                ");
                $stmt->execute([$id_historico, $id_usuario_logado]);
                $rota = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($rota) {
                    $response = ['success' => true, 'route' => $rota];
                } else {
                    throw new Exception('Rota não encontrada.');
                }
            } else {
                throw new Exception('Ação inválida.');
            }
            break;

        // Adicionar ao histórico
        case 'POST':
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($dados['origem_lat'], $dados['origem_lng'], $dados['origem_endereco'],
                       $dados['destino_lat'], $dados['destino_lng'], $dados['destino_endereco'],
                       $dados['distancia_total_km'], $dados['tempo_conducao_min'])) {
                throw new Exception('Dados incompletos.');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO historico_rota (
                    FK_USUARIO, FK_VEICULO, ORIGEM_LAT, ORIGEM_LNG, ORIGEM_ENDERECO,
                    DESTINO_LAT, DESTINO_LNG, DESTINO_ENDERECO, DISTANCIA_TOTAL_KM,
                    TEMPO_CONDUCAO_MIN, TEMPO_CARREGAMENTO_MIN, PARADAS_TOTAIS,
                    ENERGIA_CONSUMIDA_KWH, CUSTO_TOTAL, CARGA_FINAL_PCT,
                    MODO_OTIMISTA, DADOS_PARADAS, POLYLINE
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $id_usuario_logado,
                $dados['veiculo_id'] ?? null,
                $dados['origem_lat'],
                $dados['origem_lng'],
                $dados['origem_endereco'],
                $dados['destino_lat'],
                $dados['destino_lng'],
                $dados['destino_endereco'],
                $dados['distancia_total_km'],
                $dados['tempo_conducao_min'],
                $dados['tempo_carregamento_min'] ?? 0,
                $dados['paradas_totais'] ?? 0,
                $dados['energia_consumida_kwh'] ?? 0,
                $dados['custo_total'] ?? 0,
                $dados['carga_final_pct'] ?? 0,
                $dados['modo_otimista'] ?? 0,
                isset($dados['dados_paradas']) ? json_encode($dados['dados_paradas']) : null,
                $dados['polyline'] ?? null
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Rota salva no histórico!',
                'id' => $pdo->lastInsertId()
            ];
            http_response_code(201);
            break;

        // Deletar do histórico
        case 'DELETE':
            $dados = json_decode(file_get_contents('php://input'), true);
            $id_historico = $dados['id'] ?? null;
            
            if (!$id_historico) {
                throw new Exception('ID não fornecido.');
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM historico_rota 
                WHERE ID_HISTORICO = ? AND FK_USUARIO = ?
            ");
            $stmt->execute([$id_historico, $id_usuario_logado]);
            
            if ($stmt->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Rota excluída do histórico.'];
            } else {
                throw new Exception('Rota não encontrada ou sem permissão.');
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