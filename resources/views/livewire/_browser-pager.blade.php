{{-- The report browser's pager in the plain tree, handed to `$reports->links()` by its view.
     The list runs newest first, so the two directions are named after time: the page before holds
     newer reports and the page after older ones, where "Previous" and "Next" would say the
     opposite of what the buttons do. The actions are the component's own paging methods, called
     with the paginator's page name. --}}
@if ($paginator->hasPages())
    <nav class="visual-feedback-browser-pager" aria-label="{{ __('visual-feedback::browser.pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="visual-feedback-browser-pager-edge" aria-disabled="true">{{ __('visual-feedback::browser.newer') }}</span>
        @else
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled">{{ __('visual-feedback::browser.newer') }}</button>
        @endif

        <span class="visual-feedback-browser-pager-position">{{ __('visual-feedback::browser.page_of', ['page' => $paginator->currentPage(), 'pages' => $paginator->lastPage()]) }}</span>

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled">{{ __('visual-feedback::browser.older') }}</button>
        @else
            <span class="visual-feedback-browser-pager-edge" aria-disabled="true">{{ __('visual-feedback::browser.older') }}</span>
        @endif
    </nav>
@endif
