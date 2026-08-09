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
@if($isEtaOverdue)
Keterlambatan: {{ $daysLate }} hari
@else
Kondisi: Dilaporkan terlambat
@endif
Status terakhir: {!! $plain['status'] !!}

Lihat tracking: {!! $trackingUrl !!}

Pesan ini dibuat otomatis oleh {!! $plain['appName'] !!} berdasarkan status operasional dan data ETA pengiriman.

Terima kasih,
{!! $plain['appName'] !!}
