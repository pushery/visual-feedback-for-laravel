<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Channels;

/** The per-channel delivery status recorded in a report's receipt map. */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
