<?php
// ======================================================================
// ARCHIVO: admin_productos.php
// GESTIÓN DE PRODUCTOS (solo administrador).
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Solo administradores.
requiere_admin();

// Listas para los <select> (categorías y marcas).
$categorias = $conexion->query('SELECT id, nombre FROM categorias ORDER BY id')->fetchAll();
$marcas     = $conexion->query('SELECT id, nombre FROM marcas ORDER BY nombre')->fetchAll();
// Listas de ids válidos: sirven para comprobar que lo enviado por el formulario sea una opción real
// y no un valor inventado.
$catIds     = array_map('intval', array_column($categorias, 'id'));
$marcaIds   = array_map('intval', array_column($marcas, 'id'));

// Mensaje de error del formulario 'Agregar producto'.
$error = '';
// Valores del formulario de alta (para volver a mostrarlos si hay error).
$v = ['nombre' => '', 'descripcion' => '', 'marca_id' => '0', 'marca_nueva' => '', 'categoria_id' => '0', 'precio' => '', 'stock' => '0'];

// Esta página recibe DOS acciones por POST, que se distinguen con el campo oculto 'accion':
// 'alternar' (ocultar/mostrar) y 'crear' (alta).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Truco: si el archivo supera el límite post_max_size de PHP, PHP descarta todo el formulario y
    // $_POST llega vacío.
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = 'La foto es demasiado pesada (máximo 2 MB).';   // PHP descartó todo el formulario por su tamaño
    } elseif (!csrf_ok()) {
        // Token CSRF inválido o vencido.
        $error = 'La sesión expiró. Recargá la página.';
    } elseif (($_POST['accion'] ?? '') === 'alternar') {
        // Acción OCULTAR / MOSTRAR.
        // Ocultar o mostrar un producto (no se borra: los pedidos viejos lo siguen usando)
        // '1 - activo' invierte el valor: si era 1 pasa a 0 y viceversa.
        $conexion->prepare('UPDATE productos SET activo = 1 - activo WHERE id = :i')->execute(['i' => (int)($_POST['id'] ?? 0)]);
        flash('ok', 'Visibilidad del producto actualizada.');
        header('Location: admin_productos.php');
        exit;
    } elseif (($_POST['accion'] ?? '') === 'crear') {
        // Acción CREAR producto: validar, subir foto y guardar.
        // Toma cada campo del formulario con trim().
        foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
        // Valida nombre, descripción, precio y stock (función de conexion.php).
        $error = validar_producto($v);
        if ($error === '') {
            // in_array(..., true) comprueba que la categoría/marca elegida exista de verdad (lista
            // blanca).
            if (!in_array((int)$v['categoria_id'], $catIds, true)) $error = 'Elegí una categoría.';
            elseif ($v['marca_nueva'] !== '') {
                if (mb_strlen($v['marca_nueva']) > 60) $error = 'El nombre de la marca puede tener hasta 60 caracteres.';
            } elseif (!in_array((int)$v['marca_id'], $marcaIds, true)) $error = 'Elegí una marca o escribí una nueva.';
        }
        // Nombre de la foto subida (null = sin foto).
        $foto = null;
        // Desestructuración: subir_imagen devuelve un par [nombre, error] y cada valor va a su
        // variable.
        if ($error === '') [$foto, $error] = subir_imagen('foto');
        if ($error === '') {
            try {
                // Si escribió una marca nueva se la busca/crea; si no, se usa la marca elegida.
                $marcaId = $v['marca_nueva'] !== '' ? obtener_marca_id($conexion, $v['marca_nueva']) : (int)$v['marca_id'];
                // INSERT preparado con todos los datos del producto.
                $conexion->prepare('INSERT INTO productos (nombre, descripcion, marca_id, categoria_id, precio, stock, imagen)
                                    VALUES (:n, :d, :m, :c, :p, :s, :img)')
                    ->execute(['n' => $v['nombre'], 'd' => $v['descripcion'], 'm' => $marcaId, 'c' => (int)$v['categoria_id'],
                               'p' => $v['precio'], 's' => (int)$v['stock'], 'img' => $foto]);
                flash('ok', 'Producto agregado al catálogo.');
                header('Location: admin_productos.php');
                exit;
            } catch (PDOException $ex) {
                // Si falló el guardado en la base, se borra la foto recién subida para no dejar
                // archivos huérfanos.
                borrar_imagen($foto);   // si no se pudo guardar el producto, no queda la foto huérfana
                error_log($ex->getMessage());
                $error = 'No se pudo guardar el producto.';
            }
        }
    }
}

// Filtros del listado (por URL): igual que en el catálogo.
$f = [
    'q'         => trim($_GET['q'] ?? ''),
    'marca'     => (int)($_GET['marca'] ?? 0),
    'categoria' => (int)($_GET['categoria'] ?? 0),
    'orden'     => $_GET['orden'] ?? 'nombre',
];
// false = incluir también los productos ocultos (el admin ve todo).
$productos = buscar_productos($conexion, $f, false, 500);

// HTML.
cabecera('Productos'); ?>
<?php flash_mostrar(); ?>
<section class="panel">
  <h2>Productos (<?= count($productos) ?>)</h2>
  <?php /* Formulario de filtros del listado. */ ?>
  <form class="f" method="get" style="grid-template-columns:2fr 1fr 1fr auto auto;align-items:end">
    <div><label for="q">Producto</label><input id="q" name="q" value="<?= e($f['q']) ?>"></div>
    <div><label for="marca">Marca</label>
      <select id="marca" name="marca"><option value="0">Todas</option>
        <?php foreach ($marcas as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $f['marca'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?></select></div>
    <div><label for="categoria">Categoría</label>
      <select id="categoria" name="categoria"><option value="0">Todas</option>
        <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $f['categoria'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select></div>
    <button type="submit">Buscar</button>
    <?php if ($f['q'] !== '' || $f['marca'] || $f['categoria']): ?><a class="btn sec" href="admin_productos.php">Limpiar</a><?php endif; ?>
  </form>
  <?php /* Tabla de productos. */ ?>
  <div class="tabla" style="margin-top:.8rem"><table>
    <tr><th>Foto</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Visibilidad</th><th>Acciones</th></tr>
    <?php /* Una fila por producto. */ ?>
    <?php foreach ($productos as $p): ?>
    <tr>
      <td><div class="mini"><?= html_imagen($p) ?></div></td>
      <td><?= e($p['nombre']) ?><br><small><?= e($p['marca']) ?></small></td>
      <td><?= e($p['categoria']) ?></td>
      <td><?= e(precio($p['precio'])) ?></td>
      <td><?= (int)$p['stock'] ?></td>
      <td><span class="etq <?= $p['activo'] ? 'confirmado' : 'cancelado' ?>"><?= $p['activo'] ? 'Visible' : 'Oculto' ?></span></td>
      <td><div class="fila">
        <a class="btn chico sec" href="editar_producto.php?id=<?= (int)$p['id'] ?>">Editar</a>
        <?php /* Botón Ocultar/Mostrar: un mini formulario POST por fila, con token CSRF y el id oculto. */ ?>
        <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="alternar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button type="submit"><?= $p['activo'] ? 'Ocultar' : 'Mostrar' ?></button></form>
      </div></td>
    </tr>
    <?php endforeach; if (!$productos): ?><tr><td colspan="7">No hay productos con esos criterios.</td></tr><?php endif; ?>
  </table></div>
</section>

<section class="panel">
  <?php /* FORMULARIO DE ALTA. */ ?>
  <h2>Agregar producto</h2>
  <?php if ($error): ?><div class="msg error"><?= e($error) ?></div><?php endif; ?>
  <?php /* enctype multipart/form-data es OBLIGATORIO en formularios que suben archivos. */ ?>
  <form class="f" method="post" enctype="multipart/form-data">
    <?php /* Campo oculto que indica la acción 'crear'. */ ?>
    <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
    <div><label for="n-nombre">Nombre</label><input id="n-nombre" name="nombre" required maxlength="120" value="<?= e($v['nombre']) ?>"></div>
    <div><label for="n-desc">Descripción</label><textarea id="n-desc" name="descripcion" rows="3" maxlength="500"><?= e($v['descripcion']) ?></textarea></div>
    <div class="dos">
      <div><label for="n-cat">Categoría</label>
        <select id="n-cat" name="categoria_id"><option value="0">Elegir…</option>
          <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$v['categoria_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select></div>
      <div><label for="n-marca">Marca</label>
        <select id="n-marca" name="marca_id"><option value="0">Elegir…</option>
          <?php foreach ($marcas as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)$v['marca_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?></select>
        <p class="ayuda">¿No está? Escribí una nueva abajo.</p></div>
    </div>
    <div><label for="n-marca2">Marca nueva (opcional)</label><input id="n-marca2" name="marca_nueva" maxlength="60" value="<?= e($v['marca_nueva']) ?>"></div>
    <div class="dos">
      <div><label for="n-precio">Precio ($)</label><input id="n-precio" type="number" name="precio" required min="0.01" step="0.01" value="<?= e($v['precio']) ?>"></div>
      <div><label for="n-stock">Stock</label><input id="n-stock" type="number" name="stock" required min="0" step="1" value="<?= e($v['stock']) ?>"></div>
    </div>
    <?php /*
       accept solo filtra lo que ofrece el selector de archivos; la validación real está en
       subir_imagen() porque el navegador se puede engañar.
    */ ?>
    <div><label for="n-foto">Foto del producto (opcional)</label><input id="n-foto" type="file" name="foto" accept="image/jpeg,image/png,image/gif,image/webp">
      <p class="ayuda">JPG, PNG, GIF o WEBP, hasta 2 MB. Si no subís una, se muestra el ícono de la categoría.</p></div>
    <button type="submit">Agregar al catálogo</button>
  </form>
</section>
<?php pie();
