<?php
declare(strict_types=1);
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagImportExport\Tests\Unit\Setup\DefaultProfiles;

use PHPUnit\Framework\TestCase;
use SwagImportExport\Setup\DefaultProfiles\MinimalProductVariantsProfile;
use SwagImportExport\Setup\DefaultProfiles\ProfileMetaData;

class MinimalProductVariantsProfileTest extends TestCase
{
    use DefaultProfileTestCaseTrait;

    public function testItCanBeCreated(): void
    {
        $minimalProductVariantsProfile = $this->createMinimalProductVariantsProfile();

        static::assertInstanceOf(MinimalProductVariantsProfile::class, $minimalProductVariantsProfile);
        static::assertInstanceOf(\JsonSerializable::class, $minimalProductVariantsProfile);
        static::assertInstanceOf(ProfileMetaData::class, $minimalProductVariantsProfile);
    }

    public function testItShouldReturnValidProfileTree(): void
    {
        $minimalProductVariantsProfile = $this->createMinimalProductVariantsProfile();

        $this->walkRecursive($minimalProductVariantsProfile->jsonSerialize(), function ($node): void {
            $this->assertArrayHasKey('id', $node, 'Current array: ' . \print_r($node, true));
            $this->assertArrayHasKey('name', $node, 'Current array: ' . \print_r($node, true));
            $this->assertArrayHasKey('type', $node, 'Current array: ' . \print_r($node, true));
        });
    }

    private function createMinimalProductVariantsProfile(): MinimalProductVariantsProfile
    {
        return new MinimalProductVariantsProfile();
    }
}
