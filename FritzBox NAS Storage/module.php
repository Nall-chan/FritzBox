<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxNASStorage extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_storage'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Storage:1'
    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', -1);
        $this->RegisterPropertyInteger('RefreshInterval', 3600);
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
        $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval')*1000);
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
            case 'SMBEnable':
                return $this->SetSMBServer((bool)$Value);
            case 'FTPEnable':
                return $this->SetFTPServer((bool)$Value);
            case 'FTPWANEnable':
                return $this->SetFTPServerWAN((bool)$Value, $this->GetValue('FTPWANSSLOnly'));
            case 'FTPWANSSLOnly':
                return $this->SetFTPServerWAN($this->GetValue('FTPWANEnable'), (bool)$Value);
            }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }
    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('SMBEnable', 'SMB server', (bool)$result['NewSMBEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable('FTPEnable', 'FTP server', (bool)$result['NewFTPEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable('FTPStatus', 'FTP server state', (string)$result['NewFTPStatus'], VARIABLETYPE_STRING);
        $this->setIPSVariable('FTPWANEnable', 'WAN FTP server', (bool)$result['NewFTPWANEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable('FTPWANSSLOnly', 'WAN FTP SSL only', (bool)$result['NewFTPWANSSLOnly'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable('FTPWANPort', 'WAN FTP Port', (int)$result['NewFTPWANPort'], VARIABLETYPE_INTEGER);
        return true;
    }
    public function GetInfo()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetSMBServer(bool $Enable)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewSMBEnable'=> (int)$Enable
        ]);
        if ($result === null) {
            $this->UpdateInfo();
            return true;
        }
        return false;
    }
    public function SetFTPServer(bool $Enable)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewFTPEnable'=> (int)$Enable
        ]);
        if ($result === null) {
            $this->UpdateInfo();
            return true;
        }
        return false;
    }
    public function SetFTPServerWAN(bool $Enable, bool $SSLOnly)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewFTPWANEnable'=> (int)$Enable,
            'NewFTPWANSSLOnly'=> (int)$SSLOnly
        ]);
        if ($result === null) {
            $this->UpdateInfo();
            return true;
        }
        return false;
    }
}
