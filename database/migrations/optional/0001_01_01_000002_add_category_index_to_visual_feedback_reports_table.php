<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An index on `category` for the optional DatabaseChannel table.
 *
 * The report browser filters on this column, and so does the listing recipe in the
 * documentation. Without an index, counting the reports of one category, which the browser's
 * pager does on every filtered page, reads the whole table, and so does finding the newest
 * reports of a category that is rare. It arrives as a migration of its own because an
 * application that published the table's first migration has already run it: publishing the
 * `visual-feedback-migrations` tag again adds this file and leaves the ones you already have as
 * they are, and `migrate` runs only this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->tableName(), static function (Blueprint $table): void {
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName(), static function (Blueprint $table): void {
            $table->dropIndex(['category']);
        });
    }

    private function tableName(): string
    {
        $table = config('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }
};
