# OllariTech – Trabajo Práctico Final

Autor: Gonzalo Ollari · Sagrado Corazón · 4A · 2026

OllariTech es una tienda web de periféricos (mouse, teclados, auriculares y monitores) hecha con HTML, CSS, PHP, PDO y MySQL.

**Cuenta de administrador de prueba:** `admin@tienda.com` / `Admin1234` (cambiar la clave después de entrar).

## Instalación

1. Copiar la carpeta del proyecto a `C:\xampp\htdocs\`.
2. Iniciar **Apache** y **MySQL** desde XAMPP.
3. En phpMyAdmin, importar `tienda_perifericos.sql` (crea la base `tienda_perifericos`, las tablas y productos de ejemplo).
4. Abrir `http://localhost/<nombre-de-la-carpeta>/`.

## Explicación general del sistema

- **Público:** la página de inicio es el **catálogo**, con búsqueda por producto, marca y categoría, y el detalle de cada producto.
- **Usuario:** se registra, inicia sesión y accede a su **panel personal** (`panel.php`) con sus datos. Desde el detalle de un producto aprieta **Comprar** y el pedido queda registrado al instante; después ve sus pedidos e imprime el comprobante.
- **Administrador:** puede **editar el catálogo** (alta, edición, precio, stock, **foto de cada producto** y ocultar/mostrar), administrar los **usuarios** (alta, listado, búsqueda, edición, archivado, reactivación y eliminación definitiva) y **ver los pedidos realizados**: cada compra aparece con quién la hizo, cuándo y todos los detalles (cliente, DNI, email, producto, marca, categoría, cantidad, precio y total). No tiene otras funciones.
- **Stock:** al comprar se descuenta el stock en una transacción, y no se puede comprar más de lo que hay.
- **Fotos:** se guardan en `uploads/productos/` y en la base solo queda el nombre del archivo.

## Archivos principales

| Archivo | Para qué sirve |
|---|---|
| `conexion.php` | Conexión PDO, configuración del local, sesión, CSRF, roles y funciones comunes |
| `registro.php` / `login.php` / `cerrar_sesion.php` | Registro, inicio y cierre de sesión |
| `panel.php` | Panel personal |
| `admin_usuarios.php` | Listado, búsqueda y alta de usuarios |
| `editar_usuario.php` | Edición de usuarios |
| `archivar_usuario.php` / `usuarios_archivados.php` / `reactivar_usuario.php` | Archivado, listado de archivados y reactivación |
| `eliminar_usuario.php` | Eliminación definitiva de un usuario (admin) |
| `index.php` / `producto.php` | Catálogo con filtros (página de inicio) y detalle con pedido |
| `pedidos.php` / `comprobante.php` | Pedidos del usuario y comprobante imprimible |
| `admin.php` | Pedidos realizados con todos sus detalles (admin) |
| `uploads/productos/` | Fotos de los productos (con `.htaccess` que bloquea PHP) |
| `actualizar_bd.sql` | Solo si ya tenías la base de la versión anterior |
| `admin_productos.php` / `editar_producto.php` | Gestión de productos (admin) |
| `css/estilos.css` | Estilos |
| `tienda_perifericos.sql` | Base de datos |

## Medidas de seguridad

- Contraseñas guardadas con `password_hash()` y comprobadas con `password_verify()`. Nunca se muestran ni se leen los hashes fuera del login.
- Todas las consultas usan **consultas preparadas con PDO** (también los `LIKE` de los buscadores).
- **Sesiones PHP** con cookie `HttpOnly` y `SameSite`, y `session_regenerate_id()` al iniciar sesión.
- Las páginas privadas llaman a `requiere_login()`; las de administración, a `requiere_admin()`. El rol y el estado de la cuenta se leen de la base en cada pedido, así que una cuenta archivada pierde el acceso aunque tenga la sesión abierta.
- **Token CSRF** en todos los formularios que modifican datos; archivar, reactivar y las acciones de admin solo aceptan **POST**.
- Todo lo que se muestra en HTML pasa por `htmlspecialchars()` (función `e()`).
- Validación de datos en el servidor (DNI, email, contraseña, precio, stock, estados permitidos con listas blancas).
- Un administrador no puede archivarse, eliminarse ni cambiarse el rol a sí mismo.
- Eliminar un usuario pide confirmación, solo acepta POST con token CSRF y borra también sus pedidos.
- Los pedidos solo los ve su dueño o un administrador.
- **Subida de fotos:** se valida el contenido real del archivo (JPG, PNG, GIF o WEBP), máximo 2 MB, y se guarda con un nombre aleatorio y la extensión elegida por el servidor. La carpeta de fotos no ejecuta PHP.

## Pruebas obligatorias (marcar al probar)

1. [ ] Registrar un usuario desde `registro.php`.
2. [ ] Intentar registrar otro con el mismo DNI o email: debe rechazarlo con un mensaje claro.
3. [ ] Iniciar y cerrar sesión con ese usuario.
4. [ ] Entrar a `panel.php` sin sesión: redirige al login con aviso.
5. [ ] Con un usuario común, abrir `admin_usuarios.php`: muestra "Acceso denegado".
6. [ ] Como admin: registrar, buscar y editar un usuario.
7. [ ] Archivar un usuario: desaparece de la lista de activos.
8. [ ] En `usuarios_archivados.php`: verlo y reactivarlo.
   - Extra: eliminar un usuario de prueba y comprobar que desaparece.
9. [ ] Probar errores (campos vacíos, clave corta, login incorrecto): los mensajes se entienden.

Pruebas extra de la tienda: buscar en el catálogo por marca, por categoría y por producto; comprar un producto y ver que baja el stock y que el pedido aparece en el panel del admin; subir, reemplazar y quitar la foto de un producto.

## Capturas de pantalla (sacarlas vos)

Catálogo (inicio) con un filtro aplicado, detalle de producto, registro con error de duplicado, panel personal, `admin_usuarios.php` con búsqueda, edición de usuario, usuarios archivados, pedidos realizados del admin, edición de un producto con su foto y el mensaje "Acceso denegado".

## Dificultades encontradas (borrador: reemplazá por lo que realmente te pasó)

- Evitar que dos clientes compren la última unidad: se resolvió descontando el stock con una sola consulta `UPDATE ... WHERE stock >= cantidad` dentro de una transacción.
- Subir fotos de forma segura: se verifica el tipo real del archivo y se guarda con nombre propio, para que no se pueda subir un archivo PHP disfrazado.
- Que una cuenta archivada no siga usando el sitio con la sesión abierta: se revalida la cuenta contra la base en cada página privada.
- Mantener los buscadores seguros: se usaron parámetros en los `LIKE` y se escaparon `%` y `_`.
