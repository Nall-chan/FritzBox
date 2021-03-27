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
        if (count(static::$EventSubURLArray) > 0) {
            $this->RegisterTimer('RenewSubscription', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"Subscribe",true);');
        }
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        if (count(static::$EventSubURLArray) > 0) {
            $this->SetTimerInterval('RenewSubscription', 0);
        }
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
            if (property_exists($this, 'SecondEventGUID')) {
                $Filter.= '.*|.*"DataID":"'.preg_quote(static::$SecondEventGUID).'"';
            }
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
            $this->Subscribe();
            return true;
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
        if ($data['DataID'] == '{CBD869A0-869B-3D4C-7EA8-D917D935E647}') {
            unset($data['DataID']);
            if (!array_key_exists('EventData', $data)) {
                return false;
            }
            $this->GotEvent = true;
            $this->SendDebug('Event', $data['EventData'], 0);
            $this->DecodeEvent($data['EventData']);
            return true;
        }
        return null;
    }
    protected function DecodeEvent($Event)
    {
        foreach ($Event as $Ident => $EventData) {
            $vid = @$this->GetIDForIdent($Ident);
            if ($vid > 0) {
                if (IPS_GetVariable($vid)['VariableType']==VARIABLETYPE_BOOLEAN) {
                    $EventData = (string)$EventData !== '0';
                }
                $this->SetValue($Ident, $EventData);
            }
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
            $this->SetTimerInterval('RenewSubscription', 60000);
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
    protected function LoadAndGetData(string $Uri)
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $this->SendDebug('Uri', $Uri, 0);
        $Ret = $this->SendDataToParent(json_encode(
                [
                    'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                    'Function'   => 'GETFILE',
                    'Uri'=> $Uri
                ]
            ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
    protected function LoadAndSaveFile(string $Uri, string $Filename)
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $this->SendDebug('Uri', $Uri, 0);
        $this->SendDebug('Filename', $Filename, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'LOADFILE',
                'Uri'=> $Uri,
                'Filename'=> $Filename
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
    protected function GetFile(string $Filename)
    {
        if ($this->ParentID == 0) {
            return false;
        }
        return @file_get_contents(IPS_GetKernelDir() . 'FritzBoxTemp/' . $this->ParentID . '/'.$Filename);
    }

    protected function Send($Function, array $Parameter = [])
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        if ($this->GetStatus() == IS_INACTIVE) {
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
            if (property_exists($Result, 'detail')) {
                $Details = $Result->detail->{$Result->faultstring};
                $Detail = $Result->faultstring . '('.$Details->errorCode.')';
                $this->SendDebug($Detail, $Details->errorDescription, 0);
                trigger_error($Detail."\r\n".$Details->errorDescription, E_USER_WARNING);
                return false;
            }
            $this->SendDebug('SoapFault', $Result->getMessage(), 0);
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

    protected function ConvertRuntime(int $Time)
    {
        $strtime = '';
        $sec = intval(gmdate('s', $Time));
        if ($sec != 0) {
            $strtime = $sec . ' Sek';
        }
        if ($Time >= 60) {
            $strtime = intval(gmdate('i', $Time)) . ' Min ' . $strtime;
        }
        if ($Time >= 3600) {
            $strtime = gmdate('G', $Time) . ' Std ' . $strtime;
        }
        if ($Time >= 3600 * 24) {
            $strtime = gmdate('z', $Time) . ' Tg ' . $strtime;
        }
        return $strtime;
    }
    protected function ConvertIdent(string $Ident)
    {
        //return str_replace([':','.','[',']'], ['','','',''], $Ident);
        return preg_replace('/[^a-zA-Z0-9_]/', '', $Ident);
    }
    /**
     * Erstellt eine Untervariable in IPS.
     *
     * @param int    $ParentID IPS-ID der übergeordneten Variable.
     * @param string $Ident    IDENT der neuen Statusvariable.
     * @param string $Name     Name der neuen Statusvariable.
     * @param int    $Type     Der zu erstellende Typ von Variable.
     * @param string $Profile  Das dazugehörige Variabelprofil.
     * @param int    $Position Position der Variable.
     *
     * @throws Exception Wenn Variable nicht erstellt werden konnte.
     *
     * @return int IPS-ID der neuen Variable.
     */
    protected function RegisterSubVariable($ParentID, $Ident, $Name, $Type, $Profile = '', $Position = 0)
    {
        if ($Profile != '') {
            if (IPS_VariableProfileExists('~' . $Profile)) {
                $Profile = '~' . $Profile;
            }
            if (!IPS_VariableProfileExists($Profile)) {
                throw new Exception('Profile with name ' . $Profile . ' does not exist', E_USER_NOTICE);
            }
        }

        $vid = @IPS_GetObjectIDByIdent($Ident, $ParentID);

        if ($vid === false) {
            $vid = 0;
        }

        if ($vid > 0) {
            if (!IPS_VariableExists($vid)) {
                throw new Exception('Ident with name ' . $Ident . ' is used for wrong object type', E_USER_NOTICE); //bail out
            }
            if (IPS_GetVariable($vid)['VariableType'] != $Type) {
                IPS_DeleteVariable($vid);
                $vid = 0;
            }
        }

        if ($vid == 0) {
            $vid = IPS_CreateVariable($Type);

            IPS_SetParent($vid, $ParentID);
            IPS_SetIdent($vid, $Ident);
            IPS_SetName($vid, $this->Translate($Name));
            IPS_SetPosition($vid, $Position);
            //IPS_SetReadOnly($vid, true);
        }

        IPS_SetVariableCustomProfile($vid, $Profile);
        if (!in_array($vid, $this->GetReferenceList())) {
            $this->RegisterReference($vid);
        }
        return $vid;
    }
}
