<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * @property int $HostNumberOfEntriesId
 */
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
    protected static $SecondEventGUID ='{FE6C73CB-028B-F569-46AC-3C02FF1F8F2F}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->HostNumberOfEntriesId=0;
        $this->RegisterPropertyInteger('Index', 0);
        $this->RegisterPropertyBoolean('HostAsVariable', true);
        $this->RegisterPropertyBoolean('ShowOnlineCounter', true);
        $this->RegisterPropertyBoolean('HostAsTable', true);
        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterTimer('RefreshHosts', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshHosts",true);');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->HostNumberOfEntriesId = $this->RegisterVariableInteger('HostNumberOfEntries', $this->Translate('Number Of Hosts'), '', -2);
        $this->RegisterMessage($this->HostNumberOfEntriesId, VM_UPDATE);
        parent::ApplyChanges();
        $this->SetTimerInterval('RefreshHosts', $this->ReadPropertyInteger('RefreshInterval')*1000);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case VM_UPDATE:
                if ($SenderID == $this->HostNumberOfEntriesId) {
                    $this->RefreshHostList();
                    return;
                }
                break;
        }
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
    }
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        if ($Ident == 'RefreshHosts') {
            return $this->GetHostNumberOfEntries();
        }
        $this->SendDebug(__FUNCTION__, $Ident, 0);
        if (strpos($Ident, 'MAC')===0) {
            if ($Value===true) {
                $MACAddress = implode(':', str_split(substr($Ident, 3), 2));
                $this->WakeOnLANByMACAddress($MACAddress);
            }
        }
        //invalid Ident
        return false;
    }
    public function ReceiveData($JSONString)
    {
        $Processed = parent::ReceiveData($JSONString);
        if ($Processed !== null) {
            return $Processed;
        }
        $data = json_decode($JSONString, true);
        unset($data['DataID']);
        $this->SendDebug('ReceiveHostData', $data, 0);
        return true;
    }
    public function RefreshHostList()
    {
        $Table = $this->ReadPropertyBoolean('HostAsTable');
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        if (!($Variable || ($Table))) {
            return true;
        }
        if ($this->ParentID == 0) {
            return false;
        }
            
        $File = $this->GetHostListPath();
        if ($File === false) {
            return false;
        }
        
        if (!$this->LoadAndSaveFile($File, 'Hosts.xml')) {
            return false;
        }
        
        //$Url = IPS_GetProperty($this->ParentID, 'Host'). $File;
        //$XMLData = @Sys_GetURLContentEx($Url, ['Timeout'=>3000]);
        $XMLData = $this->GetFile('Hosts.xml');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', $Url, 0);
            return false;
        }
        $xml = new simpleXMLElement($XMLData);
        if ($xml === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
        }
        //var_dump($xml->Item );
        //$xmlItems = $xml->xpath('//Item');
        $OnlineCounter=0;
        $TableData=[];
        $pos=0;
        foreach ($xml as $xmlItem) {
            $this->SendDebug('XML xmlItem', (array)$xmlItem, 0);
            if ((string)$xmlItem->MACAddress == '') {
                $Ident = 'IP'.strtoupper(str_replace([':','.','[',']'], ['','','',''], (string)$xmlItem->IPAddress));
                $Action = false;
            } else {
                $Ident = 'MAC'.strtoupper(str_replace(':', '', (string)$xmlItem->MACAddress));
                $Action = true;
            }
            if ($Variable) {
                $this->setIPSVariable($Ident, (string)$xmlItem->HostName, (int)$xmlItem->Active==1, VARIABLETYPE_BOOLEAN, '~Switch', $Action, ++$pos);
            }
            if ((bool)$xmlItem->Active) {
                $OnlineCounter++;
            }
            $TableData[] = (array)$xmlItem;
        }
        if ($this->ReadPropertyBoolean('ShowOnlineCounter')) {
            $this->setIPSVariable('HostNumberActive', $this->Translate('Number of active hosts'), $OnlineCounter, VARIABLETYPE_INTEGER, '', false, -1);
        }
        // TableData
        //$this->CreateHostHTMLTable($TableData);
        return true;
    }
    private function CreateHostHTMLTable(array $TableData)
    {
    }
    public function GetHostNumberOfEntries()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('HostNumberOfEntries', $this->Translate('Number of hosts'), (int)$result, VARIABLETYPE_INTEGER, '', false, -2);
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
