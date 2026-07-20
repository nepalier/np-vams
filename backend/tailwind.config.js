/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/js/**/*.{vue,ts}',
    './resources/views/**/*.blade.php',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#eef5f2', 100: '#d3e6de', 500: '#1f6f54', 600: '#185943', 700: '#124232',
        },
      },
    },
  },
  plugins: [],
};
