<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * @property array $ServiceIdents
 */
class FritzBoxMyFritz extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_myfritz'
    ];
    protected static $EventSubURLArray = [
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_MyFritz:1'
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
        $this->ServiceIdents = [];
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        parent::ApplyChanges();
        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateInfo();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        if (in_array($this->ConvertIdent($Ident), $this->ServiceIdents)) {
            return $this->EnableService($this->ConvertIdent($Ident), (bool) $Value);
        }
        switch ($Ident) {
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    public function GetInfo()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function EnableService(string $Ident, bool $Value)
    {
        if (@$this->GetIDForIdent($Ident) == false) {
            $this->ReportError($this->Translate('Invalid Ident.'));
            return false;
        }

        return $this->UpdateService($Ident, $Value);
    }

    public function GetIdentsForActions()
    {
        return $this->ServiceIdents;
    }

    public function GetNumberOfServices()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetServiceByIndex(int $index)
    {
        $result = $this->Send(__FUNCTION__, ['NewIndex'=>$index]);
        if ($result == false) {
            return false;
        }
        return [
            'NewEnabled'                => (bool) $result['NewEnabled'],
            'NewName'                   => (string) $result['NewName'],
            'NewScheme'                 => (string) $result['NewScheme'],
            'NewPort'                   => (int) $result['NewPort'],
            'NewURLPath'                => (string) $result['NewURLPath'],
            'NewType'                   => (string) $result['NewType'],
            'NewIPv4Addresses'          => (string) $result['NewIPv4Addresses'],
            'NewIPv6Addresses'          => (string) $result['NewIPv6Addresses'],
            'NewIPv6InterfaceIDs'       => (string) $result['NewIPv6InterfaceIDs'],
            'NewMACAddress'             => (string) $result['NewMACAddress'],
            'NewHostName'               => (string) $result['NewHostName'],
            'NewDynDnsLabel'            => (string) $result['NewDynDnsLabel'],
            'NewStatus'                 => (int) $result['NewStatus']
        ];
    }

    public function DeleteServiceByIndex(int $Index)
    {
        return $this->Send(__FUNCTION__, [
            'NewIndex'  => $Index
        ]) === null;
    }

    public function SetServiceByIndex(
        int $NewIndex,
        bool $NewEnabled,
        string $NewName,
        string $NewScheme,
        int $NewPort,
        string $NewURLPath,
        string $NewType,
        string $NewIPv4Address,
        string $NewIPv6Address,
        string $NewIPv6InterfaceID,
        string $NewMACAddress,
        string $NewHostName
    ) {
        $result = $this->Send(__FUNCTION__, [
            'NewIndex'              => $NewIndex,
            'NewEnabled'            => (int) $NewEnabled,
            'NewName'               => $NewName,
            'NewScheme'             => $NewScheme,
            'NewPort'               => $NewPort,
            'NewURLPath'            => $NewURLPath,
            'NewType'               => $NewType,
            'NewIPv4Address'        => $NewIPv4Address,
            'NewIPv6Address'        => $NewIPv6Address,
            'NewIPv6InterfaceID'    => $NewIPv6InterfaceID,
            'NewMACAddress'         => $NewMACAddress,
            'NewHostName'           => $NewHostName
        ]);

        if ($result === null) {
            return true;
        }
        return false;
    }

    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result == false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\MyFritz\Variables::Enabled, 'MyFritz enabled', (bool) $result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', false, -5);
        $this->setIPSVariable(\FritzBox\MyFritz\Variables::DeviceRegistered, 'FritzBox registered', (bool) $result['NewDeviceRegistered'], VARIABLETYPE_BOOLEAN, '~Switch', false, -4);
        $this->setIPSVariable(\FritzBox\MyFritz\Variables::DynDNSName, 'MyFritz address', (string) $result['NewDynDNSName'], VARIABLETYPE_STRING, '', false, -3);
        $this->setIPSVariable(\FritzBox\MyFritz\Variables::DynDNSURL, 'FritzBox URL', (string) $result['NewDynDNSName'] . ':' . (string) $result['NewPort'], VARIABLETYPE_STRING, '', false, -2);
        $result = $this->UpdateService();
        if ($result == false) {
            return false;
        }
    }

    private function UpdateService(string $NewIdent = '', bool $NewEnabled = false)
    {
        $NoOfServices = $this->GetNumberOfServices();
        if ($NoOfServices === false) {
            return false;
        }
        $MyIPs = array_column(Sys_GetNetworkInfo(), 'IP');
        if (IPS_GetOption('NATPublicIP') !== '') {
            $MyIPs[] = IPS_GetOption('NATPublicIP');
        }
        $MyIdents = [];
        $this->setIPSVariable(\FritzBox\MyFritz\Variables::NumberOfServices, 'Number of MyFritz services', $NoOfServices, VARIABLETYPE_INTEGER, '', false, -1);
        for ($i = 0; $i < $NoOfServices; $i++) {
            $result = $this->GetServiceByIndex($i);
            if ($result === false) {
                continue;
            }

            $Ident = $this->ConvertIdent((string) $result['NewName']);
            $MyIdents[] = $Ident;
            if ($NewIdent == $Ident) {
                $SaveResult = $result;
                $SaveIndex = $i;
            } else {
                $this->setIPSVariable($Ident, (string) $result['NewName'], (bool) $result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', false, $i);
                if (in_array((string) $result['NewIPv4Addresses'], $MyIPs)) {
                    $this->EnableAction($Ident);
                }
            }
        }
        $this->ServiceIdents = $MyIdents;
        if ($NewIdent !== '') {

            if (!in_array((string) $SaveResult['NewIPv4Addresses'], $MyIPs)) {
                $this->ReportError($this->Translate('Only rules that target the Symcon host can be changed.'));
                return false;
            }
            if (!$this->DeleteServiceByIndex($SaveIndex)) {
                return false;
            }
            $changeResult = $this->SetServiceByIndex(
                --$NoOfServices,
                $NewEnabled,
                $SaveResult['NewName'],
                $SaveResult['NewScheme'],
                $SaveResult['NewPort'],
                $SaveResult['NewURLPath'],
                $SaveResult['NewType'],
                $SaveResult['NewIPv4Addresses'],
                $SaveResult['NewIPv6Addresses'],
                $SaveResult['NewIPv6InterfaceIDs'],
                $SaveResult['NewMACAddress'],
                $SaveResult['NewHostName']
            );
            if ($changeResult) {
                $SaveResult['NewEnabled'] = $NewEnabled;
            }
            $Ident = $this->ConvertIdent((string) $SaveResult['NewName']);
            $this->setIPSVariable($Ident, (string) $SaveResult['NewName'], (bool) $SaveResult['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch');
        }
        return true;
    }
}
