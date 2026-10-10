[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-fritzbox-ersatz-fuer-fritzbox-project/125451)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v70-v71-q1-2024/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# FritzBox WebDav Speicher <!-- omit in toc -->

Auslesen und steuern des Online-Speichers.  

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
- Auslesen und steuern des Online-Speichers.  

## 2. Voraussetzungen

- Symcon ab Version 7.1

## 3. Software-Installation

- Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  

Unter 'Instanz hinzufügen' ist das 'FritzBox Online-Speicher'-Modul unter dem Hersteller 'AVM' aufgeführt.

**Konfigurationsseite**:

![Config](imgs/config.png)  

**Konfigurationsparameter**:  

| Eigenschaft              | Name            | Typ     | Standardwert | Beschreibung                                                        |
| ------------------------ | --------------- | ------- | ------------ | ------------------------------------------------------------------- |
| Aktualisierungsintervall | RefreshInterval | integer | 3600         | Intervall in Sekunden, in dem die Werte abgefragt werden; `0` = aus |
| Benutzername             | Username        | string  |              | Benutzername beim WebDAV-Anbieter                                   |
| Passwort                 | Password        | string  |              | Passwort zum Benutzernamen                                          |

> [!IMPORTANT]
> Beim Schalten des Online-Speichers überträgt die Instanz Benutzername und Passwort aus ihrer Konfiguration an die FritzBox. Sind sie hier nicht eingetragen, werden die Zugangsdaten in der FritzBox mit leeren Werten überschrieben.

Beispiel:  

```php
IPS_SetProperty(12345, 'Username', 'benutzer');
IPS_SetProperty(12345, 'Password', 'GeheimesPasswort');
IPS_ApplyChanges(12345);
```

## 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

| Ident  | Name                | Typ     | Beschreibung                                      |
| ------ | ------------------- | ------- | ------------------------------------------------- |
| Enable | WebDav Client Aktiv | boolean | Online-Speicher (WebDAV-Client) der FritzBox aktiv |

## 6. Visualisierung

### Kachel Visualisierung

Die Instanz hat nur eine Variable und wird deshalb als `Einzelnes Element` angezeigt: Die Kachel zeigt direkt den Schalter `WebDav Client Aktiv`, der in der Kachel geschaltet werden kann.  

![Kachel](imgs/tile_instance.png)  

In der Darstellung `Liste` (maximierte Kachel, Umschalten über das Tabellen-Symbol) erscheint die Variable als Schalter mit Beschriftung.  

![Kachel Liste](imgs/tile_list.png)  

### WebFront Visualisierung

![WebFront](imgs/webfront.png)  

## 7. PHP-Befehlsreferenz

Alle Funktionen liefern `false`, wenn die Instanz nicht aktiv ist oder die FritzBox einen Fehler meldet. Meldet die FritzBox einen Fehler, wird zusätzlich eine Meldung ausgegeben.  

---

```php
array|false FB_GetInfo(integer $InstanzID);
```

Liefert die Einstellungen des Online-Speichers als Array mit den Schlüsseln `NewEnable` (`1` = aktiv), `NewHostURL` (Adresse des WebDAV-Anbieters), `NewUsername` und `NewMountpointName` (Name des Speichers in der FritzBox). Das Passwort wird nicht geliefert.  

```php
$Result = FB_GetInfo(12345);
echo $Result['NewMountpointName']; // Online-Speicher
```

---

```php
bool FB_EnableWebDAVClient(integer $InstanzID, bool $Enable, string $HostURL, string $NewMountpointName);
```

Schaltet den Online-Speicher ein (`true`) oder aus (`false`) und setzt dabei Adresse und Namen des Speichers. Benutzername und Passwort werden aus der Konfiguration der Instanz übernommen. Für unveränderte Werte `NewHostURL` und `NewMountpointName` aus `FB_GetInfo` übergeben. Liefert `true` bei Erfolg.  
*Nicht live getestet.*  

```php
$Info = FB_GetInfo(12345);
FB_EnableWebDAVClient(12345, true, $Info['NewHostURL'], $Info['NewMountpointName']);
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
