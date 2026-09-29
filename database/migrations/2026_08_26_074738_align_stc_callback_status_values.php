<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $callbackStatuses = [
        'pending',
        'processed_packages',
        'shipped_packages',
        'cancelled_packages',
        'canceled_packages',
        'finished_packages',
        'returned_packages',
        'problem_packages',
        'return_finished_package',
        'completed',
    ];

    /** @var list<string> */
    private array $legacyStatuses = [
        'pending',
        'processed_packages',
        'shipped_packages',
        'cancelled_packages',
        'finished_packages',
        'returned_packages',
        'completed',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->alterStatusColumn(
            'shipping_courier_express_status',
            'shipping_courier_express_status_value',
            $this->callbackStatuses,
            'Nilai status pengiriman express dari callback STC',
        );
        $this->alterStatusColumn(
            'shipping_courier_instant_status',
            'shipping_courier_instant_status_value',
            $this->callbackStatuses,
            'Nilai status pengiriman instan dari callback STC',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $newStatuses = ['canceled_packages', 'problem_packages', 'return_finished_package'];
        $hasNewExpressStatus = DB::table('shipping_courier_express_status')
            ->whereIn('shipping_courier_express_status_value', $newStatuses)
            ->exists();
        $hasNewInstantStatus = DB::table('shipping_courier_instant_status')
            ->whereIn('shipping_courier_instant_status_value', $newStatuses)
            ->exists();

        if ($hasNewExpressStatus || $hasNewInstantStatus) {
            throw new RuntimeException(
                'Migrasi status callback STC tidak dapat dibatalkan karena status baru sudah digunakan.'
            );
        }

        $this->alterStatusColumn(
            'shipping_courier_express_status',
            'shipping_courier_express_status_value',
            $this->legacyStatuses,
            'Nilai status pengiriman',
        );
        $this->alterStatusColumn(
            'shipping_courier_instant_status',
            'shipping_courier_instant_status_value',
            $this->legacyStatuses,
            'Nilai status pengiriman',
        );
    }

    /** @param list<string> $statuses */
    private function alterStatusColumn(
        string $table,
        string $column,
        array $statuses,
        string $comment,
    ): void {
        $enum = collect($statuses)
            ->map(fn (string $status): string => "'{$status}'")
            ->implode(',');

        DB::statement(
            "ALTER TABLE `{$table}` MODIFY `{$column}` ENUM({$enum}) "
            ."NOT NULL DEFAULT 'pending' COMMENT '{$comment}'"
        );
    }
};
