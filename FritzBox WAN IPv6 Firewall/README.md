[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-fritzbox-ersatz-fuer-fritzbox-project/125451)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v70-v71-q1-2024/)
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)
# FritzBox WAN IPv6-Firewall <!-- omit in toc -->

Status der IPv6-Firewall einer FritzBox und Verwaltung von Pinholes (IPv6-Freigaben für eingehende Verbindungen).  

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

- Anzeige, ob die IPv6-Firewall aktiv ist und ob eingehende Pinholes erlaubt sind.  
- Pinholes per PHP-Befehl anlegen und löschen (die Gültigkeit ändert man mit erneutem `FB_AddPinhole`, siehe Befehlsreferenz).  

## 2. Voraussetzungen

- Symcon ab Version 7.1
- FritzBox mit IGD2 (Dienst `WANIPv6FirewallControl`)
- FritzBox mit IPv6-Anschluss

## 3. Software-Installation

- Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  

Unter 'Instanz hinzufügen' ist das 'FritzBox WAN IPv6 Firewall'-Modul unter dem Hersteller 'AVM' aufgeführt.

**Konfigurationsparameter**:  

| Eigenschaft              | Name            | Typ     | Standardwert | Beschreibung                                                        |
| ------------------------ | --------------- | ------- | ------------ | ------------------------------------------------------------------- |
| Aktualisierungsintervall | RefreshInterval | integer | 60           | Intervall in Sekunden, in dem die Werte abgefragt werden; `0` = aus |

Beispiel:  

```php
IPS_SetProperty(12345, 'RefreshInterval', 30);
IPS_ApplyChanges(12345);
```

*Kein Screenshot der Konfiguration, da das Modul noch nicht an einer passenden FritzBox geprüft wurde.*  

## 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

| Ident                 | Name                        | Typ     | Beschreibung                                         |
| --------------------- | --------------------------- | ------- | ---------------------------------------------------- |
| FirewallEnabled       | IPv6-Firewall aktiv         | boolean | `true` = Firewall aktiv                              |
| InboundPinholeAllowed | Eingehende Pinholes erlaubt | boolean | `true` = die FritzBox lässt neue Pinholes zu         |


> [!NOTE]
> Anders als bei den Port-Weiterleitungen (IPv4) kennt die FritzBox keine Aktion zum Auflisten der Pinholes. Ein Pinhole ist nur über die `UniqueID` erreichbar, die `FB_AddPinhole` zurückliefert. Diese ID muss der Aufrufer selbst speichern. Pinholes sind zeitlich begrenzt (1 bis 86400 Sekunden).

## 6. Visualisierung

### Kachel Visualisierung

Die Instanz wird als Kachel in der Darstellung `Liste` angezeigt. Keine der Variablen ist bedienbar.  
*Kein Screenshot.*  

### WebFront Visualisierung

*Nicht geprüft.*  

## 7. PHP-Befehlsreferenz

Alle Funktionen liefern `false`, wenn die Instanz nicht aktiv ist oder die FritzBox einen Fehler meldet. Meldet die FritzBox einen Fehler, wird zusätzlich eine Meldung ausgegeben. Ungültige Parameter werden vor dem Senden mit einer Meldung abgelehnt.  
*Live getestet an einer FRITZ!Box 6690 (FRITZ!OS 8.25): `FB_GetFirewallStatus`, `FB_AddPinhole` und `FB_DeletePinhole` funktionieren (das Pinhole erscheint in der FritzBox beim Gerät unter „geöffnete Ports über IPv6“). `FB_UpdatePinhole` wurde dort immer mit `Error code 501: Generic Error` abgelehnt, auch mit anderen Gültigkeiten und mit erlaubter Einstellung, obwohl AVM die Aktion als unterstützt nennt. Die Gültigkeit lässt sich stattdessen ändern, indem `FB_AddPinhole` noch einmal mit denselben Parametern, aber anderer `$LeaseTime` aufgerufen wird: Die UPnP-Beschreibung schreibt vor, dass die FritzBox dann die Gültigkeit des vorhandenen Pinholes verlängert und dessen `UniqueID` zurückliefert (an der 6690 geprüft: gleiche ID, anderer Port ergibt eine neue ID). Die Funktionen `GetPinholePackets` und `CheckPinholeWorking` aus der Schnittstellenbeschreibung meldet die Box als nicht verfügbar (`403`) und sind deshalb nicht enthalten.*  

> [!IMPORTANT]
> Die FritzBox lässt Pinholes nur zu, wenn für die beteiligten Geräte die Einstellung **Selbstständige Portfreigaben und das Trennen und Wiederherstellen der Internetverbindung für dieses Gerät erlauben** aktiviert ist (Einstellungen des Geräts in der FritzBox). Ohne sie antwortet die FritzBox mit `Error code 606: Unknown Error Code`:
>
> - `FB_AddPinhole`: Die Einstellung muss für das Gerät der Adresse `InternalClient` aktiv sein (das Gerät, das erreichbar werden soll).
> - `FB_DeletePinhole`: Die Einstellung muss für das Gerät aktiv sein, auf dem IP-Symcon läuft (das Gerät, das den Befehl sendet).
>
> Als `InternalClient` die dauerhaft gültige IPv6-Adresse des Geräts angeben (Präfix plus Interface-ID), nicht die temporäre Adresse „GUA-Temporary“. Temporäre Adressen wechseln, danach läuft das Pinhole ins Leere.

---

```php
array|false FB_GetFirewallStatus(integer $InstanzID);
```

Liefert die Schlüssel `FirewallEnabled` und `InboundPinholeAllowed` (jeweils `1` oder `0`).  

```php
var_dump(FB_GetFirewallStatus(12345));
```

---

```php
int|false FB_AddPinhole(integer $InstanzID, string $RemoteHost, integer $RemotePort, string $InternalClient, integer $InternalPort, integer $Protocol, integer $LeaseTime);
```

Legt ein Pinhole an und liefert dessen `UniqueID`.  

| Parameter       | Beschreibung                                                                                  |
| --------------- | --------------------------------------------------------------------------------------------- |
| `$RemoteHost`     | IPv6-Adresse des Absenders; leer = beliebig                                                   |
| `$RemotePort`     | Port des Absenders (0 bis 65535); `0` = beliebig                                              |
| `$InternalClient` | IPv6-Adresse des Geräts im Heimnetz (Pflicht)                                                 |
| `$InternalPort`   | Port am Gerät (0 bis 65535); `0` = beliebig                                                   |
| `$Protocol`       | IP-Protokollnummer, z.B. `6` = TCP, `17` = UDP, `65535` = alle                                |
| `$LeaseTime`      | Gültigkeit in Sekunden (1 bis 86400)                                                          |

```php
$ID = FB_AddPinhole(12345, '', 0, '2001:db8::10', 443, 6, 3600);
```

---

```php
bool FB_UpdatePinhole(integer $InstanzID, integer $UniqueID, integer $LeaseTime);
bool FB_DeletePinhole(integer $InstanzID, integer $UniqueID);
```

`FB_UpdatePinhole` setzt die Gültigkeit (1 bis 86400 Sekunden) eines Pinholes neu, `FB_DeletePinhole` löscht es. `$UniqueID` ist der Wert, den `FB_AddPinhole` geliefert hat. Liefern `true` bei Erfolg.  
Fehlercodes der FritzBox laut UPnP-Beschreibung (`WANIPv6FirewallControl:1`): `606` nicht autorisiert (siehe Hinweis oben), `702` Firewall deaktiviert, `703` Pinholes über UPnP nicht erlaubt (`InboundPinholeAllowed` ist `false`), `704` kein Pinhole mit dieser `UniqueID`.  

```php
FB_UpdatePinhole(12345, $ID, 7200);
FB_DeletePinhole(12345, $ID);
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
