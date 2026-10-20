<?php
declare(strict_types=1);

// Runtime for PRIVATE test-source copies only, never production selection.
if (PHP_SAPI !== 'cli-server' || !is_file('/.dockerenv') || getenv('APP_ENV') !== 'test'
    || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/\Aather_career_test_[a-f0-9]{24}\z/D',(string)getenv('DB_NAME')) !== 1
    || getenv('DB_NAME') !== getenv('ATHERCAR_TEST_DB_NAME')) { exit(2); }
$GLOBALS['attestation_http_fixture'] = json_decode(file_get_contents(__CONTROL__),true,8,JSON_THROW_ON_ERROR);

function attestationHttpFixtureBundle(): ?OnboardingAttestationBundle
{
    $c=$GLOBALS['attestation_http_fixture'];
    if($c['null_bundle']??false){return null;}
    $v=$c['version']??'test-v1'; $lang=$c['language']??'en';
    $doc=static fn(string $kind): OnboardingDocumentDescriptor=>new OnboardingDocumentDescriptor('test-'.$kind,$v,hash('sha256','SYNTHETIC ONLY:'.$kind.':'.$v.':'.$lang),$lang);
    return new OnboardingAttestationBundle($doc('age'),$doc('terms'),$doc('privacy'));
}

class AttestationHttpFixturePDO extends PDO
{
    private int $writes=0;
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
        if(preg_match('/\AINSERT INTO (?:portfolios|personal_info|onboarding_completions)\b/',$query)===1){
            ++$this->writes;
            if(($c['fail_write']??0)===$this->writes){$this->exec('SELECT * FROM private_completion_http_missing_table');}
        }
        return parent::prepare($query,$options);
    }
    public function commit(): bool
    {
        if (($GLOBALS['attestation_http_fixture']['fault']??null)==='before_commit') { throw new PDOException('PRIVATE_FAULT_SQL_SENTINEL'); }
        $result=parent::commit();
        if (($GLOBALS['attestation_http_fixture']['fault']??null)==='after_commit') { throw new PDOException('PRIVATE_FAULT_SQL_SENTINEL'); }
        if (($GLOBALS['attestation_http_fixture']['fault']??null)==='lost_response') { exit; }
        return $result;
    }
}
