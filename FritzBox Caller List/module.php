<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxCallerList extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_contact'
    ];
    protected static $EventSubURLArray = [

    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_OnTel:1'
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        //Status laden
    }
    public function GetInfo()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function SetEnable(bool $Enable)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewEnable'=> $Enable
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetInfoByIndex(int $Index)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewIndex'=> $Index
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function SetEnableByIndex(int $Index, bool $Enable)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewIndex' => $Index,
            'NewEnable'=> $Enable
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetNumberOfEntries()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetCallList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetPhonebookList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetPhonebook(int $PhonebookID)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewPhonebookID'=> $PhonebookID
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetPhonebookEntry(int $PhonebookID, int $PhonebookEntryID)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewPhonebookID'     => $PhonebookID,
            'NewPhonebookEntryID'=> $PhonebookEntryID
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetPhonebookEntryUID(int $PhonebookID, int $PhonebookEntryUniqueID)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewPhonebookID'           => $PhonebookID,
            'NewPhonebookEntryUniqueID'=> $PhonebookEntryUniqueID
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetDECTHandsetList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetDECTHandsetInfo(int $DectID)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewDectID'=> $DectID
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetNumberOfDeflections()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetDeflections()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetDeflection(int $Index)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewDeflectionId'=> $Index
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function SetDeflectionEnable(int $DeflectionId, bool $Enable)
    {
        $result = $this->Send(__FUNCTION__,
        [
            'NewDeflectionId' => $DeflectionId,
            'NewEnable'       => $Enable
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
}