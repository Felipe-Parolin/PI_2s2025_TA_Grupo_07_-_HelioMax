<?php
// Este script executa automaticamente quando a página de login é carregada
// Ele atualiza as senhas do banco de dados de forma silenciosa, sem exibir mensagens

// Apenas responde a requisições POST (vem do login.php)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Define o header como JSON para ficar silencioso
header('Content-Type: application/json');

try {
    // Requer a conexão com o banco de dados
    require_once 'conexao.php';

    // Cria um arquivo de controle para executar apenas uma vez por dia
    $arquivo_controle = sys_get_temp_dir() . '/heliomax_hash_control.txt';

    // Verifica se já foi executado hoje
    if (file_exists($arquivo_controle)) {
        $ultima_execucao = (int) file_get_contents($arquivo_controle);
        $hoje = strtotime('today');

        // Se já foi executado hoje, sai silenciosamente
        if ($ultima_execucao === $hoje) {
            echo json_encode(['status' => 'already_executed']);
            exit;
        }
    }

    // Seleciona todos os usuários cujas senhas ainda não foram criptografadas
    // Um hash gerado pelo PHP password_hash começa com '$2y$' ou '$2a$' e tem 60+ caracteres
    $stmt = $pdo->prepare("SELECT ID_USER, SENHA FROM usuario WHERE LENGTH(SENHA) < 60 OR SENHA NOT LIKE '$2%'");
    $stmt->execute();
    $usuarios_para_atualizar = $stmt->fetchAll();

    // Se não há senhas para atualizar, sai silenciosamente
    if (empty($usuarios_para_atualizar)) {
        // Atualiza o arquivo de controle mesmo assim
        file_put_contents($arquivo_controle, strtotime('today'));
        echo json_encode(['status' => 'no_updates_needed']);
        exit;
    }

    // Itera sobre cada usuário e atualiza a senha
    $atualizados = 0;
    foreach ($usuarios_para_atualizar as $usuario) {
        $id_usuario = $usuario['ID_USER'];
        $senha_plana = $usuario['SENHA'];

        // Verifica se a senha já é um hash válido
        if (password_needs_rehash($senha_plana, PASSWORD_DEFAULT)) {
            // Gera o hash seguro da senha
            $senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

            // Atualiza o registro no banco de dados com o novo hash
            $update_stmt = $pdo->prepare("UPDATE usuario SET SENHA = ? WHERE ID_USER = ?");
            $update_stmt->execute([$senha_hash, $id_usuario]);

            $atualizados++;
        }
    }

    // Atualiza o arquivo de controle para indicar que foi executado hoje
    if ($atualizados > 0) {
        file_put_contents($arquivo_controle, strtotime('today'));
    }

    // Responde silenciosamente com sucesso (o usuário não vê nada)
    echo json_encode(['status' => 'success', 'updated' => $atualizados]);

} catch (Exception $e) {
    // Em caso de erro, registra no log mas não mostra nada ao usuário
    error_log("Erro ao atualizar senhas: " . $e->getMessage());
    echo json_encode(['status' => 'error']);
}

exit;
?>