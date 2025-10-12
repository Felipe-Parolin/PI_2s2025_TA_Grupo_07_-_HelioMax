<?php
include('conexao.php');

header("Content-Type: application/json");

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

// Recebe dados do JSON
$data = json_decode(file_get_contents("php://input"), true);

$nome = trim($data["nome"] ?? "");
$cpf = preg_replace('/[^0-9]/', '', trim($data["cpf"] ?? ""));
$email = trim($data["email"] ?? "");
$senha = password_hash($data["senha"] ?? "", PASSWORD_DEFAULT); // HASH DA SENHA!
$cep = preg_replace('/[^0-9]/', '', trim($data["cep"] ?? ""));
$logradouro = trim($data["rua"] ?? ""); // NOME DA RUA
$bairro_nome = trim($data["bairro"] ?? ""); // BAIRRO
$numero_residencia = trim($data["numero_residencia"] ?? "");
$complemento_endereco = trim($data["complemento_endereco"] ?? "");
$cidade_nome = trim($data["cidade"] ?? "");
$estado_uf = strtoupper(trim($data["estado"] ?? ""));

// Validações básicas
if (empty($nome) || empty($cpf) || empty($email) || empty($senha)) {
    echo json_encode(["success" => false, "message" => "Campos obrigatórios não preenchidos."]);
    exit;
}

// Validação de CPF
if (!validarCPF($cpf)) {
    echo json_encode(["success" => false, "message" => "CPF inválido."]);
    exit;
}

// Verifica se CPF ou Email já existem (COM PREPARED STATEMENT)
$stmt = $conn->prepare("SELECT ID_USER FROM usuario WHERE cpf = ? OR email = ?");
$stmt->bind_param("ss", $cpf, $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "CPF ou E-mail já cadastrado."]);
    exit;
}

// ------------------- INSERÇÃO NO BANCO -------------------
$conn->begin_transaction();

try {
    // 1️⃣ ESTADO - Busca ou cria
    $stmt = $conn->prepare("SELECT ID_ESTADO FROM estado WHERE UF = ?");
    $stmt->bind_param("s", $estado_uf);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $id_estado = $result->fetch_assoc()["ID_ESTADO"];
    } else {
        $stmt = $conn->prepare("INSERT INTO estado (UF) VALUES (?)");
        $stmt->bind_param("s", $estado_uf);
        $stmt->execute();
        $id_estado = $conn->insert_id;
    }

    // 2️⃣ CIDADE - Busca ou cria
    $stmt = $conn->prepare("SELECT ID_CIDADE FROM cidade WHERE NOME = ? AND FK_ESTADO = ?");
    $stmt->bind_param("si", $cidade_nome, $id_estado);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $id_cidade = $result->fetch_assoc()["ID_CIDADE"];
    } else {
        $stmt = $conn->prepare("INSERT INTO cidade (NOME, FK_ESTADO) VALUES (?, ?)");
        $stmt->bind_param("si", $cidade_nome, $id_estado);
        $stmt->execute();
        $id_cidade = $conn->insert_id;
    }

    // 3️⃣ BAIRRO - Busca ou cria (AGORA COM O NOME DO BAIRRO, NÃO DA RUA!)
    $stmt = $conn->prepare("SELECT ID_BAIRRO FROM bairro WHERE NOME = ? AND FK_CIDADE = ?");
    $stmt->bind_param("si", $bairro_nome, $id_cidade);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $id_bairro = $result->fetch_assoc()["ID_BAIRRO"];
    } else {
        $stmt = $conn->prepare("INSERT INTO bairro (NOME, FK_CIDADE) VALUES (?, ?)");
        $stmt->bind_param("si", $bairro_nome, $id_cidade);
        $stmt->execute();
        $id_bairro = $conn->insert_id;
    }

    // 4️⃣ CEP - Busca ou cria
    $stmt = $conn->prepare("SELECT ID_CEP FROM cep WHERE ID_CEP = ? AND LOGRADOURO = ? AND FK_BAIRRO = ?");
    $stmt->bind_param("ssi", $cep, $logradouro, $id_bairro);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $id_cep = $result->fetch_assoc()["ID_CEP"];
    } else {
        $stmt = $conn->prepare("INSERT INTO cep (ID_CEP, LOGRADOURO, FK_BAIRRO) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $cep, $logradouro, $id_bairro);
        $stmt->execute();
        $id_cep = $conn->insert_id;
    }

    // 5️⃣ USUÁRIO
    $stmt = $conn->prepare("INSERT INTO usuario (NOME, CPF, EMAIL, SENHA, NUMERO_RESIDENCIA, COMPLEMENTO_ENDERENCO, FK_ID_CEP) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $nome, $cpf, $email, $senha, $numero_residencia, $complemento_endereco, $id_cep);
    $stmt->execute();

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Usuário cadastrado com sucesso!"]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Erro ao cadastrar: " . $e->getMessage()]);
}

$conn->close();
?>