<?php
declare(strict_types=1);

// Runtime for PRIVATE test-source copies only, never production selection.
if (PHP_SAPI !== 'cli-server' || !is_file('/.dockerenv') || getenv('APP_ENV') !== 'test'
    || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/\Aather_career_test_[a-f0-9]{24}\z/D',(string)getenv('DB_NAME')) !== 1
    || getenv('DB_NAME') !== getenv('ATHERCAR_TEST_DB_NAME')) { exit(2); }
$GLOBALS['attestation_http_fixture'] = json_decode(file_get_contents(__CONTROL__),true,8,JSON_THROW_ON_ERROR);

function attestationHttpFixtureContent(string $kind): string
{
    $c=$GLOBALS['attestation_http_fixture'];
    return $c['maximum']??false ? str_repeat('x',65536) : 'SYNTHETIC TEST ONLY — ليست سياسة: '.$kind.':'.($c['version']??'v1').':'.($c['language']??'en');
}
function attestationHttpFixtureBundle(): ?OnboardingAttestationBundle
{
    $c=$GLOBALS['attestation_http_fixture'];
    if ($c['null_bundle']??false) { return null; }
    $doc=static fn(string $kind): OnboardingDocumentDescriptor=>new OnboardingDocumentDescriptor('test-'.$kind,$c['version']??'v1',hash('sha256',attestationHttpFixtureContent($kind)),$c['language']??'en');
    return new OnboardingAttestationBundle($doc('age'),$doc('terms'),$doc('privacy'));
}
function attestationHttpFixtureDocuments(): ?array
{
    $c=$GLOBALS['attestation_http_fixture'];
    if ($c['null_documents']??false) { return null; }
    $documents=[];
    foreach (['age','terms','privacy'] as $kind) {
        $documents[$kind]=['language'=>$c['language']??'en','content'=>attestationHttpFixtureContent($kind)];
        if (($c['kind']??null)===$kind) {
            switch ($c['bad']??null) {
                case 'missing': unset($documents[$kind]); break;
                case 'digest': $documents[$kind]['content'].='changed'; break;
                case 'language': $documents[$kind]['language']='fr'; break;
                case 'empty': $documents[$kind]['content']=''; break;
                case 'utf8': $documents[$kind]['content']="\xff"; break;
                case 'oversized': $documents[$kind]['content']=str_repeat('x',65537); break;
                case 'type': $documents[$kind]['content']=true; break;
            }
        }
    }
    return $documents;
}

class AttestationHttpFixturePDO extends PDO
{
    private bool $inserted=false;
    public function beginTransaction(): bool
    {
        if (isset($GLOBALS['attestation_http_fixture']['revoke_user'])) {
            $other=new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_NAME'),getenv('DB_USER'),getenv('DB_PASSWORD'));
            $other->prepare('UPDATE users SET authz_version=authz_version+1 WHERE id=?')->execute([$GLOBALS['attestation_http_fixture']['revoke_user']]);
        }
        return parent::beginTransaction();
    }
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        $c=$GLOBALS['attestation_http_fixture'];
        if (($c['barrier']??false) && str_contains($query,'SELECT id FROM users') && str_contains($query,'FOR UPDATE')) {
            file_put_contents(__BARRIER__.'/'.getmypid(),'ready');
        }
        if (str_starts_with($query,'INSERT INTO onboarding_attestations')) {
            if (($c['fault']??null)==='before_insert') { throw new PDOException('PRIVATE_FAULT_SQL_SENTINEL'); }
            $this->inserted=true;
        } elseif ($this->inserted && ($c['fault']??null)==='after_insert' && str_contains($query,'FROM onboarding_attestations')) {
            throw new PDOException('PRIVATE_FAULT_SQL_SENTINEL');
        }
        return parent::prepare($query,$options);
    }
    public function commit(): bool
    {
        $result=parent::commit();
        if (($GLOBALS['attestation_http_fixture']['fault']??null)==='after_commit') { throw new PDOException('PRIVATE_FAULT_SQL_SENTINEL'); }
        return $result;
    }
}
