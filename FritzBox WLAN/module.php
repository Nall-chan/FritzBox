<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

    class FritzBoxWLAN extends FritzBoxModulBase
    {
        protected static $ControlUrl = '/upnp/control/wlanconfig';
        protected static $EventSubURLWlan = '/upnp/control/wlanconfig';
        protected static $ServiceType = 'urn:dslforum-org:service:WLANConfiguration:';

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyInteger('Index', 0);
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
            $WlanIndex = $this->ReadPropertyInteger('Index');
            if ($WlanIndex == 0) {
                $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
            } else {
                $Filter = preg_quote(substr(json_encode(static::$EventSubURLWlan . $WlanIndex), 1, -1));
                $this->SetReceiveDataFilter('.*"EventSubURL":"' . $Filter . '".*');
                $this->SendDebug('Filter', '.*"EventSubURL":"' . $Filter . '".*', 0);
                $this->Subscribe();
            }
        }
        protected function Subscribe(string $EventSubURL = ''): bool
        {
            $WlanIndex = $this->ReadPropertyInteger('Index');
            if ($WlanIndex == 0) {
                return parent::Subscribe(static::$EventSubURLWlan . $WlanIndex);
            }
            return false;
        }
    }