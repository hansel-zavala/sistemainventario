<?php

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\EquipoTaller;
use App\Models\IngresoTaller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<IngresoTaller> */
class IngresoTallerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'borrador_token' => (string) Str::uuid(),
            'tecnico_id' => User::factory(),
            'tecnico_nombre' => fn (array $atributos): string => User::query()->findOrFail($atributos['tecnico_id'])->name,
            'fecha_ingreso' => now()->startOfMinute(),
        ];
    }

    public function emitido(): static
    {
        return $this->state([
            'departamento_id' => Departamento::factory(),
            'departamento_nombre' => fn (array $atributos): string => Departamento::query()->findOrFail($atributos['departamento_id'])->nombre,
            'equipo_taller_id' => EquipoTaller::factory(),
            'entregado_por' => fake()->name(),
            'tipo_equipo' => 'Computadora portátil',
            'condicion_equipo' => 'Usado',
            'estado_fisico' => 'Bueno',
            'motivo' => 'Mantenimiento preventivo',
            'autorizado_por' => fn (array $atributos): string => $atributos['entregado_por'],
            'autoriza_respaldo' => false,
        ])->afterCreating(function (IngresoTaller $ingreso): void {
            $ingreso->folio = 'T-'.str_pad((string) $ingreso->id, 4, '0', STR_PAD_LEFT);
            $ingreso->revision = 1;
            $ingreso->version = 1;
            $ingreso->save();
        });
    }
}
