<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\RequestException;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * An exception's message with what must not leave the package taken out, and the exception that
 * stands in for one whose message had to change.
 *
 * Every exception text this package writes to a log, hands to an event or throws out of a
 * delivery job goes through here. Some failures put secrets into their message. A database error
 * carries its statement with the values filled in, and for a report those are the reporter's
 * words, name and address. An HTTP error response carries its body, which can echo the payload.
 * And a client error carries the URL it called, where for a Slack, Zapier or n8n webhook the path
 * is the credential. So:
 *
 * - a database error keeps its SQLSTATE, its driver code and the connection name;
 * - an HTTP error response keeps its status code, without the body it answered with;
 * - a URL in any other message keeps its scheme, host and port, without the user info, the path,
 *   the query or the fragment.
 *
 * A delivery job throws the stand-in rather than the original, because what a job throws is what
 * the queue worker reports to the error tracker and stores in the failed jobs. The stand-in keeps
 * the original class for a listener, and leaves the original out of its chain: a previous
 * exception is printed with its message wherever the stand-in is.
 */
final class RedactedFailure extends RuntimeException
{
    private function __construct(string $message, public readonly string $originalClass)
    {
        parent::__construct($message);
    }

    /** The exception to throw in place of $exception: itself when its message is safe, a stand-in when it is not. */
    public static function standIn(Throwable $exception): Throwable
    {
        $message = self::message($exception);

        return $message === $exception->getMessage() ? $exception : new self($message, $exception::class);
    }

    /** The class of the failure, the original one for a stand-in. */
    public static function classOf(Throwable $exception): string
    {
        return $exception instanceof self ? $exception->originalClass : $exception::class;
    }

    /** The message of $exception with the statement, the response body and every URL secret taken out. */
    public static function message(Throwable $exception): string
    {
        if ($exception instanceof PDOException) {
            return self::databaseMessage($exception);
        }

        if ($exception instanceof RequestException) {
            return 'HTTP request returned status code '.$exception->response->status().'; the response body is left out.';
        }

        return self::withoutUrlSecrets($exception->getMessage());
    }

    private static function databaseMessage(PDOException $exception): string
    {
        $info = $exception->errorInfo ?? [];
        $state = is_string($info[0] ?? null) && $info[0] !== '' ? $info[0] : (string) $exception->getCode();
        $driverCode = isset($info[1]) && is_scalar($info[1]) ? ', driver code '.$info[1] : '';
        $connection = $exception instanceof QueryException ? ', on the '.$exception->getConnectionName().' connection' : '';

        return 'SQLSTATE['.$state.']'.$driverCode.$connection.'; the statement and its values are left out.';
    }

    private static function withoutUrlSecrets(string $message): string
    {
        // Everything up to the next space, quote or angle bracket after the host goes, a closing
        // parenthesis included: a path that stopped there could carry its secret past it.
        return (string) preg_replace(
            '~\b([a-z][a-z0-9+.-]*)://(?:[^\s/?#@"\'<>]*@)?([^\s/?#@"\'<>]+)[^\s"\'<>]*~i',
            '$1://$2',
            $message,
        );
    }
}
