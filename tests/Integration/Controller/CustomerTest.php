<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\TestCase;
use Tests\TestFactory;

final class CustomerTest extends TestCase
{
    public function testListIsPublicAndPaginated(): void
    {
        $this->seedCustomers(['Alpha One', 'Beta Two', 'Gamma Three', 'Delta Four', 'Epsilon Five']);

        $response = $this->handle($this->createRequest('GET', '/customer', [], [], [
            'page' => '1',
            'limit' => '2',
        ]));
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['status']);
        self::assertCount(2, $payload['data']);
        self::assertSame(5, $payload['total_data']);
        self::assertSame(3, $payload['total_page']);
    }

    public function testListClampsPaginationParameters(): void
    {
        $this->seedCustomers(['Only One']);

        $payload = $this->json($this->handle($this->createRequest('GET', '/customer', [], [], [
            'page' => '0',
            'limit' => '9999',
        ])));

        self::assertCount(1, $payload['data']);
        self::assertSame(1, $payload['total_page']);
        self::assertSame(1, $payload['total_data']);
    }

    public function testKeywordSearchIsCaseInsensitive(): void
    {
        $this->seedCustomers(['Alpha One', 'Beta Two']);

        $payload = $this->json($this->handle($this->createRequest('GET', '/customer', [], [], [
            'keywords' => 'ALPHA',
        ])));

        self::assertSame(1, $payload['total_data']);
        self::assertSame('Alpha One', $payload['data'][0]['name']);
    }

    public function testAddRequiresAuthentication(): void
    {
        $response = $this->handle($this->createRequest('POST', '/customer/add', [], ['name' => 'Acme']));
        $payload = $this->json($response);

        self::assertSame(401, $response->getStatusCode());
        self::assertFalse($payload['status']);
    }

    public function testBrokenTokenIsRejected(): void
    {
        $response = $this->handle($this->createRequest(
            'POST',
            '/customer/add',
            ['Authorization' => 'broken'],
            ['name' => 'Acme']
        ));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testNonAdminTokenIsForbidden(): void
    {
        $response = $this->handle($this->createRequest(
            'POST',
            '/customer/add',
            ['Authorization' => $this->authHeader('member', 2)],
            ['name' => 'Acme']
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAddValidatesTheName(): void
    {
        $response = $this->handle($this->createRequest(
            'POST',
            '/customer/add',
            ['Authorization' => $this->authHeader()],
            ['name' => '   ']
        ));
        $payload = $this->json($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Name is required', $payload['message']);
    }

    public function testAddRejectsDuplicateNames(): void
    {
        $this->seedCustomers(['Acme']);

        $response = $this->handle($this->createRequest(
            'POST',
            '/customer/add',
            ['Authorization' => $this->authHeader()],
            ['name' => 'Acme']
        ));
        $payload = $this->json($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('already exists', $payload['message']);
    }

    public function testAdminCanCreateRenameAndDelete(): void
    {
        $created = $this->handle($this->createRequest(
            'POST',
            '/customer/add',
            ['Authorization' => $this->authHeader()],
            ['name' => 'Acme']
        ));
        self::assertSame(200, $created->getStatusCode());

        $row = $this->connection()->table('customer')->where('customer.name', '=', 'Acme')->first();
        self::assertNotNull($row);
        $id = (string) $row->id;

        $renamed = $this->handle($this->createRequest(
            'POST',
            '/customer/update',
            ['Authorization' => $this->authHeader()],
            ['id' => $id, 'name' => 'Acme Renamed']
        ));
        self::assertSame(200, $renamed->getStatusCode());

        $deleted = $this->handle($this->createRequest(
            'DELETE',
            '/customer/delete',
            ['Authorization' => $this->authHeader()],
            ['id' => $id]
        ));
        self::assertSame(200, $deleted->getStatusCode());

        $payload = $this->json($this->handle($this->createRequest('GET', '/customer')));
        self::assertSame(0, $payload['total_data']);
    }

    public function testReadReplicaFallsBackToThePrimary(): void
    {
        $this->seedCustomers(['Alpha One']);

        self::assertSame(1, $this->readConnection()->table('customer')->count());
    }

    /**
     * @param list<string> $names
     */
    private function seedCustomers(array $names): void
    {
        foreach ($names as $name) {
            $this->connection()->table('customer')->insert(TestFactory::customer(['name' => $name]));
        }
    }
}
