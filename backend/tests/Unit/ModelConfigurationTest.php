<?php

namespace Tests\Unit;

use App\Models\IntegratedTerminal;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModelConfigurationTest extends TestCase
{
    public function test_user_sensitive_attributes_are_hidden(): void
    {
        $user = new User(['username' => 'hsse', 'password' => 'secret-value']);
        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
        $this->assertArrayNotHasKey('public_key', $serialized);
    }

    public function test_user_password_is_hashed_by_cast(): void
    {
        $user = new User(['password' => 'rahasia-123']);

        $this->assertNotSame('rahasia-123', $user->getAttributes()['password']);
        $this->assertTrue(Hash::check('rahasia-123', $user->getAttributes()['password']));
    }

    public function test_terminal_active_flag_is_cast_to_boolean(): void
    {
        $terminal = new IntegratedTerminal(['is_active' => 1]);

        $this->assertTrue($terminal->is_active);
    }
}
