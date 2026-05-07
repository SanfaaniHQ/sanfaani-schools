<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('mail_settings', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->cascadeOnDelete();
                $table->unique('school_id', 'mail_settings_school_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            if (Schema::hasColumn('mail_settings', 'school_id')) {
                $table->dropUnique('mail_settings_school_unique');
                $table->dropConstrainedForeignId('school_id');
            }
        });
    }
};
