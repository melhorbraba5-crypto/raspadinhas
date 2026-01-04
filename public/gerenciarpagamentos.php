<?php
// public/gerenciar_pagamentos.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$page = 'gerenciarpagamentos';
$admin_user = $_SESSION['admin_user'] ?? 'Admin';

try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, provider_key, is_active FROM payment_methods ORDER BY id ASC;");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['form_feedback'] = [
        'type' => 'error',
        'message' => 'Erro ao buscar métodos de pagamento: ' . $e->getMessage()
    ];
    $payment_methods = [];
}

$feedback = $_SESSION['form_feedback'] ?? null;
unset($_SESSION['form_feedback']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Admin - Métodos de Pagamento</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS GERAL DA DASHBOARD PARA SEGUIR O PADRÃO */
        :root {
            --bg-dark: #0D1117;
            --bg-light: #161B22;
            --border-color: #30363D;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-blue: #58A6FF;
            --accent-green: #3FB950;
            --accent-red: #E24C4C;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: var(--text-primary); margin:0; }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            padding: 2rem;
            min-height: 100vh;
        }
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

        /* CSS ESPECÍFICO DESTA PÁGINA */
        .main-content { overflow-y: auto; }
        .page-header { border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem; }
        .card { background-color: var(--bg-light); padding: 2rem; border-radius: 10px; border: 1px solid var(--border-color); }
        .table-container { overflow-x: auto; }
        .table-container table { width: 100%; border-collapse: collapse; background-color: var(--bg-light); }
        .table-container th, .table-container td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .table-container th { background-color: #1e2a3a; font-weight: 600; }
        .table-container tbody tr:hover { background-color: #2a3038; }
        .table-container td form { display: flex; align-items: center; gap: 0.5rem; }
        .submit-btn { padding: 0.6rem 1rem; background-color: var(--accent-blue); color: #fff; border: none; border-radius: 6px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        .submit-btn:hover { background-color: #4a90e2; }
        .feedback-box { padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; border: 1px solid transparent; }
        .feedback-box.success { background-color: rgba(63, 185, 80, 0.1); border-color: var(--accent-green); color: var(--accent-green); }
        .feedback-box.error { background-color: rgba(226, 76, 76, 0.1); border-color: var(--accent-red); color: var(--accent-red); }
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
                <li><a href="index.php?page=gerenciarpagamentos" class="<?= ($page === 'gerenciarpagamentos') ? 'active' : '' ?>"><i class="bi bi-credit-card-fill"></i> Métodos de Pagamento</a></li>
            </ul>
            <div class="logout-btn">
                <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Sair da Conta</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Gerenciar Métodos de Pagamento</h1>
                <p class="text-secondary">Ative ou desative os métodos de pagamento disponíveis no site.</p>
            </div>

            <?php if ($feedback): ?>
                <div class="feedback-box <?= htmlspecialchars($feedback['type']) ?>">
                    <strong><?= htmlspecialchars($feedback['message']) ?></strong>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>Métodos de Pagamento</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payment_methods)): ?>
                                <?php foreach ($payment_methods as $method): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($method['id']) ?></td>
                                        <td><?= htmlspecialchars($method['provider_key']) ?></td>
                                        <td>
                                            <span style="color: <?= $method['is_active'] ? 'var(--accent-green)' : 'var(--accent-red)' ?>;">
                                                <?= $method['is_active'] ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="toggle_payment_method.php">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($method['id']) ?>">
                                                <input type="hidden" name="is_active" value="<?= $method['is_active'] ? '0' : '1' ?>">
                                                <button type="submit" class="submit-btn" style="background-color: <?= $method['is_active'] ? 'var(--accent-red)' : 'var(--accent-green)' ?>;">
                                                    <?= $method['is_active'] ? 'Desativar' : 'Ativar' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">Nenhum método de pagamento encontrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>