<?php

declare(strict_types=1);

namespace FritzBox;

require_once __DIR__ . '/FritzBoxConstants.php';

class GUID
{
    //Library
    public const CIRS = '{D0E8905A-F00C-EA84-D607-3D27000348D8}';
    //Modules
    public const FritzBoxIO = '{6FF9A05D-4E49-4371-23F1-7F58283FB1D9}';
    public const Configurator = '{32CF40DC-51DA-6C63-8BD7-55E82F64B9E7}';
    public const WANCommonInterface = '{FD564AAF-E00A-4CF8-A60D-7F60B4CDFC1B}';
    public const WANIPConnection = '{61C9DC95-5A0F-7B9A-4427-82EDB57AA1B9}';
    public const WLAN = '{B3D72623-556E-B6C6-25E0-B3DEFE41F031}';
    public const Hosts = '{66495783-EAEF-7A90-B13C-399045E4790B}';
    public const DHCPServer = '{BDD8382D-00EF-4D84-8B3E-795584ABEB12}';
    public const DeviceInfo = '{0E5BA3F0-4622-4C96-8D5F-F28DAB051C2F}';
    public const Time = '{4BD2D88F-E56B-9DF1-19A2-E6A688C5EA70}';
    public const WANPortMapping = '{9396D756-40EA-46C7-AA06-623B8DCB789B}';
    public const UPnPMediaServer = '{0F09E36F-BE54-D01D-5AF1-48FF618426AC}';
    public const WebDavStorage = '{EA069D88-287E-9F08-DB85-799EBA7A2678}';
    public const Storage = '{14588B6C-6F13-A3C1-0C79-88B6624E1D87}';
    public const MyFritz = '{D8AA1AB8-0FCE-56F9-FE36-E0D49878FB75}';
    public const DynDns = '{A3828BD1-B487-1860-2812-36E8DA0D358E}';
    public const FileShare = '{E9B51A32-A87F-8E47-34E8-594535C7D42A}';
    public const HostFilter = '{6E830782-BB60-4E2E-B6FD-8937BEAE2B46}';
    public const PowerLine = '{B1B43095-2B0A-4C85-A4DE-E962404201C7}';
    public const DVBC = '{DF0D838F-C5B4-43D4-A95B-CBCF2A05138E}';
    public const HomeautomationDevice = '{822E981D-9195-4AA7-821A-36BB1E63F993}';
    public const HomeautomationConfigurator = '{D636EA77-03A0-4359-9DFD-4CE035459023}';
    public const FirmwareInfo = '{802BCC3D-7144-4803-8886-0F5A08E2BF5D}';
    public const CallMonitor = '{5B5C7F75-E7FE-AE5C-6A51-8252688CBF4D}';
    public const WANDSLLink = '{061AEAF5-ADCD-AD9E-9BF9-AD40DF364EB6}';
    public const WANPhysicalInterface = '{C1A97F94-EE83-0553-5F42-FA242F406B1E}';
    public const Telephony = '{AD0B22A7-C71C-71DD-A40B-B70334C5AB3C}';
    public const AnsweringMachine = '{63B1641C-78C2-46D1-B994-1BD673CCC496}';
    public const DECT = '{4446C104-4F20-486A-AC47-096E4B1DBAA4}';
    public const DSLInterface = '{D42F6309-DE5D-4EA3-90E6-4B9AA1942D77}';
    public const MobileConnection = '{E9F671F4-64AE-4AA3-83FF-B654F85D3718}';
    public const WANFiber = '{1A5E713B-DE3D-402F-A2DA-A3EEB3ED9D11}';
    public const WANIPv6Firewall = '{156C748D-17A9-4648-A125-E74D3E7933DD}';
    public const ClientSocket = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';
    public const UtilControl = '{B69010EA-96D5-46DF-B885-24821B8C8DBD}';
    public const Store = '{F45B5D1F-56AE-4C61-9AB2-C87C63149EC3}';
    //DataFlow
    public const SendToFritzBoxIO = '{D62D4515-7689-D1DB-EE97-F555AD9433F0}';
    public const SendEventToChildren = '{CBD869A0-869B-3D4C-7EA8-D917D935E647}';
    public const NewHostListEvent = '{FE6C73CB-028B-F569-46AC-3C02FF1F8F2F}';
    public const RefreshHostListRequest = '{3C010D20-02A3-413A-9C5E-D0747D61BEF0}';
    public const CallMonitorEvent = '{FE5B2BCA-CA0F-25DC-8E79-BDFD242CB06E}';
}

class Store
{
    public const BundleId = 'de.nall.chan.cirs';
    public static $Opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Awesome-PHP\r\n"
        ]
    ];
}

/**
 * Maskiert Zugangsdaten und Schlüssel in Debug-Ausgaben.
 * Leere Werte bleiben sichtbar, damit erkennbar ist, ob ein Wert gesetzt ist.
 */
class DebugMask
{
    public const Mask = '***';

    /**
     * Namen von UPnP-Argumenten mit sensiblen Werten (z.B. NewPassword, NewKeyPassphrase, NewPreSharedKey, NewWEPKey0,
     * EncryptionSecret, NewToken, NewX_AVM-DE_UrlSID, NewIMSI). Quelle: SCPD-Beschreibungen mehrerer FritzBoxen.
     * Ausgenommen sind Längen- und Zeichenvorgaben wie NewMaxCharsPassword oder NewAllowedCharsIPSecPreSharedKey.
     */
    private const SensitiveName = '(?![\w-]*(?:AllowedChars|MaxChars|MinChars))[\w-]*(?:Password|PasswordHash|Passphrase|PreSharedKey|WEPKey\d*|PIN|Secret|Token|SessionID|UrlSID|IMSI)';

    /**
     * Aktionen, deren einziger Rückgabewert sensibel ist. Die IO liefert bei genau einem
     * Rückgabewert nur den Wert ohne Argumentnamen, daher wird hier am Aktionsnamen erkannt.
     */
    private const SensitiveScalarActions = [
        'X_AVM-DE_CreateUrlSID' // NewX_AVM-DE_UrlSID
    ];

    /** Session-ID der FritzBox als URL-Parameter (auch HTML-kodiert als &amp;sid=). */
    private const SessionId = '/([?&;]sid=)[^&\s<>"\']+/i';

    /**
     * Maskiert den Wert des Authorization-Headers (Basic und Digest).
     *
     * @param mixed $Headers HTTP-Header
     *
     * @return mixed
     */
    public static function Headers(mixed $Headers): mixed
    {
        if (!is_string($Headers)) {
            return $Headers;
        }
        return preg_replace('/^(Authorization:[ \t]*[\w-]+)[ \t][^\r\n]*/mi', '$1 ' . self::Mask, $Headers);
    }

    /**
     * Maskiert den Inhalt sensibler XML-Elemente (SOAP-Request, -Response, Events).
     *
     * @param mixed $Xml XML
     *
     * @return mixed
     */
    public static function Xml(mixed $Xml): mixed
    {
        if (!is_string($Xml)) {
            return $Xml;
        }
        $Xml = preg_replace(
            '/(<(?:[\w-]+:)?(' . self::SensitiveName . ')(?:\s[^>]*)?>)[^<]+(<\/(?:[\w-]+:)?\2>)/',
            '$1' . self::Mask . '$3',
            $Xml
        );
        return self::Url($Xml);
    }

    /**
     * Maskiert die Session-ID (sid) in URLs.
     *
     * @param mixed $Url URL oder Text mit URLs
     *
     * @return mixed
     */
    public static function Url(mixed $Url): mixed
    {
        if (!is_string($Url)) {
            return $Url;
        }
        return preg_replace(self::SessionId, '$1' . self::Mask, $Url);
    }

    /**
     * Maskiert das Ergebnis einer SOAP-Aktion. Bei genau einem Rückgabewert ist das Ergebnis
     * ein Skalar ohne Argumentnamen, dann wird anhand des Aktionsnamens maskiert.
     *
     * @param string $Action Name der SOAP-Aktion
     * @param mixed  $Result Ergebnis
     *
     * @return mixed
     */
    public static function Result(string $Action, mixed $Result): mixed
    {
        if (is_scalar($Result) && (string) $Result !== '' && in_array($Action, self::SensitiveScalarActions, true)) {
            return self::Mask;
        }
        return self::Values($Result);
    }

    /**
     * Maskiert sensible Werte in Arrays und Objekten anhand des Schlüssels, rekursiv.
     * Strings werden zusätzlich auf eine Session-ID in URLs geprüft.
     *
     * @param mixed $Data Daten
     *
     * @return mixed
     */
    public static function Values(mixed $Data): mixed
    {
        if (is_object($Data)) {
            $Data = clone $Data;
        }
        if (is_string($Data)) {
            return self::Url($Data);
        }
        if (!is_array($Data) && !($Data instanceof \stdClass)) {
            return $Data;
        }
        foreach ($Data as $Key => $Value) {
            if (is_string($Key) && preg_match('/^' . self::SensitiveName . '$/', $Key) && is_scalar($Value) && (string) $Value !== '') {
                $Value = self::Mask;
            } else {
                $Value = self::Values($Value);
            }
            if (is_array($Data)) {
                $Data[$Key] = $Value;
            } else {
                $Data->{$Key} = $Value;
            }
        }
        return $Data;
    }
}

class Services
{
    public static $Data = [
        'urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1'=> [GUID::WANCommonInterface=>0],
        'urn:schemas-upnp-org:service:WANIPConnection:1'         => [GUID::WANIPConnection=>0],
        'urn:schemas-upnp-org:service:WANIPConnection:2'         => [GUID::WANIPConnection=>1],
        'urn:dslforum-org:service:WLANConfiguration:1'           => [GUID::WLAN=>0], // WLAN
        'urn:dslforum-org:service:WLANConfiguration:2'           => [GUID::WLAN=>1], // WLAN
        'urn:dslforum-org:service:WLANConfiguration:3'           => [GUID::WLAN=>2], // WLAN
        'urn:dslforum-org:service:WLANConfiguration:4'           => [GUID::WLAN=>3], // WLAN
        'urn:dslforum-org:service:Hosts:1'                       => [GUID::Hosts=>0], // Hosts
        'urn:dslforum-org:service:LANHostConfigManagement:1'     => [GUID::DHCPServer=>0],
        'urn:dslforum-org:service:DeviceInfo:1'                  => [GUID::DeviceInfo=>0], // Geräteinformationen
        'urn:dslforum-org:service:Time:1'                        => [GUID::Time=>0], // Zeitserver
        'urn:dslforum-org:service:WANPPPConnection:1'            => [GUID::WANPortMapping=>0], // bei DSL
        'urn:dslforum-org:service:WANIPConnection:1'             => [GUID::WANPortMapping=>1], // bei Cabel
        'urn:dslforum-org:service:X_AVM-DE_UPnP:1'               => [GUID::UPnPMediaServer=>0],
        'urn:dslforum-org:service:X_AVM-DE_WebDAVClient:1'       => [GUID::WebDavStorage=>0],
        'urn:dslforum-org:service:X_AVM-DE_Storage:1'            => [GUID::Storage=>0],
        'urn:dslforum-org:service:X_AVM-DE_MyFritz:1'            => [GUID::MyFritz=>0],
        'urn:dslforum-org:service:X_AVM-DE_RemoteAccess:1'       => [GUID::DynDns=>0],
        'urn:dslforum-org:service:X_AVM-DE_Filelinks:1'          => [GUID::FileShare=>0],
        'urn:dslforum-org:service:X_AVM-DE_HostFilter:1'         => [GUID::HostFilter=>0], // Hostfilter
        'urn:dslforum-org:service:X_AVM-DE_Homeplug:1'           => [GUID::PowerLine=>0], // Powerline
        'urn:dslforum-org:service:X_AVM-DE_Media:1'              => [GUID::DVBC=>0], // DVB-C (nur Cable)
        'urn:dslforum-org:service:X_AVM-DE_Homeauto:1'           => [GUID::HomeautomationConfigurator=>0], // Homeautomation Configurator
        'urn:dslforum-org:service:UserInterface:1'               => [GUID::FirmwareInfo=>0], // Firmware
        'callmonitor'                                            => [GUID::CallMonitor=>0], // Anrufmonitor
        'urn:schemas-upnp-org:service:WANDSLLinkConfig:1'        => [GUID::WANDSLLink=>0],
        //'urn:dslforum-org:service:WANDSLLinkConfig:1'            => [], // unnötig? DSL Daten?
        'urn:dslforum-org:service:WANDSLInterfaceConfig:1'       => [GUID::DSLInterface=>0], // nur DSL
        'urn:dslforum-org:service:WANCommonInterfaceConfig:1'    => [GUID::WANPhysicalInterface=>0],
        //todo (jetzt)
        'urn:dslforum-org:service:X_AVM-DE_OnTel:1'              => [GUID::Telephony =>0], // todo Telefonie
        'urn:dslforum-org:service:X_VoIP:1'                      => [], // in OnTel enthalten ?! // todo
        'urn:dslforum-org:service:X_AVM-DE_Dect:1'               => [GUID::DECT=>0], // DECT-Geräte
        'urn:dslforum-org:service:X_AVM-DE_TAM:1'                => [GUID::AnsweringMachine=>0], // Anrufbeantworter
        'urn:dslforum-org:service:X_AVM-DE_WANMobileConnection:1'=> [GUID::MobileConnection=>0], // Mobilfunk
        'urn:dslforum-org:service:Layer3Forwarding:1'            => [], // im IO Enthalten
        'urn:schemas-upnp-org:service:WANIPv6FirewallControl:1'  => [GUID::WANIPv6Firewall=>0], // nur IGD2
        'urn:dslforum-org:service:X_AVM-DE_WANFiber:1'           => [GUID::WANFiber=>0], // nur Glasfaser-Boxen
        // Ohne eigene Module
        //'urn:dslforum-org:service:LANEthernetInterfaceConfig:1'  => [], // nur eine nicht zuordenbare Schnittstelle, Zähler passen nicht zum Gesamtverkehr
        //'urn:dslforum-org:service:LANConfigSecurity:1'           => [], // im IO Enthalten -> Fallback für User Anmeldung
        //'urn:dslforum-org:service:DeviceConfig:1'                => [], // im IO Enthalten -> ausbauen?
        //'urn:dslforum-org:service:WANEthernetLinkConfig:1'       => [], // unnötig?
        //'urn:dslforum-org:service:X_AVM-DE_Speedtest:1'          => [], // unnötig
        //'urn:dslforum-org:service:X_AVM-DE_Auth:1'               => [], // unnötig
        //'urn:dslforum-org:service:ManagementServer:1'            => [], // unnötig
        //'urn:dslforum-org:service:X_AVM-DE_AppSetup:1'           => [], // unnötig
        //'urn:schemas-any-com:service:Any:1'                      => [] // Dummy
    ];
}
