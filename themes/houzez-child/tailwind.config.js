/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./template/**/*.php",
    "./template-parts/**/*.php",
    "./inc/**/*.php",
    "./assets/js/**/*.js",
    "./functions.php",
  ],
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        card: '0 1px 2px 0 rgba(15, 23, 42, 0.06), 0 1px 3px 0 rgba(15, 23, 42, 0.06)',
      },
    },
  },
  plugins: [],
};
