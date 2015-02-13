<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require 'vendor/autoload.php';

use Sabre\VObject\Reader;
use Sabre\VObject\Component\VCalendar;

$feeds = [
    'http://calendar.luxeria.ch/feeds/talks.ics',
    'http://calendar.luxeria.ch/feeds/meetings.ics'
];

$vcal = new VCalendar();

foreach ($feeds as $url) {
    $data = file_get_contents($url);
    $ics = Reader::read($data);

    foreach($ics->VEVENT as $vevent) {
        $vcal->add($vevent);
    }
}

// The format of the JSON output is loosley based on
// http://fullcalendar.io/docs/event_data/Event_Object/

$events = [];

$start = new DateTime(isset($_GET['start']) ? $_GET['start'] : 'now');
$end   = new DateTime(isset($_GET['end']) ? $_GET['end'] : '+6 week');

$vcal->expand($start, $end);
if (isset($vcal->VEVENT)) {
    foreach ($vcal->VEVENT as $vevent) {
        $dtstart = $vevent->DTSTART;
        $values = [
            'id'    => (string) $vevent->UID,
            'start' => $dtstart->getJsonValue()[0],
            'title' => (string) $vevent->SUMMARY,
        ];

        $desc = (string) $vevent->DESCRIPTION;
        $url  = (string) $vevent->URL;
        if (!empty($desc))  $values['desc'] = $desc;
        if (!empty($url))   $values['url']  = $url;
        if (isset($vevent->DTEND)) {
            $values['end'] = $vevent->DTEND->getJsonValue()[0];
        }

        $events[] = $values;
    }

    // sort according to start date
    usort($events, function ($a, $b) {
        $dta = new DateTime($a['start']);
        $dtb = new DateTime($b['start']);
        return ($dta < $dtb) ? -1 : 1;
    });
}

header('Content-Type: application/json; ');
$json = json_encode($events);
if (isset($_GET['jsonp']) && preg_match('/[A-Z_$][0-9A-Z_$]*/i', $_GET["jsonp"])) {
    header('Content-Type: application/javascript; charset=utf-8');
    printf("%s(%s);", $_GET["jsonp"], $json);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
}
