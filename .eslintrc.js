module.exports = {
  extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
  rules: {
    indent: 'off',
    'react/jsx-indent': 'off',
    'space-in-parens': 'off',
    'prettier/prettier': 'error',
    'no-console': 0,
  },
  overrides: [
    {
      files: [ 'src/**/*.{js,jsx}', 'richie/admin/feed-editor/src/**/*.{js,jsx}' ],
      env: {
        browser: true,
      },
      rules: { 'no-alert': 'off' },
    },
    {
      files: [ 'scripts/**/*.mjs' ],
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: 2022,
      },
    },
  ],
  settings: {
    'import/resolver': {
      node: {
        extensions: [ '.js', '.jsx', '.mjs', '.cjs' ],
      },
    },
  },
};
