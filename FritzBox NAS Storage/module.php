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

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 3600);
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
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
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
            case \FritzBox\NASStorage\Variables::SMBEnable:
                return $this->SetSMBServer((bool) $Value);
            case \FritzBox\NASStorage\Variables::FTPEnable:
                return $this->SetFTPServer((bool) $Value);
            case \FritzBox\NASStorage\Variables::FTPWANEnable:
                return $this->SetFTPServerWAN((bool) $Value, $this->GetValue(\FritzBox\NASStorage\Variables::FTPWANSSLOnly));
            case \FritzBox\NASStorage\Variables::FTPWANSSLOnly:
                return $this->SetFTPServerWAN($this->GetValue(\FritzBox\NASStorage\Variables::FTPWANEnable), (bool) $Value);
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

    public function SetSMBServer(bool $Enable)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewSMBEnable'=> (int) $Enable
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
            'NewFTPEnable'=> (int) $Enable
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
            'NewFTPWANEnable' => (int) $Enable,
            'NewFTPWANSSLOnly'=> (int) $SSLOnly
        ]);
        if ($result === null) {
            $this->UpdateInfo();
            return true;
        }
        return false;
    }

    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\NASStorage\Variables::SMBEnable, 'SMB server', (bool) $result['NewSMBEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable(\FritzBox\NASStorage\Variables::FTPEnable, 'FTP server', (bool) $result['NewFTPEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable(\FritzBox\NASStorage\Variables::FTPStatus, 'FTP server state', (string) $result['NewFTPStatus'], VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\NASStorage\Variables::FTPWANEnable, 'WAN FTP server', (bool) $result['NewFTPWANEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable(\FritzBox\NASStorage\Variables::FTPWANSSLOnly, 'WAN FTP SSL only', (bool) $result['NewFTPWANSSLOnly'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable(\FritzBox\NASStorage\Variables::FTPWANPort, 'WAN FTP Port', (int) $result['NewFTPWANPort'], VARIABLETYPE_INTEGER);
        return true;
    }
}
