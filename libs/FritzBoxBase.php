<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');

/**
 * @property string $SID
 * @property bool $GotEvent
 */
class FritzBoxModulBase extends IPSModule
{
    use \FritzBoxModulBase\BufferHelper;
    use \FritzBoxModulBase\DebugHelper;
    use \FritzBoxModulBase\VariableProfileHelper;
    use \FritzBoxModulBase\InstanceStatus {
        \FritzBoxModulBase\InstanceStatus::MessageSink as IOMessageSink; // MessageSink gibt es sowohl hier in der Klasse, als auch im Trait InstanceStatus. Hier wird für die Methode im Trait ein Alias benannt.
        \FritzBoxModulBase\InstanceStatus::RegisterParent as IORegisterParent;
        \FritzBoxModulBase\InstanceStatus::RequestAction as IORequestAction;
    }
    protected static $ControlUrlArray = [];
    protected static $ServiceTypeArray = [];
    protected static $EventSubURLArray = [];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->SID = '';
        $this->ConnectParent('{6FF9A05D-4E49-4371-23F1-7F58283FB1D9}');
        $this->RegisterTimer('RenewSubscription', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"Subscribe",true);');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        $this->SetTimerInterval('RenewSubscription', 0);
        parent::ApplyChanges();
        $this->SID = '';
        $this->GotEvent = false;
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RegisterParent();
        $Index = $this->ReadPropertyInteger('Index');
        if (count(static::$EventSubURLArray) == 0) {
            $Index = -1;
        }
        if ($Index > -1) {
            $Filter = preg_quote(substr(json_encode(static::$EventSubURLArray[$Index]), 1, -1));
            $this->SetReceiveDataFilter('.*"EventSubURL":"' . $Filter . '".*');
            $this->SendDebug('Filter', '.*"EventSubURL":"' . $Filter . '".*', 0);
        } else {
            $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        }
        $this->Subscribe();
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        if ($Ident == 'Subscribe') {
            return $this->Subscribe();
        }
        return false;
    }
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        unset($data['DataID']);
        if (!array_key_exists('EventData', $data)) {
            return false;
        }
        $this->GotEvent = true;
        $this->SendDebug('Event', $data['EventData'], 0);
        $this->DecodeEvent($data['EventData']);
    }
    protected function DecodeEvent($Event)
    {
        foreach ($Event as $Ident => $EventData) {
            $vid = @$this->GetIDForIdent($Ident);
            if ($vid > 0) {
                $this->SetValue($Ident, $EventData);
            }
            return true;
        }
    }
    protected function KernelReady()
    {
        $this->RegisterParent();
    }

    protected function RegisterParent()
    {
        $this->IORegisterParent();
    }
    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        if ($State == IS_ACTIVE) {
            $this->ApplyChanges();
            //$this->Subscribe();
        }
    }

    protected function Subscribe(): bool
    {
        $Result = $this->DoSubscribe();
        if ($Result) {
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_EBASE + 1);
        }
        return $Result;
    }
    protected function DoSubscribe(): bool
    {
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index < 0) {
            return true;
        }
        if (count(static::$EventSubURLArray) == 0) {
            return true;
        }
        if (!$this->HasActiveParent()) {
            $this->SID = '';
            return true;
        }
        $this->GotEvent = false;
        $this->SendDebug('Subscribe', $this->SID, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'SUBSCRIBE',
                'EventSubURL'=> static::$EventSubURLArray[$Index],
                'SID'        => $this->SID
            ]
        ));
        if ($Ret === false) {
            $this->SID = '';
            return true;
        }
        $Result = unserialize($Ret);
        if ($Result === false) {
            $this->SID = '';

            $this->SendDebug('Error on subscribe', static::$EventSubURLArray[$Index], 0);
            trigger_error('Error on subscribe ' . static::$EventSubURLArray[$Index], E_USER_WARNING);
            return false;
        }
        $this->SendDebug('Result', $Result, 0);

        if (!$this->WaitForEvent()) {
            $this->SID = '';
            $this->SetTimerInterval('RenewSubscription', 0);
            return false;
        }
        $this->SID = $Result['SID'];
        $this->SetTimerInterval('RenewSubscription', ($Result['TIMEOUT'] - 5) * 1000);
        return true;
    }
    protected function WaitForEvent()
    {
        for ($i = 0; $i < 1000; $i++) {
            if ($this->GotEvent) {
                $this->GotEvent = false;
                return true;
            } else {
                IPS_Sleep(5);
            }
        }
        return false;
    }
    /*     protected function Send($Function, array $Parameter = [], string $ServiceType = '', string $ControlUrl = '')
        {
            if (!$this->HasActiveParent()) {
                return false;
            }
            $Ret = $this->SendDataToParent(json_encode(
                    [
                        'DataID'    => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                        'ServiceTyp'=> ($ServiceType != '') ? $ServiceType : static::$ServiceType,
                        'ControlUrl'=> ($ControlUrl != '') ? $ControlUrl : static::$ControlUrl,
                        'Function'  => $Function,
                        'Parameter' => $Parameter
                    ]
                ));
            if ($Ret === false) {
                return false;
            }
            $Result = unserialize($Ret);
            if (is_a($Result, 'SoapFault')) {
                trigger_error($Result->getMessage(), E_USER_WARNING);
                return false;
            }
            $this->SendDebug('Result', $Result, 0);
            return $Result;
        } */
    protected function Send($Function, array $Parameter = [])
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index < 0) {
            return false;
        }
        $this->SendDebug('Function', $Function, 0);
        $this->SendDebug('Params', $Parameter, 0);
        $Ret = $this->SendDataToParent(json_encode(
                [
                    'DataID'    => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                    'ServiceTyp'=> static::$ServiceTypeArray[$Index],
                    'ControlUrl'=> static::$ControlUrlArray[$Index],
                    'Function'  => $Function,
                    'Parameter' => $Parameter
                ]
            ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        if (is_a($Result, 'SoapFault')) {
            trigger_error($Result->getMessage(), E_USER_WARNING);
            return false;
        }
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
    protected function setIPSVariable(string $ident, string $name, $value, $type, string $profile = '', bool $action = false, int $pos = 0)
    {
        $this->MaintainVariable($ident, $this->Translate($name), $type, $profile, $pos, true);
        if ($action) {
            $this->EnableAction($ident);
        }
        $this->SetValue($ident, $value);
    }
    protected function ConvertRuntime($Time)
    {
        date_default_timezone_set('UTC');
        $strtime = '';
        $sec = intval(date('s', $Time));
        if ($sec != 0) {
            $strtime = $sec . ' Sek';
        }
        if ($Time > 60) {
            $strtime = intval(date('i', $Time)) . ' Min ' . $strtime;
        }
        if ($Time > 3600) {
            $strtime = date('G', $Time) . ' Std ' . $strtime;
        }
        if ($Time > 3600 * 24) {
            $strtime = date('z', $Time) . ' Tg ' . $strtime;
        }
        return $strtime;
    }
}