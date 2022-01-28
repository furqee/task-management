<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Status::insert([
            ['status' => 'Todo'],
            ['status' => 'In Progress'],
            ['status' => 'Completed']
        ]);
    }
}
