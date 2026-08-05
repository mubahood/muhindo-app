<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Serves the API v1 OpenAPI 3.0 document. Hand-maintained alongside the
 * routes; the standard ApiResponse envelope is described once as a reusable
 * schema. Public, it's just docs.
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
                '/lessons/{id}/heartbeat' => ['post' => ['summary' => 'Report ~15s of watch time (watch_seconds/resume/min_watch completion)', 'tags' => ['Courses'], 'security' => $secured, 'parameters' => [$this->path('id')], 'responses' => ['200' => $ok]]],
                '/my/enrollments' => ['get' => ['summary' => "The signed-in student's enrollments", 'tags' => ['Courses'], 'security' => $secured, 'responses' => ['200' => $ok]]],

                '/courses/{course}/lessons/{id}' => ['get' => ['summary' => 'Lesson detail: content, video source, resume position', 'tags' => ['Courses'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['200' => $ok, '403' => $ok]]],
                '/courses/{course}/lessons/{id}/video-stream' => ['get' => ['summary' => 'Signed, time-limited self-hosted video stream (no bearer token needed. The signature is the credential)', 'tags' => ['Courses'], 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['200' => ['description' => 'Video file'], '403' => $ok, '404' => $ok]]],
                '/courses/{course}/lessons/{id}/notes' => [
                    'get' => ['summary' => "List the student's notes on a lesson", 'tags' => ['Courses'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['200' => $ok]],
                    'post' => ['summary' => 'Add a timestamped note', 'tags' => ['Courses'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['201' => $ok]],
                ],
                '/courses/{course}/lessons/{id}/notes/{note}' => ['delete' => ['summary' => 'Delete a note', 'tags' => ['Courses'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id'), $this->path('note')], 'responses' => ['200' => $ok, '404' => $ok]]],

                '/courses/{course}/quizzes' => ['get' => ['summary' => 'List published quizzes with the latest attempt', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course')], 'responses' => ['200' => $ok]]],
                '/courses/{course}/quizzes/{id}' => ['get' => ['summary' => 'Quiz intro: attempts used, in-progress/best attempt', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['200' => $ok]]],
                '/courses/{course}/quizzes/{id}/start' => ['post' => ['summary' => 'Start (or resume) an attempt', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['201' => $ok]]],
                '/courses/{course}/quizzes/{id}/attempts/{attempt}' => ['get' => ['summary' => 'The frozen question order for an in-progress attempt, never exposes which option is correct', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id'), $this->path('attempt')], 'responses' => ['200' => $ok, '409' => $ok]]],
                '/courses/{course}/quizzes/{id}/attempts/{attempt}/questions/{question}/answer' => ['post' => ['summary' => 'Autosave one answer', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id'), $this->path('attempt'), $this->path('question')], 'responses' => ['200' => $ok]]],
                '/courses/{course}/quizzes/{id}/attempts/{attempt}/submit' => ['post' => ['summary' => 'Submit and grade the attempt', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id'), $this->path('attempt')], 'responses' => ['200' => $ok]]],
                '/courses/{course}/quizzes/{id}/attempts/{attempt}/review' => ['get' => ['summary' => 'Per-question feedback, gated by the quiz feedback_mode', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id'), $this->path('attempt')], 'responses' => ['200' => $ok]]],

                '/courses/{course}/assignments' => ['get' => ['summary' => 'List published assignments with the latest submission', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course')], 'responses' => ['200' => $ok]]],
                '/courses/{course}/assignments/{id}' => ['get' => ['summary' => 'Assignment detail, latest + submission history', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['200' => $ok]]],
                '/courses/{course}/assignments/{id}/draft' => ['post' => ['summary' => 'Save a draft submission', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['200' => $ok]]],
                '/courses/{course}/assignments/{id}/submit' => ['post' => ['summary' => 'Submit for grading', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id')], 'responses' => ['200' => $ok]]],
                '/courses/{course}/assignments/{id}/submissions/{submission}/download' => ['get' => ['summary' => "Download the student's own submitted file", 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course'), $this->path('id'), $this->path('submission')], 'responses' => ['200' => ['description' => 'File'], '404' => $ok]]],

                '/courses/{course}/grades' => ['get' => ['summary' => 'Per-item grades + current course grade', 'tags' => ['Assessment'], 'security' => $secured, 'parameters' => [$this->path('course')], 'responses' => ['200' => $ok]]],

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
