<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxDHCPServer extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/lanhostconfigmgm'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:LANHostConfigManagement:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 3600);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshState, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshState . '",true);');
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
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
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
            case \FritzBox\Base\Action::RefreshState:
                return $this->UpdateInfo();
            case \FritzBox\DHCPServer\Variables::DHCPServerEnable:
                return $this->SetDHCPServerEnable((bool) $Value);
            case \FritzBox\DHCPServer\Variables::MinAddress:
                return $this->SetAddressRange($Value, $this->GetValue(\FritzBox\DHCPServer\Variables::MaxAddress));
            case \FritzBox\DHCPServer\Variables::MaxAddress:
                return $this->SetAddressRange($this->GetValue(\FritzBox\DHCPServer\Variables::MinAddress), $Value);
            case \FritzBox\DHCPServer\Variables::SubnetMask:
                return $this->SetSubnetMask($Value);
            case \FritzBox\DHCPServer\Variables::IPRouters:
                return $this->SetIPRouter($Value);
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

    public function GetAddressRange()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return $result;
    }

    public function GetIPRoutersList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return $result;
    }

    public function GetSubnetMask()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return $result;
    }

    public function GetDNSServers()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return $result;
    }

    public function GetIPInterfaceNumberOfEntries()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return $result;
    }

    public function SetDHCPServerEnable(bool $Value)
    {
        $result = $this->Send(__FUNCTION__, ['NewDHCPServerEnable'=>(int) $Value]);
        if ($result === false) {
            return false;
        }

        return true;
    }

    public function SetIPInterface(bool $Enable, string $IPInterfaceIPAddress, string $IPInterfaceSubnetMask, string $IPInterfaceIPAddressingType)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewEnable'                     => $Enable,
            'NewIPInterfaceIPAddress'       => $IPInterfaceIPAddress,
            'NewIPInterfaceSubnetMask'      => $IPInterfaceSubnetMask,
            'NewIPInterfaceIPAddressingType'=> $IPInterfaceIPAddressingType
        ]);
        if ($result === false) {
            return false;
        }

        return true;
    }

    public function SetAddressRange(string $MinAddress, string $MaxAddress)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewMinAddress'=> $MinAddress,
            'NewMaxAddress'=> $MaxAddress
        ]);
        if ($result === false) {
            return false;
        }

        return true;
    }

    public function SetIPRouter(string $IPRouters)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIPRouters'=> $IPRouters
        ]);
        if ($result === false) {
            return false;
        }

        return true;
    }

    public function SetSubnetMask(string $SubnetMask)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewSubnetMask'=> $SubnetMask
        ]);
        if ($result === false) {
            return false;
        }

        return true;
    }

    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\DHCPServer\Variables::DHCPServerEnable, 'DHCP active', (bool) $result['NewDHCPServerEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true, 1);
        $this->setIPSVariable(\FritzBox\DHCPServer\Variables::MinAddress, 'IP-Adresse Start', $result['NewMinAddress'], VARIABLETYPE_STRING, '', true, 2);
        $this->setIPSVariable(\FritzBox\DHCPServer\Variables::MaxAddress, 'IP-Adresse End', $result['NewMaxAddress'], VARIABLETYPE_STRING, '', true, 3);
        $this->setIPSVariable(\FritzBox\DHCPServer\Variables::SubnetMask, 'Subnet Mask', $result['NewSubnetMask'], VARIABLETYPE_STRING, '', true, 4);
        $this->setIPSVariable(\FritzBox\DHCPServer\Variables::IPRouters, 'Gateway', $result['NewIPRouters'], VARIABLETYPE_STRING, '', true, 5);
        $this->setIPSVariable(\FritzBox\DHCPServer\Variables::DNSServers, 'DNS-Server', $result['NewDNSServers'], VARIABLETYPE_STRING, '', false, 6);
        $this->setIPSVariable(\FritzBox\DHCPServer\Variables::DomainName, 'Domain', $result['NewDomainName'], VARIABLETYPE_STRING, '', false, 7);
        return true;
    }
}
