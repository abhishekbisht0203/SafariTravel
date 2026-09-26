/**
 * One-off codemod for the Safari Travel stylesheets.
 *
 * 1. Autoprefixer is now enabled in the Vite pipeline, so hand-written
 *    vendor prefixes are removed.
 * 2. stylelint's `alpha-value-notation` wants a bare `0` rather than `0%`.
 * 3. Collapse adjacent `:not()` chains into the modern complex form.
 *
 * Run with: node scripts/codemod-css.mjs
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', 'theme', 'assets', 'src');

/**
 * Recursively collect every .css file under a directory.
 *
 * @param {string} dir Directory to walk.
 * @returns {string[]} Absolute file paths.
 */
function walk(dir) {
  return readdirSync(dir).flatMap((entry) => {
    const full = join(dir, entry);
    return statSync(full).isDirectory() ? walk(full) : full.endsWith('.css') ? [full] : [];
  });
}

let touched = 0;

walk(root).forEach((file) => {
  const before = readFileSync(file, 'utf8');

  let after = before
    // Autoprefixer handles these now.
    .replace(/^\s*-webkit-backdrop-filter:/gm, '  backdrop-filter:')
    .replace(/^\s*-webkit-background-clip:/gm, '  background-clip:')
    .replace(/^\s*-webkit-text-size-adjust:/gm, '  text-size-adjust:')
    .replace(/^\s*-webkit-font-smoothing:/gm, '  -webkit-font-smoothing:')
    // `rgb(0 0 0 / 0%)` -> `rgb(0 0 0 / 0)`
    .replace(/(\/ (?:0|100)%)/g, (_m, pct) => (pct === '/ 0%' ? '/ 0' : '/ 1'))
    // `.a:not(:focus):not(:active)` -> `.a:not(:focus, :active)`
    .replace(/:not\((:[a-z-]+)\):not\((:[a-z-]+)\)/g, ':not($1, $2)');

  if (after !== before) {
    writeFileSync(file, after, 'utf8');
    touched += 1;
    console.log(`updated ${file.replace(root, 'src')}`);
  }
});

console.log(`\n${touched} file(s) changed.`);
