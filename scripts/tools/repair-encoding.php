<?php
/**
 * Repair UTF-8 that was corrupted by a Windows-1252 text round trip.
 *
 * Reading a UTF-8 file with a Windows-1251/1252 default and writing it back as
 * UTF-8 double-encodes every non-ASCII character. The damage is exactly
 * reversible, because after the round trip every non-ASCII character sits in
 * U+0080..U+00FF, which Windows-1252 encodes losslessly.
 *
 * Usage: php scripts/tools/repair-encoding.php <file> [<file> ...]
 *        php scripts/tools/repair-encoding.php --check <file> [<file> ...]
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

/**
 * Signatures of a double-encoded UTF-8 string.
 */
const DAMAGE = '/\xC3\xA2\xE2\x82\xAC|\xC3\x83|\xC3\x82\xC2|\xEF\xBF\xBD/';

/**
 * Count corrupted sequences in a file.
 */
function damageCount(string $path): int
{
	$bytes = (string) file_get_contents($path);

    if (! mb_check_encoding($bytes, 'UTF-8')) {
        return -1;
    }

    return (int) preg_match_all(DAMAGE, $bytes);
}

/**
 * Reverse the double encoding in a single line, if it is damaged.
 *
 * Repairing a whole file at once oscillates: a line that has already been
 * repaired holds a legitimate em dash, and re-encoding it to Windows-1252 turns
 * it into a different character. Working line by line means only genuinely
 * damaged lines are touched, so the repair converges instead of swapping one
 * broken encoding for another.
 */
function repairLine(string $line): string
{
	if (! preg_match(DAMAGE, $line)) {
		return $line;
	}

	$fixed = mb_convert_encoding($line, 'Windows-1252', 'UTF-8');

	if (! is_string($fixed) || '' === $fixed || ! mb_check_encoding($fixed, 'UTF-8')) {
		return $line;
	}

	// Only accept the result when it actually reduces the damage on this line.
	return preg_match(DAMAGE, $fixed) ? $line : $fixed;
}

/**
 * Reverse the double encoding in place.
 */
function repair(string $path): array
{
	$original = (string) file_get_contents($path);
	$before   = damageCount($path);

	if ($before <= 0) {
		return [$before, $before, strlen($original)];
	}

	$lines = explode("\n", $original);
	$fixed = implode("\n", array_map('repairLine', $lines));

	if ($fixed === $original) {
		return [$before, $before, strlen($original)];
	}

	file_put_contents($path, $fixed);

	return [$before, damageCount($path), strlen($original)];
}

$args = array_slice($argv, 1);
$check = in_array('--check', $args, true);
$files = array_values(array_filter($args, static fn (string $a): bool => ! str_starts_with($a, '--')));

if (! $files) {
    fwrite(STDERR, "Usage: php scripts/tools/repair-encoding.php [--check] <file> ...\n");
    exit(1);
}

$failed = 0;

foreach ($files as $file) {
    if (! is_file($file)) {
        printf("%-60s MISSING\n", $file);
        $failed++;
        continue;
    }

    if ($check) {
        $count = damageCount($file);
        printf("%-60s damage=%d\n", $file, $count);

        if ($count !== 0) {
            $failed++;
        }

        continue;
    }

    [$before, $after, $bytes] = repair($file);
    printf("%-60s damage %d -> %d  (%d bytes)\n", $file, $before, $after, $bytes);

    if ($after !== 0) {
        $failed++;
    }
}

exit($failed > 0 ? 1 : 0);
