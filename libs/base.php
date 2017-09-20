<?

if( !defined( '_ErgomationBase' ) )
{
    define( '_ErgomationBase', 1 );

    class ErgoIPSModule extends IPSModule {

        protected function IsDebug()
        {
            return false;
        }

        protected function GetLogID()
        {
            return get_class( $this );
        }

        protected function LogError( $Error )
        {
            IPS_LogMessage( $this->GetLogID(), $Error );
        }

        protected function LogDebug( $Debug )
        {
            if ( $this->IsDebug() )
                IPS_LogMessage( $this->GetLogID() . ' Debug', $Debug );
        }

    }
}

?>
