<?php
// ======================================================================
// ARCHIVO: archivar_usuario.php
// ARCHIVAR UN USUARIO (solo administrador).
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Solo administradores.
requiere_admin();
// Solo acepta POST (no se puede archivar pegando una URL).
solo_post();

// Valida el token CSRF.
if (!csrf_ok()) {
    flash('error', 'La sesión expiró. Intentá de nuevo.');
} else {
    // Id del usuario a archivar.
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash('error', 'Usuario inválido.');
    } elseif ($id === (int)$_SESSION['uid']) {
        // Un admin no puede archivarse a sí mismo.
        flash('error', 'No podés archivar tu propia cuenta.');
    } else {
        // Marca la fecha y hora actual. 'AND archivado_en IS NULL' evita archivar dos veces.
        $st = $conexion->prepare('UPDATE usuarios SET archivado_en = NOW() WHERE id = :i AND archivado_en IS NULL');
        $st->execute(['i' => $id]);
        // rowCount() = filas modificadas: 1 = se archivó; 0 = no había un usuario activo con ese
        // id.
        if ($st->rowCount() === 1) flash('ok', 'Usuario archivado. Sus datos se conservan y puede reactivarse desde "Usuarios archivados".');
        else flash('error', 'No se encontró un usuario activo con ese identificador.');
    }
}
// Siempre vuelve a la lista de usuarios (el mensaje flash dice qué pasó).
header('Location: admin_usuarios.php');
exit;
