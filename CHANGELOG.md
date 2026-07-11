# Registro de cambios

## Fork Julian Juan

### 3.0.7.2 (fork, no oficial) — 2026-07
Basado en la versión oficial 3.0.7. Correcciones y mejoras del fork:
- [Fix] `try/catch` en el cálculo de tarifas: un token 401 ya no tira un fatal 500
  que rompía carrito y checkout.
- [Fix] Credenciales de la API configurables desde `wp-config.php`
  (`CORRARFO_USERNAME/PASSWORD_MI_CORREO_PRODUCTION`).
- [Fix] La activación deja de forzar `ship_to_destination='shipping'` y
  `debug_mode='yes'`; se respeta el default de WooCommerce (domicilio no obliga
  "otra dirección"; la sucursal sigue funcionando).
- [Fix] Fallback del destino de envío a la dirección de facturación cuando el
  paquete no trae CP (filtrable con `corrarfo_fill_destination_from_billing`).
- [Fix] `timeout` y guardas de null en todas las llamadas a la API.
- [Seguridad] `esc_attr()`/`esc_html()` en el selector de sucursales.
- [Fix] Reintento con renovación de token ante 401 en `/rates`, `/shipping/import`
  y `/agencies`.
- [Fix][HPOS] Metabox del pedido: acepta `WC_Order` (antes fatal por `$post->ID`).
- [Fix][HPOS] Creación manual de pedidos en el admin: guarda de sesión nula.
- [Fix] Renovación de token robusta (lock + reintento) ante cambios rápidos de
  sucursal.
- [Fix] Validez del token por el claim `exp` del JWT (UTC): corrige el bug de zona
  horaria que lo marcaba vencido apenas emitido y renovaba en casi cada request.
- [UX] Envío a sucursal: autocompleta el destinatario (nombre, apellido, teléfono
  y DNI/documento; filtrable con `corrarfo_recipient_document_keys`), oculta la
  sección "otra dirección" + checkbox, y muestra un spinner al cargar sucursales.

---

## Versión oficial (upstream)

### v3.0.6.rc-20250801
- [Mejora UX] Deshabilitación de campos de login y link de "Crear nueva cuenta" en formularios de Paq.Ar y MiCorreo cuando el usuario ya está conectado.
- [Mejora UX] Ocultado el botón de "Guardar cambios" mediante CSS en formularios de login.
- [Mejora UX] Mejor experiencia de usuario evitando interacciones innecesarias en formularios bloqueados.
### v3.0.5.rc-20250730
- [Fix] Se corrigió la configuración de permisos para que el rol Gestor de la tienda (shop_manager) pueda gestionar el plugin.
- [Fix] Se eliminaron validaciones restrictivas en varias funciones (current_user_can, manage_options).
- [Update] Los menús del plugin ahora utilizan la capability read para mayor compatibilidad con otros roles.
- [Update] Se agregó el soporte de capability manage_correoargentino a los roles administrator, shop_manager y customer en la activación.
### v3.0.5.rc-20250729
- [Mejora UX] se muestra el mensaje "Envío Gratis Correo Argentino" en el carrito y checkout.
- [Mejora UX] se guarda el estado histórico de envío gratis en el meta _ca_free_shipping de cada pedido.
- [Mejora UX] se agrega automáticamente una nota al pedido indicando que tuvo envío gratis.
- [Mejora UX] se muestra el aviso de envío gratis en el metabox de Correo Argentino en el admin.
- [Mejora UX] se muestra el aviso de envío gratis en la sección de métodos de envío en el detalle del pedido.
- Traducciones: todos los textos nuevos son traducibles desde el text domain correoargentino.
### v3.0.5.rc-20250724-1
-  Se implementó validación estricta para el campo de Código Postal.
  - Solo se permiten 4 dígitos numéricos.
  - Se bloquea entrada alfabética o formatos extendidos tipo CPA.
  - Se agregó validación visual en tiempo real (JS) en checkout, carrito y Mi Cuenta.
  - Se limitó el input en HTML con 'maxlength', 'pattern' e 'inputmode'.
  - Se normaliza automáticamente el código postal antes de enviarlo a la API.
  - Evita fallos de cotización por CP inválido (ej. '7000FSZ', '700098').
### v3.0.5.rc-20250724
- Se agregó nueva sección de Historial de Pedidos Enviados a la API.
- Nueva grilla con columnas: ID Pedido, Tipo de Envío, Estado, Dimensiones del Paquete, Peso, Sucursal destino, Fecha.
- Se implementó ordenamiento en columnas: ID Pedido, Fecha, Estado, Tipo de Envío.
- Se incorporaron columnas específicas para peso y dimensiones del paquete.
- Si hay más de un producto en el pedido, se calcula automáticamente el tamaño total del paquete.
- Se agregó botón Exportar a CSV, exporta todos los datos visibles de la grilla.
- Compatibilidad garantizada para MiCorreo y Paq.ar.
### v3.0.5.rc-20250717
- [Mejora UX] acciones directas sobre pedidos en el listado
- [Nuevo] ocultamiento individual de pedidos (icono de tacho)
- [Nuevo] visualización de pedidos ocultos mediante checkbox
- [Nuevo] restauración masiva de pedidos ocultos (botón con alerta)
- [Fix] eliminación del límite de 20 pedidos en la tabla de Correo Argentino
### v3.0.5.rc-20250716
- Se incorporó opción en la configuración para eliminar todas las opciones del plugin al desinstalar.
- Se creó el archivo uninstall.php para ejecutar la limpieza condicional de datos según la opción configurada.
- Se eliminaron entradas en wp_options, postmeta, configuraciones de métodos de envío de WooCommerce y transients relacionados.
- Se añadió advertencia visual al tildar la opción de eliminación, informando que el borrado es irreversible.
### v3.0.5.rc-202507-14-2
- Nueva opción para configurar un prefijo en el número de pedido enviado a MiCorreo.
- Visualización del ID con prefijo en el detalle del pedido (admin) y como nota interna.
- Actualización en el listado de órdenes del plugin: se muestra el ID original junto al ID enviado.
- Validación de formato del prefijo y conversión automática a mayúsculas.
- Compatibilidad exclusiva con MiCorreo. Deshabilitado en PAQ.AR para evitar errores en el payload.
### v3.0.5.rc-20250714
- Se modifica el resumen de pedido para incluir el nombre del método de envío en la línea de "Envío".
- Se crea archivo correoargentino-order-summary-hooks.php para centralizar hooks relacionados al resumen de pedido.
- Se mejora la presentación estética del método de envío en frontend.
### v3.0.4.rc-20250711
- Nueva funcionalidad: envío automático de pedidos a la API de MiCorreo al alcanzar un estado configurable por el usuario.
- Nueva sección de configuración en el panel de administración:
  - Habilita/deshabilita el envío automático.
  - Permite seleccionar el estado del pedido que dispara el envío.
- Registro automático en la orden sobre el resultado del intento de envío (éxito o error).
- Sistema reutilizable de desactivación de campos dependientes por checkbox, mediante JavaScript.
- Comportamiento condicionado
   - La funcionalidad solo está disponible si el servicio activo es **MiCorreo**.
  - Si el servicio activo es **PAQ.AR**:
   - La opción de envío automático no se muestra.
   - No se ejecuta el envío automático.
   - Se registra un mensaje en 'error_log'.
   - Se agrega una nota en la orden en caso de que se intente ejecutar.
### v3.0.4.rc-20250710
- Agregado: Nueva opción de configuración en el Panel MiCorreo para habilitar el cambio automático de estado luego de enviar un pedido a la API.
- Mejora: Se valida que el estado seleccionado sea válido antes de aplicarse.
- Mejora: Si ocurre un error al intentar aplicar el cambio de estado (por ejemplo, estado inexistente), se registra en las notas del pedido.
- Técnico: Se agregó el archivo Settings/correoargentino-autostatus-settings.php para mantener la lógica modular y separada.
- Técnico: La lógica se integra al flujo del método registerOrder de MiCorreo, luego del envío exitoso a la API.
### v3.0.3.rc-20250707
- Validación de dimensiones y peso máximos en 'calculate_shipping()'.
- Mensaje informativo cuando un paquete excede los límites establecidos por Correo Argentino.
- Nuevo método 'isValidDimensions()' para encapsular la lógica de validación.
- [Fix]Validación de dirección incompleta (reordenado para lógica más clara).
- [Fix]Límite de ítems en listado de órdenes extendido ('$per_page = 99999').
- [Mejora] Reorganización del flujo de validaciones en el método de envío para evitar conflictos entre validaciones de dirección y de peso.
### v3.0.3.rc-20250703
- Eliminada validación de peso al agregar al carrito.
- Validación y uso de valores por defecto al cotizar, según configuración.
- Compatibilidad plena con productos variables.
- Se ajustaron los controles de dimensiones y peso para cumplir con los requerimientos de Correo Argentino.
### v3.0.3.rc-20250702
- Nueva funcionalidad: envío gratuito configurable por monto mínimo (solo para MiCorreo).
- Se agregó un nuevo bloque de ajustes en el panel de tarifas de envío.
- Se aplica tarifa 0 automáticamente si el carrito supera el monto establecido.
- Ingreso flexible con punto o coma como separador decimal.
- No disponible si el servicio activo es PAQ.AR.
### v3.0.3.rc-20250613
- [Fix] Persistencia de 'customerId' (MiCorreo) en credenciales del plugin luego del login.
- [Fix] Soporte para fallback de 'customer_id' desde credenciales al importar pedidos o cotizar.
### v3.0.3.rc-20250626
- FIX: Corrección en la lógica de renovación del token de autenticación MiCorreo.
- Se aseguraron los valores necesarios ('auth_hash') antes de ejecutar 'renewToken()'.
- Se evita error de cabecera vacía al expirar el token de acceso.
### v3.0.3.rc-20250613
- Nuevo: Visualización del método de envío en el panel de administración de pedidos de WooCommerce.
- Agregado hook para mostrar el/los método(s) de envío en el detalle del pedido del admin.
- Implementado en el archivo Hooks/correoargentino-admin-order-shipping-method.php.
- Corrección estructural:Se modificó la carga de loader.php, que ahora se incluye directamente en correoargentino-shipping.php (fuera del register_activation_hook). Esto asegura que todos los hooks y funcionalidades se carguen correctamente en cada request y no sólo en la activación del plugin.
### v3.0.3.rc-20250612-2
- [Mejora] Se agregó lógica de validación visual de dirección en el checkout para evitar intentos de cotización incompleta.
- [Revisión] Durante el control del ticket “Bucle para Envíos a Domicilio en el Checkout”, se detectó la falta de un mensaje claro ante dirección incompleta. Se incorporó un método de envío informativo con mensaje al usuario.
- [UX] Se evita el loop de recálculo si se intenta cotizar con datos de dirección incompletos.
### v3.0.3.rc-20250612-1
- Se agrega funcionalidad de ajuste porcentual sobre el costo de envío.
- Permite aplicar un recargo o descuento configurado desde el admin.
- Incluye opciones: habilitar ajuste, tipo (recargo/descuento), porcentaje y monto mínimo.
- Sanitiza valores numéricos con coma o punto como separador decimal.
- Se garantiza formato decimal consistente con 2 dígitos.
- Nota: dependiendo del navegador, los campos pueden mostrarse con coma o punto, y con uno o dos decimales, pero esto no afecta el procesamiento interno.
### v3.0.3.rc-20250612
- Refactor completo del menú de administración del plugin para mejorar la organización y experiencia del usuario.
- Separación de submenús: "Conexión API", "Ajustes de Envío" y "Datos Comerciales".
- Los slugs de menú ahora son seguros y redirigen correctamente mediante funciones internas del plugin.
- Se modulariza la lógica de ajustes dentro de 'correoargentino-settings-page-wrapper.php'.
- Cada sección del panel de ajustes se renderiza por bloques individuales, facilitando la extensión del panel sin duplicación de código.
- Se corrige el uso de funciones de sanitización ('esc_html' en lugar de 'esc_html__') para textos dinámicos.
- Limpieza de sintaxis en 'add_menu_page' (coma sobrante eliminada).
### v3.0.3.rc-20250605
- Se agregó una nueva sección en el menú de administración llamada Configuración [MiCorreo/PAQ.AR], con título dinámico según el tipo de servicio conectado.
- El nuevo ítem se integra dentro del menú principal del plugin (Correo Argentino).
- Se preparó una nueva función ca_behavior_settings_page() para centralizar futuras configuraciones generales del plugin.
- El contenido de la página también refleja el tipo de conexión activa (o se muestra genérico si no hay conexión).
- Se garantiza compatibilidad con futuras extensiones sin alterar el esquema actual de ajustes de envío.
### v3.0.3.rc-20250604
- Fix: prevenir errores en activación por uso prematuro de constantes no definidas.
- Se reorganiza el 'register_activation_hook' y se asegura la carga de 'loader.php' antes del uso de constantes.
- Se carga 'load_plugin_textdomain()' desde el inicio del plugin para habilitar traducciones incluso antes de establecer conexión.
- Se integra la lógica de override de páginas ('cart', 'checkout') en el proceso de activación.
### v3.0.3.rc-20250602
- Se agregó el submenú "Ajustes de Envío" al menú de administración del plugin Correo Argentino.
### v3.0.3.rc-20250528-2
- Se refina la lógica de validación de dimensiones antes de cotizar envíos, incluyendo altura máxima individual y suma volumétrica.
- Se agregan constantes para límites volumétricos y de altura aplicada por servicio (PAQ.AR y MiCorreo).
- Se mejora la estabilidad del plugin evitando errores silenciosos al consultar tarifas con datos inválidos.
- Se corrige un error que enviaba datos malformados a la API al construir las dimensiones de productos.
### v3.0.3.rc-20250528-1
- Se elimina el comportamiento condicional del selector de uso de cotizador para el servicio 'miCorreo', manteniéndolo siempre activo.
- Simplificación del código JavaScript en 'service-selector.js' para evitar que el usuario tenga que configurar manualmente la opción de uso de tarifas.
### v3.0.3.rc-20250528
- Se agregó un script en el checkout para destildar automáticamente el checkbox de "¿Enviar a otra dirección?" cuando se selecciona un método de envío a domicilio de Correo Argentino (clásico o expreso).
### v3.0.3.rc-250526
- Se renombra el archivo 'correoargentino-hooks.php' a 'correoargentino-order-hooks.php'.
- Se actualiza la referencia correspondiente en el archivo 'load.php'.
### v3.0.3.rc-240331-1
- Se corrigen los texto dependiendo del tipo de servicio reflejado en la orden
- Se corrigen la visibilidad de acciones dependiendo el tipo de servicio reflejado en la orden
### v3.0.3.rc-240331
- Se corrige un error en el evento de orden completada.
### v3.0.3.rc-240327-1
- Bypass on-complete hook
### v3.0.3.rc-240327
- Agregar validacion al completar orden.
- Mostrar acciones en ordenes completadas.
### v3.0.3.rc-240326
- Agregar shortcodes de WC por defecto.
### v3.0.3.rc-240320
- Se corrige problema con los metaboxes.
- Se corrige error en la importacion.
### v3.0.3.rc-240319
- Se agrega soporte a las consultas de la DB para versiones de WC
### v3.0.3.rc-240318
- Se genera version 240318 con mergeo de commit de version 27
### v3.0.3.rc-240315
- Se genera version sin cotizador para deploy a prod, con fecha 2024-03-15
### v3.0.3.rc-25
- Ticket 1032, no se puede editar las ordenes con sucursales.
### v3.0.3.rc-24
- Ajustes en tabla elegir address + telefono no obligatorio
### v3.0.3.rc-23
- Tabla de elegir address diferente no habilitada cuando es sucursal
### v3.0.3.rc-22
- Cambio de extOrderId a solamente el orderId (Se quita el time())
### v3.0.3.rc-21
- Corregir mensaje al ingresar contraseña incorrecta en MiCorreo login.
- Agregar validaciones y mensajes al crear cuenta con CUIT.
- Inhabilitar temporalmente Correo Argentino Hoy - Domicilio.
### v3.0.3.rc-20
- Prevenir que se cliquee el botón de aplicar multiples veces.
### v3.0.3.rc-19
- Se corrige un error que permitía enviar órdenes duplicadas.
### v3.0.3.rc-18
- Se oculta el checkbox que permite seleccionar e importar una orden, para evitar ordenes duplicadas.
### v3.0.3.rc-17
- Se corrige error que ocultaba los botones en metabox.
- Se establece que el producto a enviar es el que posee la cotización más alta.
### v3.0.3.rc-16
- Se corrigen los enlaces en las referencias.
- Se agrega validación y mensaje de dimensiones al cotizar.
### v3.0.3.rc-15
- Se corrige un error en el cálculo de la tarifa al importar relacionado con el peso.
- Se garantiza que se envía el producto con mayor costo.
### v3.0.3.rc-14
- Agregado de orderNumber en servicio import MiCorreo
### v3.0.3.rc-13
- Cambios de roland
### v3.0.3.rc-12
- Se corrige un problema con el peso que no era enviado en la unidad de medida correcta (gramos)
### v3.0.3.rc-11
- Se traducen mensajes de validación en contraseña.
- Se valida longitud requerida en contraseña.
### v3.0.3.rc-10
- Se corrige error al seleccionar método de envío por sucursal.
- Se agrega mensaje de validación en formulario de login.
### v3.0.3.rc-09
- Se corrigen errores en la dirección de envío.
- Se remueve servicio mockeado.
### v3.0.3.rc-08
- Se corrige problema con direcciones al usar el navegador para volver atrás.
### v3.0.0.rc-07
- Se corrigen problemas en las direcciones.
- Se corrigen defecto al registrar una orden.
### v3.0.0.rc-06
- Se corrigen varios errores en la cotización.
- Se corrigen errores en las direcciones.
- Se filtra la table de órdenes por el tipo de API activa.
- Se corrigen mensajes y acciones en las órdenes.
- Se muestran direcciones más detalladas.
### v3.0.0.rc-05
- Se actualiza la dirección de envío bajo demanda.
- Se fuerza a completar los datos del comercio y se muestra un mensaje mientras no estén completados.
- Se corrigen la selección de las órdenes mediantes checkboxes. 
- Se corrigen el mensaje del campo email.
### v3.0.3.rc-04
- Se cambian los textos de MiCorreo y Paq.AR por los correctos.
- Se agrega un mensaje de validación faltante.
- Se limpia el formulario de forma correcta.
### v3.0.3.rc-03
- Corrigen errores en los formularios de configuración.
- Se mejora el selector de servicios
- Se elimina un select vacío
- Se corrigen mensajes no traducidos
- Mejoras generales
### v3.0.3.rc-02
- Se fuerza limpiar caché en los métodos de envío. 
- Se limita el número de registros a 10 durante una operacion masiva.
### v3.0.3
- Se envia el dato peso en gramos.
- Se agregan acciones masivas para importar.
- Se reactivan las validaciones del form de configuracion.
### v3.0.2
- Se agrega la opción de cotización contra MiCorreo.
- Se filtra el método de envío desde la página de checkout.
- Se mejora el cuadro info de sucursal.
- Se mejora el selector de servicio.
### v3.0.1
- Se agrega la opción de cotización.
- Se agrega un rates setting.
### v3.0.0
- Se corrigen cadenas de texto.
- Se agrega una confirmacion al cancelar una orden.
- Se ajustan los nombres de los servicios.
### v2.0.0
- Se agrega el soporte de integracion con ambas plataformas Paq.ar y MiCorreo
- Se setea a entorno productivo

### v2.0.0.rc-03
- Se corrige un error de estilos en el select de provincias y sucursales.
- Se corrige un error que permitía seleccionar operar con otro servicio una vez instalado el plugin.
- Se agrega un mensaje de guía en el registro como consumidor final.
- Se corrige un error que denegaba el listado de sucursales en modo incógnito. 
### v2.0.0.rc-02

- Se muestran mensajes al importar un orden a MiCorreo, y se agrega un asterisco a cada campo requerido.

### v2.0.0.rc-01

- Se integra el servicio de MiCorreo y se adecuan los servicios para que sean compatibles entre si.

### v1.0.0.rc-12

- Se cambia la URL de la API por una con SSL válido.

### v1.0.0.rc-11

- Agregan validaciones en el campo 'Ciudad' del formulario de configuración y se coloca como obligatorio.
- Se agrega un contador de caracteres en el campo 'Observación'.

### v1.0.0.rc-10

- Se aumenta la version.

### v1.0.0.rc-09

- Se corrige la longitud del campo 'Observacion' al validar.

### v1.0.0.rc-08

- Se cambia a 150 caracteres la longitud del campo 'Observación'.

### v1.0.0.rc-07

- Se agrega un mensaje de error cuando falla la operación de pre-imposición al momento de actualizar el estado de una orden.
- Se agrega muestran los botones de imprimir rótulo y cancelar cuando la orden está en el estado 'Completado' .

### v1.0.0.rc-06

- Se establece la longitud del campo 'calle' a 10 caracteres.

### v1.0.0.rc-05

- Corregir mostrar acciones solo cuando estén disponibles en detalle del pedido.
- Agregar validación campo calle, corregir validación de email.

### v1.0.0.rc-04

- Se agregan nuevas validaciones máscaras en inputs.
- Se valida que no se genere una orden sin seleccionar sucursal de destino cuando es el caso.
- Se ocultan acciones condicionalmente por estado.

### v1.0.0.rc-03

- Se remueve la ordenación de la lista de órdenes.
- Se agregan opciones al menu Correo Argentino.
- Se muestran los errores de la API cuando la conexión es fallida.
- Se restringe el acceso a la opción Datos Comerciales si no se ha completado la conexión a la API.

### v1.0.0.rc

- Se agrega el archivo de registros de cambios.
- Se adecuan el endpoint de sucursales.
- Se adecua al nuevo método de autenticación con API Key.
- Se agregan traducciones (trabajo en progreso).
- Se corrigen errores al crear órdenes sin sucursales de destino.
- Se agrega Select2 al listado de provincias.
- Se agregan validaciones en el formulario de configuración del plugin.
