<?php

/*
WCA Competitions iCal Scrape
Lucas Garron, August 04, 2011
*/

//header("Content-type: text/calendar");
header("Content-type: text/plain");

$results_url = "http://www.worldcubeassociation.org/results";
$competition_list_url = "{$results_url}/competitions.php";
$competitions_base_url = "{$results_url}/c.php?i=";

$cache_folder = "cache";

if (!is_dir($cache_folder)) {
  mkdir($cache_folder);
}


/*  Helper Functions  */


# Based on http://stackoverflow.com/questions/5262857/5-minute-file-cache-in-php
function url_get_contents_cached($url, $days_to_cache) {
  global $cache_folder;

  $cache_file = "{$cache_folder}/".preg_replace("/[^A-Za-z0-9]/", "-", $url)."_".md5($url).".html";

  if (file_exists($cache_file) && (filemtime($cache_file) > (time() - 60 * 60 * 24 * $days_to_cache ))) {
     // Cache file is less than five minutes old. 
     // Don't bother refreshing, just use the file as-is.
     $file = file_get_contents($cache_file);
  } else {
     // Our cache is out-of-date, so load the data from our remote server,
     // and also save it over our cache for next time.
     $file = file_get_contents($url);
     $fp =  fopen($cache_file, "w");
     fwrite($fp, $file);
     fclose($fp);
  }

  return $file;

};

function get_regex($html, $regex) {
  preg_match_all($regex, $html, $matches);
  if (count($matches[1]) == 0) {
    return "";
  }
  $out = $matches[1][0];
  $out = html_entity_decode($out);
  $out = preg_replace("/<[^>]*>/", "", $out); // Strip out tags
  return $out;
}

$space = "[ ]*";
$td = "{$space}<td[^>]*>{$space}";
$ttd = "{$space}<\/td[^>]*>{$space}";
$tag_match = "(.*)";

function get_info($html, $str) {
  global $space, $td, $ttd, $tag_match;
  return get_regex($html, "/{$td}{$str}{$ttd}{$space}{$td}{$tag_match}{$ttd}/");
}

function iCalUIDHash($text) {
  srand(fmod(hexdec(md5($text)), getrandmax()));
  $out = "";
  for ($i = 0; $i < 32; $i++) {
    $htmlarVal = rand(0, 35);
    if ($htmlarVal >= 10) {
      $htmlarVal = chr($htmlarVal - 10 + 65);    
    }
    $out .= $htmlarVal;
    if ($i == 7 || $i == 11 || $i == 15 || $i == 19){
      $out .= "-";
    }
  }
  return $out;
}


/*  Start creating the calendar file. */


$competition_list_html = url_get_contents_cached($competition_list_url, 1);

$competition_pattern = "/<tr>.*c\.php\?i=([^\']*)\'.*<\/tr>/";
preg_match_all($competition_pattern, $competition_list_html, $matches);

$competitions = $matches[1];


$cal_string_start = "";

$calendar_name = "WCA";
$calendar_uid = "FA600E4B-3472-4167-B61C-C6E63D4962D2";

$cal_string_start .= "BEGIN:VCALENDAR\n";
$cal_string_start .= "METHOD:PUBLISH\n";
$cal_string_start .= "CALSCALE:GREGORIAN\n";
$cal_string_start .= "X-WR-CALNAME:$calendar_name\n";
$cal_string_start .= "X-WR-RELCALID:$calendar_uid\n";
$cal_string_start .= "VERSION:2.0\n";

echo $cal_string_start;
flush();

foreach ($competitions as $key => $competition_id) {
  global $space, $td, $ttd, $tag_match;
  global $competitions_base_url;

  //Competition HTML
  $html = url_get_contents_cached($competitions_base_url.$competition_id, 3);

  $competition_name = get_regex($html, "/<h1>{$tag_match}<\/h1>/");

  $months = array(
    "Jan" => 1,
    "Feb" => 2,
    "Mar" => 3,
    "Apr" => 4,
    "May" => 5,
    "Jun" => 6,
    "Jul" => 7,
    "Aug" => 8,
    "Sep" => 9,
    "Oct" => 10,
    "Nov" => 11,
    "Dec" => 12
  );

  $month_regex = "".implode(array_keys($months), "|")."";

  $date_pattern = "/({$month_regex}) (\d*)(-(\d*))?, (\d*)/";
  preg_match_all($date_pattern, $html, $matches);

  $start_date = sprintf("%04d%02d%02d", $matches[5][0], $months[$matches[1][0]], $matches[2][0]);
  if ($matches[4] == "") {
    $early_end_date = $start_date;
  }
  else {
    $early_end_date = sprintf("%04d%02d%02d", $matches[5][0], $months[$matches[1][0]], $matches[4][0]);
  }
  $end_date = date("Ymd", strtotime($early_end_date." + 1 day"));

  $competition_venue = get_info($html, "City")." - ".get_info($html, "Venue");

  $competition_wca_website = $competitions_base_url.$competition_id;
  $competition_website = get_regex($html, "/{$td}Website{$ttd}{$space}{$td}.*href=\'(http[^\']*)\'.*{$ttd}/");
  
  $competition_description = "Competition Details:\\n";
  $competition_description .= get_info($html, "Information")."\\n";
  $competition_description .= "- Location: ".$competition_venue."\\n";
  $competition_description .= "- Organiser: ".get_info($html, "Organiser")."\\n";
  $competition_description .= "- WCA Delegate: ".get_info($html, "WCA Delegate")."\\n";
  $competition_description .= "- WCA Website: ".$competition_wca_website."\\n";
  $competition_description .= "- Competition Website: ".$competition_website;

  $competition_description = preg_replace("/\n+/", "\n", $competition_description);

  $competition_event_string = "";
  $competition_event_string .= "BEGIN:VEVENT"."\n";
  $competition_event_string .= "SEQUENCE:0"."\n";
  $competition_event_string .= "DESCRIPTION:".$competition_description."\n";
  $competition_event_string .= "UID:".iCalUIDHash($competition_id)."\n";
  $competition_event_string .= "TRANSP:TRANSPARENT"."\n";
  $competition_event_string .= "URL;VALUE=URI:".$competition_wca_website."\n";
  $competition_event_string .= "DTSTART;VALUE=DATE:".$start_date."\n";
  $competition_event_string .= "SUMMARY:".$competition_name."\n";
  $competition_event_string .= "DTEND;VALUE=DATE:".$end_date."\n";
  $competition_event_string .= "LOCATION:".$competition_venue."\n";
  $competition_event_string .= "END:VEVENT"."\n";

  echo $competition_event_string;
  flush(); // In case we are refreshing a lot of the cache.

}

$cal_string_end = "END:VCALENDAR";

echo $cal_string_end;
flush();

?>