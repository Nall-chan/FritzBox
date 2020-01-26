<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');

/**
 * @property string $SID
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
    protected static $ControlUrl = '';
    protected static $ServiceType = '';
    protected static $EventSubURL = '';

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
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RegisterParent();
        if (static::$EventSubURL == '') {
            $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        } else {
            $Filter = preg_quote(substr(json_encode(static::$EventSubURL), 1, -1));
            $this->SetReceiveDataFilter('.*"EventSubURL":"' . $Filter . '".*');
            $this->SendDebug('Filter', '.*"EventSubURL":"' . $Filter . '".*', 0);
            $this->Subscribe();
        }
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
        $this->SendDebug('Event', $data, 0);
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
        }
    }

    protected function Subscribe(string $EventSubURL = ''): bool
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        if ($EventSubURL == '') {
            $EventSubURL = static::$EventSubURL;
        }
        $this->SendDebug('Subscribe', $this->SID, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'SUBSCRIBE',
                'EventSubURL'=> $EventSubURL,
                'SID'        => $this->SID
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        if ($Result === false) {
            $this->SendDebug('Error on subscribe', $EventSubURL, 0);
            trigger_error('Error on subscribe ' . $EventSubURL, E_USER_WARNING);
            return false;
        }
        $this->SendDebug('Result', $Result, 0);
        $this->SID = $Result['SID'];
        $this->SetTimerInterval('RenewSubscription', ($Result['TIMEOUT'] - 5) * 1000);
        return true;
    }

    protected function Send($Function, array $Parameter = [], string $ServiceType = '', string $ControlUrl = '')
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
    }
}