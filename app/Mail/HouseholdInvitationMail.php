<?php

namespace App\Mail;

use App\Models\HouseholdInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A propósito NO implementa ShouldQueue: en el hosting compartido real de
 * producción no hay confirmación de que corra un worker de colas (`queue:work`
 * necesita un proceso persistente, poco común en shared hosting) — si se
 * encolara y nadie la procesa, la invitación nunca llegaría, silenciosamente.
 * Se envía sincrónico en el propio request; es una operación de baja
 * frecuencia (invitar a un familiar), la latencia extra es aceptable.
 */
class HouseholdInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public HouseholdInvitation $invitation) {}

    public function envelope(): Envelope
    {
        $hogar = $this->invitation->household;

        return new Envelope(
            subject: "Te invitaron a la familia \"{$hogar->name}\" en UparVital",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.household-invitation',
            with: [
                'householdName' => $this->invitation->household->name,
                'invitedByName' => $this->invitation->invitedBy->name,
                'token' => $this->invitation->token,
                'roleLabel' => $this->invitation->role_assigned === 'viewer' ? 'observador' : 'miembro',
                'expiresAt' => $this->invitation->expires_at,
                'appUrl' => config('app.frontend_url'),
            ],
        );
    }
}
