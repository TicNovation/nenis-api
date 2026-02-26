<?php
 
namespace App\Mail;
 
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class SolicitudPublicidadEmail extends Mailable
{
    use Queueable, SerializesModels, MailerSendTrait;

    public $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: " 🚀 Nueva solicitud de publicidad: " . $this->data['nombre_negocio'],
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'emails.solicitudpublicidad',
            with: [
                'nombre' => $this->data['nombre'],
                'correo' => $this->data['correo'],
                'telefono' => $this->data['telefono'],
                'nombre_negocio' => $this->data['nombre_negocio'],
                'mensaje' => $this->data['mensaje'],
            ]  
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
