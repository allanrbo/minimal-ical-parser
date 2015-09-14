<?php

function _parse_ical_expand_escapes($s) {
    if($s == null) {
        return null;
    }

    $s = htmlentities($s);
    $s = str_replace('\,', ',', $s);
    $s = str_replace('\n', "<br/>", $s);
    return $s;
}


function _parse_ical_generate_time_desc($event) {
    $startTime = $event['dtstart'];
    $endTime = $event['dtend'];

    $startTimePlusOneDay = clone $startTime;
    $startTimePlusOneDay->modify('+1 day');

    $endTimeMinusOneMinute = clone $endTime;
    $endTimeMinusOneMinute->modify('-1 minute');

    if($startTime->format('H:i') == '00:00' && $startTimePlusOneDay == $endTime) {
        // Full day event
        $time_desc = strftime('%x', $startTime->getTimestamp());
    } else if($startTime->format('Y-m-d') == $endTime->format('Y-m-d')) {
        // Event within one date
        $time_desc = strftime('%x', $startTime->getTimestamp());
        $time_desc .= ', ';
        $time_desc .= strftime('%k:%M', $startTime->getTimestamp());
        $time_desc .= ' - ';
        $time_desc .= strftime('%k:%M', $endTime->getTimestamp());
    } else {
        // Multi-day event
        $time_desc = strftime('%x', $startTime->getTimestamp());
        $time_desc .= ' - ';
        $time_desc .= strftime('%x', $endTimeMinusOneMinute->getTimestamp());
    }

    return $time_desc;
}


function _parse_ical_event_date_comparer($event1, $event2) {
    $t1 = $event1['dtstart']->getTimestamp();
    $t2 = $event2['dtstart']->getTimestamp();
    if($t1 == $t2) return 0;
    else if($t1 > $t2) return 1;
    return -1;
}


function parse_ical($filename) {
    $lines = explode("\n", str_replace("\r", '', file_get_contents($filename)));
    $events = array();
    $current_event = array();
    $current_field = null;

    foreach($lines as $line) {
        if($line == 'END:VEVENT') {
            $current_event['description'] = _parse_ical_expand_escapes($current_event['description']);
            $current_event['summary'] = _parse_ical_expand_escapes($current_event['summary']);
            $current_event['location'] = _parse_ical_expand_escapes($current_event['location']);
            $current_event['time_desc'] = _parse_ical_generate_time_desc($current_event);

            if(time() < $current_event['dtend']->getTimestamp()) {
                $events[] = $current_event;
            }

            $current_event = array();
            $current_field = null;

            continue;
        }

        if(preg_match('/DTSTART:(.*)/', $line, $matches) > 0) {
            $current_event['dtstart'] = DateTime::createFromFormat('Ymd?His? T', $matches[1] . ' UTC');
            continue;
        }

        if(preg_match('/DTSTART;VALUE=DATE:(.*)/', $line, $matches) > 0) {
            $current_event['dtstart'] = DateTime::createFromFormat('Ymd?His? T', $matches[1] . 'T000000Z UTC');
            continue;
        }

        if(preg_match('/DTSTART;TZID=([^:]*):(.*)/', $line, $matches) > 0) {
            $current_event['dtstart'] = DateTime::createFromFormat('Ymd?His', $matches[2], new DateTimeZone($matches[1]));
            continue;
        }

        if(preg_match('/DTEND:(.*)/', $line, $matches) > 0) {
            $current_event['dtend'] = DateTime::createFromFormat('Ymd?His? T', $matches[1] . ' UTC');
            continue;
        }

        if(preg_match('/DTEND;VALUE=DATE:(.*)/', $line, $matches) > 0) {
            $current_event['dtend'] = DateTime::createFromFormat('Ymd?His? T', $matches[1] . 'T000000Z UTC');
            continue;
        }

        if(preg_match('/DTEND;TZID=([^:]*):(.*)/', $line, $matches) > 0) {
            $current_event['dtend'] = DateTime::createFromFormat('Ymd?His', $matches[2], new DateTimeZone($matches[1]));
            continue;
        }

        if(preg_match('/DESCRIPTION:(.*)/', $line, $matches) > 0) {
            $current_event['description'] = $matches[1];
            $current_field = 'description';
            continue;
        }

        if(preg_match('/SUMMARY:(.*)/', $line, $matches) > 0) {
            $current_event['summary'] = $matches[1];
            $current_field = 'summary';
            continue;
        }

        if(preg_match('/LOCATION:(.*)/', $line, $matches) > 0) {
            $current_event['location'] = $matches[1];
            $current_field = 'location';
            continue;
        }

        // lines starting with spaces are continuation lines
        if(preg_match('/ (.+)/', $line, $matches) > 0) {
            if($current_field != null) {
                $current_event[$current_field] .= $matches[1];
            }
            continue;
        }
    }

    usort($events, '_parse_ical_event_date_comparer');

    return $events;
}
