<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            [
                'email' => 'admin@municipalidad.local',
            ],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin12345'),
                'activo' => true,
            ]
        );

        if (! $admin->hasRole('Administrador')) {
            $admin->assignRole('Administrador');
        }
    }
}
