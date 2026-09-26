<?php

declare(strict_types=1);

// SDK-Attrappe für die Bild+Variable-Kachel. Verbindet sich nie mit einem laufenden Symcon.
// Signaturen wie IPSModuleStrict ab Symcon 8.1 (SymconStubs, Handbuch: bool RegisterHook(string)):
// unter strict_types wirft jeder lockere Aufruf (int statt bool, false statt string) einen TypeError.
const KR_INIT = 10102, KR_READY = 10103, IPS_KERNELSTARTED = 10001, VM_UPDATE = 10603, MM_UPDATE = 10905,
    MEDIATYPE_IMAGE = 1, MEDIATYPE_SOUND = 2, KL_ERROR = 10205;

// Gemeinsamer Unterbau der beiden Basisklassen. Unbekannte Properties und Attribute werfen,
// damit Tippfehler nicht still durchrutschen (Symcon selbst warnt nur).
trait SdkDouble
{
    public int $InstanceID;
    public array $properties = [], $attributes = [], $buffers = [], $messages = [], $references = [];
    public array $updates = [], $hooks = [], $logs = [];

    public function __construct(int $InstanceID = 12345)
    {
        $this->InstanceID = $InstanceID;
    }

    protected function RegisterPropertyBoolean(string $Name, bool $DefaultValue): bool { return $this->sdkStore('properties', $Name, $DefaultValue); }
    protected function RegisterPropertyInteger(string $Name, int $DefaultValue): bool { return $this->sdkStore('properties', $Name, $DefaultValue); }
    protected function RegisterPropertyFloat(string $Name, float $DefaultValue): bool { return $this->sdkStore('properties', $Name, $DefaultValue); }
    protected function RegisterPropertyString(string $Name, string $DefaultValue): bool { return $this->sdkStore('properties', $Name, $DefaultValue); }
    protected function ReadPropertyBoolean(string $Name): bool { return $this->sdkRead('properties', $Name); }
    protected function ReadPropertyInteger(string $Name): int { return $this->sdkRead('properties', $Name); }
    protected function ReadPropertyFloat(string $Name): float { return $this->sdkRead('properties', $Name); }
    protected function ReadPropertyString(string $Name): string { return $this->sdkRead('properties', $Name); }
    protected function RegisterAttributeString(string $Name, string $DefaultValue): bool { return $this->sdkStore('attributes', $Name, $DefaultValue); }
    protected function ReadAttributeString(string $Name): string { return $this->sdkRead('attributes', $Name); }
    protected function WriteAttributeString(string $Name, string $Value): bool
    {
        $this->sdkRead('attributes', $Name);
        return $this->sdkStore('attributes', $Name, $Value);
    }
    protected function SetBuffer(string $Name, string $Data): bool { $this->buffers[$Name] = $Data; return true; }
    protected function GetBuffer(string $Name): string { return $this->buffers[$Name] ?? ''; }
    // Nativer, flüchtiger Hook; ohne $hookAvailable gilt er als nicht registriert.
    protected function RegisterHook(string $HookPath): bool { $this->hooks[] = $HookPath; return $GLOBALS['hookAvailable']; }
    protected function SetVisualizationType(int $Type): void {}
    protected function UpdateVisualizationValue(string $Value): bool { $this->updates[] = $Value; return true; }
    public function GetReferenceList(): array { return array_keys($this->references); }
    protected function RegisterReference(int $ID): bool { $this->references[$ID] = true; return true; }
    protected function UnregisterReference(int $ID): bool { unset($this->references[$ID]); return true; }
    protected function GetMessageList(): array { return $this->messages; }
    protected function RegisterMessage(int $SenderID, int $Message): bool
    {
        if (!in_array($Message, $this->messages[$SenderID] ?? [], true)) {
            $this->messages[$SenderID][] = $Message;
        }
        return true;
    }
    protected function UnregisterMessage(int $SenderID, int $Message): bool
    {
        $this->messages[$SenderID] = array_values(array_diff($this->messages[$SenderID] ?? [], [$Message]));
        if ($this->messages[$SenderID] === []) {
            unset($this->messages[$SenderID]);
        }
        return true;
    }
    protected function LogMessage(string $Message, int $Type): bool { $this->logs[] = $Message; return true; }
    protected function SendDebug(string $Message, string $Data, int $Format): bool { return true; }

    private function sdkStore(string $store, string $name, mixed $value): bool
    {
        $this->{$store}[$name] = $value;
        return true;
    }

    private function sdkRead(string $store, string $name): mixed
    {
        if (!array_key_exists($name, $this->{$store})) {
            throw new RuntimeException('Not registered (' . $store . '): ' . $name);
        }
        return $this->{$store}[$name];
    }
}

class IPSModuleStrict
{
    use SdkDouble;

    public function Create(): void {}
    public function ApplyChanges(): void {}
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void {}
    public function GetVisualizationTile(): string { return ''; }
    protected function ProcessHookData(): void {}
}

// Nur für die Gegenprobe gegen den Stand vor der Umstellung: untypisierte Basisklasse wie IPSModule.
class IPSModule
{
    use SdkDouble;

    public function Create() {}
    public function ApplyChanges() {}
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {}
    public function GetVisualizationTile() { return ''; }
}

// Kernel-Zustand der Attrappe
$runlevel = KR_READY;
$hookAvailable = false;
$options = ['ScriptOutputBufferLimit' => 1048576];
$variables = $media = $profiles = [];

function IPS_GetKernelRunlevel(): int { return $GLOBALS['runlevel']; }
function IPS_GetOption(string $Option): mixed
{
    return $GLOBALS['options'][$Option] ?? throw new RuntimeException('Unknown option ' . $Option);
}
function IPS_VariableExists(int $VariableID): bool { return isset($GLOBALS['variables'][$VariableID]); }
function IPS_MediaExists(int $MediaID): bool { return isset($GLOBALS['media'][$MediaID]); }
function IPS_GetVariable(int $VariableID): array { return fixture('variables', $VariableID); }
function IPS_GetObject(int $ID): array { return fixture('variables', $ID); }
function IPS_GetName(int $ID): string { return fixture('variables', $ID)['ObjectName']; }
function GetValue(int $VariableID): mixed { return fixture('variables', $VariableID)['value']; }
function GetValueFormatted(int $VariableID): string { return fixture('variables', $VariableID)['formatted']; }
function IPS_GetMedia(int $MediaID): array { return fixture('media', $MediaID); }
function IPS_GetMediaContent(int $MediaID): string { return fixture('media', $MediaID)['content']; }
function IPS_VariableProfileExists(string $ProfileName): bool { return isset($GLOBALS['profiles'][$ProfileName]); }
function IPS_GetVariableProfile(string $ProfileName): array
{
    return $GLOBALS['profiles'][$ProfileName] ?? throw new RuntimeException('Unknown profile ' . $ProfileName);
}
function fixture(string $kind, int $id): array
{
    return $GLOBALS[$kind][$id] ?? throw new RuntimeException('Object #' . $id . ' does not exist');
}

// Testobjekte anlegen
function variable(int $id, mixed $value, string $formatted, array $extra = []): void
{
    $GLOBALS['variables'][$id] = array_replace([
        'value' => $value, 'formatted' => $formatted, 'ObjectName' => 'Variable ' . $id, 'ObjectIcon' => '',
        'VariableProfile' => '', 'VariableCustomProfile' => '',
    ], $extra);
}
// Symcon liefert Medieninhalte Base64-kodiert.
function media(int $id, string $file, string $bytes, int $type = MEDIATYPE_IMAGE): void
{
    $GLOBALS['media'][$id] = ['MediaType' => $type, 'MediaFile' => $file, 'content' => base64_encode($bytes)];
}
function profile(string $name, array $associations, string $icon = ''): void
{
    $GLOBALS['profiles'][$name] = ['Associations' => $associations, 'Icon' => $icon];
}

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}
function latest(object $module): array
{
    return json_decode((string) end($module->updates), true, 512, JSON_THROW_ON_ERROR);
}
// Anfangszustand, den GetVisualizationTile per handleMessage(...) in die Kachel schreibt.
function snapshot(object $module): array
{
    $html = $module->GetVisualizationTile();
    $prefix = '<script>handleMessage(';
    $start = strrpos($html, $prefix);
    if ($start === false || !str_ends_with($html, ')</script>')) {
        throw new RuntimeException('Missing initial state');
    }
    $literal = substr($html, $start + strlen($prefix), -strlen(')</script>'));
    return json_decode(json_decode($literal, true, 512, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
}

// IMGV_MODULE wählt eine andere Fassung der Kachel (Gegenprobe, Kopie ohne Standardbild).
require getenv('IMGV_MODULE') ?: __DIR__ . '/../ImageVariable/module.php';
