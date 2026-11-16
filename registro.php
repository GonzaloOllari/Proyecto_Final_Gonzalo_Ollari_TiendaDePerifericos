<?php
// ======================================================================
// ARCHIVO: registro.php
// REGISTRO DE CLIENTES.
// ======================================================================
// Carga configuración, base de datos y funciones.
require_once __DIR__ . '/conexion.php';
// Mensaje de error.
$error = '';
// $v guarda los valores del formulario para volver a mostrarlos si hay un error (así el usuario no
// reescribe todo).
$v = ['nombre' => '', 'apellido' => '', 'dni' => '', 'email' => ''];
// Solo cuando se envió el formulario.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recorre cada campo de $v y lo toma de $_POST con trim().
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    // La contraseña se lee aparte (sin trim, y nunca se vuelve a mostrar).
    $pass = $_POST['password'] ?? '';
    // Cadena if / elseif de validaciones: se corta en el PRIMER error.
    if (!csrf_ok()) $error = 'La sesión expiró. Recargá la página.';
    elseif ($v['nombre'] === '' || $v['apellido'] === '') $error = 'Completá nombre y apellido.';
    // DNI: de 7 a 9 dígitos (expresión regular).
    elseif (!preg_match('/^\d{7,9}$/', $v['dni'])) $error = 'El DNI debe tener entre 7 y 9 números, sin puntos.';
    // filter_var con FILTER_VALIDATE_EMAIL: PHP verifica que tenga forma de email.
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $error = 'Ingresá un email válido.';
    // Largo de contraseña: mínimo 8 y máximo 72 caracteres.
    elseif (strlen($pass) < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
    elseif (strlen($pass) > 72) $error = 'La contraseña no puede superar los 72 caracteres.';
    // Se guarda en $dup el resultado de usuario_duplicado(); si no está vacío es porque ya existe
    // ese DNI o email.
    elseif (($dup = usuario_duplicado($conexion, $v['dni'], $v['email'])) !== '') $error = $dup;
    // Todo validado: se inserta el usuario.
    else {
        try {
            // INSERT con consulta preparada.
            $st = $conexion->prepare('INSERT INTO usuarios (nombre, apellido, dni, email, password_hash, rol)
                                      VALUES (:n, :a, :d, :e, :p, "usuario")');
            $st->execute(['n' => $v['nombre'], 'a' => $v['apellido'], 'd' => $v['dni'], 'e' => $v['email'],
                          // password_hash() convierte la contraseña en un hash irreversible.
                          'p' => password_hash($pass, PASSWORD_DEFAULT)]);
            // Cuenta creada: redirige al login con el aviso verde.
            header('Location: login.php?registrado=1');
            exit;
        // Código 23000 = violación de una restricción UNIQUE (DNI o email repetido).
        } catch (PDOException $ex) {
            $error = $ex->getCode() === '23000' ? 'Ya existe una cuenta con ese DNI o email.' : 'No se pudo crear la cuenta.';
        }
    }
}
// A partir de acá, HTML.
cabecera('Crear cuenta'); ?>
<section class="panel" style="max-width:560px;margin:0 auto">
  <h2>Crear cuenta</h2>
  <p class="ayuda">Con tu cuenta podés hacer pedidos de productos del catálogo.</p>
  <?php if ($error): ?><div class="msg error"><?= e($error) ?></div><?php endif; ?>
  <form class="f" method="post">
    <?= csrf_campo() ?>
    <?php /* Clase 'dos' = dos columnas lado a lado (nombre y apellido). */ ?>
    <div class="dos">
      <div><label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="80" value="<?= e($v['nombre']) ?>"></div>
      <div><label for="apellido">Apellido</label><input id="apellido" name="apellido" required maxlength="80" value="<?= e($v['apellido']) ?>"></div>
    </div>
    <div><label for="dni">DNI</label><input id="dni" name="dni" required inputmode="numeric" value="<?= e($v['dni']) ?>"></div>
    <div><label for="email">Email</label><input id="email" type="email" name="email" required value="<?= e($v['email']) ?>"></div>
    <?php /*
       minlength=8 valida en el navegador, pero la validación que cuenta es la del servidor
       (arriba).
    */ ?>
    <div><label for="password">Contraseña</label><input id="password" type="password" name="password" required minlength="8"><p class="ayuda">Mínimo 8 caracteres.</p></div>
    <button type="submit">Crear cuenta</button>
  </form>
</section>
<?php pie();
