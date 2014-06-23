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
				'args'   => ['app=file:///tmp']
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

		$window = $this->prepareSession()->currentWindow();
		$window->size(['width' => 800, 'height' => 600]);
//		$window->position(['x' => -1000, 'y' => -1000]);
	}

	/**
	* @dataProvider getBrowserRenderingTests
	*/
	public function testBrowserRendering($filename, $url)
	{
		$configurator = new Configurator;
		$configurator->cacheDir = sys_get_temp_dir();
		$configurator->MediaEmbed->add(substr($filename, 0, strpos($filename, '-')));

		$filepathImg  = sys_get_temp_dir() . '/' . $filename . '.png';
		$filepathHtml = sys_get_temp_dir() . '/' . $filename . '.html';

		$text = $url;
		$xml  = $configurator->getParser()->parse($text);
		$html = $configurator->getRenderer()->render($xml);

		$html = '<!DOCTYPE html><html><head><style>body{margin:0;background:#000}</style><base href="http://localhost/"></head><body><div>' . $html . '</div></body></html>';
		file_put_contents($filepathHtml, $html);

		$filepathExpected = __DIR__ . '/screenshots/' . $filename . '.png';
		list($width, $height) = getimagesize($filepathExpected);

		$max = (file_exists($filepathExpected . '.txt')) ? (float) file_get_contents($filepathExpected . '.txt') : 0.1;

		$this->url($filename . '.html');

		$i = 5;
		do
		{
			sleep(2);

			$gd = imagecreatefromstring($this->currentScreenshot());
			$gd = imagecrop($gd, ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height]);

			imagepng($gd, $filepathImg, 0, PNG_NO_FILTER);

			$output = exec(self::$dssim . ' ' . escapeshellarg($filepathExpected) . ' ' . escapeshellarg($filepathImg) . ' 2>&1', $arr, $error);

			if ($error || !preg_match('/^[\\d.]+/', $output, $m))
			{
				$this->fail($output);
			}

			$ssim = (float) $m[0];
		}
		while (--$i && $ssim > $max);

		unlink($filepathHtml);

		$this->assertLessThanOrEqual($max, $ssim, base64_encode(file_get_contents($filepathImg)));

		unlink($filepathImg);
	}

	public function getBrowserRenderingTests()
	{
		return [
			[
				'abcnews-1',
				'http://abcnews.go.com/WNN/video/dog-goes-wild-when-owner-leaves-22936610'
			],
			[
				'amazon-ca',
				'http://www.amazon.ca/gp/product/B00GQT1LNO/'
			],
			[
				'audiomack-song',
				'http://www.audiomack.com/song/your-music-fix/jammin-kungs-remix-1'
			],
			[
				'cnbc-1',
				'http://video.cnbc.com/gallery/?video=3000269279'
			],
		];
	}
}