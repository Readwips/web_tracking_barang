<?php

namespace App\Mail;

use App\Models\DelayAlertDelivery;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentDelayedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Shipment $shipment,
        public DelayAlertDelivery $delivery,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengiriman '.$this->plainLine($this->shipment->booking_number).' mengalami keterlambatan',
        );
    }

    public function content(): Content
    {
        $isEtaOverdue = $this->delivery->expected_arrival->startOfDay()->lt(today());
        $daysLate = $isEtaOverdue
            ? max(1, (int) $this->delivery->expected_arrival->startOfDay()->diffInDays(today()))
            : 0;
        $plain = [
            'appName' => $this->plainLine((string) config('app.name')),
            'bookingNumber' => $this->plainLine($this->shipment->booking_number),
            'containerNumber' => $this->plainLine($this->shipment->container->container_number),
            'customerName' => $this->plainLine($this->shipment->customer->name),
            'destination' => $this->plainLine($this->shipment->destinationPort->city),
            'expectedArrival' => $this->plainLine($this->delivery->expected_arrival->translatedFormat('d F Y')),
            'message' => $this->plainMessage($this->delivery->message),
            'origin' => $this->plainLine($this->shipment->originPort->city),
            'status' => $this->plainLine($this->shipment->status),
        ];

        return new Content(
            view: 'mail.shipment-delayed',
            text: 'mail.shipment-delayed-text',
            with: [
                'daysLate' => $daysLate,
                'isEtaOverdue' => $isEtaOverdue,
                'plain' => $plain,
                'trackingUrl' => route('tracking.show', $this->shipment->container->container_number),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function plainLine(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($value)));
    }

    private function plainMessage(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', strip_tags($value)));
    }
}
