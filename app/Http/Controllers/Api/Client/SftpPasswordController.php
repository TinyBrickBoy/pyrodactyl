<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Hash;

class SftpPasswordController extends ClientApiController
{
    /**
     * How long a generated temporary SFTP password remains valid, in hours.
     */
    private const EXPIRY_HOURS = 24;

    /**
     * Length of the generated temporary SFTP password.
     */
    private const PASSWORD_LENGTH = 24;

    /**
     * Returns metadata about the current temporary SFTP password, if any.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = $user->sftp_password
            && $user->sftp_password_expires_at !== null
            && $user->sftp_password_expires_at->isFuture();

        return new JsonResponse([
            'data' => [
                'active' => $active,
                'expires_at' => $active ? $user->sftp_password_expires_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Generates a new temporary SFTP password. The plaintext value is only ever
     * returned here — it is stored hashed and cannot be retrieved again.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $password = Str::password(self::PASSWORD_LENGTH, symbols: false);
        $expiresAt = Carbon::now()->addHours(self::EXPIRY_HOURS);

        $user->forceFill([
            'sftp_password' => Hash::make($password),
            'sftp_password_expires_at' => $expiresAt,
        ])->saveOrFail();

        Activity::event('user:sftp-password.create')->subject($user)->log();

        return new JsonResponse([
            'data' => [
                'password' => $password,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    /**
     * Revokes the current temporary SFTP password.
     */
    public function delete(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'sftp_password' => null,
            'sftp_password_expires_at' => null,
        ])->saveOrFail();

        Activity::event('user:sftp-password.delete')->subject($user)->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
