<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels\Mail;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Can the configured mailer actually put a message on the wire?
 *
 * `Mailer::send()` SUCCEEDS on the `log`, `array` and `null` transports — that is their correct
 * behavior, not a fault. So a report rendered into `laravel.log` walks the whole happy path: no
 * exception, no retry, no `failed_job`, a DELIVERED receipt, a `ReportDelivered` event, the
 * attachment refcount released, and a success state in the widget. From the outside a swallowed
 * delivery is indistinguishable from a real one at every single seam.
 *
 * And a host reaches that state by doing NOTHING: Laravel's own `config/mail.php` reads
 * `env('MAIL_MAILER', 'log')`, and this package ships `channels.mail` enabled with
 * `channels.database` disabled — so on a default install the mail channel is the only one there
 * is, and its transport is the one that drops the message.
 *
 * A fan-out transport is resolved into its MEMBERS rather than trusted by its own name. A
 * `failover` that ends on `log` works for as long as the real transport has a good day and
 * reports success from the moment it does not, which is the same defect with a delay on it.
 *
 * What this deliberately does NOT do is refuse an unknown or unconfigured mailer. Laravel throws
 * `Mailer [x] is not defined.` at send time, the job retries, `failed()` runs and the receipt
 * settles FAILED — that is an honest failure with a trail, and turning it into a silent skip
 * would remove the trail.
 */
final readonly class TransportDeliverability
{
    /**
     * The transports that accept a message and deliver it nowhere.
     *
     * `smtp` against an unreachable host is NOT one of them: it throws, and a throw is the
     * honest outcome this class exists to preserve.
     */
    private const array NON_DELIVERING = ['log', 'array', 'null'];

    /** Transports that are a list of other mailers rather than a destination. */
    private const array FAN_OUT = ['failover', 'roundrobin'];

    public function __construct(private Config $config) {}

    /**
     * The non-delivering transports the configured mailer can end up on, in configuration order.
     *
     * An empty list means "nothing here silently drops a message" — which includes the cases
     * where the answer could not be determined at all, on purpose (see the class docblock).
     *
     * @return list<string>
     */
    public function nonDeliveringTransports(): array
    {
        $default = $this->config->get('mail.default');

        if (! is_string($default) || $default === '') {
            return [];
        }

        return $this->resolve($default, []);
    }

    /**
     * @param  list<string>  $seen  mailer names already visited, so a `failover` naming itself
     *                              cannot recurse forever. A cycle is a host's configuration
     *                              mistake, and a stack overflow is a worse answer than none.
     * @return list<string>
     */
    private function resolve(string $mailer, array $seen): array
    {
        if (in_array($mailer, $seen, true)) {
            return [];
        }

        $seen[] = $mailer;

        $transport = $this->config->get("mail.mailers.{$mailer}.transport");

        if (! is_string($transport) || $transport === '') {
            return []; // Not configured — Laravel will say so loudly at send time.
        }

        if (in_array($transport, self::FAN_OUT, true)) {
            $members = $this->config->get("mail.mailers.{$mailer}.mailers");

            $found = [];

            foreach (is_array($members) ? $members : [] as $member) {
                if (is_string($member) && $member !== '') {
                    $found = [...$found, ...$this->resolve($member, $seen)];
                }
            }

            return array_values(array_unique($found));
        }

        return in_array($transport, self::NON_DELIVERING, true) ? [$transport] : [];
    }
}
