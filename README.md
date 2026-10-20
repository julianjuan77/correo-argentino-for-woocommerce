# Correo Argentino for WooCommerce — Fork Julian Juan

> **Fork no oficial** del plugin [Correo Argentino for WooCommerce](https://www.correoargentino.com.ar/MiCorreo/public/mi-correo) (versión base 3.0.7).
> No está afiliado ni respaldado por Correo Argentino. Se publica bajo **GPLv2 o posterior**, la misma licencia del plugin original, conservando su autoría.

Integrá tu tienda WooCommerce con Correo Argentino para gestionar tus envíos **PAQ.AR** y **MiCorreo**: cotización en tiempo real, envío a domicilio o a sucursal, impresión de etiquetas y seguimiento, todo desde el panel de WordPress.

Esta es una versión corregida del plugin oficial: soluciona varios errores que rompían el carrito o el checkout en ciertos casos, y mejora la experiencia al elegir una sucursal de entrega.

**⬇️ [Descargar la última versión](https://github.com/julianjuan77/correo-argentino-for-woocommerce/releases/latest)** — no hace falta clonar el repositorio, ver [Descarga e instalación](#descarga-e-instalación).

---

## ¿Qué trae esta versión que no tiene la oficial?

- **El carrito ya no se rompe.** Antes, si la conexión con Correo Argentino fallaba por un instante, todo el carrito dejaba de funcionar con un error fatal. Ahora, si eso pasa, simplemente no se ofrece esa opción de envío — el resto de la tienda sigue andando normal.
- **Checkout más prolijo al elegir sucursal.** Se autocompletan los datos del destinatario (nombre, apellido, teléfono y DNI), se muestra un cartel de "Cargando sucursales…" mientras se consultan, y ya no aparece de más la sección de "enviar a otra dirección" cuando no corresponde.
- **Compatible con el sistema de pedidos de alto rendimiento (HPOS)** de WooCommerce — la versión oficial lo declaraba pero fallaba al abrir ciertos pedidos.
- **Reconexión automática más confiable.** Un error de zona horaria hacía que el plugin pidiera un nuevo token de conexión casi en cada visita a la tienda, lo que podía generar lentitud al elegir sucursal. Ya está corregido.

## Requisitos

| | |
|---|---|
| WordPress | 6.7 o superior |
| WooCommerce | 8.9 o superior, con **checkout clásico** (no el de bloques) |
| PHP | 7.4 o superior |
| Credenciales | Tu usuario y contraseña de [Mi Correo](https://www.correoargentino.com.ar/MiCorreo/public/), o tu Agreement/Apikey de la API PAQ.AR 2.0 |

> El checkout por **bloques** (el nuevo editor de Gutenberg para la pantalla de pago) todavía no lo soporta ni el plugin oficial ni este fork. Si tu tienda lo usa, el plugin sigue funcionando pero sin las mejoras visuales de sucursal.

## Descarga e instalación

**No hace falta clonar el repositorio.** Bajá el plugin ya armado como un `.zip` listo para instalar:

1. Andá a la página de [**Releases**](https://github.com/julianjuan77/correo-argentino-for-woocommerce/releases/latest) y descargá el archivo `correo-argentino-for-woocommerce-X.Y.Z.zip` de la última versión.
2. En tu sitio, andá a **Plugins → Añadir nuevo → Subir plugin** y seleccioná el `.zip` descargado.
3. Activalo desde el listado de **Plugins**.
4. Andá a **WooCommerce → Ajustes → Envío → Correo Argentino** y cargá tus credenciales de Mi Correo o de PAQ.AR.
5. *(Opcional, recomendado)* En **WooCommerce → Ajustes → Envío**, configurá el destino por defecto como "dirección de facturación" para la mejor experiencia en envíos a domicilio.

### 📖 Manual de usuario oficial

Para la configuración paso a paso del plugin (PAQ.AR, MiCorreo, zonas de envío, envío gratis, recargos/descuentos, el panel de MiCorreo y cómo dar de alta una sucursal cabecera), Correo Argentino publica un manual de usuario completo:

**[📄 Manual de usuario — Integración Plugin de Correo Argentino para WooCommerce](docs/manual-usuario-micorreo-woocommerce-v3.0.8.pdf)**

## ¿Problemas para iniciar sesión con Mi Correo?

Si al conectar la cuenta aparece el mensaje *"Ha ocurrido un error al conectarse a MiCorreo"*, revisá primero:

- Que el **usuario y contraseña** sean los mismos que usás para entrar a [MiCorreo](https://www.correoargentino.com.ar/MiCorreo/public/) (no los de tu cuenta de WordPress).
- Que el **país de tu tienda**, en WooCommerce → Ajustes → General, esté configurado como **Argentina**. El plugin no funciona con otro país de origen.
- Que tu servidor tenga salida a internet habilitada hacia `api.correoargentino.com.ar` (puede bloquearlo un firewall o un plugin de seguridad).

## Preguntas frecuentes

**¿Puedo usarlo junto con el plugin oficial?** No — tenés que desactivar el oficial antes de activar este fork, ambos usan el mismo identificador interno.

**¿Se pierde algo al migrar del oficial a este fork?** No. Es exactamente el mismo plugin con correcciones; tus pedidos, sucursales y configuración existentes se mantienen igual.

**¿Y si Correo Argentino saca una versión oficial que ya corrige estos problemas?** Se puede desactivar este fork y pasar a la oficial sin perder datos: ambos comparten la misma estructura de información.

## Licencia

GPLv2 o posterior. Ver [`license.txt`](license.txt). El código original es de Correo Argentino; este fork mantiene la misma licencia y su atribución.

---

# Sección técnica

Lo que sigue es para quien instala el plugin por código, lo personaliza con hooks, o contribuye al repo.

## Arquitectura

PHP clásico (sin build step), con un autoloader PSR-4 propio para el namespace `Corrarfo\` (ver `loader.php`). Estructura por responsabilidad:

```
Classes/    Método de envío de WooCommerce (WC_Shipping_Method)
Hooks/      Enganches a WordPress/WooCommerce (checkout, admin, pedidos)
Orders/     Metabox, sucursal y etiqueta del pedido
Service/    Clientes de la API: Mi Correo y PAQ.AR 2.0
Settings/   Pantallas de configuración del admin
Tables/     Listado de pedidos de Correo Argentino
Helper/     Utilidades puntuales (prefijo de pedido, ajustes de envío, etc.)
```

Cada clase lleva alias de compatibilidad hacia atrás (`class_alias`) con el nombre original del plugin oficial, para no romper integraciones de terceros que lo referencien por su nombre global.

## Credenciales de la API (Mi Correo)

Las credenciales *partner* de fábrica (`WOOCOMMERCE:...`, en `Lib/Constants.php`) no son de tu tienda ni de ningún cliente: son la clave genérica que Correo Argentino le da a cualquier instalación del plugin oficial para autenticarse como aplicación y obtener un token; después cada tienda valida su propia cuenta de MiCorreo por separado. Cuando Correo Argentino las rota quedan inválidas y devuelven **401**. Si eso pasa, cargá las tuyas en `wp-config.php`, antes de `/* That's all, stop editing! */`:

```php
define( 'CORRARFO_USERNAME_MI_CORREO_PRODUCTION', 'usuario-real' );
define( 'CORRARFO_PASSWORD_MI_CORREO_PRODUCTION', 'clave-real' );
```

Luego re-guardá los ajustes de Correo Argentino para regenerar el token.

## Hooks / filtros

| Filtro | Default | Para qué |
|--------|---------|----------|
| `corrarfo_fill_destination_from_billing` | `true` | Devolver `false` para desactivar el fallback que completa el destino de envío con la dirección de facturación. |
| `corrarfo_recipient_document_keys` | lista AR | Agregar/quitar nombres de campo de documento (DNI/CUIT/…) que se autocompletan en el destinatario. |

```php
// Desactivar el fallback de destino a facturación:
add_filter( 'corrarfo_fill_destination_from_billing', '__return_false' );

// Agregar un nombre de campo de documento propio del tema:
add_filter( 'corrarfo_recipient_document_keys', function ( $keys ) {
	$keys[] = 'mi_campo_documento';
	return $keys;
} );
```

## Compatibilidad y límites conocidos

- Pensado para el **checkout clásico**. En el checkout por bloques, la UX de sucursal y el autocompletado se ignoran (no rompen).
- El autocompletado del destinatario usa los IDs `#billing_*`/`#shipping_*`. Si un tema/plugin renombra los campos, se ignora lo que no encuentre.
- La UX de ocultar/mostrar la sección de envío usa una clase con `display:none !important` para ganarle al JS del plugin base; si un tema tiene su propia gestión de esos campos, revisá que no haya conflicto.

## Lint y calidad de código

El plugin trae `composer.json`/`phpcs.xml`/`phpstan.neon.dist` propios. El ruleset de PHPCS está acotado a sniffs de seguridad y corrección (nonces, sanitización/escape, SQL preparado, funciones de PHP peligrosas) — no al estilo de código completo del estándar `WordPress`, porque el código heredado del fork original nunca se escribió en ese formato.

Dentro del entorno de desarrollo (`wp-dev`):

```bash
make lint-plugin SITE=<sitio> PLUGIN=correo-argentino-for-woocommerce
```

## Changelog detallado (3.0.7 → 3.0.7.2)

1. **Fatal 500 por token 401.** `try/catch` en el cálculo de tarifas: si la API falla, no ofrece tarifa en vez de tumbar carrito y checkout.
2. **Credenciales de API configurables** desde `wp-config.php` (antes eran `const` fijas imposibles de cambiar).
3. **"Enviar a otra dirección" ya no se fuerza en domicilio.** La activación deja de imponer `ship_to_destination='shipping'`; usa el default de WooCommerce. La sucursal sigue funcionando (el JS marca el check solo en ese caso).
4. **Fallback del destino de envío a facturación** (para que domicilio cotice sin "otra dirección"). Filtrable (ver arriba).
5. **Timeouts y guardas de null** en todas las llamadas a la API.
6. **`esc_attr()`/`esc_html()`** en el selector de sucursales (anti-XSS).
7. **Reintento de token ante 401** en `/rates`, `/shipping/import` y `/agencies`.
8. **HPOS:** arreglado el fatal al abrir el metabox del pedido (`WC_Order` vs `WP_Post`).
9. **HPOS/admin:** arreglado el fatal al crear un pedido manual (sesión nula).
10. **Renovación de token robusta** (lock + reintento) ante cambios rápidos de sucursal.
11. **Bug de zona horaria (causa raíz de #10):** la API devuelve `expire` en hora de Argentina; se usa el claim `exp` del **JWT** (UTC) para validar el token.
12. **Endpoint REST público sin autenticación, eliminado** (`/correoagentino/labels`, código muerto, nunca activo en producción).
13. **Nonce agregado** a los botones "Ver Rótulo"/"Cancel" del metabox del pedido (antes fallaban siempre).
14. **`uninstall.php`** ahora también limpia los metadatos de pedidos en HPOS (`wc_orders_meta`), no solo la tabla legacy.
15. **Código muerto eliminado:** clase `Service` sin uso y sus fixtures de prueba (`Mock/agencies.*`).

Ver [`CHANGELOG.md`](CHANGELOG.md) para el historial completo, incluyendo las versiones previas a este fork.

## Estado del proyecto y decisiones

El trabajo en curso, las tareas pendientes y las decisiones de diseño de este fork se documentan en [`STATUS.md`](STATUS.md).
