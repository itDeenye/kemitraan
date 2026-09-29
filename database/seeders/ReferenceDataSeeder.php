<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->command?->warn('Reference SQL seeder dilewati karena hanya mendukung MySQL.');

            return;
        }

        foreach (['reference_core.sql', 'reference_areas.sql'] as $fileName) {
            $path = database_path('seeders/sql/'.$fileName);

            if (! File::isReadable($path)) {
                throw new RuntimeException("SQL seed file tidak dapat dibaca: {$path}");
            }

            DB::unprepared(File::get($path));
        }
    }
}
