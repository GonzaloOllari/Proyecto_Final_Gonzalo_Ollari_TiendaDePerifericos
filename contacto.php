<?php
// ======================================================================
// ARCHIVO: contacto.php
// NOTA IMPORTANTE: este archivo parece SOBRANTE de una versión anterior y hoy daría error: usa
// las constantes TIENDA_TEL, TIENDA_DIRECCION y TIENDA_HORARIO, que no están definidas en
// conexion.php, y guarda en una tabla 'contactos' que no existe en tienda_perifericos.sql.
// Ninguna página enlaza hacia acá. Qué intenta hacer: formulario de contacto (nombre,
// teléfono, email, mensaje) que guarda la consulta en la base.
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// $msg = mensaje de éxito; $error = mensaje de error.
$msg = ''; $error = '';
// Valores del formulario, para volver a mostrarlos si hay error.
$v = ['nombre' => '', 'telefono' => '', 'email' => '', 'mensaje' => ''];

// Solo cuando se envió el formulario.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toma cada campo de $_POST con trim().
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    // Cadena de validaciones; se corta en el primer error.
    if (!csrf_ok()) $error = 'La sesión expiró. Recargá la página.';
    // HONEYPOT ('trampa para bots'): hay un campo oculto llamado 'web' que una persona nunca
    // completa.
    elseif (!empty($_POST['web'])) { $msg = 'Gracias, recibimos tu mensaje.'; $v = array_map(fn() => '', $v); } // campo trampa para bots
    // Nombre obligatorio, hasta 80 caracteres.
    elseif ($v['nombre'] === '' || mb_strlen($v['nombre']) > 80) $error = 'Ingresá tu nombre (hasta 80 caracteres).';
    // Teléfono: 8 a 20 caracteres entre números, espacio, + ( ) y guion.
    elseif (!preg_match('/^[0-9 +()\-]{8,20}$/', $v['telefono'])) $error = 'Ingresá un teléfono válido, por ejemplo 11 5143-1316.';
    // Email con formato válido.
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 254) $error = 'Ingresá un email válido, por ejemplo nombre@gmail.com.';
    // Mensaje obligatorio, hasta 1000 caracteres.
    elseif ($v['mensaje'] === '' || mb_strlen($v['mensaje']) > 1000) $error = 'Escribí tu consulta (hasta 1000 caracteres).';
    // Todo bien: se guarda la consulta.
    else {
        // INSERT preparado (la tabla 'contactos' no existe en el .sql actual).
        $st = $conexion->prepare('INSERT INTO contactos (nombre, telefono, email, mensaje) VALUES (:n,:t,:e,:m)');
        $st->execute(['n' => $v['nombre'], 't' => $v['telefono'], 'e' => $v['email'], 'm' => $v['mensaje']]);
        $msg = 'Gracias, recibimos tu mensaje. Te vamos a contactar a la brevedad.';
        // Vacía todos los campos aplicando una función que devuelve '' a cada uno, para que el
        // formulario quede limpio.
        $v = array_map(fn() => '', $v);
    }
}
// Arma el enlace tel: dejando solo números y +, para que al tocarlo en el celular se pueda llamar.
$telLink = 'tel:' . preg_replace('/[^0-9+]/', '', TIENDA_TEL);
// HTML.
cabecera('Contacto'); ?>
<?php /* Dos columnas: formulario a la izquierda, datos del local a la derecha. */ ?>
<div class="grid2">
<section class="panel">
  <h2>Contactanos</h2>
  <p class="ayuda" style="margin-bottom:1rem">Dejanos tu consulta, tu teléfono y tu email, y te respondemos.</p>
  <?php if ($msg): ?><div class="msg ok"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="msg error"><?= e($error) ?></div><?php endif; ?>
  <form class="f" method="post">
    <?= csrf_campo() ?>
    <?php /*
       Campo trampa (honeypot): está fuera de la pantalla, no lo ven las personas pero los bots sí
       lo completan.
    */ ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true"><label>No completar<input name="web" tabindex="-1" autocomplete="off"></label></div>
    <div><label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="80" value="<?= e($v['nombre']) ?>"></div>
    <div class="dos">
      <div><label for="telefono">Teléfono</label><input id="telefono" type="tel" name="telefono" required maxlength="20" placeholder="11 2345-6789" value="<?= e($v['telefono']) ?>"></div>
      <div><label for="email">Email</label><input id="email" type="email" name="email" required maxlength="254" placeholder="nombre@gmail.com" value="<?= e($v['email']) ?>"></div>
    </div>
    <div><label for="mensaje">¿Qué producto o consulta tenés?</label>
      <textarea id="mensaje" name="mensaje" rows="5" required maxlength="1000" placeholder="Contanos qué producto buscás o qué duda tenés."><?= e($v['mensaje']) ?></textarea></div>
    <button type="submit">Enviar mensaje</button>
  </form>
</section>

<section class="panel">
  <?php /* Columna derecha: teléfono, dirección y horarios (vienen de constantes). */ ?>
  <h2>Nuestros datos</h2>
  <p><strong>Teléfono</strong><br><a href="<?= e($telLink) ?>"><?= e(TIENDA_TEL) ?></a></p>
  <p><strong>Dirección</strong><br><?= e(TIENDA_DIRECCION) ?></p>
  <p><strong>Horarios</strong><br><?= e(TIENDA_HORARIO) ?></p>
  <p><a class="btn" href="catalogo.php">Ver catálogo</a></p>
</section>
</div>
<?php pie();
