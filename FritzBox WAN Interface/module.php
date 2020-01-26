<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

    class FritzBoxWANInterface extends FritzBoxModulBase
    {
        protected static $ServiceType = 'urn:schemas-upnp-org:service:WANIPConnection:2';
        protected static $ControlUrl = '/igd2upnp/control/WANIPConn1';
        protected static $EventSubURL = '/igd2upnp/control/WANIPConn1';

        /*        protected static $ControlUrl = '/upnp/control/wanipconnection1';
                protected static $EventSubURL = '/upnp/control/wanipconnection1';
                protected static $ServiceType = 'urn:dslforum-org:service:WANIPConnection:1';*/
        public function Create()
        {
            //Never delete this line!
            parent::Create();
        }
        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();
            $this->GetConnectionTypeInfo();
        }

        public function ForceTermination()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetConnectionTypeInfo()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetPortMappingNumberOfEntries()
        {
            return $this->Send(__FUNCTION__);
        }
    }