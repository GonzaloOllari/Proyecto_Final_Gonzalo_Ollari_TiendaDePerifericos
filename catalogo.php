<?php
// ======================================================================
// ARCHIVO: catalogo.php
// NOTA: parece una versión ANTIGUA y duplicada de index.php (hace lo mismo pero sus enlaces
// apuntan a catalogo.php). El menú no enlaza a esta página; solo la nombra contacto.php. Si no
// la usás, se puede borrar. Qué hace: catálogo público con filtros por texto, marca y
// categoría.
// ======================================================================
// Carga conexion.php (base de datos, sesión y funciones). require_once evita cargarlo dos veces.
require_once __DIR__ . '/conexion.php';

// $_GET trae lo que viene en la URL (?q=...&marca=...). trim() quita espacios sobrantes; (int)
// convierte a número (si viene texto raro queda 0); ?? da un valor por defecto si el dato no
// existe.
// Criterios de búsqueda: producto (texto), marca y categoría. Se combinan entre sí.
$f = [
    'q'         => trim($_GET['q'] ?? ''),
    'marca'     => (int)($_GET['marca'] ?? 0),
    'categoria' => (int)($_GET['categoria'] ?? 0),
    'orden'     => $_GET['orden'] ?? 'nombre',
];
// Listas para llenar los botones y los <select> de filtros. query() sin parámetros es seguro porque
// no usa datos del usuario.
$categorias = $conexion->query('SELECT id, nombre FROM categorias ORDER BY id')->fetchAll();
$marcas     = $conexion->query('SELECT id, nombre FROM marcas ORDER BY nombre')->fetchAll();
// Busca los productos con esos filtros (función de conexion.php). true = solo los visibles.
$productos  = buscar_productos($conexion, $f, true);
// true si hay algún filtro aplicado; se usa para mostrar el enlace 'Quitar filtros'.
$hayFiltro  = $f['q'] !== '' || $f['marca'] || $f['categoria'];
// Opciones del <select> 'Ordenar por': la clave va en la URL y el texto se muestra al usuario.
$ordenes    = ['nombre' => 'Nombre (A-Z)', 'precio_asc' => 'Precio: menor a mayor', 'precio_desc' => 'Precio: mayor a menor', 'recientes' => 'Más nuevos'];

// Imprime cabecera y menú.
cabecera('Catálogo'); ?>
<?php /* Panel con título, filtros y contador. */ ?>
<section class="panel">
  <h2>Catálogo de periféricos</h2>
  <?php /* Botones de acceso rápido por categoría; el activo se marca con la clase 'activo'. */ ?>
  <div class="chips">
    <a class="chip <?= !$f['categoria'] ? 'activo' : '' ?>" href="catalogo.php">Todos</a>
    <?php foreach ($categorias as $c): ?>
      <a class="chip <?= $f['categoria'] === (int)$c['id'] ? 'activo' : '' ?>" href="catalogo.php?categoria=<?= (int)$c['id'] ?>"><?= e(icono_categoria($c['nombre'])) ?> <?= e($c['nombre']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php /*
     FORMULARIO DE BÚSQUEDA por GET: los filtros viajan en la URL, así se puede copiar el enlace.
     value= mantiene lo que se había escrito; e() lo protege.
  */ ?>
  <form class="f" method="get" style="grid-template-columns:2fr 1fr 1fr 1fr auto;align-items:end">
    <div><label for="q">Producto</label><input id="q" name="q" maxlength="80" placeholder="Ej: G203, Kumara, Odyssey" value="<?= e($f['q']) ?>"></div>
    <div><label for="marca">Marca</label>
      <select id="marca" name="marca"><option value="0">Todas</option>
        <?php /* Una <option> por marca; 'selected' deja elegida la que está filtrada. */ ?>
        <?php foreach ($marcas as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $f['marca'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="categoria">Categoría</label>
      <select id="categoria" name="categoria"><option value="0">Todas</option>
        <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $f['categoria'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="orden">Ordenar por</label>
      <select id="orden" name="orden">
        <?php /* Una <option> por cada forma de ordenar. */ ?>
        <?php foreach ($ordenes as $k => $txt): ?><option value="<?= e($k) ?>" <?= $f['orden'] === $k ? 'selected' : '' ?>><?= e($txt) ?></option><?php endforeach; ?>
      </select></div>
    <button type="submit">Buscar</button>
  </form>
  <?php /* Contador de resultados, con plural correcto ('1 producto' / '2 productos'). */ ?>
  <p class="ayuda">
    <?= count($productos) ?> producto<?= count($productos) === 1 ? '' : 's' ?> encontrado<?= count($productos) === 1 ? '' : 's' ?>.
    <?php if ($hayFiltro): ?><a href="catalogo.php">Quitar filtros</a><?php endif; ?>
  </p>
</section>

<?php /* Si no hubo resultados se muestra un aviso; si hubo, la grilla de tarjetas. */ ?>
<?php if (!$productos): ?>
  <div class="msg error">No encontramos productos con esos criterios. Probá con otra marca, otra categoría o menos palabras.</div>
<?php else: ?>
  <div class="grilla">
    <?php /* Por cada producto se dibuja una tarjeta (función de conexion.php). */ ?>
    <?php foreach ($productos as $p) tarjeta_producto($p); ?>
  </div>
<?php endif; ?>
<?php /* Cierra la página con el pie. */ ?>
<?php pie();
