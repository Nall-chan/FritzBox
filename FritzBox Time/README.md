[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-fritzbox-ersatz-fuer-fritzbox-project/125451)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v81-v90-q1-2026/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# FritzBox NTP-Server & Systemzeit <!-- omit in toc -->

Auslesen und Steuern des NTP Dienst.  

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

- Alte Variablen vom FB-Project **sind** kompatibel.  
- Auslesen und Steuern des NTP Dienst.  
- Anzeige der aktuellen Systemzeit.  

## 2. Voraussetzungen

- Symcon ab Version 9.0

## 3. Software-Installation

- Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  

Unter 'Instanz hinzufügen' ist das 'FritzBox Zeitserver'-Modul unter dem Hersteller 'AVM' aufgeführt.

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

| Ident            | Name                | Typ     | Beschreibung                                |
| ---------------- | ------------------- | ------- | ------------------------------------------- |
| CurrentLocalTime | Aktuelle Systemzeit | integer | Systemzeit der FritzBox (Unix-Zeitstempel)  |
| NTPServer        | NTP-Server 1        | string  | Erster Zeitserver der FritzBox              |
| NTPServer2       | NTP-Server 2        | string  | Zweiter Zeitserver der FritzBox             |

## 6. Visualisierung

### Kachel Visualisierung

Die Instanz wird als Kachel in der Darstellung `Liste` angezeigt. Das Bild zeigt die maximierte Kachel mit allen Variablen.  
`Aktuelle Systemzeit` wird als Datum/Uhrzeit dargestellt, die NTP-Server als Text. Die NTP-Server können direkt in der Kachel geändert werden.  

![Kachel Liste](imgs/tile_list.png)  

### WebFront Visualisierung

![WebFront](imgs/webfront.png)  

## 7. PHP-Befehlsreferenz

Alle Funktionen liefern `false`, wenn die Instanz nicht aktiv ist oder die FritzBox einen Fehler meldet. Meldet die FritzBox einen Fehler, wird zusätzlich eine Meldung ausgegeben.  

---

```php
array|false FB_GetInfo(integer $InstanzID);
```

Liefert Zeit- und Zeitserver-Einstellungen der FritzBox als Array. Alle Werte sind Strings.  

| Schlüssel               | Beschreibung                                               |
| ----------------------- | ---------------------------------------------------------- |
| NewCurrentLocalTime     | Systemzeit der FritzBox (ISO 8601)                         |
| NewLocalTimeZoneName    | Zeitzone im POSIX-Format                                   |
| NewLocalTimeZone        | Zeitzone (oft leer)                                        |
| NewDaylightSavingsUsed  | `1` = Sommerzeit-Angaben werden genutzt                    |
| NewDaylightSavingsStart | Beginn der Sommerzeit                                      |
| NewDaylightSavingsEnd   | Ende der Sommerzeit                                        |
| NewNTPServer1           | Erster Zeitserver                                          |
| NewNTPServer2           | Zweiter Zeitserver                                         |

```php
var_dump(FB_GetInfo(12345));
/*
array(8) {
  ["NewCurrentLocalTime"]=> string(25) "2026-10-09T19:08:28+02:00"
  ["NewDaylightSavingsEnd"]=> string(19) "0001-01-01T00:00:00"
  ["NewDaylightSavingsStart"]=> string(19) "0001-01-01T00:00:00"
  ["NewDaylightSavingsUsed"]=> string(1) "0"
  ["NewLocalTimeZone"]=> string(0) ""
  ["NewLocalTimeZoneName"]=> string(26) "CET-1CEST,M3.5.0,M10.5.0/3"
  ["NewNTPServer1"]=> string(15) "ptbtime1.ptb.de"
  ["NewNTPServer2"]=> string(15) "ptbtime2.ptb.de"
}
*/
```

---

```php
bool FB_SetNTPServers(integer $InstanzID, string $NTPServer1, string $NTPServer2);
```

Setzt beide Zeitserver der FritzBox und führt danach die Statusvariablen nach. Liefert `true` bei Erfolg.  

```php
var_dump(FB_SetNTPServers(12345, 'ptbtime1.ptb.de', 'ptbtime2.ptb.de')); // bool(true)
```

## 8. Aktionen

**Grundsätzlich können alle bedienbaren Statusvariablen als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) mit `Auf Wert schalten` angesteuert werden, so dass hier keine speziellen Aktionen benutzt werden müssen.**

Darüber hinaus bietet das Modul keine speziellen Aktionen.

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
