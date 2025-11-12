<?php
header('Content-Type: application/json');
session_start();

// Configurações de conexão
$host = '127.0.0.1';
$dbname = 'heliomax';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na conexão com o banco de dados.']);
    exit;
}

$response = ['success' => false, 'message' => 'Requisição inválida.'];

// Verificação de segurança - usuário deve estar logado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    $response['message'] = 'Acesso não autorizado. Faça login primeiro.';
    echo json_encode($response);
    exit;
}

$id_usuario_logado = $_SESSION['usuario_id'];
$metodo = $_SERVER['REQUEST_METHOD'];

/**
 * Função para buscar ou criar a hierarquia de endereço (Estado, Cidade, Bairro, CEP).
 * Verifica se o CEP já existe antes de tentar inserir.
 * @return string Retorna o número do CEP formatado (string), pois ele é usado como FK_ID_CEP.
 */
function getOrCreateCepId(PDO $pdo, $dados)
{
    // 1. ESTADO
    $stmt = $pdo->prepare("SELECT ID_ESTADO FROM estado WHERE UF = ?");
    $stmt->execute([$dados->estado_uf]);
    $estado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estado) {
        $stmt = $pdo->prepare("INSERT INTO estado (UF) VALUES (?)");
        $stmt->execute([$dados->estado_uf]);
        $id_estado = $pdo->lastInsertId();
    } else {
        $id_estado = $estado['ID_ESTADO'];
    }

    // 2. CIDADE
    $stmt = $pdo->prepare("SELECT ID_CIDADE FROM cidade WHERE NOME = ? AND FK_ESTADO = ?");
    $stmt->execute([$dados->cidade_nome, $id_estado]);
    $cidade = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cidade) {
        $stmt = $pdo->prepare("INSERT INTO cidade (NOME, FK_ESTADO) VALUES (?, ?)");
        $stmt->execute([$dados->cidade_nome, $id_estado]);
        $id_cidade = $pdo->lastInsertId();
    } else {
        $id_cidade = $cidade['ID_CIDADE'];
    }

    // 3. BAIRRO
    $stmt = $pdo->prepare("SELECT ID_BAIRRO FROM bairro WHERE NOME = ? AND FK_CIDADE = ?");
    $stmt->execute([$dados->bairro_nome, $id_cidade]);
    $bairro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bairro) {
        $stmt = $pdo->prepare("INSERT INTO bairro (NOME, FK_CIDADE) VALUES (?, ?)");
        $stmt->execute([$dados->bairro_nome, $id_cidade]);
        $id_bairro = $pdo->lastInsertId();
    } else {
        $id_bairro = $bairro['ID_BAIRRO'];
    }

    // 4. CEP (Verifica se já existe antes de inserir)
    $cep_formatado = preg_replace('/[^0-9]/', '', $dados->cep);

    $stmt = $pdo->prepare("SELECT ID_CEP FROM cep WHERE ID_CEP = ?");
    $stmt->execute([$cep_formatado]);
    $cep = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cep) {
        $stmt = $pdo->prepare("INSERT INTO cep (ID_CEP, LOGRADOURO, FK_BAIRRO) VALUES (?, ?, ?)");
        $stmt->execute([
            $cep_formatado,
            $dados->logradouro,
            $id_bairro
        ]);
        return $cep_formatado;
    } else {
        return $cep['ID_CEP'];
    }
}

try {
    switch ($metodo) {

        // --- (READ) Buscar pontos favoritos DO USUÁRIO LOGADO ---
        case 'GET':
            if (isset($_GET['id'])) {
                // Buscar um favorito específico (apenas se pertencer ao usuário)
                $stmt = $pdo->prepare("
                    SELECT 
                        pf.ID_PONTO_INTERESSE,
                        pf.NOME,
                        pf.DESCRICAO,
                        pf.NUMERO_RESIDENCIA,
                        pf.COMPLEMENTO_ENDERECO,
                        pf.LATITUDE,
                        pf.LONGITUDE,
                        c.LOGRADOURO,
                        c.ID_CEP as CEP,
                        b.NOME as bairro,
                        ci.NOME as cidade,
                        e.UF,
                        upf.FK_USUARIO_ID_USER
                    FROM ponto_favorito pf
                    LEFT JOIN cep c ON pf.FK_ID_CEP = c.ID_CEP
                    LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
                    LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
                    LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
                    INNER JOIN usuario_ponto_favorito upf ON pf.ID_PONTO_INTERESSE = upf.FK_PONTOS_FAV_ID_PONTO_INTERESSE
                    WHERE pf.ID_PONTO_INTERESSE = ? AND upf.FK_USUARIO_ID_USER = ?
                ");
                $stmt->execute([$_GET['id'], $id_usuario_logado]);
                $favorito = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($favorito) {
                    $response = ['success' => true, 'data' => $favorito];
                } else {
                    http_response_code(404);
                    throw new Exception('Ponto favorito não encontrado ou você não tem permissão para acessá-lo.');
                }
            } else {
                // Buscar APENAS os favoritos do usuário logado
                $stmt = $pdo->prepare("
                    SELECT 
                        pf.ID_PONTO_INTERESSE,
                        pf.NOME,
                        pf.DESCRICAO,
                        pf.NUMERO_RESIDENCIA,
                        pf.COMPLEMENTO_ENDERECO,
                        pf.LATITUDE,
                        pf.LONGITUDE,
                        c.LOGRADOURO,
                        b.NOME as bairro,
                        ci.NOME as cidade,
                        e.UF
                    FROM ponto_favorito pf
                    LEFT JOIN cep c ON pf.FK_ID_CEP = c.ID_CEP
                    LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
                    LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
                    LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
                    INNER JOIN usuario_ponto_favorito upf ON pf.ID_PONTO_INTERESSE = upf.FK_PONTOS_FAV_ID_PONTO_INTERESSE
                    WHERE upf.FK_USUARIO_ID_USER = ?
                    ORDER BY pf.NOME
                ");
                $stmt->execute([$id_usuario_logado]);
                $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $favoritos];
            }
            break;

        // --- (CREATE) Adicionar ponto favorito ---
        case 'POST':
            $dados = json_decode(file_get_contents('php://input'));

            if (!isset($dados->nome, $dados->latitude, $dados->longitude, $dados->cep, $dados->estado_uf)) {
                http_response_code(400);
                throw new Exception('Nome, coordenadas, CEP e Estado são obrigatórios para o cadastro.');
            }

            // Validar coordenadas
            $lat = floatval($dados->latitude);
            $lng = floatval($dados->longitude);

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                http_response_code(400);
                throw new Exception('Coordenadas inválidas.');
            }

            // VERIFICAR SE O USUÁRIO JÁ TEM UM PONTO COM ESSAS COORDENADAS
            $stmt = $pdo->prepare("
                SELECT pf.NOME, pf.DESCRICAO 
                FROM ponto_favorito pf
                INNER JOIN usuario_ponto_favorito upf ON pf.ID_PONTO_INTERESSE = upf.FK_PONTOS_FAV_ID_PONTO_INTERESSE
                WHERE upf.FK_USUARIO_ID_USER = ?
                AND ABS(pf.LATITUDE - ?) < 0.0001 
                AND ABS(pf.LONGITUDE - ?) < 0.0001
            ");
            $stmt->execute([$id_usuario_logado, $lat, $lng]);
            $ponto_existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ponto_existente) {
                http_response_code(409); // Conflict
                throw new Exception('Você já possui um ponto favorito cadastrado nesta localização: "' . $ponto_existente['NOME'] . '". Não é possível cadastrar o mesmo local duas vezes.');
            }

            $pdo->beginTransaction();

            // 1. Obter ID do CEP
            $cep_id = getOrCreateCepId($pdo, $dados);

            if ($cep_id === null) {
                throw new Exception('Falha ao obter o ID do CEP. Certifique-se de que os campos de endereço estão preenchidos corretamente.');
            }

            // 2. Inserir ponto favorito
            $stmt = $pdo->prepare("
                INSERT INTO ponto_favorito 
                (NOME, DESCRICAO, NUMERO_RESIDENCIA, LATITUDE, LONGITUDE, FK_ID_CEP)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $dados->nome,
                $dados->descricao ?? null,
                $dados->numero_residencia ?? null,
                $lat,
                $lng,
                $cep_id
            ]);

            $ponto_id = $pdo->lastInsertId();

            // 3. Associar com o usuário LOGADO
            $stmt = $pdo->prepare("
                INSERT INTO usuario_ponto_favorito (FK_USUARIO_ID_USER, FK_PONTOS_FAV_ID_PONTO_INTERESSE)
                VALUES (?, ?)
            ");
            $stmt->execute([$id_usuario_logado, $ponto_id]);

            $pdo->commit();

            $response = [
                'success' => true,
                'message' => 'Ponto favorito cadastrado com sucesso!',
                'new_id' => $ponto_id
            ];
            http_response_code(201);
            break;

        // --- (UPDATE) Atualizar ponto favorito ---
        case 'PUT':
            $id_favorito = $_GET['id'] ?? null;
            $dados = json_decode(file_get_contents('php://input'));

            if (!$id_favorito || !isset($dados->nome, $dados->latitude, $dados->longitude)) {
                http_response_code(400);
                throw new Exception('Dados de atualização incompletos.');
            }

            // Verifica se o favorito pertence ao usuário logado
            $stmt = $pdo->prepare("
                SELECT 1 FROM usuario_ponto_favorito 
                WHERE FK_PONTOS_FAV_ID_PONTO_INTERESSE = ? AND FK_USUARIO_ID_USER = ?
            ");
            $stmt->execute([$id_favorito, $id_usuario_logado]);

            if (!$stmt->fetch()) {
                http_response_code(403);
                throw new Exception('Você não tem permissão para editar este ponto favorito.');
            }

            // Validar coordenadas
            $lat = floatval($dados->latitude);
            $lng = floatval($dados->longitude);

            // Revalida e atualiza o FK_ID_CEP se os dados de endereço vierem
            $cep_id = null;
            if (isset($dados->cep, $dados->estado_uf)) {
                $pdo->beginTransaction();
                $cep_id = getOrCreateCepId($pdo, $dados);
                $pdo->commit();
            }

            // Atualizar ponto favorito
            $sql = "
                UPDATE ponto_favorito 
                SET NOME = ?, 
                    DESCRICAO = ?, 
                    NUMERO_RESIDENCIA = ?,
                    LATITUDE = ?,
                    LONGITUDE = ?
                    " . ($cep_id !== null ? ", FK_ID_CEP = ?" : "") . "
                WHERE ID_PONTO_INTERESSE = ?
            ";

            $params = [
                $dados->nome,
                $dados->descricao ?? null,
                $dados->numero_residencia ?? null,
                $lat,
                $lng,
            ];

            if ($cep_id !== null) {
                $params[] = $cep_id;
            }
            $params[] = $id_favorito;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $response = ['success' => true, 'message' => 'Ponto favorito atualizado com sucesso.'];
            break;

        // --- (DELETE) Excluir ponto favorito ---
        case 'DELETE':
            $id_favorito = $_GET['id'] ?? null;

            if (!$id_favorito) {
                http_response_code(400);
                throw new Exception('ID do favorito não fornecido para exclusão.');
            }

            // Verifica se o favorito pertence ao usuário logado
            $stmt = $pdo->prepare("
                SELECT 1 FROM usuario_ponto_favorito 
                WHERE FK_PONTOS_FAV_ID_PONTO_INTERESSE = ? AND FK_USUARIO_ID_USER = ?
            ");
            $stmt->execute([$id_favorito, $id_usuario_logado]);

            if (!$stmt->fetch()) {
                http_response_code(403);
                throw new Exception('Você não tem permissão para excluir este ponto favorito.');
            }

            $pdo->beginTransaction();

            // Remove a associação usuário-favorito
            $stmt = $pdo->prepare("
                DELETE FROM usuario_ponto_favorito 
                WHERE FK_PONTOS_FAV_ID_PONTO_INTERESSE = ? AND FK_USUARIO_ID_USER = ?
            ");
            $stmt->execute([$id_favorito, $id_usuario_logado]);

            // Remove o ponto favorito
            $stmt = $pdo->prepare("DELETE FROM ponto_favorito WHERE ID_PONTO_INTERESSE = ?");
            $stmt->execute([$id_favorito]);

            $pdo->commit();

            $response = ['success' => true, 'message' => 'Ponto favorito excluído com sucesso.'];
            break;

        default:
            http_response_code(405);
            $response['message'] = 'Método de requisição não permitido.';
            break;
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Mantém o código HTTP já definido nos catches acima
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>