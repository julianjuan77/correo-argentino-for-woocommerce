# Correo Argentino for WooCommerce (Fork Julian Juan) — Estado del proyecto

> Plugin: `correo-argentino-for-woocommerce` 3.0.7.2 | WP 6.7+ | PHP 7.4+ | WooCommerce 8.9+
> Repo: [correo-argentino-for-woocommerce](https://github.com/julianjuan77/correo-argentino-for-woocommerce)
> Creado: 2026 (fork del oficial 3.0.7) · Última sesión: 2026-10-20 — Primera sesión de trabajo sobre el fork: arranca el endurecimiento de seguridad y estabilidad.

Fork **no oficial** del plugin oficial "Correo Argentino for WooCommerce" v3.0.7 (autor `gdicorreo`),
mantenido por julianjuan77. Integra WooCommerce con el servicio de envíos de Correo Argentino
(MiCorreo y API PAQ.AR 2.0): cotización, generación de etiquetas, seguimiento y gestión de envíos.

El fork ya trae correcciones sobre el oficial: evita el fatal 500 por token 401, credenciales
configurables por `wp-config.php`, comportamiento correcto de "otra dirección" por método,
fallback de destino a facturación, timeouts y guardas de null, compatibilidad HPOS, reintento de
token ante 401 (con fix de zona horaria) y mejoras de UX en envío a sucursal.

| | |
|---|---|
| **Origen** | Fork de WordPress.org, `Update URI: false` para que WP.org no lo pise |
| **Tests** | No tiene suite propia todavía |
| **Lint** | No corrido todavía en este entorno — pendiente `make lint-plugin` |

---

## 🔄 En progreso

**Objetivo de esta etapa:** llevar el fork a una versión segura y estable aplicando las buenas
prácticas básicas de WordPress (nonces, capability checks, sanitización/escape, prepared
statements, manejo de credenciales, llamadas HTTP). **Sin sobreingeniería**: se ataca lo concreto
y de impacto real, no una reescritura ni arquitectura nueva.

**2026-10-20:** primera sesión. No existía `STATUS.md`. Se relevó el repo: 2 commits (`d7e194d`
fork inicial, `7ec146c` fix de Update URI), ~10.100 líneas de PHP en `Classes/`, `Helper/`,
`Hooks/`, `Orders/`, `Service/`, `Settings/`, `Tables/` + archivo principal y `uninstall.php`.

**Auditoría de seguridad/estabilidad (sólo lectura) — resultado:** el fork está en buen estado
general. Todos los handlers reales de escritura (admin-post/admin_init/AJAX) verifican nonce y
`current_user_can('manage_woocommerce')`; el SQL directo usa `$wpdb->prepare()`; las llamadas
`wp_remote_*` tienen timeout y chequeo de errores; no hay `sslverify => false` ni logging de
tokens/API keys; prefijo `Corrarfo_`/`CORRARFO_` consistente, sin colisión de nombres. 6 hallazgos
puntuales, ninguno crítico, convertidos en la tabla de tareas de abajo.

---

## Plan

Leyenda: ✅ hecho · 🔄 en curso · ⬜ pendiente · 🔒 bloqueado

### 1. Diagnóstico

| | Tarea | Estado |
|---|---|---|
| 1.1 | Auditoría de seguridad/estabilidad (nonces, capability checks, sanitización/escape, SQL, credenciales, llamadas HTTP, `uninstall.php`) | 🔄 |
| 1.2 | Priorizar hallazgos en tareas puntuales (sin refactor de arquitectura) | ⬜ |

### 2. Hardening

| | Tarea | Severidad | Estado |
|---|---|---|---|
| 2.1 | Eliminar el endpoint REST público `/labels` (código muerto) — `Orders/correoargentino-label.php` | Alto | ✅ |
| 2.2 | Agregar `_wpnonce` a los links "Ver Rótulo"/"Cancel" del metabox — `Orders/correoargentino-metabox.php` | Medio | ✅ |
| 2.3 | Extender `uninstall.php` para borrar también postmeta de HPOS (`wc_orders_meta`), reusando la detección ya existente en `Helper/correoargentino-order-actions-handler.php` | Medio | ✅ |
| 2.4 | Documentar las credenciales default del partner en `Lib/Constants.php` (son del partner, no de una tienda; se mantienen porque hacen falta out-of-the-box) | Medio | ✅ |
| 2.5 | Borrar clase muerta `Service`/`Corrarfo_Service` y sus fixtures (`Mock/agencies.*`); se mantiene `Mock/provinces.json` porque sí se usa en producción | Bajo | ✅ |
| 2.6 | Agregar el `is_wp_error()` faltante en `Service::login()` — `Service/correoargentino-service.php:72-73` | Bajo | ✅ (resuelto por 2.5: era la clase muerta borrada; `MiCorreoService`/`PaqArService` ya son WP_Error-safe) |

No se encontraron: falta de nonce/capability en acciones reales de escritura, SQL sin prepare,
XSS explotable, `sslverify` deshabilitado, ni logging de tokens/API keys.

### 3. Lint

El plugin no tenía `composer.json`/`phpcs.xml` (fork de terceros, nunca se creó con
`/wp-create-plugin`). Se armó el andamiaje y se corrió `make lint-plugin SITE=sandbox
PLUGIN=correo-argentino-for-woocommerce`.

| | Tarea | Estado |
|---|---|---|
| 3.1 | `composer.json` + `phpcs.xml` + `phpstan.neon.dist` | ✅ |
| 3.2 | PHPCS con el ruleset `WordPress` completo: ~15.455 errores, casi todos de estilo (indentación con espacios en vez de tabs en todo el plugin). Se descartó corregirlo: diff enorme por una cuestión de formato, no de seguridad | ✅ (descartado a propósito) |
| 3.3 | `phpcs.xml` acotado a sniffs de seguridad/corrección (`WordPress.Security.*`, `WordPress.DB.*`, `WordPress.PHP.*` peligrosas, `WordPress.WP.Alternative/Deprecated/DiscouragedFunctions`, naming, i18n, `PHPCompatibilityWP`) | ✅ |
| 3.4 | Resultado del ruleset acotado: 2 errores (clases sin prefijo: `CorreoArgentinoAdminForm`, `CorreoArgentino_List_Table` — cosmético, bajo riesgo real de colisión) + 17 warnings, todos falsos positivos de contexto (`base64_*` para Basic Auth/PDF, `serialize()` sobre datos propios del servidor sin riesgo de Object Injection, `file_get_contents()` sobre JSON local del propio plugin, `json_encode()` en vez de `wp_json_encode()`) | ✅ (sin cambios de código necesarios) |
| 3.5 | PHPStan: 141 errores, en su enorme mayoría ruido de que el plugin usa `class_alias()` en runtime para compatibilidad hacia atrás (PHPStan no puede resolver clases creadas dinámicamente) y de los stubs de WC siendo más estrictos que la realidad | ✅ (investigado) |
| 3.6 | Sospecha puntual investigada: `$order->get_items()` sin argumento + llamadas a métodos de producto (`get_product()`, `get_total()`) — **no es un bug**: `get_items()` ya tiene default `'line_item'` en WooCommerce, así que nunca trae ítems de envío/fee/cupón. Falso positivo del stub (retorno genérico `WC_Order_Item[]`) | ✅ (descartado, confirmado contra el stub oficial) |

**Para lint de código nuevo:** `make lint-plugin SITE=sandbox PLUGIN=correo-argentino-for-woocommerce`.
Las ~15.000 violaciones de estilo del código heredado quedan fuera del ruleset a propósito; si en
algún momento se quiere normalizar el formato completo, es tarea aparte (correr `phpcbf` con el
ruleset `WordPress` completo y revisar el diff).

### 4. Documentación

| | Tarea | Estado |
|---|---|---|
| 4.1 | `README.md` reescrito: sección para usuarios de tienda primero (qué trae, requisitos, instalación, troubleshooting de login, FAQ), sección técnica al final (arquitectura, credenciales, hooks, lint, changelog detallado) | ✅ |

Sitios de prueba usados en esta sesión (`sandbox`, `octubre-minorista`) dados de baja al cierre.

---

## Decisiones

_Sin ADRs todavía. Se documentan acá a medida que se tomen decisiones de diseño (p. ej. dónde
guardar credenciales, cómo versionar los fixes respecto del oficial)._

---

## Deuda y cabos sueltos conocidos

| Qué | Dónde |
|---|---|
| Sin suite de tests propia | — |
| Sin lint corrido en este entorno todavía | — |
