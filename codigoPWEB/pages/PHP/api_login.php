<?php
// Define que a resposta será SEMPRE no formato JSON
header('Content-Type: application/json');
session_start();

require_once 'conexao.php';

$response = ['success' => false, 'message' => 'Requisição inválida.'];

try {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data);

    if (!isset($data->email) || !isset($data->password)) {
        throw new Exception('Dados de e-mail ou senha não foram enviados.');
    }

    $email = $data->email;
    $senha_form = $data->password;

    $stmt = $pdo->prepare("SELECT ID_USER, NOME, EMAIL, SENHA, TIPO_USUARIO FROM usuario WHERE EMAIL = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha_form, $usuario['SENHA'])) {
        $_SESSION['usuario_id'] = $usuario['ID_USER'];
        $_SESSION['usuario_nome'] = $usuario['NOME'];
        $_SESSION['usuario_email'] = $usuario['EMAIL'];
        $_SESSION['usuario_tipo'] = $usuario['TIPO_USUARIO'];

        // Redireciona conforme o tipo numérico
        $redirectUrl = ($usuario['TIPO_USUARIO'] == 1) ? 'dashADM.php' : 'dashUSER.php';

        $response = [
            'success' => true,
            'message' => 'Login bem-sucedido!',
            'redirectUrl' => $redirectUrl
        ];
    } else {
        http_response_code(401);
        $response['message'] = 'E-mail ou senha inválidos!';
    }

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit();
?>