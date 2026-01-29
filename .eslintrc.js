module.exports = {
  extends: ["plugin:@wordpress/eslint-plugin/recommended"],
  rules: {
    "space-in-parens": 0,
  },
  overrides: [
    {
      files: ["scripts/**/*.mjs"],
      parserOptions: {
        sourceType: "module",
        ecmaVersion: 2022,
      },
    },
  ],
};