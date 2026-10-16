<?php

namespace Database\Seeders;

use App\Models\Content\ContentEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Starter website content so the site looks finished before the client sends their own.
 * Photos are free Unsplash images; staff replace them from Portal → Website.
 * Prices and capacities are placeholders: confirm them with the client.
 */
class ContentSeeder extends Seeder
{
    private function photo(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1600&q=80";
    }

    public function run(): void
    {
        $eventTypes = [
            ['Weddings', 'Say "I do" under the trees, then dance on the lawn until late.', "From the ceremony arch to the last dance, our main lawn is made for weddings. Hold the vows in the garden, take photos by the pool and move to the tented reception as the sun sets.\n\nWe work with your planner or recommend trusted Ugandan decorators, caterers, MCs and bands.", '1783981914249-d6c44b10dc7f', 600, 3500000, ['Ceremony and reception on one site', 'Bridal changing room', 'Space for tents up to 600 guests', 'Generator back-up power', 'Secure parking for 150 cars']],
            ['Introductions (Kwanjula & Kuhingira)', 'Room for both families, the tents, the gifts and the procession.', "Introduction ceremonies need space: for two families, for the gift procession and for the celebration that follows. Our lawns fit large tents with a clear walkway, and the shaded side garden is a calm place for the bride's party to prepare.\n\nWe can arrange tents, chairs, décor and catering, or work with your own Ssenga and organising committee.", '1780847614316-c9e933e9a9e0', 800, 3000000, ['Separate tents for each family', 'Procession walkway', 'Changing rooms', 'Catering for large numbers', 'Ample parking']],
            ['Corporate events & retreats', 'Meetings, launches, team building and end-of-year parties.', "Swap the boardroom for the garden. Our meeting space seats up to 80 with a projector and Wi-Fi, and the lawns are ready for team-building games, product launches and staff parties.\n\nDay packages include tea breaks and lunch. Out-of-town teams can stay in our rooms.", '1573164574572-cb89e39749b4', 300, 45000, ['Meeting room with projector and Wi-Fi', 'Day packages with tea and lunch', 'Team-building lawn', 'Rooms for overnight retreats', 'PA system']],
            ['Birthday parties', 'From first birthdays to 50th celebrations.', "Children love the open lawn and supervised pool. Grown-ups love that we handle the cake table, the décor and the clean-up.\n\nBring your own theme or choose one of our packages.", '1648556141401-eb1389a12571', 200, 800000, ['Kids play area', 'Supervised pool time', 'Décor and cake table', 'Catering packages']],
            ['Graduation parties', 'Celebrate the gown, the cap and the hard work behind it.', 'Book a shaded corner of the garden for family and friends, with a photo spot, music and food. Popular during Makerere, Kyambogo and UCU graduation weeks, so book early.', '1612214495858-4f32b96155a7', 300, 1200000, ['Photo spot with garden backdrop', 'Sound system and MC on request', 'Buffet or barbecue menus']],
            ['Garden parties & get-togethers', 'Family reunions, baby showers, bridal showers and Sunday lunches.', 'Smaller groups can reserve a private garden area with low tables, cushions and a picnic-style setup, or a long table under the trees.', '1790699591929-506c3c3bd020', 80, 500000, ['Private garden areas', 'Picnic and long-table setups', 'Drinks packages']],
        ];
        foreach ($eventTypes as $i => [$title, $summary, $body, $photo, $capacity, $price, $features]) {
            $this->entry('event_type', $title, $i, [
                'summary' => $summary, 'body' => $body, 'image_url' => $this->photo($photo),
                'data' => ['capacity' => $capacity, 'price_from_ugx' => $price, 'features' => $features],
            ]);
        }

        $friday = Carbon::now('Africa/Kampala')->next(Carbon::FRIDAY);
        $events = [
            ['Friday Garden Nights', 'Live band, nyama choma and cold drinks under the stars.', $friday->copy()->setTime(18, 30), 'Main lawn', 'Free entry', '1784202388318-a2caaeffe4b7'],
            ['Sunday Family Lunch', 'Buffet lunch with a pool pass for the children.', $friday->copy()->addDays(2)->setTime(12, 30), 'Restaurant and pool', 'UGX 45,000 per adult', '1643856557143-09189e523fd6'],
            ['Wedding Open Day', 'Meet decorators, caterers and photographers, and walk the venue set up for a wedding.', $friday->copy()->addDays(15)->setTime(10, 0), 'Main lawn', 'Free, please register', '1772127822562-a898d9f5733c'],
        ];
        foreach ($events as $i => [$title, $summary, $at, $location, $price, $photo]) {
            $this->entry('event', $title, $i, [
                'summary' => $summary, 'image_url' => $this->photo($photo),
                'data' => ['starts_at' => $at->format('Y-m-d H:i'), 'location' => $location, 'price_label' => $price],
            ]);
        }

        $gallery = [
            ['Garden ceremony', 'Weddings', '1695281536457-01f9a07c575b'],
            ['The first dance', 'Weddings', '1784202388427-5c92c7a165a6'],
            ['Signing the register', 'Weddings', '1783981914249-d6c44b10dc7f'],
            ['Wedding party', 'Weddings', '1784202388218-82a9b7b2fe74'],
            ['Introduction ceremony', 'Introductions', '1660675133902-acd1b057f75d'],
            ['Families together', 'Introductions', '1780847614316-c9e933e9a9e0'],
            ['Traditional attire', 'Introductions', '1661332517932-2d441bfb2994'],
            ['Reception tables', 'Venue', '1707333512379-8f70ad28dca4'],
            ['Ceremony arch', 'Venue', '1772127822562-a898d9f5733c'],
            ['The gazebo', 'Venue', '1523438885200-e635ba2c371e'],
            ['Long table dinner', 'Venue', '1533120921505-7f40f5237ee1'],
            ['Pool and palms', 'Gardens', '1645447556627-137df9819feb'],
            ['Pool at night', 'Gardens', '1790737434221-e8b259499c31'],
            ['Set up under the trees', 'Venue', '1578298880489-ff269cea9894'],
            ['Under the leaves', 'Gardens', '1470058869958-2a77ade41c02'],
            ['Birthday cake', 'Parties', '1648556141401-eb1389a12571'],
            ['Graduation day', 'Parties', '1612214495858-4f32b96155a7'],
            ['Picnic setup', 'Parties', '1790699591929-506c3c3bd020'],
        ];
        foreach ($gallery as $i => [$title, $category, $photo]) {
            $this->entry('gallery', $title, $i, ['image_url' => $this->photo($photo), 'data' => ['category' => $category]]);
        }

        $testimonials = [
            ['Sarah & Daniel Ssempijja', 'Wedding, 450 guests', 'The garden looked like a dream. The team handled the tents, the generator and even the rain scare without us noticing anything. Our guests still talk about it.'],
            ['Grace Atim', 'Kwanjula', 'Both families fitted comfortably and the procession had a beautiful path. Parking was easy, which our elders really appreciated.'],
            ['Brian Mugisha', 'Company retreat, MTN team', 'We had meetings in the morning and team building on the lawn in the afternoon. The food was excellent and the rooms were quiet and clean.'],
            ['Juliet Namubiru', '40th birthday', 'Stress-free from start to finish. The kids spent the whole day in the pool and the adults danced until midnight.'],
        ];
        foreach ($testimonials as $i => [$name, $what, $quote]) {
            $this->entry('testimonial', $name, $i, ['summary' => $what, 'body' => $quote, 'data' => ['rating' => 5]]);
        }

        $faqs = [
            ['Can we visit before booking?', 'Yes. Site visits are free, every day from 9:00 am to 5:00 pm. Call or WhatsApp us to book a time so an events coordinator can walk you round.'],
            ['Can we bring our own caterer and decorator?', 'Yes. You can use your own vendors, or choose from our list of trusted partners. Outside vendors pay a small set-up fee that covers power and clean-up.'],
            ['What happens if it rains?', 'We keep tents on standby and the main lawn drains quickly. Our team will plan a wet-weather layout with you in advance.'],
            ['Is there power back-up?', 'Yes. A standby generator covers lighting, sound and the kitchen.'],
            ['How do we pay?', 'A 30% deposit secures your date. Pay by MTN Mobile Money, Airtel Money, bank transfer or card. The balance is due one week before the event.'],
            ['Do you have rooms for guests?', 'Yes. Our garden rooms are perfect for the bridal party and out-of-town guests. Ask about group rates when you book an event.'],
        ];
        foreach ($faqs as $i => [$q, $a]) {
            $this->entry('faq', $q, $i, ['body' => $a]);
        }
    }

    /** @param array<string, mixed> $fields */
    private function entry(string $type, string $title, int $order, array $fields): void
    {
        ContentEntry::updateOrCreate(
            ['type' => $type, 'slug' => Str::slug($title)],
            ['title' => $title, 'sort_order' => $order + 1, 'is_published' => true, ...$fields],
        );
    }
}
