<?php
declare(strict_types=1);

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_panel'])) {
        $_SESSION['csrf_panel'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_panel'];
}

function validarCsrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(tokenCsrf(), $token)) {
        throw new RuntimeException('La solicitud no es válida. Recarga la página e inténtalo otra vez.');
    }
}

function guardarArchivo(string $campo, string $carpeta, array $extensiones, ?string $anterior = null): ?string
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return $anterior;
    }
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK || $_FILES[$campo]['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('No se pudo subir el archivo o supera los 10 MB.');
    }
    $extension = strtolower(pathinfo((string) $_FILES[$campo]['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensiones, true)) {
        throw new RuntimeException('El archivo seleccionado no tiene un formato permitido.');
    }
    $directorio = dirname(__DIR__, 2) . '/uploads/' . $carpeta;
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear la carpeta de archivos.');
    }
    $nombre = bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $directorio . '/' . $nombre)) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }
    return 'uploads/' . $carpeta . '/' . $nombre;
}

function borrarArchivo(?string $ruta): void
{
    if (!$ruta || !str_starts_with($ruta, 'uploads/')) return;
    $archivo = dirname(__DIR__, 2) . '/' . $ruta;
    if (is_file($archivo)) @unlink($archivo);
}

function rutaArchivo(?string $ruta): string
{
    return $ruta ? '../' . ltrim($ruta, '/') : '';
}

