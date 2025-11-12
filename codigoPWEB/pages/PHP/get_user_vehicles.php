<?php
// get_user_vehicles.php - Busca os veículos do usuário logado
session_start(); // OBRIGATÓRIO! Garante que a sessão está ativa
header('Content-Type: application/json');

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado. A variável de sessão "usuario_id" não está definida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Configuração do banco de dados
$host = '127.0.0.1';
$dbname = 'heliomax';
$username = 'root';
$password = ''; // Preencha se o seu banco de dados exigir senha!

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Convertendo para inteiro para segurança, embora o prepare statement já trate disso.
    $user_id = (int)$_SESSION['usuario_id'];
    
    // CORREÇÃO: Campo correto é MODELO (não FK_MODELO)
    $sql = "SELECT 
                v.ID_VEICULO,
                v.PLACA,
                v.ANO_FAB,
                v.NIVEL_BATERIA,
                m.NOME as MODELO_NOME,
                m.CAPACIDADE_BATERIA,
                m.CONSUMO_MEDIO,
                ma.NOME as MARCA_NOME,
                c.NOME as COR_NOME,
                con.NOME as CONECTOR_NOME
            FROM veiculo v
            INNER JOIN modelo m ON v.MODELO = m.ID_MODELO
            INNER JOIN marca ma ON m.FK_MARCA = ma.ID_MARCA
            INNER JOIN cor c ON v.FK_COR = c.ID_COR
            INNER JOIN conector con ON v.FK_CONECTOR = con.ID_CONECTOR
            WHERE v.FK_USUARIO_ID_USER = ?
            ORDER BY v.ID_VEICULO DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para debug
    error_log("Usuario ID: " . $user_id);
    error_log("Veículos encontrados: " . count($veiculos));
    
    // Formata os dados para facilitar o uso no frontend
    $veiculos_formatados = [];
    foreach ($veiculos as $veiculo) {
        $veiculos_formatados[] = [
            // O ID é crucial para a seleção no JS
            'id' => $veiculo['ID_VEICULO'], 
            'placa' => $veiculo['PLACA'],
            'nome_completo' => $veiculo['MARCA_NOME'] . ' ' . $veiculo['MODELO_NOME'],
            'marca' => $veiculo['MARCA_NOME'],
            'modelo' => $veiculo['MODELO_NOME'],
            'ano' => intval($veiculo['ANO_FAB']),
            'cor' => $veiculo['COR_NOME'],
            'conector' => $veiculo['CONECTOR_NOME'],
            'nivel_bateria' => floatval($veiculo['NIVEL_BATERIA']),
            'capacidade_bateria' => floatval($veiculo['CAPACIDADE_BATERIA']),
            'consumo_medio' => floatval($veiculo['CONSUMO_MEDIO'])
        ];
    }
    
    // Retorna a lista de veículos
    echo json_encode([
        'success' => true,
        'veiculos' => $veiculos_formatados,
        'total' => count($veiculos_formatados)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Em caso de erro do banco de dados (Verifique suas credenciais!)
    error_log("Erro no banco de dados: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao buscar veículos no banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // Outros erros
    error_log("Erro inesperado: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>