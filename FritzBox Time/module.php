<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxTime extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/time'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:Time:1'
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', -1);
        $this->RegisterPropertyInteger('RefreshInterval', 60);
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
        $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval') * 1000);
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
            case 'NTPServer':
                return $this->SetNTPServers($Value, $this->GetValue('NTPServer2'));
            case 'NTPServer2':
                return $this->SetNTPServers($this->GetValue('NTPServer'), $Value);
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
    public function SetNTPServers(string $NTPServer1, string $NTPServer2)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewNTPServer1'=> $NTPServer1,
            'NewNTPServer2'=> $NTPServer2
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
        $VarTime = new DateTime((string) $result['NewCurrentLocalTime']);
        $this->setIPSVariable('CurrentLocalTime', 'Current system clock', $VarTime->getTimestamp(), VARIABLETYPE_INTEGER, '~UnixTimestamp');
        $this->setIPSVariable('NTPServer', 'NTP-Server 1', $result['NewNTPServer1'], VARIABLETYPE_STRING, '', true);
        $this->setIPSVariable('NTPServer2', 'NTP-Server 2', $result['NewNTPServer2'], VARIABLETYPE_STRING, '', true);
        //todo
        /*
        NewDaylightSavingsUsed
        NewDaylightSavingsStart
        NewDaylightSavingsEnd
         */
        return true;
    }
}
