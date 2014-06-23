<?php

namespace s9e\MediaWatch;

use PHPUnit_Extensions_Selenium2TestCase;
use RuntimeException;
use s9e\TextFormatter\Configurator;

class Test extends PHPUnit_Extensions_Selenium2TestCase
{
	static $dssim;

	public static function setUpBeforeClass()
	{
		if (file_exists('/tmp/dssim-master/dssim'))
		{
			self::$dssim = '/tmp/dssim-master/dssim';
		}
		else
		{
			self::$dssim = exec('which dssim');

			if (!self::$dssim)
			{
				throw new RuntimeException('Could not find dssim');
			}
		}
	}

	public function setUp()
	{
		$desiredCapabilities = [
			'chromeOptions' => [
				'binary' => '/usr/bin/google-chrome-unstable',
				'args'   => ['app=file:///tmp', 'no-referrers']
			]
		];

		if (!empty($_SERVER['TRAVIS_JOB_NUMBER']))
		{
			$this->setHost($_SERVER['SAUCE_USERNAME'] . ':' . $_SERVER['SAUCE_ACCESS_KEY'] . '@ondemand.saucelabs.com');
			$this->setBrowserUrl('http://127.0.0.1:8000/');

			$desiredCapabilities['tunnel-identifier'] = $_SERVER['TRAVIS_JOB_NUMBER'];
		}
		else
		{
			$this->setBrowserUrl('file://' . sys_get_temp_dir() . '/');
		}

		$this->setDesiredCapabilities($desiredCapabilities);
		$this->setBrowser('chrome');
	}

	/**
	* @dataProvider getBrowserRenderingTests
	*/
	public function testBrowserRendering($filename, $url, $max = 0.11)
	{
		$filepathHtml     = sys_get_temp_dir() . '/' . $filename . '.html';
		$filepathActual   = sys_get_temp_dir() . '/' . $filename . '.png';
		$filepathExpected = __DIR__ . '/screenshots/' . $filename . '.png';

		if (!empty($_SERVER['TRAVIS']) && file_exists($filepathHtml))
		{
			$this->markTestSkipped('Already exists');
		}

		if (!empty($_SERVER['MAINTENANCE']) && file_exists($filepathExpected))
		{
			$this->markTestSkipped('Maintenance');
		}

		$window = $this->prepareSession()->currentWindow();
		$window->size(['width' => 800, 'height' => 600]);

		$configurator = new Configurator;
		$configurator->cacheDir = sys_get_temp_dir();
		$configurator->MediaEmbed->add(substr($filename, 0, strcspn($filename, '-')));

		$text = $url;
		$xml  = $configurator->getParser()->parse($text);
		$html = $configurator->getRenderer()->render($xml);

		$html = '<!DOCTYPE html><html><head><style>body{margin:0;background:#000}</style><link rel="icon" href="data:;base64,="><base href="http://localhost/"></head><body><div>' . $html . '</div></body></html>';
		file_put_contents($filepathHtml, $html);

		$this->url($filename . '.html');

		if (!file_exists($filepathExpected))
		{
			sleep(10);
			file_put_contents($filepathExpected, $this->currentScreenshot());

			$this->markTestSkipped('Missing expected screenshot ' . $filename);
		}

		list($width, $height) = getimagesize($filepathExpected);

		$i = 5;
		do
		{
			sleep(3);

			$gd = imagecreatefromstring($this->currentScreenshot());
			$gd = imagecrop($gd, ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height]);

			imagepng($gd, $filepathActual, 0, PNG_NO_FILTER);

			$output = exec(self::$dssim . ' ' . escapeshellarg($filepathExpected) . ' ' . escapeshellarg($filepathActual) . ' 2>&1', $arr, $error);

			if ($error || !preg_match('/^-?[\\d.]+/', $output, $m))
			{
				$this->fail($output);
			}

			$ssim = abs($m[0]);
		}
		while (--$i && $ssim > $max);

		if (empty($_SERVER['TRAVIS']))
		{
			unlink($filepathHtml);
		}
		unlink($filepathActual);

		$this->assertLessThanOrEqual($max, $ssim);
	}

	public function getBrowserRenderingTests()
	{
		$tests = [
			[
				'abcnews-1',
				'http://abcnews.go.com/WNN/video/dog-goes-wild-when-owner-leaves-22936610'
			],
			[
				'amazon-ca',
				'http://www.amazon.ca/gp/product/B00GQT1LNO/',
				0.308
			],
			[
				'amazon-jp',
				'http://www.amazon.co.jp/gp/product/B003AKZ6I8/',
				0.30
			],
			[
				'amazon-uk',
				'http://www.amazon.co.uk/gp/product/B00BET0NR6/',
				0.25
			],
			[
				'amazon-1',
				'http://www.amazon.com/dp/B002MUC0ZY',
				0.26
			],
			[
				'amazon-2',
				'http://www.amazon.com/The-BeerBelly-200-001-80-Ounce-Belly/dp/B001RB2CXY/',
				0.25
			],
			[
				'amazon-3',
				'http://www.amazon.com/gp/product/B0094H8H7I',
				0.25
			],
			[
				'amazon-de',
				'http://www.amazon.de/Netgear-WN3100RP-100PES-Repeater-integrierte-Steckdose/dp/B00ET2LTE6/',
				0.25
			],
			[
				'amazon-fr',
				'http://www.amazon.fr/Vans-Authentic-Baskets-mixte-adulte/dp/B005NIKPAY/',
				0.261
			],
			[
				'amazon-it',
				'http://www.amazon.it/gp/product/B00JGOMIP6/',
				0.25
			],
			[
				'audiomack-song',
				'http://www.audiomack.com/song/your-music-fix/jammin-kungs-remix-1'
			],
			[
				'audiomack-album',
				'http://www.audiomack.com/album/chance-the-rapper/acid-rap'
			],
			[
				'bandcamp-album',
				'http://proleter.bandcamp.com/album/curses-from-past-times-ep'
			],
			[
				'bandcamp-song',
				'http://proleter.bandcamp.com/track/april-showers'
			],
			[
				'blip-1',
				'http://blip.tv/blip-on-blip/damian-bruno-and-vinyl-rewind-blip-on-blip-58-5226104'
			],
			[
				'blip-2',
				'http://blip.tv/play/hr4jg5i1MwA.x?p=1'
			],
			/*[
				// Break.com has a bug that prevents it from being embedded. They don't seem eager
				// to fix it so I guess we don't test Break.com
				'break',
				'http://www.break.com/video/video-game-playing-frog-wants-more-2278131'
			],*/
			[
				'cbsnews-1',
				'http://www.cbsnews.com/video/watch/?id=50156501n'
			],
			[
				'cbsnews-2',
				'http://www.cbsnews.com/videos/is-the-us-stock-market-rigged'
			],
			[
				'cnbc-1',
				'http://video.cnbc.com/gallery/?video=3000269279'
			],
			[
				'cnn-1',
				'http://edition.cnn.com/video/data/2.0/video/showbiz/2013/10/25/spc-preview-savages-stephen-king-thor.cnn.html'
			],
			[
				'cnn-2',
				'http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html'
			],
			[
				'colbertnation',
				'http://thecolbertreport.cc.com/videos/gh6urb/neil-degrasse-tyson-pt--1'
			],
			[
				'collegehumor',
				'http://www.collegehumor.com/video/1181601/more-than-friends'
			],
			[
				'comedycentral-1',
				'http://www.cc.com/video-clips/uu5qz4/key-and-peele-dueling-hats'
			],
			[
				'comedycentral-2',
				'http://www.comedycentral.com/video-clips/uu5qz4/key-and-peele-dueling-hats'
			],
			[
				'dailymotion-1',
				'http://www.dailymotion.com/video/x222z1'
			],
			[
				'dailymotion-2',
				'http://www.dailymotion.com/user/Dailymotion/2#video=x222z1'
			],
		];

		if (!empty($_SERVER['REVERSE']))
		{
			$tests = array_reverse($tests);
		}

		return $tests;
	}
}