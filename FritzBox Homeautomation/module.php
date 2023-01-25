<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxHomeautomation extends FritzBoxModulBase
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
        $this->RegisterPropertyString('AIN', '');
        $this->RegisterPropertyInteger('RefreshInterval', 5);
        $this->RegisterTimer('RefreshInfo', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshInfo",true);');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshInfo', 0);
        parent::ApplyChanges();
        /*$this->RegisterProfileInteger('FB.Intensity', 'FB.Intensity', '', ' %', 0, 100, 1);
         */
        $this->RegisterProfileStringEx('FB.AHA.Present', '', '', '', [
            ['DISCONNECTED', 'disconnected', '', -1],
            ['REGISTRERED', 'registrered', '', -1],
            ['CONNECTED', 'connected', '', -1],
            ['UNKNOWN', 'unknown', '', -1]
        ]);
        $this->RegisterProfileStringEx('FB.AHA.Valid', '', '', '', [
            ['INVALID', 'invalid', '', -1],
            ['VALID', 'valid', '', -1],
            ['UNDEFINED', 'undefined', '', -1]
        ]);
        $this->RegisterProfileStringEx('FB.AHA.Mode', '', '', '', [
            ['AUTO', 'automatic', '', -1],
            ['MANUAL', 'manual', '', -1],
            ['UNDEFINED', 'undefined', '', -1]
        ]);
        $this->RegisterProfileStringEx('FB.AHA.State', '', '', '', [
            ['OFF', 'off', '', -1],
            ['ON', 'on', '', -1],
            ['TOGGLE', 'toggle', '', -1]
        ]);
        $this->RegisterProfileStringEx('FB.AHA.VentilState', '', '', '', [
            ['CLOSED', 'closed', '', -1],
            ['OPEN', 'open', '', -1],
            ['TEMP', 'temp controlled', '', -1]
        ]);
        if ($this->ReadPropertyString('AIN') != '') {
            $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateInfo();
    }
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshInfo':
                return $this->UpdateInfo();
            case 'SwitchState':
                return $this->SetSwitch((string) $Value);
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }
    public function GetInfo()
    {
        $Result = $this->Send('GetInfo');
        if ($Result === false) {
            return false;
        }
        return $Result;
    }
    public function UpdateInfo()
    {
        $Result = $this->GetSpecificDeviceInfos();
        if ($Result === false) {
            return false;
        }

        $this->SetSummary((string) $Result['NewProductName'] . ' (' . (string) $Result['NewFirmwareVersion'] . ')');
        $this->setIPSVariable('Present', 'Present', (string) $Result['NewPresent'], VARIABLETYPE_STRING, 'FB.AHA.Present', false);
        if ($Result['NewMultimeterIsEnabled'] == 'ENABLED') {
            $this->setIPSVariable('MultimeterIsValid', 'Multimeter (valid)', (string) $Result['NewMultimeterIsValid'], VARIABLETYPE_STRING, 'FB.AHA.Valid', false);
            $this->setIPSVariable('MultimeterPower', 'Multimeter (Power)', ((int) $Result['NewMultimeterPower'] / 100), VARIABLETYPE_FLOAT, '~Watt', false);
            $this->setIPSVariable('MultimeterEnergy', 'Multimeter (Energy)', (int) $Result['NewMultimeterEnergy'], VARIABLETYPE_FLOAT, '~Electricity.Wh', false);
        }
        if ($Result['NewTemperatureIsEnabled'] == 'ENABLED') {
            $this->setIPSVariable('TemperatureIsValid', 'Temperature (valid)', (string) $Result['NewTemperatureIsValid'], VARIABLETYPE_STRING, 'FB.AHA.Valid', false);
            $this->setIPSVariable('TemperatureCelsius', 'Temperature (Celsius)', ((int) $Result['NewTemperatureCelsius'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
            $this->setIPSVariable('TemperatureOffset', 'Temperature (Offset)', ((int) $Result['NewTemperatureOffset'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
        }
        if ($Result['NewSwitchIsEnabled'] == 'ENABLED') {
            $this->setIPSVariable('SwitchIsValid', 'Switch (valid)', (string) $Result['NewSwitchIsValid'], VARIABLETYPE_STRING, 'FB.AHA.Valid', false);
            $this->setIPSVariable('SwitchState', 'Switch (State)', (string) $Result['NewSwitchState'], VARIABLETYPE_FLOAT, 'FB.AHA.State', true);
            $this->setIPSVariable('SwitchMode', 'Switch (Mode)', (string) $Result['NewSwitchMode'], VARIABLETYPE_STRING, 'FB.AHA.Mode', false);
            $this->setIPSVariable('SwitchLock', 'Switch (Lock)', (int) $Result['NewSwitchLock'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', false);
        }
        if ($Result['NewHkrIsValid'] == 'ENABLED') {
            $this->setIPSVariable('HkrIsTemperature', 'Heating Thermostat (actual temperature)', ((int) $Result['NewHkrIsTemperature'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);

            $this->setIPSVariable('HkrSetTemperature', 'Heating Thermostat (target temperature)', ((int) $Result['NewHkrSetTemperature'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
            $this->setIPSVariable('HkrSetVentilStatus', 'Heating Thermostat (target valve state)', (string) $Result['NewHkrSetVentilStatus'], VARIABLETYPE_STRING, 'FB.AHA.VentilState', false);

            $this->setIPSVariable('HkrReduceTemperature', 'Heating Thermostat (reduce temperature)', ((int) $Result['NewHkrReduceTemperature'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
            $this->setIPSVariable('HkrReduceVentilStatus', 'Heating Thermostat (reduce valve state)', (string) $Result['NewHkrReduceVentilStatus'], VARIABLETYPE_STRING, 'FB.AHA.VentilState', false);

            $this->setIPSVariable('HkrComfortTemperature', 'Heating Thermostat (comfort temperature)', ((int) $Result['NewHkrComfortTemperature'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
            $this->setIPSVariable('HkrComfortVentilStatus', 'Heating Thermostat (comfort valve state)', (string) $Result['NewHkrComfortVentilStatus'], VARIABLETYPE_STRING, 'FB.AHA.VentilState', false);
        }

        return true;
    }
    public function GetSpecificDeviceInfos()
    {
        $Result = $this->Send('GetSpecificDeviceInfos', [
            'NewAIN'         => $this->ReadPropertyString('AIN')
        ]);
        if ($Result === false) {
            return false;
        }
        return $Result;
    }

    public function SetDeviceName(string $Name)
    {
        $Result = $this->Send('SetDeviceName', [
            'NewAIN'         => $this->ReadPropertyString('AIN'),
            'NewDeviceName'  => $Name
        ]);
        if ($Result === false) {
            return false;
        }
        return true;
    }

    public function SetSwitch(string $State)
    {
        /*
        OFF Switch off
        ON Switch On
        TOGGLE Toggle switch state
         */
        $Result = $this->Send('SetSwitch', [
            'NewAIN'         => $this->ReadPropertyString('AIN'),
            'NewSwitchState' => $State
        ]);
        if ($Result === false) {
            return false;
        }
        return true;
    }
}