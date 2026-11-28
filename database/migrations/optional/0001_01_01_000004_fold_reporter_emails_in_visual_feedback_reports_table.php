<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Folds every `reporter_email` of the optional DatabaseChannel table to lower case.
 *
 * The database channel stores an address in lower case, and `visual-feedback:forget` asks for a
 * requested address in lower case as well, so a request in any case reaches a report on every
 * engine. A report stored before the channel folded addresses keeps the case it was submitted in,
 * and on PostgreSQL and SQLite only that spelling reaches it. This migration folds those rows the
 * way the channel does, with `mb_strtolower()` in PHP rather than SQL `lower()`, which folds
 * letters outside ASCII differently. It arrives as a migration of its own for the reason the index
 * migrations give: publishing the `visual-feedback-migrations` tag again adds this file and leaves
 * the ones you already have as they are, and `migrate` runs only this one.
 *
 * The case an address was submitted in is kept nowhere once it is folded, so down() refuses with
 * that reason rather than report a rollback it did not make.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->tableName();

        DB::table($table)->select(['id', 'reporter_email'])->whereNotNull('reporter_email')
            ->chunkById(500, static function (iterable $rows) use ($table): void {
                foreach ($rows as $row) {
                    $stored = $row->reporter_email;

                    if (is_string($stored) && mb_strtolower(trim($stored)) !== $stored) {
                        DB::table($table)->where('id', $row->id)->update(['reporter_email' => mb_strtolower(trim($stored))]);
                    }
                }
            });
    }

    public function down(): never
    {
        throw new RuntimeException('The fold of reporter_email to lower case cannot be undone: the case each address was submitted in is kept nowhere.');
    }

    private function tableName(): string
    {
        $table = config('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }
};
