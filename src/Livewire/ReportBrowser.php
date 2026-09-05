<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Livewire;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Pushery\VisualFeedback\Console\Concerns\ResolvesReportStorage;

/**
 * A minimal browser over the reports table — deliberately minimal, and deliberately optional.
 *
 * The package's stated position is "bring your own admin": the table is public API and a host
 * that wants a rich console builds one. This exists for the host that wants to READ its
 * feedback without building anything, and it covers what such a host actually needs — filter,
 * detail with the screenshot, and delete with the files cleaned up — rather than a subset that
 * sends them off to write the rest themselves.
 *
 * THREE THINGS MAKE IT SAFE TO SHIP AT ALL, and each is a decision rather than a default:
 *
 * 1. IT IS UNREACHABLE UNTIL A HOST ROUTES IT. The package registers the component but no
 *    route. Livewire components are addressable by name over the update endpoint, which is why
 *    the authorization below is not a formality — see `livewire-audit`, and this repository's
 *    own audit lineage: a component reachable by name is a component that must check.
 *
 * 2. AUTHORIZATION IS FAIL-CLOSED AND RE-CHECKED PER ACTION. The gate name is the package's;
 *    its DEFINITION is the host's, and this package deliberately does not define it. An
 *    undefined gate denies in Laravel, so a host that installs the package and does nothing
 *    else has an endpoint that answers 403 — not one that answers with its users' feedback.
 *
 *    Per action, not once in mount(): a Livewire component re-hydrates on every request from
 *    client-supplied state, so a check that ran only at mount is a check that ran on a request
 *    nobody is making any more.
 *
 * 3. THE TABLE IS OPTIONAL, SO THIS IS TOO. Without the opt-in migration there is nothing to
 *    browse, and the component says so instead of throwing — the same shape DatabaseChannel
 *    already uses for the same condition.
 */
final class ReportBrowser extends Component
{
    use ResolvesReportStorage;
    use WithPagination;

    /**
     * The gate a host defines to open this view.
     *
     * A constant rather than a config key on purpose: a name that can be changed in
     * configuration is a name an attacker's environment can change too, and there is no reason
     * a host needs two ways to spell it. What is configurable is whether the host defines the
     * gate at all.
     */
    public const string GATE = 'viewVisualFeedbackReports';

    /** The filters. `#[Url]` so a filtered view is a shareable link rather than lost on reload. */
    #[Url(as: 'mode', keep: false)]
    public string $filterMode = '';

    #[Url(as: 'category', keep: false)]
    public string $filterCategory = '';

    #[Url(as: 'from', keep: false)]
    public string $filterFrom = '';

    #[Url(as: 'to', keep: false)]
    public string $filterTo = '';

    /** The report open in the detail pane, by uuid. Empty means the list. */
    public string $selected = '';

    public function mount(): void
    {
        $this->authorizeBrowsing();
    }

    /**
     * The one authorization point, called by every entry.
     *
     * `Gate::allows` rather than `Gate::authorize` so the denial is this method's to shape: a
     * 403 for a page a host has not opened is the right answer, and it must be the same answer
     * whether the gate is undefined or defined-and-denying. A host must not be able to tell
     * "no such feature here" from "not for you" by the status code.
     */
    private function authorizeBrowsing(): void
    {
        abort_unless(Gate::allows(self::GATE), 403);
    }

    /** Reset to the first page whenever a filter moves, or page 4 of the old result set shows nothing. */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'filter')) {
            $this->resetPage();
        }
    }

    public function open(string $uuid): void
    {
        $this->authorizeBrowsing();

        $this->selected = $uuid;
    }

    public function close(): void
    {
        $this->selected = '';
    }

    public function clearFilters(): void
    {
        $this->filterMode = '';
        $this->filterCategory = '';
        $this->filterFrom = '';
        $this->filterTo = '';
        $this->resetPage();
    }

    /**
     * Delete one report AND its attachment files.
     *
     * Files first, then the row — the same order `visual-feedback:prune` uses, and for the same
     * reason: a row deleted first leaves paths nobody can resolve any more, and the files leak
     * for the lifetime of the disk. The orphan sweep is a backstop for eviction, not a license
     * to delete in the convenient order.
     *
     * The uuid arrives from the client, so it is authorized and then used only as a WHERE
     * value. Nothing here trusts a client-supplied path.
     */
    public function delete(string $uuid): void
    {
        $this->authorizeBrowsing();

        $config = app(Config::class);
        $db = app(DatabaseManager::class);
        $table = $this->reportsTable($config);

        if (! Schema::hasTable($table)) {
            return;
        }

        $row = $db->connection()->table($table)->where('uuid', $uuid)->first();

        if ($row === null) {
            return;
        }

        $paths = $this->attachmentPaths($row->attachments ?? null);

        if ($paths !== []) {
            app(FilesystemFactory::class)
                ->disk($this->attachmentsDisk($config))
                ->delete($paths);
        }

        $db->connection()->table($table)->where('uuid', $uuid)->delete();

        if ($this->selected === $uuid) {
            $this->selected = '';
        }

        $this->resetPage();
    }

    /** Whether the opt-in table exists at all. The view branches on this rather than erroring. */
    public function tableExists(): bool
    {
        return Schema::hasTable($this->reportsTable(app(Config::class)));
    }

    /**
     * The distinct categories present in the data, for the filter.
     *
     * Read from the ROWS rather than from configuration on purpose: a report keeps the category
     * it was filed under, so a category removed from config still exists in the table — and a
     * filter built from config alone could not reach those rows at all.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        /** @var list<string> $values */
        $values = app(DatabaseManager::class)->connection()
            ->table($this->reportsTable(app(Config::class)))
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        return $values;
    }

    /**
     * The modes present in the data, same reasoning as categories().
     *
     * @return list<string>
     */
    public function modes(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        /** @var list<string> $values */
        $values = app(DatabaseManager::class)->connection()
            ->table($this->reportsTable(app(Config::class)))
            ->select('mode')
            ->distinct()
            ->orderBy('mode')
            ->pluck('mode')
            ->all();

        return $values;
    }

    /**
     * A displayable URL for one attachment, or null when there is none to give.
     *
     * Null is the common and CORRECT answer: the attachments disk is private in every shipped
     * configuration, and a private disk has no public URL. This deliberately does not invent
     * one — no signed route, no streaming controller, no `storage:link` assumption. Adding any
     * of those would put a package-owned download endpoint in front of a host's private files,
     * which is a far larger decision than "let me look at my feedback" and is exactly the sort
     * of thing a host should build deliberately if they want it.
     *
     * So the detail pane shows the PATH when it cannot show the picture. That is honest and
     * still useful — it is what a host needs to fetch the file with their own tooling.
     */
    public function attachmentUrl(string $path): ?string
    {
        $config = app(Config::class);
        $disk = $this->attachmentsDisk($config) ?? 'local';

        // THE DECIDER IS THE DISK'S CONFIGURED `url`, NOT WHETHER url() THROWS.
        // The first version of this method asked the adapter and treated a RuntimeException as
        // "no url". Measured: Laravel's local driver does NOT throw — it falls back to
        // `/storage/<path>`, which resolves only if the host ran `storage:link` AND the file
        // lives under the public disk root. On the shipped private disk that URL is a 404, so
        // the browser would have rendered a broken image and called it a preview.
        //
        // Reading the configuration answers the question that actually matters: did the host
        // DECLARE this disk publicly addressable. A disk with no `url` is private by the host's
        // own configuration, and the detail pane shows the path instead — which is honest and
        // is what they need to fetch the file with their own tooling.
        if (! is_string($config->get("filesystems.disks.{$disk}.url"))) {
            return null;
        }

        // No try/catch, and that is a decision. A `RuntimeException` here means the host
        // declared `url` on a disk whose adapter cannot produce one — a configuration error
        // that should be loud, not swallowed into a silently missing preview. And a branch no
        // run in this repository can enter is a line the 100% floor would block the release on
        // while proving nothing: the guard against inventing a URL is the config check above,
        // which every run does exercise, in both directions.
        $url = app(FilesystemFactory::class)->disk($disk)->url($path);

        return $url === '' ? null : $url;
    }

    /**
     * The attachment entries of one report, decoded.
     *
     * @return list<string>
     */
    public function attachmentsOf(mixed $json): array
    {
        return $this->attachmentPaths($json);
    }

    /**
     * Whether a path looks like an image this browser can preview inline.
     *
     * Extension-based and deliberately narrow. The alternative — reading the file to sniff its
     * type — would mean the browser fetches every attachment of every listed report just to
     * decide how to render a link, and a report can carry several.
     */
    public function isPreviewable(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif'], true);
    }

    /** The filtered query, shared by the list and by the detail lookup. */
    private function query(): Builder
    {
        $query = app(DatabaseManager::class)->connection()
            ->table($this->reportsTable(app(Config::class)));

        if ($this->filterMode !== '') {
            $query->where('mode', $this->filterMode);
        }

        if ($this->filterCategory !== '') {
            $query->where('category', $this->filterCategory);
        }

        // Whole days on both ends: a reader typing a date means the day, not midnight. Without
        // the end-of-day the `to` filter silently excludes everything filed after 00:00:00 on
        // the very day the reader asked for, which reads as "no reports today".
        if ($this->filterFrom !== '') {
            $query->where('created_at', '>=', $this->filterFrom.' 00:00:00');
        }

        if ($this->filterTo !== '') {
            $query->where('created_at', '<=', $this->filterTo.' 23:59:59');
        }

        return $query;
    }

    public function render(): View
    {
        $this->authorizeBrowsing();

        $reports = $this->tableExists()
            ? $this->query()->orderByDesc('created_at')->paginate(20)
            : null;

        $detail = null;

        if ($this->selected !== '' && $this->tableExists()) {
            $detail = app(DatabaseManager::class)->connection()
                ->table($this->reportsTable(app(Config::class)))
                ->where('uuid', $this->selected)
                ->first();
        }

        return view('visual-feedback::livewire.report-browser', [
            'reports' => $reports,
            'detail' => $detail,
            'categories' => $this->categories(),
            'modes' => $this->modes(),
        ]);
    }
}
