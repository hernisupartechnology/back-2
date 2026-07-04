<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('reports._partials.styles')
</head>
<body>
    @php($reportTitle = "Semáforo de alertas — {$household->name}")
    @include('reports._partials.header')

    <table class="data" style="margin-bottom:16px;">
        <tr>
            <td style="text-align:center;"><span class="badge badge-red">{{ $summary['red'] }}</span><br>Rojas</td>
            <td style="text-align:center;"><span class="badge badge-yellow">{{ $summary['yellow'] }}</span><br>Amarillas</td>
            <td style="text-align:center;"><span class="badge badge-blue">{{ $summary['blue'] }}</span><br>Informativas</td>
        </tr>
    </table>

    @if(empty($alerts))
        <p class="empty">✅ Sin alertas activas — todo en orden.</p>
    @else
        <table class="data">
            <thead><tr><th>Nivel</th><th>Miembro</th><th>Alerta</th><th>Detalle</th></tr></thead>
            <tbody>
            @foreach($alerts as $alert)
                <tr>
                    <td><span class="badge badge-{{ $alert['level'] }}">{{ strtoupper($alert['level']) }}</span></td>
                    <td>{{ $alert['member']['name'] ?? '—' }}</td>
                    <td>{{ $alert['title'] }}</td>
                    <td>{{ $alert['description'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @include('reports._partials.footer')
</body>
</html>
