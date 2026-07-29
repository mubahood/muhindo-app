<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The one place that turns a chosen account type ("student" | "client" | "both")
 * into capability flags and the records those capabilities need. Registration,
 * the account screen and the admin JSON drawer all go through here so a person
 * who becomes a client on their profile ends up in exactly the same state as one
 * who chose "client" at sign-up.
 */
class AccountService
{
    public const TYPES = ['student', 'client', 'both'];

    /**
     * Apply a chosen account type to an account.
     *
     * Capabilities are only ever added or kept — never silently revoked. Dropping
     * client access from someone who owns projects and invoices, or student
     * access from someone with enrollments, would orphan that work and hide it
     * behind a menu they can no longer reach, so existing access is retained and
     * the caller is told via the return value.
     *
     * @return list<string> The capabilities that were kept despite not being asked for
     *                      (empty when the requested type was applied exactly).
     */
    public function applyAccountType(User $user, string $type): array
    {
        $wantsStudent = in_array($type, ['student', 'both'], true);
        $wantsClient = in_array($type, ['client', 'both'], true);

        $keptStudent = ! $wantsStudent && $user->enrollments()->exists();
        $keptClient = ! $wantsClient && $this->hasClientWork($user);

        $user->update([
            'is_student' => $wantsStudent || $keptStudent,
            'is_client' => $wantsClient || $keptClient,
        ]);

        if ($user->is_client) {
            $this->ensureClientProfile($user);
        }

        return array_values(array_filter([
            $keptStudent ? 'student' : null,
            $keptClient ? 'client' : null,
        ]));
    }

    /**
     * Whether dropping client access would strand something real.
     *
     * Deliberately not "does a client record exist" — choosing "both" creates an
     * empty client profile up front, and treating that container as work would
     * trap anyone who ever ticked the box into keeping a side of the app they
     * don't use. Only actual projects or invoices count.
     */
    private function hasClientWork(User $user): bool
    {
        $client = $user->client;

        return $client !== null
            && ($client->projects()->exists() || $client->invoices()->exists());
    }

    /**
     * A client needs a client record to own projects and be invoiced against.
     * Mirrors Admin\ClientController::store's numbering so the self-service path
     * and the admin-created path produce the same shape.
     */
    public function ensureClientProfile(User $user): void
    {
        if ($user->client()->exists()) {
            return;
        }

        Client::create([
            'uuid' => (string) Str::uuid(),
            'client_number' => 'CL-'.now()->format('Y').'-'.str_pad((string) (Client::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $user->unsetRelation('client');
    }

    /** The account type a user currently holds, as the value the forms post back. */
    public function currentType(User $user): string
    {
        return match ($user->accountTypes()) {
            ['student', 'client'] => 'both',
            ['client'] => 'client',
            default => 'student',
        };
    }
}
