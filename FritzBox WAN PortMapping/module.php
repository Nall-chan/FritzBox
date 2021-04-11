<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

    class FritzBoxWANPortMapping extends FritzBoxModulBase
    {
        protected static $ControlUrlArray = [
            '/upnp/control/wanpppconn1',
            '/upnp/control/wanipconnection1'
        ];
        protected static $EventSubURLArray = [
        ];
        protected static $ServiceTypeArray = [
            'urn:dslforum-org:service:WANPPPConnection:1',
            'urn:dslforum-org:service:WANIPConnection:1'
        ];
        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyInteger('Index', -1);
            $this->RegisterPropertyInteger('RefreshInterval', 60);
            $this->RegisterTimer('RefreshInfo', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshInfo",true);');
        }
        public function ApplyChanges()
        {
            $this->SetTimerInterval('RefreshInfo', 0);
            parent::ApplyChanges();
            $Index = $this->ReadPropertyInteger('Index');
            if ($Index == -1) {
                $this->SetStatus(IS_INACTIVE);
                return;
            }
            $this->SetStatus(IS_ACTIVE);
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
            $this->UpdatePortMapping();
            $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval')*1000);
        }
        public function RequestAction($Ident, $Value)
        {
            if (parent::RequestAction($Ident, $Value)) {
                return true;
            }
            if (strpos($Ident, 'P')===3) {
                return $this->EnablePortMapping($Ident, (bool)$Value);
            }
            switch ($Ident) {
                case 'RefreshInfo':
                    return $this->UpdatePortMapping();
            }
            trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
            return false;
        }

        private function UpdatePortMapping(string $NewIdent = '', bool $NewEnabled = false)
        {
            $NoOfMappings = $this->GetPortMappingNumberOfEntries();
            if ($NoOfMappings === false) {
                return false;
            }
            $MyIPs = array_column(Sys_GetNetworkInfo(), 'IP');
            $this->setIPSVariable('PortMappingNumberOfEntries', 'Number of port mapping', $NoOfMappings, VARIABLETYPE_INTEGER, '', false, -1);
            for ($i = 0; $i < $NoOfMappings; $i++) {
                $result = $this->GetGenericPortMappingEntry($i);
                if ($result === false) {
                    continue;
                }
                $Ident = str_replace('.', 'P', $result['NewInternalClient']) . '_' . $result['NewInternalPort'] . '_' . $result['NewProtocol'];
                if ($NewIdent == $Ident) {
                    $changeResult = $this->AddPortMapping(
                        $result['NewRemoteHost'],
                        $result['NewExternalPort'],
                        $result['NewProtocol'],
                        $result['NewInternalPort'],
                        $result['NewInternalClient'],
                        $NewEnabled,
                        $result['NewPortMappingDescription'],
                        $result['NewLeaseDuration']
                    );
                    if ($changeResult) {
                        $result['NewEnabled']=$NewEnabled;
                    }
                }

                $this->setIPSVariable($Ident, $result['NewPortMappingDescription'], $result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', false, $i);
                if (in_array((string)$result['NewInternalClient'], $MyIPs)) {
                    $this->EnableAction($Ident);
                }
            }
            return true;
        }
        public function GetPortMappingNumberOfEntries()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return (int)$result;
        }
        public function GetGenericPortMappingEntry(int $index)
        {
            $result = $this->Send(__FUNCTION__, ['NewPortMappingIndex'=>$index]);
            if ($result == false) {
                return false;
            }
            return [
                'NewRemoteHost'             => (string) $result['NewRemoteHost'],
                'NewInternalPort'           => (int) $result['NewExternalPort'],
                'NewProtocol'               => (string) $result['NewProtocol'],
                'NewExternalPort'           => (int) $result['NewInternalPort'],
                'NewInternalClient'         => (string) $result['NewInternalClient'],
                'NewEnabled'                => (int) $result['NewEnabled'],
                'NewPortMappingDescription' => (string) $result['NewPortMappingDescription'],
                'NewLeaseDuration'          => (int) $result['NewLeaseDuration']
            ];
        }

        public function GetSpecificPortMappingEntry(string $RemoteHost, int $ExternalPort, string $Protocol)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewRemoteHost'  => $RemoteHost,
                'NewExternalPort'=> $ExternalPort,
                'NewProtocol'    => $Protocol
            ]);
            if ($result == false) {
                return false;
            }

            return [
                'NewInternalPort'           => (int) $result['NewInternalPort'],
                'NewInternalClient'         => (string) $result['NewInternalClient'],
                'NewEnabled'                => (int) $result['NewEnabled'],
                'NewPortMappingDescription' => (string) $result['NewPortMappingDescription'],
                'NewLeaseDuration'          => (int) $result['NewLeaseDuration'],
                'NewRemoteHost'             => $RemoteHost,
                'NewProtocol'               => $Protocol,
                'NewExternalPort'           => $ExternalPort
            ];
        }
        public function DeletePortMapping(string $RemoteHost, int $ExternalPort, string $Protocol)
        {
            return  $this->Send(__FUNCTION__, [
                'NewRemoteHost'  => $RemoteHost,
                'NewExternalPort'=> $ExternalPort,
                'NewProtocol'    => $Protocol
            ]);
        }
        public function AddPortMapping(
            string $NewRemoteHost,
            int $NewExternalPort,
            string $NewProtocol,
            int $NewInternalPort,
            string $NewInternalClient,
            bool $NewEnabled,
            string $NewPortMappingDescription,
            int $NewLeaseDuration
        ) {
            $result = $this->Send(__FUNCTION__, [
                'NewRemoteHost'            => $NewRemoteHost,
                'NewExternalPort'          => $NewExternalPort,
                'NewProtocol'              => $NewProtocol,
                'NewInternalPort'          => $NewInternalPort,
                'NewInternalClient'        => $NewInternalClient,
                'NewEnabled'               => (int)$NewEnabled,
                'NewPortMappingDescription'=> $NewPortMappingDescription,
                'NewLeaseDuration'         => $NewLeaseDuration
            ]);

            if ($result === null) {
                return true;
            }
            return false;
        }
        /*
         hosts can only add port mapping entries for
         themselves and not for other hosts in the LAN.
*/
        public function EnablePortMapping(string $Ident, bool $Value)
        {
            if (@$this->GetIDForIdent($Ident) == false) {
                trigger_error('Invalid ident.', E_USER_NOTICE);
                return false;
            }

            return $this->UpdatePortMapping($Ident, $Value);
        }
        /*
                public function GetInfo()
                {
                    $this->Send(__FUNCTION__);
                }
                public function GetConnectionTypeInfo()
                {
                    $this->Send(__FUNCTION__);

                }
                public function GetStatusInfo()
                {
                    $this->Send(__FUNCTION__);
                }
                public function GetNATRSIPStatus()
                {
                    $this->Send(__FUNCTION__);

                }
                public function GetExternalIPAddress()
                {
                    $this->Send(__FUNCTION__);

                }
                public function X_GetDNSServers()
                {
                    $this->Send(__FUNCTION__);

                }
                public function GetLinkLayerMaxBitRates()
                {
                    $this->Send(__FUNCTION__);

                }
        */
    }
