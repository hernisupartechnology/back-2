<x-mail::message>
# ¡Te invitaron a una familia en UparVital!

**{{ $invitedByName }}** te invitó a unirte a la familia **"{{ $householdName }}"** como **{{ $roleLabel }}**.

Para aceptar, entra a UparVital, crea tu cuenta (o inicia sesión si ya tienes una) y usa este código de invitación:

<x-mail::panel>
# {{ $token }}
</x-mail::panel>

<x-mail::button :url="$appUrl">
Ir a UparVital
</x-mail::button>

Este código vence el {{ $expiresAt->translatedFormat('d \d\e F \d\e Y') }}.

Si no esperabas esta invitación, puedes ignorar este correo.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
