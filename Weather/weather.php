<?php
/******************************************************************************
 *
 * Working retooling for DarkSky's free API
 * Uses https://github.com/dmitry-ivanov/dark-sky-api, manually installed
 * Commit #10
 * 
 * Dark Sky does not look up by anything but lat/long
 * Possible future lookup via zip code: https://www.zipcodeapi.com/API#zipToLoc
 *
 *****************************************************************************/

require "weather_darksky_token.txt";

/******************************************************************************
 *
 * User configuration section. Please read included comments.
 *
 *****************************************************************************/

// the state and city are no longer used -- alas
// The latitude and longitude of Stowe, VT is (close enough to):
$latitude = "44.4875532";
$longitude = "-72.7223417";


// Dark Sky doesn't do tides. Maybe we can find something better
$has_tides = FALSE; // set to TRUE if your area has  tides

/*
   The directory that holds the fine file; use exact path.
   Must end with /, e.g.:
       $file_dir = '/home/bitn/game/etc/text/';
   This directory path must be in the netmux.conf, for example: 
       helpfile meteo /home/bitn/game/etc/text/weather
*/

// $file_dir = '/home/fateshar/tinymux/mux/game/text/'; 
$file_dir = '/Users/jenkins/Documents/Projects/Mush Code/support systems/Weather/'; 


/******************************************************************************
 *
 * No configuration is required beyond this point.
 * 
 * Basic setup and file reading.
 *
 *****************************************************************************/

$file = $file_dir . 'weather.txt'; 
$base_string = 'https://api.darksky.net/forecast/' . $token_DarkSky . '/' . 
    $latitude . ',' . $longitude . 
    '?exclude=hourly,minutely'; 

$json_string = file_get_contents( $base_string ); 
$weather = json_decode( $json_string ); 

// force the weather's time zone for any time math we need to do
$tz = $weather->timezone;
date_default_timezone_set( $tz );

// set a 'DateTime' object for math we'll do with $astronomy
$datetime = new DateTime('@'.$weather->currently->time );
date_timezone_set( $datetime, new DateTimeZone($tz));

// astronomy from the USNO office.
// Our token as "TinyMUX" to let them know who we are.
/* -- Astronomy Not Pulling --

$astronomy_string = 'https://api.usno.navy.mil/rstt/oneday?' . 
    'ID=TinyMUX&date=today&coords=' . $latitude . ',' . $longitude;
$json_string = file_get_contents( $astronomy_string ); 
$astronomy = json_decode( $json_string ); 
*/

/* we don't know how to error yet *

@$error = $weather->response->error->description; 
if( isset( $error )) { 
    $file_error = "& help\nError from query: " . $error . "\n\n";
    $file_error .= "Last Updated on " . date( 'F d, h:i A T' ) . "\n";
    $fr = fopen( $file, 'w' );
    fputs( $fr, $file_error );
    fclose( $fr );
    die( $error ); 
}

*/



/******************************************************************************
 * A few functions:
 *
 * degToCompass(degrees): Find the compass directions from degrees.
 * 
 * https://stackoverflow.com/questions/7490660/converting-wind-direction-in-
 * angles-to-text-words
 * 
 * mphToKph(speed): Does what it says on the tin.
 * 
 * fahrenheitToCelsius(temp): Ditto. (32°F − 32) × 5/9 
 * 
 * cloudCoverDesc(cloudCover): Turn % cloud cover to description.
 *
 * https://www.weather.gov/media/pah/ServiceGuide/A-forecast.pdf
 *   Overcast            88-100%
 *   Mostly Cloudy       70-87%
 *   Partly Cloudy       26-69%
 *   Mostly Clear        6-25%
 *   Clear               0-5%
 *
 *****************************************************************************/

function degToCompass($deg) {
    $val = floor(($deg/22.5)+.5);
    $arr = [
       "N","NNE","NE","ENE","E","ESE","SE","SSE",
       "S","SSW","SW","WSW","W","WNW","NW","NNW"
    ]; 
    return $arr[($val % 16)];
}

function mphToKph($speed) {
    return round($speed * 1.609344); 
}

function fahrenheitToCelsius($temp) {
    return round(($temp - 32) * 5 / 9 );
}

// this probably looks ugly as hell...
function cloudCoverDesc($cloudCover) {
    $cloudPercent = $cloudCover * 100;
    switch (true) { 
        case ( $cloudPercent >= 88 ):
            $cloudDescription = "Overcast"; break;
        case ( $cloudPercent >= 70 ):
            $cloudDescription = "Mostly Cloudy"; break;
        case ( $cloudPercent >= 26 ):
            $cloudDescription = "Partly Cloudy"; break;
        case ( $cloudPercent >= 88 ):
            $cloudDescription = "Mostly Clear"; break;
        case ( $cloudPercent >= 0 ):
            $cloudDescription = "Clear"; break;
        default:
            $cloudDescription = "<error>";
    }; 
    return $cloudDescription;
}

function windSpeedDesc($speed) {
	// speed in MPH -> Knots
    $knots = $speed * 0.868976;

    // beaufort scale
    switch (true) {
        case ( $knots >= 64 ):
            $windDescription = "Hurricane Force"; break;
        case ( $knots >= 56 ):
            $windDescription = "Violent Storm"; break;
        case ( $knots >= 48 ):
            $windDescription = "Storm"; break;
        case ( $knots >= 41 ):
            $windDescription = "Strong Gale"; break;
        case ( $knots >= 34 ):
            $windDescription = "Gale"; break;
        case ( $knots >= 28 ):
            $windDescription = "Near Gale"; break;
        case ( $knots >= 22 ):
            $windDescription = "Strong"; break;
        case ( $knots >= 17 ):
            $windDescription = "Fresh"; break;
        case ( $knots >= 11 ):
            $windDescription = "Moderate"; break;
        case ( $knots >= 7 ):
            $windDescription = "Gentle"; break;
        case ( $knots >= 4 ):
            $windDescription = "Light"; break;
        case ( $knots >= 1 ):
            $windDescription = "Light Air"; break;
        case ( $knots >= 0 ):
            $windDescription = "Calm"; break;
        default:
            $windDescription = "<error>"; break;
    }; 
    return $windDescription;
}



/******************************************************************************
 * 
 * Conditions (current observations) : $conditions
 * 
 * [$weather->currently]
 * temperature: ->temperature ["83.8", F]
 * feels like: ->apparentTemperature ["81", F]
 * humidity: ->humidity ["0.88", x 100 == %]
 * 
 * wind direction: ->windBearing [degrees, undefined if windSpeed == 0]
 * wind speed: ->windSpeed ["0.0", mph ]
 * wind gust: ->windGust ["3.0", mph ]
 * 
 * pressure: ->pressure_in ["1013.82", millibars]
 * pressure_trend: shows the direction of change (higher, lower, steady) 
 *     of the barometric pressure over the last three hours.
 * "Rising Rapidly" is indicated if the pressure increases > 2 mb (0.06")
 * "Rising Slowly" is indicated if the pressure increases >1 mb but < 2 mb 
 *     (> 0.02" but < 0.06")
 * "Steady" is indicated if the pressure changes < 1 mb (< 0.02")
 * "Falling Slowly" is indicated if the pressure falls > 1 mb but < 2 mb 
 *     (> 0.02" but < 0.06")
 * "Falling Rapidly" is indicated when the pressure decreases > 2 mb (>0.06")
 * 
 * visibility: ->visibility ["10.0", mi]
 * 
 ******************************************************************************/

$conditions = $weather->currently; 

$file_conditions = "& conditions\n"; 
$file_conditions .= "Summary: " .  $conditions->summary . "\n"; 
$file_conditions .= "Temperature: " . 
    round($conditions->temperature) . " F (" . 
    fahrenheitToCelsius($conditions->temperature) ." C)\n"; 
if ( $conditions->temperature != $conditions->apparentTemperature ) { 
    $file_conditions .= "Feels Like: " . 
	    round($conditions->apparentTemperature) . " F (" . 
    	fahrenheitToCelsius($conditions->apparentTemperature) ." C)\n"; 
}
$file_conditions .= "Humidity: " . 
    $conditions->humidity * 100 . "\% \n"; 
$file_conditions .= "Wind: " . 
    round($conditions->windSpeed) . " mph " . 
    degToCompass($conditions->windBearing) . 
    " (" . mphToKph($conditions->windSpeed) . " kph)\n"; 
$file_conditions .= "Wind Description: " . 
    windSpeedDesc($conditions->windSpeed) . "\n";
if ( $conditions->windGust != 0 ) { 
    $file_conditions .= "Wind Gusts: " . 
        round($conditions->windGust) . " mph (" . 
        mphToKph($conditions->windGust) . " kph)\n"; 
}
$file_conditions .= "Pressure: " . 
    round( $conditions->pressure * 0.029530, 2 ) . " in\n"; 
$file_conditions .= "Cloud Cover: " . 
    cloudCoverDesc($conditions->cloudCover). "\n"; 

/*
Precipitation amount will be more challenging and may not be important
*/



/******************************************************************************
 * 
 * Forecast (today & tomorrow) : $today, $tomorrow
 * 
 * high: ->high->fahrenheit & ->high->celsius
 * low: ->low->fahrenheit & ->low->celsius
 * condition: ->conditions
 * average wind: ->avewind->mph & ->avewind->kph & ->avewind->string (direction)
 * max wind: ->maxwind->mph & ->maxwind->kph & ->maxwind->string (direction)
 * average humidity: ->avehumidity
 * 
 * [Quantitative Precipitation Forecasts: melted liquid impact on the area]
 * predicted rainfall: ->qpf_allday->in & ->qpf_allday->mm
 * rainfall for day: ->qpf_day->in & ->qpf_day->mm
 * rainfall for night: ->qpf_night->in & ->qpf_day->mm
 * predicted snowfall: ->snow_allday->in & ->snow_allday->cm
 * snowfall for day: ->snow_day->in & ->snow_day->cm
 * snowfall for night: ->snow_night->in & ->snow_night->cm
 * 
 ******************************************************************************/

$forecastSummary = $weather->daily->summary;
$today = $weather->daily->data[0];
$tomorrow = $weather->daily->data[1];

// today's forecast 

$file_today = "& today\n"; 
$file_today .= "High: " . 
    round($today->temperatureHigh) . " F (" . 
    fahrenheitToCelsius($today->temperatureHigh) ." C)\n"; 
$file_today .= "Low: " . 
    round($today->temperatureLow) . " F (" . 
    fahrenheitToCelsius($today->temperatureLow) ." C)\n"; 
$file_today .= "Conditions: " . 
    $today->summary . "\n";  
$file_today .= "Wind: " . 
    round($today->windSpeed) . " mph " . 
    degToCompass($today->windBearing) . " (" . 
    mphToKph($today->windSpeed) . " kph)\n"; 
if ( $today->windGust != 0 ) { 
$file_today .= "Wind Gusts: " . 
    round($today->windGust) . " mph (" . 
    mphToKph($today->windGust) . " kph)\n"; 
};
$file_today .= "Humidity: " . 
    round($today->humidity * 100) . "\% \n"; 
if ( !empty( $today->precipType )) { 
    $file_today .= 
        "Chance of " . ucfirst( $today->precipType ) . ": " . 
        round($today->precipProbability * 100) . "\% \n";
};


// tomorrow's forecast

$file_tomorrow = "& tomorrow\n"; 
$file_tomorrow .= "High: " . 
    round($tomorrow->temperatureHigh) . " F (" . 
    fahrenheitToCelsius($tomorrow->temperatureHigh) ." C)\n"; 
$file_tomorrow .= "Low: " . 
    round($tomorrow->temperatureLow) . " F (" . 
    fahrenheitToCelsius($tomorrow->temperatureLow) ." C)\n"; 
$file_tomorrow .= "Conditions: " . 
    $tomorrow->summary . "\n";  
$file_tomorrow .= "Wind: " . 
    round($tomorrow->windSpeed) . " mph " . 
    degToCompass($tomorrow->windBearing) . " (" . 
    mphToKph($tomorrow->windSpeed) . " kph)\n"; 
if ( $tomorrow->windGust != 0 ) { 
$file_tomorrow .= "Wind Gusts: " . 
    round($tomorrow->windGust) . " mph (" . 
    mphToKph($tomorrow->windGust) . " kph)\n"; 
};
$file_tomorrow .= "Humidity: " . 
    round($tomorrow->humidity * 100) . "\% \n"; 
if ( !empty( $tomorrow->precipType )) { 
    $file_tomorrow .= 
        "Chance of " . ucfirst( $tomorrow->precipType ) . ": " . 
        round($tomorrow->precipProbability * 100) . "\% \n";
};



/******************************************************************************
 * 
 * Alerts (emergency information) : $alerts
 * 
 * ->alerts->description
 * ->alerts->expires
 * ->alerts->message (\u000A -> %r)
 * 
 ******************************************************************************/

@$alerts = $weather->alerts; 

$file_alerts = "& alerts\n"; 

if (isset($alerts)) { 
    foreach ($alerts as $one_alert) {
        $file_alerts .= "Title: " . $one_alert->title . "\n";
        $file_alerts .= "Severity: " . ucfirst($one_alert->severity) . "\n";
        $file_alerts .= "Issued: " . date('M jS, ga', $one_alert->time) . 
                        "\n";
        $file_alerts .= "Description: " . $one_alert->description . "\n";
        $file_alerts .= "Expires: " . date('M jS, ga', $one_alert->expires) . 
                        "\n\n";
    }
} else { 
    $file_alerts .= "No alerts in your area.\n"; 
};




/******************************************************************************
 * 
 * Astronomy (sunrise, sunset, moonrise, moonset, etc. ) : $astronomy
 * 
 * Currently grabbed from the United States Naval Observatory
 * TimeZone conversion based on:
 * https://stackoverflow.com/questions/36012378/convert-utc-to-est-by-taking-
 * care-of-daylight-saving/36012664
 * 
 ******************************************************************************/

/* -- Astronomy Not Pulling --

$sun = $astronomy->sundata; 
foreach ($sun as $value) {
    if ($value->phen == "R") { 
            $sunrise = $value->time; 
    } elseif ($value->phen == "S") { 
            $sunset = $value->time; 
    };
};

$moon = $astronomy->moondata;

*/

$file_astronomy = "& astronomy\nIntentionally if sadly blank.\n";

/* we ignore other days - it makes 'is the moon visible' calculation easier *
if (!is_null(@$astronomy->prevmoondata)) {
    $moon = array_merge($astronomy->prevmoondata, $moon);
}; 
if (!is_null(@$astronomy->nextmoondata)) {
    $moon = array_merge($moon, $astronomy->nextmoondata);
}; 
*/
/* -- Astronomy Not Pulling --

foreach ($moon as $value) {
    if ($value->phen == "R") { 
            $moonrise = $value->time; 
    } elseif ($value->phen == "S") { 
            $moonset = $value->time; 
    };
};

$file_astronomy = "& astronomy\n";
$file_astronomy .= "Sunrise: " . $sunrise . "\n";
$file_astronomy .= "Sunset: " . $sunset . "\n";
if( isset( $moonrise )) { 
    $file_astronomy .= "Moonrise: " . $moonrise . "\n";
}
if( isset( $moonset )) { 
    $file_astronomy .= "Moonset: " . $moonset . "\n";
}
$file_astronomy .= "Moon Phase: " . $astronomy->curphase . 
    " (" . str_replace("%", "\%", $astronomy->fracillum) . ")\n";

*/

/******************************************************************************
 * 
 * Tides (high tides & low tides) : $tide, $raw_tide
 * 
 * & tides: timestamp|"High Tide" or "Low Tide"|height in feet
 * & tide heights : timestamp|height in feet
 * 
 * system turned off due to lack of ability to grab tides

if( $has_tides == TRUE ) {
    $tide = $weather->tide; 
    $file_tides = "& tides\n"; 

    foreach ( $tide->tideSummary as $index => $summary ) {
        if (in_array($summary->data->type, array( 'High Tide', 'Low Tide'))) {
            $file_tides .= 
                $summary->date->epoch . "|" .
                $summary->data->type . "|" . 
                $summary->data->height . "\n"; 
            }
    }; 

    $raw_tide = $weather->rawtide; // giddyup raw tide!
    $file_tide_heights = "& tide height\n";

    for ( $i = 0; $i <= 23; $i++ ) {
        $file_tide_heights .= 
            $raw_tide->rawTideObs[$i]->epoch . "|" . 
            $raw_tide->rawTideObs[$i]->height . "\n";
    }; 

}

 ******************************************************************************/


/******************************************************************************
 * 
 * Top level of the 'meteo' help file
 * 
 ******************************************************************************/

$file_help = "& help
Raw weather data for weather code.

Conditions: Current conditions as of the Last Updated time.
Today: Today's forecast.
Tomorrow: Tomorrow's forecast.
Astronomy: Sun & Moon facts. (under construction)
Alerts: Emergency weather service alerts.\n";

/*
if( $has_tides == TRUE ){
    $file_help .= "Tides: High and Low Tides for the next 3 days\n";
    $file_help .= "Tide Height: Estimates of tide height for the next 2 days\n";
};
*/ 

$file_help .= "Credits: People and things to thank.

Powered by Dark Sky (https://darksky.net/poweredby/)

Last Updated on " . date( 'F d, h:i A T', $datetime->getTimestamp() ) . "\n";


// credits

$file_credits = "& credits
Weather powered by Dark Sky (https://darksky.net/poweredby/)

Sun & moon information provided by the United States Naval Observatory's 
amazing Astronomical Applications department (https://aa.usno.navy.mil/data/docs/api.php)

PHP and TinyMUX code originally by Brus using the Yahoo Weather API.
Rewritten by Thenomain using the Dark Sky API.\n";

// write everything to the file

$fr = fopen( $file, 'w' );
fputs( $fr, $file_help );
fputs( $fr, $file_conditions );
fputs( $fr, $file_today );
fputs( $fr, $file_tomorrow );
fputs( $fr, $file_astronomy );
/* tides reporting currently disabled until we can find a good source *
if( $has_tides == TRUE ){
    fputs( $fr, $file_tides );
    fputs( $fr, $file_tide_heights );
};
*/ 
fputs( $fr, $file_alerts );
fputs( $fr, $file_credits );
fclose( $fr );

?>
