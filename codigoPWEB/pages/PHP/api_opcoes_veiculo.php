<?php
header('Content-Type: application/json');
require_once 'conexao.php'; // Inclui sua conexão com o banco
// session_start(); // Descomente se a verificação de login for necessária aqui também

// Resposta padrão
$response = ['success' => false, 'message' => 'Ação inválida.', 'data' => []];
$acao = $_GET['acao'] ?? '';

try {
    switch ($acao) {
        case 'marcas':
            // Busca ID_MARCA e NOME da tabela marca
            $stmt = $pdo->query("SELECT ID_MARCA, NOME FROM marca ORDER BY NOME");
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['message'] = 'Marcas carregadas.';
            break;

        case 'modelos':
            // Busca modelos baseados na FK_MARCA
            if (isset($_GET['marca_id'])) {
                $marca_id = (int)$_GET['marca_id'];
                $stmt = $pdo->prepare("SELECT ID_MODELO, NOME FROM modelo WHERE FK_MARCA = ? ORDER BY NOME");
                $stmt->execute([$marca_id]);
                $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['success'] = true;
                $response['message'] = 'Modelos carregados.';
            } else {
                $response['message'] = 'ID da marca não fornecido.';
            }
            break;

        case 'cores':
            // Busca ID_COR e NOME da tabela cor
            $stmt = $pdo->query("SELECT ID_COR, NOME FROM cor ORDER BY NOME");
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['message'] = 'Cores carregadas.';
            break;

        case 'conectores':
            // Busca ID_CONECTOR e NOME da tabela conector
            $stmt = $pdo->query("SELECT ID_CONECTOR, NOME FROM conector ORDER BY NOME");
            $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['message'] = 'Conectores carregados.';
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>