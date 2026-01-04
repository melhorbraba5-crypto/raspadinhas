<?php
// public/create_demo_account.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// 1. Verificar se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redireciona de volta para a página de criação de conta demo
    header("Location: /index.php?page=contasdemo");
    exit();
}

// 2. Capturar e validar os dados do formulário
$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$win_rate = filter_input(INPUT_POST, 'win_rate', FILTER_VALIDATE_FLOAT);

$validation_errors = [];
if (!$name) {
    $validation_errors[] = 'O campo "Nome do Usuário" é obrigatório.';
}
if (!$email) {
    $validation_errors[] = 'O campo "Email" é obrigatório.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $validation_errors[] = 'Formato de email inválido.';
}
// O valor pode ser NULL no banco, então validamos se foi enviado
if ($win_rate !== false && ($win_rate < 0 || $win_rate > 100)) {
    $validation_errors[] = 'A "Porcentagem de Ganho" deve ser um número entre 0 e 100.';
}

// Se houver erros, armazena-os na sessão e redireciona
if (!empty($validation_errors)) {
    $_SESSION['form_feedback'] = [
        'type' => 'error',
        'message' => 'Falha na validação dos campos.',
        'details' => $validation_errors
    ];
    header("Location: /index.php?page=contasdemo");
    exit();
}

try {
    global $pdo;

    // 3. Verificar se o email já existe
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['form_feedback'] = [
            'type' => 'error',
            'message' => 'Este email já está cadastrado.'
        ];
        header("Location: /index.php?page=contasdemo");
        exit();
    }

    // 4. Gerar uma senha aleatória e um código de referência único
    $password = substr(bin2hex(random_bytes(6)), 0, 8); // Senha aleatória de 8 caracteres
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $referral_code = null;
    $max_attempts = 10;
    for ($i = 0; $i < $max_attempts; $i++) {
        $potential_code = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $stmt_check_code = $pdo->prepare('SELECT id FROM users WHERE referral_code = ?');
        $stmt_check_code->execute([$potential_code]);
        if (!$stmt_check_code->fetch()) {
            $referral_code = $potential_code;
            break;
        }
    }

    if (is_null($referral_code)) {
        $_SESSION['form_feedback'] = [
            'type' => 'error',
            'message' => 'Não foi possível gerar um código de referência único. Tente novamente.'
        ];
        header("Location: /index.php?page=contasdemo");
        exit();
    }

    // 5. Inserir o novo usuário demo no banco de dados com os valores corretos
    $stmt = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, referral_code, is_demo, demo_win_rate, level_id)
        VALUES (?, ?, ?, ?, TRUE, ?, ?)
    ');

    // Assumimos que o level_id para contas demo é 1, como na sua tabela.
    $default_level_id = 1;

    $stmt->execute([$name, $email, $password_hash, $referral_code, $win_rate, $default_level_id]);

    // 6. Armazenar o feedback na sessão antes do redirecionamento
    $_SESSION['form_feedback'] = [
        'type' => 'success',
        'message' => 'Conta de demonstração criada com sucesso!',
        'account_details' => [
            'email' => $email,
            'password' => $password
        ]
    ];

    // 7. Redirecionar de volta para a página de criação de conta demo
    header("Location: /index.php?page=contasdemo");
    exit();

} catch (PDOException $e) {
    error_log("Erro ao criar conta demo: " . $e->getMessage());
    $_SESSION['form_feedback'] = [
        'type' => 'error',
        'message' => 'Erro no banco de dados ao criar a conta. Tente novamente.'
    ];
    header("Location: /index.php?page=contasdemo");
    exit();
} catch (Exception $e) {
    error_log("Erro inesperado: " . $e->getMessage());
    $_SESSION['form_feedback'] = [
        'type' => 'error',
        'message' => 'Ocorreu um erro inesperado. Tente novamente.'
    ];
    header("Location: /index.php?page=contasdemo");
    exit();
}