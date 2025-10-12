<?php
session_start();


$users = [
    "admin@heliomax.com" => "123456",
    "usuario@heliomax.com" => "solar123",
    "demo@test.com" => "demo123"
];


$data = json_decode(file_get_contents("php://input"), true);
$email = $data["email"] ?? "";
$password = $data["password"] ?? "";
$remember = $data["remember"] ?? false;


$response = ["success" => false, "message" => "Email ou senha incorretos."];


if (isset($users[$email]) && $users[$email] === $password) {
    $_SESSION["user"] = $email;

    
    if ($remember) {
        setcookie("rememberedEmail", $email, time() + (7 * 24 * 60 * 60), "/");
    }

    $response = [
        "success" => true,
        "message" => "Login realizado com sucesso!",
        "user" => ["email" => $email]
    ];
}


header("Content-Type: application/json");
echo json_encode($response);
