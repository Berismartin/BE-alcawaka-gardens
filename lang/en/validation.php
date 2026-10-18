<?php

// Field names as staff and guests read them in error messages ("The amount field is required."),
// merged over Laravel's built-in validation messages.
return [
    'attributes' => [
        'amount_ugx' => 'amount',
        'payment.amount_ugx' => 'amount',
        'base_rate_ugx' => 'nightly rate',
        'price_ugx' => 'price',
        'facility_id' => 'facility',
        'guest_id' => 'guest',
        'reservation_id' => 'guest',
        'room_id' => 'room',
        'room_type_id' => 'room type',
        'user_id' => 'staff member',
        'starts_at' => 'date and time',
        'party_size' => 'number of guests',
        'payment_method' => 'payment method',
        'guest.full_name' => 'name',
        'guest.phone' => 'phone',
        'guest.email' => 'email',
        'check_in' => 'check-in date',
        'check_out' => 'check-out date',
    ],
];
