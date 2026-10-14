# Proyecto Espina: JobTracker Core

### Definición del Proyecto Espina (JobTracker Core)

API REST modular y ligera para la gestión del ciclo de vida de vacantes y postulaciones técnicas (estados de postulación, notas, seguimiento y métricas de conversión), diseñada con enfoque *contract-first* mediante especificación OpenAPI 3.1 y validada con pruebas de caja negra sobre HTTP para independizar la verificación de la implementación interna. Crece de forma incremental en cuatro fases: Fase 1 (Entorno Docker, OpenAPI y Healthcheck), Fase 2 (CRUD transaccional de vacantes e idempotencia), Fase 3 (Seguridad OWASP, hashing y tokens) y Fase 4 (Métricas de conversión y observabilidad).