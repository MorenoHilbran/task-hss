<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: alter the ENUM to replace 'completed' with 'done'
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending','done') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending','completed') NOT NULL DEFAULT 'pending'");
    }
};
