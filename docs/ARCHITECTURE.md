# Correo Argentino for WooCommerce (Fork Julian Juan) — Arquitectura técnica

> Última actualización: 2026-11-02
> Documentación para desarrolladores: cómo funciona, por qué se diseñó así, cómo extenderlo.

Este plugin es un fork de un plugin de terceros (no se creó con `/wp-create-plugin`), así
que no tiene el contrato completo documentado desde el origen. Este archivo arranca
**incompleto a propósito** y crece sección por sección a medida que se toman decisiones de
arquitectura nuevas sobre el código heredado — no se completan las secciones 1-5 con el
estado actual del fork retroactivamente, para no fabricar documentación que nadie validó.

Las decisiones y su justificación viven en `DECISIONS.md`. Acá se documenta el **resultado**
de esas decisiones, con referencia al ADR que las respalda.

---

## 6. Decisiones

Ver `DECISIONS.md`. Índice de las aprobadas:

| ADR | Título | Temas |
|---|---|---|
| ADR-001 | Dataset local de localidades/CP de Argentina para validar y seleccionar el código postal | api-externa, dependencias |
