# Correo Argentino for WooCommerce (Fork Julian Juan) — Decisiones de arquitectura (ADRs)

> Cada decisión de diseño vive acá y **necesita aprobación humana explícita** antes de que
> se pueda escribir el código que la implementa.

## Cómo funciona

1. Claude propone el ADR con `Estado: Propuesta` y 2-3 opciones con trade-offs.
2. El humano lo lee y decide. Si aprueba, el `Estado:` pasa a `Aprobada`. **Claude nunca lo
   marca por su cuenta**: la decisión es del humano y ponerlo solo produce una señal falsa.
3. Recién entonces se escribe el código que el ADR declara en `Cubre:`.
4. Si un ADR aprobado cambia de contenido después, hay que volver a mirarlo: la decisión que
   se aprobó ya no es la que está escrita.

**Los tres campos de cabecera son obligatorios.**

- `Estado:` — `Propuesta` · `Aprobada` · `Rechazada` · `Reemplazada-por-ADR-N`
- `Temas:` — uno o más del vocabulario cerrado, separados por coma:
  `db-schema` · `rest-api` · `hooks-publicos` · `opciones` · `capabilities` · `uninstall` ·
  `api-externa` · `dependencias` · `modulos` · `scaffold`
- `Cubre:` — globs de path relativos a la raíz del plugin, separados por coma

Los ADRs no se renumeran ni se borran: un ADR superado pasa a
`Estado: Reemplazada-por-ADR-N`, y así queda el registro de por qué se cambió de idea.

> Nota: este plugin es un fork de un plugin de terceros (no se creó con `/wp-create-plugin`),
> por eso no tiene un ADR-000 de scaffold — arranca directamente en ADR-001.

---

## ADR-001 — Dataset local de localidades/CP de Argentina para validar y seleccionar el código postal

Estado: Aprobada
Temas: api-externa, dependencias
Cubre: Mock/postal-codes.json, bin/generate-postal-codes.php, Lib/PostalCodes.php, Hooks/correoargentino-postcode-validation.php, Hooks/correoargentino-postal-codes-cron.php, Service/correoargentino-paq-ar-service.php, Service/correoargentino-mi-correo-service.php, js/postal-code-select.js

**Contexto:** un pedido fue rechazado por Correo Argentino al generar la etiqueta porque el
código postal cargado por el cliente en el checkout no correspondía a la provincia elegida.
Hoy `Hooks/correoargentino-postcode-validation.php` sólo valida que el CP tenga 4 dígitos; no
existe ningún cruce entre provincia, localidad y CP, ni en el checkout ni antes de registrar
el envío (`Service/correoargentino-paq-ar-service.php:293-296`,
`Service/correoargentino-mi-correo-service.php:299`), que arman el request a Correo Argentino
con `$shipping['state']` y `$shipping['postcode']` tal cual los tipeó el cliente.

Se confirmó que Correo Argentino expone (sin documentar, pero público) el endpoint que usa su
propio formulario de "Consulta de Código Postal":
`POST https://www.correoargentino.com.ar/sites/all/modules/custom/ca_forms/api/wsFacade.php`
con `action=localidades&localidad=none&calle=&altura=&provincia=<letra>`, usando las mismas
letras de provincia que ya tiene `Mock/provinces.json`. Devuelve localidad, partido y CP por
provincia (probado hoy contra San Luis: 1.233 localidades; total estimado ~23.000 en el país).

### Opción A — Dataset estático generado una vez desde el endpoint de Correo Argentino, con script versionado para regenerarlo (recomendada)

**Decisión:** se agrega `bin/generate-postal-codes.php`, un script que pega las 24
provincias contra el endpoint de Correo Argentino y produce el dataset (agrupado por letra
de provincia, con `localidad`, `partido`, `cp`), siguiendo el mismo esquema que
`Mock/provinces.json`. El plugin se distribuye con `Mock/postal-codes.json` como dataset
base (generado a mano antes de cada release, igual que hoy). Además, un evento de **WP-Cron
mensual** (`corrarfo_refresh_postal_codes`) corre la misma lógica en segundo plano en el
sitio del cliente y guarda el resultado en una `option` de WordPress
(`corrarfo_postal_codes_cache`, `autoload => no`) — nunca pisa el archivo del plugin. El
checkout y la validación previa a `registerOrder()` leen primero esa `option`; si está vacía
o el último refresh falló, caen al `Mock/postal-codes.json` bundleado. El checkout consume
el dataset vigente vía AJAX para los selects dependientes de Provincia → Localidad (el CP
queda determinado por la combinación localidad+partido, sin necesidad de un tercer select en
la mayoría de los casos).

**Por qué:** es la única fuente que es realmente de Correo Argentino (no un tercero), ya
usa el mismo esquema de letras de provincia que el plugin, y al generarse una sola vez evita
depender de un endpoint no documentado durante el checkout de un cliente real — si el
endpoint cambia o cae, el plugin sigue funcionando con el último dataset generado.

**Por qué no consultar el endpoint en vivo en cada checkout:** es un endpoint no
documentado y no soportado por Correo Argentino (mismo que usa su formulario web, no su API
de partner PAQ.AR/MiCorreo); meterlo en el camino crítico del checkout agrega latencia y un
punto de falla externo fuera de nuestro control, justo en el flujo que hoy queremos hacer
más confiable.

**Por qué no usar un dataset de terceros (ej. GeoNames):** GeoNames no tiene el campo
provincia para CABA y es una fuente ajena a Correo Argentino; el endpoint propio de Correo
Argentino da el mismo tipo de dato pero correcto y completo, sin ese hueco.

**Por qué el refresh mensual por `option` y no pisando el archivo del plugin:** muchos
hostings de WordPress sirven el directorio de plugins de sólo lectura o lo reponen en cada
deploy, así que escribir sobre `Mock/postal-codes.json` en producción puede no persistir o
desaparecer en la próxima actualización del plugin. Guardarlo en una `option` separada evita
depender de permisos de filesystem y no mezcla "dato bundleado versionado en git" con "dato
refrescado en el sitio del cliente".

**Por qué no cron de sistema operativo:** el plugin corre en sitios de terceros (WordPress.org)
donde no hay acceso al servidor; WP-Cron es lo único portable sin pedirle al hosting que
configure nada.

**Consecuencias:** WP-Cron es "pseudo-cron" — se dispara con una visita al sitio después de
cumplirse el intervalo, así que en sitios de muy bajo tráfico el refresh mensual puede
demorar algunos días extra en disparar (aceptable para un dato que cambia poco). Si el
endpoint no documentado de Correo Argentino cambia de forma o cae justo en ese intento, el
refresh falla silenciosamente esa vez y el plugin sigue usando el último dataset bueno (la
`option` si ya tenía uno, o el `Mock/postal-codes.json` bundleado si no) — hay que loguear el
fallo (vía los canales de log que ya usa el plugin) para poder notarlo. `bin/generate-postal-codes.php`
sigue existiendo además como script manual (PHP plano, sin `wp-load.php` ni WP-CLI) para
regenerar `Mock/postal-codes.json` antes de cada release; no dispara hooks de activación ni
requiere reinstalar el plugin.

### Opción B — Consultar el endpoint de Correo Argentino en vivo desde el checkout (con caché corta)

**Decisión:** en vez de un dataset estático, el checkout llama por AJAX al propio backend
del plugin, que a su vez consulta el endpoint de Correo Argentino en el momento (con
`transient` de WordPress como caché de, por ejemplo, 24hs por provincia).

**Por qué no:** agrega un punto de falla externo al checkout de clientes reales — si el
formulario de Correo Argentino está caído, lento, o cambia de forma sin aviso (es un
endpoint interno de su web, no una API soportada), el checkout de la tienda se ve afectado
por algo completamente fuera del control del plugin. El dato de localidades/CP cambia muy
poco en el tiempo como para justificar ese riesgo a cambio de "frescura".

### Opción C — Mantener el cruce sólo como validación de rangos (sin reemplazar el campo Ciudad por un select)

**Decisión:** no tocar el campo "Ciudad" del checkout; sólo agregar una validación de que
el CP tipeado cae dentro del rango de CPs conocidos de la provincia elegida (usando el mismo
dataset de la Opción A, pero sin el paso de selección de localidad).

**Por qué no:** no resuelve el pedido original — el usuario pidió explícitamente un select
de CP según localidad y provincia, no sólo una validación de rango. Además deja el error más
común sin corregir: el cliente puede cargar una localidad real pero mal tipeada o un CP
válido para la provincia pero de otra localidad, que es justamente lo que pasó en el caso
que originó esta tarea.

**Consecuencias de descartar esta opción:** se asume el costo de reemplazar el campo Ciudad
por un select — hay que revisar que ningún otro plugin/tema del sitio dependa de que ese
campo sea texto libre antes de hacerlo (ver `Cubre:` de un futuro ADR de implementación del
checkout si aparece ese conflicto).
