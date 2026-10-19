<?php
declare(strict_types=1);

function onboardingCompletionSchemaColumns(): array
{
    $columns=[];
    foreach (['user_id','portfolio_id','profile_id','draft_version','authz_version','readiness_observed_at'] as $name) { $columns[$name]=['type'=>'int unsigned','nullable'=>'NO']; }
    foreach (['draft_digest','intent_digest','review_digest'] as $name) { $columns[$name]=['type'=>'binary(32)','nullable'=>'NO']; }
    foreach (['operation_id'=>43,'contract_revision'=>64,'catalog_revision'=>64,'deferred_fields'=>256] as $name=>$length) { $columns[$name]=['type'=>"varbinary($length)",'nullable'=>'NO']; }
    foreach (onboardingAttestationSchemaColumns() as $name=>$definition) {
        if (!in_array($name,['user_id','recorded_at'],true)) { $columns[$name]=$definition; }
    }
    $columns['attested_at']=['type'=>'datetime(6)','nullable'=>'NO'];
    $columns['completed_at']=['type'=>'datetime(6)','nullable'=>'NO'];
    return $columns;
}

function onboardingCompletionAttestationKey(): array
{
    return array_values(array_diff(array_keys(onboardingAttestationSchemaColumns()),['recorded_at']));
}

function onboardingCompletionSchemaChecks(): array
{
    return ['chk_completion_draft_version'=>'((draft_version > 0) and (draft_version <= 2147483647))',
        'chk_completion_authz'=>'(authz_version > 0)', 'chk_completion_observation'=>'(readiness_observed_at > 0)'];
}

function verifyOnboardingCompletionSchema(PDO $database): void
{
    verifyExpectedColumns($database,'onboarding_completions',onboardingCompletionSchemaColumns());
    foreach (fetchColumns($database,'onboarding_completions') as $column) {
        if ($column['default_value']!==null || $column['extra']!=='') { throw new RuntimeException('Incompatible completion column attributes.'); }
    }
    if ($database->query("SELECT engine FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='onboarding_completions'")->fetchColumn()!=='InnoDB') { throw new RuntimeException('Completion evidence requires InnoDB.'); }
    $expectedIndexes=['PRIMARY'=>[true,['user_id']], 'uq_completion_portfolio'=>[true,['portfolio_id']],
        'uq_completion_profile'=>[true,['profile_id']], 'idx_completion_attestation'=>[false,onboardingCompletionAttestationKey()]];
    $indexes=[];
    foreach ($database->query("SELECT index_name AS name, non_unique AS non_unique, column_name AS column_name, sub_part AS sub_part, collation AS collation, index_type AS index_type FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='onboarding_completions' ORDER BY index_name,seq_in_index")->fetchAll(PDO::FETCH_ASSOC) as $part) {
        if ($part['sub_part']!==null || $part['collation']!=='A' || $part['index_type']!=='BTREE') { throw new RuntimeException('Incompatible completion index attributes.'); }
        $indexes[$part['name']][0]=(int)$part['non_unique']===0; $indexes[$part['name']][1][]=$part['column_name'];
    }
    ksort($indexes); ksort($expectedIndexes);
    if ($indexes!==$expectedIndexes) { throw new RuntimeException('Incompatible completion indexes.'); }
    $expectedFks=['fk_completion_user'=>['users',['user_id'],['id']],
        'fk_completion_portfolio'=>['portfolios',['portfolio_id'],['id']],
        'fk_completion_profile'=>['personal_info',['profile_id'],['id']],
        'fk_completion_attestation'=>['onboarding_attestations',onboardingCompletionAttestationKey(),onboardingCompletionAttestationKey()]];
    $fks=[];
    $sql="SELECT k.constraint_name AS name,k.column_name AS column_name,k.referenced_table_name AS target,k.referenced_column_name AS target_column,r.update_rule AS update_rule,r.delete_rule AS delete_rule FROM information_schema.key_column_usage k JOIN information_schema.referential_constraints r ON k.constraint_schema=r.constraint_schema AND k.constraint_name=r.constraint_name AND k.table_name=r.table_name WHERE k.table_schema=DATABASE() AND k.table_name='onboarding_completions' ORDER BY k.constraint_name,k.ordinal_position";
    foreach ($database->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $part) {
        if ($part['update_rule']!=='RESTRICT' || $part['delete_rule']!=='RESTRICT') { throw new RuntimeException('Incompatible completion FK rules.'); }
        $fks[$part['name']][0]=$part['target']; $fks[$part['name']][1][]=$part['column_name']; $fks[$part['name']][2][]=$part['target_column'];
    }
    ksort($fks); ksort($expectedFks);
    if ($fks!==$expectedFks) { throw new RuntimeException('Incompatible completion evidence references.'); }
    $checks=$database->query("SELECT tc.constraint_name AS name,cc.check_clause AS clause,tc.enforced AS enforced FROM information_schema.table_constraints tc JOIN information_schema.check_constraints cc ON tc.constraint_schema=cc.constraint_schema AND tc.constraint_name=cc.constraint_name WHERE tc.table_schema=DATABASE() AND tc.table_name='onboarding_completions'")->fetchAll(PDO::FETCH_ASSOC);
    $expected=onboardingCompletionSchemaChecks();
    if (count($checks)!==count($expected)) { throw new RuntimeException('Incompatible completion checks.'); }
    foreach ($checks as $check) {
        $clause=$expected[$check['name']]??null;
        if ($clause===null || $check['enforced']!=='YES' || preg_replace('/[\s`]/','',$check['clause'])!==preg_replace('/[\s`]/','',$clause)) { throw new RuntimeException('Incompatible completion check expression.'); }
    }
    $constraints=(int)$database->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='onboarding_completions'")->fetchColumn();
    $triggers=(int)$database->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='onboarding_completions'")->fetchColumn();
    if ($constraints!==10 || $triggers!==0) { throw new RuntimeException('Unexpected completion constraints or triggers.'); }
}

function executeOnboardingCompletionMigration(PDO $database): void
{
    $definitions=[];
    foreach (onboardingCompletionSchemaColumns() as $name=>$definition) { $definitions[]="$name {$definition['type']} NOT NULL"; }
    $definitions[]='PRIMARY KEY (user_id)';
    $definitions[]='UNIQUE KEY uq_completion_portfolio (portfolio_id)';
    $definitions[]='UNIQUE KEY uq_completion_profile (profile_id)';
    $key=implode(',',onboardingCompletionAttestationKey());
    $definitions[]="KEY idx_completion_attestation ($key)";
    $definitions[]='CONSTRAINT fk_completion_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT';
    $definitions[]='CONSTRAINT fk_completion_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON UPDATE RESTRICT ON DELETE RESTRICT';
    $definitions[]='CONSTRAINT fk_completion_profile FOREIGN KEY (profile_id) REFERENCES personal_info(id) ON UPDATE RESTRICT ON DELETE RESTRICT';
    $definitions[]="CONSTRAINT fk_completion_attestation FOREIGN KEY ($key) REFERENCES onboarding_attestations($key) ON UPDATE RESTRICT ON DELETE RESTRICT";
    foreach (onboardingCompletionSchemaChecks() as $name=>$clause) { $definitions[]="CONSTRAINT $name CHECK ($clause)"; }
    $database->exec('CREATE TABLE IF NOT EXISTS onboarding_completions ('.implode(',',$definitions).') ENGINE=InnoDB');
    verifyOnboardingCompletionSchema($database);
    if ((int)$database->query('SELECT COUNT(*) FROM onboarding_completions')->fetchColumn()!==0) { throw new RuntimeException('Unrecorded 019 evidence requires manual review; no automatic adoption.'); }
    testOnlyMigrationFailurePoint('after-completion-table-create');
}
