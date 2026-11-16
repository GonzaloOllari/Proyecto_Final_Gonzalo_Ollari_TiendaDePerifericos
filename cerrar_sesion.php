<?php
// ======================================================================
// ARCHIVO: cerrar_sesion.php
// CIERRE DE SESIÓN.
// ======================================================================
// Hay que iniciar la sesión para poder destruirla.
session_start();
// Vacía todos los datos guardados (uid, rol, nombre...).
$_SESSION = [];
// Si la sesión usa cookie, se la reemplaza por una vencida (time() - 42000 está en el pasado), así
// el navegador la elimina.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
// Destruye la sesión en el servidor.
session_destroy();
// Redirige al login con el aviso 'Cerraste sesión'.
header('Location: login.php?salio=1');
exit;
