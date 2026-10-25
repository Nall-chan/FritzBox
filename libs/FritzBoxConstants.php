<?php

declare(strict_types=1);

/**
 * Konstanten für Idents, Property-, Attribut- und Timernamen der FritzBox Module.
 *
 * Die Werte entsprechen den bisher verwendeten Strings und dürfen nicht
 * geändert werden, da sie in bestehenden Instanzen gespeichert sind.
 */

namespace FritzBox {
    /**
     * Funktionen, welche Instanzen per ForwardData an die FritzBox IO senden
     * (Feld 'Function'). Unbekannte Werte führt die IO als SOAP-Action aus.
     */
    class IOFunction
    {
        public const Scpd = 'SCPD';
        public const Subscribe = 'SUBSCRIBE';
        public const Unsubscribe = 'UNSUBSCRIBE';
        public const IsPPP = 'ISPPP';
        public const HasIGD2 = 'HasIGD2';
        public const HasTel = 'HasTel';
        public const CallMonitorOpen = 'CallMonitorOpen';
        public const GetMaxWLANs = 'GetMaxWLANs';
        public const RefreshHostList = 'RefreshHostList';
        public const NewHostListEvent = 'NewHostListEvent';
        public const LoadAndGetData = 'LoadAndGetData';
        public const LoadAndSaveFile = 'LoadAndSaveFile';
        public const GetFile = 'GetFile';
        public const GetAreaCodes = 'GetAreaCodes';
        public const SetPhonebooks = 'SetPhonebooks';
        public const GetPhonebooks = 'GetPhonebooks';
        public const SetPhoneDevices = 'SetPhoneDevices';
        public const GetPhoneDevice = 'GetPhoneDevice';
        public const GetPhoneDevices = 'GetPhoneDevices';
    }
}

namespace FritzBox\ClientSocket {
    class Property
    {
        public const Open = 'Open';
        public const Host = 'Host';
        public const Port = 'Port';
    }
}

namespace FritzBox\Base {
    /**
     * Gemeinsame Namen aller Instanzen, die von FritzBoxModulBase erben.
     */
    class Property
    {
        public const Index = 'Index';
        public const RefreshInterval = 'RefreshInterval';
    }

    class Timer
    {
        public const RenewSubscription = 'RenewSubscription';
        public const RefreshInfo = 'RefreshInfo';
        public const RefreshState = 'RefreshState';
    }

    class Action
    {
        public const Subscribe = 'Subscribe';
        public const RefreshInfo = 'RefreshInfo';
        public const RefreshState = 'RefreshState';
    }
}

namespace FritzBox\IO {
    class Property
    {
        public const Open = 'Open';
        public const Host = 'Host';
        public const Username = 'Username';
        public const Password = 'Password';
        public const ReturnIP = 'ReturnIP';
        public const ReturnPort = 'ReturnPort';
    }

    class Attribute
    {
        public const ConsumerAddress = 'ConsumerAddress';
        public const Events = 'Events';
        public const PhoneBooks = 'PhoneBooks';
        public const PhoneDevices = 'PhoneDevices';
        public const AreaCodes = 'AreaCodes';
        public const UsePPP = 'usePPP';
        public const HasIGD2 = 'HasIGD2';
        public const HasTel = 'HasTel';
        public const NoOfWlan = 'NoOfWlan';
    }

    class Timer
    {
        public const RenewSubscription = 'RenewSubscription';
    }

    class Action
    {
        public const KernelReady = 'KernelReady';
        public const Subscribe = 'Subscribe';
    }

    class Variables
    {
        public const ConnectionType = 'ConnectionType';
    }
}

namespace FritzBox\Time {
    class Variables
    {
        public const CurrentLocalTime = 'CurrentLocalTime';
        public const NTPServer = 'NTPServer';
        public const NTPServer2 = 'NTPServer2';
    }
}

namespace FritzBox\Table {
    /**
     * Properties der Tabellen-Darstellung (FritzBoxTable).
     */
    class Property
    {
        public const Table = 'Table';
        public const Columns = 'Columns';
        public const Rows = 'Rows';
    }
}

namespace FritzBox\TelHelper {
    /**
     * Properties der Rückwärtssuche (FritzBoxTelHelper), genutzt von Anrufmonitor und Telefonie.
     */
    class Property
    {
        public const CustomSearchScriptID = 'CustomSearchScriptID';
        public const MaxNameSize = 'MaxNameSize';
        public const ReverseSearchInstanceID = 'ReverseSearchInstanceID';
        public const UnknownNumberName = 'UnknownNumberName';
    }
}

namespace FritzBox\Callmonitor {
    class Property
    {
        public const Actions = 'Actions';
        public const AreaCode = 'AreaCode';
        public const CallsAsNotification = 'CallsAsNotification';
        public const CallsAsTable = 'CallsAsTable';
        public const CountryCode = 'CountryCode';
        public const Icons = 'Icons';
        public const Notification = 'Notification';
        public const NotShowWarning = 'NotShowWarning';
        public const SearchMarker = 'SearchMarker';
        public const Targets = 'Targets';
    }

    class Action
    {
        public const CallsAsNotification = 'CallsAsNotification';
        public const CallsAsTable = 'CallsAsTable';
        public const CustomSearchScriptID = 'CustomSearchScriptID';
        public const EditAreaCodes = 'EditAreaCodes';
        public const PreviewIcon = 'PreviewIcon';
        public const ReadAreaCodes = 'ReadAreaCodes';
        public const RefreshCallList = 'RefreshCallList';
        public const ReverseSearchInstanceID = 'ReverseSearchInstanceID';
        public const RunActions = 'RunActions';
        public const SendNotification = 'SendNotification';
    }

    class Variables
    {
        public const CallList = 'CallList';
    }
}

namespace FritzBox\DHCPServer {
    class Variables
    {
        public const DHCPServerEnable = 'DHCPServerEnable';
        public const DNSServers = 'DNSServers';
        public const DomainName = 'DomainName';
        public const IPRouters = 'IPRouters';
        public const MaxAddress = 'MaxAddress';
        public const MinAddress = 'MinAddress';
        public const SubnetMask = 'SubnetMask';
    }
}

namespace FritzBox\DVBC {
    class Variables
    {
        public const DVBCEnabled = 'DVBCEnabled';
        public const SearchProgress = 'SearchProgress';
        public const StationSearchMode = 'StationSearchMode';
        public const StationSearchStatus = 'StationSearchStatus';
    }
}

namespace FritzBox\DeviceInfo {
    class Variables
    {
        public const DeviceLog = 'DeviceLog';
        public const LastReboot = 'LastReboot';
        public const Manufacturer = 'Manufacturer';
        public const Model = 'Model';
        public const Runtime = 'Runtime';
        public const RunTimeRAW = 'RunTimeRAW';
        public const SerialNumber = 'SerialNumber';
        public const SoftwareVersion = 'SoftwareVersion';
    }
}

namespace FritzBox\DynDNS {
    class Variables
    {
        public const Domain = 'Domain';
        public const Enable = 'Enable';
        public const EnableDDNS = 'EnableDDNS';
        public const IPv4State = 'IPv4State';
        public const IPv6State = 'IPv6State';
        public const ProviderName = 'ProviderName';
    }
}

namespace FritzBox\FileShare {
    class Property
    {
        public const Icons = 'Icons';
        public const SharesAsTable = 'SharesAsTable';
    }

    class Action
    {
        public const SharesAsTable = 'SharesAsTable';
    }

    class Variables
    {
        public const SharesTable = 'SharesTable';
    }
}

namespace FritzBox\FirmwareInfo {
    class Variables
    {
        public const AutoUpdateMode = 'AutoUpdateMode';
        public const BuildType = 'BuildType';
        public const CurrentFwVersion = 'CurrentFwVersion';
        public const LastFwVersion = 'LastFwVersion';
        public const UpdateState = 'UpdateState';
        public const UpdateSuccessful = 'UpdateSuccessful';
        public const UpdateTime = 'UpdateTime';
        public const UpgradeAvailable = 'UpgradeAvailable';
        public const WarrantyDate = 'WarrantyDate';
    }
}

namespace FritzBox\Homeautomation {
    class Property
    {
        public const AIN = 'AIN';
    }

    class Variables
    {
        public const HkrComfortTemperature = 'HkrComfortTemperature';
        public const HkrComfortVentilStatus = 'HkrComfortVentilStatus';
        public const HkrIsTemperature = 'HkrIsTemperature';
        public const HkrReduceTemperature = 'HkrReduceTemperature';
        public const HkrReduceVentilStatus = 'HkrReduceVentilStatus';
        public const HkrSetTemperature = 'HkrSetTemperature';
        public const HkrSetVentilStatus = 'HkrSetVentilStatus';
        public const MultimeterEnergy = 'MultimeterEnergy';
        public const MultimeterIsValid = 'MultimeterIsValid';
        public const MultimeterPower = 'MultimeterPower';
        public const Present = 'Present';
        public const SwitchIsValid = 'SwitchIsValid';
        public const SwitchLock = 'SwitchLock';
        public const SwitchMode = 'SwitchMode';
        public const SwitchState = 'SwitchState';
        public const TemperatureCelsius = 'TemperatureCelsius';
        public const TemperatureIsValid = 'TemperatureIsValid';
        public const TemperatureOffset = 'TemperatureOffset';
    }
}

namespace FritzBox\HostFilter {
    class Property
    {
        public const AutoAddHostVariables = 'AutoAddHostVariables';
        public const HostAsVariable = 'HostAsVariable';
        public const HostVariables = 'HostVariables';
        public const RenameHostVariables = 'RenameHostVariables';
    }

    class Action
    {
        public const DelHostVariable = 'DelHostVariable';
        public const HostAsVariable = 'HostAsVariable';
        public const HostvariablesPanel = 'HostvariablesPanel';
        public const ReloadForm = 'ReloadForm';
    }
}

namespace FritzBox\Hosts {
    class Property
    {
        public const AutoAddHostVariables = 'AutoAddHostVariables';
        public const HostAsTable = 'HostAsTable';
        public const HostAsVariable = 'HostAsVariable';
        public const HostVariables = 'HostVariables';
        public const RenameHostVariables = 'RenameHostVariables';
    }

    class Timer
    {
        public const RefreshHosts = 'RefreshHosts';
    }

    class Action
    {
        public const DelHostVariable = 'DelHostVariable';
        public const HostAsVariable = 'HostAsVariable';
        public const HostTableAsVariable = 'HostTableAsVariable';
        public const HostvariablesPanel = 'HostvariablesPanel';
        public const RefreshHosts = 'RefreshHosts';
        public const ReloadForm = 'ReloadForm';
    }

    class Variables
    {
        public const HostNumberActive = 'HostNumberActive';
        public const HostNumberOfEntries = 'HostNumberOfEntries';
        public const HostTable = 'HostTable';
    }
}

namespace FritzBox\MyFritz {
    class Variables
    {
        public const DeviceRegistered = 'DeviceRegistered';
        public const DynDNSName = 'DynDNSName';
        public const DynDNSURL = 'DynDNSURL';
        public const Enabled = 'Enabled';
        public const NumberOfServices = 'NumberOfServices';
    }
}

namespace FritzBox\NASStorage {
    class Variables
    {
        public const FTPEnable = 'FTPEnable';
        public const FTPStatus = 'FTPStatus';
        public const FTPWANEnable = 'FTPWANEnable';
        public const FTPWANPort = 'FTPWANPort';
        public const FTPWANSSLOnly = 'FTPWANSSLOnly';
        public const SMBEnable = 'SMBEnable';
    }
}

namespace FritzBox\Powerline {
    class Property
    {
        public const RenameDeviceVariables = 'RenameDeviceVariables';
    }
}

namespace FritzBox\Telephony {
    class Property
    {
        public const CallBarringAsVariable = 'CallBarringAsVariable';
        public const CallLogAsVariable = 'CallLogAsVariable';
        public const DeflectionAsVariable = 'DeflectionAsVariable';
        public const Icons = 'Icons';
        public const LastEntries = 'LastEntries';
        public const LoadListType = 'LoadListType';
        public const NotShowWarning = 'NotShowWarning';
        public const RefreshIntervalCallLog = 'RefreshIntervalCallLog';
        public const RefreshIntervalDeflectionList = 'RefreshIntervalDeflectionList';
        public const RefreshIntervalPhonebook = 'RefreshIntervalPhonebook';
        public const SearchMarker = 'SearchMarker';
        public const UseRelativDate = 'UseRelativDate';
    }

    class Timer
    {
        public const RefreshCallLog = 'RefreshCallLog';
        public const RefreshDeflectionList = 'RefreshDeflectionList';
        public const RefreshPhonebook = 'RefreshPhonebook';
    }

    class Action
    {
        public const CallLogAsVariable = 'CallLogAsVariable';
        public const CustomSearchScriptID = 'CustomSearchScriptID';
        public const InstallCIRSModule = 'InstallCIRSModule';
        public const PreviewIcon = 'PreviewIcon';
        public const RefreshCallLog = 'RefreshCallLog';
        public const RefreshDeflectionList = 'RefreshDeflectionList';
        public const RefreshPhonebook = 'RefreshPhonebook';
        public const ReverseSearchInstanceID = 'ReverseSearchInstanceID';
    }

    class Variables
    {
        public const CallLog = 'CallLog';
    }
}

namespace FritzBox\UPnPMediaServer {
    class Variables
    {
        public const Enable = 'Enable';
        public const UPnPMediaServer = 'UPnPMediaServer';
    }
}

namespace FritzBox\WANCommonInterface {
    class Property
    {
        public const RefreshLinkPropertiesInterval = 'RefreshLinkPropertiesInterval';
    }

    class Attribute
    {
        public const Downstream = 'Downstream';
        public const Upstream = 'Upstream';
    }

    class Timer
    {
        public const RefreshLinkProperties = 'RefreshLinkProperties';
    }

    class Action
    {
        public const RefreshLinkProperties = 'RefreshLinkProperties';
    }

    class Variables
    {
        public const DNSServer1 = 'DNSServer1';
        public const DNSServer2 = 'DNSServer2';
        public const DownstreamMaxBitRate = 'DownstreamMaxBitRate';
        public const KByteReceiveRate = 'KByteReceiveRate';
        public const KByteSendRate = 'KByteSendRate';
        public const LevelReceiveRate = 'LevelReceiveRate';
        public const LevelSendRate = 'LevelSendRate';
        public const PhysicalLinkStatus = 'PhysicalLinkStatus';
        public const TotalMBytesReceived = 'TotalMBytesReceived';
        public const TotalMBytesSent = 'TotalMBytesSent';
        public const UpnpControlEnabled = 'UpnpControlEnabled';
        public const UpstreamMaxBitRate = 'UpstreamMaxBitRate';
        public const VoipDNSServer1 = 'VoipDNSServer1';
        public const VoipDNSServer2 = 'VoipDNSServer2';
        public const WANAccessType = 'WANAccessType';
    }
}

namespace FritzBox\WANDSLLink {
    class Variables
    {
        public const AAL5CRCErrors = 'AAL5CRCErrors';
        public const ATMCRCErrors = 'ATMCRCErrors';
        public const ATMEncapsulation = 'ATMEncapsulation';
        public const ATMReceivedBlocks = 'ATMReceivedBlocks';
        public const ATMTransmittedBlocks = 'ATMTransmittedBlocks';
        public const AutoConfig = 'AutoConfig';
        public const DestinationAddress = 'DestinationAddress';
        public const FCSPreserved = 'FCSPreserved';
        public const LinkStatus = 'LinkStatus';
        public const LinkType = 'LinkType';
        public const ModulationType = 'ModulationType';
    }
}

namespace FritzBox\WANIPConnection {
    class Attribute
    {
        public const MigrationVersion = 'MigrationVersion';
    }

    class Variables
    {
        public const ConnectionAction = 'ConnectionAction';
        public const ConnectionStatus = 'ConnectionStatus';
        public const ExternalIPAddress = 'ExternalIPAddress';
        public const ExternalIPv6Address = 'ExternalIPv6Address';
        public const IPv4DNSServer1 = 'IPv4DNSServer1';
        public const IPv4DNSServer2 = 'IPv4DNSServer2';
        public const IPv6DNSServer1 = 'IPv6DNSServer1';
        public const IPv6DNSServer2 = 'IPv6DNSServer2';
        public const IPv6Prefix = 'IPv6Prefix';
        public const Uptime = 'Uptime';
        public const UptimeRAW = 'UptimeRAW';
    }
}

namespace FritzBox\WANPhysicalInterface {
    class Timer
    {
        public const RefreshLinkProperties = 'RefreshLinkProperties';
    }

    class Action
    {
        public const RefreshLinkProperties = 'RefreshLinkProperties';
    }

    class Variables
    {
        public const DownstreamMaxBitRate = 'DownstreamMaxBitRate';
        public const PhysicalLinkStatus = 'PhysicalLinkStatus';
        public const UpstreamMaxBitRate = 'UpstreamMaxBitRate';
        public const WANAccessType = 'WANAccessType';
    }
}

namespace FritzBox\WANPortMapping {
    class Variables
    {
        public const PortMappingNumberOfEntries = 'PortMappingNumberOfEntries';
    }
}

namespace FritzBox\WLAN {
    class Property
    {
        public const AutoAddHostVariables = 'AutoAddHostVariables';
        public const HostAsTable = 'HostAsTable';
        public const HostAsVariable = 'HostAsVariable';
        public const HostVariables = 'HostVariables';
        public const InfoVariables = 'InfoVariables';
        public const QRCodeSize = 'QRCodeSize';
        public const RenameHostVariables = 'RenameHostVariables';
        public const ShowWLanKeyAsQRCode = 'ShowWLanKeyAsQRCode';
        public const ShowWLanKeyAsVariable = 'ShowWLanKeyAsVariable';
    }

    class Action
    {
        public const DelHostVariable = 'DelHostVariable';
        public const HostAsTable = 'HostAsTable';
        public const HostAsVariable = 'HostAsVariable';
        public const HostvariablesPanel = 'HostvariablesPanel';
        public const ReloadForm = 'ReloadForm';
        public const ShowWLanKeyAsQRCode = 'ShowWLanKeyAsQRCode';
    }

    class Variables
    {
        public const ForcedOff = 'ForcedOff';
        public const HostNumberOfEntries = 'HostNumberOfEntries';
        public const HostTable = 'HostTable';
        public const KeyPassphrase = 'KeyPassphrase';
        public const OffTime = 'OffTime';
        public const SSID = 'SSID';
        public const TimeoutActive = 'TimeoutActive';
        public const TimeRemain = 'TimeRemain';
        public const TimeRemainRaw = 'TimeRemainRaw';
        public const X_AVM_DE_APEnabled = 'X_AVM_DE_APEnabled';
    }

    class Media
    {
        public const QRCodeIMG = 'QRCodeIMG';
    }
}

namespace FritzBox\WebDavStorage {
    class Property
    {
        public const Password = 'Password';
        public const Username = 'Username';
    }

    class Variables
    {
        public const Enable = 'Enable';
    }
}

namespace FritzBox\AnsweringMachine {
    class Property
    {
        public const TAMIndex = 'TAMIndex';
        public const MessagesAsTable = 'MessagesAsTable';
    }

    class Variables
    {
        public const Enable = 'Enable';
        public const Messages = 'Messages';
        public const NewMessages = 'NewMessages';
        public const TotalMessages = 'TotalMessages';
    }

    class Attribute
    {
        public const HookSecret = 'HookSecret';
    }

    class Hook
    {
        public const Prefix = '/hook/FritzBoxTAM';
        public const Play = 'Play';
        public const Mark = 'Mark';
        public const Delete = 'Delete';
    }
}

namespace FritzBox\DECT {
    class Property
    {
        public const RenameDeviceVariables = 'RenameDeviceVariables';
    }

    class Variables
    {
        public const Model = 'Model';
        public const UpdateAvailable = 'UpdateAvailable';
    }
}

namespace FritzBox\DSLInterface {
    class Variables
    {
        public const Status = 'Status';
        public const UpstreamCurrRate = 'UpstreamCurrRate';
        public const DownstreamCurrRate = 'DownstreamCurrRate';
        public const UpstreamMaxRate = 'UpstreamMaxRate';
        public const DownstreamMaxRate = 'DownstreamMaxRate';
        public const UpstreamNoiseMargin = 'UpstreamNoiseMargin';
        public const DownstreamNoiseMargin = 'DownstreamNoiseMargin';
        public const UpstreamAttenuation = 'UpstreamAttenuation';
        public const DownstreamAttenuation = 'DownstreamAttenuation';
        public const FECErrors = 'FECErrors';
        public const CRCErrors = 'CRCErrors';
        public const ErroredSecs = 'ErroredSecs';
        public const SeverelyErroredSecs = 'SeverelyErroredSecs';
    }
}

namespace FritzBox\MobileConnection {
    class Variables
    {
        public const Enabled = 'Enabled';
        public const Status = 'Status';
        public const CurrentAccessTechnology = 'CurrentAccessTechnology';
        public const Roaming = 'Roaming';
        public const SignalRSRP0 = 'SignalRSRP0';
        public const SignalRSRP1 = 'SignalRSRP1';
    }
}

namespace FritzBox\WANFiber {
    class Variables
    {
        public const FiberMode = 'FiberMode';
        public const OpticalSignalLevel = 'OpticalSignalLevel';
        public const TransmitOpticalLevel = 'TransmitOpticalLevel';
        public const ConnectionRateDown = 'ConnectionRateDown';
        public const ConnectionRateUp = 'ConnectionRateUp';
        public const PacketErrorsSent = 'PacketErrorsSent';
        public const PacketErrorsReceived = 'PacketErrorsReceived';
        public const Resyncs = 'Resyncs';
        public const MinutesInShowtime = 'MinutesInShowtime';
    }
}

namespace FritzBox\WANIPv6Firewall {
    class Variables
    {
        public const FirewallEnabled = 'FirewallEnabled';
        public const InboundPinholeAllowed = 'InboundPinholeAllowed';
    }
}
