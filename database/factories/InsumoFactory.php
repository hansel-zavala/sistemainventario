<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Insumo>
 */
class InsumoFactory extends Factory
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
            'categoria_id' => fn (array $attributes): int => Categoria::create(['nombre' => fake()->unique()->word(), 'aplica_a' => 'insumo', 'creado_por' => $attributes['creado_por']])->id,
            'unidad_medida_id' => fn (array $attributes): int => UnidadMedida::create(['nombre_singular' => fake()->unique()->word(), 'nombre_plural' => fake()->unique()->word(), 'permite_decimales' => true, 'creado_por' => $attributes['creado_por']])->id,
            'nombre' => fake()->unique()->words(3, true),
            'cantidad_actual' => 10,
            'stock_minimo' => 2,
            'activo' => true,
        ];
    }
}
