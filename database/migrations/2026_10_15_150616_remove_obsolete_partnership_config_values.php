<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('config')->where('config_type', 'json')
                ->whereIn('config_key', ['partnership', 'development'])->get();

            foreach ($rows as $row) {
                $values = json_decode($row->config_value, true, 512, JSON_THROW_ON_ERROR);
                $key = $row->config_key === 'partnership' ? 'reward_point_per_item' : 'frontend_enabled';
                if (! is_array($values) || ! array_key_exists($key, $values)) {
                    continue;
                }

                unset($values[$key]);
                $query = DB::table('config')->where('config_id', $row->config_id);
                if ($row->config_key === 'development' && $values === []) {
                    $query->delete();
                } else {
                    $query->update([
                        'config_value' => json_encode((object) $values, JSON_THROW_ON_ERROR),
                        'config_updated_datetime' => now(),
                    ]);
                }
            }

            DB::table('config')->whereIn('config_key', [
                'partnership.reward_point_per_item', 'development.frontend_enabled',
            ])->delete();
        });
    }

    public function down(): void
    {
        // Retired configuration values are not restored; product points and internal seed markers are preserved.
    }
};
