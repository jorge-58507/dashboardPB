<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User; 
use Spatie\Permission\Models\Role;
class userSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
$adminUser = User::firstOrCreate(
            ['email' => 'admin@mail.com'],
            ['name' => 'Administrador', 'password' => bcrypt('admin'), 'email_verified_at' => now()]
        );
        $adminUser->assignRole('Admin');

        // Usuario Mantenimiento
        $mantenimientoUser = User::firstOrCreate(
            ['email' => 'mantenimiento@mail.com'],
            ['name' => 'Usuario Mantenimiento', 'password' => bcrypt('mantenimiento'), 'email_verified_at' => now()]
        );
        $mantenimientoUser->assignRole('Mantenimiento');

        // Usuario Ventas (que también accederá a "Llamadas")
        $ventasUser = User::firstOrCreate(
            ['email' => 'ventas@mail.com'],
            ['name' => 'Usuario Ventas', 'password' => bcrypt('ventas'), 'email_verified_at' => now()]
        );
        $ventasUser->assignRole('Ventas');

        // Usuario Housekeeping (accede a Inventario HK y Lavandería)
        $hkUser = User::firstOrCreate(
            ['email' => 'housekeeping@mail.com'],
            ['name' => 'Usuario Housekeeping', 'password' => bcrypt('housekeeping'), 'email_verified_at' => now()]
        );
        $hkUser->assignRole('Housekeeping');
        $hkUser->assignRole('Lavanderia'); // Si Housekeeping también tiene acceso a Lavandería

        // Usuario Lavandería (solo acceso a Lavandería)
        $lavanderiaUser = User::firstOrCreate(
            ['email' => 'lavanderia@mail.com'],
            ['name' => 'Usuario Lavanderia', 'password' => bcrypt('lavanderia'), 'email_verified_at' => now()]
        );
        $lavanderiaUser->assignRole('Lavanderia');

        // Usuario A&B
        $abUser = User::firstOrCreate(
            ['email' => 'restaurante@mail.com'],
            ['name' => 'Usuario A&B', 'password' => bcrypt('restaurante'), 'email_verified_at' => now()]
        );
        $abUser->assignRole('A&B');
    }
}
