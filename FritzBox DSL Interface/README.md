[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-fritzbox-ersatz-fuer-fritzbox-project/125451)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v70-v71-q1-2024/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# FritzBox DSL-Schnittstelle <!-- omit in toc -->

Auslesen der DSL-Leitungswerte einer FritzBox mit DSL-Anschluss.  

> [!WARNING]
> Dieses Modul wurde nach der Schnittstellenbeschreibung von AVM erstellt. Ein Tester hat es an einer FRITZ!Box 7690 mit VDSL2 (Profil 35b, Vectoring) geprüft: Datenraten, Störabstand und Dämpfung stimmen mit der Anzeige der FritzBox überein. Beim FEC-Zähler gibt es eine Abweichung (siehe Statusvariablen). Rückmeldungen im Forum sind willkommen.

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

- DSL-Status, aktuelle und maximale Datenrate (Down- und Upstream).  
- Störabstand und Dämpfung (Down- und Upstream).  
- Fehlerzähler (FEC, CRC, fehlerhafte Sekunden) seit dem Start der FritzBox.  
- Erweiterte DSL-Werte und DSL-Diagnose per PHP-Befehl.  

## 2. Voraussetzungen

- Symcon ab Version 7.1
- FritzBox mit DSL-Anschluss (der Konfigurator bietet das Modul nur an, wenn die FritzBox den Dienst `WANDSLInterfaceConfig` meldet)

## 3. Software-Installation

- Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  

Unter 'Instanz hinzufügen' ist das 'FritzBox DSL Interface'-Modul unter dem Hersteller 'AVM' aufgeführt.

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

| Ident                 | Name                        | Typ     | Beschreibung                                    |
| --------------------- | --------------------------- | ------- | ----------------------------------------------- |
| Status                | DSL-Status                  | string  | Zustand der Leitung laut FritzBox, z.B. `Up`    |
| DownstreamCurrRate    | Downstream aktuelle Rate    | integer | Aktuelle Datenrate in kBit/s                    |
| UpstreamCurrRate      | Upstream aktuelle Rate      | integer | Aktuelle Datenrate in kBit/s                    |
| DownstreamMaxRate     | Downstream maximale Rate    | integer | Maximal mögliche Datenrate in kBit/s            |
| UpstreamMaxRate       | Upstream maximale Rate      | integer | Maximal mögliche Datenrate in kBit/s            |
| DownstreamNoiseMargin | Downstream Störabstand      | float   | Störabstand in dB                               |
| UpstreamNoiseMargin   | Upstream Störabstand        | float   | Störabstand in dB                               |
| DownstreamAttenuation | Downstream Dämpfung         | float   | Leitungsdämpfung in dB                          |
| UpstreamAttenuation   | Upstream Dämpfung           | float   | Leitungsdämpfung in dB                          |
| FECErrors             | FEC-Fehler                  | integer | Zähler der korrigierten Fehler (`NewFECErrors`) |
| CRCErrors             | CRC-Fehler                  | integer | Zähler der nicht korrigierbaren Fehler          |
| ErroredSecs           | Fehlerhafte Sekunden        | integer | Sekunden mit Fehlern seit dem Start             |
| SeverelyErroredSecs   | Stark fehlerhafte Sekunden  | integer | Sekunden mit vielen Fehlern seit dem Start      |

> [!NOTE]
> Störabstand und Dämpfung liefert die FritzBox laut TR-064 in 0,1 dB, das Modul rechnet sie in dB um (an einer 7690 mit VDSL2 bestätigt: `70` = 7,0 dB Störabstand, `200` = 20,0 dB Dämpfung).
>
> Die FEC-Fehler sind ein Zähler der Schnittstelle (`NewFECErrors` von `FB_GetStatisticsTotal`), der laufend wächst. Die Anzeige der FritzBox weicht davon ab (Zeitraum oder Zählweise ungeklärt).

## 6. Visualisierung

### Kachel Visualisierung

Die Instanz wird als Kachel in der Darstellung `Liste` angezeigt. Alle Werte werden als Text bzw. Zahl mit Einheit dargestellt. Keine der Variablen ist bedienbar.  
*Kein Screenshot, da keine DSL-Box zum Testen vorhanden war.*  

### WebFront Visualisierung

*Nicht geprüft.*  

## 7. PHP-Befehlsreferenz

Alle Funktionen liefern `false`, wenn die Instanz nicht aktiv ist oder die FritzBox einen Fehler meldet. Meldet die FritzBox einen Fehler, wird zusätzlich eine Meldung ausgegeben.  
Alle Werte in den Arrays sind Strings. Die Schlüssel entsprechen der Schnittstellenbeschreibung von AVM (`WANDSLInterfaceConfig`). *Alle vier Funktionen wurden von einem Tester an einer 7690 mit VDSL2 ausgeführt und lieferten Daten; die Werte von `FB_GetDSLInfo` (z.B. `NewSNRpsds`) sind nicht ausgewertet.*  

---

```php
array|false FB_GetInfo(integer $InstanzID);
```

Liefert Status (`NewStatus`), aktuelle und maximale Raten (`NewDownstreamCurrRate`, `NewUpstreamCurrRate`, `NewDownstreamMaxRate`, `NewUpstreamMaxRate`, kBit/s), Störabstand (`NewDownstreamNoiseMargin`, `NewUpstreamNoiseMargin`) und Dämpfung (`NewDownstreamAttenuation`, `NewUpstreamAttenuation`, jeweils 0,1 dB laut TR-064) sowie Sendeleistung und Hersteller der Gegenstelle (`NewATURVendor`).  

```php
$Info = FB_GetInfo(12345);
echo $Info['NewDownstreamCurrRate'] . ' kBit/s';
```

---

```php
array|false FB_GetStatisticsTotal(integer $InstanzID);
```

Liefert die Fehlerzähler der Schnittstelle, u.a. `NewFECErrors`, `NewCRCErrors`, `NewErroredSecs`, `NewSeverelyErroredSecs`, `NewLinkRetrain` sowie die Zähler der Gegenstelle (`NewATUCFECErrors`, `NewATUCCRCErrors`).  

```php
echo FB_GetStatisticsTotal(12345)['NewCRCErrors'];
```

---

```php
array|false FB_GetDSLInfo(integer $InstanzID);
```

Liefert erweiterte DSL-Werte (`X_AVM-DE_GetDSLInfo`), u.a. Störabstand je Band (`NewSNRpsds`, `NewSNRpsus`), Leitungsdämpfung je Band (`NewLATNds`, `NewLATNus`), `NewLinkStatus`, `NewModulationType` und `NewCurrentProfile`.  

```php
echo FB_GetDSLInfo(12345)['NewCurrentProfile'];
```

---

```php
array|false FB_GetDSLDiagnoseInfo(integer $InstanzID);
```

Liefert das Ergebnis der letzten DSL-Diagnose (`X_AVM-DE_GetDSLDiagnoseInfo`), u.a. `NewX_AVM-DE_DSLDiagnoseState`, `NewX_AVM-DE_DSLActive` und `NewX_AVM-DE_DSLSync`.  

```php
var_dump(FB_GetDSLDiagnoseInfo(12345));
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
