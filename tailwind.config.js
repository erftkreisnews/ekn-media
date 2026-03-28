/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './resources/**/*.php',
    ],
    theme: {
        extend: {
            // EKN-Brand-Farben, benutzt in den Frontend-Templates via `bg-ekn-*`, `text-ekn-*`.
            colors: {
                ekn: {
                    50: '#F3F8FB',
                    200: '#D0DAE0',
                    800: '#0A3A5C',
                    900: '#092E48',
                },
            },
        },
    },
    plugins: [],
};

