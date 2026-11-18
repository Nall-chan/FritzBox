<?php

declare(strict_types=1);

/**
 * Parser für Daten der FritzBox ohne Abhängigkeit von Symcon.
 * Getestet in tests/ParserTest.php mit den Beispielen aus der AVM-Beschreibung
 * „TR-064 Support – X_AVM-DE_OnTel“ (Telefonbuch, Anrufliste) und dem Ereignisformat
 * aus der UPnP Device Architecture 1.1 (Abschnitt 4.3.2).
 */

namespace FritzBox;

/**
 * Lädt XML ohne PHP-Warnungen; Fehler werden als Exception mit der Meldung von libxml geworfen.
 */
class XmlLoader
{
    /**
     * @throws \Exception Bei ungültigem XML.
     */
    public static function Load(string $Data): \SimpleXMLElement
    {
        $Previous = libxml_use_internal_errors(true);
        try {
            $Xml = simplexml_load_string(trim($Data));
            if ($Xml === false) {
                $Error = libxml_get_last_error();
                throw new \Exception('Invalid XML: ' . ($Error ? trim($Error->message) : 'unknown error'));
            }
            return $Xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($Previous);
        }
    }
}

/**
 * Ereignisse (GENA NOTIFY) der FritzBox.
 */
class EventParser
{
    public const EventNamespace = 'urn:schemas-upnp-org:event-1-0';

    /**
     * Liefert die Statusvariablen eines Ereignisses als Name => Wert.
     * Bindestriche im Namen werden durch Unterstriche ersetzt (X_AVM-DE_… → X_AVM_DE_…).
     *
     * @param string $Data Inhalt der NOTIFY-Anfrage (e:propertyset).
     *
     * @throws \Exception Bei ungültigem XML.
     *
     * @return array<string, string>
     */
    public static function Parse(string $Data): array
    {
        $Properties = [];
        $Xml = XmlLoader::Load($Data);
        $Namespaces = $Xml->getNamespaces(false);
        $Xml->registerXPathNamespace('event', $Namespaces['e'] ?? self::EventNamespace);
        foreach ($Xml->xpath('//event:property') as $Property) {
            foreach ($Property->children() as $Variable) {
                $Properties[str_replace('-', '_', $Variable->getName())] = (string) $Variable;
            }
        }
        return $Properties;
    }
}

/**
 * Telefonbücher der FritzBox (Format siehe AVM „X_AVM-DE_OnTel“, Phonebook Content).
 */
class Phonebook
{
    /**
     * Liefert die Varianten einer Rufnummer, nach denen im Telefonbuch gesucht wird:
     * die Nummer selbst, bei führender 0 zusätzlich mit Ländervorwahl bzw. ohne eigene Ortsvorwahl,
     * ohne führende 0 zusätzlich mit Orts- und Ländervorwahl.
     *
     * @param string $Number      Rufnummer.
     * @param string $AreaCode    Eigene Ortsvorwahl mit führender 0 (z.B. 030).
     * @param string $CountryCode Eigene Ländervorwahl (z.B. +49 oder 0049).
     *
     * @return string[]
     */
    public static function SearchNumbers(string $Number, string $AreaCode = '', string $CountryCode = ''): array
    {
        $Numbers = [$Number];
        if ($Number === '') {
            return $Numbers;
        }
        switch ($Number[0]) {
            case '+':
                break;
            case '0':
                if ($CountryCode != '') {
                    $Numbers[] = $CountryCode . substr($Number, 1);
                }
                if (($AreaCode != '') && (strpos($Number, $AreaCode) === 0)) {
                    $Numbers[] = substr($Number, strlen($AreaCode));
                }
                break;
            default:
                if ($AreaCode != '') {
                    $Numbers[] = $AreaCode . $Number;
                    if ($CountryCode != '') {
                        $Numbers[] = $CountryCode . substr($AreaCode, 1) . $Number;
                    }
                }
                break;
        }
        return $Numbers;
    }

    /**
     * Sucht den Namen des ersten Kontakts, der eine der Rufnummern enthält.
     * Die Rufnummern werden in der übergebenen Reihenfolge geprüft.
     *
     * @param string   $Xml         Telefonbuch als XML.
     * @param string[] $Numbers     Rufnummern (siehe SearchNumbers).
     * @param int      $MaxNameSize Maximale Länge des Namens in Zeichen.
     *
     * @throws \Exception Bei ungültigem XML.
     *
     * @return string|false Name oder false, wenn kein Kontakt gefunden wurde.
     */
    public static function FindName(string $Xml, array $Numbers, int $MaxNameSize): string|false
    {
        $PhoneBook = XmlLoader::Load($Xml);
        foreach ($Numbers as $Number) {
            // Ein Hochkomma würde den XPath-Ausdruck beenden, solche Nummern gibt es nicht.
            if (($Number === '') || (strpos($Number, "'") !== false)) {
                continue;
            }
            $Contact = $PhoneBook->xpath("//contact[telephony/number ='" . $Number . "']");
            if (($Contact !== false) && (count($Contact) > 0)) {
                return self::TruncateName((string) $Contact[0]->person->realName, $MaxNameSize);
            }
        }
        return false;
    }

    /**
     * Kürzt einen Namen auf die maximale Anzahl Zeichen (nicht Bytes, damit kein UTF-8-Zeichen zerteilt wird).
     *
     * @param string $Name        Name.
     * @param int    $MaxNameSize Maximale Länge in Zeichen.
     *
     * @return string
     */
    public static function TruncateName(string $Name, int $MaxNameSize): string
    {
        if (($MaxNameSize < 0) || (mb_strlen($Name, 'UTF-8') <= $MaxNameSize)) {
            return $Name;
        }
        return mb_substr($Name, 0, $MaxNameSize, 'UTF-8');
    }
}

/**
 * Anrufliste der FritzBox (Format siehe AVM „X_AVM-DE_OnTel“, Call List Content).
 */
class CallLog
{
    public const TypeIncoming = 1;
    public const TypeMissed = 2;
    public const TypeOutgoing = 3;
    public const TypeActiveIncoming = 9;
    public const TypeRejectedIncoming = 10;
    public const TypeActiveOutgoing = 11;
    /** Port eines Fax-Anrufs laut AVM. */
    public const PortFax = 5;

    /**
     * Liefert die Einträge der Anrufliste.
     * Je Eintrag: Type, Port (int), Name, Caller, Called, Number, Device, Date, Duration, Path (string).
     * Number ist die Rufnummer der Gegenstelle (abgehend: Called, sonst Caller). Bei der eigenen Nummer
     * (abgehend: Caller, sonst Called) entfällt das Präfix des Nummerntyps (z.B. `SIP: `).
     *
     * @param string $Xml Anrufliste als XML.
     *
     * @throws \Exception Bei ungültigem XML.
     *
     * @return array<int, array<string, int|string>>
     */
    public static function Parse(string $Xml): array
    {
        $Calls = [];
        $CallLog = XmlLoader::Load($Xml);
        foreach ($CallLog->Call as $Call) {
            $Type = (int) $Call->Type;
            $Caller = (string) $Call->Caller;
            $Called = (string) $Call->Called;
            $NumberType = (string) $Call->Numbertype;
            if ($Type == self::TypeOutgoing) {
                $Caller = self::RemoveNumberType($Caller, $NumberType);
                $Number = $Called;
            } else {
                $Called = self::RemoveNumberType($Called, $NumberType);
                $Number = $Caller;
            }
            $Calls[] = [
                'Type'     => $Type,
                'Port'     => (int) $Call->Port,
                'Name'     => (string) $Call->Name,
                'Caller'   => $Caller,
                'Called'   => $Called,
                'Number'   => $Number,
                'Device'   => (string) $Call->Device,
                'Date'     => (string) $Call->Date,
                'Duration' => (string) $Call->Duration,
                'Path'     => (string) $Call->Path
            ];
        }
        return $Calls;
    }

    /**
     * Entfernt das Präfix des Nummerntyps (z.B. `SIP: 98765` mit Numbertype `sip` → `98765`).
     * Ohne Nummerntyp bleibt die Nummer unverändert.
     */
    private static function RemoveNumberType(string $Number, string $NumberType): string
    {
        if ($NumberType === '') {
            return $Number;
        }
        $Prefix = strtoupper($NumberType) . ': ';
        if (strpos($Number, $Prefix) === 0) {
            return substr($Number, strlen($Prefix));
        }
        return $Number;
    }
}
