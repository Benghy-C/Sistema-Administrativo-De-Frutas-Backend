<?php

namespace App\Http\Controllers;

use App\Services\OperationalQueries;
use App\Services\ReportDetails;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportsController extends Controller
{
    public function catalogos()
    {
        return response()->json([
            'proveedores' => DB::table('proveedores')->orderBy('nombre')
                ->get(['id', 'nombre']),
            'clientes' => DB::table('clientes')->orderBy('nombres')
                ->get(['id', 'nombres as nombre']),
            'categorias' => DB::table('categorias_gasto')->orderBy('nombre')
                ->get(['id', 'nombre']),
        ]);
    }

    public function index(
        Request $request,
        OperationalQueries $queries,
        ReportDetails $detalles
    ) {
        $datos = $this->filtros($request);
        $tipo = $datos['tipo'];
        $query = $queries->reporte($tipo, $datos);
        $columnas = $this->columnas($tipo);
        $descripcionFiltros = $this->descripcionFiltros($datos);
        $tieneImporte = in_array($tipo, ['compras', 'envios', 'gastos']);
        $resumen = [
            'registros' => (clone $query)->count(),
            'cajas' => $tipo === 'gastos' ? null : (float) (clone $query)->sum('cajas'),
            'importe' => $tieneImporte ? (float) (clone $query)->sum('importe') : null,
        ];
        $nota = $tipo === 'existencias'
            ? 'Saldo actual de lotes vigentes. El período filtra su fecha de ingreso; '.
                'no reconstruye saldos pasados.'
            : ($tipo === 'perdidas'
                ? 'Pérdidas registradas en cajas.'
                : 'Los importes corresponden a los registros y estados seleccionados.');
        $query->orderByDesc('fecha')->orderByDesc('id');

        if (($datos['formato'] ?? '') === 'csv') {
            return response()->streamDownload(function () use (
                $query, $columnas, $tipo, $detalles, $resumen, $descripcionFiltros
            ) {
                $salida = fopen('php://output', 'w');
                fwrite($salida, "\xEF\xBB\xBF");
                $escribir = function (array $valores) use ($salida) {
                    $valores = array_map(function ($valor) {
                        $texto = (string) ($valor ?? 'Sin registrar');

                        return preg_match('/^[=+@\-\t\r]/u', $texto)
                            ? "'".$texto : $texto;
                    }, $valores);
                    fputcsv($salida, $valores, ';', '"', '');
                };
                $escribir(['Florencia', 'Reporte de '.$tipo]);
                $escribir(['Filtros', $descripcionFiltros]);
                $escribir(['Generado', now('America/Lima')->format('Y-m-d H:i:s')]);
                $escribir(['Unidades', 'Cajas enteras; importes en soles (PEN)']);
                $escribir([]);
                $cabeceras = array_column($columnas, 'titulo');
                $tieneDetalle = in_array($tipo, ['compras', 'envios']);
                if ($tieneDetalle) {
                    $cabeceras[] = $tipo === 'compras' ? 'Precios por calidad' : 'Lotes de origen';
                }
                $escribir($cabeceras);
                foreach ($query->lazy(200)->chunk(200) as $grupo) {
                    $detalles->completar($tipo, $grupo);
                    foreach ($grupo as $fila) {
                        $valores = [];
                        foreach ($columnas as $columna) {
                            $valores[] = $fila->{$columna['clave']} ?? null;
                        }
                        if ($tieneDetalle) {
                            $valores[] = $detalles->texto($tipo, $fila);
                        }
                        $escribir($valores);
                    }
                }
                $escribir([]);
                $escribir(['Total de registros', $resumen['registros']]);
                if ($resumen['cajas'] !== null) {
                    $escribir(['Total de cajas', $resumen['cajas']]);
                }
                if ($resumen['importe'] !== null) {
                    $escribir(['Total en soles (PEN)', $resumen['importe']]);
                }
                fclose($salida);
            }, 'florencia-'.$tipo.'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        if (($datos['formato'] ?? '') === 'pdf') {
            if ($resumen['registros'] > 5000) {
                throw ValidationException::withMessages([
                    'formato' => 'Para PDF, reduce la consulta a 5000 filas o utiliza CSV.',
                ]);
            }
            $pagina = [
                'data' => $query->get(),
                'current_page' => 1,
                'last_page' => 1,
                'total' => $resumen['registros'],
            ];
        } else {
            $pagina = $query->paginate(5)->toArray();
        }

        $detalles->completar($tipo, $pagina['data']);

        return response()->json($pagina + [
            'columnas' => $columnas,
            'detalle_columnas' => $detalles->columnas($tipo),
            'filtros_descripcion' => $descripcionFiltros,
            'resumen' => $resumen,
            'nota' => $nota,
            'generado' => now('America/Lima')->format('Y-m-d H:i:s'),
            'filtros' => $datos,
        ]);
    }

    public function indicadores(Request $request, OperationalQueries $queries)
    {
        $datos = $request->validate([
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ]);
        $desde = CarbonImmutable::parse($datos['desde'])->startOfMonth();
        $hasta = CarbonImmutable::parse($datos['hasta'])->startOfMonth();
        if ($desde->diffInMonths($hasta) > 119) {
            throw ValidationException::withMessages([
                'hasta' => 'Consulta un período de hasta diez años.',
            ]);
        }

        $compras = $queries->reporte('compras', $datos + ['estado' => 1]);
        $envios = $queries->reporte('envios', $datos + ['estado' => 1]);
        $perdidas = $queries->reporte('perdidas', $datos);
        $gastos = $queries->reporte('gastos', $datos);
        $series = [];
        foreach ([
            'compras' => [$compras, 'importe'],
            'envios' => [$envios, 'importe'],
            'perdidas' => [$perdidas, 'cajas'],
            'gastos' => [$gastos, 'importe'],
        ] as $nombre => [$query, $campo]) {
            $series[$nombre] = DB::query()->fromSub(clone $query, 'r')
                ->selectRaw("TO_CHAR(CAST(fecha AS DATE), 'YYYY-MM') AS mes")
                ->selectRaw('SUM('.$campo.') AS valor')
                ->groupByRaw("TO_CHAR(CAST(fecha AS DATE), 'YYYY-MM')")
                ->pluck('valor', 'mes');
        }

        $perdidasPorCalidad = DB::query()->fromSub(clone $perdidas, 'r')
            ->select('calidad')
            ->selectRaw("TO_CHAR(CAST(fecha AS DATE), 'YYYY-MM') AS mes")
            ->selectRaw('SUM(cajas) AS cajas')
            ->groupByRaw("TO_CHAR(CAST(fecha AS DATE), 'YYYY-MM')")
            ->groupBy('calidad')->get()->groupBy('mes');
        $meses = [];
        for ($mes = $desde; $mes <= $hasta; $mes = $mes->addMonth()) {
            $clave = $mes->format('Y-m');
            $fila = ['mes' => $clave];
            foreach ($series as $nombre => $valores) {
                $fila[$nombre] = (float) ($valores[$clave] ?? 0);
            }
            $calidadesPerdidas = [];
            $registros = $perdidasPorCalidad->get($clave, collect());
            foreach (['A', 'B', 'C'] as $calidad) {
                $registro = $registros->firstWhere('calidad', $calidad);
                $calidadesPerdidas[$calidad] = (int) ($registro->cajas ?? 0);
            }
            $fila['perdidas_calidad'] = $calidadesPerdidas;
            $meses[] = $fila;
        }

        return response()->json([
            'periodo' => $datos,
            'totales' => [
                'compras' => (float) (clone $compras)->sum('importe'),
                'envios' => (float) (clone $envios)->sum('importe'),
                'perdidas' => (float) (clone $perdidas)->sum('cajas'),
                'gastos' => (float) (clone $gastos)->sum('importe'),
            ],
            'meses' => $meses,
            'calidades' => [
                'A' => (float) (clone $envios)->sum('a'),
                'B' => (float) (clone $envios)->sum('b'),
                'C' => (float) (clone $envios)->sum('c'),
            ],
            'proveedores' => DB::query()->fromSub($compras, 'r')
                ->select('proveedor_id', 'contacto')
                ->selectRaw('SUM(cajas) AS cajas, SUM(importe) AS importe')
                ->groupBy('proveedor_id', 'contacto')
                ->orderByDesc('importe')->get(),
            'categorias' => DB::query()->fromSub($gastos, 'r')
                ->select('categoria_id', 'categoria')
                ->selectRaw('SUM(importe) AS importe')
                ->groupBy('categoria_id', 'categoria')
                ->orderByDesc('importe')->get(),
            'nota' => 'Envíos comerciales vigentes; no representan cobros ni utilidad. '.
                'Las pérdidas se expresan en cajas, sin valoración monetaria.',
        ]);
    }

    private function filtros(Request $request): array
    {
        $reglas = [
            'tipo' => 'required|in:compras,envios,existencias,perdidas,gastos',
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'buscar' => 'nullable|string|max:100',
            'calidad' => 'nullable|in:A,B,C',
            'proveedor_id' => 'nullable|integer|min:1',
            'cliente_id' => 'nullable|integer|min:1',
            'categoria_id' => 'nullable|integer|min:0',
            'estado' => 'nullable|integer|in:0,1',
            'page' => 'nullable|integer|min:1',
            'formato' => 'nullable|in:csv,pdf',
        ];
        if ($request->filled('desde')) {
            $reglas['hasta'][] = 'after_or_equal:desde';
        }

        return $request->validate($reglas);
    }

    private function descripcionFiltros(array $datos): string
    {
        $partes = [
            'Desde: '.($datos['desde'] ?? 'Inicio'),
            'Hasta: '.($datos['hasta'] ?? 'Actualidad'),
        ];
        foreach (['buscar' => 'Búsqueda', 'calidad' => 'Calidad'] as $campo => $titulo) {
            if (! empty($datos[$campo])) {
                $partes[] = $titulo.': '.$datos[$campo];
            }
        }
        if (isset($datos['estado'])) {
            $partes[] = 'Estado: '.((int) $datos['estado'] === 1
                ? 'Vigentes' : 'Anulados / cancelados');
        }
        $catalogos = [
            'proveedor_id' => ['proveedores', 'nombre', 'Proveedor'],
            'cliente_id' => ['clientes', 'nombres', 'Cliente'],
            'categoria_id' => ['categorias_gasto', 'nombre', 'Categoría'],
        ];
        foreach ($catalogos as $campo => [$tabla, $nombre, $titulo]) {
            if (isset($datos[$campo])) {
                $valor = $campo === 'categoria_id' && (int) $datos[$campo] === 0
                    ? 'Sin clasificar'
                    : DB::table($tabla)->where('id', $datos[$campo])->value($nombre);
                $partes[] = $titulo.': '.($valor ?? 'Registro '.$datos[$campo]);
            }
        }

        return implode(' · ', $partes);
    }

    private function columnas(string $tipo): array
    {
        $campos = match ($tipo) {
            'compras' => [
                ['codigo', 'Pedido'], ['fecha', 'Fecha', 'fecha'],
                ['contacto', 'Proveedor'], ['fruta', 'Fruta'],
                ['cajas', 'Cajas', 'numero'], ['a', 'A', 'numero'],
                ['b', 'B', 'numero'], ['c', 'C', 'numero'],
                ['subtotal', 'Subtotal', 'dinero'],
                ['costos', 'Costos adicionales', 'dinero'],
                ['importe', 'Total', 'dinero'], ['estado', 'Estado'],
            ],
            'envios' => [
                ['codigo', 'Envío'], ['fecha', 'Fecha', 'fecha'],
                ['contacto', 'Cliente'], ['fruta', 'Fruta'],
                ['cajas', 'Cajas', 'numero'], ['a', 'A', 'numero'],
                ['b', 'B', 'numero'], ['c', 'C', 'numero'],
                ['importe', 'Total', 'dinero'], ['estado', 'Estado'],
            ],
            'existencias' => [
                ['codigo', 'Lote'], ['fecha', 'Ingreso', 'fecha'],
                ['contacto', 'Proveedor'], ['fruta', 'Fruta'],
                ['original', 'Entrada original', 'numero'],
                ['a', 'A', 'numero'], ['b', 'B', 'numero'],
                ['c', 'C', 'numero'], ['cajas', 'Disponibles', 'numero'],
            ],
            'perdidas' => [
                ['codigo', 'Lote'], ['fecha', 'Fecha', 'fecha'],
                ['fruta', 'Fruta'], ['calidad', 'Calidad'],
                ['cajas', 'Cajas', 'numero'], ['motivo', 'Motivo'],
                ['responsable', 'Responsable'],
            ],
            'gastos' => [
                ['fecha', 'Fecha y hora', 'fecha'], ['descripcion', 'Descripción'],
                ['categoria', 'Categoría'], ['importe', 'Monto', 'dinero'],
                ['responsable', 'Responsable'],
            ],
        };

        return array_map(fn ($campo) => [
            'clave' => $campo[0],
            'titulo' => $campo[1],
            'tipo' => $campo[2] ?? 'texto',
        ], $campos);
    }
}
