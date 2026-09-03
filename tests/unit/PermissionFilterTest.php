<?php

use App\Filters\Permission;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;
use Config\UserAgents;
use CodeIgniter\Test\CIUnitTestCase;
/** @internal */
final class PermissionFilterTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        session()->remove('permissions');
        parent::tearDown();
    }
    public function testNormalRequestIsRedirectedWhenPermissionIsMissing(): void
    {
        session()->set('permissions', []);
        $request = new IncomingRequest(new App(), new URI('http://example.com/'), 'php://memory', new UserAgent(new UserAgents()));
        $result = (new Permission())->before($request, ['prescription.review']);
        $this->assertNotNull($result);
        $this->assertSame(base_url('permission-denied'), $result->getHeaderLine('Location'));
    }
    public function testRequestIsAllowedWhenPermissionExists(): void
    {
        session()->set('permissions', ['prescription.review']);
        $request = new IncomingRequest(new App(), new URI('http://example.com/'), 'php://memory', new UserAgent(new UserAgents()));
        $this->assertNull((new Permission())->before($request, ['prescription.review']));
    }
}
