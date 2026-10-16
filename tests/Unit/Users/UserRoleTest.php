<?php

namespace Tests\Unit\Users;

use App\Domains\Users\User\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_manage_implies_view_and_manager_has_everything(): void
    {
        $this->assertTrue(UserRole::Housekeeping->can('housekeeping.view'));
        $this->assertFalse(UserRole::Housekeeping->can('payments.view'));
        $this->assertTrue(UserRole::Manager->can('anything.manage'));
        $this->assertFalse(UserRole::Owner->can('facilities.manage'));
        $this->assertFalse(UserRole::Guest->can('dashboard.view'));
    }
}
