<?php

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Departamento>
 */
class DepartamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->bothify('Department-####-????'),
            'activo' => true,
            'creado_por' => User::factory(),
        ];
    }
}
