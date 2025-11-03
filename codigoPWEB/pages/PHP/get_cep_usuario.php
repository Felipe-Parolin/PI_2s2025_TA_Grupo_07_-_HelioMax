<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
     http_response_code(401);
     echo json_encode(['success' => false, 'message' => 'Não autorizado']);
     exit;
}

// Configuração do banco de dados
$host = '127.0.0.1';
$dbname = 'heliomax';
$username = 'root';
$password = '';

try {
     $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
     http_response_code(500);
     echo json_encode(['success' => false, 'message' => 'Erro na conexão']);
     exit;
}

$id_user = $_GET['id_user'] ?? null;

if (!$id_user) {
     echo json_encode(['success' => false, 'message' => 'ID do usuário não informado']);
     exit;
}

// Verificar se o usuário está acessando seus próprios dados
if ($id_user != $_SESSION['usuario_id']) {
     http_response_code(403);
     echo json_encode(['success' => false, 'message' => 'Acesso negado']);
     exit;
}

try {
     // Buscar informações completas do usuário com endereço
     $stmt = $pdo->prepare("
          SELECT 
               u.NUMERO_RESIDENCIA,
               u.COMPLEMENTO_ENDERECO,
               u.CEP as CEP_USUARIO,
               c.CEP as CEP_TABELA,
               c.LOGRADOURO, 
               b.NOME as bairro, 
               ci.NOME as cidade, 
               e.UF
          FROM usuario u
          LEFT JOIN cep c ON u.FK_ID_CEP = c.ID_CEP
          LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
          LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
          LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
          WHERE u.ID_USER = ?
     ");
     $stmt->execute([$id_user]);
     $result = $stmt->fetch(PDO::FETCH_ASSOC);

     if (!$result) {
          echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
          exit;
     }

     // Priorizar CEP do usuário, senão usar o da tabela cep
     $cep_final = $result['CEP_USUARIO'] ?? $result['CEP_TABELA'] ?? '';

     echo json_encode([
          'success' => true,
          'cep' => $cep_final,
          'numero' => $result['NUMERO_RESIDENCIA'] ?? '',
          'complemento' => $result['COMPLEMENTO_ENDERECO'] ?? '',
          'logradouro' => $result['LOGRADOURO'] ?? '',
          'bairro' => $result['bairro'] ?? '',
          'cidade' => $result['cidade'] ?? '',
          'uf' => $result['UF'] ?? ''
     ]);

} catch (Exception $e) {
     http_response_code(500);
     echo json_encode(['success' => false, 'message' => 'Erro ao buscar dados: ' . $e->getMessage()]);
}
?>