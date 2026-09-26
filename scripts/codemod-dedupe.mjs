/**
 * Second codemod pass: remove declarations that became duplicates when the
 * hand-written vendor prefixes were stripped in favour of Autoprefixer.
 *
 * Run with: node scripts/codemod-dedupe.mjs
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

/**
 * Drop a declaration line when the previous non-blank declaration in the same
 * block has an identical property.
 *
 * @param {string} css Source CSS.
 * @returns {string} Cleaned CSS.
 */
function dedupe(css) {
  const lines = css.split('\n');
  const out = [];
  let lastProperty = '';
  let lastWasDeclaration = false;

  lines.forEach((line) => {
    const isDeclaration = /^\s{2}[a-z-]+:\s/.test(line) && !/^\s*\*/.test(line);

    if (isDeclaration) {
      const property = line.trim().split(':')[0];

      // A comment or a closing brace between them means it is a new block.
      if (lastWasDeclaration && property === lastProperty) {
        return;
      }

      lastProperty = property;
      lastWasDeclaration = true;
    } else if (line.trim() === '' || /^\s*\/\*/.test(line)) {
      // Keep state across comments, reset on block boundaries.
      if (/^\s*}\s*$/.test(line)) {
        lastWasDeclaration = false;
        lastProperty = '';
      }
    } else {
      lastWasDeclaration = false;
      lastProperty = '';
    }

    out.push(line);
  });

  return out.join('\n');
}

let touched = 0;

walk(root).forEach((file) => {
  const before = readFileSync(file, 'utf8');
  const after = dedupe(before);

  if (after !== before) {
    writeFileSync(file, after, 'utf8');
    touched += 1;
    console.log(`updated ${file.replace(root, 'src')}`);
  }
});

console.log(`\n${touched} file(s) changed.`);
