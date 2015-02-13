<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require 'vendor/autoload.php';

use Mf2\Parser;
use Sabre\VObject\Component\VCalendar;

// fetch only microdata 2 items
$mf = Mf2\fetch('http://wiki.luxeria.ch/talks:calendar', false);

// we are only interested in h-events
$items = array_filter($mf['items'], function ($item) {
    return in_array('h-event', $item['type']);
});

// flatten and require singular semantic properties
$hevents = array_map(function ($item) {
    return array_map(function ($property) {
        return trim($property[0]);
    }, $item['properties']);
}, $items);

// sadly, sabre vobject has no support for timezones
$timezone = new \DateTimeZone('Europe/Zurich');

$vcal = new VCalendar();
$vcal->METHOD = "PUBLISH";
$vcal->{'X-WR-CALNAME'} = "Luxeria Vorträge";
$vcal->{'X-WR-TIMEZONE'} = 'Europe/Zurich';

$vtimezone = $vcal->createComponent('VTIMEZONE');
$vtimezone->TZID = 'Europe/Zurich';
$vtimezone->{'X-WR-LOCATION'} = 'Europe/Zurich';

$cest = $vcal->createComponent('DAYLIGHT');
$cest->TZOFFSETFROM = '+0100';
$cest->TZOFFSETTO = '+0200';
$cest->TZNAME = 'CEST';
$cest->DTSTART = '19700329T020000';
$cest->RRULE = 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU';
$vtimezone->add($cest);

$cet = $vcal->createComponent('STANDARD');
$cet->TZOFFSETFROM = '+0200';
$cet->TZOFFSETTO = '+0100';
$cet->TZNAME = 'CET';
$cet->DTSTART = '19701025T030000';
$cet->RRULE = 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU';
$vtimezone->add($cet);

$vcal->add($vtimezone);

foreach ($hevents as $hevent) {
    $vevent = $vcal->createComponent('VEVENT');
    $vevent->UID = $hevent['x-uid'];
    $vevent->SUMMARY = $hevent['name'];
    $vevent->DTSTART = new \DateTime($hevent['start'], $timezone);
    $vevent->{'LAST-MODIFIED'} = new \DateTime($hevent['x-lastmod']);
    $vevent->URL = $hevent['url'];

    // make sure set value type to DATE if we only have a date
    $start = new \DateTime($hevent['start'], $timezone);
    if(preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $hevent['start'])) {
        $vevent->DTSTART = $start->format('Ymd');
        $vevent->DTSTART['VALUE'] = 'DATE';
    } else {
        $vevent->DTSTART = $start;
    }

    // add lecturers as attendees (because multiple attendees are supported)
    $attendees = array_map('trim', explode(',', $hevent['x-lecturer']));
    foreach ($attendees as $attendee) {
        // Google Calendar seems to support mailto URIs only
        $uri = 'mailto:'.urlencode($attendee).'@wiki.luxeria';
        $cn = $attendee;
        $prop = $vcal->createProperty('ATTENDEE', $uri, ['CN' => $cn]);
        $vevent->add($prop);
    }

    $vcal->add($vevent);
}

header('Content-type: text/calendar; charset=utf-8');
echo $vcal->serialize();
