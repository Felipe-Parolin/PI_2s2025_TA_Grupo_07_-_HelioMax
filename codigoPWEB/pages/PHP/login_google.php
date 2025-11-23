<?php
// Arquivo: login_google.php (CORRIGIDO FINAL)

// 1. Inicia sessão se não existir
if (!isset($_SESSION)) {
    session_start();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_token'])) {
    header('Location: ../PHP/login.php');
    exit;
}

$id_token = $_POST['id_token'];
$client_id = "915868671198-mipp6tk42ikmq945t7q48vnphejiet96.apps.googleusercontent.com";

// 2. Validação Google
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['email']) || $data['aud'] !== $client_id) {
    $_SESSION['login_error'] = "Token inválido.";
    header('Location: ../PHP/login.php');
    exit;
}

$email = $data['email'];
$nome = $data['name'];
$google_id = $data['sub'];

// 3. Verifica Banco de Dados
$sql = "SELECT ID_USER, NOME, TIPO_USUARIO FROM usuario WHERE EMAIL = ?";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) == 1) {
        mysqli_stmt_bind_result($stmt, $id_user, $nome_db, $tipo_usuario);
        mysqli_stmt_fetch($stmt);

        // --- DEFINIÇÃO DE SESSÃO ---
        $_SESSION['loggedin'] = true;
        $_SESSION['usuario_id'] = $id_user;
        $_SESSION['usuario_nome'] = $nome_db;
        $_SESSION['email'] = $email;

        // CORREÇÃO AQUI: Mudámos de 'tipo_usuario' para 'usuario_tipo'
        // para bater certo com o protectadmin.php
        $_SESSION['usuario_tipo'] = $tipo_usuario;

        mysqli_stmt_close($stmt);
        mysqli_close($link);

        session_write_close(); // Garante o salvamento

        // Redirecionamento
        if ($tipo_usuario == 1) {
            header('Location: ../PHP/dashADM.php');
        } else {
            header('Location: ../PHP/dashUSER.php');
        }
        exit;

    } else {
        // Pré-cadastro
        mysqli_stmt_close($stmt);
        $_SESSION['google_data'] = [
            'email' => $email,
            'nome' => $nome,
            'google_id' => $google_id
        ];
        header('Location: cadastro_google.php');
        exit;
    }
} else {
    $_SESSION['login_error'] = "Erro interno.";
    header('Location: ../PHP/login.php');
    exit;
}
?>