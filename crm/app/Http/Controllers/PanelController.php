<?php

namespace App\Http\Controllers;

use App\Enums\Canal;
use App\Enums\EstadoEmpresa;
use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Empresa;
use App\Models\Rubro;
use App\Services\Estadisticas;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function __invoke(Estadisticas $estadisticas): View
    {
        $hoy = CarbonImmutable::today();
        $sinSeguimiento = [EstadoEmpresa::Perdido->value, EstadoEmpresa::NoContactar->value];

        $seguimientosHoy = Empresa::with('rubro')
            ->whereDate('proxima_accion_fecha', $hoy->toDateString())
            ->whereNotIn('estado', $sinSeguimiento)
            ->orderBy('nombre')->get();

        $seguimientosVencidos = Empresa::with('rubro')
            ->whereDate('proxima_accion_fecha', '<', $hoy->toDateString())
            ->whereNotIn('estado', $sinSeguimiento)
            ->orderBy('proxima_accion_fecha')->limit(50)->get();

        $diasAviso = Ajuste::entero('dias_aviso_abono');
        $abonos = Cliente::with(['empresa', 'pagos'])->where('activo', true)->get()
            ->map(fn (Cliente $c) => ['cliente' => $c, 'abono' => $c->estadoAbono($diasAviso)])
            ->filter(fn ($x) => in_array($x['abono']['estado'], ['vencido', 'proximo'], true))
            ->sortBy(fn ($x) => $x['abono']['estado'] === 'vencido' ? 0 : 1)
            ->values();

        return view('panel', [
            'hoy' => $hoy,
            'seguimientosHoy' => $seguimientosHoy,
            'seguimientosVencidos' => $seguimientosVencidos,
            'porEstado' => Empresa::query()->toBase()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado'),
            'porRubro' => Rubro::withCount('empresas')->orderBy('nombre')->get(),
            'totalEmpresas' => Empresa::count(),
            'contactosHoy' => $this->salientesDesde($hoy),
            'contactosSemana' => $this->salientesDesde($hoy->startOfWeek(CarbonInterface::MONDAY)),
            'whatsappHoy' => self::whatsappHoy(),
            'limiteWhatsapp' => Ajuste::entero('limite_whatsapp_diario'),
            'abonos' => $abonos,
            'tasas' => $estadisticas->tasas($hoy->subDays(30), $hoy),
        ]);
    }

    /** WhatsApp registrados hoy entre todos los usuarios. */
    public static function whatsappHoy(): int
    {
        $hoy = CarbonImmutable::today();

        return Contacto::where('canal', Canal::WhatsApp->value)
            ->whereBetween('fecha_hora', [$hoy, $hoy->endOfDay()])
            ->count();
    }

    private function salientesDesde(CarbonImmutable $desde): int
    {
        return Contacto::whereIn('canal', Canal::salientes())
            ->whereBetween('fecha_hora', [$desde->startOfDay(), CarbonImmutable::today()->endOfDay()])
            ->count();
    }
}
