<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class OperationalQueries
{
    public function reporte(string $tipo, array $filtros): Builder
    {
        $query = match ($tipo) {
            'compras' => $this->compras(),
            'envios' => $this->envios(),
            'existencias' => $this->existencias(),
            'perdidas' => $this->perdidas(),
            'gastos' => $this->gastos(),
        };
        $query = DB::query()->fromSub($query, 'registros');

        if (!empty($filtros['desde'])) {
            $query->whereDate('fecha', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->whereDate('fecha', '<=', $filtros['hasta']);
        }

        foreach (['proveedor_id', 'cliente_id', 'categoria_id'] as $campo) {
            $aplica = ($campo === 'proveedor_id' && in_array($tipo, ['compras', 'existencias', 'perdidas']))
                || ($campo === 'cliente_id' && $tipo === 'envios')
                || ($campo === 'categoria_id' && $tipo === 'gastos');

            if ($aplica && isset($filtros[$campo])) {
                if ($campo === 'categoria_id' && (int) $filtros[$campo] === 0) {
                    $query->whereNull($campo);
                } else {
                    $query->where($campo, $filtros[$campo]);
                }
            }
        }

        if (isset($filtros['estado']) && in_array($tipo, ['compras', 'envios'])) {
            $query->where('estado_id', $filtros['estado']);
        }

        if (!empty($filtros['calidad'])) {
            $calidad = $filtros['calidad'];
            if ($tipo === 'perdidas') {
                $query->where('calidad', $calidad);
            } elseif (in_array($tipo, ['compras', 'envios', 'existencias'])) {
                $query->where(strtolower($calidad), '>', 0);
            }
        }

        if (!empty($filtros['buscar'])) {
            $buscar = '%'.mb_strtolower(trim($filtros['buscar'])).'%';
            $campos = match ($tipo) {
                'compras', 'envios', 'existencias' => ['codigo', 'contacto', 'fruta'],
                'perdidas' => ['codigo', 'fruta', 'motivo', 'responsable'],
                'gastos' => ['descripcion', 'categoria', 'responsable'],
            };
            $query->where(function ($query) use ($buscar, $campos) {
                foreach ($campos as $campo) {
                    $query->orWhereRaw('LOWER('.$campo.') LIKE ?', [$buscar]);
                }
            });
        }

        return $query;
    }

    private function compras(): Builder
    {
        return DB::table('compra as c')
            ->join('compra_fruta as d', 'd.id_pedido', '=', 'c.id')
            ->leftJoin('proveedores as p', 'p.id', '=', 'c.id_proveedor')
            ->leftJoin('fruta as f', 'f.id', '=', 'd.id_fruta')
            ->select(
                'c.id', 'c.codigo_compra as codigo', 'c.fecha',
                'c.id_proveedor as proveedor_id', 'p.nombre as contacto',
                'c.precio_total as importe', 'c.cost_adici as costos',
                'c.estado as estado_id'
            )->selectRaw(
                "STRING_AGG(DISTINCT f.descripcion, ', ') AS fruta, ".
                'SUM(d.precio_sub_total) AS subtotal, '.
                'SUM(d.cantidad) AS cajas, SUM(d.canta) AS a, '.
                'SUM(d.cantb) AS b, SUM(d.cantc) AS c, '.
                "CASE WHEN c.estado = 1 THEN 'Vigente' ELSE 'Anulado' END AS estado"
            )->groupBy('c.id', 'p.nombre');
    }

    private function envios(): Builder
    {
        return DB::table('venta as v')
            ->join('venta_detalle as d', 'd.venta_id', '=', 'v.pk_venta')
            ->leftJoin('clientes as c', 'c.id', '=', 'v.id_clie')
            ->leftJoin('fruta as f', 'f.id', '=', 'd.futra_id')
            ->select(
                'v.pk_venta as id', 'v.fecha_registro as fecha',
                'v.id_clie as cliente_id', 'c.nombres as contacto',
                'v.cant_ven as cajas', 'v.total_ven as importe',
                'v.estado as estado_id'
            )->selectRaw(
                "'ENV-' || LPAD(CAST(v.pk_venta AS TEXT), ".
                "GREATEST(6, LENGTH(CAST(v.pk_venta AS TEXT))), '0') AS codigo, ".
                "CASE WHEN v.estado = 1 THEN 'Registrado' ELSE 'Cancelado' END AS estado, ".
                "STRING_AGG(DISTINCT f.descripcion, ', ') AS fruta, ".
                'SUM(d.canta) AS a, SUM(d.cantb) AS b, SUM(d.cantc) AS c'
            )->groupBy('v.pk_venta', 'c.nombres');
    }
    private function existencias(): Builder
    {
        return DB::table('camara_refigeracion as l')
            ->join('compra as c', 'c.id', '=', 'l.id_compra')
            ->leftJoin('proveedores as p', 'p.id', '=', 'c.id_proveedor')
            ->leftJoin('fruta as f', 'f.id', '=', 'l.id_fruta')
            ->where('c.estado', 1)
            ->select(
                'l.id_camara as id', 'l.fecha_registro as fecha',
                'c.id_proveedor as proveedor_id', 'p.nombre as contacto',
                'f.descripcion as fruta', 'l.cantidad_camara as original',
                'l.canta as a', 'l.cantb as b', 'l.cantc as c'
            )->selectRaw(
                "'LOT-' || LPAD(CAST(c.id AS TEXT), ".
                "GREATEST(4, LENGTH(CAST(c.id AS TEXT))), '0') AS codigo, ".
                'COALESCE(l.canta, 0) + COALESCE(l.cantb, 0) + '.
                'COALESCE(l.cantc, 0) AS cajas'
            );
    }

    private function perdidas(): Builder
    {
        return DB::table('perdidas as p')
            ->join('camara_refigeracion as l', 'l.id_camara', '=', 'p.camara_id')
            ->join('compra as c', 'c.id', '=', 'l.id_compra')
            ->leftJoin('fruta as f', 'f.id', '=', 'l.id_fruta')
            ->leftJoin('users as u', 'u.id', '=', 'p.usuario_id')
            ->select(
                'p.id', 'p.fecha', 'p.calidad', 'p.cantidad as cajas',
                'p.motivo', 'u.name as responsable', 'f.descripcion as fruta',
                'c.id_proveedor as proveedor_id'
            )->selectRaw(
                "'LOT-' || LPAD(CAST(c.id AS TEXT), ".
                "GREATEST(4, LENGTH(CAST(c.id AS TEXT))), '0') AS codigo"
            );
    }

    private function gastos(): Builder
    {
        return DB::table('cjchica as g')
            ->leftJoin('categorias_gasto as c', 'c.id', '=', 'g.categoria_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.id_user_ins')
            ->select(
                'g.id_caja as id', 'g.fecha_registro as fecha',
                'g.descripcion', 'g.mmonto as importe',
                'g.categoria_id', 'u.name as responsable'
            )->selectRaw("COALESCE(c.nombre, 'Sin clasificar') AS categoria");
    }
}
