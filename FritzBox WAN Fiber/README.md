[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-fritzbox-ersatz-fuer-fritzbox-project/125451)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FFritzBox%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v70-v71-q1-2024/)
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/FritzBox/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![Run Tests](https://github.com/Nall-chan/FritzBox/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/FritzBox/actions)
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)
# FritzBox Glasfaser-Anschluss <!-- omit in toc -->

Auslesen der Werte eines Glasfaser-Anschlusses (z.B. GPON) einer FritzBox.  

> [!WARNING]
> Dieses Modul wurde nach der Schnittstellenbeschreibung von AVM erstellt und nicht an einer FritzBox mit Glasfaser-Anschluss getestet. Tester mit einer solchen Box (z.B. FRITZ!Box 5690 Pro) werden gesucht, siehe „Hinweise für Tester“.

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

- Betriebsart des Anschlusses (z.B. GPON, XGSPON, AON).  
- Optischer Empfangs- und Sendepegel in dBm.  
- Verbindungsraten, Paketfehler, Neusynchronisierungen und Minuten im Showtime-Zustand.  
- Daten des optischen Moduls (SFP) und GPON-Daten per PHP-Befehl.  

## 2. Voraussetzungen

- Symcon ab Version 7.1
- FritzBox mit Glasfaser-Anschluss (der Konfigurator bietet das Modul nur an, wenn die FritzBox den Dienst `X_AVM-DE_WANFiber` meldet)

## 3. Software-Installation

- Über den Module Store das `FritzBox`-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

Es wird empfohlen Instanzen über die entsprechenden [FritzBox Konfigurator](../FritzBox%20Configurator/README.md)-Instanz zu erzeugen.  

Unter 'Instanz hinzufügen' ist das 'FritzBox WAN Fiber'-Modul unter dem Hersteller 'AVM' aufgeführt.

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

| Ident                | Name                         | Typ     | Beschreibung                                   |
| -------------------- | ---------------------------- | ------- | ---------------------------------------------- |
| FiberMode            | Betriebsart                  | string  | z.B. `GPON`, `XGSPON`, `AON`, `Unknown`        |
| OpticalSignalLevel   | Optischer Empfangspegel      | float   | Empfangspegel in dBm                           |
| TransmitOpticalLevel | Optischer Sendepegel         | float   | Sendepegel in dBm                              |
| ConnectionRateDown   | Downstream aktuelle Rate     | integer | Verbindungsrate in kBit/s (Einheit angenommen) |
| ConnectionRateUp     | Upstream aktuelle Rate       | integer | Verbindungsrate in kBit/s (Einheit angenommen) |
| PacketErrorsSent     | Paketfehler gesendet         | integer | Zähler                                         |
| PacketErrorsReceived | Paketfehler empfangen        | integer | Zähler                                         |
| Resyncs              | Neusynchronisierungen        | integer | Zähler                                         |
| MinutesInShowtime    | Minuten im Showtime-Zustand  | integer | Minuten seit der letzten Synchronisation       |


> [!NOTE]
> Die optischen Pegel liefert die FritzBox laut AVM in dBm/1000, das Modul rechnet sie in dBm um. Die Einheit der Verbindungsraten nennt die Beschreibung von AVM nicht, angenommen wird kBit/s wie bei DSL. Beides ist nicht an einer Glasfaser-Box geprüft.

## 6. Visualisierung

### Kachel Visualisierung

Die Instanz wird als Kachel in der Darstellung `Liste` angezeigt. Keine der Variablen ist bedienbar.  
*Kein Screenshot.*  

### WebFront Visualisierung

*Nicht geprüft.*  

## 7. PHP-Befehlsreferenz

Alle Funktionen liefern `false`, wenn die Instanz nicht aktiv ist oder die FritzBox einen Fehler meldet. Meldet die FritzBox einen Fehler, wird zusätzlich eine Meldung ausgegeben.  
Alle Werte in den Arrays sind Strings. Die Schlüssel entsprechen der Schnittstellenbeschreibung von AVM (`X_AVM-DE_WANFiber`). *Alle Funktionen sind nicht live getestet.*  

---

```php
array|false FB_GetInfo(integer $InstanzID);
```

Liefert Betriebsart (`NewFiberMode`), Pegel und Schwellwerte (`NewOpticalSignalLevel`, `NewTransmitOpticalLevel`, `NewLowerOpticalThreshold`, `NewUpperOpticalThreshold`, `NewLowerTransmitPowerThreshold`, `NewUpperTransmitPowerThreshold`, jeweils dBm/1000) sowie Daten des optischen Moduls (`NewSFPVendor`, `NewSFPPartNumber`, `NewSFPSerialNumber`, `NewSFPType`, `NewTXWaveLength`).  

```php
$Info = FB_GetInfo(12345);
echo $Info['NewOpticalSignalLevel'] / 1000 . ' dBm';
```

---

```php
array|false FB_GetInfoGPON(integer $InstanzID);
```

Liefert GPON-Daten (`NewGPONSerial`, `NewPONId`, `NewONUId`, `NewUNIType`, `NewGEMPortCount`). Nur bei Boxen mit GPON; sonst meldet die FritzBox einen Fehler (`401 Invalid Action`).  

```php
var_dump(FB_GetInfoGPON(12345));
```

---

```php
array|false FB_GetStatistics(integer $InstanzID);
```

Liefert Zähler und Verbindungsdaten (`NewBytesSent`, `NewBytesReceived`, `NewPacketsSent`, `NewPacketsReceived`, `NewPacketErrorsSent`, `NewPacketErrorsReceived`, `NewPacketsMulticast`, `NewConnectionRateDown`, `NewConnectionRateUp`, `NewBestTrainState`, `NewResyncs`, `NewMinutesInShowtime`).  

```php
var_dump(FB_GetStatistics(12345));
```

### Hinweise für Tester <!-- omit in toc -->

Wer eine FritzBox mit Glasfaser-Anschluss hat, kann helfen, das Modul zu prüfen:

1. Instanz `FritzBox Glasfaser-Anschluss` über den Konfigurator anlegen. Wird sie dort nicht angeboten, meldet die FritzBox den Dienst nicht.
2. In einem Skript folgende Befehle ausführen (Instanz-ID einsetzen) und die Ausgabe im Forum posten. **Seriennummern (`NewSFPSerialNumber`, `NewGPONSerial`) vorher unkenntlich machen.**

```php
var_dump(FB_GetInfo(12345));
var_dump(FB_GetInfoGPON(12345));
var_dump(FB_GetStatistics(12345));
```

3. Bitte dazu angeben: FritzBox-Modell und FRITZ!OS-Version, Anschlussart (GPON, XGS-PON, AON …), die in der FritzBox angezeigten optischen Pegel und Verbindungsraten sowie die Werte der Statusvariablen.

Geklärt werden soll vor allem, ob die Pegel (dBm/1000) und die Einheit der Verbindungsraten (kBit/s) stimmen.

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

