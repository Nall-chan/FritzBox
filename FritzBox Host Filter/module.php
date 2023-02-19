<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * @property int $HostNumberOfEntries
 */
class FritzBoxHostFilter extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_hostfilter'
    ];
    protected static $EventSubURLArray = [
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_HostFilter:1'
    ];
    protected static $SecondEventGUID = '{FE6C73CB-028B-F569-46AC-3C02FF1F8F2F}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->HostNumberOfEntries = 0;

        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshState', 0);
        parent::ApplyChanges();
        //$this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        //todo VAriablen anlegen aus Liste
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        /*
        switch ($Ident) {
                case 'RefreshState':
                    return $this->UpdateInfo();

                case 'X_AVM_DE_APEnabled':
                    return $this->SetEnable((bool) $Value);
            }*/
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (!$this->GetFile('Hosts')) {
            $Form['actions'][2]['visible'] = true;
            $Form['actions'][2]['popup']['items'][0]['caption'] = 'Hostnames not available!';
            $Form['actions'][2]['popup']['items'][1]['caption'] = 'The \'FritzBox Host\' instance is required to display hostnames.';
            $Form['actions'][2]['popup']['items'][1]['width'] = '200px';
            $ConfiguratorID = $this->GetConfiguratorID();
            if ($ConfiguratorID > 1) {
                $Form['actions'][2]['popup']['items'][2]['caption'] = 'Open Configurator';
                $Form['actions'][2]['popup']['items'][2]['visible'] = true;
                $Form['actions'][2]['popup']['items'][2]['objectID'] = $ConfiguratorID;
            }
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }
    //private
    public function RefreshHostList()
    {
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        $Rename = $this->ReadPropertyBoolean('RenameHostVariables');
        if (!$Variable) {
            return true;
        }
        if ($this->ParentID == 0) {
            return false;
        }
        $XMLData = $this->GetFile('Hosts');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts', 0);
        } else {
            $xml = new \simpleXMLElement($XMLData);
            if ($xml === false) {
                $this->SendDebug('XML decode error', $XMLData, 0);
            }
        }
        $Hosts = $this->HostNumberOfEntries;

        $ChildsOld = IPS_GetChildrenIDs($this->InstanceID);
        $ChildsNew = [];
        /*
        for ($i = 0; $i < $Hosts; $i++) {
            $Hostname = strtoupper((string) $result['NewAssociatedDeviceMACAddress']) . ' (' . (string) $result['NewAssociatedDeviceIPAddress'] . ')';
            $Ident = 'MAC' . strtoupper($this->ConvertIdent((string) $result['NewAssociatedDeviceMACAddress']));
            if (isset($xml)) {
                $Xpath = $xml->xpath('/List/Item[MACAddress="' . (string) $result['NewAssociatedDeviceMACAddress'] . '"]/HostName');
                if (count($Xpath) > 0) {
                    $Hostname = (string) $Xpath[0];
                }
            }
            if ($Variable) {
                $this->setIPSVariable($Ident, $Hostname, (int) $result['NewX_AVM-DE_Speed'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', false, $pos);
                $VarId = $this->GetIDForIdent($Ident);
                $ChildsNew[] = $VarId;
                if ($Rename && (IPS_GetName($VarId) != $Hostname)) {
                    IPS_SetName($VarId, $Hostname);
                }

                $SpeedId = $this->RegisterSubVariable($VarId, 'Speed', 'Speed', VARIABLETYPE_INTEGER, 'FB.MBits');
                SetValueInteger($SpeedId, (int) $result['NewX_AVM-DE_Speed']);
                $SignalId = $this->RegisterSubVariable($VarId, 'Signal', 'Signalstrength', VARIABLETYPE_INTEGER, '~Intensity.100');
                SetValueInteger($SignalId, (int) $result['NewX_AVM-DE_SignalStrength']);
            }

        }
         */
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
    public function MarkTicket()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetTicketIDStatus(int $TicketID)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewTicketID'=> $TicketID
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function DiscardAllTickets()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function DisallowWANAccessByIP(string $IPv4Address, bool $Disallow)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIPv4Address'=> $IPv4Address,
            'NewDisallow'   => (int) $Disallow
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetWANAccessByIP(string $IPv4Address)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIPv4Address'=> $IPv4Address
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
}
