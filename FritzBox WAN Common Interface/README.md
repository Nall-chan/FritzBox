[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-fritzbox-ersatz-fuer-fritzbox-project/125451)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v70-v71-q1-2024/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# FritzBox WAN Common Interface <!-- omit in toc -->
Auslesen der aktuell genutzten WAN Verbindung.  

### Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
  - [Statusvariablen](#statusvariablen)
  - [Profile](#profile)
- [6. WebFront](#6-webfront)
- [7. PHP-Funktionsreferenz](#7-php-funktionsreferenz)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

* Alte Variablen vom FB-Project **sind** kompatibel.  
* Auslesen der aktuell genutzten WAN Verbindung.  
  
## 2. Voraussetzungen

- Symcon ab Version 7.1

## 3. Software-Installation

* Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

 Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  
 
 Unter 'Instanz hinzufügen' ist das 'FritzBox allgemeine WAN-Schnittstelle'-Modul unter dem Hersteller 'AVM' aufgeführt.

__Konfigurationsseite__:

![Config](imgs/config.png)  

__Konfigurationsparameter__:  

| Name                          | Typ     | Beschreibung                                     |
| ----------------------------- | ------- | ------------------------------------------------ |
| Index                         | integer | Dienst (Service Index)                           |
| RefreshInterval               | integer | Aktualisierungsintervall in Sekunden             |
| RefreshLinkPropertiesInterval | integer | Aktualisierungsintervall Link Status in Sekunden |

## 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

### Statusvariablen

| Ident                | Name                                             | Typ     |
| -------------------- | ------------------------------------------------ | ------- |
| WANAccessType        | WAN Zugangsart                                   | string  |
| PhysicalLinkStatus   | Status der physischen Verbindung                 | string  |
| UpstreamMaxBitRate   | Upstream Max kBitrate                            | integer |
| DownstreamMaxBitRate | Downstream Max kBitrate                          | integer |
| KByteSendRate        | Senderate                                        | float   |
| KByteReceiveRate     | Empfangsrate                                     | float   |
| LevelReceiveRate     | Auslastung Download                              | float   |
| LevelSendRate        | Auslastung Upload                                | float   |
| TotalMBytesSent      | Gesendet seit verbunden                          | float   |
| TotalMBytesReceived  | Empfangen seit verbunden                         | float   |
| UpnpControlEnabled   | Automatische Portweiterleitung per UPnP erlauben | boolean |
| DNSServer1           | DNS-Server 1                                     | string  |
| DNSServer2           | DNS-Server 2                                     | string  |
| VoipDNSServer1       | VoIP DNS-Server 1                                | string  |
| VoipDNSServer2       | VoIP DNS-Server 2                                | string  |

### Profile

| Name             | Typ     |
| ---------------- | ------- |
| FB.kBit          | integer |
| FB.Speed         | float   |
| FB.MByte         | float   |
| FB.kbs           | float   |
| FB.AvmAccessType | string  |

## 6. WebFront

![WebFront](imgs/webfront.png)  

## 7. PHP-Funktionsreferenz

```php
array|false FB_GetCommonLinkProperties(integer $InstanzID);
integer|false FB_GetTotalBytesSent(integer $InstanzID);
integer|false FB_GetTotalBytesReceived(integer $InstanzID);
integer|false FB_GetTotalPacketsSent(integer $InstanzID);
integer|false FB_GetTotalPacketsReceived(integer $InstanzID);
array|false FB_GetAddonInfos(integer $InstanzID);
boolean FB_GetDsliteStatus(integer $InstanzID);
array|false FB_GetIPTVInfos(integer $InstanzID);
```

## 8. Aktionen

Keine Aktionen verfügbar.

## 9. Anhang

### 1. Changelog

[Changelog der Library](../README.md#changelog)

### 2. Spenden

  Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share) 

## 10. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  

