<?php

declare(strict_types=1);
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
require_once __DIR__ . '/../libs/FritzBoxModule.php';
/**
 * @property bool $HasCallMonitor
 */
    class FritzBoxConfigurator extends IPSModule
    {
        use \FritzBoxModulBase\BufferHelper;
        use \FritzBoxModulBase\DebugHelper;

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->ParentID = 0;
            $this->HasCallMonitor = false;
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
            //todo
            //prüfen ob Anrufmonitor aktiv ist
            $this->HasCallMonitor = true;
        }

        public function GetConfigurationForm()
        {
            $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
            if ($this->GetStatus() == IS_CREATING) {
                return json_encode($Form);
            }
            $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            if (($Splitter == 0) || !$this->HasActiveParent()) {
                $Form['actions'][1]['visible'] = true;
                $Form['actions'][1]['popup']['items'][0]['caption'] = 'Not connected!';
                $Form['actions'][1]['popup']['items'][1]['caption'] = 'The \'FritzBox IO\' instance is not connected or missing.';
                $Form['actions'][1]['popup']['items'][1]['width'] = '200px';

                return json_encode($Form);
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
                // todo error handling
                $xpath = new DOMXPath($xml);
                $xpath->registerNamespace('xmlns', $xml->firstChild->namespaceURI);
                $xmlDevices = $xpath->query('//xmlns:service', null, false);
                $xmlmodelName = $xpath->query('//xmlns:modelName', null, false);
                $IsCable = false;
                if (count($xmlmodelName) > 1) {
                    $IsCable = (strpos(strtoupper($xmlmodelName[0]->nodeValue), 'CABLE') !== false);
                }
                $this->SendDebug('isCable', $IsCable, 0);
                foreach ($xmlDevices as $xmlDevice) {
                    $serviceType = $xmlDevice->getElementsByTagName('serviceType')[0]->nodeValue;
                    if (in_array($serviceType, \FritzBox\Services::$Data)) {
                        continue;
                    }
                    if (($serviceType == 'urn:dslforum-org:service:WANIPConnection:1') && !$IsCable) {
                        continue;
                    }
                    if (($serviceType == 'urn:dslforum-org:service:WANPPPConnection:1') && $IsCable) {
                        continue;
                    }
                    if (($serviceType == 'urn:schemas-upnp-org:service:WANDSLLinkConfig:1') && $IsCable) {
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
                    $Event = 'no';
                    $SCPDUrl = substr($xmlDevice->getElementsByTagName('SCPDURL')[0]->nodeValue, 1);
                    if (array_key_exists($SCPDUrl, $SCPDhasEvent)) {
                        $Event = $SCPDhasEvent[$SCPDUrl] ? 'yes' : 'no';
                    }

                    $AddService = [
                        'instanceID'      => 0,
                        'url'             => $xmlDevice->getElementsByTagName('controlURL')[0]->nodeValue,
                        'name'            => $this->Translate('currently not available'),
                        'event'           => $this->Translate($Event),
                        'type'            => $serviceType,
                        'parent'          => $deviceType
                    ];
                    if (array_key_exists($serviceType, \FritzBox\Services::$Data)) {
                        $guid = key(\FritzBox\Services::$Data[$serviceType]);
                        if ($guid !== null) {
                            $index = \FritzBox\Services::$Data[$serviceType][$guid];
                            if (($serviceType == 'urn:schemas-upnp-org:service:WANDSLLinkConfig:1') && ($Xml == 'igd2desc.xml')) {
                                $index++;
                            }
                            $AddService['type'] = $AddService['type'] . ' (' . $this->Translate(IPS_GetModule($guid)['ModuleName']) . ')';
                            $AddService['create'] = [
                                'moduleID'      => $guid,
                                'configuration' => ['Index' => $index],
                                'location'      => [IPS_GetName($this->InstanceID)]
                            ];
                            $Key = array_search(\FritzBox\Services::$Data[$serviceType], $KnownInstances);
                            if ($Key === false) {
                                if (is_numeric($AddService['url'][-1])) {
                                    $AddService['name'] = $this->Translate(IPS_GetModule($guid)['ModuleName']) . ' ' . $AddService['url'][-1];
                                } else {
                                    $AddService['name'] = $this->Translate(IPS_GetModule($guid)['ModuleName']);
                                }
                            } else {
                                $AddService['name'] = IPS_GetName($Key);
                                $AddService['instanceID'] = $Key;
                                unset($KnownInstances[$Key]);
                            }
                            //test mit filter nur auf bekannte
                            $ServiceValues[] = $AddService;
                        }
                    } else {
                        //$ServiceValues[] = $AddService;  
                    }
                    // test ohne Filter.
                    //$ServiceValues[] = $AddService;
                }
                if ($Xml == 'igd2desc.xml') {
                    break;
                }
            }
            if ($this->HasCallMonitor) {
                //todo reinpatchen
                //und aus KnownInstances entfernen
                $serviceType = 'callmonitor';
                $guid = key(\FritzBox\Services::$Data[$serviceType]);
                $AddService = [
                    'instanceID'      => 0,
                    'url'             => '',
                    'name'            => 'currently not available',
                    'event'           => ''
                ];
                $AddService['type'] = $this->Translate(IPS_GetModule($guid)['ModuleName']);
                $AddService['create'] = [
                    'moduleID'      => $guid,
                    'configuration' => ['Index' => 0],
                    'location'      => [IPS_GetName($this->InstanceID)]
                ];
                $Key = array_search(\FritzBox\Services::$Data[$serviceType], $KnownInstances);
                if ($Key === false) {
                    $AddService['name'] = $this->Translate(IPS_GetModule($guid)['ModuleName']);
                } else {
                    $AddService['name'] = IPS_GetName($Key);
                    $AddService['instanceID'] = $Key;
                    unset($KnownInstances[$Key]);
                }
                $ServiceValues[] = $AddService;
            }
            foreach ($KnownInstances as $InstanceId => $KnownInstance) {
                $ServiceValues[] = [
                    'instanceID'      => $InstanceId,
                    'url'             => 'invalid',
                    'name'            => IPS_GetName($InstanceId),
                    'event'           => '',
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
                $value = [IPS_GetInstance($key)['ModuleInfo']['ModuleID'] => IPS_GetProperty($key, 'Index')];
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
