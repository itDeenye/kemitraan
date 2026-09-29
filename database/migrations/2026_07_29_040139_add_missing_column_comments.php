<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateMediaComments(true);
        $this->updateAuthenticationSecurityComments(true);
        $this->updateRewardComments(true);
        $this->updateSpreadPaymentComments(true);
    }

    public function down(): void
    {
        $this->updateMediaComments(false);
        $this->updateAuthenticationSecurityComments(false);
        $this->updateRewardComments(false);
        $this->updateSpreadPaymentComments(false);
    }

    private function updateMediaComments(bool $documented): void
    {
        $comment = static fn (string $value): string => $documented ? $value : '';

        Schema::table('media', function (Blueprint $table) use ($comment) {
            $table->bigIncrements('media_id')->comment($comment('ID media'))->change();
            $table->uuid('media_uuid')->comment($comment('UUID unik media'))->change();
            $table->string('media_uploader_type', 150)->comment($comment('Tipe pemilik atau pengunggah media'))->change();
            $table->unsignedBigInteger('media_uploader_id')->comment($comment('ID pemilik atau pengunggah media'))->change();
            $table->string('media_collection', 50)->comment($comment('Kelompok penggunaan media'))->change();
            $table->string('media_original_name')->comment($comment('Nama asli file saat diunggah'))->change();
            $table->string('media_original_mime_type', 100)->comment($comment('MIME type file asli'))->change();
            $table->unsignedBigInteger('media_original_size')->comment($comment('Ukuran file asli dalam byte'))->change();
            $table->string('media_mime_type', 100)->nullable()->comment($comment('MIME type file setelah diproses'))->change();
            $table->unsignedBigInteger('media_size')->nullable()->comment($comment('Ukuran file setelah diproses dalam byte'))->change();
            $table->unsignedInteger('media_chunk_size')->comment($comment('Ukuran setiap potongan upload dalam byte'))->change();
            $table->unsignedInteger('media_total_chunks')->comment($comment('Total potongan yang harus diunggah'))->change();
            $table->unsignedInteger('media_uploaded_chunks')->default(0)->comment($comment('Jumlah potongan yang sudah diterima'))->change();
            $table->enum('media_status', [
                'pending',
                'uploading',
                'processing',
                'ready',
                'failed',
                'aborted',
            ])->default('pending')->comment($comment('Status proses upload dan pengolahan media'))->change();
            $table->string('media_disk', 30)->comment($comment('Disk penyimpanan Laravel'))->change();
            $table->string('media_path')->nullable()->comment($comment('Lokasi file pada disk penyimpanan'))->change();
            $table->char('media_checksum', 64)->nullable()->comment($comment('Checksum SHA-256 file'))->change();
            $table->text('media_error_message')->nullable()->comment($comment('Pesan kegagalan upload atau pengolahan'))->change();
            $table->dateTime('media_expires_at')->comment($comment('Batas waktu upload sementara'))->change();
            $table->dateTime('media_created_datetime')->comment($comment('Waktu media dibuat'))->change();
            $table->dateTime('media_updated_datetime')->comment($comment('Waktu media terakhir diperbarui'))->change();
        });
    }

    private function updateAuthenticationSecurityComments(bool $documented): void
    {
        $comment = static fn (string $value): string => $documented ? $value : '';

        Schema::table('member_account', function (Blueprint $table) use ($comment) {
            $table->unsignedTinyInteger('member_account_failed_login_attempts')
                ->default(0)
                ->comment($comment('Jumlah percobaan login member yang gagal berturut-turut'))
                ->change();
            $table->dateTime('member_account_last_failed_login_datetime')
                ->nullable()
                ->comment($comment('Waktu percobaan login member gagal terakhir'))
                ->change();
            $table->dateTime('member_account_locked_until')
                ->nullable()
                ->comment($comment('Batas waktu akun member dikunci'))
                ->change();
        });

        Schema::table('site_administrator', function (Blueprint $table) use ($comment) {
            $table->unsignedTinyInteger('administrator_failed_login_attempts')
                ->default(0)
                ->comment($comment('Jumlah percobaan login administrator yang gagal berturut-turut'))
                ->change();
            $table->dateTime('administrator_last_failed_login_datetime')
                ->nullable()
                ->comment($comment('Waktu percobaan login administrator gagal terakhir'))
                ->change();
            $table->dateTime('administrator_locked_until')
                ->nullable()
                ->comment($comment('Batas waktu akun administrator dikunci'))
                ->change();
        });
    }

    private function updateRewardComments(bool $documented): void
    {
        $comment = static fn (string $value): string => $documented ? $value : '';

        Schema::table('reward_point_monthly', function (Blueprint $table) use ($comment) {
            $table->unsignedInteger('reward_point_monthly_upline_id')
                ->default(0)
                ->comment($comment('Snapshot ID upline pada periode reward'))
                ->change();
            $table->unsignedInteger('reward_point_monthly_upline_level_id')
                ->default(0)
                ->comment($comment('Snapshot ID level upline pada periode reward'))
                ->change();
            $table->unsignedInteger('reward_point_monthly_member_level_id')
                ->comment($comment('Snapshot ID level member pada periode reward'))
                ->change();
            $table->unsignedInteger('reward_point_monthly_bonus_value')
                ->default(0)
                ->comment($comment('Nominal bonus bulanan berdasarkan point atau kuantitas'))
                ->change();
            $table->unsignedInteger('reward_point_monthly_admin_id')
                ->default(0)
                ->comment($comment('ID administrator yang memproses reward Distributor'))
                ->change();
        });

        Schema::table('reward_stockist', function (Blueprint $table) use ($comment) {
            $table->unsignedInteger('reward_stockist_used_trx_id')
                ->comment($comment('ID transaksi yang menggunakan voucher reward stokis'))
                ->change();
        });
    }

    private function updateSpreadPaymentComments(bool $documented): void
    {
        $comment = static fn (string $value): string => $documented ? $value : '';

        Schema::table('trx_spread_payment', function (Blueprint $table) use ($comment) {
            $table->increments('trx_spread_payment_id')
                ->comment($comment('ID kewajiban spread payment'))
                ->change();
        });
    }
};
