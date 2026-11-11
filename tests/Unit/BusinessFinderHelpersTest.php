<?php

namespace Tests\Unit;

use App\Services\BusinessFinder\HostResolver;
use App\Services\BusinessFinder\RobotsTxt;
use App\Services\BusinessFinder\WebsiteChecker;
use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class BusinessFinderHelpersTest extends TestCase
{
    public function test_phone_digits(): void
    {
        $this->assertSame('9876543210', Phone::digits('+91 98765 43210'));
        $this->assertSame('9876543210', Phone::digits('098765-43210'));
        $this->assertSame('9876543210', Phone::digits('9876543210'));
        $this->assertSame('4423456789', Phone::digits('044 2345 6789'));
        $this->assertNull(Phone::digits('12'));
        $this->assertNull(Phone::digits(null));
    }

    public function test_public_ip_detection(): void
    {
        foreach (['127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.0.1', '169.254.169.254', '100.64.1.1', '0.0.0.0', '::1', 'fe80::1', 'fc00::1', '::ffff:127.0.0.1'] as $ip) {
            $this->assertFalse(HostResolver::isPublicIp($ip), $ip);
        }
        foreach (['8.8.8.8', '93.184.216.34', '2606:4700:4700::1111'] as $ip) {
            $this->assertTrue(HostResolver::isPublicIp($ip), $ip);
        }
    }

    public function test_url_normalisation(): void
    {
        $this->assertSame('https://example.com/', WebsiteChecker::normalize('Example.com'));
        $this->assertSame('http://example.com/a?b=1', WebsiteChecker::normalize('http://example.com/a?b=1'));
        $this->assertNull(WebsiteChecker::normalize('ftp://example.com'));
        $this->assertNull(WebsiteChecker::normalize(''));
        $this->assertNull(WebsiteChecker::normalize('javascript:alert(1)'));
    }

    public function test_robots_rules(): void
    {
        $ua = 'SlingCRM-WebsiteCheck/1.0 (manual)';
        $txt = "User-agent: *\nDisallow: /private\nAllow: /private/open\n\nUser-agent: BadBot\nDisallow: /";

        $this->assertTrue(RobotsTxt::allows($txt, $ua, '/'));
        $this->assertFalse(RobotsTxt::allows($txt, $ua, '/private/x'));
        $this->assertTrue(RobotsTxt::allows($txt, $ua, '/private/open/page'));
        $this->assertFalse(RobotsTxt::allows($txt, 'BadBot/2.0', '/'));
        $this->assertTrue(RobotsTxt::allows('', $ua, '/'));
        $this->assertTrue(RobotsTxt::allows("User-agent: *\nDisallow:", $ua, '/'));
        $this->assertFalse(RobotsTxt::allows("User-agent: slingcrm-websitecheck\nDisallow: /", $ua, '/'));
        $this->assertFalse(RobotsTxt::allows("User-agent: *\nDisallow: /*.php$", $ua, '/index.php'));
        $this->assertTrue(RobotsTxt::allows("User-agent: *\nDisallow: /*.php$", $ua, '/index.php?x=1'));
    }
}
