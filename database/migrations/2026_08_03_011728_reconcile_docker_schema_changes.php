<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignMemberNetworkSwitch();
        $this->alignMemberRegistration();
        $this->alignPaymentTransferStatus();
        $this->alignSpreadPayment();
    }

    public function down(): void
    {
        $this->restoreSpreadPayment();
        $this->restorePaymentTransferStatus();
        $this->restoreMemberRegistration();
        $this->restoreMemberNetworkTransfer();
    }

    private function alignMemberNetworkSwitch(): void
    {
        if (Schema::hasTable('member_network_transfer') && ! Schema::hasTable('member_network_switch')) {
            Schema::rename('member_network_transfer', 'member_network_switch');
        }

        if (! Schema::hasTable('member_network_switch')) {
            return;
        }

        $columns = [
            'member_network_transfer_id' => 'network_switch_transfer_id',
            'member_network_transfer_upgrade_qualified_id' => 'network_switch_qualified_id',
            'member_network_transfer_member_id' => 'network_switch_member_id',
            'member_network_transfer_from_parent_member_id' => 'network_switch_from_parent_member_id',
            'member_network_transfer_to_parent_member_id' => 'network_switch_to_parent_member_id',
            'member_network_transfer_from_level_id' => 'network_switch_from_level_id',
            'member_network_transfer_to_level_id' => 'network_switch_to_level_id',
            'member_network_transfer_type' => 'network_switch_type',
            'member_network_transfer_status' => 'network_switch_status',
            'member_network_transfer_admin_id' => 'network_switch_admin_id',
            'member_network_transfer_approved_datetime' => 'network_switch_approved_datetime',
            'member_network_transfer_effective_date' => 'network_switch_effective_date',
            'member_network_transfer_applied_datetime' => 'network_switch_applied_datetime',
            'member_network_transfer_note' => 'network_switch_transfer_note',
            'member_network_transfer_created_datetime' => 'network_switch_created_datetime',
        ];

        foreach ($columns as $from => $to) {
            if (Schema::hasColumn('member_network_switch', $from) && ! Schema::hasColumn('member_network_switch', $to)) {
                Schema::table('member_network_switch', function (Blueprint $table) use ($from, $to): void {
                    $table->renameColumn($from, $to);
                });
            }
        }
    }

    private function alignMemberRegistration(): void
    {
        if (! Schema::hasTable('member_registration')) {
            return;
        }

        if (Schema::hasColumn('member_registration', 'member_registration_parent_member_id')
            && ! Schema::hasColumn('member_registration', 'member_registration_upline_member_id')) {
            Schema::table('member_registration', function (Blueprint $table): void {
                $table->renameColumn(
                    'member_registration_parent_member_id',
                    'member_registration_upline_member_id'
                );
            });
        }

        if (! Schema::hasColumn('member_registration', 'member_registration_member_id')) {
            Schema::table('member_registration', function (Blueprint $table): void {
                $table->unsignedInteger('member_registration_member_id')
                    ->default(0)
                    ->after('member_registration_upline_member_id');
            });
        }
    }

    private function alignPaymentTransferStatus(): void
    {
        if (! Schema::hasTable('trx_payment_transfer')) {
            return;
        }

        Schema::table('trx_payment_transfer', function (Blueprint $table): void {
            $table->enum('payment_transfer_approval_status', [
                'pending',
                'submitted',
                'approved',
                'rejected',
            ])->default('pending')->comment('Status verifikasi')->change();
        });
    }

    private function alignSpreadPayment(): void
    {
        if (! Schema::hasTable('trx_spread_payment')) {
            return;
        }

        foreach (['trx_spread_payment_trx_status_index', 'trx_spread_payment_recipient_index'] as $indexName) {
            if (Schema::hasIndex('trx_spread_payment', $indexName)) {
                Schema::table('trx_spread_payment', function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }

        $renamedColumns = [
            'trx_spread_payment_recipient_id' => 'trx_spread_payment_upline_id',
            'trx_spread_payment_verified_by' => 'trx_spread_payment_approved_by',
            'trx_spread_payment_verified_datetime' => 'trx_spread_payment_approved_datetime',
        ];

        foreach ($renamedColumns as $from => $to) {
            if (Schema::hasColumn('trx_spread_payment', $from)
                && ! Schema::hasColumn('trx_spread_payment', $to)) {
                Schema::table('trx_spread_payment', function (Blueprint $table) use ($from, $to): void {
                    $table->renameColumn($from, $to);
                });
            }
        }

        if (Schema::hasColumn('trx_spread_payment', 'trx_spread_payment_recipient_type')) {
            Schema::table('trx_spread_payment', function (Blueprint $table): void {
                $table->dropColumn('trx_spread_payment_recipient_type');
            });
        }

        Schema::table('trx_spread_payment', function (Blueprint $table): void {
            $table->unsignedInteger('trx_spread_payment_upline_id')
                ->default(0)
                ->comment('ID Distributor Utama')
                ->change();
        });

        Schema::table('trx_spread_payment', function (Blueprint $table): void {
            if (! Schema::hasColumn('trx_spread_payment', 'trx_spread_payment_member_id')) {
                $table->unsignedInteger('trx_spread_payment_member_id')
                    ->default(0)
                    ->after('trx_spread_payment_upline_id')
                    ->comment('ID Distributor Downline (Buyer)');
            }
            if (! Schema::hasColumn('trx_spread_payment', 'trx_spread_payment_paid_by')) {
                $table->unsignedInteger('trx_spread_payment_paid_by')
                    ->default(0)
                    ->after('trx_spread_payment_approved_datetime');
            }
            if (! Schema::hasColumn('trx_spread_payment', 'trx_spread_payment_paid_datetime')) {
                $table->dateTime('trx_spread_payment_paid_datetime')
                    ->nullable()
                    ->after('trx_spread_payment_paid_by');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE trx_spread_payment AS spread
                INNER JOIN trx ON trx.trx_id = spread.trx_spread_payment_trx_id
                SET spread.trx_spread_payment_member_id = trx.trx_buyer_id
                WHERE spread.trx_spread_payment_member_id = 0
                SQL);
        }

        Schema::table('trx_spread_payment', function (Blueprint $table): void {
            $table->enum('trx_spread_payment_status', [
                'pending',
                'submitted',
                'approved',
                'rejected',
                'paid',
            ])->default('pending')->comment('Status pembayaran bagian penerima')->change();
        });
    }

    private function restoreSpreadPayment(): void
    {
        if (! Schema::hasTable('trx_spread_payment')) {
            return;
        }

        DB::table('trx_spread_payment')
            ->where('trx_spread_payment_status', 'paid')
            ->update(['trx_spread_payment_status' => 'approved']);

        Schema::table('trx_spread_payment', function (Blueprint $table): void {
            $table->enum('trx_spread_payment_status', [
                'pending',
                'submitted',
                'approved',
                'rejected',
            ])->default('pending')->comment('Status pembayaran bagian penerima')->change();

            if (! Schema::hasColumn('trx_spread_payment', 'trx_spread_payment_recipient_type')) {
                $table->enum('trx_spread_payment_recipient_type', ['pbf', 'partnership'])
                    ->default('partnership')
                    ->after('trx_spread_payment_trx_id')
                    ->comment('Jenis penerima pembagian pembayaran');
            }
        });

        $renamedColumns = [
            'trx_spread_payment_upline_id' => 'trx_spread_payment_recipient_id',
            'trx_spread_payment_approved_by' => 'trx_spread_payment_verified_by',
            'trx_spread_payment_approved_datetime' => 'trx_spread_payment_verified_datetime',
        ];

        foreach ($renamedColumns as $from => $to) {
            if (Schema::hasColumn('trx_spread_payment', $from)
                && ! Schema::hasColumn('trx_spread_payment', $to)) {
                Schema::table('trx_spread_payment', function (Blueprint $table) use ($from, $to): void {
                    $table->renameColumn($from, $to);
                });
            }
        }

        Schema::table('trx_spread_payment', function (Blueprint $table): void {
            foreach ([
                'trx_spread_payment_member_id',
                'trx_spread_payment_paid_by',
                'trx_spread_payment_paid_datetime',
            ] as $column) {
                if (Schema::hasColumn('trx_spread_payment', $column)) {
                    $table->dropColumn($column);
                }
            }

            $table->index(
                ['trx_spread_payment_trx_id', 'trx_spread_payment_status'],
                'trx_spread_payment_trx_status_index'
            );
            $table->index(
                ['trx_spread_payment_recipient_type', 'trx_spread_payment_recipient_id'],
                'trx_spread_payment_recipient_index'
            );
        });
    }

    private function restorePaymentTransferStatus(): void
    {
        if (! Schema::hasTable('trx_payment_transfer')) {
            return;
        }

        DB::table('trx_payment_transfer')
            ->where('payment_transfer_approval_status', 'submitted')
            ->update(['payment_transfer_approval_status' => 'pending']);

        Schema::table('trx_payment_transfer', function (Blueprint $table): void {
            $table->enum('payment_transfer_approval_status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending')->comment('Status verifikasi')->change();
        });
    }

    private function restoreMemberRegistration(): void
    {
        if (! Schema::hasTable('member_registration')) {
            return;
        }

        if (Schema::hasColumn('member_registration', 'member_registration_member_id')) {
            Schema::table('member_registration', function (Blueprint $table): void {
                $table->dropColumn('member_registration_member_id');
            });
        }

        if (Schema::hasColumn('member_registration', 'member_registration_upline_member_id')
            && ! Schema::hasColumn('member_registration', 'member_registration_parent_member_id')) {
            Schema::table('member_registration', function (Blueprint $table): void {
                $table->renameColumn(
                    'member_registration_upline_member_id',
                    'member_registration_parent_member_id'
                );
            });
        }
    }

    private function restoreMemberNetworkTransfer(): void
    {
        if (! Schema::hasTable('member_network_switch')) {
            return;
        }

        $columns = [
            'network_switch_transfer_id' => 'member_network_transfer_id',
            'network_switch_qualified_id' => 'member_network_transfer_upgrade_qualified_id',
            'network_switch_member_id' => 'member_network_transfer_member_id',
            'network_switch_from_parent_member_id' => 'member_network_transfer_from_parent_member_id',
            'network_switch_to_parent_member_id' => 'member_network_transfer_to_parent_member_id',
            'network_switch_from_level_id' => 'member_network_transfer_from_level_id',
            'network_switch_to_level_id' => 'member_network_transfer_to_level_id',
            'network_switch_type' => 'member_network_transfer_type',
            'network_switch_status' => 'member_network_transfer_status',
            'network_switch_admin_id' => 'member_network_transfer_admin_id',
            'network_switch_approved_datetime' => 'member_network_transfer_approved_datetime',
            'network_switch_effective_date' => 'member_network_transfer_effective_date',
            'network_switch_applied_datetime' => 'member_network_transfer_applied_datetime',
            'network_switch_transfer_note' => 'member_network_transfer_note',
            'network_switch_created_datetime' => 'member_network_transfer_created_datetime',
        ];

        foreach ($columns as $from => $to) {
            if (Schema::hasColumn('member_network_switch', $from)
                && ! Schema::hasColumn('member_network_switch', $to)) {
                Schema::table('member_network_switch', function (Blueprint $table) use ($from, $to): void {
                    $table->renameColumn($from, $to);
                });
            }
        }

        if (! Schema::hasTable('member_network_transfer')) {
            Schema::rename('member_network_switch', 'member_network_transfer');
        }
    }
};
