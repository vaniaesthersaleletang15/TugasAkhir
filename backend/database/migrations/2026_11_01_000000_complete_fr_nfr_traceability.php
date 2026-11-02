<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('integrated_terminals')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['terminal_id', 'code']);
        });

        Schema::create('transporter_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transporter_id')->constrained('transporters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['transporter_id', 'user_id']);
        });

        Schema::table('user_registrations', function (Blueprint $table) {
            $table->foreignId('transporter_id')->nullable()->after('terminal_id')->constrained('transporters')->nullOnDelete();
            $table->text('review_note')->nullable()->after('transporter_id');
        });

        Schema::table('kendaraan', function (Blueprint $table) {
            $table->foreignId('transporter_id')->nullable()->after('terminal_id')->constrained('transporters')->nullOnDelete();
            $table->string('nomor_urut', 50)->nullable()->after('nomor_polisi');
            $table->string('produk', 100)->nullable()->after('tahun_kendaraan');
            $table->string('kapasitas', 100)->nullable()->after('produk');
            $table->string('approval_status', 20)->default('PENDING')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
        });
        DB::table('kendaraan')->where('status', 'AKTIF')->update(['approval_status' => 'APPROVED']);

        Schema::table('dokumen_kendaraan', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('terminal_id')->constrained('kendaraan')->nullOnDelete();
            $table->foreignId('transporter_id')->nullable()->after('vehicle_id')->constrained('transporters')->nullOnDelete();
        });

        Schema::table('formulir_checklist', function (Blueprint $table) {
            $table->string('template_version', 64)->nullable()->after('jenis_kendaraan');
        });

        Schema::create('vehicle_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained('kendaraan')->nullOnDelete();
            $table->foreignId('terminal_id')->constrained('integrated_terminals')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('checklist_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formulir_id')->constrained('formulir_checklist')->cascadeOnDelete();
            $table->foreignId('terminal_id')->constrained('integrated_terminals')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 100);
            $table->string('actor_role', 50);
            $table->string('decision', 30);
            $table->text('notes')->nullable();
            $table->string('document_hash', 128)->nullable();
            $table->json('snapshot');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('kim_notifications', function (Blueprint $table) {
            $table->string('notification_key', 64)->nullable()->unique();
        });

        Schema::create('restore_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('integrated_terminals')->cascadeOnDelete();
            $table->string('filename', 255);
            $table->string('checksum', 64);
            $table->string('status', 30)->default('pending')->index();
            $table->text('request_notes')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->text('result_message')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('process_runs', function (Blueprint $table) {
            $table->id();
            $table->string('process_name', 80)->index();
            $table->string('status', 20)->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('message')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $this->backfillTransporters();
        $this->backfillDocumentOwnership();
    }

    private function backfillTransporters(): void
    {
        $rows = DB::table('kendaraan')
            ->whereNotNull('terminal_id')
            ->whereNotNull('nama_transport')
            ->whereRaw("TRIM(nama_transport) <> ''")
            ->select('terminal_id', 'nama_transport')
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $baseCode = Str::upper(Str::slug($row->nama_transport, '-'));
            $code = Str::limit($baseCode !== '' ? $baseCode : 'TRANSPORTIR', 32, '');
            $suffix = 1;
            $candidate = $code;
            while (DB::table('transporters')->where('terminal_id', $row->terminal_id)->where('code', $candidate)->exists()) {
                $candidate = Str::limit($code, 35, '').'-'.$suffix++;
            }
            $id = DB::table('transporters')->insertGetId([
                'terminal_id' => $row->terminal_id,
                'code' => $candidate,
                'name' => $row->nama_transport,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('kendaraan')
                ->where('terminal_id', $row->terminal_id)
                ->where('nama_transport', $row->nama_transport)
                ->update(['transporter_id' => $id]);
        }

        $links = DB::table('pengurus_kendaraan as pk')
            ->join('kendaraan as k', 'k.nomor_polisi', '=', 'pk.nomor_polisi')
            ->whereNotNull('k.transporter_id')
            ->select('pk.user_id', 'k.transporter_id')
            ->distinct()
            ->get();
        foreach ($links as $link) {
            DB::table('transporter_user')->insertOrIgnore([
                'transporter_id' => $link->transporter_id,
                'user_id' => $link->user_id,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function backfillDocumentOwnership(): void
    {
        $documents = DB::table('dokumen_kendaraan')->get(['id', 'nomor_polisi']);
        foreach ($documents as $document) {
            $vehicle = DB::table('kendaraan')->where('nomor_polisi', $document->nomor_polisi)->first(['id', 'transporter_id']);
            if ($vehicle) {
                DB::table('dokumen_kendaraan')->where('id', $document->id)->update([
                    'vehicle_id' => $vehicle->id,
                    'transporter_id' => $vehicle->transporter_id,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('process_runs');
        Schema::dropIfExists('restore_requests');

        Schema::table('kim_notifications', function (Blueprint $table) {
            $table->dropUnique(['notification_key']);
            $table->dropColumn('notification_key');
        });

        Schema::dropIfExists('checklist_approvals');
        Schema::dropIfExists('vehicle_histories');

        Schema::table('formulir_checklist', function (Blueprint $table) {
            $table->dropColumn('template_version');
        });
        Schema::table('dokumen_kendaraan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transporter_id');
            $table->dropConstrainedForeignId('vehicle_id');
        });
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('transporter_id');
            $table->dropColumn(['nomor_urut', 'produk', 'kapasitas', 'approval_status', 'reviewed_at', 'review_note']);
        });

        Schema::dropIfExists('transporter_user');
        Schema::table('user_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transporter_id');
            $table->dropColumn('review_note');
        });
        Schema::dropIfExists('transporters');
    }
};
