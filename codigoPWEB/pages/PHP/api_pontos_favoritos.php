<?php
// PHP/api_pontos_favoritos.php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'protectuser.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = '127.0.0.1';
$dbname = 'heliomax';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro conexao DB']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit;
}

$user_id = $_SESSION['usuario_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'));

// Função auxiliar de CEP (Mantida igual)
function getOrCreateCepId($pdo, $dados)
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

    // 4. CEP
    $stmt = $pdo->prepare("SELECT ID_CEP FROM cep WHERE CEP = ?");
    $stmt->execute([$dados->cep]);
    $cep_por_numero = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cep_por_numero)
        return $cep_por_numero['ID_CEP'];

    $stmt = $pdo->prepare("SELECT ID_CEP FROM cep WHERE LOGRADOURO = ? AND FK_BAIRRO = ?");
    $stmt->execute([$dados->logradouro, $id_bairro]);
    $cep_por_endereco = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cep_por_endereco)
        return $cep_por_endereco['ID_CEP'];

    $stmt = $pdo->prepare("INSERT INTO cep (CEP, LOGRADOURO, FK_BAIRRO) VALUES (?, ?, ?)");
    $stmt->execute([$dados->cep, $dados->logradouro, $id_bairro]);
    return $pdo->lastInsertId();
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // GET ÚNICO
                // Trazemos COMPLEMENTO_ENDERECO pois é lá que guardamos o ícone
                $sql = "SELECT pf.*, 
                            cep.CEP, cep.LOGRADOURO,
                            b.NOME as bairro,
                            c.NOME as cidade,
                            e.UF as ESTADO
                        FROM ponto_favorito pf
                        INNER JOIN usuario_ponto_favorito upf ON pf.ID_PONTO_INTERESSE = upf.FK_PONTOS_FAV_ID_PONTO_INTERESSE
                        LEFT JOIN cep ON pf.FK_ID_CEP = cep.ID_CEP
                        LEFT JOIN bairro b ON cep.FK_BAIRRO = b.ID_BAIRRO
                        LEFT JOIN cidade c ON b.FK_CIDADE = c.ID_CIDADE
                        LEFT JOIN estado e ON c.FK_ESTADO = e.ID_ESTADO
                        WHERE pf.ID_PONTO_INTERESSE = ? AND upf.FK_USUARIO_ID_USER = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_GET['id'], $user_id]);
                $dado = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($dado) {
                    $response = [
                        'ID_PONTO_INTERESSE' => $dado['ID_PONTO_INTERESSE'],
                        'NOME' => $dado['NOME'],
                        'DESCRICAO' => $dado['DESCRICAO'],
                        'LATITUDE' => $dado['LATITUDE'],
                        'LONGITUDE' => $dado['LONGITUDE'],
                        'CEP' => $dado['CEP'],
                        'LOGRADOURO' => $dado['LOGRADOURO'],
                        'NUMERO_RESIDENCIA' => $dado['NUMERO_RESIDENCIA'],
                        'icone' => $dado['COMPLEMENTO_ENDERECO'], // Mapeamos COMPLEMENTO para ícone
                        'bairro' => $dado['bairro'],
                        'cidade' => $dado['cidade'],
                        'UF' => $dado['ESTADO']
                    ];
                    echo json_encode(['success' => true, 'data' => $response]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Não encontrado.']);
                }

            } else {
                // GET LISTA
                $sql = "SELECT pf.*, cep.LOGRADOURO, b.NOME as bairro
                        FROM ponto_favorito pf
                        INNER JOIN usuario_ponto_favorito upf ON pf.ID_PONTO_INTERESSE = upf.FK_PONTOS_FAV_ID_PONTO_INTERESSE
                        LEFT JOIN cep ON pf.FK_ID_CEP = cep.ID_CEP
                        LEFT JOIN bairro b ON cep.FK_BAIRRO = b.ID_BAIRRO
                        WHERE upf.FK_USUARIO_ID_USER = ?
                        ORDER BY pf.NOME ASC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id]);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $result]);
            }
            break;

        case 'POST':
            if (!isset($input->nome) || !isset($input->latitude))
                throw new Exception("Dados faltando.");
            $pdo->beginTransaction();

            $id_cep = getOrCreateCepId($pdo, $input);

            // Salvamos o 'icone' no campo COMPLEMENTO_ENDERECO
            $icone = isset($input->icone) ? $input->icone : 'map-pin';

            $stmt = $pdo->prepare("INSERT INTO ponto_favorito 
                (NOME, DESCRICAO, NUMERO_RESIDENCIA, COMPLEMENTO_ENDERECO, LATITUDE, LONGITUDE, FK_ID_CEP) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $input->nome,
                $input->descricao,
                $input->numero_residencia,
                $icone, // Aqui salvamos o ícone escondido
                $input->latitude,
                $input->longitude,
                $id_cep
            ]);

            $novo_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO usuario_ponto_favorito (FK_USUARIO_ID_USER, FK_PONTOS_FAV_ID_PONTO_INTERESSE) VALUES (?, ?)");
            $stmt->execute([$user_id, $novo_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Salvo com sucesso!']);
            break;

        case 'PUT':
            $id = $_GET['id'] ?? null;
            if (!$id)
                throw new Exception("ID inválido.");

            // Verifica Permissão
            $check = $pdo->prepare("SELECT 1 FROM usuario_ponto_favorito WHERE FK_PONTOS_FAV_ID_PONTO_INTERESSE = ? AND FK_USUARIO_ID_USER = ?");
            $check->execute([$id, $user_id]);
            if (!$check->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
                exit;
            }

            $pdo->beginTransaction();
            $id_cep = getOrCreateCepId($pdo, $input);

            // Salvamos o 'icone' no campo COMPLEMENTO_ENDERECO
            $icone = isset($input->icone) ? $input->icone : 'map-pin';

            $stmt = $pdo->prepare("UPDATE ponto_favorito SET 
                NOME = ?, DESCRICAO = ?, NUMERO_RESIDENCIA = ?, COMPLEMENTO_ENDERECO = ?,
                LATITUDE = ?, LONGITUDE = ?, FK_ID_CEP = ?
                WHERE ID_PONTO_INTERESSE = ?");

            $stmt->execute([
                $input->nome,
                $input->descricao,
                $input->numero_residencia,
                $icone, // Atualiza o ícone aqui
                $input->latitude,
                $input->longitude,
                $id_cep,
                $id
            ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Atualizado!']);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id)
                throw new Exception("ID inválido.");

            $check = $pdo->prepare("SELECT 1 FROM usuario_ponto_favorito WHERE FK_PONTOS_FAV_ID_PONTO_INTERESSE = ? AND FK_USUARIO_ID_USER = ?");
            $check->execute([$id, $user_id]);
            if (!$check->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
                exit;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM usuario_ponto_favorito WHERE FK_PONTOS_FAV_ID_PONTO_INTERESSE = ? AND FK_USUARIO_ID_USER = ?");
            $stmt->execute([$id, $user_id]);
            $stmt = $pdo->prepare("DELETE FROM ponto_favorito WHERE ID_PONTO_INTERESSE = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Excluído!']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Método inválido']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>