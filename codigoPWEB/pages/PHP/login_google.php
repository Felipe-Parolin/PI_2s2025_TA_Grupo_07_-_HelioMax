<?php
// Arquivo: login_google.php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_token'])) {
    header('Location: ../PHP/login.php');
    exit;
}

$id_token = $_POST['id_token'];
$client_id = "915868671198-mipp6tk42ikmq945t7q48vnphejiet96.apps.googleusercontent.com"; // SEU ID DE CLIENTE

// 1. Validação do ID Token com a API do Google
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['email']) || $data['aud'] !== $client_id) {
    $_SESSION['login_error'] = "Falha na autenticação ou token inválido.";
    header('Location: ../PHP/login.php');
    exit;
}

$email = $data['email'];
$nome = $data['name'];
$google_id = $data['sub']; 

// 2. Busca o usuário no banco de dados
$sql = "SELECT ID_USER, NOME, TIPO_USUARIO FROM usuario WHERE EMAIL = ?";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) == 1) {
        // Usuário ENCONTRADO (Login)
        mysqli_stmt_bind_result($stmt, $id_user, $nome_db, $tipo_usuario);
        mysqli_stmt_fetch($stmt);

        // Define as variáveis de sessão (CORREÇÃO AQUI!)
        $_SESSION['loggedin'] = true;
        $_SESSION['usuario_id'] = $id_user; 
        $_SESSION['usuario_nome'] = $nome_db; // <-- Variável corrigida
        $_SESSION['email'] = $email;
        $_SESSION['tipo_usuario'] = $tipo_usuario;
        
        mysqli_stmt_close($stmt);
        mysqli_close($link);

        // Redireciona para o dashboard
        header('Location: ../PHP/dashUSER.php');
        exit;

    } else {
        // Usuário NÃO ENCONTRADO (Pré-Cadastro)
        mysqli_stmt_close($stmt);

        // Armazena os dados do Google temporariamente
        $_SESSION['google_data'] = [
            'email' => $email,
            'nome' => $nome,
            'google_id' => $google_id
        ];

        // Redireciona para o formulário de complementação
        header('Location: cadastro_google.php');
        exit;
    }
} else {
    $_SESSION['login_error'] = "Erro interno do servidor. Tente novamente mais tarde.";
    header('Location: ../PHP/login.php');
    exit;
}
?>