<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxHomeautomationConfigurator extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_homeauto'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Homeauto:1'
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if (($Splitter == 0) || !$this->HasActiveParent()) {
            $Form['actions'][1]['visible'] = true;
            $Form['actions'][1]['popup']['items'][0]['caption'] = 'Not connected!';
            $Form['actions'][1]['popup']['items'][1]['caption'] = 'The \'FritzBox IO\' instance is not connected or missing.';
            $Form['actions'][1]['popup']['items'][1]['width'] = '300px';
            return json_encode($Form);
        }
        $Devices = $this->GetHomeautomationDevices();
        if (count($Devices) == 0) {
            $Form['actions'][1]['visible'] = true;
            $Form['actions'][1]['popup']['items'][0]['caption'] = 'Nothing found!';
            $Form['actions'][1]['popup']['items'][1]['caption'] = "No devices were found.\r\nEither no devices were paired or the firmware of the FritzBox is too old.";
            $Form['actions'][1]['popup']['items'][1]['width'] = '300px';
            //return json_encode($Form);
        }
        $DeviceValues = [];
        $KnownInstances = $this->GetInstanceList(); // [InstanceID => AIN]
        $this->SendDebug('KnownInstances', $KnownInstances, 0);
        foreach ($Devices as $DeviceData) {
            $AddDevice = [
                'instanceID'      => 0,
                'AIN'             => (string) $DeviceData['NewAIN'],
                'Manufacturer'    => (string) $DeviceData['NewManufacturer'],
                'ProductName'     => (string) $DeviceData['NewProductName'],
                'name'            => (string) $DeviceData['NewDeviceName'],
                'create'          => [

                    'moduleID'      => \FritzBox\GUID::HomeautomationDevice,
                    'configuration' => [
                        'Index'   => 0,
                        'AIN'     => (string) $DeviceData['NewAIN']
                    ],
                    'location'      => [$this->Translate('FritzBox Homeautomation')]
                ]

            ];

            $InstanceIDDevice = array_search((string) $DeviceData['NewAIN'], $KnownInstances);

            if ($InstanceIDDevice !== false) {
                $AddDevice['instanceID'] = $InstanceIDDevice;
                $AddDevice['name'] = IPS_GetName($InstanceIDDevice);
                unset($KnownInstances[$InstanceIDDevice]);
            }
            $DeviceValues[] = $AddDevice;
        }
        foreach ($KnownInstances as $id => $AIN) {
            $AddDevice = [
                'instanceID'      => $id,
                'AIN'             => $AIN,
                'Manufacturer'    => '',
                'ProductName'     => '',
                'name'            => IPS_GetName($id)
            ];
            $DeviceValues[] = $AddDevice;
        }
        $Form['actions'][0]['values'] = $DeviceValues;
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
        $AllInstancesOfParent = array_flip(array_filter(IPS_GetInstanceListByModuleID(\FritzBox\GUID::HomeautomationDevice), [$this, 'FilterInstances']));
        foreach ($AllInstancesOfParent as $key => &$value) {
            $value = IPS_GetProperty($key, 'AIN');
        }
        return $AllInstancesOfParent;
    }

    private function GetHomeautomationDevices(): array
    {
        /* testing
         return [
             [
                 'NewAIN'          => '11657 0489978',
                 'NewManufacturer' => 'AVM',
                 'NewProductName'  => 'FRITZ!DECT 210',
                 'NewDeviceName'   => 'SD Geschirrspüler'
             ],
             [
                 'NewAIN'          => '57479 6611955',
                 'NewManufacturer' => 'AVM',
                 'NewProductName'  => 'FRITZ!DECT 999',
                 'NewDeviceName'   => 'SD WaMa'
             ]
         ];
         */
        $Index = 0;
        $Data = [];
        do {
            $Result = $this->GetGenericDeviceInfos($Index);
            if ($Result !== false) {
                $Data[] = $Result;
            }
            $Index++;
        } while ($Result !== false);
        return $Data;
    }

    private function GetGenericDeviceInfos(int $Index)
    {
        $Result = @$this->Send('GetGenericDeviceInfos', [
            'NewIndex'         => $Index
        ]);
        if ($Result === false) {
            return false;
        }
        return  $Result;
    }
}
