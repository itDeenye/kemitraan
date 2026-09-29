<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, int> */
    private const LEVEL_IDS = [
        'distributor' => 1,
        'agent' => 2,
        'reseller' => 3,
    ];

    public function up(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->unsignedInteger('member_member_level_id')->default(0)->after('member_code')->comment('ID level member');
            $table->index('member_member_level_id');
        });

        Schema::table('member_registration', function (Blueprint $table) {
            $table->unsignedInteger('member_registration_member_level_id')->default(0)->after('member_registration_id')->comment('ID level yang diajukan');
            $table->index('member_registration_member_level_id', 'idx_member_registration_level');
        });

        Schema::table('member_history', function (Blueprint $table) {
            $table->unsignedInteger('member_history_from_level_id')->default(0)->after('member_history_action')->comment('ID level asal');
            $table->unsignedInteger('member_history_to_level_id')->default(0)->after('member_history_from_level_id')->comment('ID level tujuan');
            $table->unsignedInteger('member_history_upline_member_level_id')->default(0)->after('member_history_upline_member_id')->comment('ID level upline saat history dicatat');

            $table->index('member_history_from_level_id', 'idx_member_history_from_level');
            $table->index('member_history_to_level_id', 'idx_member_history_to_level');
            $table->index('member_history_upline_member_level_id', 'idx_member_history_upline_level');
        });

        foreach (self::LEVEL_IDS as $level => $levelId) {
            DB::table('member')->where('member_level', $level)->update([
                'member_member_level_id' => $levelId,
            ]);
            DB::table('member_registration')->where('member_registration_member_level', $level)->update([
                'member_registration_member_level_id' => $levelId,
            ]);
            DB::table('member_history')->where('member_history_from_level', $level)->update([
                'member_history_from_level_id' => $levelId,
            ]);
            DB::table('member_history')->where('member_history_to_level', $level)->update([
                'member_history_to_level_id' => $levelId,
            ]);
            DB::table('member_history')->where('member_history_upline_level', $level)->update([
                'member_history_upline_member_level_id' => $levelId,
            ]);
        }

        DB::table('product')->orderBy('product_id')->chunkById(250, function ($products): void {
            $now = now();
            $prices = [];

            foreach ($products as $product) {
                $prices[] = $this->priceRow($product->product_id, 1, $product->product_distributor_price, $now);
                $prices[] = $this->priceRow($product->product_id, 2, $product->product_agent_price, $now);
                $prices[] = $this->priceRow($product->product_id, 3, $product->product_reseller_price, $now);
            }

            DB::table('member_price')->insert($prices);
        }, 'product_id');

        Schema::table('member', function (Blueprint $table) {
            $table->dropColumn('member_level');
        });
        Schema::table('member_registration', function (Blueprint $table) {
            $table->dropColumn('member_registration_member_level');
        });
        Schema::table('member_history', function (Blueprint $table) {
            $table->dropColumn([
                'member_history_from_level',
                'member_history_to_level',
                'member_history_upline_level',
            ]);
        });
        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn([
                'product_distributor_price',
                'product_agent_price',
                'product_reseller_price',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->enum('member_level', ['reseller', 'agent', 'distributor'])->nullable()->after('member_code');
        });
        Schema::table('member_registration', function (Blueprint $table) {
            $table->enum('member_registration_member_level', ['reseller', 'agent', 'distributor'])->nullable()->after('member_registration_id');
        });
        Schema::table('member_history', function (Blueprint $table) {
            $table->enum('member_history_from_level', ['reseller', 'agent', 'distributor'])->nullable()->after('member_history_action');
            $table->enum('member_history_to_level', ['reseller', 'agent', 'distributor'])->nullable()->after('member_history_from_level');
            $table->enum('member_history_upline_level', ['reseller', 'agent', 'distributor'])->nullable()->after('member_history_upline_member_id');
        });
        Schema::table('product', function (Blueprint $table) {
            $table->unsignedInteger('product_distributor_price')->default(0)->after('product_customer_price');
            $table->unsignedInteger('product_agent_price')->default(0)->after('product_distributor_price');
            $table->unsignedInteger('product_reseller_price')->default(0)->after('product_agent_price');
        });

        foreach (self::LEVEL_IDS as $level => $levelId) {
            DB::table('member')->where('member_member_level_id', $levelId)->update(['member_level' => $level]);
            DB::table('member_registration')->where('member_registration_member_level_id', $levelId)->update([
                'member_registration_member_level' => $level,
            ]);
            DB::table('member_history')->where('member_history_from_level_id', $levelId)->update([
                'member_history_from_level' => $level,
            ]);
            DB::table('member_history')->where('member_history_to_level_id', $levelId)->update([
                'member_history_to_level' => $level,
            ]);
            DB::table('member_history')->where('member_history_upline_member_level_id', $levelId)->update([
                'member_history_upline_level' => $level,
            ]);
        }

        DB::table('product')->orderBy('product_id')->chunkById(250, function ($products): void {
            foreach ($products as $product) {
                $prices = DB::table('member_price')
                    ->where('member_price_product_id', $product->product_id)
                    ->pluck('member_price_value', 'member_price_member_level_id');

                DB::table('product')->where('product_id', $product->product_id)->update([
                    'product_distributor_price' => $prices->get(1, 0),
                    'product_agent_price' => $prices->get(2, 0),
                    'product_reseller_price' => $prices->get(3, 0),
                ]);
            }
        }, 'product_id');

        Schema::table('member_history', function (Blueprint $table) {
            $table->dropIndex('idx_member_history_from_level');
            $table->dropIndex('idx_member_history_to_level');
            $table->dropIndex('idx_member_history_upline_level');
            $table->dropColumn([
                'member_history_from_level_id',
                'member_history_to_level_id',
                'member_history_upline_member_level_id',
            ]);
        });
        Schema::table('member_registration', function (Blueprint $table) {
            $table->dropIndex('idx_member_registration_level');
            $table->dropColumn('member_registration_member_level_id');
        });
        Schema::table('member', function (Blueprint $table) {
            $table->dropIndex(['member_member_level_id']);
            $table->dropColumn('member_member_level_id');
        });
    }

    /** @return array<string, mixed> */
    private function priceRow(int $productId, int $levelId, int $value, mixed $now): array
    {
        return [
            'member_price_product_id' => $productId,
            'member_price_member_level_id' => $levelId,
            'member_price_value' => $value,
            'member_price_created_datetime' => $now,
            'member_price_updated_datetime' => $now,
        ];
    }
};
