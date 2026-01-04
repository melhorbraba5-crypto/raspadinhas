<?php
// login.php
session_start();

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';
// Credenciais fixas (IMPORTANTE: para um sistema real, use um banco de dados com senhas hasheadas)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', '102030'); // Em produção, armazene hashes de senha no banco de dados!

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Credenciais fixas, para um sistema real, você buscaria em um banco de dados
    // e verificaria a senha usando password_verify()
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $user;
        header("Location: index.php");
        exit;
    } else {
        $error = 'Usuário ou senha inválidos.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Casino Console</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Variáveis de Cores (Ajustadas para o tema Casino Vibrante) */
        :root {
            --bg-dark-primary: #000000; /* Fundo mais escuro possível */
            --bg-dark-secondary: #0A0A15; /* Fundo do container, um preto muito suave */
            --border-highlight: #1A1A30; /* Borda sutil de elementos */

            --text-light: #E8E8FF; /* Texto principal bem claro */
            --text-medium: #80809C; /* Texto secundário, cinza-azulado */

            --accent-neon-blue: #00FFFF; /* Ciano elétrico/Azul neon */
            --accent-neon-green: #39FF14; /* Verde neon vibrante */
            --accent-neon-purple: #BF00FF; /* Roxo neon */
            --accent-red-casino: #FF0055; /* Vermelho vibrante/cereja */

            --button-bg-gradient-start: var(--accent-neon-blue);
            --button-bg-gradient-end: var(--accent-neon-purple);
            --button-hover-gradient-start: var(--accent-neon-green);
            --button-hover-gradient-end: var(--accent-neon-blue);

            --shadow-pop: rgba(0, 0, 0, 0.8); /* Sombra mais escura para elementos que "saltam" */
            --glow-strong: rgba(255, 255, 255, 0.05); /* Brilho suave */
        }

        /* Base Body e Reset */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-light);
            overflow: hidden;
            position: relative;
        }

        /* Efeito de Fundo Abstrato/Sutil (Partículas com Brilho) */
        .background-effect {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            /* Combina um gradiente sutil com um filtro de ruído */
            background: radial-gradient(circle at center, #05050D 0%, #000000 100%);
            /* Opcional: Adicionar um padrão de grade muito sutil ou ruído */
            /* filter: url(#noiseFilter); /* Exige SVG filter no HTML */
            /* background-image: url('data:image/svg+xml;utf8,<svg ... grade pattern ...>'); */
            opacity: 0.9;
            pointer-events: none;
        }
        /* SVG Filter para ruído (adicionar no HTML depois do <body>) */

        /* Contêiner do Login */
        .login-container {
            background-color: var(--bg-dark-secondary);
            border: 1px solid var(--border-highlight);
            border-radius: 15px; /* Arredondamento elegante */
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 450px; /* Mais largo para presença */
            box-shadow: 0 10px 40px var(--shadow-pop);
            text-align: center;
            position: relative;
            z-index: 1;
            animation: fadeInScaleUp 0.8s ease-out forwards;
            overflow: hidden; /* Para o brilho interno */
        }

        @keyframes fadeInScaleUp {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Brilho interno do container */
        .login-container::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            right: -50px;
            bottom: -50px;
            background: radial-gradient(circle, var(--glow-strong) 0%, transparent 70%);
            opacity: 0.7;
            pointer-events: none;
            z-index: -1;
            animation: pulseGlow 4s infinite alternate ease-in-out;
        }
        @keyframes pulseGlow {
            from { transform: scale(1); opacity: 0.7; }
            to { transform: scale(1.05); opacity: 0.8; }
        }

        .login-container h2 {
            color: var(--text-light); /* Título principal */
            font-size: 2.8rem; /* Título grande */
            margin-bottom: 2.5rem;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.1); /* Sombra de texto sutil */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            letter-spacing: 0.05em;
        }
        .login-container h2 .bi {
            font-size: 2.5rem;
            color: var(--accent-neon-green); /* Ícone com cor neon */
            text-shadow: 0 0 15px var(--accent-neon-green); /* Brilho neon no ícone */
        }

        /* Grupos de Formulário */
        .form-group {
            margin-bottom: 1.8rem;
            text-align: left;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.8rem;
            color: var(--text-medium);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 15px 18px; /* Padding maior */
            padding-left: 50px; /* Espaço para o ícone */
            background-color: var(--border-highlight); /* Fundo do input, cor da borda do container */
            border: 1px solid var(--border-highlight);
            border-radius: 10px;
            color: var(--text-light);
            font-size: 1.05rem;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.3); /* Sombra interna para profundidade */
        }

        .form-group input:focus {
            border-color: var(--accent-neon-blue);
            background-color: #12121A; /* Fundo mais escuro no foco */
            box-shadow: 0 0 0 4px rgba(0, 255, 255, 0.2), inset 0 2px 5px rgba(0,0,0,0.5); /* Brilho neon no foco */
        }

        .form-group .bi {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(calc(-50% + 10px));
            color: var(--text-medium);
            font-size: 1.3rem; /* Ícone maior */
            pointer-events: none;
        }

        /* Mensagem de Erro */
        p.error {
            background-color: rgba(var(--accent-red-casino), 0.15); /* Fundo vermelho translúcido */
            color: var(--accent-red-casino);
            border: 1px solid var(--accent-red-casino);
            border-radius: 10px;
            padding: 1.2rem;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            justify-content: center;
            box-shadow: 0 0 10px rgba(255, 0, 85, 0.3); /* Brilho de erro */
        }
        p.error .bi {
            font-size: 1.4rem;
        }

        /* Botão de Entrar */
        button[type="submit"] {
            width: 100%;
            padding: 18px; /* Padding grande */
            background: linear-gradient(45deg, var(--button-bg-gradient-start), var(--button-bg-gradient-end)); /* Gradiente neon */
            color: var(--bg-dark-primary); /* Texto escuro no botão */
            border: none;
            border-radius: 10px;
            font-size: 1.3rem; /* Texto maior */
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            box-shadow: 0 5px 30px rgba(var(--accent-neon-blue), 0.6); /* Sombra com brilho */
        }

        button[type="submit"]:hover {
            background: linear-gradient(45deg, var(--button-hover-gradient-start), var(--button-hover-gradient-end));
            transform: translateY(-4px); /* Efeito de levitar mais pronunciado */
            box-shadow: 0 8px 40px rgba(var(--accent-neon-green), 0.7); /* Sombra de hover mais forte */
        }

        button[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
        }
        @media (max-width: 500px) {
        .login-container {
            padding: 2rem 1.5rem; /* Reduz o padding para telas menores */
            max-width: 90%; /* Ocupa 90% da largura da tela */
            border-radius: 10px; /* Arredondamento sutil */
        }

        .login-container h2 {
            font-size: 2rem; /* Diminui o tamanho do título */
            margin-bottom: 2rem; /* Ajusta o espaçamento */
        }
        .login-container h2 .bi {
            font-size: 2rem; /* Diminui o ícone */
        }

        .form-group {
            margin-bottom: 1.5rem; /* Reduz o espaçamento entre os campos */
        }

        .form-group input {
            padding: 12px 15px; /* Reduz o padding do input */
            padding-left: 45px; /* Ajusta o espaço do ícone */
            font-size: 1rem; /* Diminui o tamanho da fonte */
        }

        .form-group .bi {
            font-size: 1.2rem; /* Diminui o tamanho do ícone */
            left: 15px;
            transform: translateY(calc(-50% + 5px)); /* Reajusta a posição do ícone */
        }

        p.error {
            padding: 1rem; /* Reduz o padding da mensagem de erro */
            font-size: 0.9rem; /* Diminui o tamanho da fonte */
        }
        p.error .bi {
            font-size: 1.2rem;
        }

        button[type="submit"] {
            padding: 15px; /* Reduz o padding do botão */
            font-size: 1.1rem; /* Diminui o tamanho da fonte do botão */
        }
    }

    @media (max-width: 380px) {
        .login-container {
            padding: 1.5rem 1rem; /* Padding mínimo para telas muito pequenas */
        }

        .login-container h2 {
            font-size: 1.8rem;
        }

        button[type="submit"] {
            font-size: 1rem;
        }
    }
    </style>
</head>
<body>
    <div class="background-effect"></div> <div class="login-container">
        <h2><i class="bi bi-gem"></i> Admin Login</h2> <form action="login.php" method="post">
            <div class="form-group">
                <label for="username">Usuário</label>
                <i class="bi bi-person-fill"></i>
                <input type="text" name="username" id="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <i class="bi bi-lock-fill"></i>
                <input type="password" name="password" id="password" required autocomplete="current-password">
            </div>
            <?php if ($error): ?>
                <p class="error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>