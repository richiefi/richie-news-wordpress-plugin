module.exports = {
  git: {
    commitMessage: "chore: release richie v${version}",
    tagName: "richie-v${version}",
    tagAnnotation: "Release richie ${version}",
    requireCleanWorkingDir: true,
    push: true,
  },
  npm: {
    publish: false,
  },
  github: {
    release: true,
    releaseName: "Richie News ${version}",
    assets: ["releases/richie-${version}.zip"],
  },
  plugins: {
    "@release-it/bumper": {
      in: {
        file: "richie/richie.php",
        type: "text/php",
      },
      out: [
        {
          file: "richie/richie.php",
          type: "text/php",
        },
      ],
    },
    "@release-it/conventional-changelog": {
      preset: "conventionalcommits",
      infile: "richie/CHANGELOG.md",
      header: "# Changelog\n",
      writerOpts: {
        headerPartial: "## {{version}} ({{date}})\n\n",
        commitPartial: "* {{type}}: {{subject}}\n",
        groupBy: null,
        commitsSort: null,
      },
    },
  },
  hooks: {
    "after:bump": "node scripts/sync-wp-version.mjs richie ${version}",
    "before:release":
      "npm run plugin-zip && mkdir -p releases && mv richie/richie.zip releases/richie-${version}.zip",
  },
};
