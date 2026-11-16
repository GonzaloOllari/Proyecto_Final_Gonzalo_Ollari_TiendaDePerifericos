<?php
// ======================================================================
// ARCHIVO: login.php
// INICIO DE SESIÓN.
// ======================================================================
// Carga configuración, base de datos y funciones.
require_once __DIR__ . '/conexion.php';
// Mensaje de error a mostrar (vacío = sin error).
$error = '';
// Solo se ejecuta cuando se envió el formulario.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se leen los datos del formulario.
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    // Validaciones en orden: token CSRF, campos vacíos y, si todo está bien, la comprobación real.
    if (!csrf_ok()) $error = 'La sesión expiró. Recargá la página.';
    elseif ($email === '' || $pass === '') $error = 'Completá tu email y tu contraseña.';
    else {
        // Busca al usuario por email.
        $st = $conexion->prepare('SELECT id, nombre, password_hash, rol, archivado_en FROM usuarios WHERE email = :e LIMIT 1');
        $st->execute(['e' => $email]);
        $u = $st->fetch();
        // password_verify() compara lo que se escribió con el hash guardado.
        if ($u && password_verify($pass, $u['password_hash'])) {
            // Cuenta archivada: aunque la clave sea correcta, no puede ingresar.
            if ($u['archivado_en'] !== null) {
                $error = 'Tu cuenta está archivada. Comunicate con la tienda para reactivarla.';
            } else {
                // Cambia el id de sesión al loguearse (evita 'session fixation': que alguien te
                // pase una sesión ya preparada). true = borra la sesión vieja.
                session_regenerate_id(true);
                // Se guardan los datos del usuario en la sesión. 'uid' es lo que las demás páginas
                // usan para saber que hay alguien logueado.
                $_SESSION['uid'] = (int)$u['id'];
                $_SESSION['nombre'] = $u['nombre'];
                $_SESSION['rol'] = $u['rol'];
                // Redirección según el rol (operador ternario: condición ? si_si : si_no). exit
                // evita que siga ejecutándose el código.
                header('Location: ' . ($u['rol'] === 'admin' ? 'admin.php' : 'panel.php'));
                exit;
            }
        } else {
            // Mensaje genérico a propósito (no dice si falló el email o la clave).
            $error = 'Email o contraseña incorrectos.';
        }
    }
}
// A partir de acá, HTML con PHP incrustado.
cabecera('Ingresar'); ?>
<section class="panel" style="max-width:460px;margin:0 auto">
  <h2>Ingresar</h2>
  <?php /*
     Avisos según el parámetro que otras páginas ponen en la URL: ?registrado=1 (cuenta creada),
     ?requiere=1 (intentó entrar sin sesión), ?salio=1 (cerró sesión), ?archivada=1 (cuenta
     archivada).
  */ ?>
  <?php if (isset($_GET['registrado'])): ?><div class="msg ok">Cuenta creada. Ya podés ingresar.</div><?php endif; ?>
  <?php if (isset($_GET['requiere'])): ?><div class="msg error">Tenés que iniciar sesión para entrar a esa sección.</div><?php endif; ?>
  <?php if (isset($_GET['salio'])): ?><div class="msg ok">Cerraste sesión correctamente.</div><?php endif; ?>
  <?php if (isset($_GET['archivada'])): ?><div class="msg error">Tu cuenta fue archivada o ya no existe. Comunicate con la tienda.</div><?php endif; ?>
  <?php if ($error): ?><div class="msg error"><?= e($error) ?></div><?php endif; ?>
  <?php /* Formulario de login. */ ?>
  <form class="f" method="post">
    <?php /* Token CSRF oculto. */ ?>
    <?= csrf_campo() ?>
    <?php /* Si el login falla, el email escrito se vuelve a mostrar (la contraseña no). */ ?>
    <div><label for="email">Email</label><input id="email" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
    <div><label for="password">Contraseña</label><input id="password" type="password" name="password" required></div>
    <button type="submit">Ingresar</button>
  </form>
  <p class="ayuda">¿No tenés cuenta? <a href="registro.php">Crear cuenta</a></p>
</section>
<?php pie();
