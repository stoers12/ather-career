<?php

declare(strict_types=1);

final class RuntimeObservabilityStaticTest
{
    public static function run(TestEnvironment $environment): void
    {
        $health = self::read('public/health.php');
        $readiness = self::read('ready.php');
        $readinessChecks = self::read('includes/runtime_readiness.php');
        $observability = self::read('includes/observability.php');
        $http = self::read('includes/http.php');
        $errors = self::read('includes/error_reporting.php');
        $security = self::read('includes/security_events.php');
        $developmentCompose = self::read('docker-compose.yml');
        $ownerCompose = self::read('docker-compose.owner-https.yml');
        $productionCompose = self::read('docker-compose.production.yml');
        $entrypoint = self::read('docker/production-entrypoint.sh');
        $developmentVhost = self::read('docker/apache/development-vhost.conf');
        $productionVhost = self::read('docker/apache/production-vhost.conf');

        phase2Assert(str_contains($health, "runtimeStartRequest('health')")
            && !preg_match('/getDatabaseConnection|runtimeReadinessFailureReason|PDO/i', $health)
            && str_contains($health, 'echo "OK\\n";'), 'Liveness must remain process-only and preserve its minimal successful body.');

        phase2Assert(str_contains($readiness, 'runtimeReadinessFailureReason()')
            && str_contains($readiness, 'http_response_code(503)')
            && str_contains($readiness, 'echo "UNAVAILABLE\\n";')
            && str_contains($readiness, 'echo "READY\\n";')
            && !str_contains($readiness, 'getMessage()'), 'Readiness no longer has a sanitized fail-closed contract.');
        foreach (['SELECT 1', 'schema_migrations', 'information_schema.columns', 'requirePrivateStorageRoot(true)', 'publicBaseUrl()', 'auth0ConfigurationFromEnvironment()', 'configuration_invalid', 'storage_unavailable', 'schema_incompatible'] as $required) {
            phase2Assert(str_contains($readinessChecks, $required), "Readiness is missing required dependency check {$required}.");
        }

        phase2Assert(str_contains($observability, 'random_bytes(16)')
            && str_contains($observability, 'RUNTIME_APACHE_REQUEST_ID_PATTERN')
            && str_contains($observability, "\$_SERVER['UNIQUE_ID']")
            && !str_contains($observability, "header('X-Request-ID:")
            && !preg_match('/HTTP_X_REQUEST_ID|X-Forwarded|Forwarded/i', $observability), 'Request correlation accepts untrusted headers or does not share Apache-generated IDs with structured logs.');
        foreach (['request_complete', 'timestamp', 'level', 'request_id', 'method', 'route', 'status', 'duration_ms', 'outcome'] as $field) {
            phase2Assert(str_contains($observability, $field), "Request completion schema is missing {$field}.");
        }
        phase2Assert(str_contains($observability, "in_array(\$routeCategory, ['health', 'readiness'], true)")
            && str_contains($observability, "\$context['low_noise'] && \$status < 400"), 'Health/readiness success log-noise policy is missing.');
        phase2Assert(str_contains($developmentVhost, 'ather_health_probe')
            && str_contains($developmentVhost, 'env=!ather_health_probe')
            && str_contains($productionVhost, 'ather_health_probe')
            && str_contains($productionVhost, 'env=!ather_health_probe'), 'Apache access logs still record successful liveness probes.');
        phase2Assert(str_contains($http, 'runtimeStartRequest($route)') && str_contains($http, 'set_exception_handler'), 'Application requests do not start correlation before their exception boundary.');
        phase2Assert(str_contains($errors, "'event' => 'application_error'") && str_contains($errors, "'request_id'") && !str_contains($errors, 'getMessage()'), 'Application errors are not structured, correlated, and sanitized.');
        phase2Assert(str_contains($security, "'level' => 'notice'") && str_contains($security, "'request_id'") && !preg_match('/session.?id|cookie|password|token|message.?body|authorization/i', $security), 'Security event correlation weakened existing redaction.');

        foreach ([$developmentCompose, $productionCompose] as $compose) {
            phase2Assert(str_contains($compose, 'healthcheck:')
                && str_contains($compose, 'interval:')
                && str_contains($compose, 'timeout:')
                && str_contains($compose, 'retries:')
                && str_contains($compose, 'start_period:')
                && str_contains($compose, 'stop_grace_period:'), 'Web/database healthcheck timing or shutdown grace configuration is incomplete.');
        }
        phase2Assert(str_contains($developmentCompose, 'http://127.0.0.1/health.php')
            && str_contains($productionCompose, 'http://127.0.0.1/health.php')
            && !preg_match('/auth0|oauth|https?:\/\/[^"\']*(?:auth0|oauth)/i', $developmentCompose . $productionCompose), 'Web healthchecks are not local liveness probes.');
        phase2Assert(str_contains($ownerCompose, 'condition: service_healthy')
            && str_contains($ownerCompose, 'curl --fail --silent --show-error --insecure --resolve localhost:443:127.0.0.1 https://localhost/health.php')
            && str_contains($ownerCompose, 'healthcheck:'), 'Owner HTTPS proxy healthcheck does not verify the local proxy path.');
        phase2Assert(str_contains($entrypoint, 'exec docker-php-entrypoint "$@"')
            && str_contains($entrypoint, 'php /var/www/app/database/migrate.php'), 'Entrypoint no longer preserves foreground signal handling and required migrations.');
    }

    private static function read(string $path): string
    {
        $value = file_get_contents(PHASE2_REPOSITORY_ROOT . '/' . $path);
        phase2Assert(is_string($value), "{$path} is unreadable.");

        return $value;
    }
}
