<?php

namespace App\Livewire\Anggota;

use App\Models\Anggota;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';

    public $editingId = null;
    public $editingName = '';
    public $editingStatus = true;

    protected $queryString = [
        'search' => ['except' => '']
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function syncData()
    {
        try {
            $response = Http::timeout(60)->get('https://siapklu.com/api/anggota-aktif');
            
            if ($response->successful()) {
                $data = $response->json('data');
                
                if (is_array($data)) {
                    $count = 0;
                    foreach ($data as $item) {
                        Anggota::updateOrCreate(
                            ['nip' => $item['nip']],
                            [
                                'nmpeg' => $item['nmpeg'],
                                'pangkat' => $item['pangkat'] ?? null,
                                'total_bulan_terpenuhi' => $item['total_bulan_terpenuhi'] ?? 0,
                            ]
                        );
                        $count++;
                    }
                    
                    \Flux::toast("Berhasil menyinkronkan $count data anggota.");
                } else {
                    \Flux::toast('Gagal menyinkronkan: Format data tidak sesuai.', 'error');
                }
            } else {
                \Flux::toast('Gagal terhubung ke API.', 'error');
            }
        } catch (\Exception $e) {
            \Flux::toast('Terjadi kesalahan: ' . $e->getMessage(), 'error');
        }
    }

    public function editAnggota($id)
    {
        $anggota = Anggota::findOrFail($id);
        $this->editingId = $anggota->id;
        $this->editingName = $anggota->nmpeg;
        $this->editingStatus = $anggota->is_active;
        
        \Flux::modal('modal-edit-anggota')->show();
    }

    public function saveAnggota()
    {
        if ($this->editingId) {
            $anggota = Anggota::findOrFail($this->editingId);
            $anggota->update([
                'is_active' => $this->editingStatus
            ]);
            
            \Flux::modal('modal-edit-anggota')->close();
            \Flux::toast('Status anggota berhasil diperbarui.');
            $this->reset(['editingId', 'editingName', 'editingStatus']);
        }
    }

    public function render()
    {
        $anggotas = Anggota::query()
            ->with(['user.pinjaman' => function ($q) {
                $q->where('status', 'disetujui');
            }])
            ->when($this->search, function ($query) {
                $query->where('nip', 'like', '%' . $this->search . '%')
                      ->orWhere('nmpeg', 'like', '%' . $this->search . '%');
            })
            ->orderBy('nmpeg', 'asc')
            ->paginate(20);

        return view('livewire.anggota.index', [
            'anggotas' => $anggotas
        ])->layout('components.layouts.app');
    }
}
