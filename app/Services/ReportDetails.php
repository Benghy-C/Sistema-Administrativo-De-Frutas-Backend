<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReportDetails
{
    public function columnas(string $tipo): array
    {
        $campos = match ($tipo) {
            'compras' => [
                ['fruta', 'Fruta', 'texto'],
                ['calidad', 'Calidad', 'texto'],
                ['cajas', 'Cajas', 'numero'],
                ['precio', 'Precio por caja', 'dinero'],
                ['subtotal', 'Subtotal', 'dinero'],
            ],
            'envios' => [
                ['lote', 'Lote de origen', 'texto'],
                ['calidad', 'Calidad', 'texto'],
                ['cajas', 'Salidas', 'numero'],
                ['devueltas', 'Devueltas', 'numero'],
                ['saldo', 'Fuera de cámara', 'numero'],
            ],
            default => [],
        };

        return array_map(fn ($campo) => [
            'clave' => $campo[0],
            'titulo' => $campo[1],
            'tipo' => $campo[2],
        ], $campos);
    }

    public function completar(string $tipo, iterable $filas): void
    {
        $filas = collect($filas);
        $ids = $filas->pluck('id')->all();
        if (! $ids || ! in_array($tipo, ['compras', 'envios'])) {
            return;
        }

        $detalles = $tipo === 'compras'
            ? $this->compras($ids)
            : $this->envios($ids);

        foreach ($filas as $fila) {
            $fila->detalle = $detalles->get($fila->id, collect())->values()->all();
        }
    }

    private function compras(array $ids)
    {
        $registros = DB::table('compra_fruta as d')
            ->leftJoin('fruta as f', 'f.id', '=', 'd.id_fruta')
            ->whereIn('d.id_pedido', $ids)
            ->orderBy('d.id_pedido')->orderBy('d.id_fruta')
            ->get([
                'd.id_pedido', 'f.descripcion as fruta',
                'd.canta', 'd.cantb', 'd.cantc',
                'd.precioa', 'd.preciob', 'd.precioc',
            ]);
        $detalles = collect();
        foreach ($registros as $registro) {
            foreach (['A', 'B', 'C'] as $calidad) {
                $sufijo = strtolower($calidad);
                $cajas = (int) $registro->{'cant'.$sufijo};
                $precio = $registro->{'precio'.$sufijo};
                $detalles->push((object) [
                    'id' => $registro->id_pedido,
                    'fruta' => $registro->fruta,
                    'calidad' => $calidad,
                    'cajas' => $cajas,
                    'precio' => $precio,
                    'subtotal' => $precio === null
                        ? null : round($cajas * (float) $precio, 2),
                ]);
            }
        }

        return $detalles->groupBy('id');
    }

    private function envios(array $ids)
    {
        return DB::table('venta_lotes as t')
            ->join('venta_detalle as d', 'd.pk_ventadeta', '=', 't.detalle_id')
            ->join('camara_refigeracion as l', 'l.id_camara', '=', 't.camara_id')
            ->whereIn('d.venta_id', $ids)
            ->select('d.venta_id as id', 'l.id_compra', 't.calidad')
            ->selectRaw('SUM(t.cantidad) AS cajas')
            ->selectRaw('SUM(t.devuelta) AS devueltas')
            ->selectRaw('SUM(t.cantidad - t.devuelta) AS saldo')
            ->groupBy('d.venta_id', 'l.id_compra', 't.calidad')
            ->orderBy('l.id_compra')->orderBy('t.calidad')
            ->get()->map(function ($fila) {
                $fila->lote = 'LOT-'.str_pad((string) $fila->id_compra, 4, '0', STR_PAD_LEFT);

                return $fila;
            })->groupBy('id');
    }

    public function texto(string $tipo, object $fila): string
    {
        $detalles = collect($fila->detalle ?? []);
        if ($detalles->isEmpty()) {
            return $tipo === 'envios' ? 'Origen no registrado' : 'Sin registrar';
        }

        return $detalles->map(function ($detalle) use ($tipo) {
            $texto = ($tipo === 'compras' ? $detalle->fruta : $detalle->lote)
                .' · Calidad '.$detalle->calidad.' · '.$detalle->cajas.' cajas';
            if ($tipo === 'compras') {
                $texto .= ' · Precio por caja: '.($detalle->precio === null
                    ? 'Sin registrar' : 'S/ '.number_format((float) $detalle->precio, 2, '.', ''));
            }
            if ($tipo === 'envios') {
                $texto .= ' · Devueltas: '.$detalle->devueltas
                    .' · Fuera de cámara: '.$detalle->saldo;
            }

            return $texto;
        })->implode(' | ');
    }
}
