<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('reports._partials.styles')
</head>
<body>
    @php($reportTitle = "Reporte de incapacidades — {$household->name}")
    @include('reports._partials.header')

    <p style="color:#666; font-size:10px;">Período: {{ $periodLabel }}</p>

    @if($leaves->isEmpty())
        <p class="empty">Sin incapacidades registradas en el período.</p>
    @else
        <table class="data">
            <thead>
                <tr><th>Miembro</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Tipo</th><th>Diagnóstico</th><th>IPS</th></tr>
            </thead>
            <tbody>
            @foreach($leaves as $l)
                <tr>
                    <td>{{ $l->patient->name ?? '—' }}</td>
                    <td>{{ $l->start_date->format('d/m/Y') }}</td>
                    <td>{{ $l->end_date->format('d/m/Y') }}</td>
                    <td>{{ $l->total_days }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $l->leave_type)) }}</td>
                    <td>{{ $l->diagnosis ?? '—' }}</td>
                    <td>{{ $l->ips_issued ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <p style="margin-top:10px;"><strong>Total de días de incapacidad en el período:</strong> {{ $leaves->sum('total_days') }}</p>
    @endif

    @include('reports._partials.footer')
</body>
</html>
