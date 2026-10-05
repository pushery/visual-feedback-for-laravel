<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An index on `reporter_id` for the optional DatabaseChannel table.
 *
 * `visual-feedback:forget --reporter=<id>` erases a signed-in reporter's reports by this column,
 * and without an index that erasure reads the whole table on every chunk. It arrives as a
 * migration of its own because an application that published the table's first migration has
 * already run it: publishing the `visual-feedback-migrations` tag again adds this file and leaves
 * the one you already have as it is, and `migrate` runs only this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->tableName(), static function (Blueprint $table): void {
            $table->index('reporter_id');
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName(), static function (Blueprint $table): void {
            $table->dropIndex(['reporter_id']);
        });
    }

    private function tableName(): string
    {
        $table = config('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }
};
