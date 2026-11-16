<?php
// ======================================================================
// ARCHIVO: usuarios_archivados.php
// LISTA DE USUARIOS ARCHIVADOS (solo administrador).
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Solo administradores.
requiere_admin();

// Texto del buscador.
$q = trim($_GET['q'] ?? '');
// Solo usuarios archivados (archivado_en IS NOT NULL).
$sql = 'SELECT id, nombre, apellido, dni, email, rol, archivado_en FROM usuarios WHERE archivado_en IS NOT NULL';
$par = [];
if ($q !== '') {
    $sql .= " AND (nombre LIKE :q1 OR apellido LIKE :q2 OR dni LIKE :q3 OR email LIKE :q4 OR CONCAT(nombre, ' ', apellido) LIKE :q5)";
    $like = '%' . addcslashes($q, '%_\\') . '%';
    // Un marcador por aparición (q1 a q5); los % del LIKE se escapan arriba con addcslashes.
    $par = ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
}
// Los archivados más recientemente aparecen primero.
$st = $conexion->prepare($sql . ' ORDER BY archivado_en DESC LIMIT 500');
$st->execute($par);
$usuarios = $st->fetchAll();

// HTML.
cabecera('Usuarios archivados'); ?>
<?php flash_mostrar(); ?>
<section class="panel">
  <h2>Usuarios archivados (<?= count($usuarios) ?>)</h2>
  <form class="f" method="get" style="grid-template-columns:3fr auto auto;align-items:end">
    <div><label for="q">Buscar por nombre, apellido, DNI o email</label><input id="q" name="q" value="<?= e($q) ?>"></div>
    <button type="submit">Buscar</button>
    <?php if ($q !== ''): ?><a class="btn sec" href="usuarios_archivados.php">Limpiar</a><?php endif; ?>
  </form>
  <p class="ayuda"><a href="admin_usuarios.php">← Volver a usuarios activos</a></p>
  <div class="tabla" style="margin-top:.8rem"><table>
    <tr><th>Nombre</th><th>DNI</th><th>Email</th><th>Rol</th><th>Archivado el</th><th>Acción</th></tr>
    <?php /* Una fila por usuario archivado. */ ?>
    <?php foreach ($usuarios as $u): ?>
    <tr>
      <td><?= e($u['apellido'] . ', ' . $u['nombre']) ?></td>
      <td><?= e($u['dni']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['rol']) ?></td>
      <td><?= e(date('d/m/Y H:i', strtotime($u['archivado_en']))) ?></td>
      <td><div class="fila">
        <?php /* Envía por POST a reactivar_usuario.php. */ ?>
        <form method="post" action="reactivar_usuario.php">
          <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit">Reactivar</button>
        </form>
        <?php /* Envía por POST a eliminar_usuario.php; 'volver' hace que regrese a esta lista. */ ?>
        <form method="post" action="eliminar_usuario.php">
          <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="volver" value="usuarios_archivados.php">
          <button type="submit" onclick="return confirm('¿ELIMINAR a este usuario definitivamente? También se borran sus pedidos y no se puede deshacer.')">Eliminar</button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; if (!$usuarios): ?><tr><td colspan="6">No hay usuarios archivados.</td></tr><?php endif; ?>
  </table></div>
</section>
<?php pie();
