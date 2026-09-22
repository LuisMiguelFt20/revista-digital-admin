<?php
declare(strict_types=1);

require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/conexion.php';
exigirAdmin();

function escapar(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_usuarios'])) {
    $_SESSION['csrf_usuarios'] = bin2hex(random_bytes(32));
}

$error = '';
$idEditar = 0;
$nombres = '';
$apPaterno = '';
$apMaterno = '';
$email = '';
$rol = 'redactor';
$rolesPermitidos = ['admin', 'editor', 'redactor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_usuarios'], $token)) {
        $error = 'La solicitud no es válida. Recarga la página.';
    } elseif ($accion === 'eliminar') {
        $idEliminar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

        if ($idEliminar <= 0) {
            $error = 'El usuario seleccionado no es válido.';
        } elseif ($idEliminar === (int) $_SESSION['usuario_id']) {
            $error = 'No puedes eliminar tu propio usuario mientras tienes la sesión abierta.';
        } else {
            try {
                $consulta = $conexion->prepare('DELETE FROM usuarios WHERE id = :id');
                $consulta->execute(['id' => $idEliminar]);
                header('Location: usuarios.php?mensaje=eliminado');
                exit;
            } catch (PDOException $excepcion) {
                $error = 'No se puede eliminar porque este usuario tiene publicaciones relacionadas.';
            }
        }
    } elseif ($accion === 'guardar' || $accion === 'actualizar') {
        $idEditar = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        $nombres = trim($_POST['nombres'] ?? '');
        $apPaterno = trim($_POST['ap_paterno'] ?? '');
        $apMaterno = trim($_POST['ap_materno'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = trim($_POST['rol'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nombres === '' || $apPaterno === '' || $email === '' || $rol === '') {
            $error = 'Nombres, apellido paterno, correo y rol son obligatorios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Escribe un correo electrónico válido.';
        } elseif (!in_array($rol, $rolesPermitidos, true)) {
            $error = 'El rol seleccionado no es válido.';
        } elseif ($accion === 'guardar' && strlen($password) < 6) {
            $error = 'La contraseña debe tener como mínimo 6 caracteres.';
        } elseif ($accion === 'actualizar' && $password !== '' && strlen($password) < 6) {
            $error = 'La nueva contraseña debe tener como mínimo 6 caracteres.';
        } elseif ($accion === 'actualizar' && $idEditar === (int) $_SESSION['usuario_id'] && $rol !== 'admin') {
            $error = 'No puedes quitarte tu propio rol de administrador.';
        } else {
            $consulta = $conexion->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1');
            $consulta->execute(['email' => $email, 'id' => $idEditar]);

            if ($consulta->fetch()) {
                $error = 'Ya existe un usuario con ese correo.';
            } elseif ($accion === 'guardar') {
                $consulta = $conexion->prepare(
                    'INSERT INTO usuarios (nombres, ap_paterno, ap_materno, email, password_hash, rol)
                     VALUES (:nombres, :ap_paterno, :ap_materno, :email, :password_hash, :rol)'
                );
                $consulta->execute([
                    'nombres' => $nombres,
                    'ap_paterno' => $apPaterno,
                    'ap_materno' => $apMaterno !== '' ? $apMaterno : null,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'rol' => $rol,
                ]);
                header('Location: usuarios.php?mensaje=creado');
                exit;
            } elseif ($idEditar <= 0) {
                $error = 'El usuario seleccionado no es válido.';
            } else {
                $campos = 'nombres = :nombres, ap_paterno = :ap_paterno, ap_materno = :ap_materno, email = :email, rol = :rol';
                $parametros = [
                    'nombres' => $nombres,
                    'ap_paterno' => $apPaterno,
                    'ap_materno' => $apMaterno !== '' ? $apMaterno : null,
                    'email' => $email,
                    'rol' => $rol,
                    'id' => $idEditar,
                ];
                if ($password !== '') {
                    $campos .= ', password_hash = :password_hash';
                    $parametros['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $consulta = $conexion->prepare("UPDATE usuarios SET {$campos} WHERE id = :id");
                $consulta->execute($parametros);

                if ($idEditar === (int) $_SESSION['usuario_id']) {
                    $_SESSION['usuario_nombre'] = trim($nombres . ' ' . $apPaterno . ' ' . $apMaterno);
                    $_SESSION['usuario_email'] = $email;
                    $_SESSION['usuario_rol'] = $rol;
                }
                header('Location: usuarios.php?mensaje=actualizado');
                exit;
            }
        }
    }
}

if (isset($_GET['editar'])) {
    $idEditar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
    if ($idEditar > 0) {
        $consulta = $conexion->prepare('SELECT id, nombres, ap_paterno, ap_materno, email, rol FROM usuarios WHERE id = :id');
        $consulta->execute(['id' => $idEditar]);
        $usuarioEditar = $consulta->fetch();
        if ($usuarioEditar) {
            $nombres = $usuarioEditar['nombres'];
            $apPaterno = $usuarioEditar['ap_paterno'];
            $apMaterno = $usuarioEditar['ap_materno'] ?? '';
            $email = $usuarioEditar['email'];
            $rol = $usuarioEditar['rol'];
        } else {
            $idEditar = 0;
            $error = 'El usuario solicitado no existe.';
        }
    }
}

$usuarios = $conexion->query('SELECT id, nombres, ap_paterno, ap_materno, email, rol, created_at FROM usuarios ORDER BY id DESC')->fetchAll();
$mensajes = ['creado' => 'Usuario registrado correctamente.', 'actualizado' => 'Usuario actualizado correctamente.', 'eliminado' => 'Usuario eliminado correctamente.'];
$mensaje = $mensajes[$_GET['mensaje'] ?? ''] ?? '';
?>
<!doctype html>
<html lang="es" dir="ltr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios | Revista Digital</title>
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/core/libs.min.css">
    <link rel="stylesheet" href="../assets/css/hope-ui.min.css?v=4.0.0">
    <link rel="stylesheet" href="../assets/css/custom.min.css?v=4.0.0">
</head>
<body>
<aside class="sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all">
    <div class="sidebar-header d-flex align-items-center justify-content-start"><a href="index.php" class="navbar-brand"><h4 class="logo-title">Revista Digital</h4></a></div>
    <div class="sidebar-body pt-0 data-scrollbar"><div class="sidebar-list"><ul class="navbar-nav iq-main-menu">
        <li class="nav-item static-item"><span class="nav-link static-item disabled">Administración</span></li>
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="icon">🏠</i><span class="item-name">Panel principal</span></a></li>
        <li class="nav-item"><a class="nav-link active" href="usuarios.php"><i class="icon">👤</i><span class="item-name">Usuarios</span></a></li>
        <li class="nav-item"><span class="nav-link disabled"><i class="icon">✍️</i><span class="item-name">Autores</span></span></li>
        <li class="nav-item"><span class="nav-link disabled"><i class="icon">📰</i><span class="item-name">Reportajes</span></span></li>
        <li class="nav-item"><span class="nav-link disabled"><i class="icon">🖼️</i><span class="item-name">Fotos de reportajes</span></span></li>
        <li class="nav-item"><span class="nav-link disabled"><i class="icon">📢</i><span class="item-name">Noticias</span></span></li>
        <li class="nav-item"><span class="nav-link disabled"><i class="icon">📄</i><span class="item-name">Boletines</span></span></li>
        <li class="nav-item"><span class="nav-link disabled"><i class="icon">🎙️</i><span class="item-name">Podcasts</span></span></li>
        <li class="nav-item"><span class="nav-link disabled"><i class="icon">🎬</i><span class="item-name">Videos</span></span></li>
    </ul></div></div>
</aside>
<main class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4"><div><h1>Administración de usuarios</h1><p class="text-muted">Gestiona las cuentas y roles del panel.</p></div><a href="index.php" class="btn btn-outline-primary">Volver al panel</a></div>
        <?php if ($mensaje !== ''): ?><div class="alert alert-success"><?= escapar($mensaje) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= escapar($error) ?></div><?php endif; ?>
        <div class="row g-4">
            <div class="col-xl-4"><div class="card"><div class="card-body">
                <h3 class="mb-4"><?= $idEditar > 0 ? 'Editar usuario' : 'Registrar usuario' ?></h3>
                <form method="post" action="usuarios.php">
                    <input type="hidden" name="csrf_token" value="<?= escapar($_SESSION['csrf_usuarios']) ?>">
                    <input type="hidden" name="accion" value="<?= $idEditar > 0 ? 'actualizar' : 'guardar' ?>">
                    <input type="hidden" name="id" value="<?= $idEditar ?>">
                    <div class="mb-3"><label class="form-label" for="nombres">Nombres *</label><input class="form-control" id="nombres" name="nombres" maxlength="100" value="<?= escapar($nombres) ?>" required></div>
                    <div class="mb-3"><label class="form-label" for="ap_paterno">Apellido paterno *</label><input class="form-control" id="ap_paterno" name="ap_paterno" maxlength="100" value="<?= escapar($apPaterno) ?>" required></div>
                    <div class="mb-3"><label class="form-label" for="ap_materno">Apellido materno</label><input class="form-control" id="ap_materno" name="ap_materno" maxlength="100" value="<?= escapar($apMaterno) ?>"></div>
                    <div class="mb-3"><label class="form-label" for="email">Correo *</label><input type="email" class="form-control" id="email" name="email" maxlength="150" value="<?= escapar($email) ?>" required></div>
                    <div class="mb-3"><label class="form-label" for="password">Contraseña <?= $idEditar > 0 ? '(opcional)' : '*' ?></label><input type="password" class="form-control" id="password" name="password" minlength="6" <?= $idEditar > 0 ? '' : 'required' ?>></div>
                    <div class="mb-4"><label class="form-label" for="rol">Rol *</label><select class="form-select" id="rol" name="rol" required>
                        <option value="admin" <?= $rol === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="editor" <?= $rol === 'editor' ? 'selected' : '' ?>>Editor</option>
                        <option value="redactor" <?= $rol === 'redactor' ? 'selected' : '' ?>>Redactor</option>
                    </select></div>
                    <button class="btn btn-primary" type="submit"><?= $idEditar > 0 ? 'Actualizar' : 'Guardar' ?></button>
                    <?php if ($idEditar > 0): ?><a href="usuarios.php" class="btn btn-secondary">Cancelar</a><?php endif; ?>
                </form>
            </div></div></div>
            <div class="col-xl-8"><div class="card"><div class="card-header"><h3 class="mb-0">Lista de usuarios <span class="badge bg-primary"><?= count($usuarios) ?></span></h3></div><div class="card-body p-0"><div class="table-responsive">
                <table class="table align-middle mb-0"><thead><tr><th>Nombre completo</th><th>Correo</th><th>Rol</th><th>Registro</th><th>Acciones</th></tr></thead><tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?= escapar(trim($usuario['nombres'] . ' ' . $usuario['ap_paterno'] . ' ' . ($usuario['ap_materno'] ?? ''))) ?></td>
                        <td><?= escapar($usuario['email']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= escapar($usuario['rol']) ?></span></td>
                        <td><?= date('d/m/Y', strtotime($usuario['created_at'])) ?></td>
                        <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="usuarios.php?editar=<?= (int) $usuario['id'] ?>">Editar</a>
                            <?php if ((int) $usuario['id'] !== (int) $_SESSION['usuario_id']): ?>
                            <form method="post" action="usuarios.php" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario?')"><input type="hidden" name="csrf_token" value="<?= escapar($_SESSION['csrf_usuarios']) ?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div></div></div></div>
        </div>
    </div>
</main>
<script src="../assets/js/core/libs.min.js"></script><script src="../assets/js/hope-ui.js" defer></script>
</body></html>
