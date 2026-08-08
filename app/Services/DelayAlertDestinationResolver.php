<?php

namespace App\Services;

use App\Models\DelayAlertDelivery;
use App\Models\Shipment;
use Illuminate\Support\Str;

class DelayAlertDestinationResolver
{
    /**
     * @return array<string, array{channel:string, audience:string, target:string, stored_destination:string, label:string}>
     */
    public function forShipment(Shipment $shipment): array
    {
        $shipment->loadMissing('customer.user');
        $destinations = [];

        if (config('delay-alerts.notify_customer')) {
            $customerEmail = $shipment->customer?->user?->email;

            if ($customerEmail) {
                $this->addEmailDestination($destinations, $customerEmail, 'customer');
            }
        }

        foreach ((array) config('delay-alerts.operations_emails', []) as $email) {
            $this->addEmailDestination($destinations, (string) $email, 'operations');
        }

        $webhookUrl = trim((string) config('delay-alerts.webhook.url'));
        $scheme = parse_url($webhookUrl, PHP_URL_SCHEME);

        if ($webhookUrl !== ''
            && filter_var($webhookUrl, FILTER_VALIDATE_URL)
            && in_array($scheme, ['http', 'https'], true)
            && (! app()->environment('production') || $scheme === 'https')) {
            $hash = DelayAlertDelivery::destinationHash(DelayAlertDelivery::CHANNEL_WEBHOOK, $webhookUrl);
            $host = parse_url($webhookUrl, PHP_URL_HOST) ?: 'configured-endpoint';
            $destinations[$hash] = [
                'channel' => DelayAlertDelivery::CHANNEL_WEBHOOK,
                'audience' => 'system',
                'target' => $webhookUrl,
                'stored_destination' => 'webhook://'.$host,
                'label' => 'webhook '.$host,
            ];
        }

        return $destinations;
    }

    /**
     * @return array{channel:string, audience:string, target:string, stored_destination:string, label:string}|null
     */
    public function currentFor(DelayAlertDelivery $delivery, Shipment $shipment): ?array
    {
        $destination = $this->forShipment($shipment)[$delivery->destination_hash] ?? null;

        if (! $destination || $destination['channel'] !== $delivery->channel) {
            return null;
        }

        return $destination;
    }

    /**
     * @param  array<string, array{channel:string, audience:string, target:string, stored_destination:string, label:string}>  $destinations
     */
    private function addEmailDestination(array &$destinations, string $email, string $audience): void
    {
        $email = Str::lower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $hash = DelayAlertDelivery::destinationHash(DelayAlertDelivery::CHANNEL_MAIL, $email);

        if (isset($destinations[$hash])) {
            return;
        }

        $destinations[$hash] = [
            'channel' => DelayAlertDelivery::CHANNEL_MAIL,
            'audience' => $audience,
            'target' => $email,
            'stored_destination' => $email,
            'label' => $email,
        ];
    }
}
