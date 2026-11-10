<?php

namespace Database\Factories;

use App\Models\TipoEquipo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoEquipo>
 */
class TipoEquipoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->bothify('Equipment type-####-????'),
            'activo' => true,
            'creado_por' => User::factory(),
        ];
    }
}
