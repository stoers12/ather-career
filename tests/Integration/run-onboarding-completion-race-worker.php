<?php
declare(strict_types=1);
require_once __DIR__.'/../../tests/Support/completion-fixtures.php';
$input=json_decode(base64_decode($argv[1],true),true,32,JSON_THROW_ON_ERROR);
completionFixtureLoadCore(!in_array($input['mode'],['recover','retry_production'],true));
final class CompletionBarrierDatabase extends PDO
{
    private bool $waiting=true;
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if ($this->waiting && str_contains($query,'FROM users') && str_contains($query,'FOR UPDATE')) {
            $this->waiting=false; echo "READY\n"; fflush(STDOUT); if (fgets(STDIN)!=="GO\n") { exit(3); }
        }
        return parent::prepare($query,$options);
    }
}
$root=completionSessionRoot(); $_SESSION=$input['session'];
$db=completionConnection(CompletionBarrierDatabase::class); $context=AuthenticatedUserContext::fromValidatedUser($input['user']);
try {
    if ($input['mode']==='save') { $result=saveOnboardingDraft($db,$context,3,'3:'.bin2hex(random_bytes(16)),'professional_snapshot',['headline'=>'Concurrent saved headline']); }
    elseif ($input['mode']==='recover') { $result=readOnboardingCompletion($db,$context); }
    else { $result=completeOnboarding($db,$context,$input['intent']); }
    $output=['status'=>'saved','result'=>$result];
} catch (OnboardingCompletionConflictException|OnboardingDraftConflictException) { $output=['status'=>'conflict']; }
catch (Throwable $error) { $output=['status'=>'error','class'=>get_class($error)]; }
finally { completionEndSession($root); }
echo json_encode($output,JSON_THROW_ON_ERROR);
