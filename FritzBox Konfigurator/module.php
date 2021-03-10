<?php

declare(strict_types=1);
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
require_once __DIR__ . '/../libs/FritzBoxModule.php';

    class FritzBoxKonfigurator extends IPSModule
    {
        use \FritzBoxModulBase\DebugHelper;
    
        /*public static $TypeToGUID = [
            'urn:dslforum-org:service:WLANConfiguration:1' => '{B3D72623-556E-B6C6-25E0-B3DEFE41F031}',
            'urn:dslforum-org:service:WLANConfiguration:2' => '{B3D72623-556E-B6C6-25E0-B3DEFE41F031}',
            'urn:dslforum-org:service:WLANConfiguration:3' => '{B3D72623-556E-B6C6-25E0-B3DEFE41F031}',
            'urn:dslforum-org:service:DeviceConfig:1'      => '{0E5BA3F0-4622-4C96-8D5F-F28DAB051C2F}'
        ];*/
        //public static $TypeFilter = [
        /*    'urn:dslforum-org:service:DeviceInfo:1',
            'urn:dslforum-org:service:Layer3Forwarding:1',
            'urn:dslforum-org:service:LANConfigSecurity:1',
            'urn:dslforum-org:service:ManagementServer:1',
            'urn:dslforum-org:service:Time:1',
            'urn:dslforum-org:service:UserInterface:1',
            'urn:dslforum-org:service:X_AVM-DE_Storage:1',
            'urn:dslforum-org:service:X_AVM-DE_WebDAVClient:1',
            'urn:dslforum-org:service:X_AVM-DE_UPnP:1',
            'urn:dslforum-org:service:X_AVM-DE_Speedtest:1',
            'urn:dslforum-org:service:X_AVM-DE_RemoteAccess:1',
            'urn:dslforum-org:service:X_AVM-DE_MyFritz:1',
            'urn:dslforum-org:service:X_VoIP:1',
            'urn:dslforum-org:service:X_AVM-DE_OnTel:1',
            'urn:dslforum-org:service:X_AVM-DE_Dect:1',
            'urn:dslforum-org:service:X_AVM-DE_TAM:1',
            'urn:dslforum-org:service:X_AVM-DE_AppSetup:1',
            'urn:dslforum-org:service:X_AVM-DE_Homeauto:1',
            'urn:dslforum-org:service:X_AVM-DE_Homeplug:1',
            'urn:dslforum-org:service:X_AVM-DE_Filelinks:1',
            'urn:dslforum-org:service:X_AVM-DE_Auth:1',
            //'urn:dslforum-org:service:WLANConfiguration:1',
            //'urn:dslforum-org:service:WLANConfiguration:2',
            //'urn:dslforum-org:service:WLANConfiguration:3',
            'urn:dslforum-org:service:Hosts:1',
            'urn:dslforum-org:service:LANEthernetInterfaceConfig:1',
            'urn:dslforum-org:service:LANHostConfigManagement:1',
            'urn:dslforum-org:service:WANCommonInterfaceConfig:1',
            'urn:dslforum-org:service:WANDSLInterfaceConfig:1',
            'urn:dslforum-org:service:WANDSLLinkConfig:1',
            'urn:dslforum-org:service:WANEthernetLinkConfig:1',
            'urn:dslforum-org:service:WANPPPConnection:1',
            'urn:dslforum-org:service:WANIPConnection:1',

            'urn:schemas-any-com:service:Any:1',

            'urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1',
            'urn:schemas-upnp-org:service:WANDSLLinkConfig:1',
            'urn:schemas-upnp-org:service:WANIPConnection:2',
            'urn:schemas-upnp-org:service:WANIPv6FirewallControl:1'
         */
        //];

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->ConnectParent('{6FF9A05D-4E49-4371-23F1-7F58283FB1D9}');
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
            $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        }

        public function GetConfigurationForm()
        {
            $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
            $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            if (($Splitter == 0) || !$this->HasActiveParent()) {
                // TODO
                //Parent inactive ausgeben.
                //$Form[];
            }
            $Ret = $this->SendDataToParent(json_encode(
                [
                    'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                    'Function'   => 'SCPD'
                ]
            ));
            $SCPDhasEvent = unserialize($Ret);
            $this->SendDebug('SCPDhasEvent', $SCPDhasEvent, 0);
            $ServiceValues = [];
            $DeviceValues = [];
            $DevicesTypes = [];

            $this->ParentID = $Splitter;
            $KnownInstances = $this->GetInstanceList();

            $Pfad = IPS_GetKernelDir() . 'FritzBoxTemp/' . $Splitter . '/';
            $Xmls = ['tr64desc.xml', 'igd2desc.xml', 'igddesc.xml'];

            foreach ($Xmls as $Xml) {
                $xml = new DOMDocument();
                if (!@$xml->load($Pfad . $Xml)) {
                    continue;
                }
                $this->SendDebug('LoadOk', '', 0);
                // todo error handling
                $xpath = new DOMXPath($xml);

                $xpath->registerNamespace('xmlns', $xml->firstChild->namespaceURI);
                $xmlDevices = $xpath->query('//xmlns:service', null, false);
                foreach ($xmlDevices as $xmlDevice) {
                    $serviceType = $xmlDevice->getElementsByTagName('serviceType')[0]->nodeValue;
                    if (in_array($serviceType, \FritzBox\Services::$Data)) {
                        continue;
                    }

                    $deviceType = $xmlDevice->parentNode->parentNode->getElementsByTagName('deviceType')[0]->nodeValue;
                    $friendlyName = $xmlDevice->parentNode->parentNode->getElementsByTagName('friendlyName')[0]->nodeValue;
                    if (!in_array($deviceType, $DevicesTypes)) {
                        $AddDevice = [
                            'instanceID'      => 0,
                            'url'             => $Xml,
                            'name'            => $friendlyName,
                            'event'           => '',
                            'type'            => $deviceType,
                            'id'              => $deviceType
                        ];
                        $DevicesTypes[] = $deviceType;
                        if ($xmlDevice->parentNode->parentNode->parentNode->parentNode->nodeName != '#document') {
                            $AddDevice['parent'] = $xmlDevice->parentNode->parentNode->parentNode->parentNode->getElementsByTagName('deviceType')[0]->nodeValue;
                        }
                        $DeviceValues[] = $AddDevice;
                    }
                    $Event = false;
                    $SCPDUrl = substr($xmlDevice->getElementsByTagName('SCPDURL')[0]->nodeValue, 1);
                    if (array_key_exists($SCPDUrl, $SCPDhasEvent)) {
                        $Event = $SCPDhasEvent[$SCPDUrl];
                    }

                    $AddService = [
                        'instanceID'      => 0,
                        'url'             => $xmlDevice->getElementsByTagName('controlURL')[0]->nodeValue,
                        'name'            => 'currently not available',
                        'event'           => $Event,
                        'type'            => $serviceType,
                        'parent'          => $deviceType
                    ];
                    if (array_key_exists($serviceType, \FritzBox\Services::$Data)) {
                        $guid = key(\FritzBox\Services::$Data[$serviceType]);
                        if ($guid !== null) {
                            $index = \FritzBox\Services::$Data[$serviceType][$guid];
                            $AddService['type']= $AddService['type']. ' (' . IPS_GetModule($guid)['ModuleName']. ')';
                            $AddService['create'] = [
                                'moduleID'      => $guid,
                                'configuration' => ['Index' => $index],
                                'location' => [IPS_GetName($this->InstanceID)]
                            ];
                            $Key = array_search(\FritzBox\Services::$Data[$serviceType], $KnownInstances);
                            if ($Key === false) {
                                $AddService['name']= IPS_GetModule($guid)['ModuleName'];
                            } else {
                                $AddService['name']= IPS_GetName($Key);
                                $AddService['instanceID']= $Key;
                                unset($KnownInstances[$Key]);
                            }
                        }
                    }

                    $ServiceValues[] = $AddService;
                }
                //if ($Xml == 'igd2desc.xml') {
                   // break 1;
                //}
            }
            foreach ($KnownInstances as $InstanceId => $KnownInstance) {
                $ServiceValues[] =[
                'instanceID'      => $InstanceId,
                'url'             => 'invalid',
                'name'            => IPS_GetName($InstanceId),
                'event'           => false,
                'type'            => 'unknown'];
            }
            $Form['actions'][0]['values'] = array_merge($DeviceValues, $ServiceValues);
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        
        private function FilterInstances(int $InstanceID)
        {
            return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
        }

        private function GetInstanceList()
        {
            $AllInstancesOfParent = array_flip(array_filter(IPS_GetInstanceListByModuleType(MODULETYPE_DEVICE), [$this, 'FilterInstances']));
            foreach ($AllInstancesOfParent as $key => &$value) {
                $value=[IPS_GetInstance($key)['ModuleInfo']['ModuleID'] => IPS_GetProperty($key, 'Index')];
            }
            return $AllInstancesOfParent;
        }
        // Die drei XMLs holen aus dem IO
        // Als Tree darstellen, mit Device und Co&
        // Filter für bestimmte Instanzen, wie
        // Nur wenn ConnectionType DSL ist DSL anbieten
        //  Nur wenn Telefon vorhanden, den Anrufmonitor anbieten
        // StandardDevices für serviceType
        // diese Instanzen habe static $Statevars für Profile, Funktionen, VarTyp etc...
        // Speziallinstanzen für Host, WLANs und Anrufliste
    }
