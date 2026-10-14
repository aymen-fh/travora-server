<?php

declare(strict_types=1);

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case PendingCustomer = 'pending_customer';
    case PendingSupport = 'pending_support';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
