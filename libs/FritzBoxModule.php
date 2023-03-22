<?php

declare(strict_types=1);

namespace FritzBox;

class Services
{
    public static $Data = [
        'urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1'=> ['{FD564AAF-E00A-4CF8-A60D-7F60B4CDFC1B}'=>1],
        'urn:schemas-upnp-org:service:WANIPConnection:1'         => ['{61C9DC95-5A0F-7B9A-4427-82EDB57AA1B9}'=>0],
        'urn:schemas-upnp-org:service:WANIPConnection:2'         => ['{61C9DC95-5A0F-7B9A-4427-82EDB57AA1B9}'=>1],
        'urn:dslforum-org:service:WLANConfiguration:1'           => ['{B3D72623-556E-B6C6-25E0-B3DEFE41F031}'=>0], // WLAN
        'urn:dslforum-org:service:WLANConfiguration:2'           => ['{B3D72623-556E-B6C6-25E0-B3DEFE41F031}'=>1], // WLAN
        'urn:dslforum-org:service:WLANConfiguration:3'           => ['{B3D72623-556E-B6C6-25E0-B3DEFE41F031}'=>2], // WLAN
        'urn:dslforum-org:service:Hosts:1'                       => ['{66495783-EAEF-7A90-B13C-399045E4790B}'=>0], // Hosts
        'urn:dslforum-org:service:LANHostConfigManagement:1'     => ['{BDD8382D-00EF-4D84-8B3E-795584ABEB12}'=>0],
        'urn:dslforum-org:service:DeviceInfo:1'                  => ['{0E5BA3F0-4622-4C96-8D5F-F28DAB051C2F}'=>0], // Geräteinformationen
        'urn:dslforum-org:service:Time:1'                        => ['{4BD2D88F-E56B-9DF1-19A2-E6A688C5EA70}'=>0], // Zeitserver
        'urn:dslforum-org:service:WANIPConnection:1'             => ['{9396D756-40EA-46C7-AA06-623B8DCB789B}'=>1], // (nur?) bei Cabel
        'urn:dslforum-org:service:X_AVM-DE_UPnP:1'               => ['{0F09E36F-BE54-D01D-5AF1-48FF618426AC}'=>0],
        'urn:dslforum-org:service:X_AVM-DE_WebDAVClient:1'       => ['{EA069D88-287E-9F08-DB85-799EBA7A2678}'=>0],
        'urn:dslforum-org:service:X_AVM-DE_Storage:1'            => ['{14588B6C-6F13-A3C1-0C79-88B6624E1D87}'=>0],
        'urn:dslforum-org:service:X_AVM-DE_MyFritz:1'            => ['{D8AA1AB8-0FCE-56F9-FE36-E0D49878FB75}'=>0],
        'urn:dslforum-org:service:X_AVM-DE_RemoteAccess:1'       => ['{A3828BD1-B487-1860-2812-36E8DA0D358E}'=>0],
        'urn:dslforum-org:service:X_AVM-DE_Filelinks:1'          => ['{E9B51A32-A87F-8E47-34E8-594535C7D42A}'=>0],
        'urn:dslforum-org:service:X_AVM-DE_HostFilter:1'         => ['{6E830782-BB60-4E2E-B6FD-8937BEAE2B46}'=>0], // Hostfilter
        'urn:dslforum-org:service:X_AVM-DE_Homeplug:1'           => ['{B1B43095-2B0A-4C85-A4DE-E962404201C7}'=>0], // Powerline
        'urn:dslforum-org:service:X_AVM-DE_Media:1'              => ['{DF0D838F-C5B4-43D4-A95B-CBCF2A05138E}'=>0], // DVB-C (nur Cable)
        'urn:dslforum-org:service:X_AVM-DE_Homeauto:1'           => ['{D636EA77-03A0-4359-9DFD-4CE035459023}'=>0], // Homeautomation Configurator
        'urn:dslforum-org:service:UserInterface:1'               => ['{802BCC3D-7144-4803-8886-0F5A08E2BF5D}'=>0], // Firmware
        'callmonitor'                                            => ['{5B5C7F75-E7FE-AE5C-6A51-8252688CBF4D}'=>0], // Anrufmonitor
        //todo (später)
        'urn:schemas-upnp-org:service:WANDSLLinkConfig:1'        => ['{061AEAF5-ADCD-AD9E-9BF9-AD40DF364EB6}'=>0],
        //'urn:dslforum-org:service:WANDSLInterfaceConfig:1'       => [], // anstatt     WANDSLLinkConfig
        'urn:dslforum-org:service:WANPPPConnection:1'            => ['{9396D756-40EA-46C7-AA06-623B8DCB789B}'=>0], // bei DSL (prüfen im Feldtest)
        //todo (jetzt)
        'urn:dslforum-org:service:WANCommonInterfaceConfig:1'    => ['{C1A97F94-EE83-0553-5F42-FA242F406B1E}'=>0], // todo
        'urn:dslforum-org:service:X_AVM-DE_OnTel:1'              => ['{AD0B22A7-C71C-71DD-A40B-B70334C5AB3C}'=>0], // todo Telefonie
        'urn:dslforum-org:service:X_VoIP:1'                      => [], // in OnTel enthalten ?! // todo
        'urn:dslforum-org:service:X_AVM-DE_Dect:1'               => [],
        'urn:dslforum-org:service:X_AVM-DE_TAM:1'                => [],
        'urn:dslforum-org:service:Layer3Forwarding:1'            => [],
        'urn:schemas-upnp-org:service:WANIPv6FirewallControl:1'  => [],
        // Ohne eigene Module
        //'urn:dslforum-org:service:LANConfigSecurity:1'           => [], // im IO Enthalten -> Fallback für User Anmeldung
        //'urn:dslforum-org:service:DeviceConfig:1'                => [], // im IO Enthalten -> ausbauen?
        //'urn:dslforum-org:service:LANEthernetInterfaceConfig:1'  => [], // Statistik, unnötig?
        //'urn:dslforum-org:service:WANEthernetLinkConfig:1'       => [], // unnötig?
        //'urn:dslforum-org:service:WANDSLLinkConfig:1'            => [], // unnötig? DSL Daten?
        //'urn:dslforum-org:service:X_AVM-DE_Speedtest:1'          => [], // unnötig
        //'urn:dslforum-org:service:X_AVM-DE_Auth:1'               => [], // unnötig
        //'urn:dslforum-org:service:ManagementServer:1'            => [], // unnötig
        //'urn:dslforum-org:service:X_AVM-DE_AppSetup:1'           => [], // unnötig
        //'urn:schemas-any-com:service:Any:1'                      => [] // Dummy
    ];
}
