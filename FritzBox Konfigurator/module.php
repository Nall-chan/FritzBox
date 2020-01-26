<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

    class FritzBoxKonfigurator extends IPSModule
    {
		use \FritzBoxModulBase\DebugHelper;
        public function Create()
        {
            //Never delete this line!
			parent::Create();
			$this->ConnectParent('{6FF9A05D-4E49-4371-23F1-7F58283FB1D9}');
 
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
			$this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');

		}
		// Bei GetConfigurationForm
		public function GetConfigurationForm()
		{
			$Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
			$Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            if (($Splitter == 0) or !$this->HasActiveParent()) {
				//Parent inactive ausgeben.
                //$Form[];
            }
			$Ret = $this->SendDataToParent(json_encode(
				[
					'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
					'Function'   => 'SCPD'
				]
			));
			$SCPDhasEvent = unserialize($Ret);
			return json_encode($Form);
 
		}
		// Die drei XMLs holen aus dem IO
		// Als Tree darstellen, mit Device und Co&
		// Filter für bestimmte Instanzen, wie 
		// Nur wenn ConnectionType DSL ist DSL anbieten
		//  Nur wenn Telefon vorhanden, den Anrufmonitor anbieten
		// StandardDevices für serviceType
		// diese Instanzen habe static $Statevars für Profile, Funktionen, VarTyp etc...
		// Speziallinstanzen für Host, WLANs und Anrufliste
    }