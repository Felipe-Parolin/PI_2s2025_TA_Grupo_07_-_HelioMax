<?php
require_once('conexao.php'); // deve definir $pdo (PDO)
header("Content-Type: application/json");

// -------- Função de validação de CPF --------
function validarCPF($cpf)
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf))
        return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d)
            return false;
    }
    return true;
}

// -------- Recebe dados --------
$data = json_decode(file_get_contents("php://input"), true);

$nome = trim($data["name"] ?? "");
$cpf = preg_replace('/[^0-9]/', '', trim($data["cpf"] ?? ""));
$email = strtolower(trim($data["email"] ?? ""));
$senha_plana = trim($data["password"] ?? "");
// Formata o CEP para garantir consistência (apenas números)
$cep_numeros = preg_replace('/[^0-9]/', '', trim($data["cep"] ?? ""));
// Se quiseres salvar com traço no banco, usa esta linha abaixo (opcional, dependendo de como o dashADM salva):
$cep_formatado = strlen($cep_numeros) == 8 ? substr($cep_numeros, 0, 5) . '-' . substr($cep_numeros, 5, 3) : $cep_numeros;

$logradouro = trim($data["street"] ?? "");
$bairro_nome = trim($data["neighborhood"] ?? "");
$numero_residencia = trim($data["number"] ?? "");
$complemento_endereco = trim($data["complement"] ?? "");
$cidade_nome = trim($data["city"] ?? "");
$estado_uf = strtoupper(trim($data["state"] ?? ""));

// -------- Validações --------
if (empty($nome) || empty($cpf) || empty($email) || empty($senha_plana)) {
    echo json_encode(["success" => false, "message" => "Campos obrigatórios não preenchidos."]);
    exit;
}
if (!validarCPF($cpf)) {
    echo json_encode(["success" => false, "message" => "CPF inválido."]);
    exit;
}

// -------- Criptografa senha --------
$senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

try {
    // Conexão e verificação inicial
    // Verifica se CPF ou Email já existem
    $stmt = $pdo->prepare("SELECT ID_USER FROM usuario WHERE CPF = ? OR EMAIL = ?");
    $stmt->execute([$cpf, $email]);
    if ($stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "CPF ou E-mail já cadastrado."]);
        exit;
    }

    // -------- Início da transação --------
    $pdo->beginTransaction();

    // 1️⃣ ESTADO
    $stmt = $pdo->prepare("SELECT ID_ESTADO FROM estado WHERE UF = ?");
    $stmt->execute([$estado_uf]);
    $id_estado = $stmt->fetchColumn();
    if (!$id_estado) {
        $stmt = $pdo->prepare("INSERT INTO estado (UF) VALUES (?)");
        $stmt->execute([$estado_uf]);
        $id_estado = $pdo->lastInsertId();
    }

    // 2️⃣ CIDADE
    $stmt = $pdo->prepare("SELECT ID_CIDADE FROM cidade WHERE NOME = ? AND FK_ESTADO = ?");
    $stmt->execute([$cidade_nome, $id_estado]);
    $id_cidade = $stmt->fetchColumn();
    if (!$id_cidade) {
        $stmt = $pdo->prepare("INSERT INTO cidade (NOME, FK_ESTADO) VALUES (?, ?)");
        $stmt->execute([$cidade_nome, $id_estado]);
        $id_cidade = $pdo->lastInsertId();
    }

    // 3️⃣ BAIRRO
    $stmt = $pdo->prepare("SELECT ID_BAIRRO FROM bairro WHERE NOME = ? AND FK_CIDADE = ?");
    $stmt->execute([$bairro_nome, $id_cidade]);
    $id_bairro = $stmt->fetchColumn();
    if (!$id_bairro) {
        $stmt = $pdo->prepare("INSERT INTO bairro (NOME, FK_CIDADE) VALUES (?, ?)");
        $stmt->execute([$bairro_nome, $id_cidade]);
        $id_bairro = $pdo->lastInsertId();
    }

    // 4️⃣ CEP (Corrigido para usar a coluna CEP e ID Auto Increment)
    // Tenta encontrar o CEP usando o formato string (ex: '12345678' ou '12345-678')
    // Nota: O ideal é padronizar. Aqui buscamos pelo CEP numérico OU formatado para garantir.
    $stmt = $pdo->prepare("SELECT ID_CEP FROM cep WHERE (CEP = ? OR CEP = ?) AND FK_BAIRRO = ?");
    $stmt->execute([$cep_numeros, $cep_formatado, $id_bairro]);
    $id_cep = $stmt->fetchColumn();

    if (!$id_cep) {
        // Se não existe, inserimos. Deixamos o ID_CEP ser gerado automaticamente (AUTO_INCREMENT)
        // Optamos por salvar formatado (com traço) para alinhar com o DashADM, ou sem traço se preferir.
        // Aqui usarei $cep_formatado para ficar bonito no banco.
        $stmt = $pdo->prepare("INSERT INTO cep (CEP, LOGRADOURO, FK_BAIRRO) VALUES (?, ?, ?)");
        $stmt->execute([$cep_formatado, $logradouro, $id_bairro]);
        $id_cep = $pdo->lastInsertId();
    }

    // 5️⃣ USUÁRIO
    $stmt = $pdo->prepare("
        INSERT INTO usuario 
        (NOME, CPF, EMAIL, SENHA, NUMERO_RESIDENCIA, COMPLEMENTO_ENDERECO, FK_ID_CEP, TIPO_USUARIO)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $nome,
        $cpf,
        $email,
        $senha_hash,
        $numero_residencia,
        $complemento_endereco,
        $id_cep
    ]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Usuário cadastrado com sucesso!"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro cadastro: " . $e->getMessage());
    // Retorna mensagem amigável, mas mantém o erro técnico no log
    echo json_encode(["success" => false, "message" => "Erro ao processar cadastro. Tente novamente."]);
}
?>