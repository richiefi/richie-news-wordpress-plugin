module.exports = {
  // Don't use package.json for version
  npm: false,
  git: {
    commitMessage: "chore: release richie v${version}",
    tagName: "v${version}",
    tagAnnotation: "Release richie ${version}",
    requireCleanWorkingDir: true,
    push: true,
  },
  github: {
    release: true,
    releaseName: "Richie News ${version}",
    assets: ["releases/richie-news-${version}.zip"],
  },
  plugins: {
    "@release-it/bumper": {
      in: {
        file: "richie/composer.json",
        type: "application/json",
        path: "version",
      },
      out: [
        {
          file: "richie/composer.json",
          type: "application/json",
          path: "version",
        },
        {
          file: "richie/richie.php",
          type: "text/plain",
          search: "(Version:\\s*)([0-9.]+)",
          replace: "$1{{version}}",
        },
      ],
    },
    "@release-it/conventional-changelog": {
      preset: "conventionalcommits",
      infile: "richie/CHANGELOG.md",
      header: "# Changelog\n",
      writerOpts: {
        headerPartial: "\n## {{version}} ({{date}})\n",
        commitPartial: "* {{header}}\n",
        groupBy: false,
        commitsSort: ["subject"],
      },
    },
  },
  hooks: {
    "before:release": "npm run build:feed-editor && node scripts/sync-wp-version.mjs richie ${version} && npm run -w richie plugin-zip && mkdir -p releases && mv richie/richie.zip releases/richie-news-${version}.zip",
    "before:git:commit": "git add richie/admin/feed-editor/build",
  },
};
