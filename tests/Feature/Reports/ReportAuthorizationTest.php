<?php

namespace Tests\Feature\Reports;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class ReportAuthorizationTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_guest_is_redirected_from_admin_and_counter_reports(): void
    {
        $this->get('/admin/reports/transaction')->assertRedirect('/admin/login');
        $this->get('/counter/reports/transaction')->assertRedirect('/counter/login');
    }

    public function test_admin_can_open_every_admin_report(): void
    {
        $admin = $this->userWithRole('admin');

        foreach ([
            '/admin/reports/item-summary',
            '/admin/reports/bill-summary',
            '/admin/reports/bill-status',
            '/admin/reports/transaction',
            '/admin/reports/payment-mode',
            '/admin/reports/waiter',
            '/admin/reports/order-wise',
            '/admin/reports/item-wise',
            '/admin/reports/customer',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_counter_can_open_counter_reports_but_not_admin_or_thermal_routes(): void
    {
        $counter = $this->userWithRole('counter');

        foreach ([
            '/counter/reports/transaction',
            '/counter/reports/order-wise',
            '/counter/reports/item-wise',
            '/counter/reports/waiter',
            '/counter/reports/payment-mode',
            '/counter/reports/bill-status',
            '/counter/reports/customer',
        ] as $url) {
            $this->actingAs($counter)->get($url)->assertOk();
        }

        $this->actingAs($counter)->get('/admin/reports/transaction')->assertForbidden();
        $this->actingAs($counter)->get('/counter/reports/item-summary')->assertNotFound();
        $this->actingAs($counter)->get('/counter/reports/bill-summary')->assertNotFound();
    }

    public function test_waiter_and_captain_cannot_open_report_pages(): void
    {
        $this->actingAs($this->userWithRole('waiter'))
            ->get('/admin/reports/transaction')
            ->assertForbidden();

        $this->actingAs($this->userWithRole('captain'))
            ->get('/counter/reports/transaction')
            ->assertForbidden();
    }

    public function test_admin_without_reports_view_is_forbidden(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'reports.view')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/reports/transaction')
            ->assertForbidden();
    }

    public function test_visiting_a_report_does_not_write_payments_or_bills(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/reports/transaction')
            ->assertOk();

        $this->assertDatabaseCount('bills', 0);
        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertDatabaseCount('orders', 0);
    }
}
