<?php
// ======================================================================
// ARCHIVO: editar_usuario.php
// EDICIÓN DE UN USUARIO (solo administrador). editar_usuario.php?id=NUMERO.
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Solo administradores.
requiere_admin();

// Id del usuario a editar.
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
// Trae el usuario actual.
$st = $conexion->prepare('SELECT id, nombre, apellido, dni, email, rol, archivado_en FROM usuarios WHERE id = :i');
$st->execute(['i' => $id]);
// $u = datos actuales.
$u = $st->fetch();
// Si no existe: aviso y vuelve a la lista.
if (!$u) { flash('error', 'El usuario no existe.'); header('Location: admin_usuarios.php'); exit; }
// Un usuario archivado no se edita: primero hay que reactivarlo.
if ($u['archivado_en'] !== null) { flash('error', 'Ese usuario está archivado. Reactivalo antes de editarlo.'); header('Location: usuarios_archivados.php'); exit; }

// true si el admin se está editando a sí mismo.
$esYo = $id === (int)$_SESSION['uid'];
// Mensaje de error.
$error = '';
// Valores precargados con los datos actuales.
$v = ['nombre' => $u['nombre'], 'apellido' => $u['apellido'], 'dni' => $u['dni'], 'email' => $u['email'], 'rol' => $u['rol']];

// Al guardar.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toma solo los 4 campos de texto; el rol se decide aparte, justo abajo.
    foreach (['nombre', 'apellido', 'dni', 'email'] as $k) $v[$k] = trim($_POST[$k] ?? '');
    // Un admin no puede cambiarse el rol a sí mismo (evita quedarse sin administradores)
    $v['rol'] = $esYo ? $u['rol'] : trim($_POST['rol'] ?? '');
    // Contraseña nueva (opcional).
    $pass = $_POST['password'] ?? '';
    if (!csrf_ok()) {
        $error = 'La sesión expiró. Recargá la página.';
    } else {
        // false = contraseña opcional: si queda vacía significa 'no cambiarla'.
        $error = validar_usuario($v + ['password' => $pass], false);
        if ($error === '' && !in_array($v['rol'], ['usuario', 'admin'], true)) $error = 'El rol elegido no es válido.';
        // El 4.º parámetro ($id) excluye a este mismo usuario, para que no se cuente como duplicado
        // de sí mismo.
        if ($error === '') $error = usuario_duplicado($conexion, $v['dni'], $v['email'], $id);
        if ($error === '') {
            // Se arma el UPDATE por partes: la contraseña se agrega SOLO si escribieron una nueva.
            $campos = 'nombre = :n, apellido = :a, dni = :d, email = :e, rol = :r';
            $par = ['n' => $v['nombre'], 'a' => $v['apellido'], 'd' => $v['dni'], 'e' => $v['email'], 'r' => $v['rol'], 'i' => $id];
            // Hay contraseña nueva: se agrega al UPDATE (como hash).
            if ($pass !== '') { $campos .= ', password_hash = :p'; $par['p'] = password_hash($pass, PASSWORD_DEFAULT); }
            try {
                // Es seguro poner $campos dentro del texto SQL porque son nombres de columna
                // escritos por el programador (no datos del usuario).
                $conexion->prepare("UPDATE usuarios SET $campos WHERE id = :i AND archivado_en IS NULL")->execute($par);
                // Si se editó a sí mismo, se actualiza el nombre que aparece en el menú.
                if ($esYo) $_SESSION['nombre'] = $v['nombre'];
                flash('ok', 'Datos del usuario actualizados.');
                header('Location: admin_usuarios.php');
                exit;
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $error = $ex->getCode() === '23000' ? 'Ya existe un usuario con ese DNI o email.' : 'No se pudieron guardar los cambios.';
            }
        }
    }
}
// HTML.
cabecera('Editar usuario'); ?>
<section class="panel" style="max-width:640px;margin:0 auto">
  <h2>Editar usuario</h2>
  <?php if ($error): ?><div class="msg error"><?= e($error) ?></div><?php endif; ?>
  <form class="f" method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="dos">
      <div><label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="80" value="<?= e($v['nombre']) ?>"></div>
      <div><label for="apellido">Apellido</label><input id="apellido" name="apellido" required maxlength="80" value="<?= e($v['apellido']) ?>"></div>
    </div>
    <div class="dos">
      <div><label for="dni">DNI</label><input id="dni" name="dni" required inputmode="numeric" value="<?= e($v['dni']) ?>"></div>
      <div><label for="email">Email</label><input id="email" type="email" name="email" required value="<?= e($v['email']) ?>"></div>
    </div>
    <div class="dos">
      <div><label for="rol">Rol</label>
        <?php /*
           'disabled' hace que el navegador NO envíe este campo; por eso el servidor, si $esYo,
           ignora el rol enviado y mantiene el actual.
        */ ?>
        <select id="rol" name="rol" <?= $esYo ? 'disabled' : '' ?>>
          <option value="usuario" <?= $v['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario (cliente)</option>
          <option value="admin" <?= $v['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
        </select>
        <?php if ($esYo): ?><p class="ayuda">No podés cambiar tu propio rol.</p><?php endif; ?>
      </div>
      <div><label for="password">Nueva contraseña</label><input id="password" type="password" name="password" minlength="8" autocomplete="new-password"><p class="ayuda">Dejala vacía para no cambiarla.</p></div>
    </div>
    <div class="fila">
      <button type="submit">Guardar cambios</button>
      <a class="btn sec" href="admin_usuarios.php">Cancelar</a>
    </div>
  </form>
</section>
<?php pie();
