const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',

        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    safelist: [
        'bg-emerald-500',
        'bg-red-500',
        'bg-gray-700', // Si usas este como color por defecto/fallback
        // Puedes añadir cualquier otra clase que se genere dinámicamente aquí
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Define tus colores personalizados aquí
                // Puedes darles nombres descriptivos o usar un sistema como 'primary', 'secondary', etc.
                'accent-blue': '#2089d8',      // Tu color principal azul
                'light-blue': '#d5f5ff',       // Tu color azul claro/fondo complementario
                'dark-navy': '#001c39',        // Tu color azul oscuro/casi negro
                'light-gray': '#e2e2e2',       // Tu color gris claro
            },
        },
    },

    plugins: [require('@tailwindcss/forms')],
};

