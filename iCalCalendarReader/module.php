<?

use RRule\RRule;

include_once __DIR__ . '/../libs/base.php';
include_once __DIR__ . '/../libs/includes.php';

include_once __DIR__ . '/../libs/iCalcreator-master/autoload.php';
include_once __DIR__ . '/../libs/php-rrule-master/src/RRuleInterface.php';
include_once __DIR__ . '/../libs/php-rrule-master/src/RfcParser.php';
include_once __DIR__ . '/../libs/php-rrule-master/src/RRuleTrait.php';
include_once __DIR__ . '/../libs/php-rrule-master/src/RRule.php';
include_once __DIR__ . '/../libs/php-rrule-master/src/RSet.php';


define( 'ICCR_Debug', true );


define( 'ICCR_Property_CalendarURL', 'CalendarServerURL' );
define( 'ICCR_Property_Username', 'Username' );
define( 'ICCR_Property_Password', 'Password' );

define( 'ICCR_Property_DaysToCache', 'DaysToCache' );
define( 'ICCR_Property_UpdateFrequency', 'UpdateFrequency' );

define( 'ICCR_Default_DaysToCache', 366 );
define( 'ICCR_Default_UpdateFrequency', 15 );


/***********************************************************************

* iCal importer class

************************************************************************/

class ICCR_iCalImporter
{
	private $Timezone;
	private $NowDateTime;
	private $NowTimestamp;
	private $PostNotifySeconds = 0;
	private $SecondsToCache;
	private $CacheSizeDateTime;
	private $CalendarTimezones;

    /*
        debug method, depending on defined constant
    */
	private function LogDebug( $Debug )
    {
        if ( ICCR_Debug )
            IPS_LogMessage( 'iCalImporter Debug', $Debug );
    }

    /*
        convert the timezone RRULE to a datetime object in the given/current year
    */
	private function TZRRuleToDateTime( $RRule, $Year = '' )
	{
		$result = false;
		// always yearly, once a year
		if ( array_key_exists( "BYDAY", $RRule ) )
		{
			if ( array_key_exists( "0", $RRule[ "BYDAY" ] ) )
			{
				$Occ = $RRule[ "BYDAY" ][ "0" ];
				if ( array_key_exists( "DAY", $RRule[ "BYDAY" ] ) )
				{
					$Day = $RRule[ "BYDAY" ][ "DAY" ];
					if ( array_key_exists( "BYMONTH", $RRule ) )
					{
						$Month = $RRule[ "BYMONTH" ];
						$DateObj = DateTime::createFromFormat( '!m', $Month );
						$MonthName = $DateObj->format( 'F' );
						switch ( $Day ) // RFC5545
						{
							case "MO": $DayName = "Monday"; break;
							case "TU": $DayName = "Tuesday"; break;
							case "WE": $DayName = "Wednesday"; break;
							case "TH": $DayName = "Thursday"; break;
							case "FR": $DayName = "Friday"; break;
							case "SA": $DayName = "Saturday"; break;
							case "SU": $DayName = "Sunday"; break;
							default: $DayName = "Sunday"; break;
						}
						return date_timestamp_set( new DateTime, strtotime( $Occ . " " . $DayName . " " . $MonthName . " " . $Year . "00:00:00" ) );
					}
				}
			}
		}
	}

    /*
        apply the time offset from a timezone provided by the loaded calendar
    */
	private function ApplyCustomTimezoneOffset( $EventDateTime, $CustomTimezoneName )
	{
		// is timezone in calendar provided timezone?
		foreach ( $this->CalendarTimezones as $CalendarTimezone )
		{
			if ( $CalendarTimezone[ "TZID" ] == $CustomTimezoneName )
			{
				$DSTStartDateTime = $this->TZRRuleToDateTime( $CalendarTimezone[ "DSTSTART" ], $EventDateTime->format( "Y" ) );
				$DSTEndDateTime = $this->TZRRuleToDateTime( $CalendarTimezone[ "DSTEND" ], $EventDateTime->format( "Y" ) );

				// between these dates?
				if ( ( $EventDateTime > $DSTStartDateTime ) && ( $EventDateTime < $DSTEndDateTime ) )
			    {
					$EventDateTime->add( DateInterval::createFromDateString( strtotime( $CalendarTimezone[ "DSTOFFSET" ] ) ) );
			    }
				else
				{
					$EventDateTime->add( DateInterval::createFromDateString( strtotime( $CalendarTimezone[ "OFFSET" ] ) ) );
				}
				break;
			}
		}
		return $EventDateTime;
	}

    /*
        convert iCal format to PHP DateTime respecting timezone information
        every information will be transformed into the current timezone!
    */
	private function iCalDateTimeArrayToDateTime( $DT )
	{
		$Year = $DT[ "value" ][ "year" ];
		$Month = $DT[ "value" ][ "month" ];
		$Day = $DT[ "value" ][ "day" ];

		$WholeDay = false;
		if ( array_key_exists( "params", $DT ) && array_key_exists( "VALUE", $DT[ "params" ] ) )
		{
			// whole-day, this is not timezone relevant!
			if ( "DATE" == $DT[ "params" ][ "VALUE" ] )
				$WholeDay = true;
		}

		if ( array_key_exists( "hour", $DT[ "value" ] ) )
			$Hour = $DT[ "value" ][ "hour" ];
		else
			$Hour = 0;
		if ( array_key_exists( "min", $DT[ "value" ] ) )
			$Min = $DT[ "value" ][ "min" ];
		else
			$Min = 0;
		if ( array_key_exists( "sec", $DT[ "value" ] ) )
			$Sec = $DT[ "value" ][ "sec" ];
		else
			$Sec = 0;
        // owncloud calendar
		if ( array_key_exists( "params", $DT ) && array_key_exists( "TZID", $DT[ "params" ] ) )
			$Timezone = $DT[ "params" ][ "TZID" ];
        // google calendar
        else if ( array_key_exists( "tz", $DT[ "value" ] ) )
			$Timezone = "UTC";
        else
			$Timezone = $this->Timezone;

		$DateTime = new DateTime();

		if ( $WholeDay )
		{
			$DateTime->setTimezone( timezone_open( $this->Timezone ) );
			$DateTime->setDate( $Year, $Month, $Day );
			$DateTime->setTime( $Hour, $Min, $Sec );
		}
		else
		{
			$IsStandardTimezone = true;
			$SetTZResult = @$DateTime->setTimezone( timezone_open( $Timezone ) );
			if ( false === $SetTZResult )
			{
				// no standard timezone, set to UTC first
				$DateTime->setTimezone( timezone_open ( 'UTC' ) );
				$IsStandardTimezone = false;
		    }
			$DateTime->setDate( $Year, $Month, $Day );
			$DateTime->setTime( $Hour, $Min, $Sec );
			if ( !$IsStandardTimezone )
			{
				// set UTC offset if provided in calendar data
				$DateTime = $this->ApplyCustomTimezoneOffset( $DateTime, $Timezone );
			}
			// convert to local timezone
			$DateTime->setTimezone( timezone_open( $this->Timezone ) );
		}
		return $DateTime;
	}

    /*
        basic setup
    */
	function __construct( $PostNotifyMinutes, $DaysToCache )
	{
        $this->Timezone = date_default_timezone_get();
		$this->NowDateTime = date_create();
		$this->NowTimestamp = date_timestamp_get( $this->NowDateTime );
        $this->PostNotifySeconds = $PostNotifyMinutes * 60;
		$this->SecondsToCache = $DaysToCache * 24 * 60 * 60;
		$this->CacheSizeDateTime = date_timestamp_set( date_create(), $this->NowTimestamp + $this->SecondsToCache );
	}

    /*
        main import method
    */
	public function ImportCalendar( $iCalData )
	{
        $iCalCalendarArray = array();
		$this->CalendarTimezones = array();

		$Config = array(
            "unique_id" => "ergomation.de",
            "TZID" => $this->Timezone,
            "X-WR-TIMEZONE" => $this->Timezone
        );
		$vCalendar = new Kigkonsult\Icalcreator\vcalendar( $Config );
		$vCalendar->parse( $iCalData );

		// get calendar supplied timezones
		while( $Comp = $vCalendar->getComponent( "vtimezone" ) )
		{
			$ProvidedTZ = array();
			$Standard = $Comp->getComponent( "STANDARD" );
			$Daylight = $Comp->getComponent( "DAYLIGHT" );

            if ( ( false !== $Standard ) && ( false !== $Daylight ) )
            {
                $ProvidedTZ[ "TZID" ] = $Comp->getProperty( "TZID" );
                $ProvidedTZ[ "DSTSTART" ] = $Daylight->getProperty( "rrule", false, false );
                $ProvidedTZ[ "DSTEND" ] = $Standard->getProperty( "rrule", false, false );
                $ProvidedTZ[ "OFFSET" ] = $Standard->getProperty( "TZOFFSETTO" );
                $ProvidedTZ[ "DSTOFFSET" ] = $Standard->getProperty( "TZOFFSETFROM" );

                $this->CalendarTimezones[] = $ProvidedTZ;
            }
        }
		while( $Comp = $vCalendar->getComponent( "vevent" ) )
		{
			$ThisEventArray = array();
			$ThisEvent = array();
			$ThisEvent[ "UID" ] = $Comp->getProperty( "uid", false, false );
			$ThisEvent[ "Name" ] = $Comp->getProperty( "summary", false, false );
			$ThisEvent[ "Location" ] = $Comp->getProperty( "location", false, false );
			$ThisEvent[ "Description" ] = $Comp->getProperty( "description", false, false );

			$StartingTime = $this->iCalDateTimeArrayToDateTime( $Comp->getProperty( "dtstart", false, true ) );
			$EndingTime = $this->iCalDateTimeArrayToDateTime( $Comp->getProperty( "dtend", false, true ) );
			$StartingTimestamp = date_timestamp_get( $StartingTime );
			$EndingTimestamp = date_timestamp_get( $EndingTime );
			$Duration = $EndingTimestamp - $StartingTimestamp;

			if ( $this->NowTimestamp < ( $StartingTimestamp - $this->SecondsToCache ) )
			{
				// event is too far in the future, ignore
				$this->LogDebug( "Event " . $ThisEvent[ "Name" ] . "is too far in the future, ignoring" );
			}
			else
			{
				// check if recurring
				$CalRRule = $Comp->getProperty( "rrule", false, false );
				if ( is_array( $CalRRule ) )
				{
					// $this->LogDebug( "Recurring event" );
					if ( array_key_exists( "UNTIL", $CalRRule ) )
					{
						$UntilDateTime = $this->iCalDateTimeArrayToDateTime( array( "value" => $CalRRule[ "UNTIL" ] ) );
						// replace iCal date array with datetime object
						$CalRRule[ "UNTIL" ] = $UntilDateTime;
					}
					// replace/set iCal date array with datetime object
					$CalRRule[ "DTSTART" ] = $StartingTime;
                    // the array underneath "BYDAY" needs to be exactly one level deep. If not, lift it up
                    foreach( $CalRRule[ "BYDAY" ] as &$day )
                    {
                        if ( is_array( $day ) )
                        {
                            if ( array_key_exists( "DAY", $day ) )
                            {
                                $day = $day[ "DAY" ];
                            }
                        }
                    }
                    $this->LogDebug( "Decomposing RRule " . print_r( $CalRRule, true ) );
					$RRule = new RRule( $CalRRule );
					foreach ( $RRule->getOccurrencesBetween( $this->NowDateTime, $this->CacheSizeDateTime ) as $Occurrence )
                    {
						$ThisEvent[ "From" ] = date_timestamp_get( $Occurrence );
						$ThisEvent[ "To" ] = $ThisEvent[ "From" ] + $Duration;
						$ThisEvent[ "FromS" ] = date( "Y-m-d H:i:s", $ThisEvent[ "From" ] );
						$ThisEvent[ "ToS" ] = date( "Y-m-d H:i:s", $ThisEvent[ "To" ] );
						$ThisEventArray[] = $ThisEvent;
					}
				}
				else
				{
					$ThisEvent[ "From" ] = $StartingTimestamp;
					$ThisEvent[ "To" ] = $EndingTimestamp;
					$ThisEvent[ "FromS" ] = date( "Y-m-d H:i:s", $ThisEvent[ "From" ] );
					$ThisEvent[ "ToS" ] = date( "Y-m-d H:i:s", $ThisEvent[ "To" ] );
					$ThisEventArray[] = $ThisEvent;
				}
				foreach ( $ThisEventArray as $ThisEvent )
				{
					if ( $this->NowTimestamp > ( $ThisEvent[ "To" ] + $this->PostNotifySeconds ) )
					{
						// event is past notification times, ignore
						$this->LogDebug( "Event " . $ThisEvent[ "Name" ] . " is past the notification times, ignoring" );
					}
					else
					{
						// insert event(s)
						$iCalCalendarArray[] = $ThisEvent;
					}
				}
			}
		}
		// sort by start date/time to make the check on changes work
		usort( $iCalCalendarArray, function ( $a, $b ) { return $a[ "From" ] - $b[ "From" ]; } );
        return $iCalCalendarArray;
	}
}


/***********************************************************************

* module class

************************************************************************/

class iCalCalendarReader extends ErgoIPSModule {

    // buffer for ical calendar stream between function calls
    private $curl_result = '';

    /***********************************************************************

    * customized debug methods

    ************************************************************************/

    /*
        debug on/off is a defined constant
    */
    protected function IsDebug()
    {
        return ICCR_Debug;
    }

    /*
        sender for debug messages is set
    */
    protected function GetLogID()
    {
        return IPS_GetName( $this->InstanceID );
    }


    /***********************************************************************

    * standard module methods

    ************************************************************************/

    /*
        basic setup
    */
    public function Create()
    {
        parent::Create();

        // create configuration properties
        $this->RegisterPropertyString( ICCR_Property_CalendarURL, '' );
        $this->RegisterPropertyString( ICCR_Property_Username, '' );
        $this->RegisterPropertyString( ICCR_Property_Password, '' );

        $this->RegisterPropertyInteger( ICCR_Property_DaysToCache, ICCR_Default_DaysToCache );
        $this->RegisterPropertyInteger( ICCR_Property_UpdateFrequency, ICCR_Default_UpdateFrequency );

        // initialize persistence
        $this->SetBuffer( "CalendarBuffer",  "" );
        $this->SetBuffer( "Notifications",  "" );
        $this->SetBuffer( "MaxPreNotifySeconds",  "" );
        $this->SetBuffer( "MaxPostNotifySeconds",  "" );

        // create timer
        $this->RegisterTimer( "Update", 0, 'ICCR_UpdateCalendar( $_IPS["TARGET"] );' ); // no update on init
        $this->RegisterTimer( "Cron1", 1000 * 60 , 'ICCR_TriggerNotifications( $_IPS["TARGET"] );' ); // cron runs every minute
        $this->RegisterTimer( "Cron5", 5000 * 60 , 'ICCR_UpdateClientConfig( $_IPS["TARGET"] );' ); // cron runs every 5 minutes
    }

    /*
        react on user configuration dialog
    */
    public function ApplyChanges() {
        parent::ApplyChanges();

        $this->SetTimerInterval( "Update", $this->GetUpdateFrequency() * 1000 * 60 );

        $Status = $this->CheckCalendarURLSyntax();
        $this->SetStatus( $Status );
        // ready to run an update?
        if ( 102 == $Status )
            $this->UpdateClientConfig();
    }


    /***********************************************************************

    * access methods to persistence

    ************************************************************************/

    // property persistence (lasts across restarts)
    private function GetCalendarServerURL()
    {
        return $this->ReadPropertyString( ICCR_Property_CalendarURL );
    }
    private function GetUsername()
    {
        return $this->ReadPropertyString( ICCR_Property_Username );
    }
    private function GetPassword()
    {
        return $this->ReadPropertyString( ICCR_Property_Password );
    }
    private function GetDaysToCache()
    {
        return $this->ReadPropertyInteger( ICCR_Property_DaysToCache );
    }
    private function GetUpdateFrequency()
    {
        return $this->ReadPropertyInteger( ICCR_Property_UpdateFrequency );
    }

    // runtime persistence (does not lasts across restarts)
    private function GetCalendar()
    {
        return $this->GetBuffer( "CalendarBuffer" );
    }
    private function SetCalendar( $Value )
    {
        $this->SetBuffer( "CalendarBuffer",  $Value );
    }

    private function GetNotifications()
    {
        return json_decode( $this->GetBuffer( "Notifications" ), true );
    }
    /*
        save notifications and find the extremum
    */
    private function SetNotifications( $Value )
    {
        $this->SetBuffer( "Notifications", json_encode( $Value ) );
        $MaxPreNS = 0;
        $MaxPostNS = 0;

        if ( is_array( $Value ) )
        {
			foreach ( $Value as $Entry )
            {
                if ( array_key_exists( "PreNS", $Entry ) )
                    if ( $Entry[ "PreNS" ] > $MaxPreNS )
                        $MaxPreNS = $Entry[ "PreNS" ];
                if ( array_key_exists( "PostNS", $Entry ) )
                    if ( $Entry[ "PostNS" ] > $MaxPostNS )
                        $MaxPostNS = $Entry[ "PostNS" ];
            }
        }
        $this->SetMaxPreNotifySeconds( $MaxPostNS );
        $this->SetMaxPostNotifySeconds( $MaxPostNS );
    }

    private function GetMaxPreNotifySeconds()
    {
        $Value = json_decode( $this->GetBuffer( "MaxPreNotifySeconds" ), true );
        if ( empty( $Value ) )
            return 0;
        else
            return $Value;
    }
    private function SetMaxPreNotifySeconds( $Value )
    {
        $this->SetBuffer( "MaxPreNotifySeconds",  json_encode( $Value ) );
    }
    private function GetMaxPostNotifySeconds()
    {
        $Value = json_decode( $this->GetBuffer( "MaxPostNotifySeconds" ), true );
        if ( empty( $Value ) )
            return 0;
        else
            return $Value;
    }
    private function SetMaxPostNotifySeconds( $Value )
    {
        $this->SetBuffer( "MaxPostNotifySeconds",  json_encode( $Value ) );
    }

    /*
        check if calendar URL syntax is valid
    */
    public function CheckCalendarURLSyntax()
    {
        $Status = 102;

        // validate saved properties
        $NC_URL = $this->GetCalendarServerURL();
        if ( '' == $NC_URL )
        {
            $Status = 104;
        }
        else
        {
            // check URL format
            if ( false === filter_var( $NC_URL, FILTER_VALIDATE_URL ) )
            {
                $Status = 200;
            }
        }
        return $Status;
    }


    /***********************************************************************

    * configuration helper

    ************************************************************************/

    /*
        get all notifications information from connected child instances
    */
    private function GetChildrenConfig()
    {
        $this->LogDebug( 'Entering GetChildrenConfig()' );
        // empty configuration buffer
        $Notifications = array();
        $ChildInstances = IPS_GetInstanceListByModuleID( ICCN_Instance_GUID );
        if ( sizeof( $ChildInstances ) <= 0 )
            return;
        // transfer configuration
        $this->LogDebug( 'Transfering configuration from notifier children' );
        foreach( $ChildInstances as $ChInstance )
            if ( IPS_GetInstance( $ChInstance )[ "ConnectionID" ] == $this->InstanceID )
            {
                $ClientConfig = json_decode( IPS_GetConfiguration( $ChInstance ), true );
                $ClientPreNotifyMinutes = $ClientConfig[ "PreNotifyMinutes" ];
                $ClientPostNotifyMinutes = $ClientConfig[ "PostNotifyMinutes" ];
                // new entry
                $Notifications[ $ChInstance ] = array(
                    "PreNS" => $ClientPreNotifyMinutes * 60,
                    "PostNS" => $ClientPostNotifyMinutes * 60,
                    "Status" => 0,
                    "Reason" => array()
                );
            }
        $this->SetNotifications( $Notifications );
    }


    /***********************************************************************

    * calendar loading and conversion methods

    ************************************************************************/

    /*
        load calendar from URL into $this->curl_result, returns IPS status value
    */
    private function LoadCalendarURL( $URL )
    {
        $result = 102;
        $username = $this->GetUsername();
        $password = $this->GetPassword();

        $this->LogDebug( 'Entering LoadCalendarURL()' );

        $curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $URL );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false ); // yes, easy but lazy
		curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 25 ); // 30s maximum script execution time
		curl_setopt( $curl, CURLOPT_TIMEOUT, 25 ); // 30s maximum script execution time
		curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt( $curl, CURLOPT_MAXREDIRS, 5 ); // educated guess
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        if ( '' != $username )
            curl_setopt( $curl, CURLOPT_USERPWD, $username . ':' . $password );
        $this->LogDebug( 'Loading from URL...' );
		$this->curl_result = curl_exec ( $curl );
        $this->LogDebug( 'Loaded' );
        $curl_error_nr = curl_errno( $curl );
        $curl_error_str = curl_error( $curl );
		curl_close( $curl );

        // check on curl error
        if ( $curl_error_nr )
        {
            $this->LogError( 'Error on connect - ' . $curl_error_str . ', for ' . $URL );
            // only differentiate between invalid, connect, SSL and auth
            switch ( $curl_error_nr )
            {
                case 1:
                case 3:
                case 4:
                    // invalid URL
                    $result = 201;
                    break;
                case 35:
                case 53:
                case 54:
                case 58:
                case 59:
                case 60:
                case 64:
                case 66:
                case 77:
                case 80:
                case 82:
                case 83:
                    // SSL error
                    $result = 202;
                    break;
                case 35:
                case 67:
                    // auth error
                    $result = 203;
                    break;
                default:
                    // connect error
                    $result = 204;
                    break;
            }
        }
        // no curl error, continue
        else
        {
            if ( substr( $this->curl_result, 0 ,15 ) != "BEGIN:VCALENDAR" )
            {
                // handle error document
                $result = 205;

                // ownCloud sends XML error messages
                libxml_use_internal_errors( true );
                $XML = simplexml_load_string( $this->curl_result );

                // owncloud error?
                if ( $XML !== false )
                {
                    $XML->registerXPathNamespace( 'd', 'DAV:' );
                    if (0 != count( $XML->xpath( '//d:error' ) ) )
                    {
                        // XML error document
                        $exception = $XML->children( 'http://sabredav.org/ns' )->exception;
                        $message = $XML->children( 'http://sabredav.org/ns' )->message;
                        if ( 'Sabre\DAV\Exception\NotAuthenticated' == $exception )
                        {
                            $result = 203;
                        }
                        $this->LogError( 'Error: ' . $exception . ' - ' . $message );
                    }
                }
                // synology sends plain text
                else if ( 'Please log in' == substr( $this->curl_result, 0 ,13 ) )
                {
                    $this->LogError( 'Error logging on - invalid user/password combination for ' . $URL );
                    $result = 203;
                }
                // everything else goes here
                else
                {
                    $this->LogError( 'Error on connect - this is not a valid calendar URL: ' . $URL );
                }
            }
        }
        return $result;
    }

    /*
        load calendar, convert calendar, return event array of false
    */
    private function ReadCalendar()
    {
        $result = $this->CheckCalendarURLSyntax();
        if ( 102 != $result )
            return false;
        $result = $this->LoadCalendarURL( $this->GetCalendarServerURL() );
        if ( 102 != $result )
            return false;

        $MyImporter = new ICCR_iCalImporter(
            $this->GetMaxPostNotifySeconds(),
            $this->GetDaysToCache()
        );
        $iCalCalendarArray = $MyImporter->ImportCalendar( $this->curl_result );
        return json_encode( $iCalCalendarArray );
    }

    /*
        entry point for the periodic calendar update timer
        also used to trigger manual calendar updates after configuration changes
        accessible for external scripts
    */
    public function UpdateCalendar()
    {
        $this->LogDebug( 'Starting calendar update' );

        $TheOldCalendar = $this->GetCalendar();
        $TheNewCalendar = $this->ReadCalendar();
        if ( false === $TheNewCalendar )
        {
            $this->LogDebug( 'Failed to load calendar' );
            return;
        }
        if ( 0 !== strcmp( $TheOldCalendar, $TheNewCalendar ) )
        {
            $this->LogDebug( 'Updating internal calendar' );
            $this->SetCalendar( $TheNewCalendar );
        }
        else
            $this->LogDebug( 'Calendar still in sync' );
    }


    /***********************************************************************

    * calendar notifications methods

    ************************************************************************/

    /*
        check if event is triggering a presence notification
    */
    private function CheckPresence( $Start, $End, $Pre, $Post, $Timestamp )
    {
        if ( ( $Start - $Pre ) < $Timestamp )
            if ( ( $End + $Post ) > $Timestamp )
                return true;
        return false;
    }

    /*
        entry point for the periodic 1m notifications timer
        also used to trigger manual updates after configuration changes
        accessible for external scripts
    */
    public function TriggerNotifications()
    {
        $this->LogDebug( 'Entering TriggerNotifications()' );

		$NowTimestamp = date_timestamp_get( date_create() );

        $MaxPreNotifySeconds = $this->GetMaxPreNotifySeconds();
        $Notifications = $this->GetNotifications();
        if ( empty( $Notifications ) )
            return;

        $this->LogDebug( 'Processing notifications' );
        foreach ($Notifications as $Notification )
        {
            $Notification[ "Status" ] = false;
            $Notification[ "Reason" ] = array();
        }

        $TheCalendar = $this->GetCalendar();
        $iCalCalendarArray = json_decode( $TheCalendar, true );
        if ( !empty( $iCalCalendarArray ) )
        {
            foreach ( $iCalCalendarArray as $iCalItem )
            {
                foreach ($Notifications as $ChInstanceID => $Notification )
                {
                    if ( $this->CheckPresence( $iCalItem[ "From" ], $iCalItem[ "To" ], $Notification[ "PreNS" ], $Notification[ "PostNS" ], $NowTimestamp ) )
                    {
                        // append status and reason to the corresponding notification
                        $Notifications[ $ChInstanceID ][ "Status" ] = true;
                        $Notifications[ $ChInstanceID ][ "Reason" ][] = $iCalItem;
                    }
                }
            }
        }

        // set status back to children
        foreach ($Notifications as $ChInstanceID => $Notification )
        {
            $this->SendDataToChildren( json_encode(
                array(
                    "DataID" => ICCR_TX,
                    "InstanceID" => $ChInstanceID,
                    "Notify" => array(
                        "Status" => $Notification[ "Status" ],
                        "Reason" => $Notification[ "Reason" ]
                    )
                )
            ) );
        }
    }

    /*
        entry point for a child to inform the parent to update its children configuration
        accessible for external scripts
    */
    public function UpdateClientConfig()
    {
        $this->GetChildrenConfig();
        $this->UpdateCalendar();
        $this->TriggerNotifications();
    }

    /***********************************************************************

    * methods for script access

    ************************************************************************/

    /*
        returns the registered notifications structure
    */
    public function GetClientConfig()
    {
        return $this->GetNotifications();
    }

    /*
        returns the internal calendar structure
    */
    public function GetCachedCalendar()
    {
        return $this->GetCalendar();
    }

}

?>
