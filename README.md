# Correo Argentino for WooCommerce — Fork Julian Juan

> **Fork NO OFICIAL** del plugin [Correo Argentino for WooCommerce](https://www.correoargentino.com.ar/MiCorreo/public/mi-correo)
> (versión base **3.0.7**). Corrige varios bugs que rompían o degradaban el
> carrito/checkout y agrega mejoras de UX para el envío a sucursal.
>
> No está afiliado ni respaldado por Correo Argentino. Se publica bajo **GPLv2 o
> posterior**, la misma licencia del plugin original, conservando su autoría.

## ¿Por qué este fork?

El plugin oficial funciona, pero tiene varios problemas serios (fatales que
tumban el checkout, incompatibilidad con HPOS pese a declararla, un bug de zona
horaria que renueva el token en casi cada request, etc.). Este fork los corrige
de forma conservadora y documentada, pensado para poder **descartarse** el día que
salga una versión oficial que los resuelva.

## Requisitos

- WooCommerce con **checkout clásico** (shortcode `[woocommerce_checkout]`). El
  checkout por **bloques** (Gutenberg) no está soportado por el plugin base ni por
  este fork; las mejoras de UX se ignoran sin romper.
- PHP 7.4+ · WordPress 6.7+ · compatible con **HPOS**.
- Credenciales de la API "Mi Correo" (ver más abajo).

## Correcciones incluidas (3.0.7 → 3.0.7.2)

1. **Fatal 500 por token 401.** `try/catch` en el cálculo de tarifas: si la API
   falla, no ofrece tarifa en vez de tumbar carrito y checkout.
2. **Credenciales de API configurables** desde `wp-config.php` (antes eran
   `const` fijas imposibles de cambiar).
3. **"Enviar a otra dirección" ya no se fuerza en domicilio.** La activación deja
   de imponer `ship_to_destination='shipping'`; usa el default de WooCommerce.
   La sucursal sigue funcionando (el JS marca el check solo en ese caso).
4. **Fallback del destino de envío a facturación** (para que domicilio cotice sin
   "otra dirección"). Filtrable (ver abajo).
5. **Timeouts y guardas de null** en todas las llamadas a la API.
6. **`esc_attr()`/`esc_html()`** en el selector de sucursales (anti-XSS).
7. **Reintento de token ante 401** en `/rates`, `/shipping/import` y `/agencies`.
8. **HPOS:** arreglado el fatal al abrir el metabox del pedido (`WC_Order` vs
   `WP_Post`).
9. **HPOS/admin:** arreglado el fatal al crear un pedido manual (sesión nula).
10. **Renovación de token robusta** (lock + reintento) ante cambios rápidos de
    sucursal.
11. **Bug de zona horaria (causa raíz de #10):** la API devuelve `expire` en hora
    de Argentina; se usa el claim `exp` del **JWT** (UTC) para validar el token.

### Mejoras de UX (envío a sucursal)

- Autocompleta el **destinatario** (nombre, apellido, teléfono y **DNI/documento**)
  desde facturación.
- **Oculta** la sección "enviar a una dirección diferente" y el checkbox (la
  sucursal se elige con el selector de provincia/sucursal). Es solo visual: los
  valores se siguen enviando.
- **Spinner** "Cargando sucursales…" mientras se traen las sucursales.

## Instalación

1. Descargá/cloná este repositorio dentro de `wp-content/plugins/`.
2. Activá **Correo Argentino for WooCommerce (Fork Julian Juan)** en WordPress.
3. Configurá las credenciales (abajo) y, opcionalmente,
   `woocommerce_ship_to_destination = billing` (Ajustes → Envío) para la mejor UX
   de domicilio.

## Credenciales de la API (Mi Correo)

Las credenciales *partner* de fábrica del plugin oficial (`WOOCOMMERCE:...`)
devuelven **401**. Cargá las tuyas en `wp-config.php`, antes de
`/* That's all, stop editing! */`:

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

Ejemplos:

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

- Pensado para el **checkout clásico**. En el checkout por bloques, la UX de
  sucursal y el autocompletado se ignoran (no rompen).
- El autocompletado del destinatario usa los IDs `#billing_*`/`#shipping_*`. Si un
  tema/plugin renombra los campos, se ignora lo que no encuentre.
- La UX de ocultar/mostrar la sección de envío usa una clase con `display:none
  !important` para ganarle al JS del plugin base; si un tema tiene su propia
  gestión de esos campos, revisá que no haya conflicto.

## Licencia

GPLv2 o posterior. Ver [`LICENSE`](LICENSE). El código original es de Correo
Argentino; este fork mantiene la misma licencia y su atribución.

## Changelog

Ver [`CHANGELOG.md`](CHANGELOG.md).
