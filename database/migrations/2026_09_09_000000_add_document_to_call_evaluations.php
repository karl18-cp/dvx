<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_evaluations', function (Blueprint $table) {
            $table->string('document_storage_disk', 50)->nullable()->after('uploaded_at');
            $table->string('document_storage_key', 1024)->nullable()->after('document_storage_disk');
            $table->string('document_original_filename')->nullable()->after('document_storage_key');
            $table->string('document_mime_type', 100)->nullable()->after('document_original_filename');
            $table->unsignedBigInteger('document_file_size')->nullable()->after('document_mime_type');
            $table->foreignId('document_uploaded_by')->nullable()->after('document_file_size')->constrained('users')->nullOnDelete();
            $table->timestamp('document_uploaded_at')->nullable()->after('document_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('call_evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_uploaded_by');
            $table->dropColumn([
                'document_storage_disk',
                'document_storage_key',
                'document_original_filename',
                'document_mime_type',
                'document_file_size',
                'document_uploaded_at',
            ]);
        });
    }
};
