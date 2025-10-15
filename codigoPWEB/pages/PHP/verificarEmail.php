<?php
header("Content-Type: application/json");
require_once('conexao.php'); // deve definir $pdo

// recebe JSON
$data = json_decode(file_get_contents("php://input"), true);
$email = strtolower(trim($data['email'] ?? ''));

if (!$email) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT 1 FROM usuario WHERE LOWER(EMAIL) = ? LIMIT 1");
    $stmt->execute([$email]);
    $exists = (bool) $stmt->fetchColumn();
    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    // não vaza detalhes sensíveis em produção
    error_log("verificarEmail error: " . $e->getMessage());
    echo json_encode(['exists' => false]);
}