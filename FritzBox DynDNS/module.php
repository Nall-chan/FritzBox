<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxDynDns extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_remote'
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_RemoteAccess:1'
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);

        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateDynDnsClient();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
    }

    public function GetInfo(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetDDNSInfo(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    /**
     * Liefert die Liste der von der FritzBox unterstützten DynDNS-Anbieter (XML).
     *
     * @return string|false
     */
    public function GetDDNSProviders(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    public function EnableRemoteAccess(bool $Enable): bool
    {
        $result = $this->Send('SetEnable', [
            'NewEnabled'=> (int) $Enable
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }

    public function EnableConfig(bool $Enable, int $Port, string $Username, string $Password): bool
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
                $this->ReportError($this->Translate('Invalid value for $Mode'));
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

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateDynDnsClient();
            case \FritzBox\DynDNS\Variables::Enable:
                return $this->EnableRemoteAccess((bool) $Value);
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);

        return false;
    }

    private function UpdateDynDnsClient()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\DynDNS\Variables::Enable, 'Remote access active', (bool) $result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', true, 1);

        $result = $this->GetDDNSInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\DynDNS\Variables::EnableDDNS, 'DynDns active', (bool) $result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', false, 2);
        $this->setIPSVariable(\FritzBox\DynDNS\Variables::ProviderName, 'Provider', (string) $result['NewProviderName'], VARIABLETYPE_STRING, '', false, 3);
        $this->setIPSVariable(\FritzBox\DynDNS\Variables::Domain, 'Domain', (string) $result['NewDomain'], VARIABLETYPE_STRING, '', false, 4);
        $this->setIPSVariable(\FritzBox\DynDNS\Variables::IPv4State, 'IPv4 State', (string) $result['NewStatusIPv4'], VARIABLETYPE_STRING, 'FB.DynDnyState', false, 5);
        $this->setIPSVariable(\FritzBox\DynDNS\Variables::IPv6State, 'IPv6 State', (string) $result['NewStatusIPv6'], VARIABLETYPE_STRING, 'FB.DynDnyState', false, 6);
        return true;
    }
}
