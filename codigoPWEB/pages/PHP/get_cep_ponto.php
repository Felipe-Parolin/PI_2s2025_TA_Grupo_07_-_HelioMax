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

$id_ponto = $_GET['id_ponto'] ?? null;
$admin_id = $_SESSION['usuario_id'];

if (!$id_ponto) {
     echo json_encode(['success' => false, 'message' => 'ID do ponto não informado']);
     exit;
}

try {
     // Buscar informações completas do ponto
     $stmt = $pdo->prepare("
          SELECT 
               pc.CEP,
               pc.NUMERO,
               pc.COMPLEMENTO,
               c.LOGRADOURO, 
               b.NOME as bairro, 
               ci.NOME as cidade, 
               e.UF
          FROM ponto_carregamento pc
          LEFT JOIN cep c ON pc.LOCALIZACAO = c.ID_CEP
          LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
          LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
          LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
          WHERE pc.ID_PONTO = ? AND pc.FK_ID_USUARIO_CADASTRO = ?
     ");
     $stmt->execute([$id_ponto, $admin_id]);
     $result = $stmt->fetch(PDO::FETCH_ASSOC);

     if (!$result) {
          echo json_encode(['success' => false, 'message' => 'Ponto não encontrado']);
          exit;
     }

     echo json_encode([
          'success' => true,
          'cep' => $result['CEP'] ?? '',
          'numero' => $result['NUMERO'] ?? '',
          'complemento' => $result['COMPLEMENTO'] ?? '',
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