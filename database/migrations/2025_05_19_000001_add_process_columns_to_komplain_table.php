<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komplain', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('status_komplain');
            $table->unsignedBigInteger('processed_by')->nullable()->after('admin_notes');
            $table->timestamp('processed_at')->nullable()->after('processed_by');

            // Add foreign key for processed_by
            $table->foreign('processed_by')
                  ->references('id_user')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('komplain', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn(['admin_notes', 'processed_by', 'processed_at']);
        });
    }
};
