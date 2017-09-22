<?
function doReturn()
{
    exit( json_encode( array() ) );
}

// get instance ID
if ( !array_key_exists( "InstanceID", $_GET) )
{
    if ( !array_key_exists( "InstanceID", $_POST) )
    {
        doReturn();
    }
    else $InstanceID = intval( $_POST[ "InstanceID" ] );
}
else $InstanceID = intval( $_GET[ "InstanceID" ] );

// instance existing?
if ( !IPS_ObjectExists( $InstanceID ) )
    doReturn();

// calendar reader or calendar notifier?
$InstanceInfo = IPS_GetInstance( $InstanceID );
if ( "{5127CDDC-2859-4223-A870-4D26AC83622C}" == $InstanceInfo[ "ModuleInfo" ][ "ModuleID" ] )
{
    // reader instance
    $CalendarFeed = json_decode( ICCR_GetCachedCalendar( $InstanceID ), true );
}
else if ( "{F22703FF-8576-4AB1-A0E7-02E3116CD3BA}" == $InstanceInfo[ "ModuleInfo" ][ "ModuleID" ] )
{
    // notifier instance
    $CalendarFeed = json_decode( ICCN_GetNotifierPresenceReason( $InstanceID ), true );
}
else
{
    // no job for us
    doReturn();
}

$result = array();
// convert to calendar format
if ( !empty( $CalendarFeed ) )
    foreach ( $CalendarFeed as $Event )
    {
        $CalEvent = array();
        $CalEvent[ "id" ] = $Event[ "UID" ];
        $CalEvent[ "title" ] = $Event[ "Name" ];
        $CalEvent[ "start" ] = $Event[ "FromS" ];
        $CalEvent[ "end" ] = $Event[ "ToS" ];
        $result[] = $CalEvent;
    }

echo json_encode( $result );
?>
