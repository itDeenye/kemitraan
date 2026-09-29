<?php

namespace Database\Seeders;

use App\Models\SiteAdministrator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class InitialAdministratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = config('initial_data.administrator.password');

        if (blank($password)) {
            $this->command?->warn('Administrator awal tidak dibuat. Isi DNY_INITIAL_ADMIN_PASSWORD terlebih dahulu.');

            return;
        }

        if (! $this->isStrongPassword($password)) {
            throw new InvalidArgumentException(
                'DNY_INITIAL_ADMIN_PASSWORD minimal 8 karakter dan wajib mengandung huruf besar, huruf kecil, serta angka.',
            );
        }

        $administrator = SiteAdministrator::query()->find(1) ?? new SiteAdministrator;
        $administrator->administrator_id = 1;
        $administrator->fill([
            'administrator_administrator_group_id' => 1,
            'administrator_username' => config('initial_data.administrator.username'),
            'administrator_name' => config('initial_data.administrator.name'),
            'administrator_email' => config('initial_data.administrator.email'),
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);

        if (! $administrator->exists) {
            $administrator->administrator_password = Hash::make($password);
        }

        $administrator->save();
    }

    private function isStrongPassword(string $password): bool
    {
        return mb_strlen($password) >= 8
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
    }
}
