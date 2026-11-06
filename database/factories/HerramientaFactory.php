<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Herramienta;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Herramienta>
 */
class HerramientaFactory extends Factory
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
            'categoria_id' => fn (array $attributes): int => Categoria::create(['nombre' => fake()->unique()->word(), 'aplica_a' => 'herramienta', 'creado_por' => $attributes['creado_por']])->id,
            'unidad_medida_id' => fn (array $attributes): int => UnidadMedida::create(['nombre_singular' => fake()->unique()->word(), 'nombre_plural' => fake()->unique()->word(), 'permite_decimales' => true, 'creado_por' => $attributes['creado_por']])->id,
            'nombre' => fake()->unique()->words(3, true),
            'tipo_control' => 'cantidad',
            'codigo_interno' => fake()->unique()->bothify('TOOL-####-????'),
            'cantidad_actual' => 10,
            'stock_minimo' => 2,
            'activo' => true,
        ];
    }
}
