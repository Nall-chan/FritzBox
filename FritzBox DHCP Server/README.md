[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20version-0.80-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-6.0%20%3E-green.svg)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions) [![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# FritzBox DHCP Server <!-- omit in toc -->
Internen DHCP-Server der FritzBox verwalten.  

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

* Auslesen und abbilden der Konfiguration des DHCP Server der FritzBox in Symcon Variablen.
* Verändern der Konfiguration über Symcon.  
* Alte Variablen vom FB-Project **sind** kompatibel.

## 2. Voraussetzungen

- IP-Symcon ab Version 6.0

## 3. Software-Installation

* Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

 Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  
 
 Unter 'Instanz hinzufügen' ist das 'FritzBox DHCP Server'-Modul unter dem Hersteller 'AVM' aufgeführt.

__Konfigurationsseite__:

![Config](imgs/config.png)

__Konfigurationsparameter__: 
| Name            | Typ     | Beschreibung                         |
| --------------- | ------- | ------------------------------------ |
| RefreshInterval | integer | Aktualisierungsintervall in Sekunden |

## 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

### Statusvariablen

| Ident            | Name             | Typ    | Beschreibung                                  |
| ---------------- | ---------------- | ------ | --------------------------------------------- |
| DHCPServerEnable | DHCP aktiv       | string | DHCP Server ist ein/ausgeschaltet             |
| MinAddress       | IP-Adresse Start | string | Start Adresse des IP-Adressbereiches          |
| MaxAddress       | IP-Adresse Ende  | string | Letzte Adresse des IP-Adressbereiches         |
| SubnetMask       | Subnet Mask      | string | Subnet Maske des Adressbereiches              |
| IPRouters        | Gateway          | string | Gateway welches den Clients übergeben wird    |
| DNSServers       | DNS-Server       | string | DNS-Server welcher den Clients übergeben wird |
| DomainName       | Doamin           | string | Domain welche den Clients übergeben wird      |


### Profile

Dieses Modul erzeugt keine Variablenprofile.  

## 6. WebFront

![Webfront](imgs/webfront.png)

## 7. PHP-Funktionsreferenz

**Details folgen**

```php
array FB_GetInfo(integer $InstanceID)
boolean FB_SetDHCPServerEnable(integer $InstanceID, boolean $Value) 
array FB_GetAddressRange(integer $InstanceID)
boolean FB_SetAddressRange(integer $InstanceID, string $MinAddress, string $MaxAddress)
string FB_GetSubnetMask(integer $InstanceID)
boolean FB_SetSubnetMask(integer $InstanceID, string $SubnetMask)
string FB_GetIPRoutersList(integer $InstanceID)
boolean FB_SetIPRouter(integer $InstanceID, string $IPRouters)
string FB_GetDNSServers(integer $InstanceID)
integer FB_GetIPInterfaceNumberOfEntries(integer $InstanceID)
boolean FB_SetIPInterface(integer $InstanceID, boolean $Enable, string $IPInterfaceIPAddress, string $IPInterfaceSubnetMask, string $IPInterfaceIPAddressingType)
```

## 8. Aktionen

Folgende Aktion ist Verfügbar:

ActionId: `{84136A92-5C4B-AF6D-ECB0-D18E7FB4DE2C}`  
DHCP-Server steuern (Aktiviert oder deaktiviert den DHCP-Server der FritzBox)

## 9. Anhang

### 1. Changelog

[Changelog der Library](../README.md#changelog)

### 2. Spenden

  Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/donate?hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share) 

## 10. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  

