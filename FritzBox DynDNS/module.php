<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxDynDns extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_remote'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_RemoteAccess:1'
    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterTimer('RefreshInfo', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshInfo",true);');
    }

    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('FB.DynDnyState');
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshInfo', 0);

        $this->RegisterProfileStringEx('FB.DynDnyState', 'Network', '', '', [
            ['offline', $this->Translate('offline'), '', -1],
            ['checking', $this->Translate('checking'), '', -1],
            ['updating', $this->Translate('updating'), '', -1],
            ['updated', $this->Translate('updated'), '', -1],
            ['verifying', $this->Translate('verifying'), '', -1],
            ['complete', $this->Translate('complete'), '', -1],
            ['new-address', $this->Translate('new address'), '', -1],
            ['new address', $this->Translate('new address'), '', -1],
            ['account-disabled', $this->Translate('account disabled'), '', -1],
            ['internet-not-connected', $this->Translate('internet not connected'), '', -1],
            ['undefined', $this->Translate('undefined'), '', -1]
        ]);
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateDynDnsClient();
        $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval') * 1000);
    }
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshInfo':
                return $this->UpdateDynDnsClient();
            case 'Enable':
                return $this->EnableRemoteAccess($Value);
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
    public function GetDDNSInfo()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function EnableRemoteAccess(bool $Enable)
    {
        $result = $this->Send('SetEnable', [
            'NewEnabled'=> (int) $Enable
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function EnableConfig(bool $Enable, int $Port, string $Username, string $Password)
    {
        $result = $this->Send('SetConfig', [
            'NewEnabled' => (int) $Enable,
            'NewPort'    => $Port,
            'NewUsername'=> $Username,
            'NewPassword'=> $Password,
        ]);
        if ($result === null) {
            return true;
        }
        return false;
    }
    public function SetDDNSConfig(
        bool $Enable,
        string $ProviderName,
        string $UpdateURL,
        string $Domain,
        string $Username,
        string $Mode,
        string $ServerIPv4,
        string $ServerIPv6,
        string $Password
    ) {
        switch ($Mode) {
            case 'ddns_v4':
            case 'ddns_v6':
            case 'ddns_both':
            case 'ddns_together':
                break;
            default:
            trigger_error($this->Translate('Invalid value for $Mode'), E_USER_NOTICE);
                return false;

        }
        $result = $this->Send(__FUNCTION__, [
            'NewEnabled'     => (int) $Enable,
            'NewProviderName'=> $ProviderName,
            'NewUpdateURL'   => $UpdateURL,
            'NewDomain'      => $Domain,
            'NewUsername'    => $Username,
            'NewMode'        => $Mode,
            'NewServerIPv4'  => $ServerIPv4,
            'NewServerIPv6'  => $ServerIPv6,
            'NewPassword'    => $Password
        ]);
        if ($result === null) {
            return true;
        }
        return false;
    }
    private function UpdateDynDnsClient()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('Enable', 'Remote access active', (bool) $result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', true, 1);

        $result = $this->GetDDNSInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('EnableDDNS', 'DynDns active', (bool) $result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', false, 2);
        $this->setIPSVariable('ProviderName', 'Provider', (string) $result['NewProviderName'], VARIABLETYPE_STRING, '', false, 3);
        $this->setIPSVariable('Domain', 'Domain', (string) $result['NewDomain'], VARIABLETYPE_STRING, '', false, 4);
        $this->setIPSVariable('IPv4State', 'IPv4 State', (string) $result['NewStatusIPv4'], VARIABLETYPE_STRING, 'FB.DynDnyState', false, 5);
        $this->setIPSVariable('IPv6State', 'IPv6 State', (string) $result['NewStatusIPv6'], VARIABLETYPE_STRING, 'FB.DynDnyState', false, 6);
        return true;
    }
}
