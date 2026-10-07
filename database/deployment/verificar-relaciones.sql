SELECT c.conrelid::regclass AS tabla, c.conname AS relacion,
       pg_get_constraintdef(c.oid) AS definicion, c.convalidated AS validada
FROM pg_constraint c
WHERE c.connamespace = 'public'::regnamespace
  AND c.conname IN ('fk_camara_compra', 'fk_camara_fruta',
                   'fk_cjchica_usuario_registro', 'fk_cjchica_usuario_actualizacion',
                   'fk_compra_usuario_estado')
ORDER BY tabla, relacion;
