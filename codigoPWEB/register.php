<?php
include('conexao.php');


header("Content-Type: application/json");

$file = "users.json";

// Função de validação de CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// Recebe dados
$data = json_decode(file_get_contents("php://input"), true);

$name = trim($data["name"] ?? "");
$cpf = trim($data["cpf"] ?? "");
$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";
$cep = trim($data["cep"] ?? "");
$street = trim($data["street"] ?? "");
$number = trim($data["number"] ?? "");
$neighborhood = trim($data["neighborhood"] ?? "");
$city = trim($data["city"] ?? "");
$state = trim($data["state"] ?? "");

// Validação básica
if (!$name || !$cpf || !$email || !$password || !$cep || !$street || !$number || !$neighborhood || !$city || !$state) {
    echo json_encode(["success" => false, "message" => "Preencha todos os campos."]);
    exit;
}

if (!validarCPF($cpf)) {
    echo json_encode(["success" => false, "message" => "CPF inválido."]);
    exit;
}

// Se não existir arquivo, cria
if (!file_exists($file)) file_put_contents($file, json_encode([]));

// Lê usuários existentes
$users = json_decode(file_get_contents($file), true);

// Verifica duplicados
foreach ($users as $user) {
    if ($user["email"] === $email) {
        echo json_encode(["success" => false, "message" => "E-mail já cadastrado."]);
        exit;
    }
    if ($user["cpf"] === $cpf) {
        echo json_encode(["success" => false, "message" => "CPF já cadastrado."]);
        exit;
    }
}

// Adiciona novo
$users[] = [
    "name" => $name,
    "cpf" => $cpf,
    "email" => $email,
    "password" => password_hash($password, PASSWORD_DEFAULT),
    "address" => [
        "cep" => $cep,
        "street" => $street,
        "number" => $number,
        "neighborhood" => $neighborhood,
        "city" => $city,
        "state" => $state
    ]
];

// Salva
file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));

echo json_encode(["success" => true, "message" => "Usuário cadastrado com sucesso!"]);
