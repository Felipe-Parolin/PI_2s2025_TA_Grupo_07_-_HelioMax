<?php
header('Content-Type: application/json');

// Ajuste o caminho do autoload conforme a localização da sua pasta 'vendor'
require_once '../../vendor/autoload.php';
require_once 'conexao.php';

$response = ['success' => false, 'message' => 'Requisição inválida.'];

try {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data);

    if (!isset($data->email)) {
        throw new Exception('E-mail não foi enviado na requisição.');
    }

    $email = strtolower(trim($data->email));

    // Verifica se o e-mail existe
    $stmt = $pdo->prepare("SELECT ID_USER, NOME FROM usuario WHERE LOWER(EMAIL) = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        // === ALTERAÇÃO: Mensagem de erro explícita para o usuário ===
        http_response_code(404); // Define o código HTTP 404 (Not Found)
        $response = [
            'success' => false,
            'message' => 'E-mail não cadastrado. Não é possível enviar o link de recuperação.'
        ];
        echo json_encode($response);
        exit();
        // ==========================================================
    }

    // Gera um token único e seguro
    $token = bin2hex(random_bytes(32));

    // Define data de expiração (1 hora a partir de agora)
    $data_criacao = date('Y-m-d H:i:s');
    $data_expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Invalida tokens anteriores deste usuário
    $stmt = $pdo->prepare("UPDATE recuperacao_senha SET UTILIZADO = 1 WHERE FK_ID_USUARIO = ? AND UTILIZADO = 0");
    $stmt->execute([$usuario['ID_USER']]);

    // Insere o novo token
    $stmt = $pdo->prepare("INSERT INTO recuperacao_senha (FK_ID_USUARIO, TOKEN, DATA_CRIACAO, DATA_EXPIRACAO, UTILIZADO) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute([$usuario['ID_USER'], $token, $data_criacao, $data_expiracao]);

    // Cria o link de recuperação
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $link_recuperacao = $protocol . "://" . $host . $script_dir . "/redefinir_senha.php?token=" . $token;

    // ============ ENVIO DE E-MAIL ============

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Configurações do Servidor GMAIL
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // ⬇️ SEUS DADOS AQUI ⬇️
    $mail->Username = 'heliomaxpi@gmail.com';
    $mail->Password = 'mkaqfxzrdalzyxrk';
    // ⬆️ SEUS DADOS AQUI ⬆️

    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    // Remetente e Destinatário
    $mail->setFrom('heliomaxpi@gmail.com', 'Heliomax');
    $mail->addAddress($email, $usuario['NOME']);

    // Conteúdo do E-mail
    $mail->isHTML(true);
    $mail->Subject = 'Recuperação de Senha - HelioMax';
    $mail->Body = "
        <h2>Olá, {$usuario['NOME']}!</h2>
        <p>Você solicitou a recuperação de senha da sua conta HelioMax.</p>
        <p>Clique no link abaixo para redefinir sua senha:</p>
        <p><a href='{$link_recuperacao}' style='background: #06b6d4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block;'>Redefinir Senha</a></p>
        <p>Este link expira em 1 hora.</p>
        <p>Se você não solicitou esta recuperação, ignore este e-mail.</p>
    ";
    $mail->AltBody = "Olá, {$usuario['NOME']}! Você solicitou a recuperação de senha. Use o link: {$link_recuperacao} (Este link expira em 1 hora). Se você não solicitou esta recuperação, ignore este e-mail.";

    if (!$mail->send()) {
        throw new Exception('Erro ao enviar e-mail. Detalhes do Servidor: ' . $mail->ErrorInfo);
    }

    // ================================================================

    $response = [
        'success' => true,
        'message' => 'Link de recuperação de senha enviado para o seu e-mail!' // Mensagem final de sucesso
    ];

} catch (Exception $e) {
    // Caso de erro (conexão, autenticação, etc.)
    http_response_code(400);
    $response['message'] = 'Falha no processo: ' . $e->getMessage();
}

echo json_encode($response);
exit();
?>