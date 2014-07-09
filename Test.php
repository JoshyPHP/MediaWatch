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
				'binary' => '/usr/bin/google-chrome',
				'args'   => ['app=file:///tmp', 'no-referrers']
			]
		];

		if (!empty($_SERVER['TRAVIS_JOB_NUMBER']))
		{
			$this->setHost($_SERVER['SAUCE_USERNAME'] . ':' . $_SERVER['SAUCE_ACCESS_KEY'] . '@ondemand.saucelabs.com');

			$desiredCapabilities['tunnel-identifier'] = $_SERVER['TRAVIS_JOB_NUMBER'];
		}

		$this->setDesiredCapabilities($desiredCapabilities);
		$this->setBrowserUrl('http://127.0.0.1:8000/');
		$this->setBrowser('chrome');

		$window = $this->prepareSession()->currentWindow();
		$window->size(['width' => 810, 'height' => 610]);
	}

	/**
	* @dataProvider getBrowserRenderingTestsChunked
	*/
	public function testBrowserRendering($tests)
	{
		$errors = '';

		foreach ($tests as $test)
		{
			$filename = $test[0];
			$url      = $test[1];
			$max      = (isset($test[2])) ? $test[2] : 0.11;

			$filepathHtml     = sys_get_temp_dir() . '/' . $filename . '.html';
			$filepathActual   = sys_get_temp_dir() . '/' . $filename . '.png';
			$filepathExpected = __DIR__ . '/screenshots/' . $filename . '.png';

			if (!empty($_SERVER['TRAVIS']) && file_exists($filepathHtml))
			{
				// This test is being run by another instance
				continue;
			}

			if (!empty($_SERVER['MAINTENANCE']) && file_exists($filepathExpected))
			{
				// Skip existing tests in maintenance mode
				continue;
			}

			$configurator = new Configurator;
			$configurator->cacheDir = sys_get_temp_dir();
			$configurator->MediaEmbed->add(substr($filename, 0, strcspn($filename, '-')));

			$text = $url;
			$xml  = $configurator->getParser()->parse($text);
			$html = $configurator->getRenderer()->render($xml);

			if (!empty($_SERVER['TRAVIS']) && file_exists($filepathHtml))
			{
				// This test is being run by another instance, the file got created since last check
				continue;
			}

			$html = '<!DOCTYPE html><html><head><link rel="icon" href="data:;base64,="><base href="http://localhost/"></head><body style="margin:0;background:#000}"><div style="width:800px;height:600px">' . $html . '</div></body></html>';
			file_put_contents($filepathHtml, $html);

			$this->url($filename . '.html');

			if (!empty($_SERVER['MAINTENANCE']))
			{
				$width  = (preg_match('/width="(\d+)"/', $html, $m))  ? $m[1] : 800;
				$height = (preg_match('/height="(\d+)"/', $html, $m)) ? $m[1] : 600;

				// Don't trust the height for dynamically resized iframes
				if (strpos($html, 'style.height'))
				{
					$height = 600;
				}

				sleep(10);
				$this->saveScreenshot($filepathExpected, $width, $height);

				continue;
			}

			list($width, $height) = getimagesize($filepathExpected);

			$attempts = 8;
			$sleep    = 2;
			do
			{
				sleep($sleep++);

				$this->saveScreenshot($filepathActual, $width, $height);

				$output = exec(self::$dssim . ' ' . escapeshellarg($filepathExpected) . ' ' . escapeshellarg($filepathActual) . ' 2>&1', $arr, $error);

				if ($error || !preg_match('/^-?[\\d.]+/', $output, $m))
				{
					$errors .= "$filename failed: dssim output $output\n";

					continue;
				}

				$ssim = abs($m[0]);
			}
			while (--$attempts && $ssim > $max);

			if ($ssim > $max)
			{
				$errors .= "$filename does not match: $ssim > $max " . base64_encode(file_get_contents($filepathActual)) . "\n";
			}

			if (empty($_SERVER['TRAVIS']))
			{
				unlink($filepathHtml);
			}
			unlink($filepathActual);
		}

		// Output the errors so that we can find them in Travis's logs
		if ($errors)
		{
			echo "\n$errors\n";
		}

		$this->assertEmpty($errors);
	}

	protected function saveScreenshot($filepath, $width, $height)
	{
		$gd = imagecreatefromstring($this->currentScreenshot());
		$gd = imagecrop($gd, ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height]);
		imagepng($gd, $filepath, 5, PNG_NO_FILTER);
	}

	public function getBrowserRenderingTestsChunked()
	{
		$chunks = [];
		foreach ($this->getBrowserRenderingTests() as $i => $test)
		{
			$chunks[floor($i / 10)][0][] = $test;
		}

		return $chunks;
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
			[
				'dailyshow-1',
				'http://www.thedailyshow.com/watch/mon-july-16-2012/louis-c-k-'
			],
			[
				'dailyshow-2',
				'http://www.thedailyshow.com/collection/429537/shutstorm-2013/429508'
			],
			[
				'dailyshow-3',
				'http://thedailyshow.cc.com/videos/elvsf4/what-not-to-buy'
			],
//			[
//				'ebay-com',
//				'http://www.ebay.com/itm/Converse-All-Star-Chuck-Taylor-Black-Hi-Canvas-M9160-Men-/251053262701'
//			],
//			[
//				'ebay-uk',
//				'http://www.ebay.co.uk/itm/Converse-Classic-Chuck-Taylor-Low-Trainer-Sneaker-All-Star-OX-NEW-sizes-Shoes-/230993099153'
//			],
//			[
//				'ebay-de',
//				'http://www.ebay.de/itm/Converse-Chucks-All-Star-OX-Klassiker-Gr-35-48-/320748648909'
//			],
//			[
//				'ebay-fr',
//				'http://www.ebay.fr/itm/CONVERSE-CHUCK-TAYLOR-AS-CORE-OX-All-Star-Sneakers-Men-Women-Free-Shipping-/380728186640'
//			],
			[
				'eighttracks',
				'http://8tracks.com/midna/2242699'
			],
			[
				'espn',
				'http://espn.go.com/video/clip?id=espn:11112012'
			],
			[
				'espn-deportes',
				'http://espndeportes.espn.go.com/videohub/video/clipDeportes?id=2091094&cc=7586'
			],
			[
				'facebook-photo',
				'https://www.facebook.com/photo.php?v=10100658170103643&amp;set=vb.20531316728&amp;type=3&amp;theater'
			],
			[
				'facebook-video',
				'https://www.facebook.com/video/video.php?v=10150451523596807'
			],
			[
				'facebook-post',
				'https://www.facebook.com/FacebookDevelopers/posts/10151471074398553',
				0.25
			],
			[
				'funnyordie',
				'http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david'
			],
			[
				'gamespot',
				'http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/'
			],
			[
				'gametrailers',
				'http://www.gametrailers.com/reviews/zalxz0/crimson-dragon-review'
			],
			[
				'getty-im',
				'http://gty.im/3232182'
			],
			[
				'getty-com',
				'http://www.gettyimages.com/detail/3232182'
			],
			[
				'getty-uk',
				'http://www.gettyimages.co.uk/detail/3232182'
			],
			[
				'gfycat',
				'http://gfycat.com/SereneIllfatedCapybara',
				1
			],
			[
				'gist',
				'https://gist.github.com/s9e/6806305'
			],
			[
				'googleplus',
				'https://plus.google.com/106189723444098348646/posts/V8AojCoTzxV'
			],
			[
				'googlesheets',
				'https://docs.google.com/spreadsheets/d/1f988o68HDvk335xXllJD16vxLBuRcmm3vg6U9lVaYpA'
			],
			[
				'grooveshark-playlist',
				'http://grooveshark.com/playlist/Purity+Ring+Shrines/74854761'
			],
			[
				'grooveshark-song',
				'http://grooveshark.com/s/Soul+Below/4zGL7i?src=5'
			],
			[
				'hulu',
				'http://www.hulu.com/watch/484180'
			],
			[
				'ign',
				'http://www.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer'
			],
			[
				'imgur',
				'http://imgur.com/a/9UGCL'
			],
			[
				'indiegogo',
				'http://www.indiegogo.com/projects/gameheart-redesigned'
			],
			[
				'instagram',
				'http://instagram.com/p/gbGaIXBQbn/'
			],
			[
				'internetarchive',
				'https://archive.org/details/BillGate99'
			],
			[
				'izlesene',
				'http://www.izlesene.com/video/lily-allen-url-badman/7600704'
			],
			[
				'kickstarter',
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html'
			],
			[
				'kickstarter',
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html'
			],
			[
				'liveleak',
				'http://www.liveleak.com/view?i=3dd_1366238099'
			],
			[
				'metacafe',
				'http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/'
			],
			[
				'mixcloud',
				'http://www.mixcloud.com/OneTakeTapes/timsch-one-take-tapes-2/'
			],
			[
				'nytimes',
				'http://www.nytimes.com/video/technology/personaltech/100000002907606/soylent-taste-test.html'
			],
			[
				'podbean',
				'http://dialhforheroclix.podbean.com/e/dial-h-for-heroclix-episode-46-all-ya-need-is-love/'
			],
			[
				'prezi',
				'http://prezi.com/5ye8po_hmikp/10-most-common-rookie-presentation-mistakes/'
			],
			[
				'rdio',
				'http://rd.io/x/QcD7oTdeWevg/'
			],
			[
				'rutube',
				'http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd'
			],
			[
				'slideshare',
				'http://www.slideshare.net/Slideshare/how-23431564'
			],
			[
				'soundcloud-track',
				'http://api.soundcloud.com/tracks/98282116'
			],
			[
				'soundcloud-playlist',
				'https://soundcloud.com/tenaciousd/sets/rize-of-the-fenix/'
			],
			[
				'soundcloud-secret',
				'https://soundcloud.com/matt0753/iroh-ii-deep-voice/s-UpqTm'
			],
			[
				'spotify-track-uri',
				'spotify:track:5JunxkcjfCYcY7xJ29tLai'
			],
			[
				'spotify-track-url',
				'http://open.spotify.com/track/4woJuZHL7xBDirHwWxkRrX'
			],
			[
				'spotify-album',
				'https://play.spotify.com/album/5OSzFvFAYuRh93WDNCTLEz'
			],
			[
				'spotify-playlist',
				'http://open.spotify.com/user/ozmoetr/playlist/4yRrCWNhWOqWZx5lmFqZvt'
			],
			[
				'strawpoll',
				'http://strawpoll.me/738091'
			],
			[
				'teamcoco',
				'http://teamcoco.com/video/73784/historian-a-scott-berg-serious-jibber-jabber-with-conan-obrien'
			],
			[
				'ted',
				'http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html'
			],
			[
				'theatlantic',
				'http://www.theatlantic.com/video/index/358928/computer-vision-syndrome-and-you/'
			],
			[
				'traileraddict',
				'http://www.traileraddict.com/the-amazing-spider-man-2/super-bowl-tv-spot'
			],
			[
				'twitch',
				'http://www.twitch.tv/amazhs/c/4493103'
			],
			[
				'twitter',
				'https://twitter.com/BarackObama/statuses/266031293945503744'
			],
			[
				'ustream',
				'http://www.ustream.tv/channel/ps4-ustream-gameplay'
			],
			[
				'ustream-recorded',
				'http://www.ustream.tv/recorded/40688256'
			],
			[
				'vevo',
				'http://www.vevo.com/watch/USUV71400682'
			],
			[
				'vimeo',
				'http://vimeo.com/67207222'
			],
			[
				'vine',
				'https://vine.co/v/bYwPIluIipH'
			],
		];
	}
}