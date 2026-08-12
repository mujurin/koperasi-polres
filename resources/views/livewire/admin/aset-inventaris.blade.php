<?php

use App\Models\Aset;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $tanggal_perolehan = '';
    public string $nama_barang = '';
    public $foto;
    public string $foto_path = ''; // optional just in case
    public int $jumlah_barang = 1;
    public string $satuan = 'Unit';
    public string $no_register = '';
    public int $harga = 0;
    public string $hargaFormatted = '0';
    public string $keadaan = '';

    public ?Aset $selectedAset = null;
    public string $search = '';
    public bool $showForm = false;
    public bool $isEditing = false;

    public function rules(): array
    {
        return [
            'tanggal_perolehan' => 'required|date',
            'nama_barang' => 'required|string|max:255',
            'foto' => 'nullable|image|max:2048',
            'jumlah_barang' => 'required|integer|min:1',
            'satuan' => 'required|string|max:255',
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
        $this->satuan = 'Unit';
        $this->no_register = '';
        $this->harga = 0;
        $this->hargaFormatted = $this->formatCurrency($this->harga);
        $this->keadaan = '';
        $this->isEditing = false;
    }

    public function showAddForm()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function showEditForm(Aset $aset)
    {
        $this->selectedAset = $aset;
        $this->tanggal_perolehan = $aset->tanggal_perolehan->format('Y-m-d');
        $this->nama_barang = $aset->nama_barang;
        $this->jumlah_barang = $aset->jumlah_barang;
        $this->satuan = $aset->satuan ?? 'Unit';
        $this->no_register = $aset->no_register;
        $this->harga = $aset->harga;
        $this->hargaFormatted = $this->formatCurrency($aset->harga);
        $this->keadaan = $aset->keadaan;
        $this->foto = null;
        $this->isEditing = true;
        $this->showForm = true;
    }

    public function hideForm()
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function saveAset()
    {
        $this->validate();

        $data = [
            'tanggal_perolehan' => $this->tanggal_perolehan,
            'nama_barang' => $this->nama_barang,
            'jumlah_barang' => $this->jumlah_barang,
            'satuan' => $this->satuan,
            'no_register' => $this->no_register,
            'harga' => $this->harga,
            'keadaan' => $this->keadaan,
        ];

        if ($this->foto) {
            $directory = public_path('asets');

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new \RuntimeException('Unable to create upload directory: ' . $directory);
            }

            if (! is_writable($directory)) {
                throw new \RuntimeException('Upload directory is not writable: ' . $directory);
            }

            $filename = Str::random(24) . '.' . $this->foto->getClientOriginalExtension();
            $targetPath = $directory . DIRECTORY_SEPARATOR . $filename;

            try {
                $this->foto->move($directory, $filename);
            } catch (\Throwable $e) {
                if (! copy($this->foto->getRealPath(), $targetPath)) {
                    throw new \RuntimeException('Unable to save uploaded file to public/asets. ' . $e->getMessage());
                }
            }

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
        $this->showForm = false;
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

    public function editAset(Aset $aset)
    {
        $this->selectedAset = $aset;
        $this->tanggal_perolehan = $aset->tanggal_perolehan->format('Y-m-d');
        $this->nama_barang = $aset->nama_barang;
        $this->jumlah_barang = $aset->jumlah_barang;
        $this->satuan = $aset->satuan ?? 'Unit';
        $this->no_register = $aset->no_register;
        $this->harga = $aset->harga;
        $this->keadaan = $aset->keadaan;
        $this->foto = null;
    }

    public function deleteAset(Aset $aset)
    {
        $aset->delete();
        session()->flash('success', 'Data aset berhasil dihapus.');
        $this->resetForm();
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

?><div class="flex h-full w-full flex-col gap-6 p-6">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Aset & Data Inventaris</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Kelola daftar aset dan inventaris koperasi.</p>
    </div>

    @if(session()->has('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-800/40 dark:bg-emerald-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                <div>
                    <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Berhasil</h3>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(!$showForm)
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Daftar Aset</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Filter dan kelola inventaris.</p>
                </div>
                <button wire:click="showAddForm" class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                    Tambah Aset
                </button>
            </div>

            <div class="mt-4">
                <input type="text" wire:model="search" placeholder="Cari nama atau no register" class="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
            </div>

            <div class="mt-4 space-y-4">
                @forelse($assets as $item)
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-4">
                            @if($item->foto_url)
                                <img src="{{ $item->foto_url }}" alt="Foto {{ $item->nama_barang }}" class="h-16 w-16 rounded-2xl object-cover" />
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">No Image</div>
                            @endif
                            <div class="flex-1">
                                <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $item->nama_barang }}</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Register: {{ $item->no_register }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Peroleh: {{ $item->tanggal_perolehan->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div class="text-sm text-zinc-700 dark:text-zinc-300">Jumlah: {{ $item->jumlah_barang }} {{ $item->satuan }}</div>
                            <div class="text-sm text-zinc-700 dark:text-zinc-300">Harga: Rp {{ number_format($item->harga, 0, ',', '.') }}</div>
                            <div class="text-sm text-zinc-700 dark:text-zinc-300">Keadaan: {{ $item->keadaan }}</div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button wire:click="showEditForm({{ $item->id }})" class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">Edit</button>
                            <button wire:click="deleteAset({{ $item->id }})" class="rounded-2xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 transition">Hapus</button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">Belum ada data aset.</div>
                @endforelse
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 max-w-4xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ $isEditing ? 'Edit Aset' : 'Tambah Aset' }}</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Isi detail aset dan simpan.</p>
                </div>
                <button wire:click="hideForm" class="inline-flex items-center justify-center rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition">
                    Kembali ke Daftar
                </button>
            </div>

            <div class="mt-6 grid gap-4">
                <div>
                    <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Hari / Tgl di Peroleh</label>
                    <input type="date" wire:model="tanggal_perolehan" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                    @error('tanggal_perolehan') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Nama Barang</label>
                    <input type="text" wire:model="nama_barang" placeholder="Contoh: Laptop" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                    @error('nama_barang') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Foto Barang (Optional)</label>
                    <input type="file" wire:model="foto" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                    @error('foto') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    @if($foto)
                        <p class="text-xs text-zinc-500 mt-2">File siap diunggah: {{ $foto->getClientOriginalName() }}</p>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Jumlah Barang</label>
                        <input type="number" wire:model="jumlah_barang" min="1" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                        @error('jumlah_barang') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Satuan</label>
                        <input type="text" wire:model="satuan" list="satuan-list" placeholder="Contoh: Unit, Are" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                        <datalist id="satuan-list">
                            <option value="Unit"></option>
                            <option value="Buah"></option>
                            <option value="Set"></option>
                            <option value="Are"></option>
                            <option value="Meter Persegi"></option>
                            <option value="Lantai"></option>
                            <option value="Titik"></option>
                        </datalist>
                        @error('satuan') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">No Register Aset</label>
                        <input type="text" wire:model="no_register" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                        @error('no_register') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Harga Aset / Inventaris</label>
                        <input type="text" wire:model.lazy="hargaFormatted" oninput="let v = this.value.replace(/[^0-9]/g, ''); v = v.replace(/^0+/, ''); this.value = v ? v.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                        @error('harga') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Keadaan Aset</label>
                        <input type="text" wire:model="keadaan" placeholder="Contoh: Baik / Rusak Ringan" class="mt-1 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                        @error('keadaan') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button wire:click.prevent="saveAset" class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition">Simpan Aset</button>
                    <button wire:click.prevent="hideForm" class="inline-flex items-center justify-center rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition">Batal</button>
                </div>
            </div>
        </div>
    @endif
</div>
