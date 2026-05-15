<?php

namespace App\Enums;

enum Role: string
{
    // CLIENT is for reseller end-users (Phase 6). They're scoped to a single
    // SubTeam + Project and cannot see anything else, regardless of how their
    // user record happens to be attached.
    case CLIENT = 'client';
    case MEMBER = 'member';
    case ADMIN = 'admin';
    case OWNER = 'owner';

    public function rank(): int
    {
        return match ($this) {
            self::CLIENT => 0,
            self::MEMBER => 1,
            self::ADMIN => 2,
            self::OWNER => 3,
        };
    }

    public function lt(Role|string $role): bool
    {
        if (is_string($role)) {
            $role = Role::from($role);
        }

        return $this->rank() < $role->rank();
    }

    public function gt(Role|string $role): bool
    {
        if (is_string($role)) {
            $role = Role::from($role);
        }

        return $this->rank() > $role->rank();
    }
}
