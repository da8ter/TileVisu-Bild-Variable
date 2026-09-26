<?php

declare(strict_types=1);

class TileVisuImageVariableTile extends IPSModuleStrict
{
    // Bildtypen des Hintergrund-Mediums nach Dateiendung (wie bisher, Groß-/Kleinschreibung zählt)
    private const IMAGE_TYPES = [
        'bmp' => 'image/bmp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'png' => 'image/png', 'ico' => 'image/x-icon'
    ];
    // Nativer Hook /hook/imagevariabletile/<InstanzID> für das Hintergrundbild
    private const IMAGE_HOOK = 'imagevariabletile';

    public function Create(): void
    {
        // Nie diese Zeile löschen!
        parent::Create();


        // Drei Eigenschaften für die dargestellten Zähler
        $this->RegisterPropertyInteger("bgImage", 0);
        $this->RegisterPropertyBoolean('BG_Off', true);
        $this->RegisterPropertyString('ImageURL', '');
        $this->RegisterPropertyFloat('Schriftgroesse', 1.0);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', 0x000000);
        $this->RegisterPropertyInteger('InfoMenueSchriftfarbe', 0xFFFFFF);
        $this->RegisterPropertyInteger('Variable', 0);
        $this->RegisterPropertyFloat('VariableSchriftgroesse', 1.0);
        $this->RegisterPropertyString('VariableAltName', '');
        $this->RegisterPropertyBoolean('VariableNameSwitch', true);
        $this->RegisterPropertyBoolean('VariableIconSwitch', true);
        $this->RegisterPropertyBoolean('VariableVarIconSwitch', false);
        $this->RegisterPropertyBoolean('VariableAssoSwitch', true);
        $this->RegisterPropertyInteger('Sekunden', 3600);
        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);

        // Hintergrundbild über einen eigenen Hook statt als Data-URI in jedem Kacheldokument: der Browser
        // cacht es unter versionierter Adresse, das Dokument bleibt klein. Ohne Hook wie bisher eingebettet.
        $this->RegisterAttributeString('ImageHookToken', '');
        $this->SetBuffer('ImageHook', $this->RegisterHook(self::IMAGE_HOOK . '/' . $this->InstanceID) ? '1' : '');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Kein Heavy Work vor KR_READY: Referenzen, Nachrichten und Fremdobjekt-Zugriffe
        // erst, wenn der Kernel bereit ist. MessageSink holt ApplyChanges dann nach.
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->EnsureImageHookToken();

        //Referenzen Registrieren
        $ids = [
            $this->ReadPropertyInteger('bgImage'),
            $this->ReadPropertyInteger('Variable')
        ];
        $refs = $this->GetReferenceList();
            foreach($refs as $ref) {
                $this->UnregisterReference($ref);
            }
            foreach ($ids as $id) {
                // 0 = nicht konfiguriert
                if ($id > 0) {
                    $this->RegisterReference($id);
                }
            }


        // Aktualisiere registrierte Nachrichten
        foreach ($this->GetMessageList() as $senderID => $messageIDs)
        {
            foreach ($messageIDs as $messageID)
            {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }


        foreach (['bgImage', 'Variable'] as $VariableProperty)        {
            // Absender 0 hiesse: Nachrichten ALLER Objekte. Nicht konfigurierte Eigenschaften überspringen.
            $id = $this->ReadPropertyInteger($VariableProperty);
            if ($id > 0) {
                $this->RegisterMessage($id, VM_UPDATE);
                $this->RegisterMessage($id, MM_UPDATE);
            }
        }

        // Schicke eine komplette Update-Nachricht an die Darstellung, da sich ja Parameter geändert haben können.
        // Die Prüfwerte der zuletzt gesendeten Nachrichten gelten danach nicht mehr.
        $this->SetBuffer('UpdateHashes', '');
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
            return;
        }

        // Symcon meldet jede Aktualisierung einer Variable, auch ohne neuen Wert: dann bleibt die Kachel, wie sie ist
        if ($Message === VM_UPDATE && !self::ValueChanged($Data)) {
            return;
        }

        foreach (['bgImage', 'Variable'] as $index => $VariableProperty)
        {
            if ($SenderID === $this->ReadPropertyInteger($VariableProperty))
            {
                

                switch ($Message)
                {
                    case VM_UPDATE:

                        // Teile der HTML-Darstellung den neuen Wert mit. Damit dieser korrekt formatiert ist, holen wir uns den von der Variablen via GetValueFormatted
                        $messages = [$this->EncodeJSON([$VariableProperty => GetValueFormatted($this->ReadPropertyInteger($VariableProperty))])];

                        //Icon und Farbe abrufen
                            $result = [];
                            //Farbe abrufen
                            $result[$VariableProperty . 'Color'] = $this->GetColor($this->ReadPropertyInteger($VariableProperty));

                            if($VariableProperty != 'bgImage')
                            {
                                if ($this->ReadPropertyBoolean($VariableProperty . 'NameSwitch')) $result[$VariableProperty . 'name'] = IPS_GetName($this->ReadPropertyInteger($VariableProperty));
                                if ($this->ReadPropertyBoolean($VariableProperty . 'IconSwitch') && $this->GetIcon($this->ReadPropertyInteger($VariableProperty), $this->ReadPropertyBoolean($VariableProperty . 'VarIconSwitch')) !== "Transparent") {
                                   $result[$VariableProperty .'icon'] = $this->GetIcon($this->ReadPropertyInteger($VariableProperty), $this->ReadPropertyBoolean($VariableProperty . 'VarIconSwitch'));
                                }
                                if ($this->ReadPropertyBoolean($VariableProperty . 'AssoSwitch')) $result[$VariableProperty . 'asso'] = $this->CheckAndGetValueFormatted($VariableProperty);
                                $result[$VariableProperty .'AltName'] =  $this->ReadPropertyString($VariableProperty .'AltName');
                            }

                            $messages[] = $this->EncodeJSON($result);

                            // Wert und Details gehen weiter als Paar hinaus, aber nicht noch einmal unverändert
                            $this->SendUpdateIfChanged($VariableProperty, ...$messages);

                            break; // Beende die Schleife, da der passende Wert gefunden wurde


                    case MM_UPDATE: //Medienobject wird auf aktualisierung überwacht und aktualisiert

                            // Neuer Inhalt ergibt eine neue Hook-Adresse (Version = Hash des Inhalts)
                            $result = $VariableProperty == 'bgImage' ? $this->GetBackground() : [];
                            // Nicht unterstütztes Medium: nichts zu melden (vorher ein wirkungsloses "null")
                            if ($result !== []) {
                                $this->UpdateVisualizationValue($this->EncodeJSON($result));
                            }

                            
                            break; // Beende die Schleife, da der passende Wert gefunden wurde
                }
            }
        }
    }



     
    public function GetVisualizationTile(): string
    {
        // Erstaufbau: die Kachel bekommt den vollen Stand, danach geht jede Wertänderung wieder hinaus
        $this->SetBuffer('UpdateHashes', '');

        // Füge ein Skript hinzu, um beim Laden, analog zu Änderungen bei Laufzeit, die Werte zu setzen
        // (doppelt kodiert: der JSON-Text wird zum escapten JS-Stringliteral)
        $initialHandling = '<script>handleMessage(' . $this->EncodeJSON($this->GetFullUpdateMessage()) . ')</script>';

        // Füge statisches HTML aus Datei hinzu
        $module = file_get_contents(__DIR__ . '/module.html');
        if ($module === false) {
            $this->LogMessage('module.html could not be loaded', KL_ERROR);
            $module = '';
        }


        $secondsvalue = $this->ReadPropertyInteger('Sekunden') * 1000;


        $seconds = '<script type="text/javascript">';
        $seconds .= 'var seconds = ' . $secondsvalue . ';';
        $seconds .= '</script>';




        // Gebe alles zurück.
        // Wichtig: $initialHandling nach hinten, da die Funktion handleMessage erst im HTML definiert wird
        return $seconds . $module . $initialHandling;
    }



    // Generiere eine Nachricht, die alle Elemente in der HTML-Darstellung aktualisiert
    private function GetFullUpdateMessage(): string {

        $result = [];
    
            $VariableID = $this->ReadPropertyInteger("Variable");
            if (IPS_VariableExists($VariableID)) {
                $prefix = "Variable";
                $result[$prefix] = $this->CheckAndGetValueFormatted("Variable");
                $result[$prefix . 'color'] = $this->GetColor($VariableID);
        
                if ($this->ReadPropertyBoolean("VariableNameSwitch")) {
                    $result[$prefix . 'name'] = IPS_GetName($VariableID);
                }
        
                $iconSwitch = $this->ReadPropertyBoolean("VariableIconSwitch");
                $varIconSwitch = $this->ReadPropertyBoolean("VariableVarIconSwitch");
                if ($iconSwitch) {
                    $icon = $this->GetIcon($VariableID, $varIconSwitch);
                    if ($icon !== "Transparent") {
                        $result[$prefix . 'icon'] = $icon;
                    }
                }
        
                if ($this->ReadPropertyBoolean("VariableAssoSwitch")) {
                    $result[$prefix . 'asso'] = $this->CheckAndGetValueFormatted("Variable");
                }
            }

        
            $result['hintergrundfarbe'] =  '#' . sprintf('%06X', $this->ReadPropertyInteger('Kachelhintergrundfarbe'));
            $result['infomenueschriftfarbe'] =  '#' . sprintf('%06X', $this->ReadPropertyInteger('InfoMenueSchriftfarbe'));
            $result['schriftgroesse'] =  $this->ReadPropertyFloat('Schriftgroesse');
            $result['transparenz'] =  $this->ReadPropertyFloat('Bildtransparenz');
            $result['variablealtname'] =  $this->ReadPropertyString('VariableAltName');
            

            
            // Hintergrund zuletzt, wie bisher: bgimage oder imageurl
            $result += $this->GetBackground();

        return $this->EncodeJSON($result);
    }

    // Hintergrund der Kachel, Vorrang wie bisher: eigenes Medienobjekt, sonst Standardbild (BG_Off),
    // sonst Bild-URL. Ein Medium, das kein Bild ist oder einen anderen Dateityp hat, zeigt nichts.
    // Mit $references wird aus der Data-URI die Hook-Adresse, sobald der Hook bereitsteht.
    private function GetBackground(bool $references = true): array
    {
        $imageID = $this->ReadPropertyInteger('bgImage');
        if (IPS_MediaExists($imageID)) {
            $image = IPS_GetMedia($imageID);
            $imageFile = explode('.', $image['MediaFile']);
            $mime = self::IMAGE_TYPES[end($imageFile)] ?? '';
            if ($image['MediaType'] !== MEDIATYPE_IMAGE || $mime === '') {
                return [];
            }
            // IPS_GetMediaContent liefert den Inhalt bereits base64-kodiert
            $dataUri = 'data:' . $mime . ';base64,' . IPS_GetMediaContent($imageID);
        } elseif ($this->ReadPropertyBoolean('BG_Off')) {
            // Standardbild nur lesen und kodieren, wenn es auch angezeigt wird
            $dataUri = 'data:image/png;base64,' . base64_encode((string) file_get_contents(__DIR__ . '/../imgs/kachelhintergrund1.png'));
        } else {
            return ['imageurl' => $this->ReadPropertyString('ImageURL')];
        }
        return ['bgimage' => $references ? $this->ImageReference('bgimage', $dataUri) : $dataUri];
    }

    // Hook-Adresse statt Data-URI, sobald der Hook in diesem Kernel-Lauf registriert ist. Die Version (Hash
    // des Inhalts) steht in der Adresse: neuer Inhalt ergibt eine neue Adresse, der Browser darf lange cachen.
    // Was die Ausgabegrenze eines Hooks sprengen würde, bleibt eingebettet wie bisher.
    private function ImageReference(string $key, string $dataUri): string
    {
        $token = $this->ImageHookActive() ? $this->ReadAttributeString('ImageHookToken') : '';
        $comma = strpos($dataUri, ',');
        if ($token === '' || $comma === false || !str_starts_with($dataUri, 'data:')
            || intdiv((strlen($dataUri) - $comma - 1) * 3, 4) > $this->HookBodyLimit()) {
            return $dataUri;
        }
        return '/hook/' . self::IMAGE_HOOK . '/' . $this->InstanceID . '?k=' . rawurlencode($key)
            . '&v=' . substr(hash('sha256', $dataUri), 0, 16) . '&t=' . $token;
    }

    private function ImageHookActive(): bool
    {
        return $this->GetBuffer('ImageHook') === '1';
    }

    private function EnsureImageHookToken(): void
    {
        if ($this->ImageHookActive() && $this->ReadAttributeString('ImageHookToken') === '') {
            $this->WriteAttributeString('ImageHookToken', bin2hex(random_bytes(16)));
        }
    }

    // Größte Antwort, die Symcon unverändert ausliefert (ScriptOutputBufferLimit, ab Werk 1 MiB), mit Reserve.
    private function HookBodyLimit(): int
    {
        $limit = 1048576;
        try {
            $option = IPS_GetOption('ScriptOutputBufferLimit');
            if (is_numeric($option) && (int) $option > 0) {
                $limit = (int) $option;
            }
        } catch (Throwable $e) {
            $this->SendDebug('Image', 'ScriptOutputBufferLimit: ' . $e->getMessage(), 0);
        }
        return max(0, $limit - 1024);
    }

    // /hook/imagevariabletile/<ID>?k=bgimage&v=<Version>&t=<Token>: das Hintergrundbild des aktuellen Kachelstands.
    // Ohne gültiges Token keine Antwort; ausgeliefert wird nur der Bildschlüssel der Kachel.
    protected function ProcessHookData(): void
    {
        $token = $this->ImageHookActive() ? $this->ReadAttributeString('ImageHookToken') : '';
        $given = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : '';
        if ($token === '' || !hash_equals($token, $given)) {
            $this->SendStatus(403);
            return;
        }
        $key = isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '';
        $data = $key === 'bgimage' ? ($this->GetBackground(false)['bgimage'] ?? '') : '';
        $comma = strpos($data, ',');
        $mime = $comma === false ? '' : substr($data, 5, (int) strpos($data, ';') - 5);
        $bytes = $comma === false ? false : base64_decode(substr($data, $comma + 1), true);
        if (!str_starts_with($data, 'data:') || !in_array($mime, self::IMAGE_TYPES, true) || $bytes === false) {
            $this->SendStatus(404);
            return;
        }
        $version = substr(hash('sha256', $data), 0, 16);
        $current = isset($_GET['v']) && $_GET['v'] === $version;
        // Nur die passende Version darf lange gecacht werden; eine alte Adresse bekommt den neuen Inhalt ungecacht.
        $this->SendHeader('Cache-Control: ' . ($current ? 'private, max-age=31536000, immutable' : 'no-cache'));
        $this->SendHeader('ETag: "' . $version . '"');
        $this->SendHeader('X-Content-Type-Options: nosniff');
        if ($current && trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === '"' . $version . '"') {
            $this->SendStatus(304);
            return;
        }
        $this->SendHeader('Content-Type: ' . $mime);
        $this->SendHeader('Content-Length: ' . strlen($bytes));
        echo $bytes;
    }

    // Eigene Methoden für Kopfzeilen und Status, damit die Tests den Hook ohne Webserver prüfen können.
    protected function SendHeader(string $header): void
    {
        header($header);
    }

    protected function SendStatus(int $code): void
    {
        http_response_code($code);
    }

    // json_encode liefert bei ungültigem UTF-8 false; die Darstellung bekommt dann ein leeres Update statt eines Typfehlers.
    private function EncodeJSON(mixed $data): string
    {
        $json = json_encode($data);
        if ($json === false) {
            $this->SendDebug('JSON', json_last_error_msg(), 0);
            return '{}';
        }
        return $json;
    }

    // Symcon meldet mit VM_UPDATE jede Aktualisierung; $Data[1] sagt, ob sich der Wert geändert hat.
    // Fehlt die Angabe (anderes Format), gilt sie als Änderung: lieber senden als eine verschlucken.
    private static function ValueChanged(array $Data): bool
    {
        return !isset($Data[1]) || (bool) $Data[1];
    }

    // Schickt die Nachrichten eines Schlüssels nur, wenn sie sich von den zuletzt dazu gesendeten unterscheiden.
    // Die Prüfwerte (md5) stehen im Puffer UpdateHashes; ApplyChanges und der Erstaufbau leeren ihn.
    private function SendUpdateIfChanged(string $key, string ...$messages): void
    {
        $hashes = json_decode($this->GetBuffer('UpdateHashes'), true);
        $hashes = is_array($hashes) ? $hashes : [];
        $hash = md5(serialize($messages));
        if (($hashes[$key] ?? null) === $hash) {
            return;
        }
        foreach ($messages as $message) {
            $this->UpdateVisualizationValue($message);
        }
        $hashes[$key] = $hash;
        $this->SetBuffer('UpdateHashes', (string) json_encode($hashes));
    }

    private function CheckAndGetValueFormatted(string $property): string|false {
        $id = $this->ReadPropertyInteger($property);
        if (IPS_VariableExists($id)) {
            return GetValueFormatted($id);
        }
        return false;
    }


    private function GetColor(int $id): string {
        $variable = IPS_GetVariable($id);
        $Value = GetValue($id);
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];

        if ($profile && IPS_VariableProfileExists($profile)) {
            $p = IPS_GetVariableProfile($profile);
            
            foreach ($p['Associations'] as $association) {
                if (isset($association['Value'], $association['Color']) && $association['Value'] == $Value) {
                    return $association['Color'] === -1 ? "" : sprintf('%06X', $association['Color']);
                    
                }
            }
        }
        return "";
    }


    
    private function GetIcon(int $id, bool $varicon): string {
        $variable = IPS_GetVariable($id);
        $Value = GetValue($id);
        $icon = "";
        //Abfragen ob das Variablen-Icon oder das Profil-Icon verwendet werden soll
        if($varicon == true){
        $object = IPS_GetObject($id);
            if($object['ObjectIcon'] != ""){
                $icon = $object['ObjectIcon'];
            }
            else {
                $icon = "Transparent";
            }
        }
        else {
        // Profil-Icon abrufen
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        $icon = "";

        if ($profile && IPS_VariableProfileExists($profile)) {
            $p = IPS_GetVariableProfile($profile);

            foreach ($p['Associations'] as $association) {
                if (isset($association['Value']) && $association['Icon'] != "" && $association['Value'] == $Value) {
                    $icon = $association['Icon'];
                    break;
                }
            }

            if ($icon == "" && isset($p['Icon']) && $p['Icon'] != "") {
                $icon = $p['Icon'];
            }

            if ($icon == "") {
                $icon = "Transparent";
            }
        }
        else {
            $icon = "Transparent";
        }
        
        }
        return $icon;
    }

}
