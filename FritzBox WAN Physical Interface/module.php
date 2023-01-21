<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

    class FritzBoxWANPhysicalInterface extends FritzBoxModulBase
    {
        protected static $ControlUrlArray = [
            '/upnp/control/wancommonifconfig1'
        ];
        protected static $EventSubURLArray = [
            //'/upnp/control/wancommonifconfig1'
        ];
        protected static $ServiceTypeArray = [
            'urn:dslforum-org:service:WANCommonInterfaceConfig:1'
        ];
        protected static $DefaultIndex = 0;
        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyInteger('RefreshInterval', 60);
            $this->RegisterTimer('RefreshLinkProperties', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshLinkProperties",true);');
        }

        public function Destroy()
        {
            if (!IPS_InstanceExists($this->InstanceID)) {
                $this->UnregisterProfile('FB.LinkState');
                $this->UnregisterProfile('FB.kBit');
            }
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            $this->SetTimerInterval('RefreshLinkProperties', 0);
            $this->RegisterProfileIntegerEx(
                'FB.LinkState',
                '',
                '',
                '',
                [
                    [0, $this->Translate('Up'), '', 0x00ff00],
                    [1, $this->Translate('Down'), '', 0xff0000],
                    [2, $this->Translate('Initializing'), '', 0xff00ff],
                    [3, $this->Translate('Unavailable'), '', 0xff0000],
                ]
            );
            $this->RegisterProfileInteger('FB.kBit', '', '', ' kBit/s', 0, 0, 0);
            parent::ApplyChanges();
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
            $this->UpdateCommonLinkProperties();
            $this->SetTimerInterval('RefreshLinkProperties', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        }
        public function RequestAction($Ident, $Value)
        {
            if (parent::RequestAction($Ident, $Value)) {
                return true;
            }
            switch ($Ident) {
                case 'RefreshLinkProperties':
                    return $this->UpdateCommonLinkProperties();
            }
            trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
            return false;
        }
        public function GetCommonLinkProperties()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetTotalBytesSent()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetTotalBytesReceived()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetTotalPacketsSent()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetTotalPacketsReceived()
        {
            return $this->Send(__FUNCTION__);
        }

        private function UpdateCommonLinkProperties()
        {
            $result = $this->GetCommonLinkProperties();
            if ($result === false) {
                return false;
            }

            $this->setIPSVariable('WANAccessType', 'WAN Access type', (string) $result['NewWANAccessType'], VARIABLETYPE_STRING);
            $this->setIPSVariable('PhysicalLinkStatus', 'Physical Link Status', $this->LinkStateToInt((string) $result['NewPhysicalLinkStatus']), VARIABLETYPE_INTEGER, 'FB.LinkState');
            $Downstream = (int) ((int) $result['NewLayer1DownstreamMaxBitRate'] / 1000);
            $Upstream = (int) ((int) $result['NewLayer1UpstreamMaxBitRate'] / 1000);
            $this->setIPSVariable('UpstreamMaxBitRate', 'Upstream Max kBitrate', $Upstream, VARIABLETYPE_INTEGER, 'FB.kBit');
            $this->setIPSVariable('DownstreamMaxBitRate', 'Downstream Max kBitrate', $Downstream, VARIABLETYPE_INTEGER, 'FB.kBit');
            return true;
        }
    }
