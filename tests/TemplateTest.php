<?php

declare(strict_types=1);

namespace Samwilson\PhpFlickrCli\Test;

use PHPUnit\Framework\TestCase;
use Samwilson\PhpFlickr\PhpFlickr;
use Samwilson\PhpFlickrCli\Template;
use Symfony\Component\Filesystem\Filesystem;

class TemplateTest extends TestCase
{
    /** @var Filesystem */
    protected $fs;

    /** @var string */
    protected $tmpDir;

    /** @var string[][] */
    protected $testPhotoInfo;

    /**
     * Create temp directory and set up some test data.
     */
    public function setUp() : void
    {
        parent::setUp();

        $this->fs = new Filesystem();
        $this->tmpDir = __DIR__ . '/tmp';
        $this->fs->mkdir($this->tmpDir);
        $this->testPhotoInfo = [
            'b_photo' => [
                'id' => '123',
                'dates' => [
                    'taken' => '2019-01-01 13:45:00',
                    'takengranularity' => 0,
                ],
                'title' => 'Lorem ipsum',
                'originalformat' => 'png',
                'farm' => 'f',
                'server' => 's',
                'originalsecret' => 'os',
            ],
            'a_photo' => [
                'id' => '456',
                'dates' => [
                    'taken' => '2017-11-01 08:12:01',
                    'takengranularity' => 2,
                ],
                'title' => 'Foobar',
                'originalformat' => 'jpg',
                'farm' => 'f',
                'server' => 's',
                'originalsecret' => 'os',
            ],
        ];
    }

    /**
     * Remove the temp directory.
     */
    public function tearDown() : void
    {
        $this->fs->remove(__DIR__ . '/tmp');

        parent::tearDown();
    }

    /**
     * @covers \Samwilson\PhpFlickrCli\Template::render
     */
    public function testCreation() : void
    {
        $tpl = new Template('archive', $this->tmpDir, new PhpFlickr('', ''));
        $tpl->render($this->testPhotoInfo);
        static::assertFileExists($this->tmpDir . '/photos.csv');
        static::assertEquals(
            "id,date_taken,title\n456,2018-12-01 01:12:00,Foobar\n123,2019-01-01 13:45:00,Lorem ipsum\n",
            file_get_contents($this->tmpDir . '/photos.csv')
        );
        static::assertEquals(
            "id: 123\ndate_taken: 2019-01-01 13:45:00\ntitle: Lorem ipsum\n",
            file_get_contents($this->tmpDir . '/20/2c/123.yml')
        );
        static::assertEquals(
            "id: 456\ndate_taken: 2018-12-01 01:12:00\ntitle: Foobar\n",
            file_get_contents($this->tmpDir . '/25/0c/456.yml')
        );
    }
}
