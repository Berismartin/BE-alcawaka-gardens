<?php

namespace App\Domains\Channels\Channel\ValueObjects;

use Carbon\CarbonImmutable;

/** Just enough iCalendar (RFC 5545) for OTA availability feeds: all-day VEVENTs. */
final class Ical
{
    /** @return list<array{uid: string, summary: string, start: string, end: string}> */
    public static function parse(string $ics): array
    {
        // Unfold continuation lines (a line starting with a space continues the previous one).
        $text = preg_replace("/\r?\n[ \t]/", '', $ics);
        $events = [];
        foreach (preg_split('/BEGIN:VEVENT/', $text) as $i => $block) {
            if ($i === 0 || ! str_contains($block, 'END:VEVENT')) {
                continue;
            }
            $field = fn (string $name) => preg_match('/^'.$name.'(?:;[^:\r\n]*)?:(.*)$/mi', $block, $m) ? trim($m[1]) : null;
            $start = self::date($field('DTSTART'));
            $end = self::date($field('DTEND')) ?? ($start ? CarbonImmutable::parse($start)->addDay()->toDateString() : null);
            if (! $start || ! $end || $end <= $start) {
                continue;
            }
            $events[] = [
                'uid' => $field('UID') ?? md5($start.$end.$field('SUMMARY')),
                'summary' => stripcslashes($field('SUMMARY') ?? 'OTA booking'),
                'start' => $start,
                'end' => $end,
            ];
        }

        return $events;
    }

    /** @param iterable<array{uid: string, start: string, end: string, summary: string}> $events */
    public static function build(string $calendarName, iterable $events): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Alcawaka Gardens//Reservations//EN', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:'.self::escape($calendarName)];
        $stamp = now()->utc()->format('Ymd\THis\Z');
        foreach ($events as $e) {
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:'.$e['uid'],
                'DTSTAMP:'.$stamp,
                'DTSTART;VALUE=DATE:'.str_replace('-', '', $e['start']),
                'DTEND;VALUE=DATE:'.str_replace('-', '', $e['end']),
                'SUMMARY:'.self::escape($e['summary']),
                'END:VEVENT',
            );
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private static function date(?string $value): ?string
    {
        if (! $value || ! preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m)) {
            return null;
        }

        return "{$m[1]}-{$m[2]}-{$m[3]}";
    }

    private static function escape(string $text): string
    {
        return addcslashes($text, ',;\\');
    }
}
