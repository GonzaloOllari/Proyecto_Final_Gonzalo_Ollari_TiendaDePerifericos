<?php
// ======================================================================
// ARCHIVO: editar_producto.php
// EDICIÓN DE UN PRODUCTO (solo administrador). editar_producto.php?id=NUMERO.
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Solo administradores.
requiere_admin();

// Id del producto (por GET al abrir, por POST al guardar).
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
// Trae el producto actual.
$st = $conexion->prepare('SELECT p.id, p.nombre, p.descripcion, p.marca_id, p.categoria_id, p.precio, p.stock, p.activo, p.imagen, c.nombre AS categoria
                          FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.id = :i');
$st->execute(['i' => $id]);
// $p = datos actuales del producto (o false).
$p = $st->fetch();
// Si no existe: mensaje flash de error y vuelve al listado.
if (!$p) { flash('error', 'El producto no existe.'); header('Location: admin_productos.php'); exit; }

// Listas para los <select> y listas de ids válidos.
$categorias = $conexion->query('SELECT id, nombre FROM categorias ORDER BY id')->fetchAll();
$marcas     = $conexion->query('SELECT id, nombre FROM marcas ORDER BY nombre')->fetchAll();
$catIds     = array_map('intval', array_column($categorias, 'id'));
$marcaIds   = array_map('intval', array_column($marcas, 'id'));

// Mensaje de error.
$error = '';
// Valores del formulario, PRECARGADOS con los datos actuales del producto.
$v = ['nombre' => $p['nombre'], 'descripcion' => $p['descripcion'], 'marca_id' => (string)$p['marca_id'], 'marca_nueva' => '',
      'categoria_id' => (string)$p['categoria_id'], 'precio' => (string)$p['precio'], 'stock' => (string)$p['stock'], 'activo' => (string)$p['activo']];

// Al guardar: se pisan los valores con lo enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toma cada campo enviado con trim().
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    // Foto que supera el límite de PHP: llega todo vacío; se avisa y se restauran los valores
    // originales.
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = 'La foto es demasiado pesada (máximo 2 MB).';
        $v = ['nombre' => $p['nombre'], 'descripcion' => $p['descripcion'], 'marca_id' => (string)$p['marca_id'], 'marca_nueva' => '',
              'categoria_id' => (string)$p['categoria_id'], 'precio' => (string)$p['precio'], 'stock' => (string)$p['stock'], 'activo' => (string)$p['activo']];
    } elseif (!csrf_ok()) {
        // Token CSRF inválido.
        $error = 'La sesión expiró. Recargá la página.';
    } else {
        // Camino normal: validar datos, subir la foto nueva (si hay) y guardar.
        // Validaciones de nombre, precio, stock, categoría, marca y visibilidad (solo '0' o '1').
        $error = validar_producto($v);
        if ($error === '') {
            if (!in_array((int)$v['categoria_id'], $catIds, true)) $error = 'Elegí una categoría.';
            elseif ($v['marca_nueva'] !== '') {
                if (mb_strlen($v['marca_nueva']) > 60) $error = 'El nombre de la marca puede tener hasta 60 caracteres.';
            } elseif (!in_array((int)$v['marca_id'], $marcaIds, true)) $error = 'Elegí una marca o escribí una nueva.';
            elseif (!in_array($v['activo'], ['0', '1'], true)) $error = 'Visibilidad inválida.';
        }
        // Nombre de la foto nueva (null = no se subió ninguna).
        $nueva = null;
        if ($error === '') [$nueva, $error] = subir_imagen('foto');
        if ($error === '') {
            // Un checkbox solo llega en $_POST si fue tildado; isset() lo detecta.
            $quitar = isset($_POST['quitar_foto']);
            // Qué foto queda: si se subió una nueva, esa; si se tildó 'Quitar', ninguna; si no, se
            // mantiene la actual.
            $img = $p['imagen'];
            if ($nueva !== null) $img = $nueva;
            elseif ($quitar) $img = null;
            try {
                // Marca nueva (se crea si hace falta) o la elegida.
                $marcaId = $v['marca_nueva'] !== '' ? obtener_marca_id($conexion, $v['marca_nueva']) : (int)$v['marca_id'];
                // UPDATE preparado de todos los campos del producto.
                $conexion->prepare('UPDATE productos SET nombre = :n, descripcion = :d, marca_id = :m, categoria_id = :c, precio = :p, stock = :s, activo = :a, imagen = :img WHERE id = :i')
                    ->execute(['n' => $v['nombre'], 'd' => $v['descripcion'], 'm' => $marcaId, 'c' => (int)$v['categoria_id'], 'p' => $v['precio'],
                               's' => (int)$v['stock'], 'a' => (int)$v['activo'], 'img' => $img, 'i' => $id]);
                // Recién DESPUÉS de guardar bien en la base se borra del disco la foto vieja que ya
                // no se usa.
                if ($img !== $p['imagen']) borrar_imagen($p['imagen']);   // ya no se usa la foto anterior
                flash('ok', 'Producto actualizado.');
                header('Location: admin_productos.php');
                exit;
            } catch (PDOException $ex) {
                // Si falló el guardado se borra la foto nueva recién subida.
                borrar_imagen($nueva);
                error_log($ex->getMessage());
                $error = 'No se pudieron guardar los cambios.';
            }
        }
    }
}
// HTML.
cabecera('Editar producto'); ?>
<section class="panel" style="max-width:700px;margin:0 auto">
  <h2>Editar producto</h2>
  <?php if ($error): ?><div class="msg error"><?= e($error) ?></div><?php endif; ?>
  <form class="f" method="post" enctype="multipart/form-data">
    <?php /* Id oculto: al enviar, el servidor sabe qué producto está editando. */ ?>
    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div><label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="120" value="<?= e($v['nombre']) ?>"></div>
    <div><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="3" maxlength="500"><?= e($v['descripcion']) ?></textarea></div>
    <div class="dos">
      <div><label for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id">
          <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$v['categoria_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select></div>
      <div><label for="marca_id">Marca</label>
        <select id="marca_id" name="marca_id">
          <?php foreach ($marcas as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)$v['marca_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div><label for="marca_nueva">Marca nueva (opcional, reemplaza a la de arriba)</label><input id="marca_nueva" name="marca_nueva" maxlength="60" value="<?= e($v['marca_nueva']) ?>"></div>
    <div class="dos">
      <div><label for="precio">Precio ($)</label><input id="precio" type="number" name="precio" required min="0.01" step="0.01" value="<?= e($v['precio']) ?>"></div>
      <div><label for="stock">Stock</label><input id="stock" type="number" name="stock" required min="0" step="1" value="<?= e($v['stock']) ?>"></div>
    </div>
    <div><label for="activo">Visibilidad en el catálogo</label>
      <select id="activo" name="activo">
        <option value="1" <?= $v['activo'] === '1' ? 'selected' : '' ?>>Visible</option>
        <option value="0" <?= $v['activo'] === '0' ? 'selected' : '' ?>>Oculto</option>
      </select></div>
    <div>
      <label>Foto actual</label>
      <?php /* Muestra la foto actual (o el emoji de la categoría). */ ?>
      <div class="foto-actual"><?= html_imagen($p) ?></div>
      <?php if (nombre_imagen_valido($p['imagen'])): ?>
        <?php /* Casilla 'Quitar la foto actual' (solo aparece si el producto ya tiene foto). */ ?>
        <label style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem"><input type="checkbox" name="quitar_foto" value="1" style="width:auto"> Quitar la foto actual</label>
      <?php endif; ?>
    </div>
    <div><label for="foto"><?= nombre_imagen_valido($p['imagen']) ? 'Reemplazar la foto' : 'Subir una foto' ?> (opcional)</label>
      <input id="foto" type="file" name="foto" accept="image/jpeg,image/png,image/gif,image/webp">
      <p class="ayuda">JPG, PNG, GIF o WEBP, hasta 2 MB.</p></div>
    <div class="fila">
      <button type="submit">Guardar cambios</button>
      <a class="btn sec" href="admin_productos.php">Cancelar</a>
    </div>
  </form>
</section>
<?php pie();
