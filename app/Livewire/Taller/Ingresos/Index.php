<?php

namespace App\Livewire\Taller\Ingresos;

use App\Models\IngresoTaller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $buscar = '';

    public function boot(): void
    {
        Gate::authorize('acceder-taller');
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $buscar = mb_substr(trim($this->buscar), 0, 100);
        $ingresos = IngresoTaller::query()
            ->when($buscar !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereLike('folio', '%'.$buscar.'%')
                    ->orWhereLike('numero_inventario', '%'.$buscar.'%')
                    ->orWhereRelation('equipoTaller.equipo', fn (Builder $equipo) => $equipo->whereLike('numero_inventario', '%'.$buscar.'%')),
            ))
            ->orderByDesc('id')
            ->paginate(12);

        return view('livewire.taller.ingresos.index', compact('ingresos'));
    }
}
