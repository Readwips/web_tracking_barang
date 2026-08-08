<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pemberitahuan Keterlambatan Pengiriman</title>
</head>
<body style="margin: 0; background: #f1f5f9; color: #0f172a; font-family: Arial, sans-serif;">
    <div style="margin: 0 auto; max-width: 640px; padding: 32px 16px;">
        <div style="border: 1px solid #cbd5e1; border-radius: 12px; background: #ffffff; padding: 32px;">
            <h1 style="margin: 0 0 24px; font-size: 24px; line-height: 1.3;">Pemberitahuan Keterlambatan Pengiriman</h1>

            @if($delivery->audience === 'customer')
                <p>Yth. {{ $shipment->customer->name }},</p>
            @else
                <p>Yth. Tim Operasional,</p>
            @endif

            <p style="white-space: pre-line; line-height: 1.7;">{{ $delivery->message }}</p>

            <table role="presentation" style="margin: 24px 0; width: 100%; border-collapse: collapse; background: #f8fafc;">
                <tbody>
                    <tr><td style="padding: 10px 14px; color: #475569;">Booking</td><td style="padding: 10px 14px; font-weight: 700;">{{ $shipment->booking_number }}</td></tr>
                    <tr><td style="padding: 10px 14px; color: #475569;">Kontainer</td><td style="padding: 10px 14px; font-weight: 700;">{{ $shipment->container->container_number }}</td></tr>
                    <tr><td style="padding: 10px 14px; color: #475569;">Rute</td><td style="padding: 10px 14px; font-weight: 700;">{{ $shipment->originPort->city }} - {{ $shipment->destinationPort->city }}</td></tr>
                    <tr><td style="padding: 10px 14px; color: #475569;">Estimasi tiba</td><td style="padding: 10px 14px; font-weight: 700;">{{ $delivery->expected_arrival->translatedFormat('d F Y') }}</td></tr>
                    <tr><td style="padding: 10px 14px; color: #475569;">Keterlambatan</td><td style="padding: 10px 14px; font-weight: 700;">{{ $daysLate }} hari</td></tr>
                    <tr><td style="padding: 10px 14px; color: #475569;">Status terakhir</td><td style="padding: 10px 14px; font-weight: 700;">{{ $shipment->status }}</td></tr>
                </tbody>
            </table>

            <p style="margin: 28px 0; text-align: center;">
                <a href="{{ $trackingUrl }}" style="display: inline-block; border-radius: 8px; background: #0e7490; color: #ffffff; padding: 12px 20px; text-decoration: none; font-weight: 700;">Lihat Tracking Pengiriman</a>
            </p>

            <p style="color: #64748b; font-size: 13px; line-height: 1.6;">Pesan ini dibuat otomatis oleh {{ config('app.name') }} berdasarkan data ETA pengiriman.</p>
            <p style="margin-bottom: 0;">Terima kasih,<br>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
