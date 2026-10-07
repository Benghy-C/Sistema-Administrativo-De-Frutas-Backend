# Relaciones de negocio

Se añaden cinco claves foráneas y ocho índices. No se actualizan registros, funciones, tipos de columna, roles ni permisos. Los campos opcionales siguen admitiendo NULL. No se añaden borrados en cascada.

## Aplicación con DBeaver

1. Guarda un respaldo actualizado de la base que vas a modificar.
2. Abre un editor SQL asociado a la base correcta. Para local, selecciona `sistema_florencia`. Para Supabase, selecciona `postgres` dentro de la conexión remota.
3. Ejecuta `SELECT current_database(), current_schema(), inet_server_addr();` y confirma el destino.
4. Abre `aplicar-relaciones.sql` y ejecuta el script completo, desde BEGIN hasta la consulta de verificación. No ejecutes solo la sentencia donde está el cursor.
5. La consulta final debe mostrar cinco relaciones con `validada = true`. Si hubo un error, ejecuta ROLLBACK en el mismo editor y no continúes: ninguna de las modificaciones de la transacción debe quedar aplicada. El script no elimina registros huérfanos; detiene la operación para que se revisen.
6. Renueva el esquema con F5, cierra la pestaña anterior del diagrama y vuelve a abrirla para exportarlo.

Repite estos pasos por separado en Supabase. La prueba realizada corresponde a la base local, no a los datos actuales de Supabase. Los límites de bloqueo y ejecución hacen que el script falle en lugar de esperar indefinidamente; si ocurre un timeout, no aumentes esos límites sin revisar la carga del servidor.

## Migración de Laravel

La migración `2026_10_06_200000_add_business_foreign_keys.php` aplica las mismas relaciones. Como esta base fue restaurada, no ejecutes todas las migraciones pendientes. Si prefieres aplicar esta mediante Laravel, ejecuta únicamente:

```powershell
php artisan migrate --path=database/migrations/2026_10_06_200000_add_business_foreign_keys.php
```

En producción Laravel requiere `--force`. Elige el script SQL o la migración como método inicial. Si la migración se ejecuta después del SQL, reconoce las cinco restricciones con sus mismos nombres y definición sin duplicarlas.

## Efecto sobre el sistema

Las referencias válidas se aceptan. Se rechazan IDs de compra, fruta o usuario inexistentes. Se bloquea el borrado físico de una compra, fruta o usuario que tenga registros dependientes; los cambios de estado utilizados actualmente no se convierten en borrados. El script no sincroniza las cantidades de cámara cuando se edita un pedido: ese comportamiento requiere una corrección independiente.

Las tablas de roles y tokens usan relaciones polimórficas. No se añade una clave foránea desde sus IDs de modelo a users. Tampoco se inventan relaciones entre clientes, trabajadores y otras tablas.

## Validación realizada

23 comprobaciones sobre una copia transaccional de siete tablas locales: cinco restricciones validadas, repetición sin duplicados, datos intactos, rechazo de cinco referencias inválidas, bloqueo de tres borrados de padres, aceptación de NULL y referencias válidas, y eliminación de la copia mediante rollback. No se modificó la base original. Estas pruebas validan la integridad referencial; no sustituyen una prueba completa de los flujos de la aplicación en producción.
