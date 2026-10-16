<?php

namespace App\Domains\Content\ContentEntry\Enums;

/** What a website content entry is. Each type keeps its own extra fields in `data`. */
enum ContentType: string
{
    case EventType = 'event_type';   // weddings, introductions, corporate... (events page)
    case Event = 'event';            // dated "what's on" listings
    case Gallery = 'gallery';
    case Testimonial = 'testimonial';
    case Faq = 'faq';

    /** @return array<string, array<int, mixed>> validation for `data.*` */
    public function dataRules(): array
    {
        return match ($this) {
            self::EventType => [
                'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'price_from_ugx' => ['nullable', 'integer', 'min:0'],
                'features' => ['nullable', 'array', 'max:12'],
                'features.*' => ['string', 'max:120'],
            ],
            self::Event => [
                'starts_at' => ['required', 'date_format:Y-m-d H:i'],
                'location' => ['nullable', 'string', 'max:120'],
                'price_label' => ['nullable', 'string', 'max:60'],
            ],
            self::Gallery => ['category' => ['nullable', 'string', 'max:40']],
            self::Testimonial => ['rating' => ['nullable', 'integer', 'min:1', 'max:5']],
            self::Faq => [],
        };
    }

    public function requiresImage(): bool
    {
        return $this === self::Gallery;
    }
}
