<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxLANConfig extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/lanhostconfigmgm'
    ];
    protected static $EventSubURLArray = [
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:LANHostConfigManagement:1'
    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);
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
    }
    public function GetInfo()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
        $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
        $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
         */
        return true;
    }
    public function GetAddressRange()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return true;
    }
    public function GetIPRoutersList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return true;
    }
    public function GetSubnetMask()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return true;
    }
    public function GetDNSServers()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return true;
    }
    public function GetIPInterfaceNumberOfEntries()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return true;
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
}