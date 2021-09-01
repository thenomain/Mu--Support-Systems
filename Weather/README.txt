The "Hourly Weather Conditions" system, originally by Brus (I'm pretty sure), 
modified when Yahoo changed their API system to now use Weather Underground.

You will need to have access to edit files and to cron on the server account.


** STEP ONE: Get an API code ***************************************************

Go to this site:
    https://darksky.net/dev/register

NOTE: DARK SKY API TOKENS ARE NO LONGER AVAILABLE. ANOTHER SYSTEM WILL BE 
PROVIDED. EVENTUALLY.

Sign up. The base account is free up to a thousand calls per day, so unless 
you're going to install your API code on 42 games--or 42 times per hour on one 
game--you should be fine.

You will get a code on the developer page. On the page it's called a "secret 
key", but sometimes we also call it a "token". 

Don't share this code.


** STEP TWO: Work out your location ********************************************

Dark Sky API only works at latitude and longitude. Get those via your favorite 
search engine. Convert anything "South" to a negative number, and anything 
"West" to a negative number.

For instance, Stowe, Vermont is 44.4654° N, 72.6874° W. The system will need:
    latitude: 44.4654
    longitude: -72.6874

So here's an example URL to test that this is working correctly. Let's say your 
API code is "b9bfg15x30yadsfds3a0x". (It will be much longer.) Test that you're 
getting the weather that you need:
    https://api.darksky.net/forecast/b9bfg15x30yadsfds3a0x/
    44.4654,-72.6874?exclude=hourly,daily


** STEP THREE: Edit and upload weather.php *************************************

At the start of the "weather.php" file will be a few things you'll need to 
change. Change them.


** STEP FOUR: Create weather_api_token.txt *************************************

In the same directory as weather.php, type the following, replacing 
xxxxxxxxxxxxx with the secret key (aka a "token") for Dark Sky:

cat > weather_darksky_token.txt
<?php 
$token_DarkSky = "xxxxxxxxxxxxx";
?>
ctrl-d


** STEP FIVE: Run the code *****************************************************

Still logged into the server, type:

    php weather.php

This runs the very first weather-grabbing from Dark Sky. Hopefully there were 
no errors.


** STEP SIX: Set the Cron ******************************************************

You need to run that command once an hour. If you already know how to add to 
the crontab, add this line:

    @hourly cd <exact path to where 'weather.php' lives>; php weather.php > /dev/null 2>&1

Some servers don't let you edit this from the command line, and you'll have to 
find where to add to the cron in the CPANEL. Enter the above except for the 
word "@hourly", which should be an option similar to "do this once an hour".


** STEP SEVEN: Introduce the 'weather' file to the game server *****************

Find your game's config file. It's probably '<gamename>.conf'. Add this line:

    helpfile meteo <exact path to where 'weather.txt' lives>/weather

If you don't know where 'weather.txt' lives, you entered that information in 
the 'weather.php' file in Step Three.

So if 'weather.txt' lives at '/tinymux/text/weather.txt', enter:

    helpfile meteo /tinymux/text/weather

Notice that there is no '.txt' at the end of this!


** STEP EIGHT: Check the game and load the softcode ****************************

On the game as a wizard, type "@readcache", then type "meteo". If everything 
has run properly up until now, you will see the main help file for meteorology.
"meteo conditions" for the current conditions, and so forth.

If all this is good, upload the softcode system in "weather.txt" and test it by 
typing "weather".


** STEP NINE: Start the Mushcron ***********************************************

The game needs to run "@readcache" once an hour. In your Myrddin's Mushcron 
system, add the following lines:

&CRON_TIME_WEATHER mushcron=||||00 01 02|
&CRON_JOB_WEATHER mushcron=@readcache


** STEP TEN: Done! *************************************************************

Check that by 2 minutes after the hour, the weather has been updated. If it 
has, then you're done. If not, ask your local psychocoder for assistance.
