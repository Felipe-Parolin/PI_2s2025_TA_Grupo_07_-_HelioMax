<?php
$host = "localhost"; // servidor
$usuario = "root";   // usuário padrão do XAMPP
$senha = "";         // senha padrão é vazia
$banco = "heliomax"; // nome do seu banco de dados

// Criar conexão
$conn = new mysqli($host, $usuario, $senha, $banco);

// Verificar conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
} else {
    echo "Conectado com sucesso!";
}
?>
