<?php
// public/aparencia.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$page = 'aparencia';
$admin_user = $_SESSION['admin_user'] ?? 'Admin';

try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT nome_config, valor_config FROM configuracoes_tema");
    $stmt->execute();
    $configuracoes_atuais = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $_SESSION['form_feedback'] = [
        'type' => 'error',
        'message' => 'Erro ao buscar configurações de aparência: ' . $e->getMessage()
    ];
    $configuracoes_atuais = [];
}

$feedback = $_SESSION['form_feedback'] ?? null;
unset($_SESSION['form_feedback']);

// Define os valores atuais com fallbacks padrão
$corPrimary = $configuracoes_atuais['cor-primary'] ?? '#111111';
$corSecondary = $configuracoes_atuais['cor-secondary'] ?? '#e0e0e0';
$corTertiary = $configuracoes_atuais['cor-tertiary'] ?? '#28e504';
$logoUrl = $configuracoes_atuais['site-logo-url'] ?? 'https://ik.imagekit.io/azx3nlpdu/logo/01K05ABF6P2A9P098AY0REABR5.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gerenciar Aparência</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0D1117; --bg-light: #161B22; --border-color: #30363D;
            --text-primary: #c9d1d9; --text-secondary: #8b949e; --accent-blue: #58A6FF;
            --accent-green: #3FB950; --accent-red: #E24C4C;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: var(--text-primary); margin:0; }
        .dashboard-grid { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; padding: 2rem; min-height: 100vh; }
        .sidebar { background-color: var(--bg-light); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; }
        .admin-profile { text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; }
        .admin-profile .icon { font-size: 4rem; color: var(--accent-blue); }
        .admin-profile h3 { margin: 0.5rem 0 0.2rem 0; }
        .admin-profile p { margin: 0; font-size: 0.9rem; color: var(--text-secondary); }
        .sidebar-nav { list-style: none; padding: 0; margin: 1.5rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-secondary); text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 500; transition: all 0.2s; }
        .sidebar-nav a:hover { background-color: #2a3038; color: var(--text-primary); }
        .sidebar-nav a.active { background-color: var(--accent-blue); color: #fff; font-weight: 600; }
        .logout-btn { margin-top: auto; }
        .logout-btn a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-secondary); text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 500; border: 1px solid var(--text-secondary); }
        .logout-btn a:hover { background-color: #2a3038; color: var(--text-primary); }
        .main-content { overflow-y: auto; }
        .page-header { border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem; }
        .card { background-color: var(--bg-light); padding: 2rem; border-radius: 10px; border: 1px solid var(--border-color); }
        .submit-btn { padding: 0.8rem 1.5rem; background-color: var(--accent-blue); color: #fff; border: none; border-radius: 6px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        .submit-btn:hover { background-color: #4a90e2; }
        .feedback-box { padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; border: 1px solid transparent; }
        .feedback-box.success { background-color: rgba(63, 185, 80, 0.1); border-color: var(--accent-green); color: var(--accent-green); }
        .feedback-box.error { background-color: rgba(226, 76, 76, 0.1); border-color: var(--accent-red); color: var(--accent-red); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-secondary); }
        .form-group input[type="color"] { width: 100px; height: 40px; border: 1px solid var(--border-color); border-radius: 6px; cursor: pointer; padding: 0.25rem; background-color: var(--bg-light); }
        .form-group input[type="text"] { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; background-color: var(--bg-dark); color: var(--text-primary); }
    </style>
</head>
<body>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="admin-profile">
                <i class="bi bi-person-circle icon"></i>
                <h3><?= htmlspecialchars($admin_user) ?></h3>
                <p>Administrador</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="index.php?page=dashboard" class="<?= ($page === 'dashboard') ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
                <li><a href="index.php?page=usuarios" class="<?= ($page === 'usuarios') ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> Usuários</a></li>
                <li><a href="index.php?page=financeiro" class="<?= ($page === 'financeiro') ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Financeiro</a></li>
                <li><a href="index.php?page=relatorios" class="<?= ($page === 'relatorios') ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph-fill"></i> Relatórios</a></li>
                <li><a href="index.php?page=contasdemo" class="<?= ($page === 'contasdemo') ? 'active' : '' ?>"><i class="bi bi-joystick"></i> Contas Demo</a></li>
                <li><a href="index.php?page=gerenciarjogos" class="<?= ($page === 'gerenciarjogos') ? 'active' : '' ?>"><i class="bi bi-gear-fill"></i> Gerenciar Jogos</a></li>
                <li><a href="index.php?page=aparencia" class="active"><i class="bi bi-palette-fill"></i> Aparência</a></li>
                <li><a href="index.php?page=gerenciarpagamentos" class="<?= ($page === 'gerenciarpagamentos') ? 'active' : '' ?>"><i class="bi bi-credit-card-fill"></i> Métodos de Pagamento</a></li>
            </ul>
            <div class="logout-btn">
                <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Sair da Conta</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Gerenciar Aparência Universal</h1>
                <p class="text-secondary">Personalize as cores principais e o logo do seu site.</p>
            </div>
            <?php if ($feedback): ?>
                <div class="feedback-box <?= htmlspecialchars($feedback['type']) ?>">
                    <strong><?= htmlspecialchars($feedback['message']) ?></strong>
                </div>
            <?php endif; ?>
            <div class="card">
                <form action="salvar_cores.php" method="POST">
                    <div class="form-group">
                        <label for="cor-primary">Cor Primária (Fundo do Header e Modais)</label>
                        <input type="color" id="cor-primary" name="cor-primary" value="<?= htmlspecialchars($corPrimary) ?>">
                    </div>
                    <div class="form-group">
                        <label for="cor-secondary">Cor Secundária (Textos do Header e Modais)</label>
                        <input type="color" id="cor-secondary" name="cor-secondary" value="<?= htmlspecialchars($corSecondary) ?>">
                    </div>
                    <div class="form-group">
                        <label for="cor-tertiary">Cor Terciária (Fundo dos Botões)</label>
                        <input type="color" id="cor-tertiary" name="cor-tertiary" value="<?= htmlspecialchars($corTertiary) ?>">
                    </div>
                    <div class="form-group">
                        <label for="site-logo-url">URL do Logo do Site (a imagem deve estar obrigatóriamente na proporção 108 de largura e 36 de altura)</label>
                        <input type="text" id="site-logo-url" name="site-logo-url" value="<?= htmlspecialchars($logoUrl) ?>" placeholder="Ex: https://seusite.com/logo.png">
                    </div>
                    <button type="submit" class="submit-btn">Salvar Alterações</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>