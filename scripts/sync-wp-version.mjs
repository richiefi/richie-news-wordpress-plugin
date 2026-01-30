#!/usr/bin/env node
/**
 * Syncs version across WordPress plugin files after release-it bump.
 *
 * Updates:
 * - PHP version constant (define statement)
 * - README.txt Stable tag
 * - README.txt changelog (from simplified CHANGELOG.md)
 *
 * Usage: node sync-wp-version.mjs <plugin-dir> <version>
 */

import fs from "fs";
import path from "path";

const [, , pluginDir, version] = process.argv;

if (!pluginDir || !version) {
  console.error("Usage: node sync-wp-version.mjs <plugin-dir> <version>");
  process.exit(1);
}

const pluginPath = path.resolve(process.cwd(), pluginDir);

// Format date as DD.MM.YYYY for WordPress
const date = new Date()
  .toLocaleDateString("en-GB", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  })
  .replace(/\//g, ".");

console.log(`Syncing ${pluginDir} to version ${version}`);

// 1. Update PHP constant in main plugin file
const pluginFiles = fs.readdirSync(pluginPath).filter((f) => f.endsWith(".php"));
const mainFile = pluginFiles.find((f) => {
  const content = fs.readFileSync(path.join(pluginPath, f), "utf8");
  return content.includes("Plugin Name:");
});

if (mainFile) {
  const phpPath = path.join(pluginPath, mainFile);
  let phpContent = fs.readFileSync(phpPath, "utf8");

  // Update define constant (matches: define( 'Richie_VERSION', '1.7.3' );)
  const beforeConst = phpContent;
  phpContent = phpContent.replace(
    /(define\(\s*'[A-Za-z_]+VERSION'\s*,\s*')[^']+('\s*\))/gi,
    `$1${version}$2`
  );

  if (beforeConst !== phpContent) {
    fs.writeFileSync(phpPath, phpContent);
    console.log(`  ✓ Updated ${mainFile} version constant`);
  }
}

// 2. Parse CHANGELOG.md for latest version entries
const changelogPath = path.join(pluginPath, "CHANGELOG.md");
let wpChangelogEntries = [];

if (fs.existsSync(changelogPath)) {
  const changelog = fs.readFileSync(changelogPath, "utf8");

  // Match simplified format: ## 1.8.0 (2026-01-29)
  const versionRegex = new RegExp(
    `## ${version.replace(/\./g, "\\.")}[^#]*`,
    "s"
  );
  const versionMatch = changelog.match(versionRegex);

  if (versionMatch) {
    const section = versionMatch[0];

    // Parse entries: * type: subject (handles "fix:", "fix(scope):", "Bug Fixes:" formats)
    const entries = section.match(/^\* .+$/gm) || [];
    wpChangelogEntries = entries.map((entry) => {
      // Match: * fix: msg OR * fix(scope): msg OR * Bug Fixes: msg
      const match = entry.match(/^\* ([\w\s]+)(?:\([^)]+\))?: (.+)$/);
      if (match) {
        const [, rawType, subject] = match;
        // Normalize type for WordPress format
        const typeMap = {
          fix: "Fix",
          feat: "Feature",
          "bug fixes": "Fix",
          features: "Feature",
          chore: "Chore",
          docs: "Docs",
          refactor: "Refactor",
          revert: "Revert",
          reverts: "Revert",
        };
        const normalizedType = rawType.toLowerCase().trim();
        const wpType = typeMap[normalizedType] || rawType;
        return `* ${wpType}: ${subject}`;
      }
      return entry;
    });
  }
  console.log(`  ✓ Parsed CHANGELOG.md (${wpChangelogEntries.length} entries)`);
}

// 3. Update README.txt
const readmePath = path.join(pluginPath, "README.txt");
if (fs.existsSync(readmePath)) {
  let readme = fs.readFileSync(readmePath, "utf8");

  // Update or add Stable tag
  if (readme.includes("Stable tag:")) {
    readme = readme.replace(/^Stable tag:.*/m, `Stable tag: ${version}`);
  } else {
    readme = readme.replace(/^(Tested up to:.*)/m, `$1\nStable tag: ${version}`);
  }

  // Extract manual entries from WIP section
  const wipMatch = readme.match(/= WIP =\n([\s\S]*?)(?=\n= \d|== |$)/);
  const wipEntries = wipMatch
    ? wipMatch[1].trim().split("\n").filter((line) => line.startsWith("*"))
    : [];

  // Combine: conventional commits + manual WIP entries
  const allEntries = [...wpChangelogEntries, ...wipEntries];

  // Build WordPress changelog entry
  const wpChangelog =
    allEntries.length > 0
      ? `= ${version} (${date}) =\n${allEntries.join("\n")}\n\n`
      : `= ${version} (${date}) =\n* Release ${version}\n\n`;

  // Remove old WIP section
  readme = readme.replace(/= WIP =\n[\s\S]*?(?=\n= \d|== |$)/, "");

  // Insert empty WIP section + new version after == Changelog ==
  readme = readme.replace(
    /(== Changelog ==\n)/,
    `$1= WIP =\n\n${wpChangelog}`
  );

  fs.writeFileSync(readmePath, readme);
  console.log(`  ✓ Updated README.txt`);
}

console.log(`\nDone! Version ${version} synced.`);
