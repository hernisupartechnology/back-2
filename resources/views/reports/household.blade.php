<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('reports._partials.styles')
</head>
<body>
    @php($reportTitle = "Reporte del hogar — {$household->name}")
    @include('reports._partials.header')

    <p style="color:#666; font-size:10px;">Período: {{ $periodLabel }} · {{ count($membersData) }} miembro(s)</p>

    @foreach($membersData as $entry)
        <div class="member-block">
            <div style="background:#F1F8E9; padding:8px 10px; border-radius:6px; margin-bottom:8px;">
                <strong style="color:#1B5E20; font-size:13px;">{{ $entry['member']->name }}</strong>
                <span style="color:#666; font-size:10px;"> — {{ ucfirst($entry['member']->role) }}</span>
            </div>
            @include('reports._partials.member_sections', ['member' => $entry['member'], 'sections' => $sections, 'data' => $entry['data']])
        </div>
    @endforeach

    @include('reports._partials.footer')
</body>
</html>
