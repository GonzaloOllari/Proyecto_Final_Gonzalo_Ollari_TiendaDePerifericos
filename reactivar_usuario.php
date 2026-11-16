<?php
// ======================================================================
// ARCHIVO: reactivar_usuario.php
// REACTIVAR UN USUARIO ARCHIVADO (solo administrador).
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Solo administradores.
requiere_admin();
// Solo POST.
solo_post();

// Valida el token CSRF.
if (!csrf_ok()) {
    flash('error', 'La sesión expiró. Intentá de nuevo.');
} else {
    // Id del usuario.
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash('error', 'Usuario inválido.');
    } else {
        // NULL = 'ya no está archivado'.
        $st = $conexion->prepare('UPDATE usuarios SET archivado_en = NULL WHERE id = :i AND archivado_en IS NOT NULL');
        $st->execute(['i' => $id]);
        // 1 = se reactivó; 0 = no se encontró.
        if ($st->rowCount() === 1) flash('ok', 'Cuenta reactivada. El usuario ya puede volver a ingresar.');
        else flash('error', 'No se encontró un usuario archivado con ese identificador.');
    }
}
// Vuelve a la lista de archivados.
header('Location: usuarios_archivados.php');
exit;
