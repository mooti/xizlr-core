<?php
namespace Mooti\Test\Unit\Xizlr\Core;

use Mooti\Xizlr\Core\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function uuidV4Succeeds()
    {
        self::assertRegExp('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', Util::uuidV4());
    }
}