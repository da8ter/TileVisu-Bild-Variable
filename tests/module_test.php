<?php

declare(strict_types=1);

// Regressionstests der Bild+Variable-Kachel ohne laufendes Symcon. Aufrufe: tests/README.md
require __DIR__ . '/bootstrap.php';

const ALTSTAND = '772a161'; // letzter Stand vor Module Strict und Bild-Hook
const VOR_FILTER = '3c6378d'; // letzter Stand, der jede Aktualisierung an die Kachel schickte

// Derselbe Ablauf für alte und neue Fassung: jede Ausgabe an die Kachel, in Reihenfolge.
function szenarien(): array
{
    global $variables, $media, $profiles;
    $variables = $media = $profiles = [];
    $out = [];
    $m = new TileVisuImageVariableTile(12345);
    $schritt = static function (string $label, callable $aktion, bool $kachel = false) use (&$out, $m): void {
        $vorher = count($m->updates);
        $aktion();
        foreach (array_slice($m->updates, $vorher) as $update) {
            // Der alte Stand meldete ein nicht unterstütztes Medium mit einem wirkungslosen "null".
            if ($update !== 'null') {
                $out[] = [$label . ' (update)', $update];
            }
        }
        if ($kachel) {
            $out[] = [$label . ' (tile)', $m->GetVisualizationTile()];
        }
    };
    $apply = static fn () => $m->ApplyChanges();
    $wert = static fn () => $m->MessageSink(0, 20000, VM_UPDATE, []);
    $medium = static fn () => $m->MessageSink(0, 30000, MM_UPDATE, []);

    $m->Create();
    $schritt('New with default background', $apply, true);

    profile('Temp', [
        ['Value' => 0, 'Name' => 'Kalt', 'Icon' => 'Snowflake', 'Color' => 0x0000FF],
        ['Value' => 21.5, 'Name' => 'Warm', 'Icon' => 'Flame', 'Color' => 0xFF8000],
        ['Value' => 30, 'Name' => 'Heiß', 'Icon' => '', 'Color' => -1],
    ], 'Temperature');
    variable(20000, 21.5, '21,5 °C', ['ObjectName' => 'Wohnzimmer', 'VariableProfile' => 'Temp']);
    $m->properties['Variable'] = 20000;
    $m->properties['VariableAltName'] = 'Wohnen';
    $m->properties['Kachelhintergrundfarbe'] = 0x123456;
    $m->properties['InfoMenueSchriftfarbe'] = 0xABCDEF;
    $m->properties['Schriftgroesse'] = 1.5;
    $m->properties['Bildtransparenz'] = 0.3;
    $m->properties['Sekunden'] = 60;
    $schritt('Variable with profile color and icon', $apply, true);
    $variables[20000]['value'] = 0;
    $variables[20000]['formatted'] = '0,0 °C';
    $schritt('Value change', $wert);
    $variables[20000]['value'] = 30;
    $variables[20000]['formatted'] = '30,0 °C';
    $schritt('Association without icon and color', $wert, true);
    $variables[20000]['value'] = 5.5;
    $variables[20000]['formatted'] = '</script><b>"Grüße"</b>';
    $schritt('No association, special characters', $wert, true);
    $m->properties['VariableVarIconSwitch'] = true;
    $variables[20000]['ObjectIcon'] = 'Sun';
    $schritt('Variable icon', $apply, true);
    $schritt('Variable icon in update', $wert);
    $variables[20000]['ObjectIcon'] = '';
    $schritt('Empty variable icon', $apply, true);
    $schritt('Empty variable icon in update', $wert);
    $m->properties['VariableVarIconSwitch'] = false;
    $variables[20000]['VariableProfile'] = '';
    $schritt('No profile', $apply, true);
    $schritt('No profile in update', $wert);
    $variables[20000]['VariableCustomProfile'] = 'Temp';
    $variables[20000]['value'] = 0;
    $schritt('Custom profile', $apply, true);
    $schritt('Custom profile in update', $wert);
    foreach (['VariableNameSwitch', 'VariableIconSwitch', 'VariableAssoSwitch'] as $schalter) {
        $m->properties[$schalter] = false;
        $schritt($schalter . ' off', $apply, true);
        $schritt($schalter . ' off in update', $wert);
    }
    $m->properties['VariableAltName'] = '';
    $schritt('No alternative name', $wert, true);

    $m->properties['bgImage'] = 30000;
    foreach (['bild.png', 'bild.jpg', 'bild.jpeg', 'bild.gif', 'bild.bmp', 'bild.ico', 'png',
                 'bild.JPG', 'bild.webp', 'bild.svg', 'ordner.x/bild'] as $datei) {
        media(30000, 'media/' . $datei, 'Inhalt ' . $datei);
        $schritt('Media ' . $datei, $apply, true);
        media(30000, 'media/' . $datei, 'Neuer Inhalt ' . $datei);
        $schritt('Media ' . $datei . ' changed', $medium);
    }
    media(30000, 'media/bild.png', '');
    $schritt('Empty image', $apply, true);
    media(30000, 'media/klang.png', 'kein Bild', MEDIATYPE_SOUND);
    $schritt('Media is no image', $apply, true);
    $schritt('Media is no image, changed', $medium);
    unset($media[30000]);
    $schritt('Media deleted', $apply, true);
    $m->properties['BG_Off'] = false;
    $m->properties['ImageURL'] = 'https://example.com/cam.jpg?a=1&b=2';
    $schritt('Image URL instead of default background', $apply, true);
    $m->properties['ImageURL'] = '';
    $schritt('Neither default background nor URL', $apply, true);
    unset($variables[20000]);
    $schritt('Variable deleted', $apply, true);
    return $out;
}

// VM_UPDATE nur bei echter Wertänderung: Zeilen [Bezeichnung, erwartete Nachrichten, gesendete Nachrichten].
// Läuft auch gegen den Stand vor dem Filter; dort müssen genau die Zeilen fallen, die keine Nachricht erwarten.
function nachrichtenfilter(): array
{
    global $variables;
    variable(20100, 21.5, '21,5 °C', ['ObjectName' => 'Bad']);
    $m = new TileVisuImageVariableTile(12400);
    $m->Create();
    $m->properties['Variable'] = 20100;
    $m->ApplyChanges();
    $zeilen = [];
    // $Data wie von Symcon: [neuer Wert, geändert, alter Wert, Zeitstempel]
    $update = static function (string $label, int $erwartet, array $data) use ($m, &$zeilen): void {
        $vorher = count($m->updates);
        $m->MessageSink(0, 20100, VM_UPDATE, $data);
        $zeilen[] = [$label, $erwartet, count($m->updates) - $vorher];
    };
    $update('Update without a new value ($Data[1] false) sends nothing', 0, [21.5, false, 21.5, 1]);
    $variables[20100]['value'] = 22.0;
    $variables[20100]['formatted'] = '22,0 °C';
    $update('Changed value sends value and details', 2, [22.0, true, 21.5, 2]);
    $variables[20100]['value'] = 22.01; // formatiert wie zuvor
    $update('Identical messages are not sent again', 0, [22.01, true, 22.0, 3]);
    $m->ApplyChanges();
    $update('After ApplyChanges the same messages go out again', 2, [22.02, true, 22.01, 4]);
    $update('... but only once', 0, [22.03, true, 22.02, 5]);
    $m->GetVisualizationTile();
    $update('After the initial build of a tile they go out again', 2, [22.04, true, 22.03, 6]);
    $m->GetVisualizationTile();
    $update('Without $Data[1] (other format) the update is sent', 2, []);
    return $zeilen;
}

// Führt diese Datei mit einer anderen Fassung der Kachel in einem eigenen Prozess aus: [Exitcode, Ausgabe].
function unterprozess(string $moduleFile, string $mode): array
{
    $command = 'IMGV_MODULE=' . escapeshellarg($moduleFile) . ' ' . escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(__FILE__) . ' ' . escapeshellarg($mode) . ' 2>&1';
    exec($command, $lines, $code);
    return [$code, implode("\n", $lines)];
}

// Legt Dateien unter einem frischen Temp-Verzeichnis ab: [relativer Pfad => Inhalt].
function kopie(array $dateien): string
{
    $dir = sys_get_temp_dir() . '/imgv-test-' . bin2hex(random_bytes(6));
    foreach ($dateien as $pfad => $inhalt) {
        if (!is_dir(dirname($dir . '/' . $pfad))) {
            mkdir(dirname($dir . '/' . $pfad), 0700, true);
        }
        file_put_contents($dir . '/' . $pfad, $inhalt);
    }
    register_shutdown_function(static function () use ($dir, $dateien): void {
        foreach (array_keys($dateien) as $pfad) {
            if (is_file($dir . '/' . $pfad)) {
                unlink($dir . '/' . $pfad);
            }
        }
        foreach (['/ImageVariable', '/imgs', ''] as $sub) {
            if (is_dir($dir . $sub)) {
                rmdir($dir . $sub);
            }
        }
    });
    return $dir;
}

$mode = $argv[1] ?? '';
if ($mode === 'szenarien') {
    // Der alte Stand darf warnen (undefinierte Variable); gesammelt, damit die Ausgabe JSON bleibt.
    $warnungen = [];
    set_error_handler(static function (int $severity, string $message) use (&$warnungen): bool {
        $warnungen[] = $message;
        return true;
    });
    echo json_encode(['out' => szenarien(), 'warnungen' => $warnungen], JSON_THROW_ON_ERROR);
    exit(0);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if ($mode === 'ohne-standardbild') {
    // Kopie ohne imgs/: jeder Zugriff auf das Standardbild warnt und bricht hier ab.
    $hookAvailable = true;
    $m = new TileVisuImageVariableTile(12345);
    $m->Create();
    $m->properties['BG_Off'] = false;
    $m->properties['ImageURL'] = 'https://example.com/cam.jpg';
    $m->ApplyChanges();
    $m->GetVisualizationTile();
    media(30000, 'media/bild.png', 'eigenes Bild');
    $m->properties['BG_Off'] = true;
    $m->properties['bgImage'] = 30000;
    $m->ApplyChanges();
    $m->GetVisualizationTile();
    $m->MessageSink(0, 30000, MM_UPDATE, []);
    echo 'no access';
    unset($media[30000]);
    try {
        $m->ApplyChanges();
        echo ' / MISSING access when shown';
    } catch (ErrorException $e) {
        echo ' / access when shown';
    }
    exit(0);
}

if ($mode === 'nachrichtenfilter') {
    // Gegenprobe: dieselben Schritte mit einer anderen Fassung der Kachel
    echo json_encode(nachrichtenfilter(), JSON_THROW_ON_ERROR);
    exit(0);
}

// Kopfzeilen und Status abfangen, Hook ohne Webserver aufrufen.
class HookProbe extends TileVisuImageVariableTile
{
    public array $sent = [];
    public int $status = 200;
    protected function SendHeader(string $header): void { $this->sent[] = $header; }
    protected function SendStatus(int $code): void { $this->status = $code; }
    public function hook(array $get, ?string $ifNoneMatch = null): string
    {
        $_GET = $get;
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        if ($ifNoneMatch !== null) {
            $_SERVER['HTTP_IF_NONE_MATCH'] = $ifNoneMatch;
        }
        $this->sent = [];
        $this->status = 200;
        ob_start();
        $this->ProcessHookData();
        return (string) ob_get_clean();
    }
}
function query(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    return $q;
}
$png = (string) file_get_contents(__DIR__ . '/../imgs/kachelhintergrund1.png');
$defaultUri = 'data:image/png;base64,' . base64_encode($png);

// --- Ohne Hook: jede Ausgabe wie im Stand vor der Umstellung ----------------------------------
$neu = szenarien();
$repo = escapeshellarg(dirname(__DIR__));
exec('git -C ' . $repo . ' cat-file -e ' . escapeshellarg(ALTSTAND . '^{commit}') . ' 2>/dev/null', $unused, $gitCode);
if ($gitCode !== 0) {
    echo 'SKIP: ' . ALTSTAND . ' not available, no comparison with the previous state' . PHP_EOL;
} else {
    $dateien = [];
    foreach (['ImageVariable/module.php', 'imgs/kachelhintergrund1.png'] as $pfad) {
        $dateien[$pfad] = (string) shell_exec('git -C ' . $repo . ' show ' . escapeshellarg(ALTSTAND . ':' . $pfad));
    }
    // Verglichen wird das PHP-Verhalten: beide Seiten mit dem aktuellen module.html (der Icon-Baustein darin
    // wird unabhängig vom Modul weiterentwickelt).
    $dateien['ImageVariable/module.html'] = (string) file_get_contents(__DIR__ . '/../ImageVariable/module.html');
    [$code, $json] = unterprozess(kopie($dateien) . '/ImageVariable/module.php', 'szenarien');
    $alt = $code === 0 ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : ['out' => [], 'warnungen' => []];
    $erste = 'none';
    foreach ($neu as $i => $ausgabe) {
        if (($alt['out'][$i] ?? null) !== $ausgabe) {
            $erste = $ausgabe[0];
            break;
        }
    }
    check($code === 0 && count($neu) === count($alt['out']) && $erste === 'none',
        'Without hook all ' . count($neu) . ' tile outputs are byte-identical to ' . ALTSTAND . ' (first difference: ' . $erste . ')');
    check(in_array('Undefined variable $result', $alt['warnungen'], true),
        'Counter-check: ' . ALTSTAND . ' warned about the undefined $result in these scenarios, the new code does not');
}
check(count(array_filter($neu, static fn (array $a): bool => str_contains($a[1], '"bgimage":"data:image\/jpeg;base64,'))) > 0,
    'Scenarios cover embedded custom images');

// --- Module Strict: Kernelstart und Erstaufbau --------------------------------------------------
$hookAvailable = true;
$runlevel = KR_INIT;
$variables = $media = $profiles = [];
$m = new HookProbe(12345);
$m->Create();
check($m->properties['BG_Off'] === true && $m->properties['VariableVarIconSwitch'] === false
    && $m->properties['VariableNameSwitch'] === true && $m->properties['Schriftgroesse'] === 1.0
    && $m->properties['VariableSchriftgroesse'] === 1.0, 'Property defaults carry their real types');
$m->ApplyChanges();
check($m->updates === [] && $m->messages === [0 => [IPS_KERNELSTARTED]] && $m->references === [],
    'Before KR_READY ApplyChanges only waits for the kernel');
check($m->attributes['ImageHookToken'] === '' && $m->hook(['k' => 'bgimage', 't' => '']) === '' && $m->status === 403,
    'Before KR_READY there is no token and the hook answers 403');
$runlevel = KR_READY;
$m->MessageSink(0, 0, IPS_KERNELSTARTED, []);
check(count($m->updates) === 1 && $m->messages === [] && $m->references === [],
    'Kernel start runs ApplyChanges; unconfigured objects are neither watched (sender 0) nor referenced (0)');
$html = $m->GetVisualizationTile();
check(str_starts_with($html, '<script type="text/javascript">var seconds = 3600000;</script><!DOCTYPE html>'),
    'Tile document starts with the refresh interval and module.html');
check(array_keys(snapshot($m)) === ['hintergrundfarbe', 'infomenueschriftfarbe', 'schriftgroesse', 'transparenz',
    'variablealtname', 'bgimage'], 'Initial state of a new tile keeps its keys');

profile('Temp', [['Value' => 21.5, 'Name' => 'Warm', 'Icon' => 'Flame', 'Color' => 0xFF8000]], 'Temperature');
variable(20000, 21.5, '21,5 °C', ['ObjectName' => 'Wohnzimmer', 'VariableProfile' => 'Temp']);
media(30000, 'media/foto.jpg', "\xFF\xD8\xFF jpeg-bytes");
$m->properties['Variable'] = 20000;
$m->properties['bgImage'] = 30000;
$m->properties['VariableAltName'] = 'Wohnen';
$m->ApplyChanges();
check($m->references === [30000 => true, 20000 => true]
    && $m->messages === [30000 => [VM_UPDATE, MM_UPDATE], 20000 => [VM_UPDATE, MM_UPDATE]],
    'Configured variable and media are referenced and watched');
$voll = latest($m);
check(array_keys($voll) === ['Variable', 'Variablecolor', 'Variablename', 'Variableicon', 'Variableasso',
    'hintergrundfarbe', 'infomenueschriftfarbe', 'schriftgroesse', 'transparenz', 'variablealtname', 'bgimage'],
    'Full update keeps its keys and their order');
$vorher = count($m->updates);
$m->MessageSink(0, 20000, VM_UPDATE, []);
$delta = array_map(static fn (string $u): array => array_keys(json_decode($u, true)), array_slice($m->updates, $vorher));
check($delta === [['Variable'], ['VariableColor', 'Variablename', 'Variableicon', 'Variableasso', 'VariableAltName']],
    'Value change keeps its two messages and keys');

// --- Bild-Hook -----------------------------------------------------------------------------------
check($m->hooks === ['imagevariabletile/12345'], 'Hook is registered natively in Create');
$token = $m->attributes['ImageHookToken'];
check(strlen($token) === 32 && ctype_xdigit($token), 'Hook token is created after kernel start');
$muster = '~^/hook/imagevariabletile/12345\?k=bgimage&v=[0-9a-f]{16}&t=' . $token . '$~';
$first = $voll['bgimage'];
check(preg_match($muster, $first) === 1 && snapshot($m)['bgimage'] === $first,
    'Custom image is sent as versioned hook address (update and tile)');
$q = query($first);
check($m->hook($q) === "\xFF\xD8\xFF jpeg-bytes" && $m->status === 200, 'Hook delivers the media bytes');
check(in_array('Content-Type: image/jpeg', $m->sent, true) && in_array('Content-Length: 14', $m->sent, true),
    'Hook sends image type and length');
check(in_array('Cache-Control: private, max-age=31536000, immutable', $m->sent, true),
    'Current version may be cached long (private, immutable)');
check(in_array('ETag: "' . $q['v'] . '"', $m->sent, true) && in_array('X-Content-Type-Options: nosniff', $m->sent, true),
    'ETag is the version from the address, nosniff is set');
$body = $m->hook($q, '"' . $q['v'] . '"');
check($m->status === 304 && $body === '' && !in_array('Content-Type: image/jpeg', $m->sent, true),
    'Matching If-None-Match answers 304 without body');
$body = $m->hook(['k' => 'bgimage', 'v' => 'veraltet', 't' => $token], '"' . $q['v'] . '"');
check($m->status === 200 && $body === "\xFF\xD8\xFF jpeg-bytes" && in_array('Cache-Control: no-cache', $m->sent, true),
    'Outdated address gets the current content uncached');
foreach (['wrong' => ['t' => str_repeat('0', 32)], 'missing' => [], 'empty' => ['t' => ''], 'array' => ['t' => [$token]]] as $fall => $t) {
    $body = $m->hook(['k' => 'bgimage', 'v' => $q['v']] + $t);
    check($m->status === 403 && $body === '' && $m->sent === [], 'Token ' . $fall . ': 403 without content');
}
foreach (['imageurl', 'Variable', 'BGIMAGE', ''] as $key) {
    $body = $m->hook(['k' => $key, 't' => $token]);
    check($m->status === 404 && $body === '', 'Unknown image key "' . $key . '": 404');
}
check(!str_contains($m->GetVisualizationTile(), 'base64,'), 'Tile document carries no Base64 image');

media(30000, 'media/foto.jpg', 'new-bytes');
$m->MessageSink(0, 30000, MM_UPDATE, []);
$changed = latest($m)['bgimage'];
check(array_keys(latest($m)) === ['bgimage'] && $changed !== $first && preg_match($muster, $changed) === 1,
    'MM_UPDATE sends a new address for changed content');
check($m->hook(query($changed)) === 'new-bytes' && in_array('Cache-Control: private, max-age=31536000, immutable', $m->sent, true),
    'New address delivers the new content');
check($m->hook($q) === 'new-bytes' && in_array('Cache-Control: no-cache', $m->sent, true),
    'Previous address delivers the new content uncached');
$m->MessageSink(0, 30000, MM_UPDATE, []);
check(latest($m)['bgimage'] === $changed, 'Unchanged content keeps its address');
check(snapshot($m)['bgimage'] === $changed, 'Initial tile state uses the same address');

media(30000, 'media/foto.webp', 'webp');
$vorher = count($m->updates);
$m->MessageSink(0, 30000, MM_UPDATE, []);
check(count($m->updates) === $vorher && !isset(snapshot($m)['bgimage']), 'Unsupported media shows nothing and sends nothing');
$m->hook(['k' => 'bgimage', 't' => $token]);
check($m->status === 404, 'Hook answers 404 while no image is shown');

unset($media[30000]);
$m->ApplyChanges();
$state = snapshot($m);
check(preg_match($muster, $state['bgimage']) === 1 && $state['bgimage'] === latest($m)['bgimage'],
    'Default background is sent as hook address');
$body = $m->hook(query($state['bgimage']));
check($body === $png && in_array('Content-Type: image/png', $m->sent, true), 'Hook delivers the default background as PNG');
$eingebettet = strlen($m->GetVisualizationTile());

$m->properties['BG_Off'] = false;
$m->properties['ImageURL'] = 'https://example.com/cam.jpg';
$m->ApplyChanges();
check(latest($m)['imageurl'] === 'https://example.com/cam.jpg' && !isset(latest($m)['bgimage']),
    'Image URL is sent unchanged, without hook');
$m->hook(['k' => 'bgimage', 't' => $token]);
check($m->status === 404, 'Hook answers 404 for the image URL mode');
$m->properties['BG_Off'] = true;

// --- Ausgabegrenze: ScriptOutputBufferLimit - 1 KiB -------------------------------------------
$options['ScriptOutputBufferLimit'] = 4096;
media(30000, 'media/gross.png', str_repeat('x', 3072));
$m->ApplyChanges();
check(preg_match($muster, latest($m)['bgimage']) === 1, 'Image at the output limit uses the hook');
media(30000, 'media/gross.png', str_repeat('x', 3073));
$m->MessageSink(0, 30000, MM_UPDATE, []);
check(latest($m)['bgimage'] === 'data:image/png;base64,' . base64_encode(str_repeat('x', 3073)),
    'Image above the output limit stays embedded (update)');
check(snapshot($m)['bgimage'] === latest($m)['bgimage'], 'Image above the output limit stays embedded (tile)');
$options['ScriptOutputBufferLimit'] = 1048576;
unset($media[30000]);

// --- Rückfall ohne registrierten Hook ------------------------------------------------------------
$hookAvailable = false;
$n = new HookProbe(12346);
$n->Create();
$n->ApplyChanges();
check($n->hooks === ['imagevariabletile/12346'] && $n->buffers['ImageHook'] === '', 'Failed registration is remembered');
check(snapshot($n)['bgimage'] === $defaultUri && latest($n)['bgimage'] === $defaultUri,
    'Without registered hook the background stays embedded as before');
check($n->attributes['ImageHookToken'] === '', 'Without hook no token is created');
$n->hook(['k' => 'bgimage', 't' => '']);
check($n->status === 403, 'Without hook the endpoint answers nothing');
$n->attributes['ImageHookToken'] = str_repeat('a', 32);
$n->ApplyChanges();
$n->hook(['k' => 'bgimage', 't' => str_repeat('a', 32)]);
check(latest($n)['bgimage'] === $defaultUri && $n->status === 403,
    'A token from an earlier kernel run is not honoured while the hook is not registered');

// --- VM_UPDATE nur bei echter Wertänderung -------------------------------------------------------
$zeilen = nachrichtenfilter();
foreach ($zeilen as [$label, $erwartet, $ist]) {
    check($ist === $erwartet, $label . ' (' . $ist . ' messages)');
}
exec('git -C ' . $repo . ' cat-file -e ' . escapeshellarg(VOR_FILTER . '^{commit}') . ' 2>/dev/null', $unused, $gitCode);
if ($gitCode !== 0) {
    echo 'SKIP: ' . VOR_FILTER . ' not available, no counter-check of the message filter' . PHP_EOL;
} else {
    $dateien = [];
    foreach (['ImageVariable/module.php', 'ImageVariable/module.html', 'imgs/kachelhintergrund1.png'] as $pfad) {
        $dateien[$pfad] = (string) shell_exec('git -C ' . $repo . ' show ' . escapeshellarg(VOR_FILTER . ':' . $pfad));
    }
    [$code, $json] = unterprozess(kopie($dateien) . '/ImageVariable/module.php', 'nachrichtenfilter');
    $vorher = $code === 0 ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : [];
    $fallend = array_column(array_filter($vorher, static fn (array $z): bool => $z[1] !== $z[2]), 0);
    $still = array_column(array_filter($zeilen, static fn (array $z): bool => $z[1] === 0), 0);
    check($code === 0 && $still !== [] && $fallend === $still,
        'Counter-check: without the filter (' . VOR_FILTER . ') exactly the ' . count($still) . ' checks expecting no message fail');
}

// --- Standardbild nur lesen, wenn es angezeigt wird ----------------------------------------------
$ohneBild = kopie([
    'ImageVariable/module.php' => (string) file_get_contents(__DIR__ . '/../ImageVariable/module.php'),
    'ImageVariable/module.html' => (string) file_get_contents(__DIR__ . '/../ImageVariable/module.html'),
]);
[$code, $ausgabe] = unterprozess($ohneBild . '/ImageVariable/module.php', 'ohne-standardbild');
check($code === 0 && $ausgabe === 'no access / access when shown',
    'Default background is read only when it is shown (' . $ausgabe . ')');

// --- Gegenprobe der Attrappe: lockere Typen fallen unter strict_types auf ----------------------
$locker = kopie([
    'ImageVariable/module.php' => str_replace("RegisterPropertyBoolean('BG_Off', true)", "RegisterPropertyBoolean('BG_Off', 1)",
        (string) file_get_contents(__DIR__ . '/../ImageVariable/module.php')),
    'ImageVariable/module.html' => '',
]);
[$code, $ausgabe] = unterprozess($locker . '/ImageVariable/module.php', 'szenarien');
check($code !== 0 && str_contains($ausgabe, 'TypeError') && str_contains($ausgabe, 'must be of type bool, int given'),
    'Counter-check: the SDK double rejects RegisterPropertyBoolean(..., 1) under strict_types');

echo 'Tile document: ' . strlen($m->GetVisualizationTile()) . ' bytes with hook address, '
    . strlen($n->GetVisualizationTile()) . ' bytes with embedded default background' . PHP_EOL;
