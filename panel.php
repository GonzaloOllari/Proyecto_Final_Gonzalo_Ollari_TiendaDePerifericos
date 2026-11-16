<?php
// ======================================================================
// ARCHIVO: panel.php
// PANEL PERSONAL ('Mi panel').
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Página privada: sin sesión redirige al login.
requiere_login();

// Lee SOLO la fila del usuario de la sesión ($_SESSION['uid'] convertido a número).
$st = $conexion->prepare('SELECT nombre, apellido, dni, email, rol, creado_en FROM usuarios WHERE id = :i');
$st->execute(['i' => (int)$_SESSION['uid']]);
// $u queda con los datos del usuario (arreglo asociativo).
$u = $st->fetch();

// HTML.
cabecera('Mi panel'); ?>
<section class="panel" style="max-width:640px;margin:0 auto">
  <h2>Mi panel</h2>
  <p class="ayuda">Estos son los datos de tu cuenta.</p>
  <?php /* Lista de definiciones (dt = dato, dd = valor) dibujada como dos columnas con CSS. */ ?>
  <dl class="datos">
    <dt>Nombre</dt><dd><?= e($u['nombre']) ?></dd>
    <dt>Apellido</dt><dd><?= e($u['apellido']) ?></dd>
    <dt>DNI</dt><dd><?= e($u['dni']) ?></dd>
    <dt>Email</dt><dd><?= e($u['email']) ?></dd>
    <?php /*
       Operador ternario: si el rol es admin muestra 'Administrador', si no 'Cliente'. date()
       formatea la fecha como día/mes/año hora:minutos.
    */ ?>
    <dt>Tipo de cuenta</dt><dd><?= $u['rol'] === 'admin' ? 'Administrador' : 'Cliente' ?></dd>
    <dt>Cuenta creada</dt><dd><?= e(date('d/m/Y H:i', strtotime($u['creado_en']))) ?></dd>
  </dl>
  <?php /*
     Botones de acceso rápido: el admin ve Pedidos/Productos/Usuarios y el cliente ve Mis
     pedidos/Catálogo.
  */ ?>
  <div class="fila" style="margin-top:1.2rem">
    <?php if (es_admin()): ?>
      <a class="btn" href="admin.php">Pedidos</a>
      <a class="btn sec" href="admin_productos.php">Productos</a>
      <a class="btn sec" href="admin_usuarios.php">Usuarios</a>
    <?php else: ?>
      <a class="btn" href="pedidos.php">Mis pedidos</a>
      <a class="btn sec" href="index.php">Catálogo</a>
    <?php endif; ?>
    <a class="btn sec" href="cerrar_sesion.php">Cerrar sesión</a>
  </div>
</section>
<?php pie();
