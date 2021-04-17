<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxDHCPServer extends FritzBoxModulBase
{
    protected static $ControlUrlArray = ['/upnp/control/lanhostconfigmgm'];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = ['urn:dslforum-org:service:LANHostConfigManagement:1'];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);
        $this->RegisterPropertyInteger('RefreshInterval', 3600);
        $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
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
        $this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval') * 1000);
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
            case 'RefreshState':
                return $this->UpdateInfo();
            case 'DHCPServerEnable':
                return $this->SetDHCPServerEnable($Value);
            case 'MinAddress':
                return $this->SetAddressRange($Value, $this->GetValue('MaxAddress'));
            case 'MaxAddress':
                return $this->SetAddressRange($this->GetValue('MinAddress'), $Value);
            case 'SubnetMask':
                return $this->SetSubnetMask($Value);
            case 'IPRouters':
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
        $this->setIPSVariable('DHCPServerEnable', 'DHCP active', (bool) $result['NewDHCPServerEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true, 1);
        $this->setIPSVariable('MinAddress', 'IP-Adresse Start', $result['NewMinAddress'], VARIABLETYPE_STRING, '', true, 2);
        $this->setIPSVariable('MaxAddress', 'IP-Adresse End', $result['NewMaxAddress'], VARIABLETYPE_STRING, '', true, 3);
        $this->setIPSVariable('SubnetMask', 'Subnet Mask', $result['NewSubnetMask'], VARIABLETYPE_STRING, '', true, 4);
        $this->setIPSVariable('IPRouters', 'Gateway', $result['NewIPRouters'], VARIABLETYPE_STRING, '', true, 5);
        $this->setIPSVariable('DNSServers', 'DNS-Server', $result['NewDNSServers'], VARIABLETYPE_STRING, '', false, 6);
        $this->setIPSVariable('DomainName', 'Domain', $result['NewDomainName'], VARIABLETYPE_STRING, '', false, 7);
        return true;
    }
}
