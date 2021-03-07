<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxHosts extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/hosts'
    ];
    protected static $EventSubURLArray = [
        '/upnp/control/hosts'
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:Hosts:1'
    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);
        $id = $this->RegisterVariableInteger('HostNumberOfEntries', $this->Translate('Number Of Hosts'), '', 1);
        $this->RegisterMessage($id, VM_UPDATE);
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

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
    }

    public function GetHostNumberOfEntries()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('HostNumberOfEntries', $this->Translate('Number Of Hosts'), (int)$result, VARIABLETYPE_INTEGER, '', false, 1);
        return $result;
    }
    public function GetSpecificHostEntry(string $MACAddress)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewMACAddress'=> $MACAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetGenericHostEntry(int $Index)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIndex'=> $Index
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetSpecificHostEntryByIP(string $IPAddress)
    {
        $result = $this->Send('X_AVM-DE_GetSpecificHostEntryByIP', [
            'NewIPAddress'=> $IPAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetChangeCounter()
    {
        $result = $this->Send('X_AVM-DE_GetChangeCounter');
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetHostNameByMACAddress(string $MACAddress, string $Hostname)
    {
        $result = $this->Send('X_AVM-DE_SetHostNameByMACAddress', [
            'NewMACAddress'=> $MACAddress,
            'NewHostName'  => $Hostname
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetAutoWakeOnLANByMACAddress(string $MACAddress)
    {
        $result = $this->Send('X_AVM-DE_GetAutoWakeOnLANByMACAddress', [
            'NewMACAddress'=> $MACAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetAutoWakeOnLANByMACAddress(string $MACAddress, bool $Enabled)
    {
        $result = $this->Send('X_AVM-DE_SetAutoWakeOnLANByMACAddress', [
            'NewMACAddress'    => $MACAddress,
            'NewAutoWOLEnabled'=> $Enabled
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function WakeOnLANByMACAddress(string $MACAddress)
    {
        $result = $this->Send('X_AVM-DE_WakeOnLANByMACAddress', [
            'NewMACAddress'    => $MACAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function HostsCheckUpdate()
    {
        $result = $this->Send('X_AVM-DE_HostsCheckUpdate');
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function HostDoUpdate(string $MACAddress)
    {
        $result = $this->Send('X_AVM-DE_HostDoUpdate', [
            'NewMACAddress'    => $MACAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetHostListPath()
    {
        $result = $this->Send('X_AVM-DE_GetHostListPath');
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetMeshListPath()
    {
        $result = $this->Send('X_AVM-DE_GetMeshListPath');
        if ($result === false) {
            return false;
        }
        return $result;
    }
}
