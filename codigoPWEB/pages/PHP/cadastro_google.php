<?php
// Arquivo: cadastro_google.php
require_once 'config.php';

if (!isset($_SESSION['google_data'])) {
    header('Location: ../PHP/login.php');
    exit;
}

$google_data = $_SESSION['google_data'];
$nome = htmlspecialchars($google_data['nome']);
$email = htmlspecialchars($google_data['email']);
$error_message = '';

// Função de Validação Básica de CPF (PHP)
function validarCPF($cpf)
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11)
        return false;
    if (preg_match('/^(\d)\1{10}$/', $cpf))
        return false;
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d)
            return false;
    }
    return true;
}

// Função para buscar CEP na API ViaCEP
function buscarCepViaCEP($cep)
{
    $cep = preg_replace('/[^0-9]/', '', $cep);
    if (strlen($cep) != 8)
        return null;

    $url = "https://viacep.com.br/ws/{$cep}/json/";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false)
        return null;

    $data = json_decode($response, true);

    if (isset($data['erro']) && $data['erro'] === true)
        return null;

    return $data;
}

// Função para obter ou cadastrar Estado
function obterOuCadastrarEstado($link, $uf)
{
    if (empty($uf))
        return null;

    // Busca estado existente
    $sql = "SELECT ID_ESTADO FROM estado WHERE UF = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $uf);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return $id;
        }
        mysqli_stmt_close($stmt);
    }

    // Cadastra novo estado
    $sql = "INSERT INTO estado (UF) VALUES (?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $uf);
        if (mysqli_stmt_execute($stmt)) {
            $id = mysqli_insert_id($link);
            mysqli_stmt_close($stmt);
            return $id;
        }
        mysqli_stmt_close($stmt);
    }
    return null;
}

// Função para obter ou cadastrar Cidade
function obterOuCadastrarCidade($link, $nome_cidade, $fk_estado)
{
    if (empty($nome_cidade) || $fk_estado === null)
        return null;

    // Busca cidade existente
    $sql = "SELECT ID_CIDADE FROM cidade WHERE NOME = ? AND FK_ESTADO = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $nome_cidade, $fk_estado);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return $id;
        }
        mysqli_stmt_close($stmt);
    }

    // Cadastra nova cidade
    $sql = "INSERT INTO cidade (NOME, FK_ESTADO) VALUES (?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $nome_cidade, $fk_estado);
        if (mysqli_stmt_execute($stmt)) {
            $id = mysqli_insert_id($link);
            mysqli_stmt_close($stmt);
            return $id;
        }
        mysqli_stmt_close($stmt);
    }
    return null;
}

// Função para obter ou cadastrar Bairro
function obterOuCadastrarBairro($link, $nome_bairro, $fk_cidade)
{
    if ($fk_cidade === null)
        return null;

    // Se bairro vazio, usar string vazia
    $nome_bairro = $nome_bairro ?: '';

    // Busca bairro existente
    $sql = "SELECT ID_BAIRRO FROM bairro WHERE NOME = ? AND FK_CIDADE = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $nome_bairro, $fk_cidade);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return $id;
        }
        mysqli_stmt_close($stmt);
    }

    // Cadastra novo bairro
    $sql = "INSERT INTO bairro (NOME, FK_CIDADE) VALUES (?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $nome_bairro, $fk_cidade);
        if (mysqli_stmt_execute($stmt)) {
            $id = mysqli_insert_id($link);
            mysqli_stmt_close($stmt);
            return $id;
        }
        mysqli_stmt_close($stmt);
    }
    return null;
}

// Função para obter ou cadastrar CEP
function obterOuCadastrarCep($link, $cep)
{
    $cep_limpo = preg_replace('/[^0-9]/', '', $cep);

    // 1. Verifica se o CEP já existe no banco
    $sql = "SELECT ID_CEP FROM cep WHERE CEP = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $cep_limpo);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id_cep);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return ['success' => true, 'id_cep' => $id_cep];
        }
        mysqli_stmt_close($stmt);
    }

    // 2. Busca na API ViaCEP
    $dados = buscarCepViaCEP($cep_limpo);

    if ($dados === null) {
        return ['success' => false, 'error' => 'CEP não encontrado. Verifique se o CEP está correto.'];
    }

    // 3. Cadastra em cascata: Estado -> Cidade -> Bairro -> CEP
    $uf = $dados['uf'] ?? '';
    $cidade = $dados['localidade'] ?? '';
    $bairro = $dados['bairro'] ?? '';
    $logradouro = $dados['logradouro'] ?? '';

    // Cadastra Estado
    $id_estado = obterOuCadastrarEstado($link, $uf);
    if ($id_estado === null) {
        return ['success' => false, 'error' => 'Erro ao cadastrar estado.'];
    }

    // Cadastra Cidade
    $id_cidade = obterOuCadastrarCidade($link, $cidade, $id_estado);
    if ($id_cidade === null) {
        return ['success' => false, 'error' => 'Erro ao cadastrar cidade.'];
    }

    // Cadastra Bairro
    $id_bairro = obterOuCadastrarBairro($link, $bairro, $id_cidade);
    if ($id_bairro === null) {
        return ['success' => false, 'error' => 'Erro ao cadastrar bairro.'];
    }

    // Cadastra CEP
    $sql = "INSERT INTO cep (CEP, LOGRADOURO, FK_BAIRRO) VALUES (?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssi", $cep_limpo, $logradouro, $id_bairro);

        if (mysqli_stmt_execute($stmt)) {
            $id_cep = mysqli_insert_id($link);
            mysqli_stmt_close($stmt);
            return ['success' => true, 'id_cep' => $id_cep];
        } else {
            mysqli_stmt_close($stmt);
            return ['success' => false, 'error' => 'Erro ao cadastrar CEP: ' . mysqli_error($link)];
        }
    }

    return ['success' => false, 'error' => 'Erro de preparação da query de CEP.'];
}

// Processa o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_cadastro'])) {

    $cpf_raw = trim(str_replace(['.', '-'], '', $_POST['cpf']));
    $cep = trim(str_replace('-', '', $_POST['cep']));
    $numero_residencia = trim($_POST['numero_residencia']);
    $complemento_endereco = trim($_POST['complemento_endereco'] ?? '');

    if (empty($cpf_raw) || empty($cep) || empty($numero_residencia)) {
        $error_message = "Por favor, preencha todos os campos obrigatórios.";
    } elseif (!validarCPF($cpf_raw)) {
        $error_message = "CPF inválido. Por favor, insira um CPF real.";
    } else {
        $cpf = $cpf_raw;

        // Obtém ou cadastra o CEP usando ViaCEP
        $resultado_cep = obterOuCadastrarCep($link, $cep);

        if (!$resultado_cep['success']) {
            $error_message = $resultado_cep['error'];
        } else {
            $fk_id_cep = $resultado_cep['id_cep'];

            // Cadastra o novo usuário
            $senha_dummy = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $tipo_usuario = 0;

            $sql_insert = "INSERT INTO usuario (NOME, CPF, EMAIL, SENHA, TIPO_USUARIO, NUMERO_RESIDENCIA, COMPLEMENTO_ENDERECO, FK_ID_CEP) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                mysqli_stmt_bind_param($stmt_insert, "ssssissi", $nome, $cpf, $email, $senha_dummy, $tipo_usuario, $numero_residencia, $complemento_endereco, $fk_id_cep);

                if (mysqli_stmt_execute($stmt_insert)) {
                    $id_user = mysqli_insert_id($link);

                    $_SESSION['loggedin'] = true;
                    $_SESSION['usuario_id'] = $id_user;
                    $_SESSION['usuario_nome'] = $nome;
                    $_SESSION['email'] = $email;
                    $_SESSION['tipo_usuario'] = $tipo_usuario;
                    unset($_SESSION['google_data']);

                    mysqli_stmt_close($stmt_insert);
                    mysqli_close($link);

                    header('Location: dashUSER.php');
                    exit;

                } else {
                    $error_message = "Erro ao cadastrar: " . mysqli_error($link);
                }
                mysqli_stmt_close($stmt_insert);
            } else {
                $error_message = "Erro de preparação da query de cadastro.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../images/icon.png">
    <title>Completar Cadastro - HelioMax</title>
    <link rel="stylesheet" href="../../styles/login-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-card {
            padding: 40px 30px;
        }

        .login-header h2,
        .login-header p {
            margin-bottom: 5px;
        }

        .login-form .form-group {
            margin-bottom: 20px;
        }

        .error-message {
            color: #d9534f;
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .endereco-preview {
            background-color: #e8f5e9;
            border: 1px solid #a5d6a7;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .endereco-preview.show {
            display: block;
        }

        .endereco-preview i {
            color: #4caf50;
            margin-right: 8px;
        }

        .endereco-preview.error {
            background-color: #ffebee;
            border-color: #ef9a9a;
        }

        .endereco-preview.error i {
            color: #f44336;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card" style="max-width: 450px;">
            <div class="login-header">
                <a href="../HTML/landpage.html" class="logo-link">
                    <h1 class="logo">⚡HelioMax</h1>
                </a>
                <h2>Quase lá, <?php echo $nome; ?>!</h2>
                <p>Precisamos de mais alguns detalhes para finalizar seu cadastro.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="cadastro_google.php">
                <input type="hidden" name="finalizar_cadastro" value="1">

                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-id-card"></i>
                        <input type="text" id="cpf" name="cpf" placeholder=" " required maxlength="14"
                            value="<?php echo $_POST['cpf'] ?? ''; ?>" oninput="formatarCPF(this)">
                        <label for="cpf">CPF (obrigatório)</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-map-pin"></i>
                        <input type="text" id="cep" name="cep" placeholder=" " required maxlength="9"
                            value="<?php echo $_POST['cep'] ?? ''; ?>" oninput="formatarCEP(this)" onblur="buscarCEP()">
                        <label for="cep">CEP (obrigatório)</label>
                    </div>
                </div>

                <div id="endereco-preview" class="endereco-preview">
                    <i class="fas fa-check-circle"></i>
                    <span id="endereco-texto"></span>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-hashtag"></i>
                        <input type="text" id="numero_residencia" name="numero_residencia" placeholder=" " required
                            maxlength="10" value="<?php echo $_POST['numero_residencia'] ?? ''; ?>" pattern="[0-9]*"
                            title="Apenas números são permitidos.">
                        <label for="numero_residencia">Número da Residência (apenas números)</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-city"></i>
                        <input type="text" id="complemento_endereco" name="complemento_endereco" placeholder=" "
                            maxlength="100" value="<?php echo $_POST['complemento_endereco'] ?? ''; ?>">
                        <label for="complemento_endereco">Complemento (Opcional)</label>
                    </div>
                </div>

                <button type="submit" class="login-button">
                    <span class="button-text">Finalizar Cadastro</span>
                    <i class="fas fa-check button-icon"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        function limpar(valor) {
            return valor.replace(/\D/g, "");
        }

        function formatarCPF(campo) {
            let valor = limpar(campo.value);
            let formatado = valor.replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            campo.value = formatado.substring(0, 14);
        }

        function formatarCEP(campo) {
            let valor = limpar(campo.value);
            let formatado = valor.replace(/(\d{5})(\d)/, '$1-$2');
            campo.value = formatado.substring(0, 9);
        }

        function buscarCEP() {
            const cepField = document.getElementById('cep');
            const cep = limpar(cepField.value);
            const previewDiv = document.getElementById('endereco-preview');
            const previewTexto = document.getElementById('endereco-texto');
            const previewIcon = previewDiv.querySelector('i');

            if (cep.length !== 8) {
                previewDiv.classList.remove('show', 'error');
                return;
            }

            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(data => {
                    if (data.erro) {
                        previewDiv.classList.remove('show');
                        previewDiv.classList.add('show', 'error');
                        previewIcon.className = 'fas fa-times-circle';
                        previewTexto.textContent = 'CEP não encontrado';
                    } else {
                        previewDiv.classList.remove('error');
                        previewDiv.classList.add('show');
                        previewIcon.className = 'fas fa-check-circle';
                        const endereco = `${data.logradouro || 'Logradouro não informado'}, ${data.bairro || 'Bairro não informado'} - ${data.localidade}/${data.uf}`;
                        previewTexto.textContent = endereco;
                    }
                })
                .catch(() => {
                    previewDiv.classList.remove('show', 'error');
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const cpfField = document.getElementById('cpf');
            const cepField = document.getElementById('cep');

            if (cpfField.value) formatarCPF(cpfField);
            if (cepField.value) {
                formatarCEP(cepField);
                buscarCEP();
            }

            const numeroResidenciaField = document.getElementById('numero_residencia');
            if (numeroResidenciaField) {
                numeroResidenciaField.addEventListener('keypress', function (e) {
                    if (e.key.match(/[^0-9]/)) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>

</html>
<?php
if (isset($link) && is_object($link)) {
    mysqli_close($link);
}
?>