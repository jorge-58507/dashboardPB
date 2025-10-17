<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class roleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'Mantenimiento']);
        Role::firstOrCreate(['name' => 'Ventas']);
        Role::firstOrCreate(['name' => 'Housekeeping']);
        Role::firstOrCreate(['name' => 'Lavanderia']);
        Role::firstOrCreate(['name' => 'A&B']);
    }
}
