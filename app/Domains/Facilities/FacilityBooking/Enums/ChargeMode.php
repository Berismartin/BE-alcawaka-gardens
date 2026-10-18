<?php

namespace App\Domains\Facilities\FacilityBooking\Enums;

enum ChargeMode: string
{
    case Included = 'included';   // free for in-house guests
    case Folio = 'folio';         // added to the guest's room bill
    case Paid = 'paid';           // paid now at the desk
    case PayLater = 'pay_later';  // pending payment
    case Online = 'online';       // booked and paid on the website
}
