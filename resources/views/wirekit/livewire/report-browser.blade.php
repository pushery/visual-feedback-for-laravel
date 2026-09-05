{{-- The same browser through WireKit components, so it inherits the host's design tokens.

     Component signatures were read from the INSTALLED WireKit via its MCP rather than from
     memory — `table` and its subcomponents, `attachment`, `data-list`, `empty-state`. That is
     the house rule for this tree and it is not ceremony: a prop that moved between minors is
     invisible until the page renders wrong.

     No `x-` directive here either. Every interaction is a Livewire round trip, so this template
     is outside the CSP question entirely. --}}
<div>
    @if (! $this->tableExists())
        <x-wirekit::empty-state
            :title="__('visual-feedback::browser.no_table_title')"
            :description="__('visual-feedback::browser.no_table')"
        />
    @else
        <x-wirekit::card>
            <x-wirekit::card.body>
                <x-wirekit::select wire:model.live="filterMode" :label="__('visual-feedback::browser.filter_mode')">
                    <option value="">{{ __('visual-feedback::browser.any') }}</option>
                    @foreach ($modes as $mode)
                        <option value="{{ $mode }}">{{ $mode }}</option>
                    @endforeach
                </x-wirekit::select>

                <x-wirekit::select wire:model.live="filterCategory" :label="__('visual-feedback::browser.filter_category')">
                    <option value="">{{ __('visual-feedback::browser.any') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
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

                <x-wirekit::button variant="ghost" wire:click="clearFilters">
                    {{ __('visual-feedback::browser.clear') }}
                </x-wirekit::button>
            </x-wirekit::card.body>
        </x-wirekit::card>

        @if ($reports !== null && $reports->total() === 0)
            <x-wirekit::empty-state
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
                        <x-wirekit::table.th align="right">
                            <x-wirekit::visually-hidden>{{ __('visual-feedback::browser.col_actions') }}</x-wirekit::visually-hidden>
                        </x-wirekit::table.th>
                    </x-wirekit::table.row>
                </x-wirekit::table.head>

                <x-wirekit::table.body>
                    @foreach ($reports as $report)
                        <x-wirekit::table.row wire:key="vf-report-{{ $report->uuid }}">
                            <x-wirekit::table.td>{{ $report->created_at }}</x-wirekit::table.td>
                            <x-wirekit::table.td>{{ $report->mode }}</x-wirekit::table.td>
                            <x-wirekit::table.td>{{ $report->category }}</x-wirekit::table.td>
                            <x-wirekit::table.td>{{ $report->subject ?: '—' }}</x-wirekit::table.td>
                            <x-wirekit::table.td>
                                {{ $report->reporter_name ?: __('visual-feedback::browser.guest') }}
                            </x-wirekit::table.td>
                            <x-wirekit::table.td align="right">
                                <x-wirekit::button size="sm" variant="ghost" wire:click="open('{{ $report->uuid }}')">
                                    {{ __('visual-feedback::browser.open') }}
                                </x-wirekit::button>
                                {{-- wire:confirm rather than an inline handler: Livewire's own
                                     mechanism, so this tree stays script-free under any policy. --}}
                                <x-wirekit::button
                                    size="sm"
                                    variant="danger"
                                    wire:click="delete('{{ $report->uuid }}')"
                                    wire:confirm="{{ __('visual-feedback::browser.confirm_delete') }}"
                                >
                                    {{ __('visual-feedback::browser.delete') }}
                                </x-wirekit::button>
                            </x-wirekit::table.td>
                        </x-wirekit::table.row>
                    @endforeach
                </x-wirekit::table.body>
            </x-wirekit::table>

            {{ $reports->links() }}
        @endif

        @if ($detail !== null)
            <x-wirekit::card>
                <x-wirekit::card.header>
                    {{ $detail->subject ?: __('visual-feedback::browser.untitled') }}
                </x-wirekit::card.header>

                <x-wirekit::card.body>
                    <x-wirekit::data-list>
                        <x-wirekit::data-list.item :label="__('visual-feedback::browser.col_date')">
                            {{ $detail->created_at }}
                        </x-wirekit::data-list.item>
                        <x-wirekit::data-list.item :label="__('visual-feedback::browser.col_mode')">
                            {{ $detail->mode }}
                        </x-wirekit::data-list.item>
                        <x-wirekit::data-list.item :label="__('visual-feedback::browser.col_category')">
                            {{ $detail->category }}
                        </x-wirekit::data-list.item>
                        <x-wirekit::data-list.item :label="__('visual-feedback::browser.col_reporter')">
                            {{ $detail->reporter_name ?: __('visual-feedback::browser.guest') }}
                            @if ($detail->reporter_email)
                                &lt;{{ $detail->reporter_email }}&gt;
                            @endif
                        </x-wirekit::data-list.item>
                    </x-wirekit::data-list>

                    <x-wirekit::text>{{ $detail->message }}</x-wirekit::text>

                    @foreach ($this->attachmentsOf($detail->attachments ?? null) as $path)
                        @php($vfUrl = $this->attachmentUrl($path))
                        {{-- `thumbnail` only when the disk can actually give a URL. On a private
                             disk — every shipped configuration — there is none, and the card
                             then shows the path, which is what a host needs to fetch the file
                             with their own tooling. No download route is invented here; see the
                             component docblock. --}}
                        <x-wirekit::attachment
                            :name="$path"
                            :thumbnail="$vfUrl !== null && $this->isPreviewable($path) ? $vfUrl : null"
                            :href="$vfUrl"
                        />
                    @endforeach
                </x-wirekit::card.body>

                <x-wirekit::card.footer>
                    <x-wirekit::button variant="ghost" wire:click="close">
                        {{ __('visual-feedback::browser.close') }}
                    </x-wirekit::button>
                </x-wirekit::card.footer>
            </x-wirekit::card>
        @endif
    @endif
</div>
