<?php
header('Content-Type: application/json; charset=utf-8');
include('conexao.php');

// Verifica se a conexão com o banco existe
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['exists' => false, 'error' => 'Erro de conexão com banco de dados']);
    exit;
}

// Recebe os dados JSON
$data = json_decode(file_get_contents("php://input"), true);
$email = strtolower(trim($data["email"] ?? ""));

// Se email vazio, retorna false
if (empty($email)) {
    echo json_encode(['exists' => false]);
    $conn->close();
    exit;
}

// Valida formato do email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'error' => 'Email inválido']);
    $conn->close();
    exit;
}

// Busca no banco de dados usando prepared statement
$stmt = $conn->prepare("SELECT id_usuario FROM usuario WHERE LOWER(email) = ? LIMIT 1");

if (!$stmt) {
    echo json_encode(['exists' => false, 'error' => 'Erro na preparação da consulta']);
    $conn->close();
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// Retorna se existe ou não
$exists = $result->num_rows > 0;
echo json_encode(['exists' => $exists]);

// Fecha statement e conexão
$stmt->close();
$conn->close();
?>