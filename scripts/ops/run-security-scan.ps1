[CmdletBinding()]
param(
    [string]$OutputDirectory = '',
    [ValidateRange(1, 90)][int]$ActiveMinutes = 30,
    [switch]$ValidateOnly
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$repository = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$reportRoot = 'D:\2- Ather Portfolio Project Planing SaaS\جاهزية الإطلاق\L-12-تقرير'
$run = [guid]::NewGuid().ToString('N')
$project = 'ather_security_scan_' + $run.Substring(0, 24)
$label = 'dev.ather.security-scan'
$temporary = Join-Path ([IO.Path]::GetTempPath()) ('ather-security-' + $run)
$pins = [ordered]@{ zap='zaproxy/zap-stable:2.17.0'; trivy='aquasec/trivy:0.76.1'; composer='composer:2.8.12'; php='php:8.3.33-apache'; mysql='mysql:8.4.7'; caddy='caddy:2.10.2' }
$secrets = [Collections.Generic.List[string]]::new()
$ownedContainers = [Collections.Generic.List[string]]::new()
$ownedVolumes = [Collections.Generic.List[string]]::new()
$ownedNetworks = [Collections.Generic.List[string]]::new()
$image = 'ather-security-scan-' + $run + ':production'
$imageCreated = $false
$phase = 'preflight'
$assessments = [ordered]@{}

function Assert-Scan([bool]$Condition, [string]$Message) {
    if (!$Condition) { throw $Message }
}
function Safe-Text([string]$Value) {
    foreach ($secret in $script:secrets) { if ($secret.Length -ge 8) { $Value = $Value.Replace($secret, '[REDACTED]') } }
    $Value = $Value -replace '(?im)^(Cookie|Set-Cookie|Authorization|Proxy-Authorization):.*$', '$1: [REDACTED]'
    $Value = $Value -replace '(?i)(csrf_token|session_id|password|api[._-]?key|client_secret)(["''\s:=]+)[^\s"''<>;&]+', '$1$2[REDACTED]'
    return $Value -replace '(?i)\b[a-f0-9]{48,}\b', '[REDACTED]'
}
function Protect-ScanObject($Value) {
    if ($Value -is [Collections.IDictionary]) {
        $result = [ordered]@{}
        foreach ($key in $Value.Keys) {
            $result[$key] = if ($key -match '^(?i:cookie|set-cookie|authorization|token|csrf_token|session_id|password|secret|api[._-]?key|client_secret|requestHeader|responseHeader|requestBody|responseBody)$') { '[REDACTED]' } else { Protect-ScanObject $Value[$key] }
        }
        return ,$result
    }
    if ($Value -is [array]) { $items=@(foreach($item in $Value){Protect-ScanObject $item});return ,$items }
    if ($Value -is [string]) { return (Safe-Text $Value) }
    return $Value
}
function Write-Safe([string]$Path, [string]$Value) {
    if ([IO.Path]::GetExtension($Path) -eq '.json') {
        # Redact values before serializing: regex over serialized JSON can corrupt escapes.
        $parsed = ConvertFrom-Json -InputObject $Value -AsHashtable -Depth 100 -NoEnumerate
        $Value = ConvertTo-Json -InputObject (Protect-ScanObject $parsed) -Depth 100
    } else { $Value = Safe-Text $Value }
    [IO.File]::WriteAllText($Path, $Value, [Text.UTF8Encoding]::new($false))
}
function Random-Secret {
    $bytes = [byte[]]::new(32); [Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    $value = [Convert]::ToHexString($bytes).ToLowerInvariant(); $script:secrets.Add($value); return $value
}
function Invoke-ScanDocker([string[]]$Arguments, [int[]]$Allowed = @(0), [string]$Save = '') {
    # Never echo native arguments or raw errors: they may contain generated credentials.
    $output = & docker @Arguments 2>&1
    $code = $LASTEXITCODE
    $value = $output | Out-String
    if ($Save) { Write-Safe (Join-Path $script:OutputDirectory $Save) $value }
    if ($Allowed -notcontains $code) { Write-Safe (Join-Path $script:OutputDirectory 'native-failure.txt') $value; throw "Docker command failed during $script:phase (exit $code); no arguments or raw output emitted." }
    return $value.Trim()
}
function Invoke-ScanCompose([string[]]$Arguments) {
    Invoke-ScanDocker (@('compose','--project-name',$project,'--project-directory',$temporary,'--file',(Join-Path $temporary 'compose.json')) + $Arguments)
}
function Live-Inventory {
    @((Invoke-ScanDocker @('ps','--filter','name=^/portfolio_course-','--format','{{.Names}}|{{.Image}}|{{.ID}}')) -split "`r?`n" | Where-Object {$_} | Sort-Object)
}
function Assert-Owned([string]$Kind, [string]$Name) {
    Assert-Scan ($Name.StartsWith($project + '-') -or $Name -eq $image) 'Cleanup rejected an unexpected resource name.'
    $template = '{{ index .Labels "dev.ather.security-scan" }}'
    if ($Kind -eq 'container') { $template = '{{ index .Config.Labels "dev.ather.security-scan" }}' }
    if ($Kind -eq 'image') { $template = '{{ index .Config.Labels "dev.ather.security-scan" }}' }
    $owner = Invoke-ScanDocker @($Kind,'inspect','--format',$template,$Name)
    Assert-Scan ($owner -eq $run) 'Cleanup rejected a resource ownership label.'
}
function Wait-Container([string]$Name) {
    for ($attempt=0; $attempt -lt 120; $attempt++) {
        $status = Invoke-ScanDocker @('inspect','--format','{{.State.Running}}|{{.State.Health.Status}}',$Name)
        if ($status -eq 'true|healthy') { return }
        if ($status.StartsWith('false|')) { throw 'A disposable service exited during startup.' }
        Start-Sleep -Seconds 1
    }
    throw 'Disposable service did not become healthy.'
}
function Invoke-ScanPhp([string[]]$Arguments) {
    $result=Invoke-ScanCompose (@('exec','--no-TTY','--user','www-data','web','php') + $Arguments)
    $carrierPath=Join-Path $temporary 'session.json'
    if(Test-Path -LiteralPath $carrierPath){
        $carrier=Get-Content -LiteralPath $carrierPath -Raw | ConvertFrom-Json
        $secrets.Add($carrier.cookie);$secrets.Add(($carrier.cookie -split '=',2)[1]);$secrets.Add($carrier.csrf)
    }
    return $result
}
function Write-Utf8([string]$Name, [string]$Value) { [IO.File]::WriteAllText((Join-Path $temporary $Name), $Value, [Text.UTF8Encoding]::new($false)) }
function Invoke-Assessment([string]$Name, [scriptblock]$Action) {
    $script:phase = $Name
    Write-Output "SCAN_PHASE $Name"
    try { & $Action; $script:assessments[$Name] = 'completed' }
    catch {
        $script:assessments[$Name] = 'incomplete'
        Write-Safe (Join-Path $OutputDirectory "$Name-execution-failure.json") (@{phase=$script:phase;message=$_.Exception.Message} | ConvertTo-Json)
        Write-Output "SCAN_PHASE_INCOMPLETE $Name; private diagnostic written."
    }
}

$fixturePhp = @'
<?php
declare(strict_types=1);
require '/var/www/app/config/database.php';
require '/var/www/app/includes/owner_session.php';
require '/var/www/app/includes/csrf.php';
$db=getDatabaseConnection();
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='production'||getenv('ATHERCAR_TEST_MODE')!==false||!preg_match('/^ather_security_scan_[a-f0-9]{24}$/',(string)$db->query('SELECT DATABASE()')->fetchColumn())||!is_file('/.dockerenv')){exit(2);}
if((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==1||(int)$db->query('SELECT COUNT(*) FROM portfolios')->fetchColumn()!==1){exit(3);}
$user=$db->query('SELECT id,authz_version,oidc_subject FROM users')->fetch(PDO::FETCH_ASSOC);
if(!str_starts_with($user['oidc_subject'],'scan-owner-')){exit(4);}
$portfolio=(int)$db->query('SELECT id FROM portfolios')->fetchColumn();
$action=$argv[1]??'';
if($action==='seed'){
    $slug='scan-'.substr((string)getenv('DB_NAME'),-24);
    $db->prepare('UPDATE portfolios SET public_slug=?,is_published=1,published_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$slug,$portfolio]);
    $db->exec('DELETE FROM personal_info');
    $db->prepare("INSERT INTO personal_info(portfolio_id,full_name,professional_title,location,email,about_me) VALUES (?,'Synthetic Scan Owner','Synthetic Engineer','Synthetic City','owner@scan.invalid','Disposable security assessment fixture.')")->execute([$portfolio]);
    $db->exec("UPDATE projects SET title='Synthetic scan project',category='Testing',description='Disposable scanner fixture',github_url='',is_published=1");
    $project=(int)$db->query('SELECT MIN(id) FROM projects')->fetchColumn();
    file_put_contents('/tmp/security-scan/target.json',json_encode(['slug'=>$slug,'project'=>$project],JSON_THROW_ON_ERROR));
    exit;
}
if($action==='scheduled'){
    $db->prepare('UPDATE users SET deletion_requested_at=CURRENT_TIMESTAMP,deletion_due_at=CURRENT_TIMESTAMP + INTERVAL 30 DAY WHERE id=?')->execute([$user['id']]);
}
if(!in_array($action,['session','scheduled'],true)){exit(5);}
startOwnerSession();
establishVerifiedInternalUserSession((int)$user['id'],(int)$user['authz_version']);
recordOwnerReadinessObservation((int)$user['id'],(int)$user['authz_version'],true,true);
file_put_contents('/tmp/security-scan/session.json',json_encode(['cookie'=>session_name().'='.session_id(),'csrf'=>getCsrfToken()],JSON_THROW_ON_ERROR));
chmod('/tmp/security-scan/session.json',0600);
session_write_close();
'@
$scopeScript = @'
var Files = Java.type('java.nio.file.Files');
var Paths = Java.type('java.nio.file.Paths');
var URI = Java.type('org.apache.commons.httpclient.URI');
function sendingRequest(msg, initiator, helper) {
    var uri = msg.getRequestHeader().getURI();
    var path = String(uri.getPath());
    var outside = String(uri.getHost()) !== 'scan-target.invalid' || String(uri.getScheme()) !== 'https' || (uri.getPort() !== -1 && uri.getPort() !== 443);
    var sessionEnding = /^\/owner_(logout|switch_account|reauthenticate|login|register|oidc_callback|auth_retry)\.php/.test(path);
    var deletionPost = path === '/owner_account_deletion.php' && String(msg.getRequestHeader().getMethod()) !== 'GET';
    if (outside || sessionEnding || deletionPost) {
        // Rewrite rather than throw: a script error could disable the guard.
        msg.getRequestHeader().setURI(new URI('https://scan-target.invalid/__l12_scope_blocked__', false));
        msg.getRequestHeader().setMethod('GET');
        msg.setRequestBody(''); msg.getRequestHeader().setContentLength(0);
        msg.getRequestHeader().setHeader('Cookie', null);
        return;
    }
    if (path.indexOf('/owner') === 0 && Files.exists(Paths.get('/work/session.json'))) {
        var session = JSON.parse(String(Files.readString(Paths.get('/work/session.json'))));
        msg.getRequestHeader().setHeader('Cookie', session.cookie);
    }
}
function responseReceived(msg, initiator, helper) {}
'@

$scannerPython = @'
import sys,json,time,re,ssl,socket,threading
from pathlib import Path
from urllib.parse import urlencode
import requests
requests.packages.urllib3.disable_warnings()
work=Path('/work'); target='https://scan-target.invalid'; phase=sys.argv[1]; minutes=int(sys.argv[2])
key=re.search(r'^api.key=(.+)$',(work/'zap.properties').read_text(),re.M).group(1)
http=requests.Session();http.trust_env=False

def memory_monitor():
    while True:
        try:
            values={name:int(Path('/sys/fs/cgroup/'+file).read_text()) for name,file in [('peak_bytes','memory.peak'),('current_bytes','memory.current'),('limit_bytes','memory.max')]}
            values.update(heap='-Xmx2g',sample_interval_seconds=2)
            values['actual_heap_arguments']=[arg for arg in Path('/proc/1/cmdline').read_bytes().decode().split('\0') if arg.startswith('-Xmx')]
            values['memory_events']={line.split()[0]:int(line.split()[1]) for line in Path('/sys/fs/cgroup/memory.events').read_text().splitlines()}
            pending=work/'zap-memory.pending'
            pending.write_text(json.dumps(values),encoding='utf-8')
            pending.replace(work/'zap-memory.json')
        except (OSError,ValueError):pass
        time.sleep(2)
threading.Thread(target=memory_monitor,daemon=True).start()

def api(component,kind,name,**params):
    value=http.get(f'http://127.0.0.1:8090/JSON/{component}/{kind}/{name}/',params=params,headers={'X-ZAP-API-Key':key},timeout=90).json()
    if isinstance(value,dict) and 'code' in value:
        write('zap-api-failure.json',value)
        write('zap-script-state.json',http.get('http://127.0.0.1:8090/JSON/script/view/listScripts/',headers={'X-ZAP-API-Key':key},timeout=30).json())
        raise RuntimeError('ZAP API rejected '+component+'/'+name+': '+str(value['code']))
    return value

def redact(value):
    if isinstance(value,dict):return {k:('[REDACTED]' if re.search(r'(?i)^(cookie|set-cookie|token|csrf_token|password|secret|requestHeader|responseHeader|requestBody|responseBody)$',k) else redact(v)) for k,v in value.items()}
    if isinstance(value,list):return [redact(v) for v in value]
    if not isinstance(value,str):return value
    value=re.sub(r'(?im)^(?:set-cookie|cookie|authorization):[^\r\n]*','[REDACTED]',value)
    value=re.sub(r'(?i)\b[a-f0-9]{48,}\b','[REDACTED]',value)
    value=re.sub(r'(?i)([a-z0-9_]*session[a-z0-9_]*(?:=|%3d))[^;\s"<>]+',r'\1[REDACTED]',value)
    return re.sub(r'(?i)((?:csrf_token|session_id|password|api[._-]?key)[="\s:]+)[^\s"<>;&]+',r'\1[REDACTED]',value)

def write(name,value):
    (work/name).write_text(json.dumps(redact(value),ensure_ascii=False,indent=2),encoding='utf-8')

def request(path,method='GET',data=''):
    assert path.startswith('/') and '\r' not in path and '\n' not in path
    raw=f'{method} {target}{path} HTTP/1.1\r\nHost: scan-target.invalid\r\nConnection: close\r\n'
    if method=='POST':raw+='Content-Type: application/x-www-form-urlencoded\r\nContent-Length: '+str(len(data.encode()))+'\r\n'
    response=api('core','action','sendRequest',request=raw+'\r\n'+data,followRedirects='false')
    message=response['sendRequest'][0]
    header=message.get('responseHeader','')
    status=int(header.split(' ',2)[1])
    coverage.append({'method':method,'path':path,'status':status})
    return message

def passive_wait():
    end=time.time()+180
    while int(api('pscan','view','recordsToScan')['recordsToScan'])>0:
        if time.time()>end:raise RuntimeError('Passive scan did not drain')
        time.sleep(1)

try:
    for attempt in range(180):
        try:api('core','view','version');break
        except Exception:
            if attempt==179:raise
            time.sleep(1)
    coverage=json.loads((work/'coverage.json').read_text()) if (work/'coverage.json').exists() else []
    info=json.loads((work/'target.json').read_text())
    slug=info['slug'];project=info['project']
    assert re.fullmatch(r'scan-[a-f0-9]{24}',slug) and isinstance(project,int) and project>0
    context='l12-disposable'
    excluded=re.escape(target)+r'/owner_(?:logout|switch_account|reauthenticate|login|register|oidc_callback|auth_retry)\.php(?:\?.*)?'
    if phase=='public':
        write('zap-addons.json',api('autoupdate','view','installedAddons'))
        api('context','action','newContext',contextName=context)
        api('context','action','includeInContext',contextName=context,regex=r'^https://scan-target[.]invalid(?::443)?/.*$')
        api('context','action','excludeFromContext',contextName=context,regex=r'^(?!https://scan-target[.]invalid(?::443)?/).*$')
        api('context','action','excludeFromContext',contextName=context,regex=excluded)
        api('context','action','setContextInScope',contextName=context,booleanInScope='true')
        engines=api('script','view','listEngines')['listEngines']
        engine=next(e for e in engines if 'Graal' in e)
        api('script','action','load',scriptName='l12-scope',scriptType='httpsender',scriptEngine=engine,fileName='/work/scope.js',scriptDescription='Exact disposable origin and session-safe request guard')
        api('script','action','enable',scriptName='l12-scope')
        assert any(s['name']=='l12-scope' and str(s.get('enabled')).lower()=='true' for s in api('script','view','listScripts')['listScripts'])
        api('network','action','addRateLimitRule',description='l12 moderate',enabled='true',matchRegex='false',matchString='scan-target.invalid',requestsPerSecond='2',groupBy='host')
        api('spider','action','setOptionProcessForm',Boolean='false')
        api('spider','action','setOptionPostForm',Boolean='false')
        for path in ['/','/signin.php','/signup.php',f'/p/{slug}',f'/p/{slug}/projects/{project}','/privacy','/terms',f'/p/{slug}/contact']:
            request(path)
        request(f'/p/{slug}/contact','POST',urlencode({'name':'Synthetic visitor','email':'visitor@scan.invalid','message':'Disposable passive contact coverage.'}))
        scan=api('spider','action','scan',url=target+'/',maxChildren='12',recurse='true',contextName=context)['scan']
        end=time.time()+60
        while api('spider','view','status',scanId=scan)['status']!='100' and time.time()<end:time.sleep(1)
        api('spider','action','stop',scanId=scan)
        response=http.get(target+'/owner_settings.php',verify='/work/root.crt',allow_redirects=False,timeout=30)
        tls=ssl.create_default_context(cafile='/work/root.crt')
        with socket.create_connection(('scan-target.invalid',443),timeout=10) as conn:
            with tls.wrap_socket(conn,server_hostname='scan-target.invalid') as secure:version=secure.version();cipher=secure.cipher()[0]
        anon=http.get(target+'/signup.php',verify='/work/root.crt',allow_redirects=False,timeout=30)
        plain=http.get('http://scan-target.invalid/',allow_redirects=False,timeout=30)
        cookie_headers=response.raw.headers.getlist('Set-Cookie') + anon.raw.headers.getlist('Set-Cookie')
        # Values never persist; only security-attribute booleans survive.
        write('headers.json',{'https':dict(response.headers),'http':dict(plain.headers),'http_status':plain.status_code,'tls':version,'cipher':cipher,'private_ca_validated':True,'cookie_flags':[{'secure':'; secure' in c.lower(),'http_only':'; httponly' in c.lower(),'same_site_lax':'; samesite=lax' in c.lower()} for c in cookie_headers]})
    elif phase=='owner':
        for path in ['/owner.php','/owner_profile.php','/owner_projects.php','/owner_experiences.php','/owner_messages.php','/owner_publication.php','/owner/evidence-hub','/owner_settings.php?lang=ar','/owner_settings.php?lang=en']:
            msg=request(path)
            if int(msg['responseHeader'].split(' ',2)[1])!=200:raise RuntimeError('Authenticated coverage was not authorized')
        session=json.loads((work/'session.json').read_text())
        export=request('/owner_data_export.php','POST',urlencode({'csrf_token':session['csrf']}))
        if 'application/zip' not in export['responseHeader']:raise RuntimeError('Authenticated export was not covered')
    elif phase=='active':
        write('active-rules.json',api('ascan','view','scanners'))
        api('ascan','action','setOptionThreadPerHost',Integer='1')
        api('ascan','action','setOptionHostPerScan',Integer='1')
        api('ascan','action','setOptionDelayInMs',Integer='500')
        api('ascan','action','setOptionMaxRuleDurationInMins',Integer='3')
        api('ascan','action','setOptionMaxScanDurationInMins',Integer=str(minutes))
        api('ascan','action','excludeFromScan',regex=excluded)
        # Broader than action=request: no deletion POST is actively attacked.
        api('ascan','action','excludeFromScan',regex=re.escape(target)+r'/owner_account_deletion\.php(?:\?.*)?')
        cid=api('context','view','context',contextName=context)['context']['id']
        scan=api('ascan','action','scan',url=target+'/',recurse='true',inScopeOnly='true',contextId=cid)['scan']
        end=time.time()+(minutes+2)*60
        keepalive=time.time()+60
        while api('ascan','view','status',scanId=scan)['status']!='100':
            if time.time()>end:api('ascan','action','stop',scanId=scan);break
            if time.time()>=keepalive:
                alive=request('/owner_settings.php')
                if int(alive['responseHeader'].split(' ',2)[1])!=200:raise RuntimeError('Authenticated active session was lost')
                keepalive=time.time()+60
            time.sleep(2)
        write('active-progress.json',{'progress':api('ascan','view','scanProgress',scanId=scan),'bounded_minutes':minutes})
        alive=request('/owner_settings.php')
        if int(alive['responseHeader'].split(' ',2)[1])!=200:raise RuntimeError('Authenticated active session was lost')
    elif phase=='scheduled':
        msg=request('/owner_account_deletion.php')
        if 'data-account-deletion-scheduled' not in msg.get('responseBody',''):raise RuntimeError('Scheduled page was not covered')
        session=json.loads((work/'session.json').read_text())
        export=request('/owner_data_export.php','POST',urlencode({'csrf_token':session['csrf']}))
        if 'application/zip' not in export['responseHeader']:raise RuntimeError('Scheduled export was not covered')
    else:raise RuntimeError('Unknown phase')
    passive_wait()
    scripts=api('script','view','listScripts')['listScripts']
    if not any(s['name']=='l12-scope' and str(s.get('enabled')).lower()=='true' for s in scripts):raise RuntimeError('Scope guard is no longer enabled')
    write('zap-'+phase+'.json',api('core','view','alerts',baseurl=target,start='0',count='100000'))
    write('coverage.json',coverage)
except Exception as error:
    write('zap-failure.json',{'phase':phase,'type':type(error).__name__,'message':str(error)})
    sys.exit(1)
'@

Assert-Scan (Test-Path -LiteralPath $reportRoot -PathType Container) 'The private report root must exist.'
$root = [IO.Path]::GetFullPath($reportRoot).TrimEnd('\','/') + [IO.Path]::DirectorySeparatorChar
if (!$OutputDirectory) { $OutputDirectory = Join-Path $reportRoot ('run-' + $run) }
$OutputDirectory = [IO.Path]::GetFullPath($OutputDirectory)
Assert-Scan ($OutputDirectory.StartsWith($root,[StringComparison]::OrdinalIgnoreCase)) 'Output must be inside the owner-designated private report directory.'
Assert-Scan (!$OutputDirectory.StartsWith($repository,[StringComparison]::OrdinalIgnoreCase)) 'Repository output is forbidden.'
Assert-Scan ((Split-Path $OutputDirectory -Parent) -eq $root.TrimEnd('\','/')) 'Output must be a direct, new child of the private report root.'
Assert-Scan (!(Test-Path -LiteralPath $OutputDirectory)) 'Output must be a new directory.'
foreach ($path in @($reportRoot,(Split-Path $reportRoot -Parent))) {
    Assert-Scan (((Get-Item -LiteralPath $path).Attributes -band [IO.FileAttributes]::ReparsePoint) -eq 0) 'Private report paths must not be links.'
}
foreach ($pin in $pins.Values) { Assert-Scan ($pin -match '^[a-z0-9./-]+:[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z]+)?$') 'An image is not version-pinned.' }
if ($ValidateOnly) { Write-Output 'SECURITY_SCAN_PREFLIGHT_VALIDATED'; return }
New-Item -ItemType Directory -Path $OutputDirectory,$temporary | Out-Null
$before = @(Live-Inventory)
Write-Safe (Join-Path $OutputDirectory 'live-before.json') (ConvertTo-Json -InputObject $before)
$cleanupErrors = [Collections.Generic.List[string]]::new()
$success = $false
try {
    Write-Output 'SCAN_PHASE prepare'
    foreach ($entry in $pins.GetEnumerator()) { if($entry.Key -ne 'zap'){Invoke-ScanDocker @('pull',$entry.Value) | Out-Null} }
    $dockerfile = [IO.File]::ReadAllText((Join-Path $repository 'Dockerfile'))
    Assert-Scan ($dockerfile.Contains('FROM composer:2 AS dependencies') -and $dockerfile.Contains('FROM php:8.3-apache AS runtime-base')) 'Build source seams changed.'
    $dockerfile = $dockerfile.Replace('FROM composer:2 AS dependencies',"FROM $($pins.composer) AS dependencies").Replace('FROM php:8.3-apache AS runtime-base',"FROM $($pins.php) AS runtime-base")
    Write-Utf8 'Dockerfile' ($dockerfile + "`nLABEL dev.ather.security-scan=`"$run`"`n")
    $phase='production build'
    $existing = & docker image inspect $image 2>$null
    Assert-Scan ($LASTEXITCODE -ne 0) 'The generated image name is already in use.'
    $imageCreated=$true
    Invoke-ScanDocker @('build','--file',(Join-Path $temporary 'Dockerfile'),'--target','production','--build-arg',('APP_VERSION='+((& git -C $repository rev-parse HEAD).Trim())),'--tag',$image,$repository) @(0) 'build.txt' | Out-Null
    # The only source overlay: publish legal pages in this disposable runtime, as the existing legal fixture does.
    $legal = [IO.File]::ReadAllText((Join-Path $repository 'includes/legal_documents.php'))
    Assert-Scan ($legal.Contains('const LEGAL_PAGES_PUBLISHED = false;') -and $legal.Contains('const LEGAL_DOCUMENT_EFFECTIVE_DATE = null;')) 'Legal fixture seams changed.'
    Write-Utf8 'legal_documents.php' ($legal.Replace('const LEGAL_PAGES_PUBLISHED = false;','const LEGAL_PAGES_PUBLISHED = true;').Replace('const LEGAL_DOCUMENT_EFFECTIVE_DATE = null;',"const LEGAL_DOCUMENT_EFFECTIVE_DATE = '2026-11-01';"))
    Write-Utf8 'Caddyfile' ([IO.File]::ReadAllText((Join-Path $repository 'docker/Caddyfile.owner-local')).Replace('https://localhost {','https://scan-target.invalid {'))
    $environment = [ordered]@{ APP_ENV='production'; DB_HOST='db'; DB_PORT='3306'; DB_NAME=$project; DB_USER='scan_app'; DB_PASSWORD=(Random-Secret); SESSION_COOKIE_SECURE='true'; PUBLIC_BASE_URL='https://scan-target.invalid'; EXPECTED_OIDC_ISSUER='https://oidc-scan.invalid/'; PRESERVED_V1_OIDC_SUBJECT=('scan-owner-'+$run); OIDC_CLIENT_ID=('scan-client-'+$run); OIDC_CLIENT_SECRET=(Random-Secret); OIDC_REDIRECT_URI='https://scan-target.invalid/owner_oidc_callback.php'; EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY=(Random-Secret); PRIVACY_CONTACT_EMAIL='privacy@scan.invalid'; ATHERCAR_STORAGE_ROOT='/var/lib/ather-career/storage'; RATE_LIMIT_STATE_DIR='/var/www/app/runtime/rate-limit' }
    $labels=@{$label=$run}
    $mount = { param($source,$target,$readOnly=$false) @{type='bind';source=$source;target=$target;read_only=$readOnly} }
    $volumes=@{}; foreach($name in @('db','storage','rate','caddy','caddy-config','trivy')) { $volumes[$name]=@{name="$project-$name";labels=$labels};$ownedVolumes.Add("$project-$name") }
    $networks=@{};foreach($name in @('backend','scan')){$networks[$name]=@{name="$project-$name";internal=$true;labels=$labels};$ownedNetworks.Add("$project-$name")}
    $services=[ordered]@{
        db=@{image=$pins.mysql;container_name="$project-db";labels=$labels;networks=@('backend');environment=@{MYSQL_DATABASE=$project;MYSQL_USER='scan_app';MYSQL_PASSWORD=$environment.DB_PASSWORD;MYSQL_ROOT_PASSWORD=(Random-Secret)};volumes=@(@{type='volume';source='db';target='/var/lib/mysql'},(& $mount (Join-Path $repository 'database/portfolio_db.sql') '/docker-entrypoint-initdb.d/01-portfolio.sql' $true));healthcheck=@{test=@('CMD-SHELL','MYSQL_PWD="$$MYSQL_ROOT_PASSWORD" mysqladmin ping --protocol=TCP -h 127.0.0.1 -u root --silent');interval='2s';timeout='2s';retries=60}}
        web=@{image=$image;container_name="$project-web";labels=$labels;networks=@('backend');environment=$environment;security_opt=@('no-new-privileges:true');volumes=@(@{type='volume';source='storage';target='/var/lib/ather-career/storage'},@{type='volume';source='rate';target='/var/www/app/runtime/rate-limit'},(& $mount $temporary '/tmp/security-scan'),(& $mount (Join-Path $temporary 'legal_documents.php') '/var/www/app/includes/legal_documents.php' $true));healthcheck=@{test=@('CMD-SHELL','php -r ''exit(@file_get_contents("http://127.0.0.1/health.php") === "OK\n" ? 0 : 1);''');interval='2s';timeout='2s';retries=60}}
        front=@{image=$pins.caddy;container_name="$project-front";labels=$labels;networks=@{backend=@{};scan=@{aliases=@('scan-target.invalid')}};environment=@{EXPECTED_OIDC_ISSUER=$environment.EXPECTED_OIDC_ISSUER};volumes=@((& $mount (Join-Path $temporary 'Caddyfile') '/etc/caddy/Caddyfile' $true),@{type='volume';source='caddy';target='/data'},@{type='volume';source='caddy-config';target='/config'})}
    }
    Write-Utf8 'compose.json' (@{services=$services;networks=$networks;volumes=$volumes} | ConvertTo-Json -Depth 15)
    # Runtime inputs and credentials are private, never part of scanner output or the build context.
    Write-Utf8 'fixture.php' $fixturePhp
    Write-Utf8 'scan.py' $scannerPython
    Write-Utf8 'scope.js' $scopeScript
    $phase='disposable startup'
    foreach($name in @('db','web','front')){$ownedContainers.Add("$project-$name")}
    Assert-Scan (!(Invoke-ScanDocker @('ps','--all','--filter',"label=com.docker.compose.project=$project",'--format','{{.ID}}'))) 'The generated Compose project is already in use.'
    foreach($name in $ownedVolumes){Assert-Scan (!(Invoke-ScanDocker @('volume','ls','--filter',"name=^$name$",'--format','{{.Name}}'))) 'A generated volume already exists.'}
    foreach($name in $ownedNetworks){Assert-Scan (!(Invoke-ScanDocker @('network','ls','--filter',"name=^$name$",'--format','{{.Name}}'))) 'A generated network already exists.'}
    Invoke-ScanCompose @('up','--detach','--no-build') | Out-Null
    Wait-Container "$project-db"; Wait-Container "$project-web"
    foreach($name in $ownedNetworks){Assert-Scan ((Invoke-ScanDocker @('network','inspect','--format','{{.Internal}}',$name)) -eq 'true') 'A scan network is not internal.'}
    $phase='synthetic seed'
    Invoke-ScanPhp @('database/migrate.php','--through=003') | Out-Null
    Invoke-ScanPhp @('database/production-ownership-bootstrap.php') | Out-Null
    Invoke-ScanPhp @('database/migrate.php') | Out-Null
    Invoke-ScanPhp @('/tmp/security-scan/fixture.php','seed') | Out-Null
    Invoke-ScanPhp @('database/capture-owner-journey-cutover.php','--confirm-exclusive-maintenance',"--database=$project") | Out-Null
    Invoke-ScanPhp @('/tmp/security-scan/fixture.php','session') | Out-Null
    Invoke-ScanDocker @('cp',"${project}-front:/data/caddy/pki/authorities/local/root.crt",(Join-Path $temporary 'root.crt')) | Out-Null
    # Dependency and container assessments finish independently before ZAP starts.
    Invoke-Assessment 'composer' {
        $auditName="$project-composer"; $ownedContainers.Add($auditName)
        Invoke-ScanDocker @('run','--name',$auditName,'--label',"$label=$run",'--mount',"type=bind,source=$repository/composer.lock,target=/app/composer.lock,readonly",'--mount',"type=bind,source=$repository/composer.json,target=/app/composer.json,readonly",'--workdir','/app',$pins.composer,'audit','--locked','--format=json','--no-interaction') @(0,1,2,3) 'composer-audit.json' | Out-Null
    }
    Invoke-ScanDocker @('volume','create','--label',"$label=$run","$project-trivy") | Out-Null
    Invoke-ScanDocker @('image','save','--output',(Join-Path $temporary 'image.tar'),$image) | Out-Null
    Invoke-Assessment 'trivy-db' {
        $trivyName="$project-trivy-db"; $ownedContainers.Add($trivyName)
        Invoke-ScanDocker @('run','--name',$trivyName,'--label',"$label=$run",'--mount',"type=volume,source=$project-trivy,target=/root/.cache/trivy",$pins.trivy,'image','--download-db-only','--no-progress') @(0) 'trivy-db.txt' | Out-Null
    }
    Invoke-Assessment 'trivy-image' {
        $trivyName="$project-trivy-image";$ownedContainers.Add($trivyName)
        Invoke-ScanDocker @('run','--name',$trivyName,'--label',"$label=$run",'--network','none','--mount',"type=volume,source=$project-trivy,target=/root/.cache/trivy",'--mount',"type=bind,source=$temporary/image.tar,target=/image.tar,readonly",$pins.trivy,'image','--input','/image.tar','--skip-db-update','--offline-scan','--scanners','vuln','--severity','HIGH,CRITICAL','--format','json','--quiet') @(0) 'trivy-image.json' | Out-Null
    }
    Invoke-Assessment 'trivy-config' {
        New-Item -ItemType Directory -Path (Join-Path $temporary 'config-input') | Out-Null
        foreach($name in @('Dockerfile','docker-compose.yml','docker-compose.production.yml','docker-compose.owner-https.yml')) { Copy-Item -LiteralPath (Join-Path $repository $name) -Destination (Join-Path $temporary 'config-input') }
        $trivyName="$project-trivy-config";$ownedContainers.Add($trivyName)
        Invoke-ScanDocker @('run','--name',$trivyName,'--label',"$label=$run",'--mount',"type=volume,source=$project-trivy,target=/root/.cache/trivy",'--mount',"type=bind,source=$temporary/config-input,target=/config,readonly",$pins.trivy,'config','--format','json','--quiet','/config') @(0) 'trivy-config.json' | Out-Null
    }
    Invoke-Assessment 'zap' {
        try {
            $script:phase='ZAP setup'
            $zapName="$project-zap"; $ownedContainers.Add($zapName)
            Invoke-ScanDocker @('pull',$pins.zap) | Out-Null
            $apiKey=Random-Secret
            New-Item -ItemType Directory -Path (Join-Path $temporary 'zap-home') | Out-Null
            Write-Utf8 'zap.properties' "api.key=$apiKey`nautoupdate.checkOnStart=false`nautoupdate.downloadNewRelease=false`nautoupdate.installAddonUpdates=false`n"
            Invoke-ScanDocker @('run','--detach','--user','0:0','--name',$zapName,'--label',"$label=$run",'--network',"$project-scan",'--mount',"type=bind,source=$temporary,target=/work",'--memory','3g','--memory-swap','3g','--cpus','2','--entrypoint','sh',$pins.zap,'-c','exec zap.sh -Xmx2g -silent -daemon -host 127.0.0.1 -port 8090 -dir /work/zap-home -configfile /work/zap.properties') | Out-Null
            $members = (Invoke-ScanDocker @('network','inspect','--format','{{json .Containers}}',"$project-scan") | ConvertFrom-Json).PSObject.Properties.Value.Name | Sort-Object
            Assert-Scan (!(Compare-Object @("$project-front",$zapName) @($members))) 'Unexpected scanner-network membership.'
            Write-Safe (Join-Path $OutputDirectory 'isolation.json') (@{internal=$true;published_ports=@();scanner_network_members=@($members);silent=$true;bundled_addons_preserved=$true;external_oast_configured=$false} | ConvertTo-Json)
            $script:phase='ZAP public baseline'
            Write-Output 'SCAN_PHASE zap-public'
            Invoke-ScanDocker @('exec',$zapName,'python3','/work/scan.py','public',"$ActiveMinutes") | Out-Null
            $script:phase='ZAP authenticated baseline'
            Write-Output 'SCAN_PHASE zap-owner'
            Invoke-ScanPhp @('/tmp/security-scan/fixture.php','session') | Out-Null
            Invoke-ScanDocker @('exec',$zapName,'python3','/work/scan.py','owner',"$ActiveMinutes") | Out-Null
            $script:phase='ZAP active'
            Write-Output 'SCAN_PHASE zap-active'
            Invoke-ScanDocker @('exec',$zapName,'python3','/work/scan.py','active',"$ActiveMinutes") | Out-Null
            $script:phase='scheduled-deletion passive coverage'
            Write-Output 'SCAN_PHASE zap-scheduled'
            Invoke-ScanPhp @('/tmp/security-scan/fixture.php','scheduled') | Out-Null
            Invoke-ScanDocker @('exec',$zapName,'python3','/work/scan.py','scheduled',"$ActiveMinutes") | Out-Null
        } finally {
            foreach($name in @('zap-public.json','zap-owner.json','zap-active.json','zap-scheduled.json','headers.json','coverage.json','active-progress.json','active-rules.json','zap-addons.json','zap-memory.json','zap-failure.json','zap-api-failure.json','zap-script-state.json')) {
                if(Test-Path -LiteralPath (Join-Path $temporary $name)){Write-Safe (Join-Path $OutputDirectory $name) ([IO.File]::ReadAllText((Join-Path $temporary $name)))}
            }
            $sessionPath=Join-Path $temporary 'session.json'
            if(Test-Path -LiteralPath $sessionPath){
                $sessionCarrier=Get-Content -LiteralPath $sessionPath -Raw | ConvertFrom-Json
                $secrets.Add($sessionCarrier.cookie);$secrets.Add(($sessionCarrier.cookie -split '=',2)[1]);$secrets.Add($sessionCarrier.csrf)
            }
            if($zapName -and (Invoke-ScanDocker @('ps','--all','--filter',"name=^/$zapName$",'--format','{{.Names}}'))){
                Invoke-ScanDocker @('logs',$zapName) @(0) 'zap-daemon.txt' | Out-Null
                $state=Invoke-ScanDocker @('inspect','--format','{{json .State}}',$zapName) | ConvertFrom-Json
                Write-Safe (Join-Path $OutputDirectory 'zap-state.json') (@{running=$state.Running;oom_killed=$state.OOMKilled;exit_code=$state.ExitCode;heap='-Xmx2g';memory_limit_bytes=3221225472} | ConvertTo-Json)
            }
        }
    }
    Write-Safe (Join-Path $OutputDirectory 'run.json') (@{source=(Invoke-ScanDocker @('image','inspect','--format','{{index .Config.Labels "org.opencontainers.image.revision"}}',$image));project=$project;pins=$pins;active_minutes=$ActiveMinutes;active_rule_minutes=3;completed=(@($assessments.Values | Where-Object {$_ -ne 'completed'}).Count -eq 0);assessments=$assessments;target='https://scan-target.invalid';production_test_mode=$false} | ConvertTo-Json -Depth 5)
    $success=(@($assessments.Values | Where-Object {$_ -ne 'completed'}).Count -eq 0)
} catch {
    if(Test-Path -LiteralPath (Join-Path $temporary 'zap-failure.json')){Write-Safe (Join-Path $OutputDirectory 'zap-failure.json') ([IO.File]::ReadAllText((Join-Path $temporary 'zap-failure.json')))}
    Write-Safe (Join-Path $OutputDirectory 'failure.json') (@{phase=$phase;message=$_.Exception.Message} | ConvertTo-Json)
    Write-Output "SECURITY_SCAN_STOPPED phase=$phase; private diagnostic written."
} finally {
    foreach($name in $ownedContainers){
        try {
            $exists = Invoke-ScanDocker @('ps','--all','--filter',"name=^/$name$",'--format','{{.Names}}')
            if($exists){Assert-Owned 'container' $name; Invoke-ScanDocker @('stop','--time','10',$name) | Out-Null;Invoke-ScanDocker @('rm',$name) | Out-Null}
        }catch{$cleanupErrors.Add('container cleanup refused or failed')}
    }
    foreach($name in $ownedVolumes){try{if(Invoke-ScanDocker @('volume','ls','--filter',"name=^$name$",'--format','{{.Name}}')){Assert-Owned 'volume' $name;Invoke-ScanDocker @('volume','rm',$name) | Out-Null}}catch{$cleanupErrors.Add('volume cleanup refused or failed')}}
    foreach($name in $ownedNetworks){try{if(Invoke-ScanDocker @('network','ls','--filter',"name=^$name$",'--format','{{.Name}}')){Assert-Owned 'network' $name;Invoke-ScanDocker @('network','rm',$name) | Out-Null}}catch{$cleanupErrors.Add('network cleanup refused or failed')}}
    if($imageCreated){try{Assert-Owned 'image' $image;Invoke-ScanDocker @('image','rm',$image) | Out-Null}catch{$cleanupErrors.Add('image cleanup refused or failed')}}
    $after=@(Live-Inventory)
    Write-Safe (Join-Path $OutputDirectory 'live-after.json') (ConvertTo-Json -InputObject $after)
    $liveUnchanged=!(Compare-Object $before $after)
    Write-Safe (Join-Path $OutputDirectory 'cleanup.json') (@{errors=@($cleanupErrors);live_unchanged=$liveUnchanged} | ConvertTo-Json)
    $resolved=[IO.Path]::GetFullPath($temporary)
    if((Split-Path $resolved -Parent) -eq [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\','/') -and (Split-Path $resolved -Leaf) -eq ('ather-security-'+$run)) { Remove-Item -LiteralPath $resolved -Recurse -Force }
    if(!$liveUnchanged -or $cleanupErrors.Count){$success=$false;Write-Output 'SECURITY_SCAN_SAFETY_CHECK_FAILED; private status written.'}
}
if(!$success){exit 1}
Write-Output "SECURITY_SCAN_COMPLETED output=$OutputDirectory live_unchanged=true"
