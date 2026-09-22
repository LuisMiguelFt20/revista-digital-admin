<?php
declare(strict_types=1);

require_once __DIR__.'/../config/auth_base.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

$error = '';
$email = '';

if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(authText($_POST['email'] ?? null));
    $password = authText($_POST['password'] ?? null);
    $token = authText($_POST['csrf_token'] ?? null);

    if (!authLimit('login-ip', $_SERVER['REMOTE_ADDR']??'', 30, 900) || !authLimit('login-email', strtolower($email), 10, 900)) {
        $error='Demasiados intentos. Espera 15 minutos.';
    } elseif (!hash_equals($_SESSION['csrf_login'], $token)) {
        $error = 'La solicitud no es válida. Recarga la página.';
    } elseif (strlen($password)>4096 || str_contains($password, "\0")) {
        $error='Correo o contraseña incorrectos.';
    } elseif ($email === '' || $password === '') {
        $error = 'Completa el correo y la contraseña.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Escribe un correo electrónico válido.';
    } else {
        $consulta = $conexion->prepare(
            'SELECT id, nombres, ap_paterno, ap_materno, email, password_hash, rol
             FROM usuarios WHERE email = :email LIMIT 1'
        );
        $consulta->execute(['email' => $email]);
        $usuario = $consulta->fetch();

        if (password_verify($password, $usuario['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.') && $usuario && in_array($usuario['rol'], ['admin','editor','redactor'], true)) {
            session_regenerate_id(true);
            if(password_needs_rehash($usuario['password_hash'],PASSWORD_DEFAULT)){ $newHash=password_hash($password,PASSWORD_DEFAULT);$conexion->prepare('UPDATE usuarios SET password_hash=? WHERE id=?')->execute([$newHash,$usuario['id']]);$usuario['password_hash']=$newHash;}
            $_SESSION['auth_fingerprint']=hash('sha256',$usuario['password_hash']);
            $_SESSION['last_activity']=time();
            $nombreCompleto = trim($usuario['nombres'] . ' ' . $usuario['ap_paterno'] . ' ' . ($usuario['ap_materno'] ?? ''));

            $_SESSION['usuario_id'] = (int) $usuario['id'];
            $_SESSION['usuario_nombre'] = $nombreCompleto;
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_rol'] = $usuario['rol'];

            unset($_SESSION['csrf_login']);
            header('Location: ../index.php');
            exit;
        }

        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Iniciar sesión | Revista Digital</title>
    <link rel="shortcut icon" href="../../assets/images/favicon.ico">
    <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
    <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
    <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
</head>
<body>
<div id="loading"><div class="loader simple-loader"><div class="loader-body"></div></div></div>
<div class="wrapper">
    <section class="login-content">
        <div class="row m-0 align-items-center bg-white vh-100">
            <div class="col-md-6">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="card card-transparent shadow-none mb-0 auth-card">
                            <div class="card-body">
                                <div class="navbar-brand d-flex align-items-center mb-3">
                                    <svg class="text-primary icon-30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
                                        <rect x="-0.757" y="19.243" width="28" height="4" rx="2" transform="rotate(-45 -0.757 19.243)" fill="currentColor"/>
                                        <rect x="7.728" y="27.728" width="28" height="4" rx="2" transform="rotate(-45 7.728 27.728)" fill="currentColor"/>
                                        <rect x="10.537" y="16.395" width="16" height="4" rx="2" transform="rotate(45 10.537 16.395)" fill="currentColor"/>
                                        <rect x="10.556" y="-0.556" width="28" height="4" rx="2" transform="rotate(45 10.556 -0.556)" fill="currentColor"/>
                                    </svg>
                                    <h4 class="logo-title ms-3">Revista Digital</h4>
                                </div>
                                <h2 class="mb-2 text-center">Iniciar sesión</h2>
                                <p class="text-center">Ingresa al panel administrativo.</p>

                                <?php if ($error !== ''): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>

                                <form method="post" action="sign-in.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_login'], ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="form-group">
                                        <label for="email" class="form-label">Correo electrónico</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                                    </div>
                                    <div class="form-group">
                                        <label for="password" class="form-label">Contraseña</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="mostrarPassword">Mostrar</button>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-center mt-4">
                                        <button type="submit" class="btn btn-primary">Iniciar sesión</button>
                                    </div>
                                </form><p class="mt-3"><a href="recuperar.php">¿Olvidaste tu contraseña?</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 d-md-block d-none bg-primary p-0 vh-100 overflow-hidden">
                <img src="../../assets/images/auth/01.png" class="img-fluid gradient-main animated-scaleX" alt="Acceso al panel">
            </div>
        </div>
    </section>
</div>
<script src="../../assets/js/core/libs.min.js"></script>
<script src="../../assets/js/hope-ui.js" defer></script>
<script>
document.getElementById('mostrarPassword').addEventListener('click', function () {
    const campo = document.getElementById('password');
    const mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    this.textContent = mostrar ? 'Ocultar' : 'Mostrar';
});
</script>
</body>
</html>
