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
	 * Source URLs already claimed by a slot during this run.
	 *
	 * Two slots must never resolve to the same photograph. Openverse happily
	 * returns one high-ranking file for several overlapping queries, which is
	 * how a site ends up with the same elephant on the sign-up page and the
	 * photography guide — it looks like a bug because it is one.
	 *
	 * @var array<string,string> Source URL to slot id.
	 */
	private array $claimed = [];

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

		// Seed the duplicate guard from the previous run so that re-fetching a
		// single slot cannot hand it a photograph an untouched slot already owns.
		$this->loadExistingCredits();

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
			$candidates = $this->search(
				$query,
				$slot['aspect'],
				(array) ($slot['require'] ?? []),
				[
					'require_title' => (array) ($slot['require_title'] ?? []),
					'exclude'       => (array) ($slot['exclude'] ?? []),
				]
			);

			foreach ($candidates as $candidate) {
				$source = $this->resolveDownloadUrl((string) ($candidate['url'] ?? '')) ?? '';

				// Never let two slots share one photograph.
				if ('' !== $source && isset($this->claimed[ $source ])) {
					continue;
				}

				$record = $this->download($candidate, $slot, $path);

				if (null !== $record) {
					$this->claimed[ (string) $record['source'] ] = $slot['id'];

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
	 * @param array  $require Keyword groups; a candidate must match one term
	 *                        from each group, or it is discarded. This is the
	 *                        guard that stops Openverse's keyword search from
	 *                        answering "tented camp" with a photograph of a
	 *                        dead insect: relevance is checked against the
	 *                        file's own title, tags and description rather
	 *                        than trusted from the search ranking.
	 * @param array  $options require_title, exclude.
	 * @return array<int,array<string,mixed>> Candidates, best first.
	 */
	private function search(string $query, string $aspect, array $require = [], array $options = []): array {
		$url = self::ENDPOINT . '?' . http_build_query([
			'q'             => $query,
			'license'       => self::LICENSES,
			'license_type'  => 'commercial',
			'page_size'     => 20,
			'mature'        => 'false',
			// Wikimedia holds the strongest safari photography and is the only
			// source that reliably serves an unmodified original at full size.
			'source'        => 'wikimedia',
			'size'          => 'large',
		] + ('any' === $aspect ? [] : ['aspect_ratio' => $aspect]));

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

			if (! $this->isRelevant($row, $require, $options)) {
				continue;
			}

			// Prefer genuine landscape compositions for wide slots: reward width
			// and penalise extreme panoramas that crop badly in a card.
			$ratio = $width / max(1, $height);
			$score = $width;

			// `any` is used where the subject exists in the index mainly in one
			// orientation. The centre-crop below still produces a usable frame,
			// which is a better outcome than an empty slot.
			if ('any' !== $aspect) {
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
			// The resolved download URL, not the landing page: this is the
			// identity used to keep two slots from claiming one photograph.
			'source'     => $url,
			'page'       => (string) ($candidate['foreign_landing_url'] ?? ''),
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
	 * Decide whether a candidate is actually about the slot's subject.
	 *
	 * Openverse ranks by its own similarity, which is good but not good enough:
	 * a search for "safari tented camp Kenya" will happily return a macro
	 * photograph of an insect if it happens to match on colour and texture.
	 * Requiring the subject keywords to appear in the file's own metadata
	 * turns a soft ranking into a hard contract, which is the difference
	 * between a plausible-looking site and one that is quietly wrong.
	 *
	 * `require_title` is stricter than `require`: the keyword has to be in the
	 * file's own name, not merely somewhere in a tag list. Openverse tags are
	 * noisy enough that a photograph of a dirt road can be tagged "zebra"
	 * because a zebra was visible somewhere in the wider frame.
	 *
	 * @param array $row     Openverse result row.
	 * @param array $require List of keyword groups (OR within, AND across).
	 * @param array $options require_title, exclude.
	 * @return bool
	 */
	private function isRelevant(array $row, array $require, array $options = []): bool {
		$title = strtolower((string) ($row['title'] ?? ''));

		foreach ((array) ($options['exclude'] ?? []) as $group) {
			foreach ((array) $group as $term) {
				if ($this->containsWord($title, (string) $term)) {
					return false;
				}
			}
		}

		foreach ((array) ($options['require_title'] ?? []) as $group) {
			$matched = false;

			foreach ((array) $group as $term) {
				if ($this->containsWord($title, (string) $term)) {
					$matched = true;

					break;
				}
			}

			if (! $matched) {
				return false;
			}
		}

		if (! $require) {
			return true;
		}

		$parts = [];

		foreach ([(string) ($row['title'] ?? ''), (string) ($row['description'] ?? '')] as $value) {
			$parts[] = $value;
		}

		// Openverse returns `tags` as an array on some records and as a
		// comma-separated string on others; normalise before concatenating.
		$tags = $row['tags'] ?? [];

		foreach ((array) $tags as $tag) {
			if (is_scalar($tag)) {
				$parts[] = (string) $tag;
			}
		}

		$haystack = strtolower(implode(' ', $parts));
		$haystack = ' ' . (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $haystack) . ' ';

		foreach ($require as $group) {
			$matched = false;

			foreach ((array) $group as $term) {
				$needle = ' ' . preg_replace('/[^\p{L}\p{N}]+/u', ' ', strtolower((string) $term)) . ' ';

				if ('' !== trim($needle) && str_contains($haystack, $needle)) {
					$matched = true;

					break;
				}
			}

			if (! $matched) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whole-word containment against a space-padded string.
	 *
	 * @param string $haystack Lower-cased text.
	 * @param string $needle   Term to find.
	 */
	private function containsWord(string $haystack, string $needle): bool
	{
		$needle = ' ' . (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', strtolower($needle)) . ' ';

		return '' !== trim($needle) && str_contains(' ' . $haystack . ' ', $needle);
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
	 * Read a previous run's licence manifest.
	 *
	 * Existing files are left alone, so without this the run would have no idea
	 * which photographs the untouched slots already use.
	 */
	private function loadExistingCredits(): void {
		$file = $this->themeDir() . '/' . self::BASE . '/CREDITS.json';

		if (! is_file($file)) {
			return;
		}

		$json = json_decode((string) file_get_contents($file), true);

		foreach ((array) ($json['images'] ?? []) as $record) {
			if (! is_array($record)) {
				continue;
			}

			$source = (string) ($record['source'] ?? '');
			$id     = (string) ($record['id'] ?? '');

			if ('' !== $source && '' !== $id) {
				$this->claimed[ $source ] = $id;
			}
		}
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
			. "site — nothing is hotlinked. Every file below is"
			. "openly licensed for commercial use and modification.\n\n"
			. "Regenerate the set with `php scripts/safari.php images --force`.\n\n";

		foreach (self::$credits as $record) {
			$md .= sprintf(
				"## `%s`\n\n- **Title:** %s\n- **Creator:** %s\n- **Licence:** %s%s\n- **Source file:** %s\n%s- **Used for:** %s\n\n",
				$record['file'],
				$record['title'],
				$record['creator'],
				$record['license'],
				'' !== $record['license_url'] ? ' — ' . $record['license_url'] : '',
				$record['source'],
				'' !== (string) ($record['page'] ?? '') ? '- **Source page:** ' . $record['page'] . "\n" : '',
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
	 * @return array<int,array<string,mixed>>
	 * @see ImageManifest::all()
	 */
	private function manifest(): array {
		return ImageManifest::all();
	}
}
