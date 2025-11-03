/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['class'],
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#2563eb',
          foreground: '#ffffff',
        },
      },
    },
  },
  plugins: [],
};
