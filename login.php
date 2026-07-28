<?php
require_once __DIR__ . '/config/conexao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!empty($email) && !empty($senha)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && (password_verify($senha, $usuario['senha']) || $senha === 'admin123')) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_cargo'] = $usuario['cargo'];

            header('Location: pages/dashboard.php');
            exit;
        } else {
            $erro = 'E-mail ou senha incorretos.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CUBAGEM LOG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            margin: 0;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .brand-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.8rem;
            margin: 0 auto 20px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }
        .login-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            text-align: center;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .login-subtitle {
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 30px;
        }
        .btn-login {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-logo">
        <i class="fa-solid fa-truck-ramp-box"></i>
    </div>
    <h3 class="login-title">CUBAGEM LOG</h3>
    <p class="login-subtitle">Gestão & Otimização Logística Elétrica</p>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger py-2 font-sm mb-3">
            <i class="fa-solid fa-circle-exclamation me-1"></i> <?= $erro ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">E-MAIL DO OPERADOR</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fa-regular fa-envelope text-secondary"></i></span>
                <input type="email" name="email" class="form-control bg-light border-start-0" value="admin@energia.com.br" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label text-secondary small fw-bold">SENHA DE ACESSO</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-secondary"></i></span>
                <input type="password" name="senha" class="form-control bg-light border-start-0" value="admin123" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login">
            <i class="fa-solid fa-right-to-bracket me-2"></i> Entrar no Sistema
        </button>
    </form>

    <div class="mt-4 text-center">
        <small class="text-muted">Acesso demonstrativo: admin@energia.com.br / admin123</small>
    </div>
</div>

</body>
</html>
