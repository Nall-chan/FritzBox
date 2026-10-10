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
        $this->RegisterPropertyString(\FritzBox\Homeautomation\Property::AIN, '');
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 5);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        parent::ApplyChanges();
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
        if ($this->ReadPropertyString(\FritzBox\Homeautomation\Property::AIN) != '') {
            $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
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
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
            case \FritzBox\Homeautomation\Variables::SwitchState:
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

    public function GetSpecificDeviceInfos()
    {
        $Result = $this->Send('GetSpecificDeviceInfos', [
            'NewAIN'         => $this->ReadPropertyString(\FritzBox\Homeautomation\Property::AIN)
        ]);
        if ($Result === false) {
            return false;
        }
        return $Result;
    }

    public function SetDeviceName(string $Name)
    {
        $Result = $this->Send('SetDeviceName', [
            'NewAIN'         => $this->ReadPropertyString(\FritzBox\Homeautomation\Property::AIN),
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
            'NewAIN'         => $this->ReadPropertyString(\FritzBox\Homeautomation\Property::AIN),
            'NewSwitchState' => $State
        ]);
        if ($Result === false) {
            return false;
        }
        return true;
    }

    private function UpdateInfo()
    {
        $Result = $this->GetSpecificDeviceInfos();
        if ($Result === false) {
            return false;
        }

        $this->SetSummary((string) $Result['NewProductName'] . ' (' . (string) $Result['NewFirmwareVersion'] . ')');
        $this->setIPSVariable(\FritzBox\Homeautomation\Variables::Present, 'Present', (string) $Result['NewPresent'], VARIABLETYPE_STRING, 'FB.AHA.Present', false);
        if ($Result['NewMultimeterIsEnabled'] == 'ENABLED') {
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::MultimeterIsValid, 'Multimeter (valid)', (string) $Result['NewMultimeterIsValid'], VARIABLETYPE_STRING, 'FB.AHA.Valid', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::MultimeterPower, 'Multimeter (Power)', ((int) $Result['NewMultimeterPower'] / 100), VARIABLETYPE_FLOAT, '~Watt', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::MultimeterEnergy, 'Multimeter (Energy)', (int) $Result['NewMultimeterEnergy'], VARIABLETYPE_FLOAT, '~Electricity.Wh', false);
        }
        if ($Result['NewTemperatureIsEnabled'] == 'ENABLED') {
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::TemperatureIsValid, 'Temperature (valid)', (string) $Result['NewTemperatureIsValid'], VARIABLETYPE_STRING, 'FB.AHA.Valid', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::TemperatureCelsius, 'Temperature (Celsius)', ((int) $Result['NewTemperatureCelsius'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::TemperatureOffset, 'Temperature (Offset)', ((int) $Result['NewTemperatureOffset'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
        }
        if ($Result['NewSwitchIsEnabled'] == 'ENABLED') {
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::SwitchIsValid, 'Switch (valid)', (string) $Result['NewSwitchIsValid'], VARIABLETYPE_STRING, 'FB.AHA.Valid', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::SwitchState, 'Switch (State)', (string) $Result['NewSwitchState'], VARIABLETYPE_STRING, 'FB.AHA.State', true);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::SwitchMode, 'Switch (Mode)', (string) $Result['NewSwitchMode'], VARIABLETYPE_STRING, 'FB.AHA.Mode', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::SwitchLock, 'Switch (Lock)', (int) $Result['NewSwitchLock'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', false);
        }
        if ($Result['NewHkrIsValid'] == 'ENABLED') {
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::HkrIsTemperature, 'Heating Thermostat (actual temperature)', ((int) $Result['NewHkrIsTemperature'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);

            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::HkrSetTemperature, 'Heating Thermostat (target temperature)', ((int) $Result['NewHkrSetTemperature'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::HkrSetVentilStatus, 'Heating Thermostat (target valve state)', (string) $Result['NewHkrSetVentilStatus'], VARIABLETYPE_STRING, 'FB.AHA.VentilState', false);

            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::HkrReduceTemperature, 'Heating Thermostat (reduce temperature)', ((int) $Result['NewHkrReduceTemperature'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::HkrReduceVentilStatus, 'Heating Thermostat (reduce valve state)', (string) $Result['NewHkrReduceVentilStatus'], VARIABLETYPE_STRING, 'FB.AHA.VentilState', false);

            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::HkrComfortTemperature, 'Heating Thermostat (comfort temperature)', ((int) $Result['NewHkrComfortTemperature'] / 10), VARIABLETYPE_FLOAT, '~Temperature', false);
            $this->setIPSVariable(\FritzBox\Homeautomation\Variables::HkrComfortVentilStatus, 'Heating Thermostat (comfort valve state)', (string) $Result['NewHkrComfortVentilStatus'], VARIABLETYPE_STRING, 'FB.AHA.VentilState', false);
        }

        return true;
    }
}