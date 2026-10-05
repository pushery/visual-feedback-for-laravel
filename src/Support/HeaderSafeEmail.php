<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The validation rules for an address that ends up in a mail header: a guest's email, and the
 * reporter's address as the report mail's Reply-To.
 *
 * RFC 5322 allows comments, quoted local parts, folding whitespace and domain literals with a
 * warning only, and those are the forms that carry a CR or LF into a header or that Symfony Mime
 * refuses when it builds one. They are refused here by their characters rather than through the
 * validator's warnings: egulias/email-validator up to 4.0.3 also warns that the local part is too
 * long for every address of 66 characters and more, so a rule that turns warnings into rejections
 * refuses long, valid addresses wherever Laravel installed such a version.
 */
final class HeaderSafeEmail
{
    /** @var list<string> */
    public const array RULES = [
        'email:rfc',
        // Whitespace, a CR or LF among it, a quote, a parenthesis, a backslash or a bracket.
        'not_regex:/[\s"()\\\\\[\]]/u',
        // The local part is at most 64 octets (RFC 5321).
        'regex:/^[^@]{1,64}@/',
    ];
}
