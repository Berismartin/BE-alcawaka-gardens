<?php

/*
| Upload collections. Each one fixes what may be uploaded, where it is stored,
| who may upload it and which records it may belong to.
| Switch to S3/R2/Spaces by pointing the two disks at s3 disks in .env.
*/

return [
    'disks' => [
        'public' => env('MEDIA_PUBLIC_DISK', 'public'),
        'private' => env('MEDIA_PRIVATE_DISK', 'local'),
    ],

    // Minutes a signed link to a private file stays valid.
    'private_link_minutes' => 5,

    'collections' => [
        'facility_photos' => [
            'visibility' => 'public',
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => 5120,
            'permission' => 'facilities.manage',
            'mediable' => ['facility'],
        ],
        'room_photos' => [
            'visibility' => 'public',
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => 5120,
            'permission' => 'rooms.manage',
            'mediable' => ['room_type'],
        ],
        'guest_id' => [
            'visibility' => 'private',
            'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
            'max_kb' => 8192,
            'permission' => 'guests.manage',
            'mediable' => ['guest'],
        ],
        'payment_receipt' => [
            'visibility' => 'private',
            'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
            'max_kb' => 8192,
            'permission' => 'payments.record',
            'mediable' => ['payment'],
        ],
        'hotel_logo' => [
            'visibility' => 'public',
            'mimes' => ['png', 'webp', 'jpg', 'jpeg'],
            'max_kb' => 2048,
            'permission' => 'settings.manage',
            'mediable' => [], // not attached to a record
        ],
    ],
];
