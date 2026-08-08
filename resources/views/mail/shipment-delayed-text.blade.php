Pemberitahuan Keterlambatan Pengiriman

@if($delivery->audience === 'customer')
Yth. {!! $plain['customerName'] !!},
@else
Yth. Tim Operasional,
@endif

{!! $plain['message'] !!}

Booking: {!! $plain['bookingNumber'] !!}
Kontainer: {!! $plain['containerNumber'] !!}
Rute: {!! $plain['origin'] !!} - {!! $plain['destination'] !!}
Estimasi tiba: {!! $plain['expectedArrival'] !!}
Keterlambatan: {{ $daysLate }} hari
Status terakhir: {!! $plain['status'] !!}

Lihat tracking: {!! $trackingUrl !!}

Pesan ini dibuat otomatis oleh {!! $plain['appName'] !!} berdasarkan data ETA pengiriman.

Terima kasih,
{!! $plain['appName'] !!}
