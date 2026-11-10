<?php

namespace Database\Factories;

use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnidadMedida>
 */
class UnidadMedidaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_singular' => fake()->unique()->bothify('Unit-####-????'),
            'nombre_plural' => fake()->unique()->bothify('Units-####-????'),
            'permite_decimales' => false,
            'activo' => true,
            'creado_por' => User::factory(),
        ];
    }
}
