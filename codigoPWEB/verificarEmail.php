<?php
include('conexao.php');

header("Content-Type: application/json");

$file = "users.json";

// Se não existir o arquivo de usuários, nenhum e-mail existe ainda
if (!file_exists($file)) {
    echo json_encode(["exists" => false]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$email = strtolower(trim($data["email"] ?? ""));

if (!$email) {
    echo json_encode(["exists" => false]);
    exit;
}

$users = json_decode(file_get_contents($file), true);

foreach ($users as $user) {
    if (strtolower($user["email"]) === $email) {
        echo json_encode(["exists" => true]);
        exit;
    }
}

echo json_encode(["exists" => false]);
