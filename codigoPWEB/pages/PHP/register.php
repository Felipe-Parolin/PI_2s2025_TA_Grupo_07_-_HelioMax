<?php
// Configurações de erro e charset
error_reporting(E_ALL);
ini_set('display_errors', 1); // ATIVAR para debug
header('Content-Type: application/json; charset=utf-8');

// LOG para debug - REMOVER em produção
function log_debug($message, $data = null)
{
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $log .= " - " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    error_log($log . "\n", 3, "register_debug.log");
}

// Inclua seu arquivo de conexão
include('conexao.php');

// Verifica se a conexão foi estabelecida
if (!isset($conn)) {
    log_debug("ERRO: Variável \$conn não existe");
    echo json_encode([
        'success' => false,
        'message' => 'Erro: Conexão não estabelecida (variável $conn não existe)'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($conn->connect_error) {
    log_debug("ERRO: Falha na conexão", $conn->connect_error);
    echo json_encode([
        'success' => false,
        'message' => 'Erro de conexão: ' . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

log_debug("Conexão estabelecida com sucesso");

// Função para enviar resposta JSON e encerrar
function json_response($success, $message, $conn = null)
{
    log_debug("Resposta final", ['success' => $success, 'message' => $message]);
    if ($conn) {
        $conn->close();
    }
    echo json_encode([
        'success' => $success,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Recebe os dados do usuário via JSON
$input = file_get_contents("php://input");
log_debug("Dados recebidos (raw)", $input);

$data = json_decode($input, true);

if (!$data) {
    log_debug("ERRO: JSON inválido");
    json_response(false, 'Dados inválidos recebidos. Erro JSON.', $conn);
}

log_debug("Dados decodificados", $data);

// 1. Definição e Limpeza das Variáveis
$name = trim($data["name"] ?? "");
$cpf = preg_replace('/[^0-9]/', '', trim($data["cpf"] ?? ""));
$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");
$cep = preg_replace('/[^0-9]/', '', trim($data["cep"] ?? ""));
$street = trim($data["street"] ?? "");
$neighborhood = trim($data["neighborhood"] ?? "");
$city = trim($data["city"] ?? "");
$state = strtoupper(trim($data["state"] ?? ""));
$number = trim($data["number"] ?? "");

log_debug("Variáveis processadas", [
    'name' => $name,
    'cpf' => $cpf,
    'email' => $email,
    'cep' => $cep,
    'city' => $city,
    'state' => $state
]);

// Variáveis adicionais
$complemento_residencia = "";
$tipo_usuario = 1;

// Validação dos campos obrigatórios
if (
    empty($name) || empty($cpf) || empty($email) || empty($password) ||
    empty($cep) || empty($street) || empty($neighborhood) ||
    empty($city) || empty($state) || empty($number)
) {
    log_debug("ERRO: Campos obrigatórios vazios");
    json_response(false, 'Dados de cadastro incompletos.', $conn);
}

// Validação de CPF (11 dígitos)
if (strlen($cpf) !== 11) {
    log_debug("ERRO: CPF inválido", ['cpf' => $cpf, 'length' => strlen($cpf)]);
    json_response(false, 'CPF inválido (deve ter 11 dígitos).', $conn);
}

// Validação de CEP (8 dígitos)
if (strlen($cep) !== 8) {
    log_debug("ERRO: CEP inválido", ['cep' => $cep, 'length' => strlen($cep)]);
    json_response(false, 'CEP inválido (deve ter 8 dígitos).', $conn);
}

// Validação de email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    log_debug("ERRO: Email inválido", ['email' => $email]);
    json_response(false, 'E-mail inválido.', $conn);
}

// Validação de senha
if (strlen($password) < 6) {
    log_debug("ERRO: Senha muito curta");
    json_response(false, 'A senha deve ter pelo menos 6 caracteres.', $conn);
}

log_debug("Todas as validações passaram");

// Inicializa IDs
$fk_estado = 0;
$fk_cidade = 0;
$fk_bairro = 0;
$fk_id_cep = 0;

/**
 * Insere um registro, trata duplicidade e retorna o ID.
 */
function upsert_and_get_id($conn, $tableName, $data, $uniqueFields, $idColumn)
{
    log_debug("upsert_and_get_id iniciado", ['table' => $tableName, 'data' => $data]);

    // Monta a query INSERT ... ON DUPLICATE KEY UPDATE
    $fields = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    // ON DUPLICATE: atualiza os mesmos campos
    $updateClauses = array_map(function ($field) {
        return "$field = VALUES($field)";
    }, array_keys($data));
    $updateClause = implode(', ', $updateClauses);

    $sql = "INSERT INTO $tableName ($fields) VALUES ($placeholders) 
            ON DUPLICATE KEY UPDATE $updateClause";

    log_debug("SQL gerado", ['sql' => $sql]);

    // Prepara e executa o INSERT seguro
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        log_debug("ERRO: Falha no prepare", ['error' => $conn->error, 'errno' => $conn->errno]);
        json_response(false, "Erro na preparação ($tableName): " . $conn->error, $conn);
    }

    // Tipos de dados
    $types = str_repeat('s', count($data));
    $values = array_values($data);

    log_debug("Bind params", ['types' => $types, 'values' => $values]);

    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        log_debug("ERRO: Falha no execute", [
            'error' => $stmt->error,
            'errno' => $stmt->errno,
            'table' => $tableName
        ]);
        json_response(false, "Erro ao executar em $tableName: " . $stmt->error, $conn);
    }

    $id = $stmt->insert_id;
    log_debug("Insert ID", ['insert_id' => $id, 'table' => $tableName]);
    $stmt->close();

    // Se o ID for 0, significa que a linha já existia
    if ($id === 0) {
        log_debug("ID = 0, buscando registro existente", ['table' => $tableName]);

        // Monta o SELECT para pegar o ID existente
        $whereClauses = array_map(function ($field) {
            return "$field = ?";
        }, $uniqueFields);
        $where = implode(' AND ', $whereClauses);

        $sqlSelect = "SELECT $idColumn FROM $tableName WHERE $where LIMIT 1";
        log_debug("SQL SELECT", ['sql' => $sqlSelect]);

        $stmtSelect = $conn->prepare($sqlSelect);
        if (!$stmtSelect) {
            log_debug("ERRO: Falha no prepare SELECT", ['error' => $conn->error]);
            json_response(false, "Erro SELECT em $tableName: " . $conn->error, $conn);
        }

        $typesSelect = str_repeat('s', count($uniqueFields));
        $valuesSelect = array_values(array_intersect_key($data, array_flip($uniqueFields)));

        log_debug("SELECT bind params", ['types' => $typesSelect, 'values' => $valuesSelect]);

        $stmtSelect->bind_param($typesSelect, ...$valuesSelect);
        $stmtSelect->execute();
        $result = $stmtSelect->get_result();

        if ($row = $result->fetch_assoc()) {
            $id = $row[$idColumn];
            log_debug("ID encontrado", ['id' => $id, 'table' => $tableName]);
        } else {
            log_debug("ERRO: Registro não encontrado", ['table' => $tableName]);
            json_response(false, "Erro: Registro não encontrado em $tableName.", $conn);
        }
        $stmtSelect->close();
    }

    log_debug("upsert_and_get_id concluído", ['table' => $tableName, 'final_id' => $id]);
    return (int) $id;
}

// Inicia transação para garantir consistência
log_debug("Iniciando transação");
$conn->begin_transaction();

try {
    // 2.1 ESTADO (UF)
    log_debug("Processando ESTADO");
    $fk_estado = upsert_and_get_id(
        $conn,
        'estado',
        ['uf' => $state],
        ['uf'],
        'id_estado'
    );
    log_debug("Estado inserido", ['id' => $fk_estado]);

    // 2.2 CIDADE
    log_debug("Processando CIDADE");
    $fk_cidade = upsert_and_get_id(
        $conn,
        'cidade',
        ['nome' => $city, 'fk_estado' => $fk_estado],
        ['nome', 'fk_estado'],
        'id_cidade'
    );
    log_debug("Cidade inserida", ['id' => $fk_cidade]);

    // 2.3 BAIRRO
    log_debug("Processando BAIRRO");
    $fk_bairro = upsert_and_get_id(
        $conn,
        'bairro',
        ['nome' => $neighborhood, 'fk_cidade' => $fk_cidade],
        ['nome', 'fk_cidade'],
        'id_bairro'
    );
    log_debug("Bairro inserido", ['id' => $fk_bairro]);

    // 2.4 CEP
    log_debug("Processando CEP");
    $fk_id_cep = upsert_and_get_id(
        $conn,
        'cep',
        ['numero_cep' => $cep, 'logradouro' => $street, 'fk_bairro' => $fk_bairro],
        ['numero_cep', 'fk_bairro'],
        'id_cep'
    );
    log_debug("CEP inserido", ['id' => $fk_id_cep]);

    // 3. INSERÇÃO DO USUÁRIO
    log_debug("Processando USUÁRIO");
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql_user = "INSERT INTO usuario 
                 (nome, cpf, email, senha, tipo_usuario, numero_residencia, fk_id_cep) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";

    log_debug("SQL Usuario", ['sql' => $sql_user]);

    $stmt_user = $conn->prepare($sql_user);

    if (!$stmt_user) {
        log_debug("ERRO: Falha no prepare do usuário", ['error' => $conn->error]);
        throw new Exception("Erro ao preparar inserção de usuário: " . $conn->error);
    }

    $stmt_user->bind_param(
        "ssssssi",
        $name,
        $cpf,
        $email,
        $hashed_password,
        $tipo_usuario,
        $number,
        $fk_id_cep
    );

    log_debug("Executando insert do usuário");

    if (!$stmt_user->execute()) {
        log_debug("ERRO: Falha ao executar insert do usuário", [
            'error' => $stmt_user->error,
            'errno' => $stmt_user->errno
        ]);

        // Verifica se é erro de duplicação
        if ($conn->errno === 1062) {
            throw new Exception("CPF ou e-mail já cadastrado.");
        }
        throw new Exception("Erro ao cadastrar usuário: " . $stmt_user->error);
    }

    $stmt_user->close();

    log_debug("Usuário inserido com sucesso, commitando transação");

    // Confirma a transação
    $conn->commit();
    log_debug("Transação commitada com sucesso");

    json_response(true, "Usuário cadastrado com sucesso!", $conn);

} catch (Exception $e) {
    log_debug("EXCEÇÃO capturada", ['message' => $e->getMessage()]);
    // Reverte a transação em caso de erro
    $conn->rollback();
    log_debug("Transação revertida (rollback)");
    json_response(false, $e->getMessage(), $conn);
}
?>