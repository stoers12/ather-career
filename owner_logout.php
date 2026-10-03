<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/owner_session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/http.php';

startOwnerSession();

httpRegisterExceptionBoundary('owner_logout.php');
httpRequireMethod(['POST']);

requireValidCsrfToken($_POST['csrf_token'] ?? null);
destroyOwnerSession();

httpRedirect('owner_login.php');
