<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Marca;
use App\Models\TipoEquipo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipo>
 */
class EquipoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creado_por' => User::factory(),
            'categoria_id' => fn (array $attributes): int => Categoria::create(['nombre' => fake()->unique()->word(), 'aplica_a' => 'equipo', 'creado_por' => $attributes['creado_por']])->id,
            'tipo_equipo_id' => fn (array $attributes): int => TipoEquipo::create(['nombre' => fake()->unique()->word(), 'creado_por' => $attributes['creado_por']])->id,
            'marca_id' => fn (array $attributes): int => Marca::create(['nombre' => fake()->unique()->word(), 'creado_por' => $attributes['creado_por']])->id,
            'departamento_id' => fn (array $attributes): int => Departamento::create(['nombre' => fake()->unique()->word(), 'creado_por' => $attributes['creado_por']])->id,
            'numero_inventario' => fake()->unique()->bothify('INV-####-????'),
            'numero_serie' => fake()->unique()->uuid(),
            'modelo' => 'Model '.fake()->word(),
            'activo' => true,
        ];
    }
}
