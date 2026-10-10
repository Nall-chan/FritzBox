[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-fritzbox-ersatz-fuer-fritzbox-project/125451)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v70-v71-q1-2024/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# FritzBox Geräte Informationen <!-- omit in toc -->

Allgemeine Geräte Informationen abrufen.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen](#5-statusvariablen)
- [6. Visualisierung](#6-visualisierung)
  - [Kachel Visualisierung](#kachel-visualisierung)
  - [WebFront Visualisierung](#webfront-visualisierung)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

- Auslesen und abbilden allgemeiner Informationen zur FritzBox in Symcon Variablen.
- Alte Variablen vom FB-Project sind **nicht** kompatibel.

## 2. Voraussetzungen

- Symcon ab Version 7.1

## 3. Software-Installation

- Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  

Unter 'Instanz hinzufügen' ist das 'FritzBox Geräte Informationen'-Modul unter dem Hersteller 'AVM' aufgeführt.

**Konfigurationsseite**:  

![Config](imgs/config.png)

**Konfigurationsparameter**:  

| Eigenschaft              | Name            | Typ     | Standardwert | Beschreibung                                                        |
| ------------------------ | --------------- | ------- | ------------ | ------------------------------------------------------------------- |
| Aktualisierungsintervall | RefreshInterval | integer | 60           | Intervall in Sekunden, in dem die Werte abgefragt werden; `0` = aus |

Beispiel:  

```php
IPS_SetProperty(12345, 'RefreshInterval', 300);
IPS_ApplyChanges(12345);
```

## 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

| Ident           | Name                | Typ     | Beschreibung                       |
| --------------- | ------------------- | ------- | ---------------------------------- |
| Manufacturer    | Hersteller          | string  | Herstellername                     |
| Model           | Modell              | string  | Modell                             |
| SerialNumber    | Seriennummer        | string  | Seriennummer des Gerätes           |
| SoftwareVersion | Software-Version    | string  | Software Version                   |
| LastReboot      | Letzter Neustart    | integer | UnixtimeStamp vom letzten Neustart |
| RunTimeRAW      | Laufzeit (sekunden) | integer | Laufzeit in Sekunden seit Neustart |
| Runtime         | Laufzeit            | string  | Laufzeit als Klartext              |
| DeviceLog       | Letzte Ereignisse   | string  | Die letzten Ereignisse             |

## 6. Visualisierung

### Kachel Visualisierung

Die Instanz wird als Kachel in der Darstellung `Liste` angezeigt. Das Bild zeigt die maximierte Kachel mit allen Variablen.  
Alle Werte werden als Text dargestellt, `Letzter Neustart` als Datum/Uhrzeit. `Letzte Ereignisse` öffnet über das Symbol den mehrzeiligen Text. Keine der Variablen ist bedienbar.  

![Kachel Liste](imgs/tile_list.png)  

### WebFront Visualisierung

![Webfront](imgs/webfront.png)

## 7. PHP-Befehlsreferenz

Alle Funktionen liefern `false`, wenn die Instanz nicht aktiv ist oder die FritzBox einen Fehler meldet. Meldet die FritzBox einen Fehler, wird zusätzlich eine Meldung ausgegeben.  

---

```php
array|false FB_GetInfo(integer $InstanzID);
```

Liefert die Geräteinformationen der FritzBox als Array. Alle Werte sind Strings.  

| Schlüssel           | Beschreibung                                      |
| ------------------- | ------------------------------------------------- |
| NewManufacturerName | Hersteller                                        |
| NewManufacturerOUI  | Herstellerkennung (OUI)                           |
| NewModelName        | Modell                                            |
| NewDescription      | Modell und Firmware-Version                       |
| NewProductClass     | Produktklasse                                     |
| NewSerialNumber     | Seriennummer                                      |
| NewSoftwareVersion  | Firmware-Version                                  |
| NewHardwareVersion  | Hardware-Version                                  |
| NewSpecVersion      | Version der Spezifikation                         |
| NewProvisioningCode | Provisionierungscode                              |
| NewUpTime           | Laufzeit seit dem letzten Neustart in Sekunden    |
| NewDeviceLog        | Die letzten Ereignisse aus dem Ereignisprotokoll  |

Beispiel:  

```php
$Result = FB_GetInfo(12345);
var_dump($Result);
```  

Ausgabe:  

```php
array(12) {
  ["NewManufacturerName"]=>
  string(3) "AVM"
  ["NewManufacturerOUI"]=>
  string(6) "00040E"
  ["NewModelName"]=>
  string(20) "FRITZ!Box 6690 Cable"
  ["NewDescription"]=>
  string(38) "FRITZ!Box 6690 Cable Release 267.07.57"
  ["NewProductClass"]=>
  string(9) "FRITZ!Box"
  ["NewSerialNumber"]=>
  string(12) "XXXXXXXXXXXX"
  ["NewSoftwareVersion"]=>
  string(9) "267.07.57"
  ["NewHardwareVersion"]=>
  string(20) "FRITZ!Box 6690 Cable"
  ["NewSpecVersion"]=>
  string(3) "1.0"
  ["NewProvisioningCode"]=>
  string(13) "AXConfigured!"
  ["NewUpTime"]=>
  string(7) "6221779"
  ["NewDeviceLog"]=>
  string() "19.12.23 19:30:49 Internettelefonie mit ....
}
```

---

```php
integer|false FB_GetSecurityPort(integer $InstanzID);
```

Liefert den Port der TR-064-Schnittstelle für verschlüsselte Verbindungen (https).  

```php
var_dump(FB_GetSecurityPort(12345)); // int(49443)
```

---

```php
string|false FB_GetDeviceLogPath(integer $InstanzID);
```

Liefert den Pfad (inklusive Sitzungs-ID) zum vollständigen Ereignisprotokoll der FritzBox. `NewDeviceLog` von `FB_GetInfo` enthält dagegen nur die letzten Ereignisse.  

```php
var_dump(FB_GetDeviceLogPath(12345)); // string(...) "/devicelog.lua?sid=xxxxxxxxxxxxxxxx"
```

## 8. Aktionen

Keine Aktionen verfügbar.

## 9. Anhang

### 1. Changelog

[Changelog der Library](../README.md#2-changelog)

### 2. Spenden

Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)  

## 10. Lizenz

  IPS-Modul:  
  [Custom NC-SA](../LICENSE)  
