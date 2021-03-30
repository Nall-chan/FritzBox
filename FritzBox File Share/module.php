<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
    class FritzBoxFileShare extends FritzBoxModulBase
    {
        protected static $ControlUrlArray = [
            '/upnp/control/x_filelinks'
        ];
        protected static $EventSubURLArray = [];
        protected static $ServiceTypeArray = [
            'urn:dslforum-org:service:X_AVM-DE_Filelinks:1'
        ];

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyInteger('Index', 0);
            //TODO
            $this->RegisterPropertyBoolean('SharesAsTable', false);
            $this->RegisterPropertyInteger('RefreshInterval', 3600);
    
            $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
        }

        public function Destroy()
        {
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            $this->SetTimerInterval('RefreshState', 0);
            parent::ApplyChanges();
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
            $this->RefreshHTMLTable();
            $this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval')*1000);
        }

        public function RequestAction($Ident, $Value)
        {
            if (parent::RequestAction($Ident, $Value)) {
                return true;
            }
            switch ($Ident) {
                case 'RefreshState':
                    return $this->RefreshHTMLTable();

            }
            trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
            return false;
        }

        public function GetShareList()
        {
            if ($this->ParentID == 0) {
                return false;
            }
                
            $File = $this->GetFilelinkListPath();
            if ($File === false) {
                return false;
            }
            //TODO
            $Data=[];
            /*
            $Url = IPS_GetProperty($this->ParentID, 'Host'). $File;
            $XMLData = @Sys_GetURLContentEx($Url, ['Timeout'=>3000]);
            if ($XMLData === false) {
                $this->SendDebug('XML not found', $Url, 0);
                return false;
            }
            $xml = new simpleXMLElement($XMLData);
            if ($xml === false) {
                $this->SendDebug('XML decode error', $XMLData, 0);
            }
            $Data =[];
            foreach ($xml->Item as $Index => $Item) {
                $Item =(array)$Item;
                unset($Item['Username']);
                $Data[]=$Item;
            }*/
            return $Data;
        }
        public function RefreshHTMLTable()
        {
            $Table = $this->ReadPropertyBoolean('SharesAsTable');
            if (!$Table) {
                return false;
            }
            $Data = $this->GetShareList();
            return $this->CreateHostHTMLTable($Data);
        }
        private function CreateHostHTMLTable(array $TableData)
        {
            //todo
        }
        public function GetNumberOfFilelinkEntries()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }

            return $result;
        }
        public function NewFilelinkEntry(
            string $Path,
            int $AccessCountLimit,
            int $Expire
        ) {
            $result = $this->Send(__FUNCTION__, [
                'NewPath'               => $Path,
                'NewAccessCountLimit'   => $AccessCountLimit,
                'NewExpire'             => $Expire
            ]);
            return $result;
        }
        public function SetFilelinkEntry(
            string $ID,
            int $AccessCountLimit,
            int $Expire
        ) {
            $result = $this->Send(__FUNCTION__, [
                'NewID'                    => $ID,
                'NewAccessCountLimit'   => $AccessCountLimit,
                'NewExpire'             => $Expire
            ]);
            return true;
        }
        public function GetGenericFilelinkEntry(int $Index)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewIndex'=> $Index
            ]);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetSpecificFilelinkEntry(string $Id)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewID'=> $Id
            ]);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function DeleteFilelinkEntry(string $Id)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewID'=> $Id
            ]);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetFilelinkListPath()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return $result;
        }
    }
