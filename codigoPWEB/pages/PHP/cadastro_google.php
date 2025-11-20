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
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se o número de dígitos é 11
    if (strlen($cpf) != 11) return false;
    
    // Verifica se todos os dígitos são iguais (ex: 111.111.111-11)
    if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    
    // Cálculo dos dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

// Processa o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_cadastro'])) {
    
    // 1. Coleta e validação dos dados
    $cpf_raw = trim(str_replace(['.', '-'], '', $_POST['cpf']));
    $cep = trim(str_replace('-', '', $_POST['cep']));
    $numero_residencia = trim($_POST['numero_residencia']);
    $complemento_endereco = trim($_POST['complemento_endereco'] ?? '');

    if (empty($cpf_raw) || empty($cep) || empty($numero_residencia)) {
        $error_message = "Por favor, preencha todos os campos obrigatórios.";
    } elseif (!validarCPF($cpf_raw)) { // Validação do CPF
        $error_message = "CPF inválido. Por favor, insira um CPF real.";
    } else {
        $cpf = $cpf_raw; // CPF limpo e validado

        // 2. Busca o FK_ID_CEP no banco de dados
        $fk_id_cep = null;
        $sql_cep = "SELECT ID_CEP FROM cep WHERE CEP = ?";
        if ($stmt_cep = mysqli_prepare($link, $sql_cep)) {
            mysqli_stmt_bind_param($stmt_cep, "s", $cep);
            mysqli_stmt_execute($stmt_cep);
            mysqli_stmt_store_result($stmt_cep);
            
            if (mysqli_stmt_num_rows($stmt_cep) == 1) {
                mysqli_stmt_bind_result($stmt_cep, $fk_id_cep);
                mysqli_stmt_fetch($stmt_cep);
            } else {
                 $error_message = "CEP não encontrado. Por favor, insira um CEP válido cadastrado na base de dados.";
            }
            mysqli_stmt_close($stmt_cep);
        }
        
        // 3. Cadastra o novo usuário
        if (empty($error_message)) {
            $senha_dummy = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $tipo_usuario = 0; 

            $sql_insert = "INSERT INTO usuario (NOME, CPF, EMAIL, SENHA, TIPO_USUARIO, NUMERO_RESIDENCIA, COMPLEMENTO_ENDERECO, FK_ID_CEP) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                mysqli_stmt_bind_param($stmt_insert, "ssssisss", $nome, $cpf, $email, $senha_dummy, $tipo_usuario, $numero_residencia, $complemento_endereco, $fk_id_cep);
                
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
        /* Ajuste de espaçamento para o card, para ficar mais parecido com o login */
        .login-card {
            padding: 40px 30px; /* Redefine o padding interno para aumentar o espaçamento */
        }
        .login-header h2, .login-header p {
            margin-bottom: 5px; /* Reduz o espaço entre o título e a descrição */
        }
        .login-form .form-group {
            margin-bottom: 20px; /* Garante um bom espaçamento entre os campos */
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
                               value="<?php echo $_POST['cep'] ?? ''; ?>" oninput="formatarCEP(this)">
                        <label for="cep">CEP (obrigatório)</label>
                    </div>
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
        // Função para manter apenas dígitos
        function limpar(valor) {
            return valor.replace(/\D/g, "");
        }

        // Máscara de CPF: 000.000.000-00
        function formatarCPF(campo) {
            let valor = limpar(campo.value);
            let formatado = valor.replace(/(\d{3})(\d)/, '$1.$2')
                                   .replace(/(\d{3})(\d)/, '$1.$2')
                                   .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            campo.value = formatado.substring(0, 14);
        }

        // Máscara de CEP: 00000-000
        function formatarCEP(campo) {
            let valor = limpar(campo.value);
            let formatado = valor.replace(/(\d{5})(\d)/, '$1-$2');
            campo.value = formatado.substring(0, 9);
        }

        // Aplica as máscaras ao carregar a página (se houver valores prévios)
        document.addEventListener('DOMContentLoaded', function() {
            const cpfField = document.getElementById('cpf');
            const cepField = document.getElementById('cep');

            if (cpfField.value) formatarCPF(cpfField);
            if (cepField.value) formatarCEP(cepField);

            // Adiciona validação extra para o campo Número da Residência
            const numeroResidenciaField = document.getElementById('numero_residencia');
            if (numeroResidenciaField) {
                 numeroResidenciaField.addEventListener('keypress', function (e) {
                     // Permite apenas dígitos
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

email