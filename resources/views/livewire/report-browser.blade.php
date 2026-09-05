{{-- The framework-free browser. No Tailwind, no build step, no Alpine — every interaction is a
     Livewire round trip, which is why this template carries no `x-` directive at all and is
     therefore outside the CSP question the widget had to solve.

     The styles live in `visual-feedback::style`, the same stylesheet the widget uses, so a host
     that already includes it gets this for free. --}}
<div class="visual-feedback-browser">
    @if (! $this->tableExists())
        <p class="visual-feedback-browser-empty">
            {{ __('visual-feedback::browser.no_table') }}
        </p>
    @else
        <form class="visual-feedback-browser-filters" wire:submit.prevent>
            <label class="visual-feedback-browser-field">
                <span>{{ __('visual-feedback::browser.filter_mode') }}</span>
                <select wire:model.live="filterMode">
                    <option value="">{{ __('visual-feedback::browser.any') }}</option>
                    @foreach ($modes as $mode)
                        <option value="{{ $mode }}">{{ $mode }}</option>
                    @endforeach
                </select>
            </label>

            <label class="visual-feedback-browser-field">
                <span>{{ __('visual-feedback::browser.filter_category') }}</span>
                <select wire:model.live="filterCategory">
                    <option value="">{{ __('visual-feedback::browser.any') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </label>

            <label class="visual-feedback-browser-field">
                <span>{{ __('visual-feedback::browser.filter_from') }}</span>
                <input type="date" wire:model.live="filterFrom">
            </label>

            <label class="visual-feedback-browser-field">
                <span>{{ __('visual-feedback::browser.filter_to') }}</span>
                <input type="date" wire:model.live="filterTo">
            </label>

            <button type="button" class="visual-feedback-browser-clear" wire:click="clearFilters">
                {{ __('visual-feedback::browser.clear') }}
            </button>
        </form>

        @if ($reports !== null && $reports->total() === 0)
            <p class="visual-feedback-browser-empty">{{ __('visual-feedback::browser.none') }}</p>
        @else
            <table class="visual-feedback-browser-table">
                <caption class="visual-feedback-sr-only">{{ __('visual-feedback::browser.caption') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('visual-feedback::browser.col_date') }}</th>
                        <th scope="col">{{ __('visual-feedback::browser.col_mode') }}</th>
                        <th scope="col">{{ __('visual-feedback::browser.col_category') }}</th>
                        <th scope="col">{{ __('visual-feedback::browser.col_subject') }}</th>
                        <th scope="col">{{ __('visual-feedback::browser.col_reporter') }}</th>
                        <th scope="col"><span class="visual-feedback-sr-only">{{ __('visual-feedback::browser.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $report)
                        <tr wire:key="vf-report-{{ $report->uuid }}">
                            <td>{{ $report->created_at }}</td>
                            <td>{{ $report->mode }}</td>
                            <td>{{ $report->category }}</td>
                            <td>{{ $report->subject ?: '—' }}</td>
                            {{-- A guest with no name is the shipped default, so the fallback is
                                 the normal case rather than a defect. --}}
                            <td>{{ $report->reporter_name ?: __('visual-feedback::browser.guest') }}</td>
                            <td class="visual-feedback-browser-actions">
                                <button type="button" wire:click="open('{{ $report->uuid }}')">
                                    {{ __('visual-feedback::browser.open') }}
                                </button>
                                {{-- wire:confirm rather than a JS confirm(): it is Livewire's own
                                     mechanism and needs no inline handler, so this template stays
                                     free of script under any policy. --}}
                                <button
                                    type="button"
                                    wire:click="delete('{{ $report->uuid }}')"
                                    wire:confirm="{{ __('visual-feedback::browser.confirm_delete') }}"
                                >
                                    {{ __('visual-feedback::browser.delete') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="visual-feedback-browser-pagination">
                {{ $reports->links() }}
            </div>
        @endif

        @if ($detail !== null)
            <section class="visual-feedback-browser-detail" aria-label="{{ __('visual-feedback::browser.detail') }}">
                <header>
                    <h2>{{ $detail->subject ?: __('visual-feedback::browser.untitled') }}</h2>
                    <button type="button" wire:click="close">{{ __('visual-feedback::browser.close') }}</button>
                </header>

                <dl>
                    <dt>{{ __('visual-feedback::browser.col_date') }}</dt><dd>{{ $detail->created_at }}</dd>
                    <dt>{{ __('visual-feedback::browser.col_mode') }}</dt><dd>{{ $detail->mode }}</dd>
                    <dt>{{ __('visual-feedback::browser.col_category') }}</dt><dd>{{ $detail->category }}</dd>
                    <dt>{{ __('visual-feedback::browser.col_reporter') }}</dt>
                    <dd>
                        {{ $detail->reporter_name ?: __('visual-feedback::browser.guest') }}
                        @if ($detail->reporter_email)
                            &lt;{{ $detail->reporter_email }}&gt;
                        @endif
                    </dd>
                </dl>

                <p class="visual-feedback-browser-message">{{ $detail->message }}</p>

                @foreach ($this->attachmentsOf($detail->attachments ?? null) as $path)
                    <figure class="visual-feedback-browser-attachment">
                        @php($vfUrl = $this->attachmentUrl($path))
                        @if ($vfUrl !== null && $this->isPreviewable($path))
                            <img src="{{ $vfUrl }}" alt="{{ __('visual-feedback::browser.attachment_alt') }}" loading="lazy" decoding="async">
                        @endif
                        {{-- The path is shown either way. On a private disk there is no URL to
                             give, and the path is what a host needs to fetch the file with their
                             own tooling — see the component docblock for why no download route
                             is invented here. --}}
                        <figcaption>{{ $path }}</figcaption>
                    </figure>
                @endforeach
            </section>
        @endif
    @endif
</div>
