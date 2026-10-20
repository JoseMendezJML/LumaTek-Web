# Reglas de desarrollo de LumaTek-Web

## Contexto del proyecto

- Proyecto: LumaTek-Web.
- Tecnologías: Laravel 13, PHP 8.3+, MySQL, JavaScript y Vite.
- Arquitectura multiempresa.
- Autenticación JWT.
- Desarrollo colaborativo con GitHub y seguimiento de tareas en Jira.

## Reglas obligatorias

1. Antes de modificar código, analizar los archivos relacionados y el estado de Git.
2. Trabajar siempre en ramas `feature/` creadas a partir de `develop`, salvo indicación explícita.
3. No modificar directamente `main` ni `develop`.
4. No ejecutar `git push`, `merge`, `reset --hard` ni eliminar ramas sin autorización.
5. No ejecutar `migrate:fresh`, `db:wipe` ni operaciones destructivas sobre la base de datos.
6. No modificar archivos `.env`, credenciales, tokens ni secretos sin autorización.
7. Mantener el aislamiento de información entre empresas.
8. Respetar los roles, permisos y mecanismos de autenticación existentes.
9. Escribir o actualizar pruebas automatizadas cuando se implementen funcionalidades.
10. Priorizar soluciones compatibles con la arquitectura actual.
11. No modificar código de otras funcionalidades fuera del alcance de la tarea.
12. Antes de finalizar, ejecutar las verificaciones seguras disponibles y comunicar qué pruebas no pudieron realizarse.
13. Informar los archivos modificados, las decisiones técnicas, los resultados de las pruebas y los posibles riesgos.
14. Utilizar mensajes de commit convencionales como `feat:`, `fix:`, `test:`, `docs:` y `refactor:`.
15. No crear commits ni pull requests sin autorización expresa.

## Regla de seguridad

Cualquier operación sobre producción, hosting, base de datos real o sistemas externos requiere aprobación explícita.
