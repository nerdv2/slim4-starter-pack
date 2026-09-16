<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\TestCase;
use Tests\TestFactory;

final class CustomerTest extends TestCase
{
    /** @var array<string, string> */
    private array $adminHeaders = [];

    /** @var array<string, string> */
    private array $staffHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminHeaders = ['Authorization' => $this->authHeader('admin', 1)];
        $this->staffHeaders = ['Authorization' => $this->authHeader('staff', 2)];
    }

    public function testListRequiresAuthentication(): void
    {
        $response = $this->handle($this->createRequest('GET', '/customer'));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testListIsPaginatedAndFilterable(): void
    {
        $this->seedCustomer(['name' => 'Alpha One', 'status' => 'active', 'company' => 'Alpha Co']);
        $this->seedCustomer(['name' => 'Alpha Two', 'status' => 'active', 'company' => 'Beta Co']);
        $this->seedCustomer(['name' => 'Beta Three', 'status' => 'lead', 'company' => 'Alpha Co']);

        $response = $this->handle($this->createRequest('GET', '/customer', $this->staffHeaders, [], [
            'page' => '1',
            'limit' => '2',
            'status' => 'active',
        ]));
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $payload['data']);
        self::assertSame(2, $payload['total_data']);
        self::assertSame(1, $payload['total_page']);

        $keyword = $this->json($this->handle($this->createRequest('GET', '/customer', $this->staffHeaders, [], [
            'keywords' => 'ALPHA',
        ])));
        self::assertSame(3, $keyword['total_data'], 'Search covers name, email and company.');
    }

    public function testListRejectsAnUnknownStatus(): void
    {
        $response = $this->handle($this->createRequest('GET', '/customer', $this->staffHeaders, [], [
            'status' => 'customer',
        ]));

        self::assertSame(400, $response->getStatusCode());
        self::assertArrayHasKey('status', $this->json($response)['data']);
    }

    public function testShowReturnsTheCustomerOrNotFound(): void
    {
        $customer = $this->seedCustomer(['name' => 'Detail Co']);

        $found = $this->handle($this->createRequest('GET', '/customer/' . $customer['id'], $this->staffHeaders));
        self::assertSame(200, $found->getStatusCode());
        self::assertSame('Detail Co', $this->json($found)['data']['name']);

        $missing = $this->handle($this->createRequest('GET', '/customer/99999', $this->staffHeaders));
        self::assertSame(404, $missing->getStatusCode());
    }

    public function testStaffCannotWrite(): void
    {
        $customer = $this->seedCustomer();

        $create = $this->handle($this->createRequest('POST', '/customer', $this->staffHeaders, ['name' => 'Nope']));
        $update = $this->handle($this->createRequest('PUT', '/customer/' . $customer['id'], $this->staffHeaders, [
            'name' => 'Nope',
        ]));
        $delete = $this->handle($this->createRequest('DELETE', '/customer/' . $customer['id'], $this->staffHeaders));

        self::assertSame(403, $create->getStatusCode());
        self::assertSame(403, $update->getStatusCode());
        self::assertSame(403, $delete->getStatusCode());
    }

    public function testAdminCanCreateACustomer(): void
    {
        $response = $this->handle($this->createRequest('POST', '/customer', $this->adminHeaders, [
            'name' => 'Created Co',
            'email' => 'Created@Example.com',
            'phone' => '+62 811 000',
            'company' => 'Created Group',
            'status' => 'prospect',
            'address' => 'Jl. Contoh 1',
            'notes' => 'Created in a test.',
        ]));
        $payload = $this->json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('created@example.com', $payload['data']['email']);
        self::assertSame('prospect', $payload['data']['status']);

        $stored = $this->connection()->table('customer')->where('customer.id', '=', $payload['data']['id'])->first();
        self::assertNotNull($stored);
        self::assertSame('Created Co', $stored->name);
    }

    public function testCreateRejectsDuplicatesAndInvalidPayloads(): void
    {
        $this->seedCustomer(['name' => 'Duplicate Co', 'email' => 'dupe@example.com']);

        $duplicateName = $this->handle($this->createRequest('POST', '/customer', $this->adminHeaders, [
            'name' => 'Duplicate Co',
        ]));
        $duplicateEmail = $this->handle($this->createRequest('POST', '/customer', $this->adminHeaders, [
            'name' => 'Another Co',
            'email' => 'dupe@example.com',
        ]));
        $invalid = $this->handle($this->createRequest('POST', '/customer', $this->adminHeaders, [
            'name' => '',
            'email' => 'not-an-email',
            'status' => 'nope',
        ]));

        self::assertSame(400, $duplicateName->getStatusCode());
        self::assertArrayHasKey('name', $this->json($duplicateName)['data']);
        self::assertSame(400, $duplicateEmail->getStatusCode());
        self::assertArrayHasKey('email', $this->json($duplicateEmail)['data']);
        self::assertSame(400, $invalid->getStatusCode());
        self::assertSame(
            ['name', 'email', 'status'],
            array_keys($this->json($invalid)['data'])
        );
    }

    public function testAdminCanUpdateACustomer(): void
    {
        $customer = $this->seedCustomer(['name' => 'Old Name', 'status' => 'lead']);

        $response = $this->handle($this->createRequest(
            'PUT',
            '/customer/' . $customer['id'],
            $this->adminHeaders,
            ['name' => 'New Name', 'status' => 'active', 'notes' => 'Updated.']
        ));
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('New Name', $payload['data']['name']);
        self::assertSame('active', $payload['data']['status']);

        $missing = $this->handle($this->createRequest('PUT', '/customer/99999', $this->adminHeaders, [
            'name' => 'Ghost',
        ]));
        self::assertSame(404, $missing->getStatusCode());
    }

    public function testUpdateRejectsADuplicateEmailOfAnotherCustomer(): void
    {
        $first = $this->seedCustomer(['email' => 'first@example.com']);
        $this->seedCustomer(['email' => 'second@example.com']);

        $response = $this->handle($this->createRequest(
            'PUT',
            '/customer/' . $first['id'],
            $this->adminHeaders,
            ['name' => $first['name'], 'email' => 'second@example.com']
        ));

        self::assertSame(400, $response->getStatusCode());
        self::assertArrayHasKey('email', $this->json($response)['data']);
    }

    public function testDeleteSoftDeletesTheCustomer(): void
    {
        $customer = $this->seedCustomer();

        $response = $this->handle($this->createRequest(
            'DELETE',
            '/customer/' . $customer['id'],
            $this->adminHeaders
        ));
        self::assertSame(200, $response->getStatusCode());

        $stored = $this->connection()->table('customer')->where('customer.id', '=', $customer['id'])->first();
        self::assertNotNull($stored);
        self::assertNotNull($stored->deleted_at);

        $list = $this->json($this->handle($this->createRequest('GET', '/customer', $this->staffHeaders)));
        self::assertSame(0, $list['total_data']);
    }

    public function testStatsSummarizeCustomersByStatus(): void
    {
        $this->seedCustomer(['status' => 'active']);
        $this->seedCustomer(['status' => 'active']);
        $this->seedCustomer(['status' => 'lead']);

        $response = $this->handle($this->createRequest('GET', '/customer/stats', $this->staffHeaders));
        $data = $this->json($response)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $data['total']);
        self::assertSame(2, $data['by_status']['active']);
        self::assertSame(1, $data['by_status']['lead']);
        self::assertSame(0, $data['by_status']['prospect']);
        self::assertSame(3, $data['created_last_7_days']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function seedCustomer(array $overrides = []): array
    {
        $row = TestFactory::customer($overrides);
        $row['id'] = (int) $this->connection()->table('customer')->insertGetId($row);

        return $row;
    }
}
