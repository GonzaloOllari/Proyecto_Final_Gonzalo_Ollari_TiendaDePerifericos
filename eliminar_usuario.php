<?php
// ======================================================================
// ARCHIVO: eliminar_usuario.php
// ELIMINAR DEFINITIVAMENTE A UN USUARIO (solo administrador).
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Solo administradores.
requiere_admin();
// Solo POST.
solo_post();

// Después de eliminar hay que volver a la página de donde se vino ('volver').
// Solo se puede volver a una de estas dos páginas (lista blanca)
$volver = in_array($_POST['volver'] ?? '', ['admin_usuarios.php', 'usuarios_archivados.php'], true) ? $_POST['volver'] : 'admin_usuarios.php';

// Valida el token CSRF.
if (!csrf_ok()) {
    flash('error', 'La sesión expiró. Intentá de nuevo.');
} else {
    // Id del usuario.
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash('error', 'Usuario inválido.');
    } elseif ($id === (int)$_SESSION['uid']) {
        // Un admin no puede eliminarse a sí mismo.
        flash('error', 'No podés eliminar tu propia cuenta.');
    } else {
        // try/catch por si la base rechaza el borrado.
        try {
            // DELETE de la fila del usuario.
            $st = $conexion->prepare('DELETE FROM usuarios WHERE id = :i');
            $st->execute(['i' => $id]);   // los pedidos del usuario se borran solos (ON DELETE CASCADE)
            // 1 = se eliminó; 0 = no existía.
            if ($st->rowCount() === 1) flash('ok', 'Usuario eliminado definitivamente.');
            else flash('error', 'No se encontró ese usuario.');
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            flash('error', 'No se pudo eliminar al usuario.');
        }
    }
}
// Vuelve a la página validada arriba.
header('Location: ' . $volver);
exit;
