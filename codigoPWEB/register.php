<?php
include('conexao.php');

// Recebe os dados do usuário via JSON
$data = json_decode(file_get_contents("php://input"), true);

$nome = trim($data["nome"] ?? "");
$cpf = preg_replace('/[^0-9]/', '', trim($data["cpf"] ?? ""));
$email = trim($data["email"] ?? "");
$senha = trim($data["senha"] ?? ""); // sem hash só para exemplo
$cep = preg_replace('/[^0-9]/', '', trim($data["cep"] ?? ""));
$logradouro = trim($data["rua"] ?? "");
$bairro = trim($data["bairro"] ?? "");
$cidade = trim($data["cidade"] ?? "");
$estado = strtoupper(trim($data["estado"] ?? ""));
$numero_residencia = trim($data["numero_residencia"] ?? "");
$complemento_endereco = trim($data["complemento_endereco"] ?? "");
$numero_cep = trim($data["numero_cep"] ?? "");

$sql = "INSERT INTO cep (numero_cep, logradouro, fk_bairro) VALUES ('$numero_cep', '$logradouro', NULL)";

if($conn->query($sql) === FALSE){
    echo "Erro CEP: " . $conn->error;
}

$sql = "INSERT INTO bairro (nome, fk_cidade) VALUES ('$bairro', NULL)";

if($conn->query($sql) === FALSE){
    echo "Erro BAIRRO: " . $conn->error;
}

$sql = "INSERT INTO cidade (nome, fk_estado) VALUES ('$cidade', NULL)";

if($conn->query($sql) === FALSE){
    echo "Erro CIDADE: " . $conn->error;
}

$sql = "INSERT INTO estado (uf) VALUES ('$estado')";

if($conn->query($sql) === FALSE){
    echo "Erro ESTADO: " . $conn->error;
}

$sql = "INSERT INTO usuario (nome, cpf, email, senha, tipo_usuario, numero_residencia, complemento_residencia, complemento_endereco, fk_id_cep) VALUES ('$nome', '$cpf', '$email', '$senha', 1, '$numero_residencia', '$complemento_endereco', NULL)"; 


$conn->close();
?>
