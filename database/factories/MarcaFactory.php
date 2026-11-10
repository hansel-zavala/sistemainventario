<?php

namespace Database\Factories;

use App\Models\Marca;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Marca>
 */
class MarcaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->bothify('Brand-####-????'),
            'activo' => true,
            'creado_por' => User::factory(),
        ];
    }
}
