<?php

namespace App\Http\Controllers;

use App\Models\CargoType;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Port;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function index(string $resource)
    {
        $definition = $this->definition($resource);
        $model = $definition['model'];

        $items = $model::query()
            ->with($definition['with'] ?? [])
            ->latest()
            ->paginate(10);

        return view('master.index', compact('resource', 'definition', 'items'));
    }

    public function create(string $resource)
    {
        $definition = $this->definition($resource);
        $options = $this->options();

        return view('master.form', compact('resource', 'definition', 'options'));
    }

    public function store(Request $request, string $resource)
    {
        $definition = $this->definition($resource);
        $model = $definition['model'];

        $model::create($request->validate($this->rules($resource)));

        return redirect()->route('master.index', $resource)->with('status', $definition['label'].' berhasil ditambahkan.');
    }

    public function edit(string $resource, int $id)
    {
        $definition = $this->definition($resource);
        $model = $definition['model'];
        $item = $model::findOrFail($id);
        $options = $this->options();

        return view('master.form', compact('resource', 'definition', 'item', 'options'));
    }

    public function update(Request $request, string $resource, int $id)
    {
        $definition = $this->definition($resource);
        $model = $definition['model'];
        $item = $model::findOrFail($id);

        $item->update($request->validate($this->rules($resource, $id)));

        return redirect()->route('master.index', $resource)->with('status', $definition['label'].' berhasil diperbarui.');
    }

    public function destroy(string $resource, int $id)
    {
        $definition = $this->definition($resource);
        $model = $definition['model'];

        $model::findOrFail($id)->delete();

        return redirect()->route('master.index', $resource)->with('status', $definition['label'].' berhasil dihapus.');
    }

    private function definition(string $resource): array
    {
        $definitions = [
            'customers' => [
                'label' => 'Pelanggan',
                'model' => Customer::class,
                'with' => ['user'],
                'fields' => [
                    'name' => ['label' => 'Nama', 'type' => 'text'],
                    'phone' => ['label' => 'Telepon', 'type' => 'text'],
                    'address' => ['label' => 'Alamat', 'type' => 'textarea'],
                    'user_id' => ['label' => 'Akun login pelanggan', 'type' => 'select', 'options' => 'customerUsers', 'display' => fn (Customer $item) => $item->user?->email ?? '-'],
                ],
            ],
            'vessels' => [
                'label' => 'Kapal',
                'model' => Vessel::class,
                'fields' => [
                    'name' => ['label' => 'Nama kapal', 'type' => 'text'],
                    'vessel_code' => ['label' => 'Kode kapal', 'type' => 'text'],
                ],
            ],
            'ports' => [
                'label' => 'Pelabuhan',
                'model' => Port::class,
                'fields' => [
                    'name' => ['label' => 'Nama pelabuhan', 'type' => 'text'],
                    'city' => ['label' => 'Kota', 'type' => 'text'],
                ],
            ],
            'containers' => [
                'label' => 'Kontainer',
                'model' => Container::class,
                'fields' => [
                    'container_number' => ['label' => 'Nomor kontainer', 'type' => 'text'],
                    'container_type' => ['label' => 'Tipe kontainer', 'type' => 'text'],
                    'status' => ['label' => 'Status', 'type' => 'text'],
                ],
            ],
            'cargo-types' => [
                'label' => 'Jenis barang',
                'model' => CargoType::class,
                'fields' => [
                    'name' => ['label' => 'Nama jenis barang', 'type' => 'text'],
                    'description' => ['label' => 'Deskripsi', 'type' => 'textarea'],
                ],
            ],
            'schedules' => [
                'label' => 'Jadwal keberangkatan',
                'model' => Schedule::class,
                'with' => ['vessel', 'originPort', 'destinationPort'],
                'fields' => [
                    'vessel_id' => ['label' => 'Kapal', 'type' => 'select', 'options' => 'vessels', 'display' => fn (Schedule $item) => $item->vessel?->name ?? '-'],
                    'origin_port_id' => ['label' => 'Pelabuhan asal', 'type' => 'select', 'options' => 'ports', 'display' => fn (Schedule $item) => $item->originPort?->city ?? '-'],
                    'destination_port_id' => ['label' => 'Pelabuhan tujuan', 'type' => 'select', 'options' => 'ports', 'display' => fn (Schedule $item) => $item->destinationPort?->city ?? '-'],
                    'departure_date' => ['label' => 'Tanggal keberangkatan', 'type' => 'date'],
                    'estimated_arrival' => ['label' => 'Estimasi tiba', 'type' => 'date'],
                    'status' => ['label' => 'Status', 'type' => 'text'],
                ],
            ],
        ];

        abort_unless(isset($definitions[$resource]), 404);

        return $definitions[$resource];
    }

    private function rules(string $resource, ?int $id = null): array
    {
        return match ($resource) {
            'customers' => [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string'],
                'user_id' => ['nullable', Rule::exists('users', 'id')],
            ],
            'vessels' => [
                'name' => ['required', 'string', 'max:255'],
                'vessel_code' => ['required', 'string', 'max:50', Rule::unique('vessels', 'vessel_code')->ignore($id)],
            ],
            'ports' => [
                'name' => ['required', 'string', 'max:255'],
                'city' => ['required', 'string', 'max:255'],
            ],
            'containers' => [
                'container_number' => ['required', 'string', 'max:50', Rule::unique('containers', 'container_number')->ignore($id)],
                'container_type' => ['required', 'string', 'max:100'],
                'status' => ['required', 'string', 'max:100'],
            ],
            'cargo-types' => [
                'name' => ['required', 'string', 'max:255', Rule::unique('cargo_types', 'name')->ignore($id)],
                'description' => ['nullable', 'string'],
            ],
            'schedules' => [
                'vessel_id' => ['required', Rule::exists('vessels', 'id')],
                'origin_port_id' => ['required', Rule::exists('ports', 'id'), 'different:destination_port_id'],
                'destination_port_id' => ['required', Rule::exists('ports', 'id')],
                'departure_date' => ['required', 'date'],
                'estimated_arrival' => ['required', 'date', 'after_or_equal:departure_date'],
                'status' => ['required', 'string', 'max:100'],
            ],
            default => abort(404),
        };
    }

    private function options(): array
    {
        return [
            'customerUsers' => User::where('role', 'customer')->orderBy('name')->pluck('email', 'id'),
            'vessels' => Vessel::orderBy('name')->pluck('name', 'id'),
            'ports' => Port::orderBy('city')->get()->mapWithKeys(fn (Port $port) => [$port->id => $port->city.' - '.$port->name]),
        ];
    }
}
