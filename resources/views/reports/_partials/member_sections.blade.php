{{-- Espera: $member (User), $sections (array<string,bool>), $data (array de colecciones) --}}

@if($sections['profile'] ?? true)
    <h2>Datos del paciente</h2>
    <table class="data">
        <tr><td style="width:30%"><strong>Nombre</strong></td><td>{{ $member->name }}</td></tr>
        <tr><td><strong>Tipo de sangre</strong></td><td>{{ $member->blood_type ?? '—' }}</td></tr>
        <tr><td><strong>EPS</strong></td><td>{{ $member->eps ?? '—' }}</td></tr>
        <tr><td><strong>IPS preferida</strong></td><td>{{ $member->ips_preferida ?? '—' }}</td></tr>
    </table>

    @if($data['allergies']->isNotEmpty())
        <p><strong>Alergias:</strong>
            @foreach($data['allergies'] as $a)
                <span class="chip badge-red">{{ $a->name }} ({{ $a->severity }})</span>
            @endforeach
        </p>
    @endif

    @if($data['chronicConditions']->isNotEmpty())
        <p><strong>Condiciones crónicas:</strong>
            @foreach($data['chronicConditions'] as $c)
                <span class="chip badge-blue">{{ $c->name }}</span>
            @endforeach
        </p>
    @endif
@endif

@if(($sections['appointments'] ?? true))
    <h2>Citas médicas</h2>
    @if($data['appointments']->isEmpty())
        <p class="empty">Sin citas registradas en el período.</p>
    @else
        <table class="data">
            <thead><tr><th>Fecha</th><th>Especialidad</th><th>Médico</th><th>Estado</th><th>Diagnóstico</th></tr></thead>
            <tbody>
            @foreach($data['appointments'] as $a)
                <tr>
                    <td>{{ $a->is_need ? 'Sin agendar' : optional($a->appointment_date)->format('d/m/Y H:i') }}</td>
                    <td>{{ $a->specialty }}</td>
                    <td>{{ $a->doctor->name ?? $a->doctor_name_free ?? '—' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $a->status)) }}</td>
                    <td>{{ $a->diagnosis ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

@if(($sections['medications'] ?? true))
    <h2>Medicamentos</h2>
    @if($data['medications']->isEmpty())
        <p class="empty">Sin medicamentos registrados en el período.</p>
    @else
        <table class="data">
            <thead><tr><th>Nombre</th><th>Dosis</th><th>Frecuencia</th><th>Estado</th><th>Recurrente</th></tr></thead>
            <tbody>
            @foreach($data['medications'] as $m)
                <tr>
                    <td>{{ $m->name }}</td>
                    <td>{{ $m->dosage }}</td>
                    <td>{{ $m->frequency }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $m->status)) }}</td>
                    <td>{{ $m->is_recurring ? 'Sí' : 'No' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

@if(($sections['exams'] ?? true))
    <h2>Exámenes</h2>
    @if($data['exams']->isEmpty())
        <p class="empty">Sin exámenes registrados en el período.</p>
    @else
        <table class="data">
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Resultado</th></tr></thead>
            <tbody>
            @foreach($data['exams'] as $e)
                <tr>
                    <td>{{ $e->name }}</td>
                    <td>{{ ucfirst($e->exam_type) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $e->status)) }}</td>
                    <td>{{ $e->result_summary ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

@if(($sections['referrals'] ?? true))
    <h2>Remisiones</h2>
    @if($data['referrals']->isEmpty())
        <p class="empty">Sin remisiones registradas en el período.</p>
    @else
        <table class="data">
            <thead><tr><th>Especialidad</th><th>Motivo</th><th>Estado</th><th>Vence autorización</th></tr></thead>
            <tbody>
            @foreach($data['referrals'] as $r)
                <tr>
                    <td>{{ $r->specialty }}</td>
                    <td>{{ $r->reason }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $r->status)) }}</td>
                    <td>{{ optional($r->authorization_expiry_date)->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

@if(($sections['leaves'] ?? true))
    <h2>Incapacidades</h2>
    @if($data['leaves']->isEmpty())
        <p class="empty">Sin incapacidades registradas en el período.</p>
    @else
        <table class="data">
            <thead><tr><th>Desde</th><th>Hasta</th><th>Días</th><th>Tipo</th><th>Diagnóstico</th></tr></thead>
            <tbody>
            @foreach($data['leaves'] as $l)
                <tr>
                    <td>{{ $l->start_date->format('d/m/Y') }}</td>
                    <td>{{ $l->end_date->format('d/m/Y') }}</td>
                    <td>{{ $l->total_days }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $l->leave_type)) }}</td>
                    <td>{{ $l->diagnosis ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

@if(($sections['vaccinations'] ?? true))
    <h2>Vacunas</h2>
    @if($data['vaccinations']->isEmpty())
        <p class="empty">Sin vacunas registradas en el período.</p>
    @else
        <table class="data">
            <thead><tr><th>Vacuna</th><th>Dosis</th><th>Fecha</th><th>Próxima dosis</th></tr></thead>
            <tbody>
            @foreach($data['vaccinations'] as $v)
                <tr>
                    <td>{{ $v->vaccine_name }}</td>
                    <td>{{ $v->dose_number ?? '—' }}</td>
                    <td>{{ $v->application_date->format('d/m/Y') }}</td>
                    <td>{{ optional($v->next_dose_date)->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

@if(($sections['vitalSigns'] ?? true))
    <h2>Signos vitales</h2>
    @if($data['vitalSigns']->isEmpty())
        <p class="empty">Sin registros de signos vitales en el período.</p>
    @else
        <table class="data">
            <thead><tr><th>Fecha</th><th>Presión</th><th>Glucosa</th><th>Peso</th><th>SatO2</th></tr></thead>
            <tbody>
            @foreach($data['vitalSigns'] as $v)
                <tr>
                    <td>{{ $v->measurement_date->format('d/m/Y H:i') }}</td>
                    <td>{{ $v->systolic_pressure && $v->diastolic_pressure ? "{$v->systolic_pressure}/{$v->diastolic_pressure}" : '—' }}</td>
                    <td>{{ $v->blood_glucose ?? '—' }}</td>
                    <td>{{ $v->weight ?? '—' }}</td>
                    <td>{{ $v->oxygen_saturation ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

@if(($sections['documents'] ?? true))
    <h2>Documentos adjuntos</h2>
    @if($data['documents']->isEmpty())
        <p class="empty">Sin documentos adjuntos en el período.</p>
    @else
        <table class="data">
            <thead><tr><th>Título</th><th>Tipo</th><th>Fecha</th></tr></thead>
            <tbody>
            @foreach($data['documents'] as $d)
                <tr>
                    <td>{{ $d->title }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $d->document_type)) }}</td>
                    <td>{{ optional($d->document_date)->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif
