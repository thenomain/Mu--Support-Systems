The "Hourly Weather Conditions" system, originally by Brus (I'm pretty sure), 
modified when Yahoo changed their API system to now use Weather Underground.

You will need to have access to edit files and to cron on the server account.


** STEP ONE: Get an API code ***************************************************

Go to this site:
	https://www.wunderground.com/weather/api/d/pricing.html

Sign up for a free Anvil Plan (for developers). This will be used via a php 
fetching system, so the closest choice of what this will be used for is "web".

You will get a code in the mail that will run about 10 games or locations on one 
code. Don't share this code.


** STEP TWO: Work out your location ********************************************

Wunderground is pretty good about turning city names into locations, so you'll 
need the state and city (if U.S.A., otherwise I'm not currently sure).

Let's say your API code is "b9bfg15x30ya3a0x", your state is "Vermont" and your 
city is "Stowe". Test that you're getting the weather that you need:

	http://api.wunderground.com/api/b9bfg15x30ya3a0x/conditions/forecast/
	alert/astronomy/q/VT/Stowe.json


** STEP THREE: Edit and upload weather.php *************************************

At the start of the "weather.php" file will be a few things you'll need to 
change. Change them.


** STEP FOUR: Create weather_api_token.txt *************************************

In the same directory as weather.php, type the following, replacing 
xxxxxxxxxxxxx with the API code mailed to you by Weather Underground:

cat > weather_api_token.txt
<?php 
$api_token = "xxxxxxxxxxxxx";
?>
ctrl-d


** STEP FIVE: Run the code *****************************************************

Still logged into the server, type:

	php weather.php

This runs the very first weather-grabbing from Weather Underground. Hopefully 
there were no errors.


** STEP SIX: Set the Cron ******************************************************

You need to run that command once an hour. If you already know how to add to the 
crontab, add this line:

	@hourly cd <exact path to where 'weather.php' lives>; php weather.php > /dev/null 2>&1

Some servers don't let you edit this from the command line, and you'll have to 
find where to add to the cron in the CPANEL. Enter the above except for the word 
"@hourly", which should be an option similar to "do this once an hour".


** STEP SEVEN: Introduce the 'weather' file to the game server *****************

Find your game's config file. It's probably '<gamename>.conf'. Add this line:

	helpfile meteo <exact path to where 'weather.txt' lives>/weather

If you don't know where 'weather.txt' lives, you entered that information in the 'weather.php' file in Step Three.

So if 'weather.txt' lives at '/tinymux/text/weather.txt', enter:

	helpfile meteo /tinymux/text/weather

Notice that there is no '.txt' at the end of this!


** STEP EIGHT: Check the game and load the softcode ****************************

On the game as a wizard, type "@readcache", then type "meteo". If everything has 
run properly up until now, you will see the main help file for meteorology.
"meteo conditions" for the current conditions, and so forth.

If all this is good, upload the softcode system in "weather.txt" and test it by 
typing "weather".


** STEP NINE: Start the Mushcron ***********************************************

The game needs to run "@readcache" once an hour. In your Myrddin's mushcron 
system, add the following lines:

&CRON_TIME_WEATHER mushcron=||||01 02|
&CRON_JOB_WEATHER mushcron=@readcache


** STEP TEN: Done! *************************************************************

Check that by 2 minutes after the hour, the weather has been updated. If it has, 
then you're done. If not, ask your local psychocoder for assistance.
