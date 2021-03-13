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
        }
        public function ApplyChanges()
        {
            parent::ApplyChanges();
            $Index = $this->ReadPropertyInteger('Index');
            if ($Index > -1) {
                $this->ReadPortMapping();
            }
        }

        public function GetPortMappingNumberOfEntries()
        {
            return $this->Send('GetPortMappingNumberOfEntries');
        }

        public function ReadPortMapping()
        {
            $NoOfMappings = $this->Send('GetPortMappingNumberOfEntries');
            if ($NoOfMappings == false) {
                return false;
            }
            for ($i = 0; $i < $NoOfMappings; $i++) {
                $result = $this->GetGenericPortMappingEntry($i);
                if ($result === false) {
                    continue;
                }
                $ident = str_replace('.', 'P', $result['NewInternalClient']) . '_' . $result['NewInternalPort'] . '_' . $result['NewProtocol'];
                $this->SendDebug('Ident', $ident, 0);
                $this->setIPSVariable($ident, $result['NewPortMappingDescription'], $result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', false, $i);
            }
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
            string $NewRemoteHost, int $NewExternalPort,
            string $NewProtocol, int $NewInternalPort,
            string $NewInternalClient, int $NewEnabled,
            string $NewPortMappingDescription, int $NewLeaseDuration)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewRemoteHost'            => $NewRemoteHost,
                'NewExternalPort'          => $NewExternalPort,
                'NewProtocol'              => $NewProtocol,
                'NewInternalPort'          => $NewInternalPort,
                'NewInternalClient'        => $NewInternalClient,
                'NewEnabled'               => $NewEnabled,
                'NewPortMappingDescription'=> $NewPortMappingDescription,
                'NewLeaseDuration'         => $NewLeaseDuration
            ]);
            if ($result == false) {
                return false;
            }
            return true;
        }
        /*
         hosts can only add port mapping entries for
         themselves and not for other hosts in the LAN.

        public function EnablePortMapping(string $Ident, bool $Value)
        {
            if (@$this->GetIDForIdent($Ident) == false) {
                trigger_error('Invalid ident.', E_USER_NOTICE);
                return false;
            }
            $Parts = explode('_', $Ident);
            $RemoteHost = '0.0.0.0';
            $ExternalPort = (int) $Parts[1];
            $Protocol = $Parts[2];
            $Data = $this->GetSpecificPortMappingEntry($RemoteHost, $ExternalPort, $Protocol);
            if ($Data == false) {
                //trigger_error('Portmapping not found.', E_USER_NOTICE);
                return false;
            }

            $Result = $this->AddPortMapping(
    $Data['NewRemoteHost'],
    $Data['NewInternalPort'],
    $Data['NewProtocol'],
    $Data['NewExternalPort'],
    $Data['NewInternalClient'],
    (int) $Value,
    $Data['NewPortMappingDescription'],
    $Data['NewLeaseDuration']
);
            if ($Result) {
                $this->SetValue($Ident, $Value);
            }
            return $Result;
        }
        public function RequestAction($Ident, $Value)
        {
            if (parent::RequestAction($Ident, $Value)) {
                return true;
            }
            return $this->EnablePortMapping($Ident, (bool) $Value);
        }*/
    }