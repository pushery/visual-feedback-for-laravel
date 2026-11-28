{{-- The Cloudflare Turnstile widget of the `turnstile` driver this package ships, rendered into the
     challenge region of both view trees while the driver is on.

     The package's script renders the widget into the container and puts each token into the
     hidden field, which Livewire reads through `wire:model` and the standalone form sends under
     its name. Cloudflare's own script loads once this container is drawn, when the reporter opens the
     form, so a page that only carries a closed widget contacts no one. Under a content security
     policy, `script-src` and `frame-src` have to allow https://challenges.cloudflare.com. --}}
<div class="visual-feedback-turnstile"
     data-sitekey="{{ app(\Pushery\VisualFeedback\Support\Settings::class)->turnstileSiteKey() }}"
     data-action="{{ \Pushery\VisualFeedback\Abuse\TurnstileAbuseGate::ACTION }}"
     x-init="vfTurnstile($el)"></div>
<input type="hidden" name="challenge[token]" wire:model="challenge.token" data-vf-turnstile-token>
