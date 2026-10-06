<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An index on `mode` for the optional DatabaseChannel table.
 *
 * The report browser filters on this column and lists its values for the filter on every
 * render. On MySQL that list reads the whole table without an index, and with one it reads only
 * the index, a value at a time; counting the reports of one mode, which the browser's pager does
 * on every filtered page, reads the whole table without it too. It arrives as a migration of its
 * own because an application that published the table's first migrations has already run them:
 * publishing the `visual-feedback-migrations` tag again adds this file and leaves the ones you
 * already have as they are, and `migrate` runs only this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->tableName(), static function (Blueprint $table): void {
            $table->index('mode');
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName(), static function (Blueprint $table): void {
            $table->dropIndex(['mode']);
        });
    }

    private function tableName(): string
    {
        $table = config('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }
};
