<?php

declare(strict_types=1);

namespace FritzBoxModul;

/**
 * @property string $WebHookSecret
 */
trait HTMLTable
{
    /**
     * Liefert den Header der HTML-Tabelle.
     *
     * @param array $Config Die Konfiguration der Tabelle
     *
     * @return string HTML-String
     */
    protected function GetTableHeader(array $Config_Table, array $Config_Columns, bool $JSActive)
    {
        $table = '';
        if ($JSActive) {
            // JS Rückkanal erzeugen
            $table .= '<script type="text/javascript" id="script' . $this->InstanceID . '">
function xhrGet' . $this->InstanceID . '(o)
{
    var HTTP = new XMLHttpRequest();
    HTTP.open(\'GET\',o.url,true);
    HTTP.send();
    HTTP.addEventListener(\'load\', function()
    {
        if (HTTP.status >= 200 && HTTP.status < 300)
        {
            if (HTTP.responseText !== \'OK\')
                sendError' . $this->InstanceID . '(HTTP.responseText);
        } else {
            sendError' . $this->InstanceID . '(HTTP.statusText);
        }
    });
}

function sendError' . $this->InstanceID . '(data)
{
var notify = document.getElementsByClassName("ipsNotifications")[0];
var newDiv = document.createElement("div");
newDiv.innerHTML =\'<div style="height:auto; visibility: hidden; overflow: hidden; transition: height 500ms ease-in 0s" class="ipsNotification"><div class="spacer"></div><div class="message icon error" onclick="document.getElementsByClassName(\\\'ipsNotifications\\\')[0].removeChild(this.parentNode);"><div class="ipsIconClose"></div><div class="content"><div class="title">Fehler</div><div class="text">\' + data + \'</div></div></div></div>\';
if (notify.childElementCount === 0)
	var thisDiv = notify.appendChild(newDiv.firstChild);
else
	var thisDiv = notify.insertBefore(newDiv.firstChild,notify.childNodes[0]);
var newheight = window.getComputedStyle(thisDiv, null)["height"];
thisDiv.style.height = "0px";
thisDiv.style.visibility = "visible";
function sleep (time) {
  return new Promise((resolve) => setTimeout(resolve, time));
}
sleep(10).then(() => {
	thisDiv.style.height = newheight;
});
}

</script>';
        }
        $Style = '';
        if (isset($Config_Table['active'])) {
            $Style .= '.isactive {' . $Config_Table['active'] . '}' . PHP_EOL;
        }
        if (isset($Config_Table['inactive'])) {
            $Style .= '.isinactive {' . $Config_Table['inactive'] . '}' . PHP_EOL;
        }
        if ($Style != '') {
            $table .= '<style>' . $Style . '</style>' . PHP_EOL;
        }
        // Kopf der Tabelle erzeugen
        $table .= '<table style="' . $Config_Table['<table>'] . '">' . PHP_EOL;
        $table .= '<colgroup>' . PHP_EOL;
        $colgroup = [];
        foreach ($Config_Columns as $Column) {
            if ($Column['show'] !== true) {
                continue;
            }
            $colgroup[$Column['index']] = '<col width="' . $Column['width'] . 'em" />' . PHP_EOL;
        }
        ksort($colgroup);
        $table .= implode('', $colgroup) . '</colgroup>' . PHP_EOL;
        $table .= '<thead style="' . $Config_Table['<thead>'] . '">' . PHP_EOL;
        $table .= '<tr>';
        $th = [];
        foreach ($Config_Columns as $Column) {
            if ($Column['show'] !== true) {
                continue;
            }
            $ThStyle = [];
            if ($Column['hrcolor'] >= 0) {
                $ThStyle[] = 'color:#' . substr('000000' . dechex($Column['hrcolor']), -6);
            }
            $ThStyle[] = 'text-align:' . $Column['hralign'];
            $ThStyle[] = $Column['hrstyle'];
            $th[$Column['index']] = '<th style="' . implode(';', $ThStyle) . '">' . $Column['name'] . '</th>';
        }
        ksort($th);
        $table .= implode('', $th) . '</tr>' . PHP_EOL;
        $table .= '</thead>' . PHP_EOL;
        $table .= '<tbody style="' . $Config_Table['<tbody>'] . '">' . PHP_EOL;
        return $table;
    }

    /**
     * Liefert den Inhalt der HTML-Box für ein Tabelle.
     *
     * @param array  $Data        Die Nutzdaten der Tabelle.
     * @param string $HookPrefix  Der Prefix des Webhook.
     * @param string $HookType    Ein String welcher als Parameter Type im Webhook übergeben wird.
     * @param string $HookId      Der Index aus dem Array $Data welcher die Nutzdaten (Parameter ID) des Webhook enthält.
     * @param int    $CurrentLine Die Aktuelle Zeile welche als Aktiv erzeugt werden soll.
     *
     * @return string Der HTML-String.
     */
    protected function GetTable(array $Data, string $HookPrefix = '', string $HookType = '', string $HookId = '', int $CurrentLine = -1)
    {
        $Config_Table = array_column(json_decode($this->ReadPropertyString('Table'), true), 'style', 'tag');
        $Config_Columns = json_decode($this->ReadPropertyString('Columns'), true);
        $Config_Rows = json_decode($this->ReadPropertyString('Rows'), true);
        $Config_Rows_BgColor = array_column($Config_Rows, 'bgcolor', 'row');
        $Config_Rows_Color = array_column($Config_Rows, 'color', 'row');
        $Config_Rows_Style = array_column($Config_Rows, 'style', 'row');
        if ($HookId != '') {
            $NewSecret = base64_encode(openssl_random_pseudo_bytes(12));
            $this->{'WebHookSecret' . $HookType} = $NewSecret;
        }
        $HTMLData = $this->GetTableHeader($Config_Table, $Config_Columns, $HookId !== '');
        $pos = 0;
        if (count($Data) > 0) {
            foreach ($Data as $Line) {
                /*                $Line['Position'] = $pos + 1;

                                if (array_key_exists('Duration', $Line)) {
                                    $Line['Duration'] = $this->ConvertSeconds($Line['Duration']);
                                } else {
                                    $Line['Duration'] = '---';
                                }

                                $Line['Play'] = ($Line['Position'] == $CurrentLine ? '<div class="iconMediumSpinner ipsIconArrowRight" style="width: 100%; background-position: center center;"></div>' : '');
                 */
                //$LineIndex = ($Line['Position'] == $CurrentLine ? 'active' : ($pos % 2 ? 'odd' : 'even'));
                $LineIndex = ($pos % 2 ? 'odd' : 'even');
                $TrStyle = [];
                if ($Config_Rows_BgColor[$LineIndex] >= 0) {
                    $TrStyle[] = 'background-color:#' . substr('000000' . dechex($Config_Rows_BgColor[$LineIndex]), -6);
                }
                if ($Config_Rows_Color[$LineIndex] >= 0) {
                    $TrStyle[] = 'color:#' . substr('000000' . dechex($Config_Rows_Color[$LineIndex]), -6);
                }
                $TdStyle[] = $Config_Rows_Style[$LineIndex];
                $HTMLData .= '<tr style="' . implode(';', $TrStyle) . '"';
                if ($HookId != '') {
                    $LineSecret = '&Secret=' . rawurlencode(base64_encode(sha1($NewSecret . '0' . $Line[$HookId], true)));
                    $HTMLData .= ' onclick="eval(document.getElementById(\'script' . $this->InstanceID . '\').innerHTML.toString()); window.xhrGet' . $this->InstanceID . '({ url: \'hook/' . $HookPrefix . $this->InstanceID . '?Type=' . $HookType . '&ID=' . ($HookId == 'Url' ? rawurlencode($Line[$HookId]) : $Line[$HookId]) . $LineSecret . '\' });"';
                }
                $HTMLData .= '>';
                $td = [];
                foreach ($Config_Columns as $Column) {
                    if ($Column['show'] !== true) {
                        continue;
                    }
                    if (!array_key_exists($Column['key'], $Line)) {
                        $Line[$Column['key']] = '';
                    }
                    $TdStyle = [];
                    $TdStyle[] = 'text-align:' . $Column['tdalign'];
                    $TdStyle[] = $Column['tdstyle'];

                    $td[$Column['index']] = '<td style="' . implode(';', $TdStyle) . '">' . (string) $Line[$Column['key']] . '</td>';
                }
                ksort($td);
                $HTMLData .= implode('', $td) . '</tr>';
                $HTMLData .= '</tr>' . PHP_EOL;
                $pos++;
            }
        }
        $HTMLData .= $this->GetTableFooter();
        return $HTMLData;
    }

    /**
     * Liefert den Footer der HTML-Tabelle.
     *
     * @return string HTML-String
     */
    protected function GetTableFooter()
    {
        $table = '</tbody>' . PHP_EOL;
        $table .= '</table>' . PHP_EOL;
        return $table;
    }
}
