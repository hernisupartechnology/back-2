<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('reports._partials.styles')
</head>
<body>
    @php($reportTitle = "Historial médico — {$member->name}")
    @include('reports._partials.header')

    <p style="color:#666; font-size:10px;">Período: {{ $periodLabel }}</p>

    @include('reports._partials.member_sections', ['member' => $member, 'sections' => $sections, 'data' => $data])

    @include('reports._partials.footer')
</body>
</html>
