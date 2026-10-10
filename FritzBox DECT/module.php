<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * DECT-Geräte (Mobilteile) der FritzBox.
 * Je Gerät eine Variable (angemeldet/aktiv) mit Untervariablen für Modell und verfügbares Update.
 */
class FritzBoxDECT extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_dect'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Dect:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 300);
        $this->RegisterPropertyBoolean(\FritzBox\DECT\Property::RenameDeviceVariables, false);
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
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Liefert die Anzahl der DECT-Geräte.
     *
     * @return int|false
     */
    public function GetNumberOfDectEntries()
    {
        $Result = $this->Send(__FUNCTION__);
        if ($Result === false) {
            return false;
        }
        return (int) $Result;
    }

    /**
     * Liefert die Daten eines DECT-Geräts anhand seiner Position in der Liste.
     *
     * @param int $Index Position (0 bis Anzahl - 1)
     *
     * @return array|false
     */
    public function GetGenericDectEntry(int $Index)
    {
        if ($Index < 0) {
            $this->ReportError($this->Translate('Invalid index.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewIndex' => $Index
        ]);
    }

    /**
     * Liefert die Daten eines DECT-Geräts anhand seiner ID.
     *
     * @param string $ID ID des Geräts (NewID aus FB_GetGenericDectEntry)
     *
     * @return array|false
     */
    public function GetSpecificDectEntry(string $ID)
    {
        if ($ID === '') {
            $this->ReportError($this->Translate('Invalid device ID.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewID' => $ID
        ]);
    }

    /**
     * Startet das Firmware-Update eines DECT-Geräts.
     *
     * @param string $ID ID des Geräts (NewID aus FB_GetGenericDectEntry)
     *
     * @return bool
     */
    public function DectDoUpdate(string $ID)
    {
        if ($ID === '') {
            $this->ReportError($this->Translate('Invalid device ID.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewID' => $ID
        ]) !== false;
    }

    /**
     * Liefert den Pfad zur XML-Liste aller DECT-Geräte.
     *
     * @return string|false
     */
    public function GetDectListPath()
    {
        return $this->Send(__FUNCTION__);
    }

    private function UpdateInfo()
    {
        $NumberOfEntries = $this->GetNumberOfDectEntries();
        if ($NumberOfEntries === false) {
            return false;
        }
        $ReturnValue = true;
        $Rename = $this->ReadPropertyBoolean(\FritzBox\DECT\Property::RenameDeviceVariables);
        for ($i = 0; $i < $NumberOfEntries; $i++) {
            $Result = $this->GetGenericDectEntry($i);
            if ($Result === false) {
                $ReturnValue = false;
                continue;
            }
            $Ident = 'DECT' . $this->ConvertIdent((string) $Result['NewID']);
            $Name = (string) $Result['NewName'];
            $this->setIPSVariable($Ident, $Name, (int) $Result['NewActive'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', false);
            $VarId = $this->GetIDForIdent($Ident);
            if ($Rename && ($Name != '') && (IPS_GetName($VarId) != $Name)) {
                IPS_SetName($VarId, $Name);
            }
            $ModelId = $this->RegisterSubVariable($VarId, \FritzBox\DECT\Variables::Model, 'Model', VARIABLETYPE_STRING);
            SetValueString($ModelId, (string) $Result['NewModel']);
            $UpdateAvailableId = $this->RegisterSubVariable($VarId, \FritzBox\DECT\Variables::UpdateAvailable, 'Update available', VARIABLETYPE_BOOLEAN);
            SetValueBoolean($UpdateAvailableId, (int) $Result['NewUpdateAvailable'] > 0);
        }
        return $ReturnValue;
    }
}
