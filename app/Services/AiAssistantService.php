<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AiAssistantService
{
    public function customerNotice(string $context): string
    {
        $prompt = "Buat pesan informasi resmi dan empatik untuk pelanggan logistik dalam Bahasa Indonesia berdasarkan konteks berikut:\n\n{$context}";

        return $this->complete($prompt) ?? $this->fallbackCustomerNotice($context);
    }

    /**
     * @param  array{active:int, completed:int, delayed:int, stale:int, top_route:?string}  $stats
     */
    public function operationalSummary(array $stats): string
    {
        $prompt = 'Ringkas kondisi operasional pengiriman dalam Bahasa Indonesia dengan 2-3 kalimat. Data: '
            .json_encode($stats, JSON_THROW_ON_ERROR);

        return $this->complete($prompt) ?? sprintf(
            'Terdapat %d pengiriman aktif. %d pengiriman sudah selesai, %d pengiriman terlambat, dan %d pengiriman belum diperbarui selama lebih dari 24 jam. Rute tersibuk saat ini adalah %s.',
            $stats['active'],
            $stats['completed'],
            $stats['delayed'],
            $stats['stale'],
            $stats['top_route'] ?: 'belum tersedia'
        );
    }

    private function complete(string $prompt): ?string
    {
        $key = config('services.openai.key');

        if (! $key) {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4.1-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'Anda adalah asisten operasional perusahaan pelayaran dan logistik kontainer.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.35,
                ]);

            if (! $response->successful()) {
                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (Throwable) {
            return null;
        }
    }

    private function fallbackCustomerNotice(string $context): string
    {
        preg_match('/[A-Z]+-[A-Z]{2}-\d+/', $context, $containerMatches);
        $containerNumber = $containerMatches[0] ?? 'pengiriman terkait';
        $delayText = Str::contains(Str::lower($context), ['dua hari', '2 hari'])
            ? 'mundur dua hari dari jadwal sebelumnya'
            : 'mengalami penyesuaian dari jadwal sebelumnya';
        $reason = Str::contains(Str::lower($context), 'cuaca')
            ? 'akibat kondisi cuaca buruk'
            : 'karena kondisi operasional di lapangan';

        return <<<TEXT
Yth. Pelanggan,

Kami informasikan bahwa pengiriman dengan nomor kontainer {$containerNumber} mengalami penyesuaian jadwal {$reason}.

Estimasi kedatangan terbaru {$delayText}. Kami akan memberikan pembaruan apabila terdapat perkembangan lebih lanjut.
TEXT;
    }
}
