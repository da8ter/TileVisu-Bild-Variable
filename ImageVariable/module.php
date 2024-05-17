<?php
class TileVisuImageVariableTile extends IPSModule
{
    public function Create()
    {
        // Nie diese Zeile löschen!
        parent::Create();


        // Drei Eigenschaften für die dargestellten Zähler
        $this->RegisterPropertyInteger("bgImage", 0);
        $this->RegisterPropertyBoolean('BG_Off', 1);
        $this->RegisterPropertyString('ImageURL', '');
        $this->RegisterPropertyFloat('Schriftgroesse', 1);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', 0x000000);
        $this->RegisterPropertyInteger('InfoMenueSchriftfarbe', 0xFFFFFF);
        $this->RegisterPropertyInteger('Variable', 0);
        $this->RegisterPropertyFloat('VariableSchriftgroesse', 1);
        $this->RegisterPropertyString('VariableAltName', '');
        $this->RegisterPropertyBoolean('VariableNameSwitch', 1);
        $this->RegisterPropertyBoolean('VariableIconSwitch', 1);
        $this->RegisterPropertyBoolean('VariableVarIconSwitch', 0);
        $this->RegisterPropertyBoolean('VariableAssoSwitch', 1);
        $this->RegisterPropertyInteger('Sekunden', 3600);
        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();


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
                if ($id !== '') {
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
            $this->RegisterMessage($this->ReadPropertyInteger($VariableProperty), VM_UPDATE);
        }

        // Schicke eine komplette Update-Nachricht an die Darstellung, da sich ja Parameter geändert haben können
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {

        foreach (['bgImage', 'Variable'] as $index => $VariableProperty)
        {
            if ($SenderID === $this->ReadPropertyInteger($VariableProperty))
            {
                

                switch ($Message)
                {
                    case VM_UPDATE:
                        
                        // Teile der HTML-Darstellung den neuen Wert mit. Damit dieser korrekt formatiert ist, holen wir uns den von der Variablen via GetValueFormatted
                        $this->UpdateVisualizationValue(json_encode([$VariableProperty => GetValueFormatted($this->ReadPropertyInteger($VariableProperty))]));
                        
                        //Icon und Farbe abrufen
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
                            if($VariableProperty == 'bgImage')
                            {
                                $imageID = $this->ReadPropertyInteger('bgImage');
                                if (IPS_MediaExists($imageID)) {
                                    $image = IPS_GetMedia($imageID);
                                    if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                                        $imageFile = explode('.', $image['MediaFile']);
                                        $imageContent = '';
                                        // Falls ja, ermittle den Anfang der src basierend auf dem Dateitypen
                                        switch (end($imageFile)) {
                                            case 'bmp':
                                                $imageContent = 'data:image/bmp;base64,';
                                                break;
                        
                                            case 'jpg':
                                            case 'jpeg':
                                                $imageContent = 'data:image/jpeg;base64,';
                                                break;
                        
                                            case 'gif':
                                                $imageContent = 'data:image/gif;base64,';
                                                break;
                        
                                            case 'png':
                                                $imageContent = 'data:image/png;base64,';
                                                break;
                        
                                            case 'ico':
                                                $imageContent = 'data:image/x-icon;base64,';
                                                break;
                                        }
                    
                                        // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                                        if ($imageContent) {
                                            // Hänge base64-codierten Inhalt des Bildes an
                                            $imageContent .= IPS_GetMediaContent($imageID);
                                            $result['bgimage'] = $imageContent;
                                            //$result['imageurl'] =  '';
                                        }
                    
                                    }
                                }
                                else{
                                    //Wenn kein Bild durch den User konfiguriert dann Standardhintergrundbild verwenden
                                    $imageContent = 'data:image/png;base64,';
                                    $imageContent .= base64_encode(file_get_contents(__DIR__ . '/../imgs/kachelhintergrund1.png'));
                    
                                    //Standardhintergrundbild nur verwenden wenn Schalter BG_Off = true
                                    if ($this->ReadPropertyBoolean('BG_Off')) {
                                        $result['bgimage'] = $imageContent;
                                        //$result['imageurl'] =  '';
                                    }
                                    else {
                                        $result['imageurl'] =  $this->ReadPropertyString('ImageURL');
                                    }
                                    
                                    
                                } 
                            }
                            $this->UpdateVisualizationValue(json_encode($result));

                            
                            break; // Beende die Schleife, da der passende Wert gefunden wurde

                }
            }
        }
    }



     
    public function GetVisualizationTile()
    {
        // Füge ein Skript hinzu, um beim Laden, analog zu Änderungen bei Laufzeit, die Werte zu setzen
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ')</script>';

        // Füge statisches HTML aus Datei hinzu
        $module = file_get_contents(__DIR__ . '/module.html');


        $secondsvalue = $this->ReadPropertyInteger('Sekunden') * 1000;


        $seconds = '<script type="text/javascript">';
        $seconds .= 'var seconds = ' . $secondsvalue . ';';
        $seconds .= '</script>';




        // Gebe alles zurück.
        // Wichtig: $initialHandling nach hinten, da die Funktion handleMessage erst im HTML definiert wird
        return $seconds . $module . $initialHandling;
    }



    // Generiere eine Nachricht, die alle Elemente in der HTML-Darstellung aktualisiert
    private function GetFullUpdateMessage() {

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
            

            
            // Prüfe vorweg, ob ein Bild ausgewählt wurde
            $imageID = $this->ReadPropertyInteger('bgImage');
            if (IPS_MediaExists($imageID)) {
                $image = IPS_GetMedia($imageID);
                if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                    $imageFile = explode('.', $image['MediaFile']);
                    $imageContent = '';
                    // Falls ja, ermittle den Anfang der src basierend auf dem Dateitypen
                    switch (end($imageFile)) {
                        case 'bmp':
                            $imageContent = 'data:image/bmp;base64,';
                            break;
    
                        case 'jpg':
                        case 'jpeg':
                            $imageContent = 'data:image/jpeg;base64,';
                            break;
    
                        case 'gif':
                            $imageContent = 'data:image/gif;base64,';
                            break;
    
                        case 'png':
                            $imageContent = 'data:image/png;base64,';
                            break;
    
                        case 'ico':
                            $imageContent = 'data:image/x-icon;base64,';
                            break;
                    }

                    // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                    if ($imageContent) {
                        // Hänge base64-codierten Inhalt des Bildes an
                        $imageContent .= IPS_GetMediaContent($imageID);
                        $result['bgimage'] = $imageContent;
                        //$result['imageurl'] =  '';
                    }

                }
            }
            else{
                //Wenn kein Bild durch den User konfiguriert dann Standardhintergrundbild verwenden
                $imageContent = 'data:image/png;base64,';
                $imageContent .= base64_encode(file_get_contents(__DIR__ . '/../imgs/kachelhintergrund1.png'));

                //Standardhintergrundbild nur verwenden wenn Schalter BG_Off = true
                if ($this->ReadPropertyBoolean('BG_Off')) {
                    $result['bgimage'] = $imageContent;
                    //$result['imageurl'] =  '';
                }
                else {
                    $result['imageurl'] =  $this->ReadPropertyString('ImageURL');
                }
                
                
            } 



        return json_encode($result);
    }
    private function CheckAndGetValueFormatted($property) {
        $id = $this->ReadPropertyInteger($property);
        if (IPS_VariableExists($id)) {
            return GetValueFormatted($id);
        }
        return false;
    }


    private function GetColor($id) {
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


    
    private function GetIcon($id, $varicon) {
        $variable = IPS_GetVariable($id);
        $Value = GetValue($id);
        $icon = "";
        //Abfragen ob das Variablen-Icon oder das Profil-Icon verwendet werden soll
        if($varicon == true){
        $icon = IPS_GetObject($id);
            if($icon['ObjectIcon'] != ""){
                $icon = $icon['ObjectIcon'];
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
?>