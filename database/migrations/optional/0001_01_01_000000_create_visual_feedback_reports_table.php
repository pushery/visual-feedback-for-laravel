<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The optional DatabaseChannel table. It lives in `database/migrations/optional/`,
 * NOT `database/migrations/`, so it is NEVER auto-loaded — a consumer gets it only by publishing
 * the `visual-feedback-migrations` tag and running migrate. Every column is typed with an
 * explicit length because SQLite does not enforce lengths: an over-long value would otherwise
 * only fail in a MySQL/PostgreSQL production (the string-enum-vs-varchar trap).
 *
 * ⚠️ THREE OF THESE WIDTHS ARE THE OTHER HALF OF A CONFIG KNOB, SO WIDEN THE COLUMN WHENEVER
 * YOU TURN THE KNOB PAST IT. `subject` pairs with `fields.subject.max_length` (150),
 * `reporter_phone` with `fields.phone.max_length` (32), and `category` has to hold the longest
 * key in `categories` (64). None of those config values is capped in shipped code — deliberately,
 * because after publishing this file the column belongs to YOU, and a runtime cap would silently
 * ignore a column you legitimately widened. Raise one without widening its column here and the
 * validator accepts the value, the row write answers `22001`, and the report reaches every other
 * channel while the reporter still sees the success screen. SQLite will not warn you: it does not
 * enforce lengths, so a local suite stays green and only PostgreSQL or MySQL falls over.
 * The package's own suite holds the shipped defaults against these widths, so the pair cannot
 * drift apart here — but only the SHIPPED defaults: once this file is yours, the pairing is too.
 * `message` (mediumText) and `user_agent` (truncated at the write site, the way
 * `metadata.user_agent_max` is allowed to exceed its column) are outside the pairing.
 *
 * Cross-engine notes: `message` is mediumText — MySQL `text` caps at 64 KB BYTES, and 50 000
 * multibyte characters blow that (PG/SQLite would not, so only MySQL would fall over). The
 * reporter is DENORMALIZED (no FK to the host users table, whose PK type and name are unknown).
 * JSON columns use portable `json()`, never Postgres-only `jsonb()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->tableName();

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('mode', 32);
            $table->string('category', 64);
            $table->string('subject', 150)->nullable();
            $table->mediumText('message');

            // Reporter, denormalized — reporter_id tolerates int/uuid/ulid; no FK to host users.
            $table->string('reporter_id')->nullable();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            // Collected only when `fields.phone` is enabled and the reporter is a guest, so it is
            // null on most rows. It is here because the alternative was worse: the widget asked
            // for it, validated it, and then neither default channel carried it — the mail has
            // no envelope slot for a phone number the way Reply-To carries the email.
            $table->string('reporter_phone', 32)->nullable();
            $table->boolean('is_guest')->default(true);

            $table->json('context');
            $table->json('metadata');
            $table->json('attachments');
            $table->json('deliveries');

            // No ip_address by default (host opt-in, own retention doc); user agent truncated.
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();
            // One index per predicate the package's own commands and the documented listing
            // recipe actually filter on — measured against the query plans, not assumed.
            // `created_at` carries the `latest('created_at')` listing (the prune's chunkById
            // walks the primary key and treats created_at as a filter, so the comment that
            // used to name the prune here named the wrong consumer). `reporter_email` is the
            // WHERE predicate of `visual-feedback:forget`, the DSAR erasure, and was the one
            // filtered column with no index: a sequential scan on every chunk. A `mode` index
            // stood here too and served nothing — `mode` is a widget mount prop, no query in
            // this package, its docs or its README filters on it, and Postgres declined the
            // composite even for the query it was shaped for. It is gone rather than shipped,
            // because this file freezes at 0.1.0 and every later change to it is a second
            // publish plus a migration in every consumer's tree.
            $table->index('created_at');
            $table->index('reporter_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    private function tableName(): string
    {
        $table = config('visual-feedback.database.table');

        return is_string($table) && $table !== '' ? $table : 'visual_feedback_reports';
    }
};
