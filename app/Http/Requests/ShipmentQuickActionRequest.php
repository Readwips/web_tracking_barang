<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentQuickActionRequest extends FormRequest
{
    protected $errorBag = 'quickAction';

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'operator') ?? false;
    }

    public function rules(): array
    {
        /** @var Shipment $shipment */
        $shipment = $this->route('shipment');
        $action = $this->string('action')->toString();

        return [
            'action' => ['required', Rule::in(['report_delay', 'clear_delay', 'arrived', 'update'])],
            'expected_version' => ['required', 'integer', 'min:0'],
            'actual_arrival' => [
                Rule::excludeIf($action !== 'arrived'),
                Rule::requiredIf($action === 'arrived'),
                'nullable',
                'date',
                'after_or_equal:'.$shipment->departure_date->toDateString(),
                'before_or_equal:today',
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => [
                Rule::requiredIf($action === 'update' && ! $this->filled('location')),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Pilih tindakan yang ingin dilakukan.',
            'actual_arrival.required' => 'Tanggal tiba wajib diisi untuk menandai pengiriman sudah tiba.',
            'actual_arrival.after_or_equal' => 'Tanggal tiba tidak boleh lebih awal daripada tanggal keberangkatan.',
            'actual_arrival.before_or_equal' => 'Tanggal tiba tidak boleh berada di masa depan.',
            'description.required' => 'Isi lokasi atau catatan pembaruan.',
            'description.max' => 'Catatan pembaruan maksimal 1.000 karakter.',
            'expected_version.required' => 'Versi data pengiriman tidak tersedia. Muat ulang halaman dan coba kembali.',
            'expected_version.integer' => 'Versi data pengiriman tidak valid. Muat ulang halaman dan coba kembali.',
        ];
    }
}
