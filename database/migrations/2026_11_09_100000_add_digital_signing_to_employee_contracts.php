<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->string('version_group', 64)->nullable()->after('id');
            $table->unsignedSmallInteger('version')->default(1)->after('version_group');
            $table->char('document_sha256', 64)->nullable()->after('file_size');
            $table->string('signing_status', 20)->default('draft')->index()->after('status');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('signature_due_at')->nullable();
            $table->timestamp('immutable_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->string('signed_pdf_disk', 30)->nullable();
            $table->string('signed_pdf_path')->nullable();
            $table->char('signed_pdf_sha256', 64)->nullable();
        });
        DB::table('employee_contracts')->orderBy('id')->eachById(function ($contract) {
            DB::table('employee_contracts')->where('id', $contract->id)->update(['version_group' => 'contract-'.$contract->id, 'version' => 1]);
        });
        Schema::table('employee_contracts', fn (Blueprint $table) => $table->unique(['version_group', 'version']));

        Schema::create('employee_contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_contract_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('signer_id')->constrained('users')->restrictOnDelete();
            $table->string('signer_name');
            $table->string('signer_username')->nullable();
            $table->string('signer_email');
            $table->string('signer_role', 40);
            $table->string('signature_name');
            $table->text('consent_text');
            $table->char('document_sha256', 64);
            $table->char('signature_hash', 64)->unique();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contract_signatures');
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropUnique(['version_group', 'version']);
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn(['version_group', 'version', 'document_sha256', 'signing_status', 'issued_at', 'signature_due_at', 'immutable_at', 'declined_at', 'decline_reason', 'signed_pdf_disk', 'signed_pdf_path', 'signed_pdf_sha256']);
        });
    }
};
