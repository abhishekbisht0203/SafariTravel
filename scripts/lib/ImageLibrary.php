<?php
/**
 * Licensed image acquisition for the Safari Travel theme.
 *
 * The site must never hotlink a third-party CDN: a safari website whose
 * photography 404s the morning after a redesign is not production software.
 * Every photograph is therefore downloaded once, stored inside the repository
 * under theme/assets/images/, and served from the site itself.
 *
 * Sources are chosen so the licence is unambiguous and commercially usable:
 *
 *   - Openverse (https://openverse.org) is queried for candidates. It indexes
 *     openly licensed photography from Flickr, Wikimedia Commons and others,
 *     and the request below restricts results to licences that permit both
 *     commercial use and modification, which is what a commercial site needs.
 *     Attribution is captured for every file and written to
 *     theme/assets/images/CREDITS.json plus a human-readable CREDITS.md.
 *
 *   - No API key is required, and the client identifies itself with a
 *     descriptive User-Agent as the Openverse usage policy asks.
 *
 * Re-running is cheap: a file that already exists is left alone unless
 * --force is passed, so the image set is committed to the repository and the
 * network is only consulted on a fresh clone that needs to re-fetch.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

/**
 * Downloads and optimises the theme's photography.
 */
final class ImageLibrary {

	/**
	 * Licences Openverse may return that permit commercial use *and*
	 * modification. Anything with -nc (non-commercial) or -nd (no derivatives)
	 * is excluded outright; neither is usable on a commercial site.
	 */
	private const LICENSES = 'cc0,pdm,by,by-sa';

	/**
	 * Directory (relative to the theme) that holds the photography.
	 */
	private const BASE = 'assets/images';

	/**
	 * User agent sent with every request.
	 */
	private const USER_AGENT = 'SafariTravel/1.0 (+https://github.com/safari-travel; local build tooling)';

	/**
	 * Openverse endpoint.
	 */
	private const ENDPOINT = 'https://api.openverse.org/v1/images/';

	/**
	 * Path to the CA bundle used to verify TLS.
	 *
	 * @var string|null
	 */
	private static ?string $ca_bundle = null;

	/**
	 * Whether the CA bundle lookup already ran.
	 *
	 * @var bool
	 */
	private static bool $ca_checked = false;

	/**
	 * Cached HTTP bodies, keyed by URL. Avoids re-querying during one run.
	 *
	 * @var array<string,string>
	 */
	private static array $cache = [];

	/**
	 * Every licence record collected during the run, keyed by file name.
	 *
	 * @var array<string,array<string,string>>
	 */
	private static array $credits = [];

	/**
	 * Report lines for the caller.
	 *
	 * @var string[]
	 */
	private array $log = [];

	/**
	 * Run the acquisition for every slot in the manifest.
	 *
	 * @param array $options force:bool, only:array<int,string>, dry_run:bool.
	 * @return string[] Log lines.
	 */
	public function acquire(array $options = []): array {
		$force    = (bool) ($options['force'] ?? false);
		$only     = (array) ($options['only'] ?? []);
		$dry_run  = (bool) ($options['dry_run'] ?? false);
		$manifest = $this->manifest();

		foreach ($manifest as $slot) {
			if ($only && ! in_array($slot['id'], $only, true)) {
				continue;
			}

			$this->acquireSlot($slot, $force, $dry_run);
		}

		if (! $dry_run) {
			$this->writeCredits();
		}

		return $this->log;
	}

	/**
	 * Fetch one slot.
	 *
	 * @param array $slot    Slot definition.
	 * @param bool  $force   Re-download even when the file exists.
	 * @param bool  $dry_run Report only, write nothing.
	 */
	private function acquireSlot(array $slot, bool $force, bool $dry_run): void {
		$dir      = $this->themeDir() . '/' . self::BASE . '/' . $slot['dir'];
		$filename = $slot['id'] . '.jpg';
		$path     = $dir . '/' . $filename;

		if (! $force && is_file($path) && filesize($path) > 20000) {
			$this->log[] = sprintf('  = %-26s already present', $slot['id']);

			return;
		}

		if ($dry_run) {
			$this->log[] = sprintf('  ? %-26s would search "%s"', $slot['id'], implode(' | ', $slot['queries']));

			return;
		}

		foreach ($slot['queries'] as $query) {
			$candidates = $this->search($query, $slot['aspect']);

			foreach ($candidates as $candidate) {
				$record = $this->download($candidate, $slot, $path);

				if (null !== $record) {
					$this->log[] = sprintf(
						'  + %-26s %-6s %s',
						$slot['id'],
						$record['license'],
						$record['title']
					);

					return;
				}
			}

			usleep(400000);
		}

		$this->log[] = sprintf('  ! %-26s NO CANDIDATE (tried: %s)', $slot['id'], implode(' | ', $slot['queries']));
	}

	/**
	 * Query Openverse.
	 *
	 * @param string $query  Search terms.
	 * @param string $aspect wide|tall|square.
	 * @return array<int,array<string,mixed>> Candidates, best first.
	 */
	private function search(string $query, string $aspect): array {
		$url = self::ENDPOINT . '?' . http_build_query([
			'q'             => $query,
			'license'       => self::LICENSES,
			'license_type'  => 'commercial',
			'page_size'     => 20,
			'mature'        => 'false',
			// Wikimedia holds the strongest safari photography and is the only
			// source that reliably serves an unmodified original at full size.
			'source'        => 'wikimedia',
			'aspect_ratio'  => $aspect,
			'size'          => 'large',
		]);

		$body = $this->get($url);

		if (null === $body) {
			return [];
		}

		$json = json_decode($body, true);
		$rows = is_array($json['results'] ?? null) ? $json['results'] : [];

		$scored = [];

		foreach ($rows as $row) {
			$width  = (int) ($row['width'] ?? 0);
			$height = (int) ($row['height'] ?? 0);

			if ($width < 1600 || $height < 1000) {
				continue;
			}

			// Prefer genuine landscape compositions for wide slots: reward width
			// and penalise extreme panoramas that crop badly in a card.
			$ratio = $width / max(1, $height);
			$score = $width;

			if ('wide' === $aspect) {
				if ($ratio < 1.3 || $ratio > 2.2) {
					continue;
				}
				$score += 1500 - (int) abs(1.6 - $ratio) * 1500;
			} elseif ('tall' === $aspect) {
				if ($ratio < 0.7 || $ratio > 0.95) {
					continue;
				}
				$score += 1500 - (int) abs(0.8 - $ratio) * 1500;
			}

			// A file with a human title beats "DSC00412".
			$title = trim((string) ($row['title'] ?? ''));
			if ('' !== $title && ! preg_match('/^(img|dsc|dscn|p\d{4})[\s_-]*\d*$/i', $title)) {
				$score += 800;
			}

			$row['_score'] = $score;
			$scored[]      = $row;
		}

		usort(
			$scored,
			static fn (array $a, array $b): int => $b['_score'] <=> $a['_score']
		);

		return $scored;
	}

	/**
	 * Download, crop, and optimise one candidate.
	 *
	 * @param array  $candidate Openverse row.
	 * @param array  $slot      Slot definition.
	 * @param string $path      Destination path.
	 * @return array<string,string>|null Licence record, or null on failure.
	 */
	private function download(array $candidate, array $slot, string $path): ?array {
		$url = $this->resolveDownloadUrl((string) ($candidate['url'] ?? ''));

		if (null === $url) {
			return null;
		}

		$body = $this->get($url, 60);

		if (null === $body || strlen($body) < 20000) {
			return null;
		}

		if (! is_dir(dirname($path))) {
			mkdir(dirname($path), 0755, true);
		}

		$tmp = $path . '.part';
		file_put_contents($tmp, $body);
		unset($body);

		$info = @getimagesize($tmp);

		if (false === $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
			@unlink($tmp);

			return null;
		}

		if (! $this->writeOptimised($tmp, $path, (int) $slot['width'], (int) $slot['height'])) {
			@unlink($tmp);

			return null;
		}

		@unlink($tmp);

		$record = [
			'id'         => $slot['id'],
			'file'       => self::BASE . '/' . $slot['dir'] . '/' . $slot['id'] . '.jpg',
			'title'      => trim((string) ($candidate['title'] ?? $slot['id'])),
			'creator'    => trim((string) ($candidate['creator'] ?? 'Unknown')),
			'license'    => strtoupper((string) ($candidate['license'] ?? 'unknown')),
			'license_url'=> (string) ($candidate['license_url'] ?? ''),
			'source'     => (string) ($candidate['foreign_landing_url'] ?? $url),
			'used_for'   => (string) $slot['purpose'],
		];

		self::$credits[ $slot['id'] ] = $record;

		return $record;
	}

	/**
	 * Resolve the URL to download.
	 *
	 * The candidate's own `url` is used verbatim. Rewriting it into a
	 * Wikimedia `/{width}px-` derivative looks appealing but does not work:
	 * upload.wikimedia.org only serves a per-file allow-list of thumb widths
	 * and answers anything else with HTTP 400. Downloading the original and
	 * resizing it here is both simpler and exact.
	 *
	 * @param string $url Original file URL.
	 * @return string|null
	 */
	private function resolveDownloadUrl(string $url): ?string {
		$url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');

		// Openverse appends analytics parameters; strip them so the CDN serves
		// the file rather than a redirect chain.
		$url = (string) preg_replace('/[?&]utm_[^&]*/', '', $url);
		$url = rtrim($url, '?&');

		return '' !== $url ? $url : null;
	}

	/**
	 * Crop to the target aspect ratio and write optimised JPEG + WebP.
	 *
	 * JPEG is what WordPress and every browser will definitely decode; WebP is
	 * written alongside for the theme's own CSS backgrounds, where WordPress
	 * is not in the pipeline and can generate derivatives itself.
	 *
	 * @param string $src    Source file.
	 * @param string $dest   JPEG destination.
	 * @param int    $width  Target width.
	 * @param int    $height Target height.
	 */
	private function writeOptimised(string $src, string $dest, int $width, int $height): bool {
		$info = @getimagesize($src);

		if (false === $info) {
			return false;
		}

		$image = $this->loadImage($src, $info[2]);

		if (null === $image) {
			return false;
		}

		$sw = imagesx($image);
		$sh = imagesy($image);
		$target_ratio = $width / $height;
		$source_ratio = $sw / $sh;

		// Centre-crop, biased slightly above centre: wildlife photography
		// consistently puts the subject and the horizon in the upper two
		// thirds, and a geometric centre crop decapitates them.
		if ($source_ratio > $target_ratio) {
			$crop_w = (int) round($sh * $target_ratio);
			$crop_h = $sh;
			$crop_x = (int) round(($sw - $crop_w) / 2);
			$crop_y = 0;
		} else {
			$crop_w = $sw;
			$crop_h = (int) round($sw / $target_ratio);
			$crop_x = 0;
			$crop_y = (int) round(($sh - $crop_h) * 0.42);
		}

		$out = imagecreatetruecolor($width, $height);
		imagealphablending($out, false);
		imagesavealpha($out, true);
		$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
		imagefilledrectangle($out, 0, 0, $width, $height, $transparent);
		imagecopyresampled($out, $image, 0, 0, $crop_x, $crop_y, $width, $height, $crop_w, $crop_h);

		if (! is_dir(dirname($dest))) {
			mkdir(dirname($dest), 0755, true);
		}

		$ok = imagejpeg($out, $dest, 82);

		if ($ok && function_exists('imagewebp')) {
			imagewebp($out, preg_replace('/\.jpg$/', '.webp', (string) $dest), 78);
		}

		imagedestroy($out);
		imagedestroy($image);

		return $ok;
	}

	/**
	 * Load an image resource from a file.
	 *
	 * @param string $path File path.
	 * @param int    $type IMAGETYPE_* constant.
	 * @return \GdImage|null
	 */
	private function loadImage(string $path, int $type) {
		switch ($type) {
			case IMAGETYPE_JPEG:
				return @imagecreatefromjpeg($path);
			case IMAGETYPE_PNG:
				return @imagecreatefrompng($path);
			default:
				return null;
		}
	}

	/**
	 * HTTP GET with a CA bundle, polite User-Agent, retries and caching.
	 *
	 * Only small JSON responses are cached. Image payloads are written to disk
	 * and dropped immediately: holding a few multi-megabyte originals in a
	 * static array exhausts the CLI memory limit long before the run ends.
	 *
	 * @param string $url     URL.
	 * @param int    $timeout Seconds.
	 * @return string|null Body, or null on failure.
	 */
	private function get(string $url, int $timeout = 45): ?string {
		$cacheable = str_contains($url, self::ENDPOINT);

		if ($cacheable && isset(self::$cache[ $url ])) {
			return self::$cache[ $url ];
		}

		$bundle = self::caBundle();

		// Image payloads are large and transient, so they get one honest
		// attempt. Only the small JSON search responses are worth retrying,
		// because those are the requests that can hit a rate limit.
		$attempts = $cacheable ? 3 : 1;

		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			$ch = curl_init($url);
			curl_setopt_array(
				$ch,
				[
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS      => 5,
					CURLOPT_TIMEOUT        => $timeout,
					CURLOPT_USERAGENT      => self::USER_AGENT,
					CURLOPT_HTTPHEADER     => ['Accept: application/json, image/*'],
					CURLOPT_ENCODING       => '',
				]
			);

			if (null !== $bundle) {
				curl_setopt($ch, CURLOPT_CAINFO, $bundle);
			}

			$body   = curl_exec($ch);
			$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if (200 === $status && is_string($body) && '' !== $body) {
				if ($cacheable) {
					self::$cache[ $url ] = $body;
				}

				return $body;
			}

			// 429 is rate limiting: back off rather than hammering.
			if (429 === $status) {
				sleep($attempt * 3);

				continue;
			}

			if ($attempt < $attempts) {
				usleep(400000 * $attempt);
			}
		}

		return null;
	}

	/**
	 * Locate a CA bundle so TLS can be verified.
	 *
	 * Windows PHP builds frequently ship without curl.cainfo, which makes every
	 * https:// call fail certificate verification. Rather than disabling
	 * verification - never acceptable - the client looks for a bundle in the
	 * environment first, then in the usual Git for Windows / curl locations.
	 *
	 * @return string|null
	 */
	private static function caBundle(): ?string {
		if (self::$ca_checked) {
			return self::$ca_bundle;
		}

		self::$ca_checked = true;

		$configured = getenv('SAFARI_CA_BUNDLE');
		$candidates  = array_filter(
			array_merge(
				is_string($configured) && '' !== $configured ? [$configured] : [],
				[
					'C:/Program Files/Git/usr/ssl/certs/ca-bundle.crt',
					'C:/Program Files/Git/mingw64/ssl/certs/ca-bundle.crt',
					'/etc/ssl/certs/ca-certificates.crt',
					'/etc/pki/tls/certs/ca-bundle.crt',
					'/etc/ssl/cert.pem',
				]
			)
		);

		foreach ($candidates as $candidate) {
			if (is_file($candidate) && is_readable($candidate)) {
				self::$ca_bundle = $candidate;

				return self::$ca_bundle;
			}
		}

		return null;
	}

	/**
	 * Write the licence manifest in both machine and human readable form.
	 */
	private function writeCredits(): void {
		$base = $this->themeDir() . '/' . self::BASE;

		if (! is_dir($base)) {
			mkdir($base, 0755, true);
		}

		ksort(self::$credits);

		file_put_contents(
			$base . '/CREDITS.json',
			json_encode(
				[
					'note'      => 'Photography licences for the Safari Travel theme. '
						. 'Every file is stored locally and served from this site; nothing is hotlinked. '
						. 'Regenerate with: php scripts/safari.php images --force',
					'generated' => gmdate('c'),
					'images'    => array_values(self::$credits),
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			) . "\n"
		);

		$md = "# Photography credits\n\n"
			. "All photography in `theme/assets/images/` is stored locally and served from this\n"
			. "site — no third-party image CDN is contacted at runtime. Every file below is\n"
			. "openly licensed for commercial use and modification.\n\n"
			. "Regenerate the set with `php scripts/safari.php images --force`.\n\n";

		foreach (self::$credits as $record) {
			$md .= sprintf(
				"## `%s`\n\n- **Title:** %s\n- **Creator:** %s\n- **Licence:** %s%s\n- **Source:** %s\n- **Used for:** %s\n\n",
				$record['file'],
				$record['title'],
				$record['creator'],
				$record['license'],
				'' !== $record['license_url'] ? ' — ' . $record['license_url'] : '',
				$record['source'],
				$record['used_for']
			);
		}

		file_put_contents($base . '/CREDITS.md', $md);
	}

	/**
	 * Absolute path to the theme directory.
	 */
	private function themeDir(): string {
		return dirname(__DIR__, 2) . '/theme';
	}

	/**
	 * The image manifest.
	 *
	 * Each slot declares where its file is stored, the intrinsic size the
	 * layout needs, and several search phrasings. Several phrasings matter:
	 * Openverse's index is keyword-driven, so a second phrasing is often the
	 * difference between a good frame and no result at all.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function manifest(): array {
		return [
			/* ---------------------------------------------------------- hero */
			[
				'id'      => 'hero-savanna-dusk',
				'dir'     => 'hero',
				'width'   => 2400,
				'height'  => 1350,
				'aspect'  => 'wide',
				'purpose' => 'Homepage hero background',
				'queries' => ['serengeti sunset landscape', 'savanna sunset acacia', 'African savanna sunset'],
			],
			[
				'id'      => 'hero-mara-plains',
				'dir'     => 'hero',
				'width'   => 2400,
				'height'  => 1350,
				'aspect'  => 'wide',
				'purpose' => 'Homepage hero, alternate crop',
				'queries' => ['Maasai Mara landscape', 'Maasai Mara plains wildebeest', 'Kenya savanna landscape'],
			],
			[
				'id'      => 'hero-destination',
				'dir'     => 'hero',
				'width'   => 2400,
				'height'  => 1200,
				'aspect'  => 'wide',
				'purpose' => 'Fallback hero for inner pages',
				'queries' => ['Amboseli elephants Kilimanjaro', 'African elephant Kilimanjaro', 'Amboseli National Park'],
			],

			/* ------------------------------------------------- destinations */
			[
				'id'      => 'maasai-mara',
				'dir'     => 'destinations',
				'width'   => 2000,
				'height'  => 1250,
				'aspect'  => 'wide',
				'purpose' => 'Maasai Mara destination',
				'queries' => ['Maasai Mara landscape', 'Maasai Mara wildebeest', 'Maasai Mara grassland'],
			],
			[
				'id'      => 'serengeti',
				'dir'     => 'destinations',
				'width'   => 2000,
				'height'  => 1250,
				'aspect'  => 'wide',
				'purpose' => 'Serengeti destination',
				'queries' => ['Serengeti landscape plains', 'Serengeti National Park', 'Serengeti wildebeest'],
			],
			[
				'id'      => 'amboseli',
				'dir'     => 'destinations',
				'width'   => 2000,
				'height'  => 1250,
				'aspect'  => 'wide',
				'purpose' => 'Amboseli destination',
				'queries' => ['Amboseli Kilimanjaro elephants', 'Amboseli National Park', 'Kilimanjaro elephants Amboseli'],
			],
			[
				'id'      => 'okavango-delta',
				'dir'     => 'destinations',
				'width'   => 2000,
				'height'  => 1250,
				'aspect'  => 'wide',
				'purpose' => 'Okavango Delta destination',
				'queries' => ['Okavango Delta', 'Okavango Delta aerial', 'Okavango mokoro'],
			],
			[
				'id'     => 'greater-kruger',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Greater Kruger destination',
				'queries' => ['Kruger National Park landscape', 'Kruger National Park', 'Kruger National Park elephant'],
			],
			[
				'id'     => 'bwindi-forest',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Bwindi Impenetrable Forest destination',
				'queries' => ['Bwindi Impenetrable Forest', 'Bwindi Impenetrable Forest Uganda', 'mountain gorilla Bwindi'],
			],
			[
				'id'     => 'etosha-namibia',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Etosha, Namibia destination',
				'queries' => ['Etosha National Park', 'Etosha Namibia', 'Namibia desert landscape'],
			],
			[
				'id'     => 'zambezi-victoria-falls',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Zambezi / Victoria Falls destination',
				'queries' => ['Victoria Falls Zambezi', 'Victoria Falls aerial', 'Zambezi River Africa'],
			],
			[
				'id'     => 'akagera-rwanda',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Akagera, Rwanda destination',
				'queries' => ['Akagera National Park', 'Rwanda landscape hills', 'Rwanda Akagera landscape'],
			],

			/* --------------------------------------------------------- tours */
			[
				'id'     => 'tour-mara-crossing',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Mara river crossing itinerary',
				'queries' => ['wildebeest migration river crossing', 'Mara River wildebeest', 'wildebeest migration Kenya'],
			],
			[
				'id'     => 'tour-serengeti-calving',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Serengeti itinerary',
				'queries' => ['Serengeti wildebeest calving', 'Serengeti plains wildebeest herd', 'wildebeest herd Serengeti'],
			],
			[
				'id'     => 'tour-amboseli-elephants',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Amboseli itinerary',
				'queries' => ['African elephant family Amboseli', 'African elephants Kilimanjaro', 'African elephant herd savanna'],
			],
			[
				'id'     => 'tour-okavango-water',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Okavango water itinerary',
				'queries' => ['Okavango mokoro canoe', 'Okavango Delta papyrus', 'Okavango Delta channels'],
			],
			[
				'id'     => 'tour-kruger-leopard',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Kruger itinerary',
				'queries' => ['leopard Kruger National Park', 'leopard tree Africa', 'leopard Africa wild'],
			],
			[
				'id'     => 'tour-bwindi-gorilla',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Bwindi gorilla itinerary',
				'queries' => ['mountain gorilla Uganda', 'gorilla Bwindi', 'mountain gorilla Virunga'],
			],
			[
				'id'     => 'tour-namibia-desert',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Namibia itinerary',
				'queries' => ['Namibia Sossusvlei dunes', 'Sossusvlei Deadvlei', 'Namibia desert dunes'],
			],
			[
				'id'     => 'tour-zambezi-river',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Zambezi itinerary',
				'queries' => ['Zambezi river sunset', 'Victoria Falls Zimbabwe', 'Zambezi canoe Africa'],
			],
			[
				'id'     => 'tour-lion-pride',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Big cat itinerary',
				'queries' => ['lion male Serengeti', 'lion pride savanna', 'lion Africa wildlife'],
			],
			[
				'id'     => 'tour-cheetah-plains',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Cheetah itinerary',
				'queries' => ['cheetah Serengeti', 'cheetah Africa plains', 'cheetah hunting savanna'],
			],
			[
				'id'     => 'tour-rhino-conservancy',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Rhino itinerary',
				'queries' => ['white rhinoceros South Africa', 'black rhinoceros Kenya', 'rhinoceros Africa wildlife'],
			],
			[
				'id'     => 'tour-zebra-herd',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Zebra itinerary',
				'queries' => ['zebras Maasai Mara', 'zebra herd Africa plains', 'plains zebra Serengeti'],
			],
			[
				'id'     => 'tour-balloon-safari',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Balloon safari itinerary',
				'queries' => ['hot air balloon Serengeti', 'balloon safari Africa', 'hot air balloon Kenya safari'],
			],
			[
				'id'     => 'tour-tented-camp',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Camp itinerary',
				'queries' => ['safari tented camp Kenya', 'tented camp Africa safari', 'safari lodge Kenya'],
			],

			/* -------------------------------------------------------- guides */
			[
				'id'     => 'guide-packing',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Packing guide',
				'queries' => ['safari luggage packing', 'safari camp luggage', 'travelling Africa safari gear'],
			],
			[
				'id'     => 'guide-photography',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Photography guide',
				'queries' => ['wildlife photographer Africa', 'photographer Serengeti', 'safari photography elephant'],
			],
			[
				'id'     => 'guide-visas',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Visa guide',
				'queries' => ['passport visa Africa', 'East Africa visa', 'travel passport documents'],
			],
			[
				'id'     => 'guide-birds',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Birding guide',
				'queries' => ['African lilac roller', 'African bird perched', 'southern ground hornbill'],
			],
			[
				'id'     => 'guide-giraffe',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Migration guide',
				'queries' => ['giraffe Maasai Mara', 'giraffe Kenya savanna', 'giraffe acacia Africa'],
			],

			/* -------------------------------------------------------- events */
			[
				'id'     => 'event-migration',
				'dir'    => 'events',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Migration event card',
				'queries' => ['wildebeest Serengeti migration', 'wildebeest migration Tanzania', 'Serengeti wildebeest'],
			],
			[
				'id'     => 'event-gorilla-departure',
				'dir'    => 'events',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Gorilla departure event card',
				'queries' => ['mountain gorilla forest', 'gorilla Uganda forest', 'Bwindi gorilla'],
			],
			[
				'id'     => 'event-shoulder-season',
				'dir'    => 'events',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Shoulder season event card',
				'queries' => ['Lake Nakuru flamingos', 'Rift Valley Kenya landscape', 'Kenya landscape lake'],
			],

			/* -------------------------------------------------- backgrounds */
			[
				'id'     => 'bg-dust-road',
				'dir'    => 'backgrounds',
				'width'  => 2000,
				'height' => 1125,
				'aspect' => 'wide',
				'purpose' => 'Section background',
				'queries' => ['savanna dirt road Africa', 'Kenya dirt road landscape', 'African bush road'],
			],
			[
				'id'     => 'bg-acacia-silhouette',
				'dir'    => 'backgrounds',
				'width'  => 2000,
				'height' => 1125,
				'aspect' => 'wide',
				'purpose' => 'Section background',
				'queries' => ['acacia tree sunset Africa', 'acacia tree silhouette savanna', 'acacia tree Kenya'],
			],

			/* -------------------------------------------------- auth pages */
			[
				'id'     => 'auth-login',
				'dir'    => 'auth',
				'width'  => 1600,
				'height' => 2000,
				'aspect' => 'tall',
				'purpose' => 'Login page artwork',
				'queries' => ['giraffe portrait Africa', 'giraffe head portrait', 'giraffe Masai Mara close'],
			],
			[
				'id'     => 'auth-signup',
				'dir'    => 'auth',
				'width'  => 1600,
				'height' => 2000,
				'aspect' => 'tall',
				'purpose' => 'Signup page artwork',
				'queries' => ['elephant portrait Africa', 'African elephant close portrait', 'elephant Amboseli'],
			],
		];
	}
}
