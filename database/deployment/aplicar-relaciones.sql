BEGIN;
SET LOCAL lock_timeout = '5s';
SET LOCAL statement_timeout = '60s';

DO $$
DECLARE
    existing_constraint record;
BEGIN
    IF EXISTS (
        SELECT 1 FROM public.camara_refigeracion child
        LEFT JOIN public.compra parent ON parent.id = child.id_compra
        WHERE child.id_compra IS NOT NULL AND parent.id IS NULL
    ) THEN
        RAISE EXCEPTION 'Hay referencias inexistentes en camara_refigeracion.id_compra; no se aplicaron las relaciones.';
    END IF;

    SELECT c.* INTO existing_constraint
    FROM pg_constraint c
    WHERE c.conrelid = 'public.camara_refigeracion'::regclass AND c.conname = 'fk_camara_compra';

    IF FOUND THEN
        IF existing_constraint.contype <> 'f'
            OR existing_constraint.confrelid <> 'public.compra'::regclass
            OR existing_constraint.conkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.camara_refigeracion'::regclass AND attname = 'id_compra')]::smallint[]
            OR existing_constraint.confkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.compra'::regclass AND attname = 'id')]::smallint[]
            OR existing_constraint.confdeltype <> 'a'
            OR existing_constraint.confupdtype <> 'a'
            OR existing_constraint.condeferrable THEN
            RAISE EXCEPTION 'La restricción fk_camara_compra existe con otra definición.';
        END IF;
    ELSE
        ALTER TABLE public.camara_refigeracion ADD CONSTRAINT fk_camara_compra
            FOREIGN KEY (id_compra) REFERENCES public.compra(id)
            ON UPDATE NO ACTION ON DELETE NO ACTION NOT VALID;
    END IF;
    ALTER TABLE public.camara_refigeracion VALIDATE CONSTRAINT fk_camara_compra;
END;
$$;

DO $$
DECLARE
    existing_constraint record;
BEGIN
    IF EXISTS (
        SELECT 1 FROM public.camara_refigeracion child
        LEFT JOIN public.fruta parent ON parent.id = child.id_fruta
        WHERE child.id_fruta IS NOT NULL AND parent.id IS NULL
    ) THEN
        RAISE EXCEPTION 'Hay referencias inexistentes en camara_refigeracion.id_fruta; no se aplicaron las relaciones.';
    END IF;

    SELECT c.* INTO existing_constraint
    FROM pg_constraint c
    WHERE c.conrelid = 'public.camara_refigeracion'::regclass AND c.conname = 'fk_camara_fruta';

    IF FOUND THEN
        IF existing_constraint.contype <> 'f'
            OR existing_constraint.confrelid <> 'public.fruta'::regclass
            OR existing_constraint.conkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.camara_refigeracion'::regclass AND attname = 'id_fruta')]::smallint[]
            OR existing_constraint.confkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.fruta'::regclass AND attname = 'id')]::smallint[]
            OR existing_constraint.confdeltype <> 'a'
            OR existing_constraint.confupdtype <> 'a'
            OR existing_constraint.condeferrable THEN
            RAISE EXCEPTION 'La restricción fk_camara_fruta existe con otra definición.';
        END IF;
    ELSE
        ALTER TABLE public.camara_refigeracion ADD CONSTRAINT fk_camara_fruta
            FOREIGN KEY (id_fruta) REFERENCES public.fruta(id)
            ON UPDATE NO ACTION ON DELETE NO ACTION NOT VALID;
    END IF;
    ALTER TABLE public.camara_refigeracion VALIDATE CONSTRAINT fk_camara_fruta;
END;
$$;

DO $$
DECLARE
    existing_constraint record;
BEGIN
    IF EXISTS (
        SELECT 1 FROM public.cjchica child
        LEFT JOIN public.users parent ON parent.id = child.id_user_ins
        WHERE child.id_user_ins IS NOT NULL AND parent.id IS NULL
    ) THEN
        RAISE EXCEPTION 'Hay referencias inexistentes en cjchica.id_user_ins; no se aplicaron las relaciones.';
    END IF;

    SELECT c.* INTO existing_constraint
    FROM pg_constraint c
    WHERE c.conrelid = 'public.cjchica'::regclass AND c.conname = 'fk_cjchica_usuario_registro';

    IF FOUND THEN
        IF existing_constraint.contype <> 'f'
            OR existing_constraint.confrelid <> 'public.users'::regclass
            OR existing_constraint.conkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.cjchica'::regclass AND attname = 'id_user_ins')]::smallint[]
            OR existing_constraint.confkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.users'::regclass AND attname = 'id')]::smallint[]
            OR existing_constraint.confdeltype <> 'a'
            OR existing_constraint.confupdtype <> 'a'
            OR existing_constraint.condeferrable THEN
            RAISE EXCEPTION 'La restricción fk_cjchica_usuario_registro existe con otra definición.';
        END IF;
    ELSE
        ALTER TABLE public.cjchica ADD CONSTRAINT fk_cjchica_usuario_registro
            FOREIGN KEY (id_user_ins) REFERENCES public.users(id)
            ON UPDATE NO ACTION ON DELETE NO ACTION NOT VALID;
    END IF;
    ALTER TABLE public.cjchica VALIDATE CONSTRAINT fk_cjchica_usuario_registro;
END;
$$;

DO $$
DECLARE
    existing_constraint record;
BEGIN
    IF EXISTS (
        SELECT 1 FROM public.cjchica child
        LEFT JOIN public.users parent ON parent.id = child.id_user_upd
        WHERE child.id_user_upd IS NOT NULL AND parent.id IS NULL
    ) THEN
        RAISE EXCEPTION 'Hay referencias inexistentes en cjchica.id_user_upd; no se aplicaron las relaciones.';
    END IF;

    SELECT c.* INTO existing_constraint
    FROM pg_constraint c
    WHERE c.conrelid = 'public.cjchica'::regclass AND c.conname = 'fk_cjchica_usuario_actualizacion';

    IF FOUND THEN
        IF existing_constraint.contype <> 'f'
            OR existing_constraint.confrelid <> 'public.users'::regclass
            OR existing_constraint.conkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.cjchica'::regclass AND attname = 'id_user_upd')]::smallint[]
            OR existing_constraint.confkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.users'::regclass AND attname = 'id')]::smallint[]
            OR existing_constraint.confdeltype <> 'a'
            OR existing_constraint.confupdtype <> 'a'
            OR existing_constraint.condeferrable THEN
            RAISE EXCEPTION 'La restricción fk_cjchica_usuario_actualizacion existe con otra definición.';
        END IF;
    ELSE
        ALTER TABLE public.cjchica ADD CONSTRAINT fk_cjchica_usuario_actualizacion
            FOREIGN KEY (id_user_upd) REFERENCES public.users(id)
            ON UPDATE NO ACTION ON DELETE NO ACTION NOT VALID;
    END IF;
    ALTER TABLE public.cjchica VALIDATE CONSTRAINT fk_cjchica_usuario_actualizacion;
END;
$$;

DO $$
DECLARE
    existing_constraint record;
BEGIN
    IF EXISTS (
        SELECT 1 FROM public.compra child
        LEFT JOIN public.users parent ON parent.id = child.usuid_alt
        WHERE child.usuid_alt IS NOT NULL AND parent.id IS NULL
    ) THEN
        RAISE EXCEPTION 'Hay referencias inexistentes en compra.usuid_alt; no se aplicaron las relaciones.';
    END IF;

    SELECT c.* INTO existing_constraint
    FROM pg_constraint c
    WHERE c.conrelid = 'public.compra'::regclass AND c.conname = 'fk_compra_usuario_estado';

    IF FOUND THEN
        IF existing_constraint.contype <> 'f'
            OR existing_constraint.confrelid <> 'public.users'::regclass
            OR existing_constraint.conkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.compra'::regclass AND attname = 'usuid_alt')]::smallint[]
            OR existing_constraint.confkey <> ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'public.users'::regclass AND attname = 'id')]::smallint[]
            OR existing_constraint.confdeltype <> 'a'
            OR existing_constraint.confupdtype <> 'a'
            OR existing_constraint.condeferrable THEN
            RAISE EXCEPTION 'La restricción fk_compra_usuario_estado existe con otra definición.';
        END IF;
    ELSE
        ALTER TABLE public.compra ADD CONSTRAINT fk_compra_usuario_estado
            FOREIGN KEY (usuid_alt) REFERENCES public.users(id)
            ON UPDATE NO ACTION ON DELETE NO ACTION NOT VALID;
    END IF;
    ALTER TABLE public.compra VALIDATE CONSTRAINT fk_compra_usuario_estado;
END;
$$;

CREATE INDEX IF NOT EXISTS idx_camara_compra_fruta ON public.camara_refigeracion (id_compra, id_fruta);
CREATE INDEX IF NOT EXISTS idx_camara_fruta ON public.camara_refigeracion (id_fruta);
CREATE INDEX IF NOT EXISTS idx_cjchica_usuario_registro ON public.cjchica (id_user_ins);
CREATE INDEX IF NOT EXISTS idx_cjchica_usuario_actualizacion ON public.cjchica (id_user_upd);
CREATE INDEX IF NOT EXISTS idx_compra_usuario_estado ON public.compra (usuid_alt);
CREATE INDEX IF NOT EXISTS idx_compra_proveedor ON public.compra (id_proveedor);
CREATE INDEX IF NOT EXISTS idx_compra_fruta_pedido ON public.compra_fruta (id_pedido);
CREATE INDEX IF NOT EXISTS idx_compra_fruta_fruta ON public.compra_fruta (id_fruta);

COMMIT;

SELECT c.conrelid::regclass AS tabla, c.conname AS relacion,
       pg_get_constraintdef(c.oid) AS definicion, c.convalidated AS validada
FROM pg_constraint c
WHERE c.connamespace = 'public'::regnamespace
  AND c.conname IN ('fk_camara_compra', 'fk_camara_fruta',
                   'fk_cjchica_usuario_registro', 'fk_cjchica_usuario_actualizacion',
                   'fk_compra_usuario_estado')
ORDER BY tabla, relacion;
