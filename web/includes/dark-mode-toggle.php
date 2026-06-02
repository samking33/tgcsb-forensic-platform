<!-- Dark Mode Toggle Button and Script -->
<button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
    <i class="fas fa-moon"></i>
</button>

<script>
    // Dark Mode Toggle Functionality
    (function () {
        const toggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;
        const icon = toggle.querySelector('i');

        // Check for saved theme preference or default to light mode
        const currentTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', currentTheme);

        // Update icon based on current theme
        if (currentTheme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }

        // Toggle theme on button click
        toggle.addEventListener('click', function () {
            const theme = html.getAttribute('data-theme');
            const newTheme = theme === 'light' ? 'dark' : 'light';

            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);

            // Update icon with animation
            icon.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                if (newTheme === 'dark') {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                }
                icon.style.transform = 'rotate(0deg)';
            }, 150);
        });

        // Add transition to icon
        icon.style.transition = 'transform 0.3s ease';
    })();
</script>