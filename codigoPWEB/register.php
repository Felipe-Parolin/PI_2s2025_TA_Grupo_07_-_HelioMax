<?php
// AVISO DE SEGURANÇA: Este script está usando Prepared Statements.
// Não use a conexão mysqli procedural (`$conn->query(...)`) em produção sem sanear os dados!

// Inclua seu arquivo de conexão
// Sua conexão deve retornar o objeto mysqli (Orientado a Objetos)
include('conexao.php'); 

// Função para enviar resposta JSON e encerrar
function json_response($success, $message, $conn) {
    if ($conn) {
        $conn->close();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Recebe os dados do usuário via JSON
$data = json_decode(file_get_contents("php://input"), true);

// 1. Definição e Limpeza das Variáveis
// NOTA: CPF e CEP são limpos, email e nome são trimados
$name = trim($data["name"] ?? ""); 
$cpf = preg_replace('/[^0-9]/', '', trim($data["cpf"] ?? ""));
$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? ""); 
$cep = preg_replace('/[^0-9]/', '', trim($data["cep"] ?? ""));

$street = trim($data["street"] ?? "");
$neighborhood = trim($data["neighborhood"] ?? "");
$city = trim($data["city"] ?? "");
$state = strtoupper(trim($data["state"] ?? "")); // UF em MAIÚSCULAS
$number = trim($data["number"] ?? "");

// Variável para a coluna 'complemento_residencia'
$complemento_residencia = ""; 
$tipo_usuario = 1; // Exemplo

// ----------------------------------------------------
// ⚠️ PRÉ-REQUISITO IMPORTANTE: ÍNDICES ÚNICOS
// Para que a lógica funcione, suas tabelas DEVE ter índices UNIQUE:
// - estado: UNIQUE(uf)
// - cidade: UNIQUE(nome, fk_estado)
// - bairro: UNIQUE(nome, fk_cidade)
// - cep: UNIQUE(numero_cep, fk_bairro)
// ----------------------------------------------------


if (empty($name) || empty($cpf) || empty($email) || empty($password) || empty($cep) || empty($street) || empty($neighborhood) || empty($city) || empty($state) || empty($number)) {
    json_response(false, 'Dados de cadastro incompletos.', $conn);
}

// Inicializa IDs
$fk_estado = 0;
$fk_cidade = 0;
$fk_bairro = 0;
$fk_id_cep = 0;

// =========================================================
// 2. INSERÇÃO E OBTENÇÃO DE CHAVES ESTRANGEIRAS EM CASCATA
// A função 'upsert_and_get_id' encapsula a lógica:
// 1. Tenta inserir/atualizar (ON DUPLICATE KEY UPDATE)
// 2. Se o ID for novo, usa insert_id.
// 3. Se for duplicado (ON DUPLICATE), faz um SELECT para pegar o ID existente.
// =========================================================

/**
 * Insere um registro, trata duplicidade e retorna o ID.
 *
 * @param mysqli $conn Objeto de conexão MySQLi.
 * @param string $tableName Nome da tabela (e.g., 'estado').
 * @param array $data Associa campos => valores para INSERT (e.g., ['uf' => $state]).
 * @param array $uniqueFields Campos que formam o índice UNIQUE (e.g., ['uf']).
 * @param string $idColumn Nome da coluna ID (e.g., 'id_estado').
 * @return int O ID (chave primária) do registro.
 */
function upsert_and_get_id($conn, $tableName, $data, $uniqueFields, $idColumn) {
    // Monta a query INSERT ... ON DUPLICATE KEY UPDATE
    $fields = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    
    // ON DUPLICATE: Apenas atualiza um campo para que o comando seja considerado bem-sucedido
    // e o last_insert_id() funcione como 0.
    $updateClauses = array_map(function($field) {
        return "$field = VALUES($field)";
    }, array_keys($data));
    $updateClause = implode(', ', $updateClauses);

    $sql = "INSERT INTO $tableName ($fields) VALUES ($placeholders) 
            ON DUPLICATE KEY UPDATE $updateClause";

    // Prepara e executa o INSERT seguro
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(false, "Erro na preparação do INSERT para $tableName: " . $conn->error, $conn);
    }
    
    // Tipos de dados (simplificado para string 's')
    $types = str_repeat('s', count($data));
    $values = array_values($data);
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        json_response(false, "Erro ao executar INSERT/UPDATE em $tableName: " . $stmt->error, $conn);
    }
    $stmt->close();
    
    $id = $conn->insert_id;

    // Se o ID for 0, significa que a linha já existia (ON DUPLICATE KEY UPDATE)
    if ($id === 0) {
        // Monta o SELECT para pegar o ID da linha que já existe
        $whereClauses = array_map(function($field) {
            return "$field = ?";
        }, $uniqueFields);
        $where = implode(' AND ', $whereClauses);
        
        $sqlSelect = "SELECT $idColumn FROM $tableName WHERE $where";
        
        $stmtSelect = $conn->prepare($sqlSelect);
        if (!$stmtSelect) {
            json_response(false, "Erro na preparação do SELECT para $tableName: " . $conn->error, $conn);
        }
        
        // Tipos de dados (apenas dos campos UNIQUE)
        $typesSelect = str_repeat('s', count($uniqueFields));
        $valuesSelect = array_intersect_key($data, array_flip($uniqueFields));
        
        $stmtSelect->bind_param($typesSelect, ...array_values($valuesSelect));
        $stmtSelect->execute();
        $result = $stmtSelect->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $id = $row[$idColumn];
        } else {
            // Este caso não deve ocorrer se o ON DUPLICATE funcionou
            json_response(false, "Erro lógico: ID duplicado não encontrado em $tableName.", $conn);
        }
        $stmtSelect->close();
    }
    
    return (int) $id;
}


// 2.1 ESTADO (UF)
// Tabela: estado, Coluna ID: id_estado, Coluna Única: uf
$fk_estado = upsert_and_get_id(
    $conn, 
    'estado', 
    ['uf' => $state], 
    ['uf'], 
    'id_estado'
);


// 2.2 CIDADE (NOME e FK_ESTADO)
// Tabela: cidade, Coluna ID: id_cidade, Colunas Únicas: nome, fk_estado
$fk_cidade = upsert_and_get_id(
    $conn, 
    'cidade', 
    ['nome' => $city, 'fk_estado' => $fk_estado], 
    ['nome', 'fk_estado'], 
    'id_cidade'
);


// 2.3 BAIRRO (NOME e FK_CIDADE)
// Tabela: bairro, Coluna ID: id_bairro, Colunas Únicas: nome, fk_cidade
$fk_bairro = upsert_and_get_id(
    $conn, 
    'bairro', 
    ['nome' => $neighborhood, 'fk_cidade' => $fk_cidade], 
    ['nome', 'fk_cidade'], 
    'id_bairro'
);


// 2.4 CEP (NUMERO_CEP, LOGRADOURO e FK_BAIRRO)
// Tabela: cep, Coluna ID: id_cep, Colunas Únicas: numero_cep, fk_bairro
// Nota: O logradouro é inserido/atualizado, mas as chaves únicas são numero_cep e fk_bairro
$fk_id_cep = upsert_and_get_id(
    $conn, 
    'cep', 
    ['numero_cep' => $cep, 'logradouro' => $street, 'fk_bairro' => $fk_bairro], 
    ['numero_cep', 'fk_bairro'], 
    'id_cep'
);


// =========================================================
// 3. INSERÇÃO DO USUÁRIO (COM PREPARED STATEMENT)
// =========================================================

// Usando o hash seguro para a senha (IMPRESCINDÍVEL)
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql_user = "INSERT INTO usuario 
             (nome, cpf, email, senha, tipo_usuario, numero_residencia, complemento_residencia, fk_id_cep) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; 

$stmt_user = $conn->prepare($sql_user);

if (!$stmt_user) {
    json_response(false, "Erro na preparação da inserção do USUÁRIO: " . $conn->error, $conn);
}

// 'ssssisis' => 8 campos: s(string), s(string), s(string), s(string), i(int), s(string), s(string), i(int)
$stmt_user->bind_param(
    "ssssisis", 
    $name, 
    $cpf, 
    $email, 
    $hashed_password, 
    $tipo_usuario, 
    $number, 
    $complemento_residencia, 
    $fk_id_cep
);

if($stmt_user->execute()){
    // Cadastro finalizado com sucesso
    json_response(true, "Usuário cadastrado com sucesso!", $conn);
} else {
    // Erro na inserção do usuário (ex: CPF ou Email duplicado se houver UNIQUE)
    json_response(false, "Erro ao inserir USUÁRIO: " . $stmt_user->error, $conn);
}

// O json_response() encerra o script, mas é bom fechar o stmt
$stmt_user->close();
?>