<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\Rubro;
use App\Services\Estadisticas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EstadisticaController extends Controller
{
    public function __invoke(Request $request, Estadisticas $estadisticas): View
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $hasta = $request->filled('hasta') ? CarbonImmutable::parse($request->input('hasta')) : CarbonImmutable::today();
        $desde = $request->filled('desde') ? CarbonImmutable::parse($request->input('desde')) : $hasta->subDays(30);

        // Embudo: empresas por estado en cada rubro.
        $conteos = Empresa::query()->toBase()->selectRaw('rubro_id, estado, count(*) as total')
            ->groupBy('rubro_id', 'estado')->get()
            ->groupBy('rubro_id');

        return view('estadisticas', [
            'desde' => $desde,
            'hasta' => $hasta,
            'tasas' => $estadisticas->tasas($desde, $hasta),
            'rubros' => Rubro::orderBy('nombre')->get(),
            'conteos' => $conteos,
            'estados' => EstadoEmpresa::cases(),
        ]);
    }
}
