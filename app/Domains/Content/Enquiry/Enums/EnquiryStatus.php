<?php

namespace App\Domains\Content\Enquiry\Enums;

enum EnquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Booked = 'booked';
    case Closed = 'closed';
}
