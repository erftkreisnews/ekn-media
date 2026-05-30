<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_publication_findings')) {
            Schema::table('media_publication_findings', function (Blueprint $table) {
                if (! Schema::hasColumn('media_publication_findings', 'authority_access_token')) {
                    $table->string('authority_access_token', 64)->nullable()->unique()->after('evidence_manual_files');
                    $table->timestamp('authority_access_expires_at')->nullable()->after('authority_access_token');
                    $table->string('authority_access_password')->nullable()->after('authority_access_expires_at');
                    $table->string('authority_access_recipient', 255)->nullable()->after('authority_access_password');
                    $table->foreignId('authority_access_created_by')->nullable()->after('authority_access_recipient')->constrained('users')->nullOnDelete();
                    $table->timestamp('authority_access_created_at')->nullable()->after('authority_access_created_by');
                    $table->timestamp('authority_access_revoked_at')->nullable()->after('authority_access_created_at');
                }
            });
        }

        if (! Schema::hasTable('publication_finding_authority_downloads')) {
            Schema::create('publication_finding_authority_downloads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('media_publication_finding_id');
                $table->string('ip_hash', 64)->nullable();
                $table->string('ua_hash', 64)->nullable();
                $table->string('recipient_label')->nullable();
                $table->timestamps();

                $table->foreign('media_publication_finding_id', 'pf_auth_dl_finding_fk')
                    ->references('id')
                    ->on('media_publication_findings')
                    ->cascadeOnDelete();

                $table->index('media_publication_finding_id', 'pf_auth_dl_finding_idx');
                $table->index('created_at', 'pf_auth_dl_created_idx');
            });
        } elseif (! $this->foreignKeyExists('publication_finding_authority_downloads', 'pf_auth_dl_finding_fk')) {
            DB::statement(
                'ALTER TABLE `publication_finding_authority_downloads`
                 ADD CONSTRAINT `pf_auth_dl_finding_fk`
                 FOREIGN KEY (`media_publication_finding_id`)
                 REFERENCES `media_publication_findings` (`id`)
                 ON DELETE CASCADE'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_finding_authority_downloads');

        if (! Schema::hasTable('media_publication_findings')) {
            return;
        }

        if (Schema::hasColumn('media_publication_findings', 'authority_access_token')) {
            Schema::table('media_publication_findings', function (Blueprint $table) {
                $table->dropForeign(['authority_access_created_by']);
                $table->dropColumn([
                    'authority_access_token',
                    'authority_access_expires_at',
                    'authority_access_password',
                    'authority_access_recipient',
                    'authority_access_created_by',
                    'authority_access_created_at',
                    'authority_access_revoked_at',
                ]);
            });
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        );

        return $rows !== [];
    }
};
