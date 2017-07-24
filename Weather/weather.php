<?php
/******************************************************************************
 *
 * Sign up for your free Anvil Plan (for Developers) here:
 *    https://www.wunderground.com/weather/api/d/pricing.html
 * 
 * Then enter the code into "weather_api_token.txt" as:
 *    <?php 
 *    $api_token = "xxxxxxxxxxxxx";
 *    ?>
 * 
 * You have limited lookups per hour and day. Do not share this key.
 * 
 * Remember to: 
 *    chmod o-r weather_api_token.txt 
 * 
 * Make sure it runs hourly. Add the following to your crontab:
 *    @hourly cd <exact path to where 'weather.php' lives>; 
 *    php weather.php > /dev/null 2>&1
 * 
 * Read under "User configuration section" for what to add to <game>.conf
 *
 *****************************************************************************/

require "weather_api_token.txt";

/******************************************************************************
 *
 * User configuration section. Please read included comments.
 *
 *****************************************************************************/

// the state and city to pull 

$state = "MD"; 
$city = "Upper_Marlboro"; 

$has_tides = TRUE; // set to FALSE if your area has no tides

/*
   The directory that holds the fine file; use exact path.
   Must end with /, e.g.:
       $file_dir = '/home/bitn/game/etc/text/';
   This directory path must be in the netmux.conf, for example: 
	   helpfile meteo  /home/bitn/game/etc/text/weather
*/

$file_dir = './'; 

/******************************************************************************
 *
 * No configuration is required beyond this point.
 * 
 * Basic setup and file reading.
 *
 *****************************************************************************/

$file = $file_dir . 'weather.txt'; 
$base_string = 'http://api.wunderground.com/api/' . $api_token . 
	'/conditions/forecast/alert/astronomy';
if ($has_tides == TRUE) {
	$base_string .= "/tide/rawtide"; 
};
$base_string .= '/q/' . $state . '/' . $city . '.json';

$json_string = file_get_contents( $base_string ); 
$weather = json_decode( $json_string ); 

@$error = $weather->response->error->description; 
if( isset( $error )) { 
	$file_error = "& help\nError from query: " . $error . "\n\n";
	$file_error .= "Last Updated on " . date( 'F d, h:i A T' ) . "\n";
	$fr = fopen( $file, 'w' );
	fputs( $fr, $file_error );
	fclose( $fr );
	die( $error ); 
}

// force the weather's time zone for any time math we need to do
date_default_timezone_set( $weather->current_observation->local_tz_long );

/******************************************************************************
 * 
 * Conditions (current observations) : $conditions
 * 
 * [$weather->current_observation]
 * temperature: ->temperature_string ["83.8 F (28.8 C)"]
 * feels like: ->feelslike_string ["81 F (27 C)"]
 * humidity: ->relative_humidity ["22%"]
 * 
 * wind description: ->wind_string ["Calm"]
 * wind direction: ->wind_dir ["East"]
 * wind speed: ->wind_mph & ->wind_kph ["0.0" & "0"]
 * wind gust: ->wind_gust_mph & ->wind_gust_kph ["3.0" & "4.8"]
 * 
 * pressure (inches): ->pressure_in ["30.15"]
 * pressure trend: ->pressure_trend ["-"]
 * 
 * visibility: ->visibility_mi & ->visibility_km ["10.0" & "16.1"]
 * 
 ******************************************************************************/

$conditions = $weather->current_observation; 
switch( $conditions->pressure_trend ) { 
	case "-": $pressure_trend = "Falling"; break; 
	case "+": $pressure_trend = "Rising"; break; 
	default: $pressure_trend = "Steady"; break; 
}

$file_conditions = "& conditions\n"; 
$file_conditions .= "Conditions: " .  $conditions->weather . "\n"; 
$file_conditions .= "Temperature: " . 
	$conditions->temperature_string . "\n"; 
if ( $conditions->temperature_string != $conditions->feelslike_string ) { 
	$file_conditions .= "Feels Like: " . 
		$conditions->feelslike_string . "\n";
}
$file_conditions .= "Humidity: " . 
	$conditions->relative_humidity . "% \n"; 
$file_conditions .= "Wind: " . 
	$conditions->wind_mph . " mph " . 
	$conditions->wind_dir . " (" . 
	$conditions->wind_kph . " kph)\n"; 
$file_conditions .= "Wind String: " . 
	$conditions->wind_string . "\n";
if ( $conditions->wind_gust_mph != 0 ) { 
	$file_conditions .= "Wind Gusts: " . 
		$conditions->wind_gust_mph . " mph (" . 
		$conditions->wind_gust_kph . " kph)\n"; 
}
$file_conditions .= "Pressure: " . 
	$conditions->pressure_in . "\" and " . 
	$pressure_trend . "\n"; 
$file_conditions .= "Visibility: " . 
	$conditions->visibility_mi . " mi (" . 
	$conditions->visibility_km . " km)\n"; 
if ( $conditions->precip_1hr_in != 0 ) { 
	$file_conditions .= "Hour Precip: " . 
		$conditions->precip_1hr_string . "\n";
}
if ( $conditions->precip_today_in != 0 ) { 
	$file_conditions .= "Day Precip: " . 
		$conditions->precip_today_string . "\n";
}

// "precip_1hr_string":"0.00 in ( 0 mm)"

/******************************************************************************
 * 
 * Forecast (today & tomorrow) : $forecast
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

$today = $weather->forecast->simpleforecast->forecastday[0];
$tomorrow = $weather->forecast->simpleforecast->forecastday[1];

// today's forecast 

$file_today = "& today\n"; 
$file_today .= "High: " . 
	$today->high->fahrenheit . " F (" . 
	$today->high->celsius ." C)\n"; 
$file_today .= "Low: " . 
	$today->low->fahrenheit . " F (" . 
	$today->low->celsius ." C)\n"; 
$file_today .= "Conditions: " . 
	$today->conditions . "\n";  
$file_today .= "Wind: " . 
	$today->avewind->mph . " mph " . 
	$today->avewind->dir . " (" . 
	$today->avewind->kph . " kph)\n"; 
if ( $today->maxwind->mph != 0 ) { 
$file_today .= "Wind Gusts: " . 
	$today->maxwind->mph . " mph " . 
	$today->maxwind->dir . " (" . 
	$today->maxwind->kph . " kph)\n"; 
}
$file_today .= "Average Humidity: " . 
	$today->avehumidity . "\% \n"; 
$file_today .= "Predicted Precipitation: " . 
	$today->qpf_allday->in . " in (" . 
	$today->qpf_allday->mm . " mm)\n"; 
if ( $today->qpf_allday->in != 0 ) { 
	$file_today .= "Precipitation (Day): " . 
		$today->qpf_day->in . " in (" . 
		$today->qpf_day->mm . " mm)\n"; 
	$file_today .= "Precipitation (Night): " . 
		$today->qpf_night->in . " in (" . 
		$today->qpf_night->mm . " mm)\n"; 
}
if ( $today->snow_allday->in != 0 ) { 
	$file_today .= "Predicted Snowfall: " . 
		$today->snow_allday->in . " in (" . 
		$today->snow_allday->cm . " cm)\n"; 
	$file_today .= "Snowfall (Day): " . 
		$today->snow_day->in . " in (" . 
		$today->snow_day->cm . " cm)\n"; 
	$file_today .= "Snowfall (Night): " . 
		$today->snow_night->in . " in (" . 
		$today->snow_night->cm . " cm)\n"; 
}

// tomorrow's forecast

$file_tomorrow = "& tomorrow\n"; 
$file_tomorrow .= "High: " . 
	$tomorrow->high->fahrenheit . " F (" . 
	$tomorrow->high->celsius ." C)\n"; 
$file_tomorrow .= "Low: " . 
	$tomorrow->low->fahrenheit . " F (" . 
	$tomorrow->low->celsius ." C)\n"; 
$file_tomorrow .= "Conditions: " . 
	$tomorrow->conditions . "\n";  
$file_tomorrow .= "Wind: " . 
	$tomorrow->avewind->mph . " mph " . 
	$tomorrow->avewind->dir . " (" . 
	$tomorrow->avewind->kph . " kph)\n"; 
if ( $tomorrow->maxwind->mph != 0 ) { 
$file_tomorrow .= "Wind Gusts: " . 
	$tomorrow->maxwind->mph . " mph " . 
	$tomorrow->maxwind->dir . " (" . 
	$tomorrow->maxwind->kph . " kph)\n"; 
}
$file_tomorrow .= "Average Humidity: " . 
	$tomorrow->avehumidity . "\% \n"; 
$file_tomorrow .= "Predicted Precipitation: " . 
	$tomorrow->qpf_allday->in . " in (" . 
	$tomorrow->qpf_allday->mm . " mm)\n"; 
if ( $tomorrow->qpf_allday->in != 0 ) { 
	$file_tomorrow .= "Precipitation (Day): " . 
		$tomorrow->qpf_day->in . " in (" . 
		$tomorrow->qpf_day->mm . " mm)\n"; 
	$file_tomorrow .= "Precipitation (Night): " . 
		$tomorrow->qpf_night->in . " in (" . 
		$tomorrow->qpf_night->mm . " mm)\n"; 
}
if ( $tomorrow->snow_allday->in != 0 ) { 
	$file_tomorrow .= "Predicted Snowfall: " . 
		$tomorrow->snow_allday->in . " in (" . 
		$tomorrow->snow_allday->cm . " cm)\n"; 
	$file_tomorrow .= "Snowfall (Day): " . 
		$tomorrow->snow_day->in . " in (" . 
		$tomorrow->snow_day->cm . " cm)\n"; 
	$file_tomorrow .= "Snowfall (Night): " . 
		$tomorrow->snow_night->in . " in (" . 
		$tomorrow->snow_night->cm . " cm)\n"; 
}

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
@$error = $alerts->description; 

$file_alerts = "& alerts\n"; 

if( isset( $error )) { 
	$file_alerts .= "Description: " . $alerts->description . "\n";
	$file_alerts .= "Expires: " . $alerts->expires . "\n";
	$file_alerts .= "Message: " . $alerts->message . "\n";
}
else { 
	$file_alerts .= "No alerts in your area.\n"; 
};


/******************************************************************************
 * 
 * Astronomy (sunrise, sunset, moonrise, moonset, etc. ) : $astronomy
 * 
 * ->sun_phase->sunrise->hour & ->sun_phase->sunrise->minute 
 * ->sun_phase->sunset->hour & ->sun_phase->sunset->minute 
 * 
 * ->moon_phase->percentIlluminated
 * ->moon_phase->phaseofMoon
 * ->moon_phase->hemisphere
 * ->moon_phase->moonrise->hour & ->moon_phase->moonrise->minute
 * ->moon_phase->moonset->hour & ->moon_phase->moonset->minute
 * 
 ******************************************************************************/

$sun = $weather->sun_phase; 
$moon = $weather->moon_phase; 

$file_astronomy = "& astronomy\n";
$file_astronomy .= "Sunrise: " . 
	$sun->sunrise->hour . ":" . 
	$sun->sunrise->minute . "\n";
$file_astronomy .= "Sunset: " . 
	$sun->sunset->hour . ":" . 
	$sun->sunset->minute . "\n";
if( isset( $moon->moonrise->hour )) { 
	$file_astronomy .= "Moonrise: " . 
		$moon->moonrise->hour . ":" . 
		$moon->moonrise->minute . "\n";
}
if( isset( $moon->moonset->hour )) { 
	$file_astronomy .= "Moonset: " . 
		$moon->moonset->hour . ":" . 
		$moon->moonset->minute . "\n";
}
$file_astronomy .= "Moon Phase: " . 
	$moon->phaseofMoon . " (" . 
	$moon->percentIlluminated . "\%)\n";


/******************************************************************************
 * 
 * Tides (high tides & low tides) : $tide, $raw_tide
 * 
 * & tides: timestamp|"High Tide" or "Low Tide"|height in feet
 * & tide heights : timestamp|height in feet
 * 
 ******************************************************************************/
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
Astronomy: Sun & Moon facts.
Alerts: Emergency weather service alerts.\n";

if( $has_tides == TRUE ){
	$file_help .= "Tides: High and Low Tides for the next 3 days\n";
	$file_help .= "Tide Height: Estimates of tide height for the next 2 days\n";
}; 

$file_help .= "Credits: People and things to thank.

" . $weather->current_observation->observation_time . "\n"; // Last Updated on

$file_credits = "& credits
Data provided by the Weather Underground under the following terms of service:
    " . $weather->response->termsofService . "

PHP and TinyMUX code originally by Brus using the Yahoo Weather API.
Rewritten by Thenomain using the Weather Underground API.\n";

// write everything to the file

$fr = fopen( $file, 'w' );
fputs( $fr, $file_help );
fputs( $fr, $file_conditions );
fputs( $fr, $file_today );
fputs( $fr, $file_tomorrow );
fputs( $fr, $file_astronomy );
if( $has_tides == TRUE ){
	fputs( $fr, $file_tides );
	fputs( $fr, $file_tide_heights );
}; 
fputs( $fr, $file_alerts );
fputs( $fr, $file_credits );
fclose( $fr );

?>
