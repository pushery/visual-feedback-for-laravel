{{-- The same browser through WireKit components, so it inherits the host's design tokens.

     Component signatures were read from the installed WireKit via its MCP rather than from
     memory — `table` and its subcomponents, `attachment`, `data-list`, `empty-state`. That is
     the house rule for this tree and it is not ceremony: a prop that moved between minors is
     invisible until the page renders wrong.

     No `x-` directive here either — which is not the same as being outside the CSP question, and
     this comment claimed it was. A `wire:` action expression is contextualized to
     `$wire.<expression>` and evaluated by Alpine, so it meets the same grammar. See the plain
     tree's header for the full correction. --}}
<div>
    {{-- Kit components carry no outer margin, so each group of them sits in a stack, which is what
         spaces them: the page's sections here, the filter fields and the parts of the detail below. --}}
    <x-wirekit::stack gap="lg">
        {{-- The page's one h1, as in the plain tree, where the host layout brings none of its own;
             at the level the host names with `heading-level`, or none with 0, where it does. --}}
        @if ($headingLevel > 0)
            <x-wirekit::heading :level="$headingLevel">{{ __('visual-feedback::browser.title') }}</x-wirekit::heading>
        @endif

        @if ($deleteFailed)
            <x-wirekit::alert intent="danger">{{ __('visual-feedback::browser.delete_failed') }}</x-wirekit::alert>
        @endif

        @if (! $tableExists)
            {{-- level 2: the kit titles an empty state h3 unless told, and directly under the page's
                 h1 that reads as a skipped section to anyone moving by heading. --}}
            <x-wirekit::empty-state
                :level="$sectionLevel"
                :title="__('visual-feedback::browser.no_table_title')"
                :description="__('visual-feedback::browser.no_table')"
            />
        @else
            <x-wirekit::card>
                <x-wirekit::card.body>
                    {{-- The four filters in one row where they fit, each label, control and message on one
                         line with its neighbors'; the row breaks only where the room runs out, and on a phone
                         they stand under each other. The reset at its own width. --}}
                    <x-wirekit::stack>
                        <x-wirekit::row gap="sm" align-fields wrap>
                            <x-wirekit::select wire:model.live="filterMode" :label="__('visual-feedback::browser.filter_mode')">
                                <option value="">{{ __('visual-feedback::browser.any') }}</option>
                                @foreach ($modes as $mode)
                                    <option value="{{ $mode }}">{{ $modeLabels[$mode] ?? $mode }}</option>
                                @endforeach
                            </x-wirekit::select>

                            <x-wirekit::select wire:model.live="filterCategory" :label="__('visual-feedback::browser.filter_category')">
                                <option value="">{{ __('visual-feedback::browser.any') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ $categoryLabels[$category] ?? $category }}</option>
                                @endforeach
                            </x-wirekit::select>
                            <x-wirekit::input
                                type="date"
                                wire:model.live="filterFrom"
                                :label="__('visual-feedback::browser.filter_from')"
                            />

                            <x-wirekit::input
                                type="date"
                                wire:model.live="filterTo"
                                :label="__('visual-feedback::browser.filter_to')"
                            />
                        </x-wirekit::row>

                        <x-wirekit::row>
                            <x-wirekit::button intent="neutral" wire:click="clearFilters" loading-target="clearFilters">
                                {{ __('visual-feedback::browser.clear') }}
                            </x-wirekit::button>
                        </x-wirekit::row>
                    </x-wirekit::stack>
                </x-wirekit::card.body>
            </x-wirekit::card>

            @if ($reports !== null && $reports->total() === 0)
                <x-wirekit::empty-state
                    :level="$sectionLevel"
                    :title="__('visual-feedback::browser.none_title')"
                    :description="__('visual-feedback::browser.none')"
                />
            @else
                <x-wirekit::table hoverable responsive>
                    <x-wirekit::table.caption>
                        {{ __('visual-feedback::browser.caption') }}
                    </x-wirekit::table.caption>

                    <x-wirekit::table.head>
                        <x-wirekit::table.row>
                            <x-wirekit::table.th>{{ __('visual-feedback::browser.col_date') }}</x-wirekit::table.th>
                            <x-wirekit::table.th>{{ __('visual-feedback::browser.col_mode') }}</x-wirekit::table.th>
                            <x-wirekit::table.th>{{ __('visual-feedback::browser.col_category') }}</x-wirekit::table.th>
                            <x-wirekit::table.th>{{ __('visual-feedback::browser.col_subject') }}</x-wirekit::table.th>
                            <x-wirekit::table.th>{{ __('visual-feedback::browser.col_reporter') }}</x-wirekit::table.th>
                            <x-wirekit::table.th align="right" class="visual-feedback-browser-actions-head">
                                <x-wirekit::visually-hidden>{{ __('visual-feedback::browser.col_actions') }}</x-wirekit::visually-hidden>
                            </x-wirekit::table.th>
                        </x-wirekit::table.row>
                    </x-wirekit::table.head>

                    <x-wirekit::table.body>
                        @foreach ($reports as $report)
                            <x-wirekit::table.row wire:key="vf-report-{{ $report->uuid }}">
                                <x-wirekit::table.td><span class="whitespace-nowrap">{{ $receivedLabel($report->created_at) }}</span></x-wirekit::table.td>
                                <x-wirekit::table.td>{{ $modeLabels[$report->mode] ?? $report->mode }}</x-wirekit::table.td>
                                <x-wirekit::table.td>{{ $categoryLabels[$report->category] ?? $report->category }}</x-wirekit::table.td>
                                <x-wirekit::table.td :attributes="$redact">{{ $report->subject ?: '—' }}</x-wirekit::table.td>
                                <x-wirekit::table.td :attributes="$redact">
                                    {{ $report->reporter_name ?: __('visual-feedback::browser.guest') }}
                                </x-wirekit::table.td>
                                <x-wirekit::table.td align="right">
                                    <x-wirekit::button size="sm" wire:click="open('{{ $report->uuid }}')" loading-target="open('{{ $report->uuid }}')">
                                        {{ __('visual-feedback::browser.open') }}
                                    </x-wirekit::button>
                                    {{-- wire:confirm rather than an inline handler: Livewire's own
                                         mechanism, so this tree stays script-free under any policy. --}}
                                    {{-- `$wire['delete']` rather than `delete(…)`.
                                         Livewire rewrites a bare `delete('x')` to `$wire.delete('x')`, and under
                                         Alpine's CSP build `delete` is a keyword where the grammar wants an
                                         identifier — so the expression is never evaluated and the button is dead,
                                         with only the browser console to show for it. Index access parses under
                                         both builds. The whole
                                         affected set, measured against Alpine's own parser:
                                         delete false in instanceof new null true typeof undefined void. --}}
                                    <x-wirekit::button
                                        size="sm"
                                        intent="danger"
                                        wire:click="$wire['delete']('{{ $report->uuid }}')"
                                        wire:confirm="{{ __('visual-feedback::browser.confirm_delete') }}"
                                        loading-target="delete"
                                    >
                                        {{ __('visual-feedback::browser.delete') }}
                                    </x-wirekit::button>
                                </x-wirekit::table.td>
                            </x-wirekit::table.row>
                        @endforeach
                    </x-wirekit::table.body>
                </x-wirekit::table>

                {{-- The kit's own pager, turning pages through the component like Livewire's would. --}}
                {{-- Newest first, so the directions are named after time: "Previous" and "Next" would
                     say the opposite of what the buttons do. --}}
                <x-wirekit::pagination :paginator="$reports" :livewire="true"
                    :previous-label="__('visual-feedback::browser.newer')"
                    :next-label="__('visual-feedback::browser.older')" />
            @endif

            @if ($detail !== null)
                <x-wirekit::card>
                    {{-- An h2, as in the plain tree, at the size the card header gives its text. --}}
                    <x-wirekit::card.header>
                        <x-wirekit::heading :level="$sectionLevel" size="lg" :attributes="$redact">{{ $detail->subject ?: __('visual-feedback::browser.untitled') }}</x-wirekit::heading>
                    </x-wirekit::card.header>

                    <x-wirekit::card.body>
                        <x-wirekit::stack>
                            <x-wirekit::data-list>
                                <x-wirekit::data-list.item :label="__('visual-feedback::browser.col_date')">
                                    <span class="whitespace-nowrap">{{ $receivedLabel($detail->created_at) }}</span>
                                </x-wirekit::data-list.item>
                                <x-wirekit::data-list.item :label="__('visual-feedback::browser.col_mode')">
                                    {{ $modeLabels[$detail->mode] ?? $detail->mode }}
                                </x-wirekit::data-list.item>
                                <x-wirekit::data-list.item :label="__('visual-feedback::browser.col_category')">
                                    {{ $categoryLabels[$detail->category] ?? $detail->category }}
                                </x-wirekit::data-list.item>
                                <x-wirekit::data-list.item :label="__('visual-feedback::browser.col_reporter')">
                                    {{-- On the value, not on the item, so the label stays readable in a screenshot. --}}
                                    <span {{ $redact }}>
                                        {{ $detail->reporter_name ?: __('visual-feedback::browser.guest') }}
                                        @if ($detail->reporter_email)
                                            &lt;{{ $detail->reporter_email }}&gt;
                                        @endif
                                    </span>
                                </x-wirekit::data-list.item>
                            </x-wirekit::data-list>

                            {{-- The class carries `white-space: pre-wrap` from the stylesheet's WireKit block:
                                 the kit's text component collapses white space, and a reporter's numbered
                                 steps would read as one paragraph. --}}
                            <x-wirekit::text class="visual-feedback-browser-message" :attributes="$redact">{{ $detail->message }}</x-wirekit::text>

                            @foreach ($attachments as $attachment)
                                {{-- `thumbnail` only when the disk can actually give a URL. On a private
                                     disk — every shipped configuration — there is none, and the card
                                     then shows the path, which is what a host needs to fetch the file
                                     (where the picture shows, its file name is enough)
                                     with their own tooling. No download route is invented here; see the
                                     component docblock. --}}
                                <x-wirekit::attachment
                                    :name="$attachment['url'] !== null ? basename($attachment['path']) : $attachment['path']"
                                    :thumbnail="$attachment['url'] !== null && $attachment['previewable'] ? $attachment['url'] : null"
                                    :href="$attachment['url']"
                                />
                            @endforeach
                        </x-wirekit::stack>
                    </x-wirekit::card.body>

                    <x-wirekit::card.footer>
                        <x-wirekit::button intent="neutral" wire:click="close" loading-target="close">
                            {{ __('visual-feedback::browser.close') }}
                        </x-wirekit::button>
                    </x-wirekit::card.footer>
                </x-wirekit::card>
            @endif
        @endif
    </x-wirekit::stack>
</div>
