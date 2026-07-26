<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Serves the API v1 OpenAPI 3.0 document. Hand-maintained alongside the
 * routes; the standard ApiResponse envelope is described once as a reusable
 * schema. Public — it's just docs.
 */
class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json($this->spec());
    }

    /** @return array<string,mixed> */
    private function spec(): array
    {
        $envelope = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'code' => ['type' => 'string'],
                'message' => ['type' => 'string', 'nullable' => true],
                'data' => ['nullable' => true],
                'errors' => ['type' => 'object', 'nullable' => true],
            ],
        ];

        $secured = [['bearerAuth' => []]];
        $ok = ['description' => 'Success envelope', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Envelope']]]];

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Muhindo Mubaraka API',
                'version' => '1.0.0',
                'description' => 'Sanctum-authenticated JSON API for the mobile client. Every response uses the '
                    .'standard envelope {success, code, message, data, errors}; lists add a `meta` block.',
            ],
            'servers' => [['url' => url('/api/v1'), 'description' => 'v1']],
            'components' => [
                'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Sanctum personal access token']],
                'schemas' => ['Envelope' => $envelope],
            ],
            'paths' => [
                '/auth/login' => ['post' => [
                    'summary' => 'Sign in and receive a token', 'tags' => ['Auth'],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['email', 'password'], 'properties' => ['email' => ['type' => 'string'], 'password' => ['type' => 'string'], 'device_name' => ['type' => 'string']]]]]],
                    'responses' => ['200' => $ok, '401' => $ok, '403' => $ok],
                ]],
                '/auth/me' => ['get' => ['summary' => 'Current user', 'tags' => ['Auth'], 'security' => $secured, 'responses' => ['200' => $ok, '401' => $ok]]],
                '/auth/logout' => ['post' => ['summary' => 'Revoke the current token', 'tags' => ['Auth'], 'security' => $secured, 'responses' => ['200' => $ok]]],

                '/courses' => ['get' => ['summary' => 'List published courses', 'tags' => ['Courses'], 'responses' => ['200' => $ok]]],
                '/courses/{id}' => ['get' => ['summary' => 'Get a course (with modules & lessons)', 'tags' => ['Courses'], 'parameters' => [$this->path('id')], 'responses' => ['200' => $ok, '404' => $ok]]],
                '/courses/{id}/enroll' => ['post' => ['summary' => 'Enrol in a free course', 'tags' => ['Courses'], 'security' => $secured, 'parameters' => [$this->path('id')], 'responses' => ['201' => $ok, '402' => $ok]]],
                '/lessons/{id}/complete' => ['post' => ['summary' => 'Mark a lesson complete', 'tags' => ['Courses'], 'security' => $secured, 'parameters' => [$this->path('id')], 'responses' => ['200' => $ok]]],
                '/my/enrollments' => ['get' => ['summary' => "The signed-in student's enrollments", 'tags' => ['Courses'], 'security' => $secured, 'responses' => ['200' => $ok]]],

                '/my/projects' => ['get' => ['summary' => "The signed-in client's projects", 'tags' => ['Projects'], 'security' => $secured, 'responses' => ['200' => $ok]]],
                '/projects/{id}' => ['get' => ['summary' => 'Get a project (tasks, updates, documents)', 'tags' => ['Projects'], 'security' => $secured, 'parameters' => [$this->path('id')], 'responses' => ['200' => $ok, '403' => $ok]]],

                '/invoices' => ['get' => ['summary' => "List the signed-in user's invoices", 'tags' => ['Billing'], 'security' => $secured, 'parameters' => [$this->q('status')], 'responses' => ['200' => $ok]]],
                '/invoices/{id}' => ['get' => ['summary' => 'Get an invoice', 'tags' => ['Billing'], 'security' => $secured, 'parameters' => [$this->path('id')], 'responses' => ['200' => $ok, '403' => $ok]]],

                '/device-tokens' => ['post' => ['summary' => 'Register a push-notification device token', 'tags' => ['Notifications'], 'security' => $secured, 'responses' => ['201' => $ok]]],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function q(string $name): array
    {
        return ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']];
    }

    /** @return array<string,mixed> */
    private function path(string $name): array
    {
        return ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
    }
}
