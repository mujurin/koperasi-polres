<?php

namespace App\Http\Livewire\Admin;

use App\Models\Aset;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $tanggal_perolehan = '';
    public string $nama_barang = '';
    public $foto;
    public int $jumlah_barang = 1;
    public string $no_register = '';
    public int $harga = 0;
    public string $hargaFormatted = '0';
    public string $keadaan = '';

    public ?Aset $selectedAset = null;
    public string $search = '';

    public function rules(): array
    {
        return [
            'tanggal_perolehan' => 'required|date',
            'nama_barang' => 'required|string|max:255',
            'foto' => 'nullable|image|max:2048',
            'jumlah_barang' => 'required|integer|min:1',
            'no_register' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asets', 'no_register')->ignore($this->selectedAset?->id),
            ],
            'harga' => 'required|integer|min:0',
            'keadaan' => 'required|string|max:255',
        ];
    }

    public function mount()
    {
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->selectedAset = null;
        $this->tanggal_perolehan = now()->format('Y-m-d');
        $this->nama_barang = '';
        $this->foto = null;
        $this->jumlah_barang = 1;
        $this->no_register = '';
        $this->harga = 0;
        $this->hargaFormatted = $this->formatCurrency($this->harga);
        $this->keadaan = '';
    }

    public function saveAset()
    {
        $this->validate();

        $data = [
            'tanggal_perolehan' => $this->tanggal_perolehan,
            'nama_barang' => $this->nama_barang,
            'jumlah_barang' => $this->jumlah_barang,
            'no_register' => $this->no_register,
            'harga' => $this->harga,
            'keadaan' => $this->keadaan,
        ];

        if ($this->foto) {
            $directory = public_path('asets');
            if (! file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = Str::random(24) . '.' . $this->foto->getClientOriginalExtension();
            $this->foto->move($directory, $filename);
            $data['foto_path'] = 'asets/' . $filename;
        }

        if ($this->selectedAset) {
            $this->selectedAset->update($data);
            session()->flash('success', 'Data aset berhasil diperbarui.');
        } else {
            Aset::create($data);
            session()->flash('success', 'Data aset berhasil disimpan.');
        }

        $this->resetForm();
    }

    public function editAset(Aset $aset)
    {
        $this->selectedAset = $aset;
        $this->tanggal_perolehan = $aset->tanggal_perolehan->format('Y-m-d');
        $this->nama_barang = $aset->nama_barang;
        $this->jumlah_barang = $aset->jumlah_barang;
        $this->no_register = $aset->no_register;
        $this->harga = $aset->harga;
        $this->hargaFormatted = $this->formatCurrency($aset->harga);
        $this->keadaan = $aset->keadaan;
        $this->foto = null;
    }

    public function deleteAset(Aset $aset)
    {
        $aset->delete();
        session()->flash('success', 'Data aset berhasil dihapus.');
        $this->resetForm();
    }

    public function updatedHargaFormatted($value)
    {
        $numeric = preg_replace('/[^0-9]/', '', $value);
        $this->harga = $numeric !== '' ? (int) $numeric : 0;
        $this->hargaFormatted = $this->formatCurrency($this->harga);
    }

    protected function formatCurrency(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    public function getAsetsProperty()
    {
        $query = Aset::query()->orderByDesc('created_at');

        if (!empty($this->search)) {
            $query->where('nama_barang', 'like', '%' . $this->search . '%')
                  ->orWhere('no_register', 'like', '%' . $this->search . '%');
        }

        return $query->get();
    }

    public function with(): array
    {
        return [
            'assets' => $this->asets,
        ];
    }
};
