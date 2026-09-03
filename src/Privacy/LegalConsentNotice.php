<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Privacy;

use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Pushery\LegalConsent\Contracts\ConsentManager;
use Pushery\LegalConsent\Enums\DocumentType;
use Pushery\LegalConsent\Support\TenantContext;
use Pushery\VisualFeedback\Contracts\PrivacyNoticeWordingSource;
use Pushery\VisualFeedback\Data\PrivacyNoticeWording;
use Throwable;

/**
 * The optional bridge to pushery/legal-consent: with `privacy.source = legal-consent` the guest's
 * checkbox carries the PUBLISHED acknowledgement sentence instead of this package's own lang line.
 *
 * It READS legal-consent and never writes to it. Its recording API takes a Model subject on every
 * method and this widget's whole point is GUEST reports that have none; a synthetic subject was
 * measured to be unsafe rather than merely ugly — `subject_id` is an unsignedBigInteger, and a
 * subject whose key is null collapses SubjectToken's lookup to `whereNull('subject_id')`, which
 * matches the ANONYMIZED rows and hands back the pseudonym of an erased person.
 *
 * WHICH document was acknowledged is recorded, though — with the report rather than in that ledger. It needs the acceptance fingerprint over content hash AND wording, and until
 * v0.7.0 that helper only took a LegalDocument model the documented read path never handed out; the
 * returned document now carries the fingerprint itself, so it is taken from there and never
 * recomputed here.
 *
 * `privacy.url` stays REQUIRED — the wording never makes the checkbox appear on its own. A label
 * whose full text cannot be opened is an uninformed clickwrap, which legal-consent names as a
 * defect in its own stubs, and a widget that traded a working link for a nicer sentence would be
 * legally worse than the one it replaced. So this raises the quality of the label and changes
 * nothing about when a notice is required.
 *
 * Everything below refuses rather than improvises, and every refusal falls back to this package's
 * own sentence — never to another locale's text, and never to leaving the checkbox out. A silent
 * omission would drop the acknowledgement altogether; a substituted locale would show German under
 * an English UI, exactly what legal-consent's reader is written to prevent.
 */
final readonly class LegalConsentNotice implements PrivacyNoticeWordingSource
{
    public function __construct(
        private Container $container,
        private LoggerInterface $logger,
    ) {}

    /**
     * The full-text link, straight from `privacy.url`.
     *
     * legal-consent has none to offer, and that is its design rather than an omission: with its
     * default config it registers no routes at all, and enabled they are five `['api', 'auth']`
     * endpoints whose only GET returns a status map. The page belongs to the consuming app.
     */
    public function url(): ?string
    {
        $url = $this->container->make('config')->get('visual-feedback.privacy.url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function wording(): ?PrivacyNoticeWording
    {
        // NOT class_exists on a facade. The facade class exists the moment the package is
        // required, while the manager binding arrives only with its service provider — and the
        // table only after a migration. A bare class_exists guard turns an unmigrated install
        // into a QueryException inside render(), on every public page carrying the widget.
        if (! $this->container->bound(ConsentManager::class)) {
            return null; // Not installed or not booted: the feature is off, and off is not an error.
        }

        $config = $this->container->make('config');
        $key = $config->get('visual-feedback.privacy.document_key');
        $key = is_string($key) && $key !== '' ? $key : 'privacy';
        $locale = $this->container->make('translator')->getLocale();

        try {
            $tenancy = $this->container->make(TenantContext::class);
            $document = $this->container->make(ConsentManager::class)->published($key, $locale);
        } catch (Throwable $e) {
            // legal-consent promises never to throw for a MISSING publication; it promises nothing
            // about a missing table or an unreachable connection. A guest page must not 500 over
            // the wording of a checkbox.
            return $this->fallback('reading the published legal-consent document failed', [
                'document_key' => $key,
                'locale' => $locale,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        if ($document === null) {
            // Deliberate on their side: the read path has NO locale fallback, because "the page
            // must show the text of the locale it claims to be showing, or nothing". This package
            // ships seven locales and legal-consent's default config publishes two, so this is the
            // ordinary case rather than an edge one — hence a notice, not an error.
            $this->logger->notice('visual-feedback: no legal-consent document published for this key and locale — the built-in acknowledgement wording is used', [
                'document_key' => $key,
                'locale' => $locale,
            ]);

            return null;
        }

        if ($tenancy->enabled() && $document->tenantId !== $tenancy->current()) {
            // Belt and braces, and worth the line. legal-consent v0.7.0 confines reads to the
            // current tenant with a global scope on its model, so this should be unreachable — but
            // a global scope is exactly the kind of protection a `withoutGlobalScopes()` somewhere
            // up the stack removes silently, and the failure would be a guest of one tenant reading
            // another tenant's legal text with nothing to notice it. The DTO started carrying
            // `tenantId` in that same release for precisely this confirmation, so taking it is the
            // intended use rather than distrust.
            return $this->fallback('the published legal-consent document belongs to another tenant', [
                'document_key' => $key,
                'expected_tenant' => $tenancy->current(),
                'document_tenant' => $document->tenantId,
            ]);
        }

        if ($document->type !== DocumentType::PrivacyNotice) {
            // "I have read …" over a contract or an opt-in misstates what the reporter is doing,
            // and an opt-in must never be a precondition at all (Art. 7(4)).
            return $this->fallback('the configured legal-consent document is not a privacy notice, so presenting it as a passive acknowledgement would misstate it', [
                'document_key' => $key,
                'type' => $document->type->value,
            ]);
        }

        if ($document->noticeMode->gates()) {
            // A gating mode means the old text stays in force until the subject actively agrees.
            // Upstream forbids publishing a privacy notice that way, but the mode is DERIVED for a
            // legacy row (notice_mode null + requires_reconsent true), so the combination is
            // reachable — and a passive checkbox would claim a legal effect that does not occur.
            return $this->fallback('the configured legal-consent document is in a gating notice mode, which a passive acknowledgement cannot express', [
                'document_key' => $key,
                'notice_mode' => $document->noticeMode->value,
            ]);
        }

        // Held in a local so the emptiness check below and the value handed on are the SAME
        // string. Reading the property twice would leave the second read `?string` again, and
        // the only ways out of that are a cast or a suppression — both of which turn a checked
        // value back into an asserted one.
        $wording = $document->uiWording ?? '';

        if (trim($wording) === '') {
            // Two different ways to arrive at nothing, folded into one refusal because the
            // consequence is identical: a checkbox with no accessible name.
            //
            // EMPTY was the original case (upstream's column is NOT NULL with no CHECK, and the
            // translation branch of their render pipeline tests only "not the key", never "not
            // empty"), so a host that publishes its lang files and blanks the sentence freezes an
            // empty one into an immutable document.
            //
            // NULL arrived with legal-consent v0.10.0, which made `ui_wording` nullable: an
            // `informational` document — an Impressum, a cookie policy — asks the reader for
            // nothing and therefore carries no sentence, where it used to freeze the default one
            // permanently. Their release notes say plainly that code rendering the wording of an
            // arbitrary document needs a null check.
            //
            // Here it is defense in depth rather than the load-bearing guard: the type check
            // above already refuses anything that is not a privacy notice, and `informational` is
            // the only class upstream lets carry no sentence. It stays for the same reason the
            // tenant check does — the reachability argument rests on an upstream invariant this
            // package cannot see, and the failure it would prevent is a TypeError on a public
            // page. Caught by PHPStan on the 0.9 -> 0.10 bump, not by a test: no test could have
            // one, because the combination is unreachable through the documented path.
            return $this->fallback('the published legal-consent document has no acceptance sentence', [
                'document_key' => $key,
                'version' => $document->version,
            ]);
        }

        return new PrivacyNoticeWording(
            text: $wording,
            key: $document->key,
            locale: $document->locale,
            version: $document->version,
            // legal-consent's OWN value, never recomputed here — see the note on the DTO.
            acceptanceFingerprint: $document->acceptanceFingerprint(),
        );
    }

    /**
     * Fall back to this package's own sentence, loudly.
     *
     * A warning rather than an exception, because there is no earlier place to throw from: the
     * source is resolved while rendering a guest's widget, so failing hard here would answer a
     * misconfiguration with a 500 on a public page. Nothing is lost by falling back — the
     * acknowledgement still happens, the link is still there, and because this returns null the
     * caller uses its own wording and can never attribute it to a published document.
     *
     * The return type is `null` and not `?string` on purpose: this method has exactly one answer,
     * and typing it as "maybe a string" would invite a future refusal to return the published
     * sentence anyway — which is the one thing a refusal must never do.
     *
     * @param  array<string, mixed>  $context
     */
    private function fallback(string $reason, array $context): null
    {
        // Loud for the same reason AbuseGateRegistry is loud about an unregistered driver: the
        // alternative is a configuration that looks honored and is not.
        $this->logger->warning('visual-feedback: '.$reason.' — the built-in acknowledgement wording is used instead', $context);

        return null;
    }
}
