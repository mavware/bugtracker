<?php

namespace App\Actions\Surveillance;

/**
 * One room label together with the sessions that carry it. Identified by a hash
 * of owner, property and name rather than an id, because a room has no row of
 * its own — it is a string repeated across sessions.
 */
final class RoomLabel
{
    public function __construct(
        public string $key,
        public int $userId,
        public ?int $customerId,
        public string $owner,
        public ?string $customer,
        public string $room,
        public int $sessionsCount,
    ) {}
}
