/*
HOURLY WEATHER REPORTS

Pull weather information from the news files generated from Weather Underground
data. If you haven't followed the directions while installing 'weather.php', 
this won't work.

The data is pulled from the 'meteo' news file, which needs to be occasionally 
re-built as TinyMUX, MUSH, Penn, and maybe Rhost all structure the news and 
help files from text to the internal system. If the text changes, they must be 
re-built.

OUTPUT:

==========================> Current Weather & Time <===========================

         * The time is currently: Mon Apr 18 17:41:58 2016 Eastern *          

                              Current Conditions

  Conditions: Cloudy                        Sunrise: 6:37 am
 Temperature: 56.1 F (13.4 C)                Sunset: 7:37 pm
 [Feels Like: 56.1 F (13.4 C)]           Moon Phase: Waning Gibbous (96%)
    Humidity: 61%                                    ([Not ]Visible)
        Wind: 14 mph ENE (22 kph)          Pressure: 30.19 in [Falling]
 [Wind Gusts: 23 mph (37 kph)]           Visibility: 10 mi (16.09 km)

                    Today's Forecast                Tomorrow's Forecast       

         Low:         35 F (2 C)                       33 F (0 C)          
        High:         38 F (3 C)                       57 F (14 C)          
  Conditions:           Showers                           Rain               

===============================================================================

The term "current conditions" is kind of a lie, as the system updates hourly.

Pressure trend (rising/falling) is no longer easily available and is not 
reported.

WARNING: Sometimes the system doesn't pull in data, and all these fields will 
be blank. Haven't coded a way around that yet.



================================================================================
== SETUP =======================================================================
*/

@create Weather and Astronomy <waa>
@fo me=&d.waa me=search( name=Weather and Astronomy <waa> )
@set Weather and Astronomy <waa>=safe inherit



/*
================================================================================
== DOT FUNCTIONS ===============================================================
*/

&.msg [v( d.waa )]=ansi( h, <%0>, n, %b%1 )


/*
--------------------------------------------------------------------------------
-- Between ---------------------------------------------------------------------

	u( .between, <value>, <num1>, <num2> )

Is y <= a <= x?
That is: Is 'a' between x and y, including x or y?

*/

&.between [v( d.waa )]=
	cand( 
		gte( %2, min( %0, %1 )), 
		lte( %2, max( %0, %1 ))
	)



/*
================================================================================
== COMMANDS ====================================================================

	weather: report the weather
	weather/override: allow staff to make and clear their own weather report

    time: report the weather

*/

&C.WEATHER [v( d.waa )]=$^\+?weather(/.+?)?( .+)?$:
	think cat( 
		switches:, setq( s, edit( lattr( %!/c.weather/* ), C.WEATHER/* )), %r 
	); 
	@pemit %#=
		case( 1, 
			t( %1 ), 
			udefault( 
				c.weather/[trim( rest( %1, / ))], 
				u( .msg, weather, Unknown switch ), 
				%2 
			), 
			t( %2 ), 
			u( c.weather-specific, %2 ), 
			u( c.weather-default )
		);

@set v( d.waa )/c.weather=regexp

// ---

&C.WEATHER-SPECIFIC [v( d.waa )]=u( c.weather-default )

// ---

&C.WEATHER-DEFAULT [v( d.waa )]=
	if( strlen( v( d.override.conditions )), 
		u( display.weather.override ), 
		u( display.weather )
	)

// ---

&C.WEATHER/OVERRIDE [v( d.waa )]=
	case( 0, 
		isstaff( %# ), 
		u( .msg, weather/override, Staff only ), 

		strlen( %0 ), 
		strcat( 
			set( %!, d.override.conditions: ), 
			set( %!, d.override.timestamp: ), 
			set( %!, d.override.setby: ), 
			u( .msg, weather/override, Cleared )
		), 

		strcat( 
			set( %!, d.override.conditions:%0 ), 
			set( %!, d.override.timestamp:[secs()] ), 
			set( %!, d.override.setby:%# ), 
			u( .msg, weather/override, Set )
		)
	)

// ---

&C.TIME [v( d.waa )]=$^\+?time$:
    @pemit %#=u( c.weather-default )

@set v( d.waa )/c.time=regexp



/*
================================================================================
== FUNCTIONS ===================================================================


--------------------------------------------------------------------------------
-- Function: Get Field ---------------------------------------------------------

0: which 'help' section? ('astronomy')
1: which field? ('sunrise')

*/

&f.get-field [v( d.waa )]=
	strcat( 
		null( regmatchi( textfile( meteo, %0 ), %1: .+, 0 )), 
		trim( rest( %q0, : ))
	)


/*
--------------------------------------------------------------------------------
-- Function: Is the Moon Visible? ----------------------------------------------

Is the moon even possibly visible where you are? Since we only know 
the moonrise and moonset values, we can't make a reasonable prediction how 
visible the moon is, so this is all we are reporting.

if moonrise < moonset, moon is visible if time is between moonrise & moonset
if moonrise > moonset, moon is visible if time is not between moonrise & moonset

if no moonrise, moonrise = 0:00 <minimum>
if no moonset, moonset = 24:00 <maximum>

input: nothing
output: 1 if above the horizon, 0 otherwise

r: moonrise (mux timestamp format, then seconds from unix epoch)
s: moonset (mux timestamp format, then seconds from unix epoch)
b: is the current time between %qr and %qs?

*/

&f.is-moon-visible [v( d.waa )]=
	strcat( 

// moonrise (in seconds)
		setq( r, u( f.get-field, astronomy, moonrise )), 
		setq( r, 
			if( strlen( %qr ), 
				replace( time(), 4, rjust( %qr, 5, 0 ):00 ), 
				replace( time(), 4, 00:00:00 )
			)
		), 
		setq( r, convtime( %qr )), 

// moonset (in seconds)
		setq( s, u( f.get-field, astronomy, moonset )), 
		setq( s, 
			if( strlen( %qs ), 
				replace( time(), 4, rjust( %qs, 5, 0 ):00 ), 
				replace( time(), 4, 24:00:00 )
			)
		), 
		setq( s, convtime( %qs )), 

// is current time between sunrise and sunset?
		setq( b, u( .between, secs(), %qr, %qs )), 

// determine if the moon is 'above the horizon'
		case( 1, 
			gt( %qr, %qs ), not( %qb ), 
			gt( %qs, %qr ), %qb, 
			0 
		)
	)



/*
================================================================================
== FILTERS =====================================================================


--------------------------------------------------------------------------------
-- Filter: Has Field -----------------------------------------------------------

0: list of fields
1: which 'help' section? ('tomorrow')

*/

&fil.has-field [v( d.waa )]=
	regmatchi( textfile( meteo, %1 ), %0: .+ )



/*
================================================================================
== FORMATS & DISPLAYS ==========================================================

--------------------------------------------------------------------------------
-- Display: Weather ------------------------------------------------------------
*/

&display.weather [v( d.waa )]=
	strcat( 
		wheader( * CURRENT TIME: [time()] * ), %r%r, 
		u( display.conditions ), 
		u( display.forecast ), 
		u( display.alerts ), 
		wfooter()
	)


/*
--------------------------------------------------------------------------------
-- Display: Weather Override ---------------------------------------------------
*/

&display.weather.override [v( d.waa )]=
	strcat( 
		wheader( * CURRENT TIME: [time()] * ), %r, 
		center( 
			%(Moon Phase: [u( f.get-field, astronomy, moon phase )]%), 
			width( %# )
		), %r%r, 

		center( ansi( hu, Special Report ), width( %# )), %r%r, 

		wrap( u( d.override.conditions ), sub( width( %# ), 2 ), left, %b ), 
        %r, 

		wfooter( cat( 
			Set by, name( v( d.override.setby )), on, 
			timefmt( $b $d at $I$P, v( d.override.timestamp ))
		))
	)


/*
--------------------------------------------------------------------------------
-- Format: Conditions ----------------------------------------------------------

0: list|of|fields
1: 'meteo' news file to pull from

*/

&format.conditions [v( d.waa )]=
	iter( 
		filter( fil.has-field, %0, |, |, %1 ), 
		strcat( 
			rjust( ansi( h, %i0 ), 12 ), :, %b, 
			u( f.get-field, %1, %i0 )
		), 
		|, | 
	)


/*
--------------------------------------------------------------------------------
-- Display: Conditions ---------------------------------------------------------
*/

&display.conditions [v( d.waa )]=
	strcat( 
		center( ansi( hu, Current Conditions ), width( %# )), %r, %r, 
		u( display.conditions.columns, 
			u( format.conditions, 
				Summary|Temperature|Feels Like|Humidity, 
				conditions 
			), 
			strcat( 
				u( format.conditions, 
					Sunrise|Sunset|Moon Phase, 
					astronomy 
				), |, 
// is the moon visible?
				space( 14 ), 
				%(, if( not( u( f.is-moon-visible )), Not%b ), Visible, %) 
			)
		), %r, 
		u( display.conditions.columns, 
			u( format.conditions, 
				Wind|Wind Gusts, 
				conditions 
			), 
			u( format.conditions, 
				Pressure|Visibility, 
				conditions 
			)
		), %r%r 
	)


/*
--------------------------------------------------------------------------------
-- Display: Conditions in Columns ----------------------------------------------

0: format.conditions column a
1: format.conditions column b

*/

&display.conditions.columns [v( d.waa )]=
	iter( lnum( 1, max( words( %0, | ), words( %1, | ))), 
		center( 
			strcat( 
				ljust( elements( %0, %i0, | ), 38 ), 
				%b, 
				ljust( elements( %1, %i0, | ), 39 )
			), 
			width( %# )
		), , %r 
	)



/*
--------------------------------------------------------------------------------
-- Format: Forecast Header -----------------------------------------------------
*/

&format.forecast.header [v( d.waa )]=
	strcat( 
		space( 13 ), 
		center( ansi( hu, Today's Forecast ), 32 ), %b, 
		center( ansi( hu, Tomorrow's Forecast ), 32 )
	)

// 0: weather element (high, low, conditions)
// output: 'data|data'
&format.forecast [v( d.waa )]=
	strcat( 
		if( u( fil.has-field, %0, today ), 
			u( f.get-field, today, %0 ), 
		), 
		|, 
		if( u( fil.has-field, %0, tomorrow ), 
			u( f.get-field, tomorrow, %0 ), 
		)
	)


/*
--------------------------------------------------------------------------------
-- Format: Forecast Columns ----------------------------------------------------
*/

&format.forecast.columns [v( d.waa )]=
	strcat( 
		rjust( ansi( h, %0 ), 13 ), :, 
		center( first( %1, | ), 32 ), %b, 
		center( rest( %1, | ), 32 )
	)


/*
--------------------------------------------------------------------------------
-- Display: Forecast -----------------------------------------------------------
*/

&display.forecast [v( d.waa )]=
	strcat( 
		center( u( format.forecast.header ), width( %# )), %r, %r, 
		iter( 
			High|Low|Conditions, 
			center( 
				u( format.forecast.columns, %i0, u( format.forecast, %i0 )), 
				width( %# )
			), 
			|, %r 
		), %r%r 
	)


/*
--------------------------------------------------------------------------------
-- Display: Alerts -------------------------------------------------------------

if get-field (alerts, description) is not null, display, else don't

*/

&display.alerts [v( d.waa )]=
	if( setr( a, u( f.get-field, alerts, title )), 
		ansi( 
			r, center( %qa, width( %# )), 
			n, %r, 
			r, center( 
                %(see 'meteo alerts' for more information%), 
                width( %# )
            ), 
			n, %r%r, 
		)
	)









/*
================================================================================
== TIDES? ======================================================================

Let's see how this goes.

Current tide: 
* Find the one or two times (unix epoch) from "& tide height"
* Find percentage between those times representing current time
* Calculate height based on that percentage.

TEST:

@fo me=&d.tides me=1493241173|0.208064%%r1493241473|0.222029%%r1493241773|0.236199%%r1493242073|0.250554%%r1493242373|0.265074%%r1493242673|0.279739%%r1493242973|0.294526%%r1493243273|0.309414%%r1493243573|0.32438%%r1493243873|0.339402%%r1493244173|0.354455%%r1493244473|0.369518%%r1493244773|0.384565%%r1493245073|0.399574%%r1493245373|0.41452%%r1493245673|0.42938%%r1493245973|0.444129%%r1493246273|0.458746%%r1493246573|0.473205%%r1493246873|0.487485%%r1493247173|0.501562%%r1493247473|0.515416%%r1493247773|0.529023%%r1493248073|0.542365


&filter.tides.lte me=lte( first( %0, | ), %1 )
&filter.tides.gte me=gte( first( %0, | ), %1 )

secs between two entries on the above chart -> 1493242163
this is an exact time, and must also work   -> 1493242073

think strcat( 
// secs()
    setq( s, 1493242163 ), 
// l: first time less-than %qs
    setr( l, last( filter( filter.tides.lte, v( d.tides ), %r, :, %qs ), : )), 
    %b--%b, 
// g: first time greater-than %qs 
    setr( g, first( filter( filter.tides.gte, v( d.tides ), %r, :, %qs ), : )), 
    %b...%b, 
// p: percentage of time past between %ql and %qg (always 300 seconds)
    setr( p, fdiv( sub( %qs, first( %ql, | )), 300 )), 
    %b-->%b, 
// i: min tide, a: max tide, n: new tide
    setr( i, last( %ql, | )), %b...%b, setr( a, last( %qg, | )), %b...%b, 
    setr( n, sub( %qa, %qi )), %b...%b, setr( n, mul( %qp, %qn )), %b==>%b, 
    setr( n, add( %qn, %qi ))
 )

each entry is: 
    <seconds in unix epoch>|<tide height in feet>

s: secs() - unix epoch
t: '& tide height' file from news file 'meteo'

l: first entry less-than %qs
g: first entry greater-than %qs 
p: percentage of time between %ql and %qg (always 300 seconds)

i: 'less-than' tide height
a: 'greater-than' tide height
n: new tide height

*/

&filter.tides.lte [v( d.waa )]=lte( first( %0, | ), %1 )
&filter.tides.gte [v( d.waa )]=gte( first( %0, | ), %1 )

&f.tide.current [v( d.waa )]=
    strcat(
        setq( s, secs()), 
        setq( t, textfile( meteo, tide height )), 
        setq( l, last( filter( filter.tides.lte, %qt, %r, :, %qs ), : )), 
        setq( g, first( filter( filter.tides.gte, %qt, %r, :, %qs ), : )), 
        setq( p, fdiv( sub( %qs, first( %ql, | )), 300 )), 
        setq( i, last( %ql, | )), 
        setq( a, last( %qg, | )), 
        setq( n, mul( sub( %qa, %qi ), %qp )), 
        add( %qn, %qi )
    )
