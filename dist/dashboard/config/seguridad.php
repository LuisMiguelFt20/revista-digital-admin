<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_base.php';
require_once __DIR__ . '/conexion.php';

function esAdmin(): bool
{
    return ($_SESSION['usuario_rol'] ?? '') === 'admin';
}

function esRedactor(): bool
{
    return ($_SESSION['usuario_rol'] ?? '') === 'redactor';
}

function exigirAdmin(): void
{
    if (!esAdmin()) {
        denegarAcceso();
    }
}

function denegarAcceso(): void
{
    http_response_code(403);

    exit(
        'No tienes permiso para esta acción. ' .
        '<a href="index.php">Volver al panel</a>'
    );
}

/* Verificar que exista una sesión iniciada */
if (empty($_SESSION['usuario_id'])) {
    header('Location: ' . AUTH_BASE_URL . '/auth/sign-in.php');
    exit;
}

/* Consultar los datos del usuario autenticado */
$consultaUsuario = $conexion->prepare(
    'SELECT
        id,
        nombres,
        ap_paterno,
        ap_materno,
        email,
        rol,
        password_hash
     FROM usuarios
     WHERE id = ?'
);

$consultaUsuario->execute([
    $_SESSION['usuario_id']
]);

$usuario = $consultaUsuario->fetch(PDO::FETCH_ASSOC);

/* Comprobar usuario, rol, tiempo de sesión y autenticación */
$sesionInvalida =
    !$usuario ||
    !in_array(
        $usuario['rol'],
        ['admin', 'editor', 'redactor'],
        true
    ) ||
    time() - ($_SESSION['last_activity'] ?? 0) > 1800 ||
    !hash_equals(
        (string) ($_SESSION['auth_fingerprint'] ?? ''),
        hash(
            'sha256',
            (string) ($usuario['password_hash'] ?? '')
        )
    );

if ($sesionInvalida) {
    $_SESSION = [];

    session_regenerate_id(true);

    header(
        'Location: ' .
        AUTH_BASE_URL .
        '/auth/sign-in.php?sesion=expirada'
    );

    exit;
}

/* Actualizar los datos de la sesión */
$_SESSION['last_activity'] = time();
$_SESSION['usuario_rol'] = $usuario['rol'];
$_SESSION['usuario_email'] = $usuario['email'];

$_SESSION['usuario_nombre'] = trim(
    ($usuario['nombres'] ?? '') . ' ' .
    ($usuario['ap_paterno'] ?? '') . ' ' .
    ($usuario['ap_materno'] ?? '')
);

/*
 * Un redactor solamente puede modificar
 * los reportajes que le pertenecen.
 */
function authReport(PDO $db, int $id): void
{
    if (!esRedactor() || !$id) {
        return;
    }

    $consulta = $db->prepare(
        'SELECT usuario_id
         FROM reportajes
         WHERE id = ?'
    );

    $consulta->execute([$id]);

    if (
        (int) $consulta->fetchColumn() !==
        (int) $_SESSION['usuario_id']
    ) {
        denegarAcceso();
    }
}