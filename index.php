<?php
$env = parse_ini_file(".env");

$ical = "BEGIN:VCALENDAR
VERSION:2.0
NAME:Esports Calendar
PRODID:-//Errorname//NONSGML esports//EN
";

$httpContext = stream_context_create(array(
  'http'=>array(
    'method'=>"GET",
    'header'=>"Accept: application/json\r\n" .
              "Authorization: Bearer " . $env["PANDASCORE_TOKEN"] . "\r\n" 
  )
));

$teams = explode(",", $_GET["teams"] ?? "");

foreach ($teams as $team) {
  $matches = json_decode(file_get_contents("https://api.pandascore.co/matches?filter[opponent_id]=" . $team . "&page=1&per_page=20", false, $httpContext));

  foreach ($matches as $match) {
    $start = str_replace(array(":", "-"), "", $match->scheduled_at);

    $duration = "PT" . $match->number_of_games . "H";

    $teamA = array_values(array_filter($match->opponents, function($opponent) {
      global $team;
      return $opponent->opponent->slug == $team;
    }));
    $teamA = array_shift($teamA);

    $teamB = array_values(array_filter($match->opponents, function($opponent) {
      global $team;
      return $opponent->opponent->slug != $team;
    }));
    $teamB = array_shift($teamB);

    $serie_name = $match->league->name . " " . $match->serie->full_name . " - " . $match->tournament->name;

    $stream = array_values(array_filter($match->streams_list, function($stream) {
      return $stream->language == "fr";
    }));
    $stream = array_shift($stream);

    $ical .= "BEGIN:VEVENT\r\n" .
              "UID:" . $match->id ."-esports-calendar@errorna.me\r\n" .
              "DTSTAMP:" . gmdate('Ymd').'T'. gmdate('His') . "Z\r\n" .
              "DTSTART:" . $start . "\r\n" .
              "DURATION:" . $duration . "\r\n" .
              "SUMMARY:[" . $match->videogame->name . "] " . $teamA->opponent->acronym . " vs " . $teamB->opponent->acronym . " - " . $serie_name . "\r\n" .
              ($stream ? "URL:".$stream->raw_url."\r\n" : "") .
              "END:VEVENT\r\n";
  }
}

$ical .= "END:VCALENDAR";

header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename=calendar.ics');
echo $ical;
