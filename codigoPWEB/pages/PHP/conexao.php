<?php
$host = "localhost"; // ou 127.0.0.1
$usuario = "root";
$senha = "";
$banco = "heliomax";
$charset = "utf8mb4";

// DSN (Data Source Name) - define a conexão
$dsn = "mysql:host=$host;dbname=$banco;charset=$charset";

// Opções para o PDO, otimizando a conexão e o tratamento de erros
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna os resultados como arrays associativos
    PDO::ATTR_EMULATE_PREPARES => false,                  // Usa prepared statements nativos do DB
];

try {
    // Cria a instância do PDO que será usada em outros scripts
    $pdo = new PDO($dsn, $usuario, $senha, $options);
} catch (\PDOException $e) {
    // Para a execução e mostra um erro genérico em JSON se a conexão falhar
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno no servidor. Falha na conexão com o banco de dados.']);
    exit;
}
?>